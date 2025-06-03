<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

$employee_id = (int)($_GET['id'] ?? 0);

if (!$employee_id) {
    header('Location: list.php');
    exit;
}

$error = '';
$success = '';

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $status = $_POST['onboarding_status'];
            try {
                $update_fields = ['onboarding_status = ?'];
                $params = [$status];
                
                if ($status === 'in_progress' && !$employee['onboarding_start_date']) {
                    $update_fields[] = 'onboarding_start_date = NOW()';
                } elseif ($status === 'completed') {
                    $update_fields[] = 'onboarding_completion_date = NOW()';
                }
                
                $stmt = $conn->prepare("
                    UPDATE employees 
                    SET " . implode(', ', $update_fields) . "
                    WHERE id = ?
                ");
                $params[] = $employee_id;
                $stmt->execute($params);
                
                $success = "Employee onboarding status updated successfully.";
            } catch (Exception $e) {
                $error = 'Error updating status: ' . $e->getMessage();
            }
            break;
            
        case 'add_note':
            $note = trim($_POST['note']);
            if (!empty($note)) {
                try {
                    $current_notes = $employee['notes'] ? $employee['notes'] . "\n\n" : '';
                    $new_notes = $current_notes . "[" . date('Y-m-d H:i') . " - " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "]\n" . $note;
                    
                    $stmt = $conn->prepare("UPDATE employees SET notes = ? WHERE id = ?");
                    $stmt->execute([$new_notes, $employee_id]);
                    
                    $success = "Note added successfully.";
                } catch (Exception $e) {
                    $error = 'Error adding note: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Get employee details
$employee_stmt = $conn->prepare("
    SELECT e.*, 
           manager.first_name as manager_first, manager.last_name as manager_last,
           buddy.first_name as buddy_first, buddy.last_name as buddy_last,
           creator.first_name as creator_first, creator.last_name as creator_last,
           jp.title as job_title, jp.department as job_department
    FROM employees e
    LEFT JOIN users manager ON e.manager_id = manager.id
    LEFT JOIN users buddy ON e.buddy_id = buddy.id
    LEFT JOIN users creator ON e.created_by = creator.id
    LEFT JOIN job_postings jp ON e.job_id = jp.id
    WHERE e.id = ?
");
$employee_stmt->execute([$employee_id]);
$employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: list.php');
    exit;
}

// Get task statistics
$task_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status != 'completed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks,
        ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as completion_percentage
    FROM onboarding_tasks 
    WHERE employee_id = ?
");
$task_stats_stmt->execute([$employee_id]);
$task_stats = $task_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent tasks
$recent_tasks_stmt = $conn->prepare("
    SELECT *, 
           CASE 
               WHEN status != 'completed' AND due_date < CURDATE() THEN 1 
               ELSE 0 
           END as is_overdue
    FROM onboarding_tasks 
    WHERE employee_id = ? 
    ORDER BY 
        CASE status 
            WHEN 'pending' THEN 1 
            WHEN 'in_progress' THEN 2 
            WHEN 'completed' THEN 3 
            ELSE 4 
        END,
        due_date ASC 
    LIMIT 10
");
$recent_tasks_stmt->execute([$employee_id]);
$recent_tasks = $recent_tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get document statistics
$doc_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_documents,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending_documents,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents,
        SUM(CASE WHEN is_required = 1 AND status != 'approved' THEN 1 ELSE 0 END) as missing_required
    FROM onboarding_documents 
    WHERE employee_id = ?
");
$doc_stats_stmt->execute([$employee_id]);
$doc_stats = $doc_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent documents
$recent_docs_stmt = $conn->prepare("
    SELECT *,
           CASE 
               WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status IN ('pending', 'submitted') THEN 1 
               ELSE 0 
           END as is_overdue
    FROM onboarding_documents 
    WHERE employee_id = ? 
    ORDER BY is_required DESC, uploaded_at DESC 
    LIMIT 5
");
$recent_docs_stmt->execute([$employee_id]);
$recent_docs = $recent_docs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training statistics
$training_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_training,
        SUM(CASE WHEN et.status = 'completed' THEN 1 ELSE 0 END) as completed_training,
        SUM(CASE WHEN et.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_training,
        SUM(CASE WHEN et.status = 'not_started' THEN 1 ELSE 0 END) as not_started_training,
        AVG(CASE WHEN et.score IS NOT NULL THEN et.score ELSE NULL END) as avg_score
    FROM employee_training et
    JOIN training_modules tm ON et.training_module_id = tm.id
    WHERE et.employee_id = ?
");
$training_stats_stmt->execute([$employee_id]);
$training_stats = $training_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent training
$recent_training_stmt = $conn->prepare("
    SELECT et.*, tm.title, tm.module_type, tm.passing_score,
           CASE 
               WHEN et.due_date IS NOT NULL AND et.due_date < CURDATE() AND et.status NOT IN ('completed', 'skipped') THEN 1 
               ELSE 0 
           END as is_overdue
    FROM employee_training et
    JOIN training_modules tm ON et.training_module_id = tm.id
    WHERE et.employee_id = ? 
    ORDER BY et.assigned_date DESC 
    LIMIT 5
");
$recent_training_stmt->execute([$employee_id]);
$recent_training = $recent_training_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate days since start
$days_since_start = $employee['start_date'] ? max(0, (strtotime('now') - strtotime($employee['start_date'])) / (60 * 60 * 24)) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> - Employee Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="bg-blue-500 text-white w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold mr-4">
                            <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </h1>
                            <p class="text-gray-600"><?php echo htmlspecialchars($employee['position_title']); ?></p>
                            <p class="text-sm text-gray-500">Employee ID: <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="openStatusModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>Update Status
                        </button>
                        <a href="tasks.php?employee_id=<?php echo $employee_id; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-tasks mr-2"></i>View Tasks
                        </a>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
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

            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <!-- Onboarding Progress -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Onboarding Progress</h3>
                        <i class="fas fa-chart-pie text-blue-500"></i>
                    </div>
                    <div class="text-center">
                        <div class="relative inline-flex items-center justify-center w-20 h-20 mb-4">
                            <svg class="w-20 h-20 transform -rotate-90" viewBox="0 0 36 36">
                                <path class="text-gray-300" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 A 15.9155 15.9155 0 0 1 18 33.9155 A 15.9155 15.9155 0 0 1 18 2.0845"/>
                                <path class="text-blue-500" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="<?php echo $task_stats['completion_percentage']; ?>, 100" d="M18 2.0845 A 15.9155 15.9155 0 0 1 18 33.9155 A 15.9155 15.9155 0 0 1 18 2.0845"/>
                            </svg>
                            <span class="absolute text-lg font-bold text-gray-800"><?php echo $task_stats['completion_percentage']; ?>%</span>
                        </div>
                        <p class="text-sm text-gray-600"><?php echo $task_stats['completed_tasks']; ?>/<?php echo $task_stats['total_tasks']; ?> tasks completed</p>
                    </div>
                </div>

                <!-- Tasks Summary -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Tasks</h3>
                        <i class="fas fa-tasks text-green-500"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Completed</span>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm"><?php echo $task_stats['completed_tasks']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Pending</span>
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm"><?php echo $task_stats['pending_tasks']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Overdue</span>
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm"><?php echo $task_stats['overdue_tasks']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Documents Summary -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Documents</h3>
                        <i class="fas fa-file-alt text-purple-500"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Approved</span>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm"><?php echo $doc_stats['approved_documents']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Pending</span>
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm"><?php echo $doc_stats['pending_documents']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Missing Required</span>
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm"><?php echo $doc_stats['missing_required']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Training Summary -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Training</h3>
                        <i class="fas fa-graduation-cap text-orange-500"></i>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Completed</span>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm"><?php echo $training_stats['completed_training']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">In Progress</span>
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm"><?php echo $training_stats['in_progress_training']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Avg Score</span>
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">
                                <?php echo $training_stats['avg_score'] ? number_format($training_stats['avg_score'], 1) . '%' : 'N/A'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Recent Tasks -->
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Tasks</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recent_tasks)): ?>
                            <p class="text-gray-500 text-center py-8">No tasks assigned yet.</p>
                            <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_tasks as $task): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg <?php echo $task['is_overdue'] ? 'bg-red-50 border-red-200' : ''; ?>">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($task['task_name']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($task['category']); ?></p>
                                        <p class="text-xs text-gray-500">Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'in_progress' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'skipped' => 'bg-gray-100 text-gray-800'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_colors[$task['status']]; ?>">
                                            <?php echo ucfirst($task['status']); ?>
                                        </span>
                                        <?php if ($task['is_overdue']): ?>
                                        <div class="text-xs text-red-600 mt-1">Overdue</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Documents -->
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Documents</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recent_docs)): ?>
                            <p class="text-gray-500 text-center py-8">No documents uploaded yet.</p>
                            <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_docs as $doc): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($doc['document_name']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo ucfirst($doc['document_type']); ?></p>
                                        <?php if ($doc['uploaded_at']): ?>
                                        <p class="text-xs text-gray-500">Uploaded: <?php echo date('M j, Y', strtotime($doc['uploaded_at'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <?php
                                        $doc_status_colors = [
                                            'pending' => 'bg-gray-100 text-gray-800',
                                            'submitted' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $doc_status_colors[$doc['status']]; ?>">
                                            <?php echo ucfirst($doc['status']); ?>
                                        </span>
                                        <?php if ($doc['is_required']): ?>
                                        <div class="text-xs text-red-600 mt-1">Required</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Training -->
                    <div class="bg-white rounded-lg shadow-md">
                        <div class="p-6 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-800">Training Progress</h3>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recent_training)): ?>
                            <p class="text-gray-500 text-center py-8">No training assigned yet.</p>
                            <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_training as $training): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($training['title']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo ucfirst($training['module_type']); ?></p>
                                        <?php if ($training['score']): ?>
                                        <p class="text-xs text-gray-500">Score: <?php echo $training['score']; ?>%</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <?php
                                        $training_status_colors = [
                                            'not_started' => 'bg-gray-100 text-gray-800',
                                            'in_progress' => 'bg-yellow-100 text-yellow-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'failed' => 'bg-red-100 text-red-800'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $training_status_colors[$training['status']]; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $training['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Employee Info -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Employee Information</h3>
                        <div class="space-y-3">
                            <div>
                                <span class="text-sm font-medium text-gray-600">Status:</span>
                                <span class="ml-2 px-2 py-1 rounded-full text-xs font-medium 
                                    <?php 
                                    $status_colors = [
                                        'not_started' => 'bg-gray-100 text-gray-800',
                                        'in_progress' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'on_hold' => 'bg-orange-100 text-orange-800'
                                    ];
                                    echo $status_colors[$employee['onboarding_status']];
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $employee['onboarding_status'])); ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Department:</span>
                                <span class="ml-2 text-sm text-gray-800"><?php echo htmlspecialchars($employee['department']); ?></span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Start Date:</span>
                                <span class="ml-2 text-sm text-gray-800"><?php echo date('M j, Y', strtotime($employee['start_date'])); ?></span>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Days Since Start:</span>
                                <span class="ml-2 text-sm text-gray-800"><?php echo floor($days_since_start); ?> days</span>
                            </div>
                            <?php if ($employee['manager_first']): ?>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Manager:</span>
                                <span class="ml-2 text-sm text-gray-800"><?php echo htmlspecialchars($employee['manager_first'] . ' ' . $employee['manager_last']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($employee['buddy_first']): ?>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Buddy:</span>
                                <span class="ml-2 text-sm text-gray-800"><?php echo htmlspecialchars($employee['buddy_first'] . ' ' . $employee['buddy_last']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Work Arrangement:</span>
                                <span class="ml-2 text-sm text-gray-800"><?php echo ucfirst(str_replace('_', '-', $employee['work_arrangement'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="tasks.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block w-full text-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg transition duration-200">
                                <i class="fas fa-tasks mr-2"></i>Manage Tasks
                            </a>
                            <a href="documents.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block w-full text-center bg-purple-500 hover:bg-purple-600 text-white py-2 px-4 rounded-lg transition duration-200">
                                <i class="fas fa-file-alt mr-2"></i>View Documents
                            </a>
                            <a href="training.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block w-full text-center bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg transition duration-200">
                                <i class="fas fa-graduation-cap mr-2"></i>View Training
                            </a>
                            <button onclick="openNoteModal()" 
                                    class="block w-full text-center bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-4 rounded-lg transition duration-200">
                                <i class="fas fa-sticky-note mr-2"></i>Add Note
                            </button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Notes</h3>
                        <div class="max-h-64 overflow-y-auto">
                            <?php if ($employee['notes']): ?>
                            <pre class="text-sm text-gray-600 whitespace-pre-wrap"><?php echo htmlspecialchars($employee['notes']); ?></pre>
                            <?php else: ?>
                            <p class="text-gray-500 text-sm">No notes added yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Onboarding Status</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="onboarding_status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="not_started" <?php echo $employee['onboarding_status'] == 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo $employee['onboarding_status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $employee['onboarding_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="on_hold" <?php echo $employee['onboarding_status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeStatusModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="noteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Note</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_note">
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                        <textarea name="note" rows="4" required placeholder="Add your note here..." 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeNoteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Add Note
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openStatusModal() {
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function openNoteModal() {
            document.getElementById('noteModal').classList.remove('hidden');
        }

        function closeNoteModal() {
            document.getElementById('noteModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeStatusModal();
            }
        });

        document.getElementById('noteModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeNoteModal();
            }
        });
    </script>
</body>
</html> 