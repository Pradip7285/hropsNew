<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_pip_status':
            $stmt = $conn->prepare("UPDATE performance_improvement_plans SET status = ? WHERE id = ?");
            $result = $stmt->execute([$_POST['status'], $_POST['pip_id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'add_progress_note':
            $stmt = $conn->prepare("
                INSERT INTO pip_progress_notes (pip_id, note_text, added_by, note_type)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $_POST['pip_id'], $_POST['note_text'], $_SESSION['user_id'], $_POST['note_type']
            ]);
            echo json_encode(['success' => $result, 'message' => 'Progress note added successfully']);
            exit;
            
        case 'update_milestone_status':
            $stmt = $conn->prepare("
                UPDATE pip_milestones 
                SET status = ?, completion_date = ?, notes = ?
                WHERE id = ?
            ");
            $completion_date = $_POST['status'] == 'completed' ? date('Y-m-d') : null;
            $result = $stmt->execute([
                $_POST['status'], $completion_date, $_POST['notes'], $_POST['milestone_id']
            ]);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$employee_filter = $_GET['employee'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "pip.status = ?";
    $params[] = $status_filter;
}
if (!empty($severity_filter)) {
    $where_conditions[] = "pip.severity_level = ?";
    $params[] = $severity_filter;
}
if (!empty($employee_filter)) {
    $where_conditions[] = "pip.employee_id = ?";
    $params[] = $employee_filter;
}
if (!empty($search)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR pip.plan_title LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$where_clause = implode(" AND ", $where_conditions);

// Get PIPs with detailed information
$pips_query = "
    SELECT pip.*, 
           e.first_name, e.last_name, e.employee_id, e.department, e.position,
           cb.first_name as created_by_first_name, cb.last_name as created_by_last_name,
           s.first_name as supervisor_first_name, s.last_name as supervisor_last_name,
           (SELECT COUNT(*) FROM pip_milestones WHERE pip_id = pip.id) as total_milestones,
           (SELECT COUNT(*) FROM pip_milestones WHERE pip_id = pip.id AND status = 'completed') as completed_milestones,
           (SELECT COUNT(*) FROM pip_progress_notes WHERE pip_id = pip.id) as total_notes,
           CASE 
               WHEN pip.end_date < CURDATE() AND pip.status = 'active' THEN 'overdue'
               WHEN pip.end_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND pip.status = 'active' THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status,
           DATEDIFF(pip.end_date, CURDATE()) as days_remaining
    FROM performance_improvement_plans pip
    JOIN employees e ON pip.employee_id = e.id
    LEFT JOIN users cb ON pip.created_by = cb.id
    LEFT JOIN employees s ON pip.supervisor_id = s.id
    WHERE $where_clause
    ORDER BY pip.created_at DESC
";

$pips_stmt = $conn->prepare($pips_query);
$pips_stmt->execute($params);
$pips = $pips_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$employees_stmt = $conn->query("
    SELECT id, first_name, last_name, employee_id, department 
    FROM employees 
    WHERE status = 'active' 
    ORDER BY first_name, last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_pips,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_pips,
        SUM(CASE WHEN status = 'completed_successful' THEN 1 ELSE 0 END) as successful_pips,
        SUM(CASE WHEN status = 'completed_unsuccessful' THEN 1 ELSE 0 END) as unsuccessful_pips,
        SUM(CASE WHEN end_date < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as overdue_pips,
        ROUND(AVG(
            (SELECT COUNT(*) FROM pip_milestones WHERE pip_id = pip.id AND status = 'completed') * 100.0 / 
            NULLIF((SELECT COUNT(*) FROM pip_milestones WHERE pip_id = pip.id), 0)
        ), 2) as avg_completion_rate
    FROM performance_improvement_plans pip
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Improvement Plans - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Performance Improvement Plans</h1>
                        <p class="text-gray-600">Manage corrective action plans and performance improvements</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="create_pip.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create PIP
                        </a>
                        <a href="pip_templates.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-file-alt mr-2"></i>PIP Templates
                        </a>
                        <a href="pip_analytics.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-chart-bar mr-2"></i>Analytics
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-6 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-gray-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clipboard-list text-gray-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total PIPs</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_pips']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-3 rounded-full mr-4">
                            <i class="fas fa-play-circle text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Active PIPs</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_pips']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Successful</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['successful_pips']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Unsuccessful</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['unsuccessful_pips']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['overdue_pips']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-percentage text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg. Progress</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['avg_completion_rate'] ?? 0; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search PIPs or employees..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="completed_successful" <?php echo $status_filter == 'completed_successful' ? 'selected' : ''; ?>>Successful</option>
                                <option value="completed_unsuccessful" <?php echo $status_filter == 'completed_unsuccessful' ? 'selected' : ''; ?>>Unsuccessful</option>
                                <option value="terminated" <?php echo $status_filter == 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Severity</label>
                            <select name="severity" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Severities</option>
                                <option value="low" <?php echo $severity_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $severity_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $severity_filter == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="critical" <?php echo $severity_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
                            <select name="employee" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['department'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <div class="flex space-x-2 w-full">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 flex-1">
                                    <i class="fas fa-search mr-2"></i>Search
                                </button>
                                <a href="improvement.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- PIPs List -->
            <div class="space-y-4">
                <?php if (empty($pips)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-clipboard-check text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Performance Improvement Plans</h3>
                    <p class="text-gray-500">No PIPs match your current filters.</p>
                    <a href="create_pip.php" class="mt-4 inline-block bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create First PIP
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($pips as $pip): ?>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 
                    <?php 
                    switch($pip['severity_level']) {
                        case 'critical': echo 'border-red-600'; break;
                        case 'high': echo 'border-red-400'; break;
                        case 'medium': echo 'border-yellow-400'; break;
                        default: echo 'border-green-400';
                    }
                    ?>">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 mr-3">
                                    <?php echo htmlspecialchars($pip['plan_title']); ?>
                                </h3>
                                
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($pip['status']) {
                                        case 'active': echo 'bg-orange-100 text-orange-800'; break;
                                        case 'completed_successful': echo 'bg-green-100 text-green-800'; break;
                                        case 'completed_unsuccessful': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $pip['status'])); ?>
                                </span>

                                <span class="ml-2 px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($pip['severity_level']) {
                                        case 'critical': echo 'bg-red-100 text-red-800'; break;
                                        case 'high': echo 'bg-red-100 text-red-700'; break;
                                        case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                        default: echo 'bg-green-100 text-green-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst($pip['severity_level']); ?> Priority
                                </span>

                                <?php if ($pip['urgency_status'] == 'overdue'): ?>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </span>
                                <?php elseif ($pip['urgency_status'] == 'due_soon'): ?>
                                    <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Due Soon
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <span class="text-sm text-gray-500">Employee</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($pip['first_name'] . ' ' . $pip['last_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pip['employee_id'] . ' - ' . $pip['department']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Supervisor</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($pip['supervisor_first_name'] . ' ' . $pip['supervisor_last_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pip['position']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Milestones Progress</span>
                                    <p class="font-medium"><?php echo $pip['completed_milestones']; ?>/<?php echo $pip['total_milestones']; ?> completed</p>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <?php $completion_rate = $pip['total_milestones'] > 0 ? ($pip['completed_milestones'] / $pip['total_milestones']) * 100 : 0; ?>
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">End Date</span>
                                    <p class="font-medium <?php echo $pip['urgency_status'] == 'overdue' ? 'text-red-600' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($pip['end_date'])); ?>
                                    </p>
                                    <?php if ($pip['days_remaining'] > 0): ?>
                                        <p class="text-sm text-gray-600"><?php echo $pip['days_remaining']; ?> days left</p>
                                    <?php elseif ($pip['days_remaining'] < 0): ?>
                                        <p class="text-sm text-red-600"><?php echo abs($pip['days_remaining']); ?> days overdue</p>
                                    <?php else: ?>
                                        <p class="text-sm text-yellow-600">Due today</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Performance Issues -->
                            <?php if ($pip['performance_issues']): ?>
                            <div class="mb-3 p-3 bg-red-50 rounded-lg">
                                <span class="text-sm font-medium text-red-800">Performance Issues:</span>
                                <p class="text-sm text-red-700 mt-1"><?php echo htmlspecialchars($pip['performance_issues']); ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Expected Outcomes -->
                            <?php if ($pip['expected_outcomes']): ?>
                            <div class="mb-3 p-3 bg-green-50 rounded-lg">
                                <span class="text-sm font-medium text-green-800">Expected Outcomes:</span>
                                <p class="text-sm text-green-700 mt-1"><?php echo htmlspecialchars($pip['expected_outcomes']); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="text-sm text-gray-600">
                                <i class="fas fa-user mr-1"></i>
                                Created by: <?php echo htmlspecialchars($pip['created_by_first_name'] . ' ' . $pip['created_by_last_name']); ?>
                                <span class="ml-4">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Start: <?php echo date('M j, Y', strtotime($pip['start_date'])); ?>
                                </span>
                                <span class="ml-4">
                                    <i class="fas fa-sticky-note mr-1"></i>
                                    Notes: <?php echo $pip['total_notes']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="ml-6 flex flex-col space-y-2">
                            <a href="view_pip.php?id=<?php echo $pip['id']; ?>" 
                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </a>
                            
                            <?php if ($pip['status'] == 'active'): ?>
                            <a href="edit_pip.php?id=<?php echo $pip['id']; ?>" 
                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                <i class="fas fa-edit mr-1"></i>Edit PIP
                            </a>
                            
                            <button onclick="addProgressNote(<?php echo $pip['id']; ?>)" 
                                    class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-plus mr-1"></i>Add Note
                            </button>
                            <?php endif; ?>
                            
                            <button onclick="updatePIPStatus(<?php echo $pip['id']; ?>)" 
                                    class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-tasks mr-1"></i>Update Status
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Update PIP Status</h3>
                    <button onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="statusForm" class="space-y-4">
                    <input type="hidden" id="statusPipId" name="pip_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select id="newStatus" name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="active">Active</option>
                            <option value="completed_successful">Completed Successfully</option>
                            <option value="completed_unsuccessful">Completed Unsuccessfully</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeStatusModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Progress Note Modal -->
    <div id="noteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Add Progress Note</h3>
                    <button onclick="closeNoteModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="noteForm" class="space-y-4">
                    <input type="hidden" id="notePipId" name="pip_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Note Type</label>
                        <select name="note_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="progress">Progress Update</option>
                            <option value="concern">Concern</option>
                            <option value="achievement">Achievement</option>
                            <option value="meeting">Meeting Summary</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                        <textarea name="note_text" rows="4" required 
                                  placeholder="Enter your progress note..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeNoteModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Add Note
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updatePIPStatus(pipId) {
            document.getElementById('statusPipId').value = pipId;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function addProgressNote(pipId) {
            document.getElementById('notePipId').value = pipId;
            document.getElementById('noteModal').classList.remove('hidden');
        }

        function closeNoteModal() {
            document.getElementById('noteModal').classList.add('hidden');
        }

        // Status form submission
        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_pip_status');

            fetch('improvement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating status');
                }
            });
        });

        // Note form submission
        document.getElementById('noteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_progress_note');

            fetch('improvement.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeNoteModal();
                    location.reload();
                } else {
                    alert('Error adding note');
                }
            });
        });

        // Close modals when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });

        document.getElementById('noteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNoteModal();
            }
        });
    </script>
</body>
</html> 