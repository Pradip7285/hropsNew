<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$candidate_id = $_GET['id'] ?? null;

if (!$candidate_id) {
    header('Location: list.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get candidate details
$stmt = $conn->prepare("
    SELECT c.*, j.title as job_title, j.department, u.first_name as recruiter_first, u.last_name as recruiter_last
    FROM candidates c
    LEFT JOIN job_postings j ON c.applied_for = j.id
    LEFT JOIN users u ON c.assigned_to = u.id
    WHERE c.id = ?
");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    header('Location: list.php');
    exit();
}

// Get interview history
$interviews_stmt = $conn->prepare("
    SELECT i.*, u.first_name as interviewer_first, u.last_name as interviewer_last
    FROM interviews i
    JOIN users u ON i.interviewer_id = u.id
    WHERE i.candidate_id = ?
    ORDER BY i.scheduled_date DESC
");
$interviews_stmt->execute([$candidate_id]);
$interviews = $interviews_stmt->fetchAll();

// Get offers
$offers_stmt = $conn->prepare("
    SELECT * FROM offers WHERE candidate_id = ? ORDER BY created_at DESC
");
$offers_stmt->execute([$candidate_id]);
$offers = $offers_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Candidate - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">
                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                        </h1>
                        <p class="text-gray-600">Candidate Profile</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="edit.php?id=<?php echo $candidate['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>Edit
                        </a>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Personal Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-user mr-2"></i>Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($candidate['email']); ?></p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($candidate['phone'] ?? 'Not provided'); ?></p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">LinkedIn</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?php if ($candidate['linkedin_url']): ?>
                                        <a href="<?php echo htmlspecialchars($candidate['linkedin_url']); ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                            <i class="fab fa-linkedin mr-1"></i>View Profile
                                        </a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Location</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($candidate['current_location'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-briefcase mr-2"></i>Professional Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Applied Position</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($candidate['job_title'] ?? 'No position selected'); ?></p>
                                <?php if ($candidate['department']): ?>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($candidate['department']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Experience</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?php echo $candidate['experience_years'] ? $candidate['experience_years'] . ' years' : 'Not specified'; ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Source</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($candidate['source'] ?? 'Not specified'); ?></p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">AI Score</label>
                                <div class="mt-1 flex items-center">
                                    <div class="bg-gray-200 rounded-full h-2 w-20 mr-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($candidate['ai_score'] * 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-900"><?php echo number_format($candidate['ai_score'] * 100, 1); ?>%</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($candidate['skills']): ?>
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Skills & Technologies</label>
                            <div class="flex flex-wrap gap-2">
                                <?php 
                                $skills = explode(',', $candidate['skills']);
                                foreach ($skills as $skill): 
                                    $skill = trim($skill);
                                    if ($skill):
                                ?>
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                        <?php echo htmlspecialchars($skill); ?>
                                    </span>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($candidate['resume_path']): ?>
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Resume</label>
                            <a href="../<?php echo htmlspecialchars($candidate['resume_path']); ?>" target="_blank" 
                               class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-lg hover:bg-green-200 transition duration-200">
                                <i class="fas fa-file-pdf mr-2"></i>View Resume
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Notes -->
                    <?php if ($candidate['notes']): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-sticky-note mr-2"></i>Notes
                        </h3>
                        <p class="text-sm text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($candidate['notes']); ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Interview History -->
                    <?php if (!empty($interviews)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-calendar-alt mr-2"></i>Interview History
                        </h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($interviews as $interview): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-medium text-gray-900"><?php echo ucfirst($interview['interview_type']); ?> Interview</h4>
                                        <p class="text-sm text-gray-600">
                                            with <?php echo htmlspecialchars($interview['interviewer_first'] . ' ' . $interview['interviewer_last']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo date('M j, Y \a\t g:i A', strtotime($interview['scheduled_date'])); ?>
                                        </p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        echo $interview['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                             ($interview['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); 
                                        ?>">
                                        <?php echo ucfirst($interview['status']); ?>
                                    </span>
                                </div>
                                <?php if ($interview['notes']): ?>
                                <p class="mt-2 text-sm text-gray-700"><?php echo htmlspecialchars($interview['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Offers -->
                    <?php if (!empty($offers)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-file-contract mr-2"></i>Job Offers
                        </h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($offers as $offer): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-medium text-gray-900">$<?php echo number_format($offer['salary_offered']); ?> Annual Salary</h4>
                                        <?php if ($offer['start_date']): ?>
                                        <p class="text-sm text-gray-600">Start Date: <?php echo date('M j, Y', strtotime($offer['start_date'])); ?></p>
                                        <?php endif; ?>
                                        <p class="text-sm text-gray-500">Created: <?php echo date('M j, Y', strtotime($offer['created_at'])); ?></p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        $status_colors = [
                                            'draft' => 'bg-yellow-100 text-yellow-800',
                                            'sent' => 'bg-blue-100 text-blue-800',
                                            'accepted' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        echo $status_colors[$offer['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>">
                                        <?php echo ucfirst($offer['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column - Status & Actions -->
                <div class="space-y-6">
                    <!-- Status Card -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Status</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Current Status</label>
                                <span class="mt-1 px-3 py-1 text-sm font-semibold rounded-full
                                    <?php
                                    $status_colors = [
                                        'new' => 'bg-gray-100 text-gray-800',
                                        'shortlisted' => 'bg-blue-100 text-blue-800',
                                        'interviewing' => 'bg-yellow-100 text-yellow-800',
                                        'offered' => 'bg-purple-100 text-purple-800',
                                        'hired' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800'
                                    ];
                                    echo $status_colors[$candidate['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?php echo ucfirst($candidate['status']); ?>
                                </span>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Applied Date</label>
                                <p class="mt-1 text-sm text-gray-900"><?php echo date('M j, Y', strtotime($candidate['created_at'])); ?></p>
                            </div>
                            
                            <?php if ($candidate['recruiter_first']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assigned Recruiter</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($candidate['recruiter_first'] . ' ' . $candidate['recruiter_last']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                        
                        <div class="space-y-2">
                            <a href="../interviews/schedule.php?candidate_id=<?php echo $candidate['id']; ?>" 
                               class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 block text-center">
                                <i class="fas fa-calendar-plus mr-2"></i>Schedule Interview
                            </a>
                            
                            <?php if ($candidate['status'] == 'interviewing'): ?>
                            <a href="../offers/create.php?candidate_id=<?php echo $candidate['id']; ?>" 
                               class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200 block text-center">
                                <i class="fas fa-file-contract mr-2"></i>Create Offer
                            </a>
                            <?php endif; ?>
                            
                            <button onclick="updateStatus('shortlisted')" 
                                    class="w-full bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-star mr-2"></i>Shortlist
                            </button>
                            
                            <button onclick="updateStatus('rejected')" 
                                    class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-times mr-2"></i>Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updateStatus(status) {
            if (confirm('Are you sure you want to update the candidate status to ' + status + '?')) {
                window.location.href = '<?php echo BASE_URL; ?>/candidates/update_status.php?id=<?php echo $candidate['id']; ?>&status=' + status;
            }
        }
    </script>
</body>
</html> 