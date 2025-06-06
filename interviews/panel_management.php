<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/approval_engine.php';

// Check permission
requireRole('hiring_manager');

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_panel') {
        $interview_id = $_POST['interview_id'];
        $panel_name = trim($_POST['panel_name']);
        $panel_type = $_POST['panel_type'];
        $lead_interviewer_id = $_POST['lead_interviewer_id'];
        $scheduled_date = $_POST['scheduled_date'];
        $duration = $_POST['duration'];
        $location = $_POST['location'] ?? '';
        $meeting_link = $_POST['meeting_link'] ?? '';
        $panel_members = $_POST['panel_members'] ?? [];
        $member_roles = $_POST['member_roles'] ?? [];
        
        try {
            $conn->beginTransaction();
            
            // Create panel
            $panel_sql = "
                INSERT INTO interview_panels 
                (interview_id, panel_name, panel_type, lead_interviewer_id, scheduled_date, 
                 duration, location, meeting_link, evaluation_criteria)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $panel_stmt = $conn->prepare($panel_sql);
            $panel_stmt->execute([
                $interview_id, $panel_name, $panel_type, $lead_interviewer_id,
                $scheduled_date, $duration, $location, $meeting_link, '[]'
            ]);
            
            $panel_id = $conn->lastInsertId();
            
            // Add panel members
            foreach ($panel_members as $index => $member_id) {
                if (!empty($member_id)) {
                    $member_sql = "
                        INSERT INTO interview_panel_members 
                        (panel_id, interviewer_id, role, weight)
                        VALUES (?, ?, ?, ?)
                    ";
                    
                    $member_stmt = $conn->prepare($member_sql);
                    $member_stmt->execute([
                        $panel_id, 
                        $member_id, 
                        $member_roles[$index] ?? 'technical',
                        1.0
                    ]);
                }
            }
            
            // Update interview to link with panel
            $update_interview = $conn->prepare("UPDATE interviews SET panel_id = ?, interview_complexity = 'panel' WHERE id = ?");
            $update_interview->execute([$panel_id, $interview_id]);
            
            logActivity($_SESSION['user_id'], 'panel_created', 'interview_panel', $panel_id, 
                "Created panel interview: $panel_name");
            
            $conn->commit();
            $success = 'Panel interview created successfully. Notifications sent to all panel members.';
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error creating panel: ' . $e->getMessage();
        }
    }
}

// Get interviews eligible for panel creation
$eligible_interviews = $conn->query("
    SELECT i.*, c.first_name as candidate_first, c.last_name as candidate_last,
           j.title as job_title
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    WHERE i.status = 'scheduled' 
    AND i.panel_id IS NULL
    AND i.interview_type IN ('technical', 'in_person')
    ORDER BY i.scheduled_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get active panels
$active_panels = $conn->query("
    SELECT ip.*, i.scheduled_date as interview_date,
           c.first_name as candidate_first, c.last_name as candidate_last,
           j.title as job_title,
           u.first_name as lead_first, u.last_name as lead_last,
           COUNT(ipm.id) as member_count,
           SUM(CASE WHEN ipm.feedback_submitted = TRUE THEN 1 ELSE 0 END) as feedback_count
    FROM interview_panels ip
    JOIN interviews i ON ip.interview_id = i.id
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN users u ON ip.lead_interviewer_id = u.id
    LEFT JOIN interview_panel_members ipm ON ip.id = ipm.panel_id
    WHERE ip.status IN ('scheduled', 'in_progress')
    GROUP BY ip.id
    ORDER BY ip.scheduled_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get available interviewers
$interviewers = $conn->query("
    SELECT id, first_name, last_name, email, role, department
    FROM users 
    WHERE role IN ('interviewer', 'hiring_manager', 'technical_lead', 'admin')
    AND is_active = TRUE
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Interview Management - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Panel Interview Management</h1>
                        <p class="text-gray-600">Coordinate multi-interviewer panels for comprehensive candidate evaluation</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-list mr-2"></i>All Interviews
                        </a>
                        <button onclick="openCreatePanelModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-users mr-2"></i>Create Panel
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <!-- Panel Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-blue-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Active Panels</h3>
                            <p class="text-3xl font-bold text-blue-600"><?php echo count($active_panels); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock text-orange-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Pending Feedback</h3>
                            <p class="text-3xl font-bold text-orange-600">
                                <?php 
                                $pending_feedback = 0;
                                foreach ($active_panels as $panel) {
                                    $pending_feedback += ($panel['member_count'] - $panel['feedback_count']);
                                }
                                echo $pending_feedback;
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-calendar text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Eligible Interviews</h3>
                            <p class="text-3xl font-bold text-green-600"><?php echo count($eligible_interviews); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-tie text-purple-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Available Interviewers</h3>
                            <p class="text-3xl font-bold text-purple-600"><?php echo count($interviewers); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Panels -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Active Panel Interviews</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($active_panels)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">No active panel interviews found.</p>
                        <button onclick="openCreatePanelModal()" class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            Create First Panel
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Panel Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidate</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Members</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feedback Progress</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($active_panels as $panel): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($panel['panel_name']); ?></div>
                                            <div class="text-sm text-gray-500">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo ucfirst(str_replace('_', ' ', $panel['panel_type'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($panel['candidate_first'] . ' ' . $panel['candidate_last']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($panel['job_title']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M j, Y', strtotime($panel['scheduled_date'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('g:i A', strtotime($panel['scheduled_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-users text-gray-400 mr-2"></i>
                                            <span class="text-sm text-gray-900"><?php echo $panel['member_count']; ?> members</span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Lead: <?php echo htmlspecialchars($panel['lead_first'] . ' ' . $panel['lead_last']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $panel['member_count'] > 0 ? ($panel['feedback_count'] / $panel['member_count']) * 100 : 0; ?>%"></div>
                                            </div>
                                            <span class="text-sm text-gray-900"><?php echo $panel['feedback_count']; ?>/<?php echo $panel['member_count']; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="panel_details.php?id=<?php echo $panel['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Create Panel Modal -->
            <div id="createPanelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-y-auto">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Create Interview Panel</h3>
                            <button onclick="closeCreatePanelModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form method="POST" class="p-6">
                            <input type="hidden" name="action" value="create_panel">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="interview_id" class="block text-sm font-medium text-gray-700 mb-2">Interview</label>
                                    <select name="interview_id" id="interview_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">Select Interview</option>
                                        <?php foreach ($eligible_interviews as $interview): ?>
                                        <option value="<?php echo $interview['id']; ?>">
                                            <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last'] . ' - ' . $interview['job_title']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="panel_name" class="block text-sm font-medium text-gray-700 mb-2">Panel Name</label>
                                    <input type="text" name="panel_name" id="panel_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div>
                                    <label for="panel_type" class="block text-sm font-medium text-gray-700 mb-2">Panel Type</label>
                                    <select name="panel_type" id="panel_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="technical">Technical Panel</option>
                                        <option value="behavioral">Behavioral Panel</option>
                                        <option value="cultural">Cultural Fit Panel</option>
                                        <option value="final">Final Panel</option>
                                        <option value="executive">Executive Panel</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="lead_interviewer_id" class="block text-sm font-medium text-gray-700 mb-2">Lead Interviewer</label>
                                    <select name="lead_interviewer_id" id="lead_interviewer_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">Select Lead Interviewer</option>
                                        <?php foreach ($interviewers as $interviewer): ?>
                                        <option value="<?php echo $interviewer['id']; ?>">
                                            <?php echo htmlspecialchars($interviewer['first_name'] . ' ' . $interviewer['last_name'] . ' (' . $interviewer['role'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="scheduled_date" class="block text-sm font-medium text-gray-700 mb-2">Date & Time</label>
                                    <input type="datetime-local" name="scheduled_date" id="scheduled_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div>
                                    <label for="duration" class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes)</label>
                                    <input type="number" name="duration" id="duration" value="90" min="30" max="240" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                            </div>
                            
                            <!-- Panel Members -->
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Panel Members</label>
                                <div id="panelMembers">
                                    <div class="panel-member-row grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <select name="panel_members[]" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="">Select Member</option>
                                            <?php foreach ($interviewers as $interviewer): ?>
                                            <option value="<?php echo $interviewer['id']; ?>">
                                                <?php echo htmlspecialchars($interviewer['first_name'] . ' ' . $interviewer['last_name'] . ' (' . $interviewer['role'] . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="member_roles[]" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="technical">Technical Interviewer</option>
                                            <option value="behavioral">Behavioral Interviewer</option>
                                            <option value="observer">Observer</option>
                                            <option value="note_taker">Note Taker</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" onclick="addPanelMember()" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-plus mr-1"></i>Add Another Member
                                </button>
                            </div>
                            
                            <div class="mt-8 flex justify-end space-x-4">
                                <button type="button" onclick="closeCreatePanelModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                                    Create Panel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function openCreatePanelModal() {
            document.getElementById('createPanelModal').classList.remove('hidden');
        }
        
        function closeCreatePanelModal() {
            document.getElementById('createPanelModal').classList.add('hidden');
        }
        
        function addPanelMember() {
            const container = document.getElementById('panelMembers');
            const newRow = container.querySelector('.panel-member-row').cloneNode(true);
            newRow.querySelectorAll('select').forEach(select => select.value = '');
            container.appendChild(newRow);
        }
    </script>
</body>
</html>
