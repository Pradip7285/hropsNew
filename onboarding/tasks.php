<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_task':
            $employee_id = (int)$_POST['employee_id'];
            $task_name = trim($_POST['task_name']);
            $description = trim($_POST['description']);
            $category = $_POST['category'];
            $assigned_to = $_POST['assigned_to'];
            $priority = $_POST['priority'];
            $due_date = $_POST['due_date'] ?: null;
            
            if (empty($task_name)) {
                $error = 'Task name is required.';
            } else {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO onboarding_tasks (
                            employee_id, task_name, description, category, assigned_to, 
                            priority, due_date, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $employee_id, $task_name, $description, $category, $assigned_to,
                        $priority, $due_date, $_SESSION['user_id']
                    ]);
                    $success = "Task '$task_name' created successfully.";
                } catch (Exception $e) {
                    $error = 'Error creating task: ' . $e->getMessage();
                }
            }
            break;
            
        case 'update_task':
            $task_id = (int)$_POST['task_id'];
            $status = $_POST['status'];
            $completion_notes = trim($_POST['completion_notes']);
            
            try {
                $update_fields = ['status = ?'];
                $params = [$status];
                
                if ($status === 'completed') {
                    $update_fields[] = 'completed_at = NOW()';
                } elseif ($status === 'in_progress' && !$conn->query("SELECT started_at FROM onboarding_tasks WHERE id = $task_id")->fetch()['started_at']) {
                    $update_fields[] = 'started_at = NOW()';
                }
                
                if (!empty($completion_notes)) {
                    $update_fields[] = 'completion_notes = ?';
                    $params[] = $completion_notes;
                }
                
                $params[] = $task_id;
                
                $stmt = $conn->prepare("
                    UPDATE onboarding_tasks 
                    SET " . implode(', ', $update_fields) . "
                    WHERE id = ?
                ");
                $stmt->execute($params);
                
                $success = "Task status updated successfully.";
            } catch (Exception $e) {
                $error = 'Error updating task: ' . $e->getMessage();
            }
            break;
            
        case 'bulk_update':
            $task_ids = $_POST['task_ids'] ?? [];
            $bulk_action = $_POST['bulk_action'];
            
            if (empty($task_ids)) {
                $error = 'Please select at least one task.';
            } else {
                try {
                    $placeholders = str_repeat('?,', count($task_ids) - 1) . '?';
                    
                    switch ($bulk_action) {
                        case 'complete':
                            $stmt = $conn->prepare("
                                UPDATE onboarding_tasks 
                                SET status = 'completed', completed_at = NOW() 
                                WHERE id IN ($placeholders)
                            ");
                            $stmt->execute($task_ids);
                            $success = "Selected tasks marked as completed.";
                            break;
                            
                        case 'start':
                            $stmt = $conn->prepare("
                                UPDATE onboarding_tasks 
                                SET status = 'in_progress', started_at = COALESCE(started_at, NOW()) 
                                WHERE id IN ($placeholders)
                            ");
                            $stmt->execute($task_ids);
                            $success = "Selected tasks marked as in progress.";
                            break;
                            
                        case 'reset':
                            $stmt = $conn->prepare("
                                UPDATE onboarding_tasks 
                                SET status = 'pending', started_at = NULL, completed_at = NULL 
                                WHERE id IN ($placeholders)
                            ");
                            $stmt->execute($task_ids);
                            $success = "Selected tasks reset to pending.";
                            break;
                            
                        case 'delete':
                            $stmt = $conn->prepare("DELETE FROM onboarding_tasks WHERE id IN ($placeholders)");
                            $stmt->execute($task_ids);
                            $success = "Selected tasks deleted successfully.";
                            break;
                    }
                } catch (Exception $e) {
                    $error = 'Error performing bulk action: ' . $e->getMessage();
                }
            }
            break;
            
        case 'delete_task':
            $task_id = (int)$_POST['task_id'];
            try {
                $conn->prepare("DELETE FROM onboarding_tasks WHERE id = ?")->execute([$task_id]);
                $success = "Task deleted successfully.";
            } catch (Exception $e) {
                $error = 'Error deleting task: ' . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$employee_filter = $_GET['employee_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$assigned_to_filter = $_GET['assigned_to'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$overdue_filter = $_GET['overdue'] ?? '';

// Build where conditions
$where_conditions = ['1=1'];
$params = [];

if (!empty($employee_filter)) {
    $where_conditions[] = "t.employee_id = ?";
    $params[] = $employee_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "t.category = ?";
    $params[] = $category_filter;
}

if (!empty($assigned_to_filter)) {
    $where_conditions[] = "t.assigned_to = ?";
    $params[] = $assigned_to_filter;
}

if (!empty($priority_filter)) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

if ($overdue_filter === '1') {
    $where_conditions[] = "t.due_date < CURDATE() AND t.status != 'completed'";
}

$where_clause = implode(" AND ", $where_conditions);

// Get tasks with employee details
$tasks_query = "
    SELECT t.*, 
           e.first_name, e.last_name, e.employee_id as emp_id, e.department,
           creator.first_name as creator_first, creator.last_name as creator_last,
           CASE 
               WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() AND t.status != 'completed' THEN 1
               ELSE 0
           END as is_overdue,
           DATEDIFF(CURDATE(), t.created_at) as days_since_created
    FROM onboarding_tasks t
    JOIN employees e ON t.employee_id = e.id
    LEFT JOIN users creator ON t.created_by = creator.id
    WHERE $where_clause
    ORDER BY t.is_overdue DESC, t.priority DESC, t.due_date ASC, t.created_at DESC
";

$tasks_stmt = $conn->prepare($tasks_query);
$tasks_stmt->execute($params);
$tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for task creation
$employees_stmt = $conn->query("
    SELECT id, first_name, last_name, employee_id, department
    FROM employees 
    WHERE onboarding_status IN ('in_progress', 'not_started')
    ORDER BY first_name, last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get task statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_tasks,
        SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks,
        ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as completion_rate
    FROM onboarding_tasks t
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get unique categories and assignments
$categories = ['documentation', 'equipment', 'training', 'orientation', 'compliance', 'social', 'other'];
$assignments = ['employee', 'hr', 'manager', 'buddy', 'it'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Task Management</h1>
                        <p class="text-gray-600">Manage and track onboarding tasks across all employees</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="openCreateTaskModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create Task
                        </button>
                        <button onclick="showBulkActions()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-check-double mr-2"></i>Bulk Actions
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-users mr-2"></i>Back to Employees
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

            <!-- Task Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-7 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-tasks text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_tasks']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-gray-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['pending_tasks']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-play text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">In Progress</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['in_progress_tasks']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['completed_tasks']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-gray-100 p-2 rounded-full mr-3">
                            <i class="fas fa-forward text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Skipped</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['skipped_tasks']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['overdue_tasks']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-2 rounded-full mr-3">
                            <i class="fas fa-percentage text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completion</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['completion_rate']; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-7 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
                        <select name="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="skipped" <?php echo $status_filter == 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category; ?>" <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                                <?php echo ucfirst($category); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                        <select name="assigned_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Assignments</option>
                            <?php foreach ($assignments as $assignment): ?>
                            <option value="<?php echo $assignment; ?>" <?php echo $assigned_to_filter == $assignment ? 'selected' : ''; ?>>
                                <?php echo ucfirst($assignment); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Priorities</option>
                            <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Overdue</label>
                        <select name="overdue" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Tasks</option>
                            <option value="1" <?php echo $overdue_filter == '1' ? 'selected' : ''; ?>>Overdue Only</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="tasks.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions Bar (Hidden by default) -->
            <div id="bulkActionsBar" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="action" value="bulk_update">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-700">
                                <span id="selectedCount">0</span> task(s) selected
                            </span>
                            <select name="bulk_action" required class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select action...</option>
                                <option value="complete">Mark as Completed</option>
                                <option value="start">Mark as In Progress</option>
                                <option value="reset">Reset to Pending</option>
                                <option value="delete">Delete Tasks</option>
                            </select>
                        </div>
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                Apply
                            </button>
                            <button type="button" onclick="hideBulkActions()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tasks Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-tasks text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No tasks found</p>
                                    <p class="text-sm">Create tasks to start tracking onboarding progress.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                            <tr class="hover:bg-gray-50 <?php echo $task['is_overdue'] ? 'bg-red-50' : ''; ?>">
                                <td class="px-6 py-4">
                                    <input type="checkbox" name="task_ids[]" value="<?php echo $task['id']; ?>" class="task-checkbox" onchange="updateSelectedCount()">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold mr-3">
                                            <?php echo strtoupper(substr($task['first_name'], 0, 1) . substr($task['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($task['emp_id']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($task['department']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($task['task_name']); ?></div>
                                    <?php if ($task['description']): ?>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : ''); ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full"><?php echo ucfirst($task['category']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'in_progress' => 'bg-blue-100 text-blue-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'skipped' => 'bg-gray-100 text-gray-800'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_colors[$task['status']]; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                    <?php if ($task['is_overdue']): ?>
                                    <div class="text-xs text-red-600 mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $priority_colors = [
                                        'high' => 'bg-red-100 text-red-800',
                                        'medium' => 'bg-yellow-100 text-yellow-800',
                                        'low' => 'bg-green-100 text-green-800'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $priority_colors[$task['priority']]; ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($task['due_date']): ?>
                                        <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">No due date</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded-full">
                                        <?php echo ucfirst($task['assigned_to']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button onclick="updateTaskStatus(<?php echo $task['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="viewTaskDetails(<?php echo $task['id']; ?>)" 
                                                class="text-purple-600 hover:text-purple-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($task['status'] == 'pending'): ?>
                                        <button onclick="startTask(<?php echo $task['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900" title="Start Task">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="deleteTask(<?php echo $task['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900" title="Delete Task">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Task Modal -->
    <div id="createTaskModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Task</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_task">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                            <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select employee...</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>"><?php echo ucfirst($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Task Name *</label>
                        <input type="text" name="task_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                            <select name="assigned_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <?php foreach ($assignments as $assignment): ?>
                                <option value="<?php echo $assignment; ?>"><?php echo ucfirst($assignment); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                            <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeCreateTaskModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Task Status Modal -->
    <div id="updateTaskModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Task Status</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="task_id" id="update_task_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="skipped">Skipped</option>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Completion Notes</label>
                        <textarea name="completion_notes" rows="3" placeholder="Optional notes about task completion..." 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeUpdateTaskModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
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

    <script>
        function openCreateTaskModal() {
            document.getElementById('createTaskModal').classList.remove('hidden');
        }

        function closeCreateTaskModal() {
            document.getElementById('createTaskModal').classList.add('hidden');
        }

        function updateTaskStatus(taskId) {
            document.getElementById('update_task_id').value = taskId;
            document.getElementById('updateTaskModal').classList.remove('hidden');
        }

        function closeUpdateTaskModal() {
            document.getElementById('updateTaskModal').classList.add('hidden');
        }

        function viewTaskDetails(taskId) {
            // Placeholder for task details view
            alert('Task details view - Feature to be implemented');
        }

        function startTask(taskId) {
            if (confirm('Mark this task as started?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="task_id" value="${taskId}">
                    <input type="hidden" name="status" value="in_progress">
                    <input type="hidden" name="completion_notes" value="Task started">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteTask(taskId) {
            if (confirm('Are you sure you want to delete this task?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" value="${taskId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showBulkActions() {
            document.getElementById('bulkActionsBar').classList.remove('hidden');
        }

        function hideBulkActions() {
            document.getElementById('bulkActionsBar').classList.add('hidden');
            document.getElementById('selectAll').checked = false;
            document.querySelectorAll('.task-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.task-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.task-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = checked;
            
            // Update bulk form with selected task IDs
            const bulkForm = document.getElementById('bulkForm');
            const existingInputs = bulkForm.querySelectorAll('input[name="task_ids[]"]');
            existingInputs.forEach(input => input.remove());
            
            document.querySelectorAll('.task-checkbox:checked').forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'task_ids[]';
                input.value = cb.value;
                bulkForm.appendChild(input);
            });
        }

        // Close modals when clicking outside
        ['createTaskModal', 'updateTaskModal'].forEach(modalId => {
            document.getElementById(modalId).addEventListener('click', function(event) {
                if (event.target === this) {
                    this.classList.add('hidden');
                }
            });
        });

        // Initialize task checkbox event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.task-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });
        });
    </script>
</body>
</html> 