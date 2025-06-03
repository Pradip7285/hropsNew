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
$interviewer_id = $_GET['interviewer_id'] ?? '';

// Build filter conditions
$where_conditions = ["DATE(i.scheduled_date) BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if (!empty($department)) {
    $where_conditions[] = "j.department = ?";
    $params[] = $department;
}

if (!empty($interviewer_id)) {
    $where_conditions[] = "i.interviewer_id = ?";
    $params[] = $interviewer_id;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Interview Overview Statistics
$overview_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_interviews,
        SUM(CASE WHEN i.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
        SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN i.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        SUM(CASE WHEN i.status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_count,
        AVG(i.duration) as avg_duration,
        COUNT(DISTINCT i.candidate_id) as unique_candidates,
        COUNT(DISTINCT i.interviewer_id) as active_interviewers
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    $where_clause
");
$overview_stmt->execute($params);
$overview = $overview_stmt->fetch(PDO::FETCH_ASSOC);

// Interview Success Rate (based on feedback)
$success_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_with_feedback,
        SUM(CASE WHEN if.recommendation IN ('strong_hire', 'hire') THEN 1 ELSE 0 END) as positive_feedback,
        SUM(CASE WHEN if.recommendation = 'neutral' THEN 1 ELSE 0 END) as neutral_feedback,
        SUM(CASE WHEN if.recommendation IN ('no_hire', 'strong_no_hire') THEN 1 ELSE 0 END) as negative_feedback,
        AVG(if.overall_rating) as avg_overall_rating,
        AVG(if.technical_rating) as avg_technical_rating,
        AVG(if.communication_rating) as avg_communication_rating,
        AVG(if.cultural_fit_rating) as avg_cultural_fit_rating
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN interview_feedback if ON i.id = if.interview_id
    $where_clause
");
$success_stmt->execute($params);
$success_metrics = $success_stmt->fetch(PDO::FETCH_ASSOC);

// Interview Type Distribution
$type_stmt = $conn->prepare("
    SELECT 
        i.interview_type,
        COUNT(*) as count,
        AVG(i.duration) as avg_duration
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    $where_clause
    GROUP BY i.interview_type
    ORDER BY count DESC
");
$type_stmt->execute($params);
$type_distribution = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

// Department-wise Statistics
$dept_stmt = $conn->prepare("
    SELECT 
        j.department,
        COUNT(*) as interview_count,
        SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        COUNT(DISTINCT i.candidate_id) as unique_candidates,
        AVG(CASE WHEN if.overall_rating IS NOT NULL THEN if.overall_rating END) as avg_rating
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    LEFT JOIN interview_feedback if ON i.id = if.interview_id
    $where_clause
    GROUP BY j.department
    ORDER BY interview_count DESC
");
$dept_stmt->execute($params);
$department_stats = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Interviewer Performance
$interviewer_stmt = $conn->prepare("
    SELECT 
        u.first_name, u.last_name, u.id,
        COUNT(*) as interview_count,
        SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        AVG(CASE WHEN if.overall_rating IS NOT NULL THEN if.overall_rating END) as avg_rating,
        SUM(CASE WHEN if.recommendation IN ('strong_hire', 'hire') THEN 1 ELSE 0 END) as positive_recommendations
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN users u ON i.interviewer_id = u.id
    LEFT JOIN interview_feedback if ON i.id = if.interview_id
    $where_clause
    GROUP BY u.id, u.first_name, u.last_name
    ORDER BY interview_count DESC
    LIMIT 10
");
$interviewer_stmt->execute($params);
$interviewer_performance = $interviewer_stmt->fetchAll(PDO::FETCH_ASSOC);

// Time-based Analysis (by week)
$timeline_stmt = $conn->prepare("
    SELECT 
        WEEK(i.scheduled_date) as week_number,
        YEAR(i.scheduled_date) as year,
        DATE(DATE_SUB(i.scheduled_date, INTERVAL DAYOFWEEK(i.scheduled_date)-1 DAY)) as week_start,
        COUNT(*) as interview_count,
        SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    $where_clause
    GROUP BY YEAR(i.scheduled_date), WEEK(i.scheduled_date)
    ORDER BY year DESC, week_number DESC
    LIMIT 12
");
$timeline_stmt->execute($params);
$timeline_data = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments and interviewers for filters
$departments_stmt = $conn->query("SELECT DISTINCT department FROM job_postings ORDER BY department");
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);

$interviewers_stmt = $conn->query("
    SELECT DISTINCT u.id, u.first_name, u.last_name 
    FROM users u 
    JOIN interviews i ON u.id = i.interviewer_id 
    ORDER BY u.first_name, u.last_name
");
$interviewers = $interviewers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentages for charts
$total_interviews = $overview['total_interviews'] ?: 1; // Avoid division by zero
$completion_rate = round(($overview['completed_count'] / $total_interviews) * 100, 1);
$cancellation_rate = round(($overview['cancelled_count'] / $total_interviews) * 100, 1);

$total_with_feedback = $success_metrics['total_with_feedback'] ?: 1;
$success_rate = round(($success_metrics['positive_feedback'] / $total_with_feedback) * 100, 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Reports - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Interview Analytics</h1>
                        <p class="text-gray-600">Comprehensive interview performance metrics and insights</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        <button onclick="exportReport()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-download mr-2"></i>Export
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Interviews</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($overview['total_interviews']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $overview['unique_candidates']; ?> unique candidates</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completion Rate</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $completion_rate; ?>%</p>
                            <p class="text-xs text-gray-500"><?php echo $overview['completed_count']; ?> completed</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-star text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Success Rate</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $success_rate; ?>%</p>
                            <p class="text-xs text-gray-500">Based on feedback</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg Duration</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo round($overview['avg_duration'] ?? 0); ?> min</p>
                            <p class="text-xs text-gray-500"><?php echo $overview['active_interviewers']; ?> interviewers</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Interview Status Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Interview Status Distribution</h3>
                    <canvas id="statusChart" width="400" height="200"></canvas>
                </div>

                <!-- Interview Types -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Interview Types</h3>
                    <canvas id="typeChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Rating Averages -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Average Ratings</h3>
                <canvas id="ratingsChart" width="800" height="300"></canvas>
            </div>

            <!-- Interview Timeline -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Interview Timeline (Weekly)</h3>
                <canvas id="timelineChart" width="800" height="300"></canvas>
            </div>

            <!-- Department Statistics -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Department Statistics</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interviews</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidates</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($department_stats as $dept): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($dept['department']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $dept['interview_count']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $dept['completed_count']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $dept['unique_candidates']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $dept['avg_rating'] ? number_format($dept['avg_rating'], 1) . '/5' : 'N/A'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo round(($dept['completed_count'] / $dept['interview_count']) * 100, 1); ?>%
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Interviewers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Top Interviewers</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interviewer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interviews</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Rating</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Positive Recommendations</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($interviewer_performance as $interviewer): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($interviewer['first_name'] . ' ' . $interviewer['last_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $interviewer['interview_count']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $interviewer['completed_count']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $interviewer['avg_rating'] ? number_format($interviewer['avg_rating'], 1) . '/5' : 'N/A'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $interviewer['positive_recommendations'] ?? 0; ?></div>
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
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Scheduled', 'Cancelled', 'Rescheduled'],
                datasets: [{
                    data: [
                        <?php echo $overview['completed_count']; ?>,
                        <?php echo $overview['scheduled_count']; ?>,
                        <?php echo $overview['cancelled_count']; ?>,
                        <?php echo $overview['rescheduled_count']; ?>
                    ],
                    backgroundColor: ['#10B981', '#3B82F6', '#EF4444', '#F59E0B']
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

        // Interview Types Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($type_distribution as $type): ?>
                    '<?php echo ucfirst(str_replace('_', ' ', $type['interview_type'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Count',
                    data: [
                        <?php foreach ($type_distribution as $type): ?>
                        <?php echo $type['count']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#8B5CF6'
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
                        beginAtZero: true
                    }
                }
            }
        });

        // Ratings Chart
        const ratingsCtx = document.getElementById('ratingsChart').getContext('2d');
        new Chart(ratingsCtx, {
            type: 'radar',
            data: {
                labels: ['Overall', 'Technical', 'Communication', 'Cultural Fit'],
                datasets: [{
                    label: 'Average Rating',
                    data: [
                        <?php echo number_format($success_metrics['avg_overall_rating'] ?? 0, 1); ?>,
                        <?php echo number_format($success_metrics['avg_technical_rating'] ?? 0, 1); ?>,
                        <?php echo number_format($success_metrics['avg_communication_rating'] ?? 0, 1); ?>,
                        <?php echo number_format($success_metrics['avg_cultural_fit_rating'] ?? 0, 1); ?>
                    ],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: '#3B82F6',
                    pointBackgroundColor: '#3B82F6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 5
                    }
                }
            }
        });

        // Timeline Chart
        const timelineCtx = document.getElementById('timelineChart').getContext('2d');
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach (array_reverse($timeline_data) as $week): ?>
                    'Week of <?php echo date('M j', strtotime($week['week_start'])); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Total Interviews',
                    data: [
                        <?php foreach (array_reverse($timeline_data) as $week): ?>
                        <?php echo $week['interview_count']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Completed Interviews',
                    data: [
                        <?php foreach (array_reverse($timeline_data) as $week): ?>
                        <?php echo $week['completed_count']; ?>,
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
            // Create a simple CSV export
            const csvContent = "data:text/csv;charset=utf-8," 
                + "Department,Interviews,Completed,Candidates,Avg Rating,Completion Rate\n"
                + <?php 
                $csv_rows = [];
                foreach ($department_stats as $dept) {
                    $completion_rate = round(($dept['completed_count'] / $dept['interview_count']) * 100, 1);
                    $avg_rating = $dept['avg_rating'] ? number_format($dept['avg_rating'], 1) : 'N/A';
                    $csv_rows[] = "\"" . $dept['department'] . "\"," . $dept['interview_count'] . "," . $dept['completed_count'] . "," . $dept['unique_candidates'] . "," . $avg_rating . "," . $completion_rate;
                }
                echo json_encode(implode("\\n", $csv_rows));
                ?>;

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "interview_report_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html> 