<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$view = $_GET['view'] ?? 'month';
$date = $_GET['date'] ?? date('Y-m-d');

$db = new Database();
$conn = $db->getConnection();

// Validate view
$valid_views = ['day', 'week', 'month'];
if (!in_array($view, $valid_views)) {
    $view = 'month';
}

// Calculate date range based on view
switch ($view) {
    case 'day':
        $start_date = $date;
        $end_date = $date;
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        break;
    case 'month':
        $start_date = date('Y-m-01', strtotime($date));
        $end_date = date('Y-m-t', strtotime($date));
        break;
}

// Get interviews for the date range
$interviews_stmt = $conn->prepare("
    SELECT i.*, 
           c.first_name as candidate_first, c.last_name as candidate_last,
           j.title as job_title,
           u.first_name as interviewer_first, u.last_name as interviewer_last
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN users u ON i.interviewer_id = u.id
    WHERE DATE(i.scheduled_date) BETWEEN ? AND ?
    ORDER BY i.scheduled_date ASC
");
$interviews_stmt->execute([$start_date, $end_date]);
$interviews = $interviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group interviews by date for easier rendering
$interviews_by_date = [];
foreach ($interviews as $interview) {
    $interview_date = date('Y-m-d', strtotime($interview['scheduled_date']));
    $interviews_by_date[$interview_date][] = $interview;
}

function getStatusColor($status) {
    $colors = [
        'scheduled' => 'bg-blue-500',
        'completed' => 'bg-green-500',
        'cancelled' => 'bg-red-500',
        'rescheduled' => 'bg-yellow-500'
    ];
    return $colors[$status] ?? 'bg-gray-500';
}

function getTypeIcon($type) {
    $icons = [
        'video' => 'fa-video',
        'phone' => 'fa-phone',
        'in_person' => 'fa-building',
        'technical' => 'fa-code'
    ];
    return $icons[$type] ?? 'fa-calendar';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Calendar - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .calendar-cell {
            min-height: 120px;
        }
        .interview-item {
            font-size: 0.75rem;
            margin-bottom: 2px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Interview Calendar</h1>
                <p class="text-gray-600">View and manage interview schedules</p>
            </div>

            <!-- Calendar Controls -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                    <!-- View Selector -->
                    <div class="flex space-x-2">
                        <a href="?view=day&date=<?php echo $date; ?>" 
                           class="px-4 py-2 rounded-lg <?php echo $view == 'day' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> transition duration-200">
                            <i class="fas fa-calendar-day mr-2"></i>Day
                        </a>
                        <a href="?view=week&date=<?php echo $date; ?>" 
                           class="px-4 py-2 rounded-lg <?php echo $view == 'week' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> transition duration-200">
                            <i class="fas fa-calendar-week mr-2"></i>Week
                        </a>
                        <a href="?view=month&date=<?php echo $date; ?>" 
                           class="px-4 py-2 rounded-lg <?php echo $view == 'month' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> transition duration-200">
                            <i class="fas fa-calendar mr-2"></i>Month
                        </a>
                    </div>

                    <!-- Date Navigation -->
                    <div class="flex items-center space-x-4">
                        <?php
                        $prev_date = '';
                        $next_date = '';
                        $current_label = '';
                        
                        switch ($view) {
                            case 'day':
                                $prev_date = date('Y-m-d', strtotime('-1 day', strtotime($date)));
                                $next_date = date('Y-m-d', strtotime('+1 day', strtotime($date)));
                                $current_label = date('F j, Y', strtotime($date));
                                break;
                            case 'week':
                                $prev_date = date('Y-m-d', strtotime('-1 week', strtotime($date)));
                                $next_date = date('Y-m-d', strtotime('+1 week', strtotime($date)));
                                $week_start = date('M j', strtotime($start_date));
                                $week_end = date('M j, Y', strtotime($end_date));
                                $current_label = "$week_start - $week_end";
                                break;
                            case 'month':
                                $prev_date = date('Y-m-d', strtotime('-1 month', strtotime($date)));
                                $next_date = date('Y-m-d', strtotime('+1 month', strtotime($date)));
                                $current_label = date('F Y', strtotime($date));
                                break;
                        }
                        ?>
                        
                        <a href="?view=<?php echo $view; ?>&date=<?php echo $prev_date; ?>" 
                           class="p-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition duration-200">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <h2 class="text-xl font-semibold text-gray-800 min-w-[200px] text-center">
                            <?php echo $current_label; ?>
                        </h2>
                        
                        <a href="?view=<?php echo $view; ?>&date=<?php echo $next_date; ?>" 
                           class="p-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition duration-200">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>

                    <!-- Quick Actions -->
                    <div class="flex space-x-2">
                        <a href="?view=<?php echo $view; ?>&date=<?php echo date('Y-m-d'); ?>" 
                           class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition duration-200">
                            <i class="fas fa-calendar-day mr-2"></i>Today
                        </a>
                        <a href="schedule.php" 
                           class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Schedule
                        </a>
                    </div>
                </div>
            </div>

            <!-- Calendar Display -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <?php if ($view == 'month'): ?>
                    <!-- Month View -->
                    <div class="grid grid-cols-7 gap-0">
                        <!-- Day headers -->
                        <?php $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; ?>
                        <?php foreach ($days as $day): ?>
                        <div class="bg-gray-50 p-4 text-center font-semibold text-gray-700 border-b border-gray-200">
                            <?php echo $day; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Calendar cells -->
                        <?php
                        $first_day = date('w', strtotime($start_date));
                        $days_in_month = date('t', strtotime($date));
                        $current_day = 1;
                        
                        // Start from the beginning of the week
                        $calendar_start = date('Y-m-d', strtotime($start_date . " -$first_day days"));
                        
                        for ($week = 0; $week < 6; $week++):
                            for ($day = 0; $day < 7; $day++):
                                $cell_date = date('Y-m-d', strtotime($calendar_start . " +" . ($week * 7 + $day) . " days"));
                                $is_current_month = date('m', strtotime($cell_date)) == date('m', strtotime($date));
                                $is_today = $cell_date == date('Y-m-d');
                                $day_interviews = $interviews_by_date[$cell_date] ?? [];
                        ?>
                        <div class="calendar-cell p-2 border-b border-r border-gray-200 <?php echo $is_current_month ? 'bg-white' : 'bg-gray-50'; ?> <?php echo $is_today ? 'bg-blue-50' : ''; ?>">
                            <div class="text-sm font-medium mb-2 <?php echo $is_current_month ? 'text-gray-900' : 'text-gray-400'; ?> <?php echo $is_today ? 'text-blue-600' : ''; ?>">
                                <?php echo date('j', strtotime($cell_date)); ?>
                            </div>
                            <?php foreach (array_slice($day_interviews, 0, 3) as $interview): ?>
                            <div class="interview-item p-1 rounded text-white <?php echo getStatusColor($interview['status']); ?> cursor-pointer"
                                 onclick="showInterviewDetails(<?php echo $interview['id']; ?>)">
                                <i class="fas <?php echo getTypeIcon($interview['interview_type']); ?> mr-1"></i>
                                <?php echo date('g:iA', strtotime($interview['scheduled_date'])); ?>
                                <div class="truncate"><?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($day_interviews) > 3): ?>
                            <div class="text-xs text-blue-600 cursor-pointer" onclick="showDayDetails('<?php echo $cell_date; ?>')">
                                +<?php echo count($day_interviews) - 3; ?> more
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php 
                            endfor;
                            // Break if we've shown all weeks with content
                            if ($week >= 4 && $current_day > $days_in_month) break;
                        endfor; 
                        ?>
                    </div>
                    
                <?php elseif ($view == 'week'): ?>
                    <!-- Week View -->
                    <div class="grid grid-cols-8 gap-0">
                        <!-- Time column header -->
                        <div class="bg-gray-50 p-4 border-b border-gray-200"></div>
                        
                        <!-- Day headers -->
                        <?php for ($i = 0; $i < 7; $i++): 
                            $day_date = date('Y-m-d', strtotime($start_date . " +$i days"));
                            $is_today = $day_date == date('Y-m-d');
                        ?>
                        <div class="bg-gray-50 p-4 text-center font-semibold border-b border-gray-200 <?php echo $is_today ? 'bg-blue-100 text-blue-600' : 'text-gray-700'; ?>">
                            <div><?php echo date('D', strtotime($day_date)); ?></div>
                            <div class="text-lg"><?php echo date('j', strtotime($day_date)); ?></div>
                        </div>
                        <?php endfor; ?>
                        
                        <!-- Time slots -->
                        <?php for ($hour = 8; $hour <= 18; $hour++): ?>
                        <div class="p-2 text-sm text-gray-600 border-b border-gray-200 bg-gray-50">
                            <?php echo date('g A', strtotime("$hour:00")); ?>
                        </div>
                        
                        <?php for ($i = 0; $i < 7; $i++): 
                            $day_date = date('Y-m-d', strtotime($start_date . " +$i days"));
                            $day_interviews = array_filter($interviews_by_date[$day_date] ?? [], function($interview) use ($hour) {
                                return date('G', strtotime($interview['scheduled_date'])) == $hour;
                            });
                        ?>
                        <div class="p-1 border-b border-r border-gray-200 min-h-[60px]">
                            <?php foreach ($day_interviews as $interview): ?>
                            <div class="interview-item p-2 mb-1 rounded text-white <?php echo getStatusColor($interview['status']); ?> cursor-pointer"
                                 onclick="showInterviewDetails(<?php echo $interview['id']; ?>)">
                                <i class="fas <?php echo getTypeIcon($interview['interview_type']); ?> mr-1"></i>
                                <?php echo date('g:i A', strtotime($interview['scheduled_date'])); ?>
                                <div class="text-xs truncate"><?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endfor; ?>
                        <?php endfor; ?>
                    </div>
                    
                <?php else: ?>
                    <!-- Day View -->
                    <div class="p-6">
                        <?php if (empty($interviews_by_date[$date])): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">No interviews scheduled for this day</p>
                            <a href="schedule.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-plus mr-2"></i>Schedule Interview
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($interviews_by_date[$date] as $interview): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-200">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-full <?php echo getStatusColor($interview['status']); ?> flex items-center justify-center text-white">
                                            <i class="fas <?php echo getTypeIcon($interview['interview_type']); ?>"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-800">
                                                <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?>
                                            </h3>
                                            <p class="text-gray-600"><?php echo htmlspecialchars($interview['job_title']); ?></p>
                                            <p class="text-sm text-gray-500">
                                                Interviewer: <?php echo htmlspecialchars($interview['interviewer_first'] . ' ' . $interview['interviewer_last']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-semibold text-gray-800">
                                            <?php echo date('g:i A', strtotime($interview['scheduled_date'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo $interview['duration']; ?> minutes
                                        </div>
                                        <span class="inline-block mt-2 px-2 py-1 text-xs rounded-full <?php echo getStatusColor($interview['status']); ?> text-white">
                                            <?php echo ucfirst($interview['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex space-x-2">
                                    <a href="view.php?id=<?php echo $interview['id']; ?>" 
                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    <?php if ($interview['status'] == 'scheduled'): ?>
                                    <a href="edit.php?id=<?php echo $interview['id']; ?>" 
                                       class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($interview['status'] == 'completed'): ?>
                                    <a href="feedback.php?id=<?php echo $interview['id']; ?>" 
                                       class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        <i class="fas fa-comment mr-1"></i>Feedback
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($interview['meeting_link']): ?>
                                    <a href="<?php echo htmlspecialchars($interview['meeting_link']); ?>" target="_blank"
                                       class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        <i class="fas fa-external-link-alt mr-1"></i>Join
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Interview Details Modal -->
    <div id="interviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-96 overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Interview Details</h3>
                        <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="modalContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showInterviewDetails(interviewId) {
            // In a real implementation, this would load interview details via AJAX
            document.getElementById('modalContent').innerHTML = 
                '<p class="text-gray-600">Loading interview details...</p>';
            document.getElementById('interviewModal').classList.remove('hidden');
            
            // Simulate loading
            setTimeout(() => {
                document.getElementById('modalContent').innerHTML = 
                    '<p class="text-gray-600">Interview details would be loaded here. <a href="view.php?id=' + interviewId + '" class="text-blue-600 hover:underline">View full details</a></p>';
            }, 500);
        }
        
        function showDayDetails(date) {
            window.location.href = '?view=day&date=' + date;
        }
        
        function closeModal() {
            document.getElementById('interviewModal').classList.add('hidden');
        }
    </script>
</body>
</html> 