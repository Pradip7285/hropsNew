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
$employee_filter = $_GET['employee'] ?? '';

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

// Build WHERE clause for filters
$where_conditions = ["DATE(created_at) BETWEEN ? AND ?"];
$params = [$start_date, $end_date];

if (!empty($department_filter)) {
    $where_conditions[] = "department = ?";
    $params[] = $department_filter;
}

// Performance Overview Statistics
$overview_stats = [];

// Goal Achievement Statistics
$goal_stats_query = "
    SELECT 
        COUNT(*) as total_goals,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_goals,
        SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started_goals,
        AVG(progress_percentage) as avg_progress
    FROM performance_goals pg
    JOIN employees e ON pg.employee_id = e.id
    WHERE " . implode(" AND ", $where_conditions);

$goal_stmt = $conn->prepare($goal_stats_query);
$goal_stmt->execute($params);
$goal_stats = $goal_stmt->fetch(PDO::FETCH_ASSOC);

// Review Completion Statistics
$review_stats_query = "
    SELECT 
        COUNT(*) as total_reviews,
        SUM(CASE WHEN pr.status = 'completed' THEN 1 ELSE 0 END) as completed_reviews,
        SUM(CASE WHEN pr.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_reviews,
        AVG(pr.overall_rating) as avg_rating
    FROM performance_reviews pr
    JOIN employees e ON pr.employee_id = e.id
    WHERE " . implode(" AND ", $where_conditions);

$review_stmt = $conn->prepare($review_stats_query);
$review_stmt->execute($params);
$review_stats = $review_stmt->fetch(PDO::FETCH_ASSOC);

// 360 Feedback Statistics
$feedback_stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN fr.status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
        AVG(
            (SELECT COUNT(*) FROM feedback_360_providers WHERE request_id = fr.id AND status = 'completed') * 100.0 / 
            NULLIF((SELECT COUNT(*) FROM feedback_360_providers WHERE request_id = fr.id), 0)
        ) as avg_completion_rate
    FROM feedback_360_requests fr
    JOIN employees e ON fr.employee_id = e.id
    WHERE " . implode(" AND ", $where_conditions);

$feedback_stmt = $conn->prepare($feedback_stats_query);
$feedback_stmt->execute($params);
$feedback_stats = $feedback_stmt->fetch(PDO::FETCH_ASSOC);

// Development Plans Statistics
$development_stats_query = "
    SELECT 
        COUNT(*) as total_plans,
        SUM(CASE WHEN dp.status = 'active' THEN 1 ELSE 0 END) as active_plans,
        SUM(CASE WHEN dp.status = 'completed' THEN 1 ELSE 0 END) as completed_plans,
        AVG(
            (SELECT AVG(progress_percentage) FROM development_goals WHERE plan_id = dp.id)
        ) as avg_progress
    FROM development_plans dp
    JOIN employees e ON dp.employee_id = e.id
    WHERE " . implode(" AND ", $where_conditions);

$development_stmt = $conn->prepare($development_stats_query);
$development_stmt->execute($params);
$development_stats = $development_stmt->fetch(PDO::FETCH_ASSOC);

// Performance Improvement Plans Statistics
$pip_stats_query = "
    SELECT 
        COUNT(*) as total_pips,
        SUM(CASE WHEN pip.status = 'active' THEN 1 ELSE 0 END) as active_pips,
        SUM(CASE WHEN pip.status = 'completed_successful' THEN 1 ELSE 0 END) as successful_pips,
        SUM(CASE WHEN pip.status = 'completed_unsuccessful' THEN 1 ELSE 0 END) as unsuccessful_pips
    FROM performance_improvement_plans pip
    JOIN employees e ON pip.employee_id = e.id
    WHERE " . implode(" AND ", $where_conditions);

$pip_stmt = $conn->prepare($pip_stats_query);
$pip_stmt->execute($params);
$pip_stats = $pip_stmt->fetch(PDO::FETCH_ASSOC);

// Department Performance Comparison
$dept_performance_query = "
    SELECT 
        e.department,
        COUNT(DISTINCT e.id) as employee_count,
        AVG(pr.overall_rating) as avg_rating,
        COUNT(pg.id) as total_goals,
        SUM(CASE WHEN pg.status = 'completed' THEN 1 ELSE 0 END) as completed_goals
    FROM employees e
    LEFT JOIN performance_reviews pr ON e.id = pr.employee_id 
        AND DATE(pr.created_at) BETWEEN ? AND ?
    LEFT JOIN performance_goals pg ON e.id = pg.employee_id 
        AND DATE(pg.created_at) BETWEEN ? AND ?
    WHERE e.status = 'active'
    GROUP BY e.department
    ORDER BY avg_rating DESC";

$dept_stmt = $conn->prepare($dept_performance_query);
$dept_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$dept_performance = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Performance Trends
$monthly_trends_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        'goals' as type,
        COUNT(*) as count,
        AVG(progress_percentage) as avg_value
    FROM performance_goals pg
    JOIN employees e ON pg.employee_id = e.id
    WHERE DATE(pg.created_at) BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    
    UNION ALL
    
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        'reviews' as type,
        COUNT(*) as count,
        AVG(overall_rating) as avg_value
    FROM performance_reviews pr
    JOIN employees e ON pr.employee_id = e.id
    WHERE DATE(pr.created_at) BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    
    ORDER BY month, type";

$trends_stmt = $conn->prepare($monthly_trends_query);
$trends_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$monthly_trends = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Performers
$top_performers_query = "
    SELECT 
        e.first_name, e.last_name, e.department, e.position,
        AVG(pr.overall_rating) as avg_rating,
        COUNT(pg.id) as total_goals,
        SUM(CASE WHEN pg.status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
        (SUM(CASE WHEN pg.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / 
         NULLIF(COUNT(pg.id), 0)) as goal_completion_rate
    FROM employees e
    LEFT JOIN performance_reviews pr ON e.id = pr.employee_id 
        AND DATE(pr.created_at) BETWEEN ? AND ?
    LEFT JOIN performance_goals pg ON e.id = pg.employee_id 
        AND DATE(pg.created_at) BETWEEN ? AND ?
    WHERE e.status = 'active'
    GROUP BY e.id
    HAVING avg_rating IS NOT NULL
    ORDER BY avg_rating DESC, goal_completion_rate DESC
    LIMIT 10";

$top_performers_stmt = $conn->prepare($top_performers_query);
$top_performers_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
$top_performers = $top_performers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$departments = $conn->query("SELECT DISTINCT department FROM employees WHERE status = 'active' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
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
                        <a href="custom_reports.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-chart-line mr-2"></i>Custom Reports
                        </a>
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
                            <p class="text-xs text-gray-500"><?php echo $review_stats['completed_reviews']; ?> reviews</p>
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
                            <p class="text-xs text-gray-500"><?php echo $feedback_stats['completed_requests']; ?> requests</p>
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
                            <p class="text-xs text-gray-500"><?php echo $development_stats['active_plans']; ?> active plans</p>
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
                    <canvas id="goalStatusChart" width="400" height="200"></canvas>
                </div>

                <!-- Department Performance Comparison -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Performance</h3>
                    <canvas id="departmentChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Performance Trends -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Performance Trends Over Time</h3>
                <canvas id="trendsChart" width="400" height="150"></canvas>
            </div>

            <!-- Additional Analytics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Top Performers -->
                <div class="bg-white rounded-lg shadow-md p-6">
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

                <!-- Performance Summary by Department -->
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
            </div>

            <!-- Export Options -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Export & Reports</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <button onclick="exportData('goals')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-download mr-2"></i>Goals Report
                    </button>
                    <button onclick="exportData('reviews')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-download mr-2"></i>Reviews Report
                    </button>
                    <button onclick="exportData('feedback')" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-download mr-2"></i>360° Feedback Report
                    </button>
                    <button onclick="exportData('development')" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-download mr-2"></i>Development Report
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Goal Status Chart
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
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                    borderWidth: 2
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
                    label: 'Average Rating',
                    data: [<?php echo implode(',', array_map(function($d) { return $d['avg_rating'] ?? 0; }, $dept_performance)); ?>],
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
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });

        // Performance Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        
        // Process monthly trends data
        const monthlyData = {};
        <?php foreach ($monthly_trends as $trend): ?>
        if (!monthlyData['<?php echo $trend['month']; ?>']) {
            monthlyData['<?php echo $trend['month']; ?>'] = {};
        }
        monthlyData['<?php echo $trend['month']; ?>']['<?php echo $trend['type']; ?>'] = <?php echo $trend['avg_value']; ?>;
        <?php endforeach; ?>

        const months = Object.keys(monthlyData).sort();
        const goalData = months.map(month => monthlyData[month]['goals'] || 0);
        const reviewData = months.map(month => monthlyData[month]['reviews'] || 0);

        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Goal Progress (%)',
                    data: goalData,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Review Rating (1-5)',
                    data: reviewData,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
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
            const params = new URLSearchParams(window.location.search);
            window.open(`export_performance_report.php?${params.toString()}`, '_blank');
        }

        function exportData(type) {
            const params = new URLSearchParams(window.location.search);
            params.append('export_type', type);
            window.open(`export_performance_data.php?${params.toString()}`, '_blank');
        }
    </script>
</body>
</html> 