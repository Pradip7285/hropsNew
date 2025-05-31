<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

// Date range filter
$date_range = $_GET['date_range'] ?? '30_days';
$custom_start = $_GET['custom_start'] ?? '';
$custom_end = $_GET['custom_end'] ?? '';

// Calculate date range
switch ($date_range) {
    case '7_days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case '30_days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        break;
    case '90_days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = date('Y-m-d');
        break;
    case 'custom':
        $start_date = $custom_start ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $custom_end ?: date('Y-m-d');
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
}

// Key Metrics
$metrics = [];

// Total Applications
$metrics['total_applications'] = $conn->query("
    SELECT COUNT(*) as count FROM candidates 
    WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
")->fetch()['count'];

// Total Interviews
$metrics['total_interviews'] = $conn->query("
    SELECT COUNT(*) as count FROM interviews 
    WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
")->fetch()['count'];

// Total Offers
$metrics['total_offers'] = $conn->query("
    SELECT COUNT(*) as count FROM offers 
    WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
")->fetch()['count'];

// Total Hires
$metrics['total_hires'] = $conn->query("
    SELECT COUNT(*) as count FROM offers 
    WHERE status = 'accepted' AND created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
")->fetch()['count'];

// Conversion Rates
$metrics['application_to_interview'] = $metrics['total_applications'] > 0 ? 
    round(($metrics['total_interviews'] / $metrics['total_applications']) * 100, 1) : 0;
$metrics['interview_to_offer'] = $metrics['total_interviews'] > 0 ? 
    round(($metrics['total_offers'] / $metrics['total_interviews']) * 100, 1) : 0;
$metrics['offer_to_hire'] = $metrics['total_offers'] > 0 ? 
    round(($metrics['total_hires'] / $metrics['total_offers']) * 100, 1) : 0;

// Time to Hire (average days from application to offer acceptance)
$time_to_hire = $conn->query("
    SELECT AVG(DATEDIFF(o.created_at, c.created_at)) as avg_days
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    WHERE o.status = 'accepted' 
    AND o.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
")->fetch()['avg_days'];
$metrics['time_to_hire'] = round($time_to_hire ?? 0, 1);

// Cost per Hire (placeholder - would be calculated based on actual costs)
$metrics['cost_per_hire'] = 2500; // Example value

// Source Effectiveness
$source_data = $conn->query("
    SELECT source, 
           COUNT(*) as applications,
           COUNT(CASE WHEN status IN ('interviewing', 'offered', 'hired') THEN 1 END) as qualified,
           COUNT(CASE WHEN status = 'hired' THEN 1 END) as hired
    FROM candidates 
    WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
    GROUP BY source
    ORDER BY applications DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Department Performance
$department_data = $conn->query("
    SELECT j.department,
           COUNT(c.id) as applications,
           COUNT(CASE WHEN c.status = 'hired' THEN 1 END) as hires,
           AVG(DATEDIFF(o.created_at, c.created_at)) as avg_time_to_hire
    FROM candidates c
    JOIN job_postings j ON c.applied_for = j.id
    LEFT JOIN offers o ON c.id = o.candidate_id AND o.status = 'accepted'
    WHERE c.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
    GROUP BY j.department
    ORDER BY applications DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Interview Performance
$interview_stats = $conn->query("
    SELECT 
        COUNT(*) as total_interviews,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
        AVG(CASE WHEN feedback_id IS NOT NULL THEN 1 ELSE 0 END) * 100 as feedback_rate
    FROM interviews 
    WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
")->fetch();

// Onboarding Progress
$onboarding_stats = $conn->query("
    SELECT 
        COUNT(*) as total_employees,
        COUNT(CASE WHEN onboarding_status = 'completed' THEN 1 END) as completed,
        AVG(CASE WHEN onboarding_status = 'completed' THEN 
            DATEDIFF(completion_date, start_date) ELSE NULL END) as avg_completion_days
    FROM employees 
    WHERE start_date BETWEEN '$start_date' AND '$end_date 23:59:59'
")->fetch();

// Top Performers (Interviewers with highest feedback scores)
$top_interviewers = $conn->query("
    SELECT u.first_name, u.last_name,
           COUNT(i.id) as interviews_conducted,
           AVG(f.overall_rating) as avg_rating
    FROM users u
    JOIN interviews i ON u.id = i.interviewer_id
    LEFT JOIN interview_feedback f ON i.id = f.interview_id
    WHERE i.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
    GROUP BY u.id
    HAVING interviews_conducted >= 3
    ORDER BY avg_rating DESC, interviews_conducted DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Monthly Trends (last 12 months)
$monthly_trends = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(CASE WHEN 'candidates' THEN 1 END) as applications,
        COUNT(CASE WHEN 'offers' THEN 1 END) as offers,
        COUNT(CASE WHEN 'employees' THEN 1 END) as hires
    FROM (
        SELECT created_at, 'candidates' as type FROM candidates WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        UNION ALL
        SELECT created_at, 'offers' as type FROM offers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        UNION ALL
        SELECT start_date as created_at, 'employees' as type FROM employees WHERE start_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ) combined
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - <?php echo APP_NAME; ?></title>
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
                <h1 class="text-3xl font-bold text-gray-800">Analytics & Reports</h1>
                <p class="text-gray-600">Comprehensive recruitment and HR analytics dashboard</p>
            </div>

            <!-- Date Range Filter -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <select name="date_range" onchange="toggleCustomDates(this.value)" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="7_days" <?php echo $date_range == '7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30_days" <?php echo $date_range == '30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90_days" <?php echo $date_range == '90_days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="customDates" class="flex gap-4" style="display: <?php echo $date_range == 'custom' ? 'flex' : 'none'; ?>;">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" name="custom_start" value="<?php echo $custom_start; ?>"
                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" name="custom_end" value="<?php echo $custom_end; ?>"
                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-chart-line mr-2"></i>Update Report
                    </button>
                    
                    <button type="button" onclick="exportReport()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-download mr-2"></i>Export PDF
                    </button>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Applications</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($metrics['total_applications']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Interviews Conducted</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($metrics['total_interviews']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-file-contract text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Offers Extended</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($metrics['total_offers']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-3 rounded-full mr-4">
                            <i class="fas fa-user-plus text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">New Hires</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($metrics['total_hires']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Time to Hire</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $metrics['time_to_hire']; ?></p>
                    <p class="text-sm text-gray-500">Average days</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Cost per Hire</h3>
                    <p class="text-3xl font-bold text-green-600">$<?php echo number_format($metrics['cost_per_hire']); ?></p>
                    <p class="text-sm text-gray-500">Average cost</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Conversion Rate</h3>
                    <p class="text-3xl font-bold text-purple-600"><?php echo $metrics['application_to_interview']; ?>%</p>
                    <p class="text-sm text-gray-500">Application to Interview</p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Offer Acceptance</h3>
                    <p class="text-3xl font-bold text-orange-600"><?php echo $metrics['offer_to_hire']; ?>%</p>
                    <p class="text-sm text-gray-500">Offer to Hire Rate</p>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Recruitment Funnel -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recruitment Funnel</h3>
                    <canvas id="funnelChart" width="400" height="300"></canvas>
                </div>
                
                <!-- Source Effectiveness -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Source Effectiveness</h3>
                    <canvas id="sourceChart" width="400" height="300"></canvas>
                </div>
            </div>

            <!-- Monthly Trends -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Trends (Last 12 Months)</h3>
                <canvas id="trendsChart" width="800" height="400"></canvas>
            </div>

            <!-- Data Tables -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Department Performance -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Department Performance</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applications</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hires</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($department_data as $dept): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $dept['applications']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $dept['hires']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $dept['applications'] > 0 ? round(($dept['hires'] / $dept['applications']) * 100, 1) : 0; ?>%
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Top Interviewers -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">Top Performing Interviewers</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Interviewer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Interviews</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Rating</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($top_interviewers as $interviewer): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($interviewer['first_name'] . ' ' . $interviewer['last_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $interviewer['interviews_conducted']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center">
                                            <span class="mr-2"><?php echo round($interviewer['avg_rating'], 1); ?></span>
                                            <div class="flex text-yellow-400">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $interviewer['avg_rating'] ? '' : 'text-gray-300'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleCustomDates(value) {
            const customDates = document.getElementById('customDates');
            customDates.style.display = value === 'custom' ? 'flex' : 'none';
        }

        function exportReport() {
            // In a real implementation, this would generate and download a PDF report
            alert('PDF export functionality would be implemented here using libraries like jsPDF or server-side PDF generation.');
        }

        // Recruitment Funnel Chart
        const funnelCtx = document.getElementById('funnelChart').getContext('2d');
        new Chart(funnelCtx, {
            type: 'bar',
            data: {
                labels: ['Applications', 'Interviews', 'Offers', 'Hires'],
                datasets: [{
                    label: 'Count',
                    data: [
                        <?php echo $metrics['total_applications']; ?>,
                        <?php echo $metrics['total_interviews']; ?>,
                        <?php echo $metrics['total_offers']; ?>,
                        <?php echo $metrics['total_hires']; ?>
                    ],
                    backgroundColor: ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Source Effectiveness Chart
        const sourceCtx = document.getElementById('sourceChart').getContext('2d');
        new Chart(sourceCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(fn($s) => "'" . addslashes($s['source']) . "'", $source_data)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($source_data, 'applications')); ?>],
                    backgroundColor: [
                        '#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444', '#6B7280'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Monthly Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(fn($t) => "'" . $t['month'] . "'", array_reverse($monthly_trends))); ?>],
                datasets: [
                    {
                        label: 'Applications',
                        data: [<?php echo implode(',', array_column(array_reverse($monthly_trends), 'applications')); ?>],
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true
                    },
                    {
                        label: 'Offers',
                        data: [<?php echo implode(',', array_column(array_reverse($monthly_trends), 'offers')); ?>],
                        borderColor: '#8B5CF6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        fill: true
                    },
                    {
                        label: 'Hires',
                        data: [<?php echo implode(',', array_column(array_reverse($monthly_trends), 'hires')); ?>],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 