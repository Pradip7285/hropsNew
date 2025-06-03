<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';
$start_date_from = $_GET['start_date_from'] ?? '';
$start_date_to = $_GET['start_date_to'] ?? '';

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.employee_id LIKE ? OR e.position_title LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status)) {
    $where_conditions[] = "e.onboarding_status = ?";
    $params[] = $status;
}

if (!empty($department)) {
    $where_conditions[] = "e.department = ?";
    $params[] = $department;
}

if (!empty($start_date_from)) {
    $where_conditions[] = "e.start_date >= ?";
    $params[] = $start_date_from;
}

if (!empty($start_date_to)) {
    $where_conditions[] = "e.start_date <= ?";
    $params[] = $start_date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM employees e
    LEFT JOIN users manager ON e.manager_id = manager.id
    LEFT JOIN users buddy ON e.buddy_id = buddy.id
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get employees with onboarding progress
$query = "
    SELECT e.*, 
           manager.first_name as manager_first, manager.last_name as manager_last,
           buddy.first_name as buddy_first, buddy.last_name as buddy_last,
           tasks.total_tasks, tasks.completed_tasks, tasks.completion_percentage,
           tasks.overdue_tasks, tasks.critical_pending,
           CASE 
               WHEN e.start_date > CURDATE() THEN 'pre_start'
               WHEN e.onboarding_status = 'completed' THEN 'completed'
               WHEN e.onboarding_status = 'on_hold' THEN 'on_hold'
               WHEN tasks.completion_percentage >= 90 THEN 'nearly_complete'
               WHEN tasks.completion_percentage >= 50 THEN 'in_progress'
               WHEN tasks.overdue_tasks > 0 OR tasks.critical_pending > 0 THEN 'urgent'
               WHEN DATEDIFF(CURDATE(), e.start_date) > 14 AND tasks.completion_percentage < 50 THEN 'delayed'
               ELSE 'starting'
           END as progress_status,
           DATEDIFF(CURDATE(), e.start_date) as days_since_start
    FROM employees e
    LEFT JOIN users manager ON e.manager_id = manager.id
    LEFT JOIN users buddy ON e.buddy_id = buddy.id
    LEFT JOIN (
        SELECT employee_id,
               COUNT(*) as total_tasks,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
               ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as completion_percentage,
               SUM(CASE WHEN status != 'completed' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks,
               SUM(CASE WHEN status = 'pending' AND priority = 'critical' THEN 1 ELSE 0 END) as critical_pending
        FROM onboarding_tasks 
        GROUP BY employee_id
    ) tasks ON e.id = tasks.employee_id
    $where_clause
    ORDER BY 
        CASE e.onboarding_status 
            WHEN 'not_started' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'on_hold' THEN 3
            WHEN 'completed' THEN 4
        END,
        e.start_date DESC, e.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$departments_stmt = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get quick stats
$stats_query = "
    SELECT 
        COUNT(*) as total_employees,
        SUM(CASE WHEN onboarding_status = 'not_started' THEN 1 ELSE 0 END) as not_started,
        SUM(CASE WHEN onboarding_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN onboarding_status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN onboarding_status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
        SUM(CASE WHEN start_date > CURDATE() THEN 1 ELSE 0 END) as upcoming_starts
    FROM employees
    WHERE 1=1 " . (!empty($where_conditions) ? "AND " . implode(" AND ", $where_conditions) : "");

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Onboarding - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Employee Onboarding</h1>
                        <p class="text-gray-600">Track and manage new employee onboarding progress</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="create.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Add Employee
                        </a>
                        <a href="analytics.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-chart-bar mr-2"></i>Analytics
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_employees']; ?></p>
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
                            <i class="fas fa-tasks text-yellow-600"></i>
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
                        <div class="bg-orange-100 p-2 rounded-full mr-3">
                            <i class="fas fa-pause-circle text-orange-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">On Hold</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['on_hold']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-2 rounded-full mr-3">
                            <i class="fas fa-calendar-plus text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Upcoming</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['upcoming_starts']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Name, email, employee ID..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="not_started" <?php echo $status == 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="on_hold" <?php echo $status == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date From</label>
                        <input type="date" name="start_date_from" value="<?php echo $start_date_from; ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">To</label>
                            <input type="date" name="start_date_to" value="<?php echo $start_date_to; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Employee List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manager/Buddy</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-user-plus text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No employees found</p>
                                    <p class="text-sm">Add new employees to start tracking their onboarding progress.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($employees as $employee): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="bg-blue-500 text-white w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold mr-4">
                                            <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['email']); ?></div>
                                            <div class="text-xs text-gray-400">ID: <?php echo htmlspecialchars($employee['employee_id']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($employee['position_title']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['department']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('M j, Y', strtotime($employee['start_date'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php 
                                        $days = $employee['days_since_start'];
                                        if ($days < 0) {
                                            echo "Starts in " . abs($days) . " day" . (abs($days) != 1 ? 's' : '');
                                        } else {
                                            echo "Day " . ($days + 1);
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($employee['total_tasks']): ?>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $employee['completion_percentage']; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?php echo $employee['completed_tasks']; ?>/<?php echo $employee['total_tasks']; ?> tasks 
                                        (<?php echo $employee['completion_percentage']; ?>%)
                                    </div>
                                    <?php if ($employee['overdue_tasks'] > 0): ?>
                                    <div class="text-xs text-red-600 mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        <?php echo $employee['overdue_tasks']; ?> overdue
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-xs text-gray-500">No tasks assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'pre_start' => 'bg-purple-100 text-purple-800',
                                        'not_started' => 'bg-gray-100 text-gray-800',
                                        'starting' => 'bg-blue-100 text-blue-800',
                                        'in_progress' => 'bg-yellow-100 text-yellow-800',
                                        'nearly_complete' => 'bg-green-100 text-green-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'on_hold' => 'bg-orange-100 text-orange-800',
                                        'delayed' => 'bg-red-100 text-red-800',
                                        'urgent' => 'bg-red-100 text-red-800'
                                    ];
                                    $status_labels = [
                                        'pre_start' => 'Pre-Start',
                                        'not_started' => 'Not Started',
                                        'starting' => 'Starting',
                                        'in_progress' => 'In Progress',
                                        'nearly_complete' => 'Nearly Complete',
                                        'completed' => 'Completed',
                                        'on_hold' => 'On Hold',
                                        'delayed' => 'Delayed',
                                        'urgent' => 'Urgent'
                                    ];
                                    $status = $employee['progress_status'];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_colors[$status]; ?>">
                                        <?php echo $status_labels[$status]; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($employee['manager_first']): ?>
                                    <div class="mb-1">
                                        <span class="text-xs text-gray-400">Manager:</span><br>
                                        <?php echo htmlspecialchars($employee['manager_first'] . ' ' . $employee['manager_last']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($employee['buddy_first']): ?>
                                    <div>
                                        <span class="text-xs text-gray-400">Buddy:</span><br>
                                        <?php echo htmlspecialchars($employee['buddy_first'] . ' ' . $employee['buddy_last']); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a href="view.php?id=<?php echo $employee['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="tasks.php?employee_id=<?php echo $employee['id']; ?>" 
                                           class="text-green-600 hover:text-green-900" title="View Tasks">
                                            <i class="fas fa-tasks"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $employee['id']; ?>" 
                                           class="text-yellow-600 hover:text-yellow-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($employee['onboarding_status'] == 'not_started'): ?>
                                        <button onclick="startOnboarding(<?php echo $employee['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900" title="Start Onboarding">
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

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-6 rounded-lg shadow">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" 
                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                            <span class="font-medium"><?php echo min($offset + $limit, $total_records); ?></span> of 
                            <span class="font-medium"><?php echo $total_records; ?></span> results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)); ?>" 
                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo $i == $page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function startOnboarding(employeeId) {
            if (confirm('Start onboarding process for this employee? This will create their task list and begin tracking.')) {
                // In a real implementation, this would make an AJAX call
                window.location.href = 'start_onboarding.php?id=' + employeeId;
            }
        }

        // Auto-refresh for real-time updates (optional)
        if (document.querySelector('.bg-red-100')) {
            setTimeout(() => location.reload(), 300000); // 5 minutes
        }
    </script>
</body>
</html> 