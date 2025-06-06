<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

// Get date range from parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$department = $_GET['department'] ?? '';

// Build filter conditions
$where_conditions = ["DATE(o.created_at) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if (!empty($department)) {
    $where_conditions[] = "j.department = ?";
    $params[] = $department;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Overview Statistics
$overview_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_offers,
        SUM(CASE WHEN o.status = 'draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN o.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
        SUM(CASE WHEN o.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN o.status = 'expired' THEN 1 ELSE 0 END) as expired_count,
        SUM(CASE WHEN o.status = 'negotiating' THEN 1 ELSE 0 END) as negotiating_count,
        AVG(o.salary_offered) as avg_salary,
        MIN(o.salary_offered) as min_salary,
        MAX(o.salary_offered) as max_salary,
        AVG(CASE WHEN o.candidate_response_at IS NOT NULL THEN 
            DATEDIFF(o.candidate_response_at, o.created_at) END) as avg_response_time_days
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    $where_clause
");
$overview_stmt->execute($params);
$overview = $overview_stmt->fetch(PDO::FETCH_ASSOC);

// Acceptance Rate Analysis
$acceptance_rate = 0;
$rejection_rate = 0;
$response_offers = $overview['accepted_count'] + $overview['rejected_count'];
if ($response_offers > 0) {
    $acceptance_rate = round(($overview['accepted_count'] / $response_offers) * 100, 1);
    $rejection_rate = round(($overview['rejected_count'] / $response_offers) * 100, 1);
}

// Department-wise Statistics
$dept_stmt = $conn->prepare("
    SELECT 
        j.department,
        COUNT(*) as offer_count,
        SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
        SUM(CASE WHEN o.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        AVG(o.salary_offered) as avg_salary,
        AVG(CASE WHEN o.candidate_response_at IS NOT NULL THEN 
            DATEDIFF(o.candidate_response_at, o.created_at) END) as avg_response_time
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    $where_clause
    GROUP BY j.department
    ORDER BY offer_count DESC
");
$dept_stmt->execute($params);
$department_stats = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Salary Analysis by Job Level/Department
$salary_analysis_stmt = $conn->prepare("
    SELECT 
        j.department,
        j.title,
        COUNT(*) as offer_count,
        AVG(o.salary_offered) as avg_salary,
        MIN(o.salary_offered) as min_salary,
        MAX(o.salary_offered) as max_salary,
        SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    $where_clause
    GROUP BY j.department, j.title
    HAVING offer_count >= 2
    ORDER BY j.department, avg_salary DESC
");
$salary_analysis_stmt->execute($params);
$salary_analysis = $salary_analysis_stmt->fetchAll(PDO::FETCH_ASSOC);

// Response Time Analysis
$response_time_stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN DATEDIFF(o.candidate_response_at, o.created_at) <= 1 THEN '1 day'
            WHEN DATEDIFF(o.candidate_response_at, o.created_at) <= 3 THEN '2-3 days'
            WHEN DATEDIFF(o.candidate_response_at, o.created_at) <= 7 THEN '4-7 days'
            WHEN DATEDIFF(o.candidate_response_at, o.created_at) <= 14 THEN '1-2 weeks'
            ELSE '2+ weeks'
        END as response_timeframe,
        COUNT(*) as count,
        SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_in_timeframe
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    $where_clause
    AND o.candidate_response_at IS NOT NULL
    GROUP BY response_timeframe
    ORDER BY MIN(DATEDIFF(o.candidate_response_at, o.created_at))
");
$response_time_stmt->execute($params);
$response_time_data = $response_time_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Trends
$monthly_trends_stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        COUNT(*) as total_offers,
        SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_offers,
        SUM(CASE WHEN o.status = 'rejected' THEN 1 ELSE 0 END) as rejected_offers,
        AVG(o.salary_offered) as avg_salary
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    " . (!empty($department) ? "AND j.department = ?" : "") . "
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");

if (!empty($department)) {
    $monthly_trends_stmt->execute([$department]);
} else {
    $monthly_trends_stmt->execute();
}
$monthly_trends = $monthly_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Negotiation Analysis (simplified fallback)
try {
    $negotiation_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_negotiations,
            AVG(o.salary_offered) as avg_negotiated_salary,
            COUNT(CASE WHEN o.status = 'accepted' THEN 1 END) as successful_negotiations
        FROM offers o
        JOIN candidates c ON o.candidate_id = c.id
        JOIN job_postings j ON o.job_id = j.id
        $where_clause
    ");
    $negotiation_stmt->execute($params);
    $negotiation_stats = $negotiation_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback data
    $negotiation_stats = [
        'total_negotiations' => 0,
        'avg_negotiated_salary' => 0,
        'successful_negotiations' => 0
    ];
}

// Get departments for filter
$departments_stmt = $conn->query("SELECT DISTINCT department FROM job_postings ORDER BY department");
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);

// Template Usage Analysis
$template_usage_stmt = $conn->prepare("
    SELECT 
        COALESCE(ot.name, 'Default Template') as template_name,
        COUNT(*) as usage_count,
        SUM(CASE WHEN o.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
        AVG(o.salary_offered) as avg_salary
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    LEFT JOIN offer_templates ot ON o.template_id = ot.id
    $where_clause
    GROUP BY o.template_id, ot.name
    ORDER BY usage_count DESC
");
$template_usage_stmt->execute($params);
$template_usage = $template_usage_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Analytics - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Offer Analytics</h1>
                        <p class="text-gray-600">Comprehensive offer performance metrics and insights</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Offers
                        </a>
                        <button onclick="exportReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Overview Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-file-contract text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Offers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($overview['total_offers']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Acceptance Rate</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $acceptance_rate; ?>%</p>
                            <p class="text-xs text-gray-500"><?php echo $overview['accepted_count']; ?> accepted</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg Salary</p>
                            <p class="text-2xl font-bold text-gray-800">$<?php echo number_format($overview['avg_salary'] ?? 0, 0); ?></p>
                            <p class="text-xs text-gray-500">Range: $<?php echo number_format($overview['min_salary'] ?? 0, 0); ?> - $<?php echo number_format($overview['max_salary'] ?? 0, 0); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg Response Time</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo round($overview['avg_response_time_days'] ?? 0, 1); ?></p>
                            <p class="text-xs text-gray-500">days</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-handshake text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Negotiations</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $overview['negotiating_count']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $negotiation_stats['successful_negotiations'] ?? 0; ?> successful</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Offer Status Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Offer Status Distribution</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Acceptance Rate by Department -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Acceptance Rate by Department</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="deptAcceptanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Response Time Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Response Time Distribution</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="responseTimeChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Trends -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Offer Trends</h3>
                    <div style="height: 300px; position: relative;">
                        <canvas id="monthlyTrendsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Department Statistics Table -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Performance</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Offers</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Accepted</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acceptance Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Salary</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Response Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($department_stats as $dept): ?>
                            <?php 
                            $dept_acceptance_rate = ($dept['accepted_count'] + $dept['rejected_count'] > 0) 
                                ? round(($dept['accepted_count'] / ($dept['accepted_count'] + $dept['rejected_count'])) * 100, 1) 
                                : 0;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($dept['department']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $dept['offer_count']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $dept['accepted_count']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $dept_acceptance_rate; ?>%</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $dept_acceptance_rate; ?>%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">$<?php echo number_format($dept['avg_salary'] ?? 0, 0); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo round($dept['avg_response_time'] ?? 0, 1); ?> days</div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Template Usage Analysis -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Template Performance</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($template_usage as $template): ?>
                    <?php 
                    $template_acceptance_rate = $template['usage_count'] > 0 
                        ? round(($template['accepted_count'] / $template['usage_count']) * 100, 1) 
                        : 0;
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($template['template_name']); ?></h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Used:</span>
                                <span class="font-medium"><?php echo $template['usage_count']; ?> times</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Accepted:</span>
                                <span class="font-medium"><?php echo $template['accepted_count']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Success Rate:</span>
                                <span class="font-medium text-green-600"><?php echo $template_acceptance_rate; ?>%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Avg Salary:</span>
                                <span class="font-medium">$<?php echo number_format($template['avg_salary'] ?? 0, 0); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Offer Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Accepted', 'Rejected', 'Sent', 'Draft', 'Expired', 'Negotiating'],
                datasets: [{
                    data: [
                        <?php echo $overview['accepted_count']; ?>,
                        <?php echo $overview['rejected_count']; ?>,
                        <?php echo $overview['sent_count']; ?>,
                        <?php echo $overview['draft_count']; ?>,
                        <?php echo $overview['expired_count']; ?>,
                        <?php echo $overview['negotiating_count']; ?>
                    ],
                    backgroundColor: ['#10B981', '#EF4444', '#3B82F6', '#F59E0B', '#6B7280', '#8B5CF6']
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

        // Department Acceptance Rate Chart
        const deptCtx = document.getElementById('deptAcceptanceChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($department_stats as $dept): ?>
                    '<?php echo htmlspecialchars($dept['department']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Acceptance Rate (%)',
                    data: [
                        <?php foreach ($department_stats as $dept): ?>
                        <?php 
                        $rate = ($dept['accepted_count'] + $dept['rejected_count'] > 0) 
                            ? round(($dept['accepted_count'] / ($dept['accepted_count'] + $dept['rejected_count'])) * 100, 1) 
                            : 0;
                        echo $rate;
                        ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#10B981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // Response Time Chart
        const responseTimeCtx = document.getElementById('responseTimeChart').getContext('2d');
        new Chart(responseTimeCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($response_time_data as $data): ?>
                    '<?php echo $data['response_timeframe']; ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total Responses',
                    data: [
                        <?php foreach ($response_time_data as $data): ?>
                        <?php echo $data['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#3B82F6'
                }, {
                    label: 'Accepted',
                    data: [
                        <?php foreach ($response_time_data as $data): ?>
                        <?php echo $data['accepted_in_timeframe']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#10B981'
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

        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach (array_reverse($monthly_trends) as $trend): ?>
                    '<?php echo date('M Y', strtotime($trend['month'] . '-01')); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total Offers',
                    data: [
                        <?php foreach (array_reverse($monthly_trends) as $trend): ?>
                        <?php echo $trend['total_offers']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Accepted Offers',
                    data: [
                        <?php foreach (array_reverse($monthly_trends) as $trend): ?>
                        <?php echo $trend['accepted_offers']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
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
            // Create CSV export
            const csvContent = "data:text/csv;charset=utf-8," 
                + "Department,Total Offers,Accepted,Acceptance Rate,Avg Salary,Avg Response Time\n"
                + <?php 
                $csv_rows = [];
                foreach ($department_stats as $dept) {
                    $acceptance_rate = ($dept['accepted_count'] + $dept['rejected_count'] > 0) 
                        ? round(($dept['accepted_count'] / ($dept['accepted_count'] + $dept['rejected_count'])) * 100, 1) 
                        : 0;
                    $csv_rows[] = "\"" . $dept['department'] . "\"," . $dept['offer_count'] . "," . $dept['accepted_count'] . "," . $acceptance_rate . "%," . number_format($dept['avg_salary'] ?? 0, 0) . "," . round($dept['avg_response_time'] ?? 0, 1);
                }
                echo json_encode(implode("\\n", $csv_rows));
                ?>;

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "offer_analytics_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html> 