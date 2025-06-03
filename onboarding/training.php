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

// Handle training actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_module':
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $content = trim($_POST['content']);
            $module_type = $_POST['module_type'];
            $category = trim($_POST['category']);
            $duration_minutes = (int)$_POST['duration_minutes'];
            $is_required = isset($_POST['is_required']);
            $passing_score = (int)$_POST['passing_score'];
            $content_url = trim($_POST['content_url']);
            
            if (empty($title)) {
                $error = 'Training module title is required.';
            } else {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO training_modules (
                            title, description, content, module_type, category, duration_minutes,
                            is_required, passing_score, content_url, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title, $description, $content, $module_type, $category, $duration_minutes,
                        $is_required, $passing_score, $content_url, $_SESSION['user_id']
                    ]);
                    $success = "Training module '$title' created successfully.";
                } catch (Exception $e) {
                    $error = 'Error creating training module: ' . $e->getMessage();
                }
            }
            break;
            
        case 'assign_training':
            $employee_id = (int)$_POST['employee_id'];
            $module_ids = $_POST['module_ids'] ?? [];
            $due_date = $_POST['due_date'] ?: null;
            
            if (empty($module_ids)) {
                $error = 'Please select at least one training module.';
            } else {
                try {
                    foreach ($module_ids as $module_id) {
                        // Check if already assigned
                        $check_stmt = $conn->prepare("
                            SELECT id FROM employee_training 
                            WHERE employee_id = ? AND training_module_id = ?
                        ");
                        $check_stmt->execute([$employee_id, $module_id]);
                        
                        if (!$check_stmt->fetch()) {
                            $stmt = $conn->prepare("
                                INSERT INTO employee_training (
                                    employee_id, training_module_id, assigned_date, due_date
                                ) VALUES (?, ?, CURDATE(), ?)
                            ");
                            $stmt->execute([$employee_id, $module_id, $due_date]);
                        }
                    }
                    $success = "Training modules assigned successfully.";
                } catch (Exception $e) {
                    $error = 'Error assigning training: ' . $e->getMessage();
                }
            }
            break;
            
        case 'update_progress':
            $training_id = (int)$_POST['training_id'];
            $status = $_POST['status'];
            $score = $_POST['score'] ? (int)$_POST['score'] : null;
            $time_spent = $_POST['time_spent'] ? (int)$_POST['time_spent'] : 0;
            $notes = trim($_POST['notes']);
            
            try {
                $update_fields = ['status = ?', 'notes = ?'];
                $params = [$status, $notes];
                
                if ($status === 'completed') {
                    $update_fields[] = 'completed_at = NOW()';
                    if ($score !== null) {
                        $update_fields[] = 'score = ?';
                        $params[] = $score;
                    }
                } elseif ($status === 'in_progress' && !$conn->query("SELECT started_at FROM employee_training WHERE id = $training_id")->fetch()['started_at']) {
                    $update_fields[] = 'started_at = NOW()';
                }
                
                if ($time_spent > 0) {
                    $update_fields[] = 'time_spent_minutes = time_spent_minutes + ?';
                    $params[] = $time_spent;
                }
                
                $params[] = $training_id;
                
                $stmt = $conn->prepare("
                    UPDATE employee_training 
                    SET " . implode(', ', $update_fields) . "
                    WHERE id = ?
                ");
                $stmt->execute($params);
                
                $success = "Training progress updated successfully.";
            } catch (Exception $e) {
                $error = 'Error updating progress: ' . $e->getMessage();
            }
            break;
            
        case 'delete_module':
            $module_id = (int)$_POST['module_id'];
            try {
                $conn->prepare("DELETE FROM training_modules WHERE id = ?")->execute([$module_id]);
                $success = "Training module deleted successfully.";
            } catch (Exception $e) {
                $error = 'Error deleting module: ' . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$employee_filter = $_GET['employee_id'] ?? '';
$module_filter = $_GET['module_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Build training assignments query
$where_conditions = ['1=1'];
$params = [];

if (!empty($employee_filter)) {
    $where_conditions[] = "et.employee_id = ?";
    $params[] = $employee_filter;
}

if (!empty($module_filter)) {
    $where_conditions[] = "et.training_module_id = ?";
    $params[] = $module_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "et.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "tm.category = ?";
    $params[] = $category_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get training assignments with progress
$assignments_query = "
    SELECT et.*, 
           e.first_name, e.last_name, e.employee_id as emp_id, e.department,
           tm.title, tm.module_type, tm.category, tm.duration_minutes, tm.passing_score,
           CASE 
               WHEN et.due_date IS NOT NULL AND et.due_date < CURDATE() AND et.status NOT IN ('completed', 'skipped') THEN 1
               ELSE 0
           END as is_overdue,
           DATEDIFF(CURDATE(), et.assigned_date) as days_assigned
    FROM employee_training et
    JOIN employees e ON et.employee_id = e.id
    JOIN training_modules tm ON et.training_module_id = tm.id
    WHERE $where_clause
    ORDER BY et.is_overdue DESC, et.due_date ASC, et.assigned_date DESC
";

$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->execute($params);
$assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training modules
$modules_stmt = $conn->query("
    SELECT tm.*, 
           u.first_name as creator_first, u.last_name as creator_last,
           COUNT(et.id) as assigned_count,
           SUM(CASE WHEN et.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM training_modules tm
    LEFT JOIN users u ON tm.created_by = u.id
    LEFT JOIN employee_training et ON tm.id = et.training_module_id
    WHERE tm.is_active = 1
    GROUP BY tm.id
    ORDER BY tm.category, tm.title
");
$modules = $modules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for assignment
$employees_stmt = $conn->query("
    SELECT id, first_name, last_name, employee_id, department
    FROM employees 
    WHERE onboarding_status IN ('in_progress', 'not_started')
    ORDER BY first_name, last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_assignments,
        SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ('completed', 'skipped') THEN 1 ELSE 0 END) as overdue
    FROM employee_training et
    JOIN training_modules tm ON et.training_module_id = tm.id
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories
$categories_stmt = $conn->query("SELECT DISTINCT category FROM training_modules WHERE category IS NOT NULL ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Modules - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Training Modules</h1>
                        <p class="text-gray-600">Manage employee training content and track progress</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="openCreateModuleModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create Module
                        </button>
                        <button onclick="openAssignTrainingModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-user-plus mr-2"></i>Assign Training
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

            <!-- Training Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-graduation-cap text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_assignments']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-gray-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Not Started</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['not_started']; ?></p>
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
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['in_progress']; ?></p>
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
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['completed']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Failed</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['failed']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-orange-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['overdue']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Training Module</label>
                        <select name="module_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Modules</option>
                            <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module['id']; ?>" <?php echo $module_filter == $module['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($module['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="not_started" <?php echo $status_filter == 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="skipped" <?php echo $status_filter == 'skipped' ? 'selected' : ''; ?>>Skipped</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($category)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="training.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Training Assignments List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Training Module</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Spent</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($assignments)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-graduation-cap text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No training assignments found</p>
                                    <p class="text-sm">Assign training modules to employees to start tracking progress.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                            <tr class="hover:bg-gray-50 <?php echo $assignment['is_overdue'] ? 'bg-red-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="bg-green-500 text-white w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold mr-4">
                                            <?php echo strtoupper(substr($assignment['first_name'], 0, 1) . substr($assignment['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($assignment['emp_id']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($assignment['department']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                    <div class="text-sm text-gray-500">
                                        <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs font-medium rounded-full mr-2">
                                            <?php echo ucfirst($assignment['module_type']); ?>
                                        </span>
                                        <?php echo htmlspecialchars(ucfirst($assignment['category'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-400"><?php echo $assignment['duration_minutes']; ?> min</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'not_started' => 'bg-gray-100 text-gray-800',
                                        'in_progress' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'failed' => 'bg-red-100 text-red-800',
                                        'skipped' => 'bg-orange-100 text-orange-800'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_colors[$assignment['status']]; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $assignment['status'])); ?>
                                    </span>
                                    <?php if ($assignment['is_overdue']): ?>
                                    <div class="text-xs text-red-600 mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($assignment['score'] !== null): ?>
                                        <span class="font-medium <?php echo $assignment['score'] >= $assignment['passing_score'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $assignment['score']; ?>%
                                        </span>
                                        <?php if ($assignment['passing_score']): ?>
                                        <div class="text-xs text-gray-400">
                                            Pass: <?php echo $assignment['passing_score']; ?>%
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($assignment['due_date']): ?>
                                        <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">No due date</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($assignment['time_spent_minutes'] > 0): ?>
                                        <?php echo floor($assignment['time_spent_minutes'] / 60) . 'h ' . ($assignment['time_spent_minutes'] % 60) . 'm'; ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">0m</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <button onclick="updateProgress(<?php echo $assignment['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900" title="Update Progress">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="viewTrainingDetails(<?php echo $assignment['training_module_id']; ?>)" 
                                                class="text-purple-600 hover:text-purple-900" title="View Module">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($assignment['status'] == 'not_started'): ?>
                                        <button onclick="startTraining(<?php echo $assignment['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900" title="Start Training">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <?php endif; ?>
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

    <!-- Create Module Modal -->
    <div id="createModuleModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-[600px] shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Create Training Module</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_module">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                            <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <input type="text" name="category" placeholder="safety, compliance, product..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                            <select name="module_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="reading">Reading</option>
                                <option value="video">Video</option>
                                <option value="quiz">Quiz</option>
                                <option value="practical">Practical</option>
                                <option value="meeting">Meeting</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" value="30" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Passing Score (%)</label>
                            <input type="number" name="passing_score" value="80" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Content URL (Optional)</label>
                        <input type="url" name="content_url" placeholder="https://..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Training Content</label>
                        <textarea name="content" rows="6" placeholder="Detailed training instructions, learning objectives, and content..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_required" checked class="mr-2">
                            <span class="text-sm text-gray-700">This is a required training module</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeCreateModuleModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Create Module
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Training Modal -->
    <div id="assignTrainingModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Training</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_training">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                        <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select employee...</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Training Modules *</label>
                        <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-lg p-2">
                            <?php foreach ($modules as $module): ?>
                            <label class="flex items-center py-1">
                                <input type="checkbox" name="module_ids[]" value="<?php echo $module['id']; ?>" class="mr-2">
                                <span class="text-sm"><?php echo htmlspecialchars($module['title']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                        <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeAssignTrainingModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition duration-200">
                            Assign Training
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Progress Modal -->
    <div id="updateProgressModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Update Training Progress</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_progress">
                    <input type="hidden" name="training_id" id="progress_training_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="skipped">Skipped</option>
                        </select>
                    </div>
                    
                    <div class="mb-4" id="score_div">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Score (%)</label>
                        <input type="number" name="score" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time Spent (minutes)</label>
                        <input type="number" name="time_spent" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeUpdateProgressModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Update Progress
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModuleModal() {
            document.getElementById('createModuleModal').classList.remove('hidden');
        }

        function closeCreateModuleModal() {
            document.getElementById('createModuleModal').classList.add('hidden');
        }

        function openAssignTrainingModal() {
            document.getElementById('assignTrainingModal').classList.remove('hidden');
        }

        function closeAssignTrainingModal() {
            document.getElementById('assignTrainingModal').classList.add('hidden');
        }

        function updateProgress(trainingId) {
            document.getElementById('progress_training_id').value = trainingId;
            document.getElementById('updateProgressModal').classList.remove('hidden');
        }

        function closeUpdateProgressModal() {
            document.getElementById('updateProgressModal').classList.add('hidden');
        }

        function viewTrainingDetails(moduleId) {
            window.location.href = 'training_details.php?id=' + moduleId;
        }

        function startTraining(trainingId) {
            if (confirm('Mark this training as started?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_progress">
                    <input type="hidden" name="training_id" value="${trainingId}">
                    <input type="hidden" name="status" value="in_progress">
                    <input type="hidden" name="notes" value="Training started">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Show/hide score field based on status
        document.querySelector('select[name="status"]').addEventListener('change', function() {
            const scoreDiv = document.getElementById('score_div');
            if (this.value === 'completed' || this.value === 'failed') {
                scoreDiv.style.display = 'block';
            } else {
                scoreDiv.style.display = 'none';
            }
        });

        // Close modals when clicking outside
        ['createModuleModal', 'assignTrainingModal', 'updateProgressModal'].forEach(modalId => {
            document.getElementById(modalId).addEventListener('click', function(event) {
                if (event.target === this) {
                    this.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html> 