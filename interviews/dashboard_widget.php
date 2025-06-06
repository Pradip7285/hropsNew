<?php
/**
 * Interview Dashboard Widget
 * Provides interview statistics and quick access for the main dashboard
 */

function getInterviewDashboardData() {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Today's interviews
    $today_stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM interviews 
        WHERE DATE(scheduled_date) = CURDATE() 
        AND status IN ('scheduled', 'rescheduled')
    ");
    $today_count = $today_stmt->fetch()['count'];
    
    // This week's interviews
    $week_stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM interviews 
        WHERE WEEK(scheduled_date) = WEEK(CURDATE()) 
        AND YEAR(scheduled_date) = YEAR(CURDATE())
        AND status IN ('scheduled', 'rescheduled')
    ");
    $week_count = $week_stmt->fetch()['count'];
    
    // Pending feedback
    $feedback_stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM interviews i
        LEFT JOIN interview_feedback f ON i.id = f.interview_id
        WHERE i.status = 'completed'
        AND f.id IS NULL
    ");
    $pending_feedback = $feedback_stmt->fetch()['count'];
    
    // Overdue interviews
    $overdue_stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM interviews 
        WHERE scheduled_date < NOW() 
        AND status = 'scheduled'
    ");
    $overdue_count = $overdue_stmt->fetch()['count'];
    
    // Recent interviews (last 7 days)
    $recent_stmt = $conn->query("
        SELECT i.*, 
               c.first_name as candidate_first, c.last_name as candidate_last,
               j.title as job_title,
               u.first_name as interviewer_first, u.last_name as interviewer_last
        FROM interviews i
        JOIN candidates c ON i.candidate_id = c.id
        JOIN job_postings j ON i.job_id = j.id
        JOIN users u ON i.interviewer_id = u.id
        WHERE i.scheduled_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY i.scheduled_date DESC
        LIMIT 5
    ");
    $recent_interviews = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Upcoming interviews (next 7 days)
    $upcoming_stmt = $conn->query("
        SELECT i.*, 
               c.first_name as candidate_first, c.last_name as candidate_last,
               j.title as job_title,
               u.first_name as interviewer_first, u.last_name as interviewer_last,
               CASE 
                   WHEN i.scheduled_date < NOW() AND i.status = 'scheduled' THEN 'overdue'
                   ELSE i.status 
               END as display_status
        FROM interviews i
        JOIN candidates c ON i.candidate_id = c.id
        JOIN job_postings j ON i.job_id = j.id
        JOIN users u ON i.interviewer_id = u.id
        WHERE i.scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND i.status IN ('scheduled', 'rescheduled')
        ORDER BY i.scheduled_date ASC
        LIMIT 5
    ");
    $upcoming_interviews = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Interview success rate (this month)
    $success_stmt = $conn->query("
        SELECT 
            COUNT(*) as total_completed,
            SUM(CASE WHEN f.recommendation IN ('hire', 'strong_hire') THEN 1 ELSE 0 END) as positive_feedback
        FROM interviews i
        JOIN interview_feedback f ON i.id = f.interview_id
        WHERE MONTH(i.scheduled_date) = MONTH(CURDATE())
        AND YEAR(i.scheduled_date) = YEAR(CURDATE())
        AND i.status = 'completed'
    ");
    $success_data = $success_stmt->fetch();
    $success_rate = $success_data['total_completed'] > 0 
        ? round(($success_data['positive_feedback'] / $success_data['total_completed']) * 100, 1)
        : 0;
    
    return [
        'stats' => [
            'today' => $today_count,
            'this_week' => $week_count,
            'pending_feedback' => $pending_feedback,
            'overdue' => $overdue_count,
            'success_rate' => $success_rate
        ],
        'recent_interviews' => $recent_interviews,
        'upcoming_interviews' => $upcoming_interviews
    ];
}

function renderInterviewDashboardWidget() {
    $data = getInterviewDashboardData();
    $stats = $data['stats'];
    $recent = $data['recent_interviews'];
    $upcoming = $data['upcoming_interviews'];
    ?>
    
    <!-- Interview Management Widget -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">
                    <i class="fas fa-calendar-check mr-2"></i>Interview Management
                </h3>
                <a href="<?php echo BASE_URL; ?>/interviews/list.php" 
                   class="text-blue-100 hover:text-white transition duration-200">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $stats['today']; ?></div>
                    <div class="text-xs text-gray-500">Today</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo $stats['this_week']; ?></div>
                    <div class="text-xs text-gray-500">This Week</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending_feedback']; ?></div>
                    <div class="text-xs text-gray-500">Pending Feedback</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600"><?php echo $stats['overdue']; ?></div>
                    <div class="text-xs text-gray-500">Overdue</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600"><?php echo $stats['success_rate']; ?>%</div>
                    <div class="text-xs text-gray-500">Success Rate</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="flex flex-wrap gap-2 mb-6">
                <a href="<?php echo BASE_URL; ?>/interviews/schedule.php" 
                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                    <i class="fas fa-plus mr-1"></i>Schedule
                </a>
                <a href="<?php echo BASE_URL; ?>/interviews/today.php" 
                   class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                    <i class="fas fa-calendar-day mr-1"></i>Today's
                </a>
                <a href="<?php echo BASE_URL; ?>/interviews/calendar.php" 
                   class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                    <i class="fas fa-calendar mr-1"></i>Calendar
                </a>
                <a href="<?php echo BASE_URL; ?>/interviews/reminders.php" 
                   class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                    <i class="fas fa-bell mr-1"></i>Reminders
                </a>
            </div>
            
            <!-- Upcoming Interviews -->
            <?php if (!empty($upcoming)): ?>
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-clock mr-2"></i>Upcoming Interviews
                </h4>
                <div class="space-y-2">
                    <?php foreach ($upcoming as $interview): ?>
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate">
                                <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo htmlspecialchars($interview['job_title']); ?> • 
                                <?php echo date('M j, g:i A', strtotime($interview['scheduled_date'])); ?>
                            </div>
                        </div>
                        <div class="ml-2">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                <?php echo $interview['display_status'] === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                <?php echo ucfirst($interview['display_status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Alerts -->
            <?php if ($stats['overdue'] > 0): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    <span class="text-sm text-red-700">
                        <?php echo $stats['overdue']; ?> overdue interview(s) need attention
                    </span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['pending_feedback'] > 0): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-clipboard-list text-yellow-500 mr-2"></i>
                    <span class="text-sm text-yellow-700">
                        <?php echo $stats['pending_feedback']; ?> interview(s) awaiting feedback
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
}

// Function to get interview data for charts
function getInterviewChartData() {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Weekly interview data for the last 8 weeks
    $weekly_stmt = $conn->query("
        SELECT 
            WEEK(scheduled_date) as week_num,
            YEAR(scheduled_date) as year_num,
            COUNT(*) as interview_count,
            DATE(DATE_SUB(scheduled_date, INTERVAL WEEKDAY(scheduled_date) DAY)) as week_start
        FROM interviews 
        WHERE scheduled_date >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
        GROUP BY WEEK(scheduled_date), YEAR(scheduled_date)
        ORDER BY year_num, week_num
    ");
    $weekly_data = $weekly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Interview status distribution
    $status_stmt = $conn->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM interviews 
        WHERE scheduled_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        GROUP BY status
    ");
    $status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Interview type distribution
    $type_stmt = $conn->query("
        SELECT 
            interview_type,
            COUNT(*) as count
        FROM interviews 
        WHERE scheduled_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        GROUP BY interview_type
    ");
    $type_data = $type_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'weekly' => $weekly_data,
        'status' => $status_data,
        'types' => $type_data
    ];
}
?> 