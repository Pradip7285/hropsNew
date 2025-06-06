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
        case 'create_goal':
            $stmt = $conn->prepare("
                INSERT INTO performance_goals 
                (employee_id, manager_id, goal_title, goal_description, goal_category, goal_type, 
                 priority, target_value, unit_of_measure, start_date, due_date, weight_percentage, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $_POST['employee_id'], $_POST['manager_id'], $_POST['goal_title'], 
                $_POST['goal_description'], $_POST['goal_category'], $_POST['goal_type'],
                $_POST['priority'], $_POST['target_value'] ?: null, $_POST['unit_of_measure'] ?: null,
                $_POST['start_date'], $_POST['due_date'], $_POST['weight_percentage'], $_SESSION['user_id']
            ]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_goal':
            $stmt = $conn->prepare("
                UPDATE performance_goals SET 
                goal_title = ?, goal_description = ?, goal_category = ?, goal_type = ?, 
                priority = ?, target_value = ?, unit_of_measure = ?, due_date = ?, 
                weight_percentage = ?, status = ?, current_value = ?, progress_percentage = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $_POST['goal_title'], $_POST['goal_description'], $_POST['goal_category'], 
                $_POST['goal_type'], $_POST['priority'], $_POST['target_value'] ?: null,
                $_POST['unit_of_measure'] ?: null, $_POST['due_date'], $_POST['weight_percentage'],
                $_POST['status'], $_POST['current_value'] ?: 0, $_POST['progress_percentage'] ?: 0,
                $_POST['goal_id']
            ]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'delete_goal':
            $stmt = $conn->prepare("DELETE FROM performance_goals WHERE id = ?");
            $result = $stmt->execute([$_POST['goal_id']]);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Get filters
$employee_filter = $_GET['employee'] ?? '';
$status_filter = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($employee_filter)) {
    $where_conditions[] = "g.employee_id = ?";
    $params[] = $employee_filter;
}
if (!empty($status_filter)) {
    $where_conditions[] = "g.status = ?";
    $params[] = $status_filter;
}
if (!empty($category_filter)) {
    $where_conditions[] = "g.goal_category = ?";
    $params[] = $category_filter;
}
if (!empty($priority_filter)) {
    $where_conditions[] = "g.priority = ?";
    $params[] = $priority_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get goals with employee and manager info
$goals_query = "
    SELECT g.*, 
           e.first_name as employee_first_name, e.last_name as employee_last_name, e.employee_id as emp_id,
           m.first_name as manager_first_name, m.last_name as manager_last_name,
           CASE 
               WHEN g.due_date < CURDATE() AND g.status NOT IN ('completed', 'cancelled') THEN 'overdue'
               WHEN g.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND g.status NOT IN ('completed', 'cancelled') THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status
    FROM performance_goals g
    JOIN employees e ON g.employee_id = e.id
    JOIN employees m ON g.manager_id = m.id
    WHERE $where_clause
    ORDER BY g.due_date ASC, g.priority DESC
";

$goals_stmt = $conn->prepare($goals_query);
$goals_stmt->execute($params);
$goals = $goals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for filter
$employees_stmt = $conn->query("
    SELECT e.id, u.first_name, u.last_name, e.employee_id 
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    WHERE u.is_active = 1 
    ORDER BY u.first_name, u.last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get managers
$managers_stmt = $conn->query("
    SELECT e.id, u.first_name, u.last_name 
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    WHERE u.is_active = 1 AND (e.position LIKE '%manager%' OR e.position LIKE '%supervisor%' OR u.role IN ('hr_recruiter', 'hiring_manager'))
    ORDER BY u.first_name, u.last_name
");
$managers = $managers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_goals,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_goals,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_goals,
        AVG(progress_percentage) as avg_progress
    FROM performance_goals
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
    <title>Performance Goals Management - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Performance Goals Management</h1>
                        <p class="text-gray-600">Set, track, and manage employee performance goals</p>
                    </div>
                    <button onclick="openCreateGoalModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create New Goal
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-bullseye text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Goals</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_goals']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_goals']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">In Progress</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['in_progress_goals']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['overdue_goals']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg Progress</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo round($stats['avg_progress'] ?? 0, 1); ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
                        <select name="employee" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $employee_filter == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="paused" <?php echo $status_filter == 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>
                            <option value="individual" <?php echo $category_filter == 'individual' ? 'selected' : ''; ?>>Individual</option>
                            <option value="team" <?php echo $category_filter == 'team' ? 'selected' : ''; ?>>Team</option>
                            <option value="organizational" <?php echo $category_filter == 'organizational' ? 'selected' : ''; ?>>Organizational</option>
                            <option value="development" <?php echo $category_filter == 'development' ? 'selected' : ''; ?>>Development</option>
                            <option value="behavioral" <?php echo $category_filter == 'behavioral' ? 'selected' : ''; ?>>Behavioral</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Priorities</option>
                            <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $priority_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="goals.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Goals Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Goal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($goals as $goal): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($goal['goal_title']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($goal['goal_description'], 0, 100)); ?>...</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($goal['employee_first_name'] . ' ' . $goal['employee_last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($goal['emp_id']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        <?php echo ucfirst($goal['goal_category']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $goal['priority'] == 'critical' ? 'bg-red-100 text-red-800' : 
                                                   ($goal['priority'] == 'high' ? 'bg-orange-100 text-orange-800' : 
                                                   ($goal['priority'] == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')); ?>">
                                        <?php echo ucfirst($goal['priority']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $goal['progress_percentage']; ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-600"><?php echo $goal['progress_percentage']; ?>%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="<?php echo $goal['urgency_status'] == 'overdue' ? 'text-red-600 font-semibold' : 
                                                        ($goal['urgency_status'] == 'due_soon' ? 'text-yellow-600 font-semibold' : ''); ?>">
                                        <?php echo date('M j, Y', strtotime($goal['due_date'])); ?>
                                        <?php if ($goal['urgency_status'] == 'overdue'): ?>
                                            <i class="fas fa-exclamation-triangle ml-1"></i>
                                        <?php elseif ($goal['urgency_status'] == 'due_soon'): ?>
                                            <i class="fas fa-clock ml-1"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $goal['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                               ($goal['status'] == 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                                               ($goal['status'] == 'active' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $goal['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="editGoal(<?php echo htmlspecialchars(json_encode($goal)); ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteGoal(<?php echo $goal['id']; ?>)" 
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Goal Modal -->
    <div id="goalModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Create New Goal</h3>
                    <button onclick="closeGoalModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="goalForm" class="space-y-4">
                    <input type="hidden" id="goalId" name="goal_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                            <select id="employeeId" name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Manager *</label>
                            <select id="managerId" name="manager_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Manager</option>
                                <?php foreach ($managers as $manager): ?>
                                <option value="<?php echo $manager['id']; ?>">
                                    <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Goal Title *</label>
                        <input type="text" id="goalTitle" name="goal_title" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Goal Description</label>
                        <textarea id="goalDescription" name="goal_description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select id="goalCategory" name="goal_category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="individual">Individual</option>
                                <option value="team">Team</option>
                                <option value="organizational">Organizational</option>
                                <option value="development">Development</option>
                                <option value="behavioral">Behavioral</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                            <select id="goalType" name="goal_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="objective">Objective</option>
                                <option value="key_result">Key Result</option>
                                <option value="milestone">Milestone</option>
                                <option value="competency">Competency</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select id="goalPriority" name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Target Value</label>
                            <input type="number" id="targetValue" name="target_value" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit of Measure</label>
                            <input type="text" id="unitOfMeasure" name="unit_of_measure" 
                                   placeholder="e.g., %, units, hours"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Weight (%)</label>
                            <input type="number" id="weightPercentage" name="weight_percentage" min="0" max="100" step="0.1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date *</label>
                            <input type="date" id="startDate" name="start_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                            <input type="date" id="dueDate" name="due_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Progress fields (for editing) -->
                    <div id="progressFields" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="goalStatus" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="paused">Paused</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Value</label>
                            <input type="number" id="currentValue" name="current_value" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Progress (%)</label>
                            <input type="number" id="progressPercentage" name="progress_percentage" min="0" max="100" step="0.1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeGoalModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            <span id="submitButtonText">Create Goal</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateGoalModal() {
            document.getElementById('modalTitle').textContent = 'Create New Goal';
            document.getElementById('submitButtonText').textContent = 'Create Goal';
            document.getElementById('goalForm').reset();
            document.getElementById('goalId').value = '';
            document.getElementById('progressFields').classList.add('hidden');
            
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            document.getElementById('startDate').value = today;
            document.getElementById('dueDate').value = nextYear.toISOString().split('T')[0];
            
            document.getElementById('goalModal').classList.remove('hidden');
        }

        function editGoal(goal) {
            document.getElementById('modalTitle').textContent = 'Edit Goal';
            document.getElementById('submitButtonText').textContent = 'Update Goal';
            document.getElementById('progressFields').classList.remove('hidden');
            
            // Populate form
            document.getElementById('goalId').value = goal.id;
            document.getElementById('employeeId').value = goal.employee_id;
            document.getElementById('managerId').value = goal.manager_id;
            document.getElementById('goalTitle').value = goal.goal_title;
            document.getElementById('goalDescription').value = goal.goal_description || '';
            document.getElementById('goalCategory').value = goal.goal_category;
            document.getElementById('goalType').value = goal.goal_type;
            document.getElementById('goalPriority').value = goal.priority;
            document.getElementById('targetValue').value = goal.target_value || '';
            document.getElementById('unitOfMeasure').value = goal.unit_of_measure || '';
            document.getElementById('weightPercentage').value = goal.weight_percentage || '';
            document.getElementById('startDate').value = goal.start_date;
            document.getElementById('dueDate').value = goal.due_date;
            document.getElementById('goalStatus').value = goal.status;
            document.getElementById('currentValue').value = goal.current_value || '';
            document.getElementById('progressPercentage').value = goal.progress_percentage || '';
            
            document.getElementById('goalModal').classList.remove('hidden');
        }

        function closeGoalModal() {
            document.getElementById('goalModal').classList.add('hidden');
        }

        function deleteGoal(goalId) {
            if (confirm('Are you sure you want to delete this goal?')) {
                const formData = new FormData();
                formData.append('action', 'delete_goal');
                formData.append('goal_id', goalId);

                fetch('goals.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting goal');
                    }
                });
            }
        }

        document.getElementById('goalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const goalId = document.getElementById('goalId').value;
            formData.append('action', goalId ? 'update_goal' : 'create_goal');

            fetch('goals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error saving goal');
                }
            });
        });

        // Close modal when clicking outside
        document.getElementById('goalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeGoalModal();
            }
        });
    </script>
</body>
</html> 