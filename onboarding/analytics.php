<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

// Get date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
$department_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build where conditions for filtering
$where_conditions = ["e.created_at BETWEEN ? AND ?"];
$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if (!empty($department_filter)) {
    $where_conditions[] = "e.department = ?";
    $params[] = $department_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "e.onboarding_status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get overall onboarding statistics
$overall_stats_query = "
    SELECT 
        COUNT(*) as total_employees,
        SUM(CASE WHEN onboarding_status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN onboarding_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN onboarding_status = 'not_started' THEN 1 ELSE 0 END) as not_started,
        SUM(CASE WHEN onboarding_status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
        AVG(CASE 
            WHEN onboarding_completion_date IS NOT NULL AND onboarding_start_date IS NOT NULL 
            THEN DATEDIFF(onboarding_completion_date, onboarding_start_date)
            ELSE NULL 
        END) as avg_completion_days,
        AVG(DATEDIFF(CURDATE(), start_date)) as avg_days_since_start
    FROM employees e
    WHERE $where_clause
";

$overall_stats_stmt = $conn->prepare($overall_stats_query);
$overall_stats_stmt->execute($params);
$overall_stats = $overall_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get completion rate by department
$dept_stats_query = "
    SELECT 
        department,
        COUNT(*) as total,
        SUM(CASE WHEN onboarding_status = 'completed' THEN 1 ELSE 0 END) as completed,
        ROUND((SUM(CASE WHEN onboarding_status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as completion_rate
    FROM employees e
    WHERE $where_clause
    GROUP BY department
    ORDER BY completion_rate DESC
";

$dept_stats_stmt = $conn->prepare($dept_stats_query);
$dept_stats_stmt->execute($params);
$dept_stats = $dept_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get task completion statistics
$task_stats_query = "
    SELECT 
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN t.status = 'skipped' THEN 1 ELSE 0 END) as skipped_tasks,
        SUM(CASE WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks,
        ROUND((SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(t.id)), 1) as task_completion_rate
    FROM onboarding_tasks t
    JOIN employees e ON t.employee_id = e.id
    WHERE $where_clause
";

$task_stats_stmt = $conn->prepare($task_stats_query);
$task_stats_stmt->execute($params);
$task_stats = $task_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get training completion statistics
$training_stats_query = "
    SELECT 
        COUNT(et.id) as total_training,
        SUM(CASE WHEN et.status = 'completed' THEN 1 ELSE 0 END) as completed_training,
        SUM(CASE WHEN et.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_training,
        SUM(CASE WHEN et.status = 'not_started' THEN 1 ELSE 0 END) as not_started_training,
        SUM(CASE WHEN et.status = 'failed' THEN 1 ELSE 0 END) as failed_training,
        AVG(CASE WHEN et.score IS NOT NULL THEN et.score ELSE NULL END) as avg_training_score,
        ROUND((SUM(CASE WHEN et.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(et.id)), 1) as training_completion_rate
    FROM employee_training et
    JOIN employees e ON et.employee_id = e.id
    WHERE $where_clause
";

$training_stats_stmt = $conn->prepare($training_stats_query);
$training_stats_stmt->execute($params);
$training_stats = $training_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get document approval statistics
$doc_stats_query = "
    SELECT 
        COUNT(od.id) as total_documents,
        SUM(CASE WHEN od.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
        SUM(CASE WHEN od.status = 'submitted' THEN 1 ELSE 0 END) as submitted_documents,
        SUM(CASE WHEN od.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
        SUM(CASE WHEN od.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents,
        SUM(CASE WHEN od.is_required = 1 AND od.status != 'approved' THEN 1 ELSE 0 END) as missing_required,
        ROUND((SUM(CASE WHEN od.status = 'approved' THEN 1 ELSE 0 END) * 100.0 / COUNT(od.id)), 1) as approval_rate
    FROM onboarding_documents od
    JOIN employees e ON od.employee_id = e.id
    WHERE $where_clause
";

$doc_stats_stmt = $conn->prepare($doc_stats_query);
$doc_stats_stmt->execute($params);
$doc_stats = $doc_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly onboarding trends
$monthly_trends_query = "
    SELECT 
        DATE_FORMAT(e.created_at, '%Y-%m') as month,
        COUNT(*) as total_onboarded,
        SUM(CASE WHEN onboarding_status = 'completed' THEN 1 ELSE 0 END) as completed,
        ROUND((SUM(CASE WHEN onboarding_status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 1) as completion_rate
    FROM employees e
    WHERE e.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(e.created_at, '%Y-%m')
    ORDER BY month ASC
";

$monthly_trends_stmt = $conn->query($monthly_trends_query);
$monthly_trends = $monthly_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get task completion by category
$task_category_query = "
    SELECT 
        t.category,
        COUNT(t.id) as total,
        SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
        ROUND((SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(t.id)), 1) as completion_rate
    FROM onboarding_tasks t
    JOIN employees e ON t.employee_id = e.id
    WHERE $where_clause
    GROUP BY t.category
    ORDER BY completion_rate DESC
";

$task_category_stmt = $conn->prepare($task_category_query);
$task_category_stmt->execute($params);
$task_categories = $task_category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent completions
$recent_completions_query = "
    SELECT 
        e.first_name, e.last_name, e.employee_id, e.department,
        e.onboarding_completion_date,
        DATEDIFF(e.onboarding_completion_date, e.onboarding_start_date) as completion_days
    FROM employees e
    WHERE e.onboarding_status = 'completed' AND e.onboarding_completion_date IS NOT NULL
    ORDER BY e.onboarding_completion_date DESC
    LIMIT 10
";

$recent_completions_stmt = $conn->query($recent_completions_query);
$recent_completions = $recent_completions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$departments_stmt = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate completion rate
$completion_rate = $overall_stats['total_employees'] > 0 ? 
    round(($overall_stats['completed'] / $overall_stats['total_employees']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding Analytics - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Onboarding Analytics</h1>
                        <p class="text-gray-600">Comprehensive metrics and insights for employee onboarding performance</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="exportReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-users mr-2"></i>Back to Employees
                        </a>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
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
                            <option value="on_hold" <?php echo $status_filter == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                        <a href="analytics.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Employees</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $overall_stats['total_employees']; ?></p>
                            <p class="text-xs text-gray-500">In selected period</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completion Rate</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $completion_rate; ?>%</p>
                            <p class="text-xs text-gray-500"><?php echo $overall_stats['completed']; ?> completed</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg Completion Time</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo $overall_stats['avg_completion_days'] ? round($overall_stats['avg_completion_days']) : 'N/A'; ?>
                            </p>
                            <p class="text-xs text-gray-500">Days to complete</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-tasks text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Task Completion</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $task_stats['task_completion_rate'] ?? '0'; ?>%</p>
                            <p class="text-xs text-gray-500"><?php echo $task_stats['total_tasks'] ?? '0'; ?> total tasks</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Onboarding Status Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Onboarding Status Distribution</h3>
                    <div class="h-64">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <!-- Monthly Trends -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Onboarding Trends</h3>
                    <div class="h-64">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Department Performance and Task Categories -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Department Performance -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Performance</h3>
                    <div class="space-y-4">
                        <?php foreach ($dept_stats as $dept): ?>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($dept['department']); ?></span>
                                <span class="text-sm text-gray-500 ml-2">(<?php echo $dept['completed']; ?>/<?php echo $dept['total']; ?>)</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-32 bg-gray-200 rounded-full h-2 mr-3">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $dept['completion_rate']; ?>%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-900"><?php echo $dept['completion_rate']; ?>%</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Task Categories Performance -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Task Categories Performance</h3>
                    <div class="h-64">
                        <canvas id="taskCategoriesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Task Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Task Statistics</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Tasks</span>
                            <span class="font-semibold"><?php echo $task_stats['total_tasks'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Completed</span>
                            <span class="font-semibold text-green-600"><?php echo $task_stats['completed_tasks'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">In Progress</span>
                            <span class="font-semibold text-blue-600"><?php echo $task_stats['in_progress_tasks'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Pending</span>
                            <span class="font-semibold text-yellow-600"><?php echo $task_stats['pending_tasks'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Overdue</span>
                            <span class="font-semibold text-red-600"><?php echo $task_stats['overdue_tasks'] ?? '0'; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Training Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Training Statistics</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Assignments</span>
                            <span class="font-semibold"><?php echo $training_stats['total_training'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Completed</span>
                            <span class="font-semibold text-green-600"><?php echo $training_stats['completed_training'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">In Progress</span>
                            <span class="font-semibold text-blue-600"><?php echo $training_stats['in_progress_training'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Failed</span>
                            <span class="font-semibold text-red-600"><?php echo $training_stats['failed_training'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Avg Score</span>
                            <span class="font-semibold text-purple-600">
                                <?php echo $training_stats['avg_training_score'] ? round($training_stats['avg_training_score'], 1) . '%' : 'N/A'; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Document Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Document Statistics</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Documents</span>
                            <span class="font-semibold"><?php echo $doc_stats['total_documents'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Approved</span>
                            <span class="font-semibold text-green-600"><?php echo $doc_stats['approved_documents'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Submitted</span>
                            <span class="font-semibold text-blue-600"><?php echo $doc_stats['submitted_documents'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Pending</span>
                            <span class="font-semibold text-yellow-600"><?php echo $doc_stats['pending_documents'] ?? '0'; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Missing Required</span>
                            <span class="font-semibold text-red-600"><?php echo $doc_stats['missing_required'] ?? '0'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Completions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Completions</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_completions)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                    No recent completions found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_completions as $completion): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($completion['first_name'] . ' ' . $completion['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($completion['employee_id']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($completion['department']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($completion['onboarding_completion_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $completion['completion_days'] <= 7 ? 'bg-green-100 text-green-800' : 
                                                   ($completion['completion_days'] <= 14 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo $completion['completion_days'] ?? 'N/A'; ?> days
                                    </span>
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

    <script>
        // Status Distribution Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Not Started', 'On Hold'],
                datasets: [{
                    data: [
                        <?php echo $overall_stats['completed']; ?>,
                        <?php echo $overall_stats['in_progress']; ?>,
                        <?php echo $overall_stats['not_started']; ?>,
                        <?php echo $overall_stats['on_hold']; ?>
                    ],
                    backgroundColor: [
                        '#10B981',
                        '#3B82F6',
                        '#6B7280',
                        '#F59E0B'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Trends Line Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: [<?php echo '"' . implode('", "', array_column($monthly_trends, 'month')) . '"'; ?>],
                datasets: [{
                    label: 'Total Onboarded',
                    data: [<?php echo implode(', ', array_column($monthly_trends, 'total_onboarded')); ?>],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Completed',
                    data: [<?php echo implode(', ', array_column($monthly_trends, 'completed')); ?>],
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Task Categories Bar Chart
        const taskCategoriesCtx = document.getElementById('taskCategoriesChart').getContext('2d');
        new Chart(taskCategoriesCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('", "', array_map('ucfirst', array_column($task_categories, 'category'))) . '"'; ?>],
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: [<?php echo implode(', ', array_column($task_categories, 'completion_rate')); ?>],
                    backgroundColor: [
                        '#3B82F6',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6',
                        '#06B6D4',
                        '#84CC16'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        function exportReport() {
            // Placeholder for export functionality
            alert('Export functionality will be implemented to generate PDF/Excel reports');
        }
    </script>
</body>
</html> 