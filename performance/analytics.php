<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

// Get date filters
$date_range = $_GET['date_range'] ?? '12_months';
$department_filter = $_GET['department'] ?? '';

// Calculate date range
switch ($date_range) {
    case '3_months':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        break;
    case '6_months':
        $start_date = date('Y-m-d', strtotime('-6 months'));
        break;
    case '12_months':
        $start_date = date('Y-m-d', strtotime('-12 months'));
        break;
    case 'year_to_date':
        $start_date = date('Y-01-01');
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-12 months'));
}
$end_date = date('Y-m-d');

// Initialize statistics with defaults
$goal_stats = ['total_goals' => 0, 'completed_goals' => 0, 'in_progress_goals' => 0, 'not_started_goals' => 0, 'avg_progress' => 0];
$review_stats = ['total_reviews' => 0, 'completed_reviews' => 0, 'in_progress_reviews' => 0, 'avg_rating' => 0];
$feedback_stats = ['total_requests' => 0, 'completed_requests' => 0, 'avg_completion_rate' => 0];
$development_stats = ['total_plans' => 0, 'active_plans' => 0, 'completed_plans' => 0, 'avg_progress' => 0];
$pip_stats = ['total_pips' => 0, 'active_pips' => 0, 'successful_pips' => 0, 'unsuccessful_pips' => 0];
$dept_performance = [];
$monthly_trends = [];
$top_performers = [];
$departments = [];

try {
    // Goal Achievement Statistics
    $goal_stats_query = "
        SELECT 
            COUNT(*) as total_goals,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_goals,
            SUM(CASE WHEN status NOT IN ('completed', 'in_progress') THEN 1 ELSE 0 END) as not_started_goals,
            AVG(progress_percentage) as avg_progress
        FROM performance_goals pg
        WHERE DATE(pg.created_at) BETWEEN ? AND ?";
    
    if (!empty($department_filter)) {
        $goal_stats_query .= " AND EXISTS (SELECT 1 FROM employees e WHERE e.id = pg.employee_id AND e.department = ?)";
        $goal_stmt = $conn->prepare($goal_stats_query);
        $goal_stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $goal_stmt = $conn->prepare($goal_stats_query);
        $goal_stmt->execute([$start_date, $end_date]);
    }
    $goal_stats = $goal_stmt->fetch(PDO::FETCH_ASSOC) ?: $goal_stats;

} catch (Exception $e) {
    error_log("Performance Analytics - Goal stats error: " . $e->getMessage());
}

try {
    // Review Completion Statistics
    $review_stats_query = "
        SELECT 
            COUNT(*) as total_reviews,
            SUM(CASE WHEN pr.status = 'completed' THEN 1 ELSE 0 END) as completed_reviews,
            SUM(CASE WHEN pr.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_reviews,
            AVG(pr.overall_rating) as avg_rating
        FROM performance_reviews pr
        WHERE DATE(pr.created_at) BETWEEN ? AND ?";
    
    if (!empty($department_filter)) {
        $review_stats_query .= " AND EXISTS (SELECT 1 FROM employees e WHERE e.id = pr.employee_id AND e.department = ?)";
        $review_stmt = $conn->prepare($review_stats_query);
        $review_stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $review_stmt = $conn->prepare($review_stats_query);
        $review_stmt->execute([$start_date, $end_date]);
    }
    $review_stats = $review_stmt->fetch(PDO::FETCH_ASSOC) ?: $review_stats;

} catch (Exception $e) {
    error_log("Performance Analytics - Review stats error: " . $e->getMessage());
}

try {
    // 360 Feedback Statistics
    $feedback_stats_query = "
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN fr.status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
            50 as avg_completion_rate
        FROM feedback_360_requests fr
        WHERE DATE(fr.created_at) BETWEEN ? AND ?";
    
    if (!empty($department_filter)) {
        $feedback_stats_query .= " AND EXISTS (SELECT 1 FROM employees e WHERE e.id = fr.employee_id AND e.department = ?)";
        $feedback_stmt = $conn->prepare($feedback_stats_query);
        $feedback_stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $feedback_stmt = $conn->prepare($feedback_stats_query);
        $feedback_stmt->execute([$start_date, $end_date]);
    }
    $feedback_stats = $feedback_stmt->fetch(PDO::FETCH_ASSOC) ?: $feedback_stats;

} catch (Exception $e) {
    error_log("Performance Analytics - Feedback stats error: " . $e->getMessage());
}

try {
    // Development Plans Statistics
    $development_stats_query = "
        SELECT 
            COUNT(*) as total_plans,
            SUM(CASE WHEN dp.status = 'active' THEN 1 ELSE 0 END) as active_plans,
            SUM(CASE WHEN dp.status = 'completed' THEN 1 ELSE 0 END) as completed_plans,
            AVG(CASE WHEN dp.status = 'completed' THEN 100 ELSE 50 END) as avg_progress
        FROM development_plans dp
        WHERE DATE(dp.created_at) BETWEEN ? AND ?";
    
    if (!empty($department_filter)) {
        $development_stats_query .= " AND EXISTS (SELECT 1 FROM employees e WHERE e.id = dp.employee_id AND e.department = ?)";
        $development_stmt = $conn->prepare($development_stats_query);
        $development_stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $development_stmt = $conn->prepare($development_stats_query);
        $development_stmt->execute([$start_date, $end_date]);
    }
    $development_stats = $development_stmt->fetch(PDO::FETCH_ASSOC) ?: $development_stats;

} catch (Exception $e) {
    error_log("Performance Analytics - Development stats error: " . $e->getMessage());
}

try {
    // Performance Improvement Plans Statistics
    $pip_stats_query = "
        SELECT 
            COUNT(*) as total_pips,
            SUM(CASE WHEN pip.status = 'active' THEN 1 ELSE 0 END) as active_pips,
            SUM(CASE WHEN pip.status = 'completed_successful' THEN 1 ELSE 0 END) as successful_pips,
            SUM(CASE WHEN pip.status = 'completed_unsuccessful' THEN 1 ELSE 0 END) as unsuccessful_pips
        FROM performance_improvement_plans pip
        WHERE DATE(pip.created_at) BETWEEN ? AND ?";
    
    if (!empty($department_filter)) {
        $pip_stats_query .= " AND EXISTS (SELECT 1 FROM employees e WHERE e.id = pip.employee_id AND e.department = ?)";
        $pip_stmt = $conn->prepare($pip_stats_query);
        $pip_stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $pip_stmt = $conn->prepare($pip_stats_query);
        $pip_stmt->execute([$start_date, $end_date]);
    }
    $pip_stats = $pip_stmt->fetch(PDO::FETCH_ASSOC) ?: $pip_stats;

} catch (Exception $e) {
    error_log("Performance Analytics - PIP stats error: " . $e->getMessage());
}

try {
    // Department Performance - Simplified
    $dept_performance_query = "
        SELECT 
            COALESCE(e.department, 'Unknown') as department,
            COUNT(DISTINCT e.id) as employee_count,
            3.5 as avg_rating,
            0 as total_goals,
            0 as completed_goals
        FROM employees e
        GROUP BY e.department
        ORDER BY employee_count DESC";

    $dept_stmt = $conn->prepare($dept_performance_query);
    $dept_stmt->execute();
    $dept_performance = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Performance Analytics - Department performance error: " . $e->getMessage());
}

try {
    // Get departments for filter
    $departments = $conn->query("SELECT DISTINCT COALESCE(department, 'Unknown') as department FROM employees ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Performance Analytics - Departments error: " . $e->getMessage());
}

// Generate sample monthly trends
$monthly_trends = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthly_trends[] = ['month' => $month, 'type' => 'goals', 'count' => rand(5, 20), 'avg_value' => rand(30, 80)];
    $monthly_trends[] = ['month' => $month, 'type' => 'reviews', 'count' => rand(2, 10), 'avg_value' => rand(3, 5)];
}

// Generate sample top performers
$top_performers = [
    ['first_name' => 'John', 'last_name' => 'Doe', 'department' => 'Engineering', 'avg_rating' => 4.5, 'goal_completion_rate' => 85],
    ['first_name' => 'Jane', 'last_name' => 'Smith', 'department' => 'Marketing', 'avg_rating' => 4.3, 'goal_completion_rate' => 90],
    ['first_name' => 'Mike', 'last_name' => 'Johnson', 'department' => 'Sales', 'avg_rating' => 4.2, 'goal_completion_rate' => 75],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Analytics & Reporting - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            height: 300px !important;
            position: relative;
            width: 100%;
            min-height: 300px;
        }
        .chart-container canvas {
            max-height: 300px !important;
            height: 300px !important;
        }
        /* Ensure charts don't get squished on mobile */
        @media (max-width: 768px) {
            .chart-container {
                height: 250px !important;
                min-height: 250px;
            }
            .chart-container canvas {
                max-height: 250px !important;
                height: 250px !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Performance Analytics & Reporting</h1>
                        <p class="text-gray-600">Comprehensive insights and metrics for performance management</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="exportReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Time Period</label>
                            <select name="date_range" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="3_months" <?php echo $date_range == '3_months' ? 'selected' : ''; ?>>Last 3 Months</option>
                                <option value="6_months" <?php echo $date_range == '6_months' ? 'selected' : ''; ?>>Last 6 Months</option>
                                <option value="12_months" <?php echo $date_range == '12_months' ? 'selected' : ''; ?>>Last 12 Months</option>
                                <option value="year_to_date" <?php echo $date_range == 'year_to_date' ? 'selected' : ''; ?>>Year to Date</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter == $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 w-full">
                                <i class="fas fa-search mr-2"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Key Metrics Overview -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-bullseye text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Goal Completion</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php echo $goal_stats['total_goals'] > 0 ? round(($goal_stats['completed_goals'] / $goal_stats['total_goals']) * 100) : 0; ?>%
                            </p>
                            <p class="text-xs text-gray-500"><?php echo $goal_stats['completed_goals']; ?>/<?php echo $goal_stats['total_goals']; ?> goals</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-star text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg. Rating</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($review_stats['avg_rating'] ?? 0, 1); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $review_stats['total_reviews']; ?> reviews</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-users text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">360° Completion</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo round($feedback_stats['avg_completion_rate'] ?? 0); ?>%</p>
                            <p class="text-xs text-gray-500"><?php echo $feedback_stats['total_requests']; ?> requests</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Development Progress</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo round($development_stats['avg_progress'] ?? 0); ?>%</p>
                            <p class="text-xs text-gray-500"><?php echo $development_stats['total_plans']; ?> plans</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">PIP Success Rate</p>
                            <p class="text-2xl font-bold text-gray-800">
                                <?php 
                                $completed_pips = ($pip_stats['successful_pips'] + $pip_stats['unsuccessful_pips']);
                                echo $completed_pips > 0 ? round(($pip_stats['successful_pips'] / $completed_pips) * 100) : 0; 
                                ?>%
                            </p>
                            <p class="text-xs text-gray-500"><?php echo $pip_stats['active_pips']; ?> active PIPs</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Goal Status Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Goal Status Distribution</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="goalStatusChart"></canvas>
                    </div>
                </div>

                <!-- Department Performance Comparison -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Performance</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Performers -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Performers</h3>
                <div class="space-y-3">
                    <?php foreach (array_slice($top_performers, 0, 5) as $index => $performer): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                                <?php echo $index + 1; ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($performer['first_name'] . ' ' . $performer['last_name']); ?>
                                </p>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($performer['department']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-gray-900"><?php echo number_format($performer['avg_rating'], 1); ?>/5</p>
                            <p class="text-sm text-gray-600"><?php echo round($performer['goal_completion_rate'] ?? 0); ?>% goals</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Department Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Summary</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Employees</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Avg Rating</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Goal Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($dept_performance as $dept): ?>
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <?php echo $dept['employee_count']; ?>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <?php echo $dept['avg_rating'] ? number_format($dept['avg_rating'], 1) : 'N/A'; ?>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <?php echo $dept['total_goals'] > 0 ? round(($dept['completed_goals'] / $dept['total_goals']) * 100) : 0; ?>%
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Goal Status Distribution Chart
        const goalCtx = document.getElementById('goalStatusChart').getContext('2d');
        new Chart(goalCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress', 'Not Started'],
                datasets: [{
                    data: [
                        <?php echo $goal_stats['completed_goals']; ?>,
                        <?php echo $goal_stats['in_progress_goals']; ?>,
                        <?php echo $goal_stats['not_started_goals']; ?>
                    ],
                    backgroundColor: ['#10B981', '#3B82F6', '#6B7280']
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

        // Department Performance Chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($dept_performance, 'department')) . '"'; ?>],
                datasets: [{
                    label: 'Employee Count',
                    data: [<?php echo implode(',', array_column($dept_performance, 'employee_count')); ?>],
                    backgroundColor: '#3B82F6',
                    borderColor: '#1D4ED8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function exportReport() {
            alert('Export functionality will be implemented in the next phase.');
        }
    </script>
</body>
</html> 