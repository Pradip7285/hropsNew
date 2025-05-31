<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR j.title LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status)) {
    $where_conditions[] = "e.onboarding_status = ?";
    $params[] = $status;
}

if (!empty($department)) {
    $where_conditions[] = "j.department = ?";
    $params[] = $department;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$db = new Database();
$conn = $db->getConnection();

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM employees e
    JOIN job_postings j ON e.job_id = j.id
    LEFT JOIN users mentor ON e.buddy_id = mentor.id
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get employees in onboarding
$query = "
    SELECT e.*, 
           j.title as job_title, j.department,
           mentor.first_name as mentor_first, mentor.last_name as mentor_last,
           o.completion_percentage, o.days_since_start,
           CASE 
               WHEN e.start_date > CURDATE() THEN 'pre_start'
               WHEN e.onboarding_status = 'completed' THEN 'completed'
               WHEN o.completion_percentage >= 80 THEN 'nearly_complete'
               WHEN o.completion_percentage >= 50 THEN 'in_progress'
               WHEN o.completion_percentage < 50 AND o.days_since_start > 7 THEN 'delayed'
               ELSE 'starting'
           END as progress_status
    FROM employees e
    JOIN job_postings j ON e.job_id = j.id
    LEFT JOIN users mentor ON e.buddy_id = mentor.id
    LEFT JOIN (
        SELECT employee_id,
               ROUND((COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)), 1) as completion_percentage,
               DATEDIFF(CURDATE(), MIN(created_at)) as days_since_start
        FROM onboarding_tasks 
        GROUP BY employee_id
    ) o ON e.id = o.employee_id
    $where_clause
    ORDER BY e.start_date DESC, e.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$departments_stmt = $conn->query("SELECT DISTINCT department FROM job_postings ORDER BY department");
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);
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
                <h1 class="text-3xl font-bold text-gray-800">Employee Onboarding</h1>
                <p class="text-gray-600">Track and manage new employee onboarding progress</p>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Employee name, email, or position..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="delayed" <?php echo $status == 'delayed' ? 'selected' : ''; ?>>Delayed</option>
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
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-user-plus text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">New Hires</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($employees, fn($e) => $e['progress_status'] == 'starting')); ?>
                            </p>
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
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($employees, fn($e) => in_array($e['progress_status'], ['in_progress', 'nearly_complete']))); ?>
                            </p>
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
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($employees, fn($e) => $e['progress_status'] == 'completed')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Delayed</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($employees, fn($e) => $e['progress_status'] == 'delayed')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <a href="create.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Add New Employee
                    </a>
                    <a href="templates.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-clipboard-list mr-2"></i>Onboarding Templates
                    </a>
                    <a href="checklist.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-tasks mr-2"></i>Task Checklist
                    </a>
                    <a href="analytics.php" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-chart-bar mr-2"></i>Analytics
                    </a>
                </div>
                
                <div class="text-sm text-gray-600">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> employees
                </div>
            </div>

            <!-- Employees Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mentor/Buddy</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-4"></i>
                                <p>No employees in onboarding process</p>
                                <a href="create.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-plus mr-2"></i>Add First Employee
                                </a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="bg-indigo-500 text-white w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold">
                                        <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($employee['job_title']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['department']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($employee['start_date'])); ?></div>
                                <div class="text-sm text-gray-500">
                                    <?php 
                                    $days = $employee['days_since_start'] ?? 0;
                                    if ($days > 0) echo "$days days ago";
                                    elseif ($days == 0) echo "Today";
                                    else echo "Upcoming";
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $employee['completion_percentage'] ?? 0; ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600"><?php echo $employee['completion_percentage'] ?? 0; ?>%</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <?php if ($employee['completion_percentage'] == 100): ?>
                                        Completed
                                    <?php elseif ($employee['completion_percentage'] >= 80): ?>
                                        Nearly Complete
                                    <?php elseif ($employee['completion_percentage'] >= 50): ?>
                                        In Progress
                                    <?php else: ?>
                                        Getting Started
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($employee['mentor_first'] && $employee['mentor_last']): ?>
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($employee['mentor_first'] . ' ' . $employee['mentor_last']); ?>
                                </div>
                                <div class="text-xs text-gray-500">Buddy/Mentor</div>
                                <?php else: ?>
                                <span class="text-sm text-gray-400">Not assigned</span>
                                <button onclick="assignMentor(<?php echo $employee['id']; ?>)" class="block text-xs text-blue-600 hover:text-blue-800 mt-1">
                                    Assign Mentor
                                </button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'pre_start' => 'bg-gray-100 text-gray-800',
                                    'starting' => 'bg-blue-100 text-blue-800',
                                    'in_progress' => 'bg-yellow-100 text-yellow-800',
                                    'nearly_complete' => 'bg-indigo-100 text-indigo-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'delayed' => 'bg-red-100 text-red-800'
                                ];
                                $color_class = $status_colors[$employee['progress_status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $color_class; ?>">
                                    <?php 
                                    switch($employee['progress_status']) {
                                        case 'pre_start': echo 'Pre-Start'; break;
                                        case 'starting': echo 'Starting'; break;
                                        case 'in_progress': echo 'In Progress'; break;
                                        case 'nearly_complete': echo 'Nearly Complete'; break;
                                        case 'completed': echo 'Completed'; break;
                                        case 'delayed': echo 'Delayed'; break;
                                        default: echo 'Unknown'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="view.php?id=<?php echo $employee['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="tasks.php?employee_id=<?php echo $employee['id']; ?>" class="text-green-600 hover:text-green-900" title="View Tasks">
                                        <i class="fas fa-tasks"></i>
                                    </a>
                                    <a href="portal.php?employee_id=<?php echo $employee['id']; ?>" class="text-purple-600 hover:text-purple-900" title="Employee Portal">
                                        <i class="fas fa-user-circle"></i>
                                    </a>
                                    <button onclick="sendReminder(<?php echo $employee['id']; ?>)" class="text-orange-600 hover:text-orange-900" title="Send Reminder">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                    <a href="edit.php?id=<?php echo $employee['id']; ?>" class="text-gray-600 hover:text-gray-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&department=<?php echo $department; ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&department=<?php echo $department; ?>" 
                       class="px-3 py-2 border rounded-lg <?php echo $i == $page ? 'bg-blue-500 text-white border-blue-500' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&department=<?php echo $department; ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function assignMentor(employeeId) {
            // In a real implementation, this would open a modal to select a mentor
            if (confirm('Assign a mentor to this employee?')) {
                window.location.href = 'assign_mentor.php?employee_id=' + employeeId;
            }
        }

        function sendReminder(employeeId) {
            if (confirm('Send onboarding reminder to this employee?')) {
                fetch('send_reminder.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'employee_id=' + employeeId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reminder sent successfully!');
                    } else {
                        alert('Error sending reminder: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html> 