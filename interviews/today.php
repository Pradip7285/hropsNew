<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();

// Get today's interviews
$query = "
    SELECT i.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
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
    WHERE DATE(i.scheduled_date) = CURDATE()
    ORDER BY i.scheduled_date ASC
";

$stmt = $conn->prepare($query);
$stmt->execute();
$interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats for today
$stats = [
    'total' => count($interviews),
    'scheduled' => count(array_filter($interviews, fn($i) => $i['status'] == 'scheduled')),
    'completed' => count(array_filter($interviews, fn($i) => $i['status'] == 'completed')),
    'overdue' => count(array_filter($interviews, fn($i) => $i['display_status'] == 'overdue')),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today's Interviews - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
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
                        <h1 class="text-3xl font-bold text-gray-800">Today's Interviews</h1>
                        <p class="text-gray-600"><?php echo date('l, F j, Y'); ?></p>
                    </div>
                    <div class="flex space-x-2 no-print">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to All Interviews
                        </a>
                        <a href="schedule.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Schedule New
                        </a>
                        <button onclick="window.print()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-calendar-day text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['completed']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Scheduled</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['scheduled']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['overdue']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interviews List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <?php if (empty($interviews)): ?>
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-calendar-times text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold mb-2">No interviews scheduled for today</h3>
                    <p class="mb-4">Looks like you have a free day!</p>
                    <a href="schedule.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Schedule Interview
                    </a>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interviewer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($interviews as $interview): ?>
                            <tr class="hover:bg-gray-50 <?php echo $interview['display_status'] == 'overdue' ? 'bg-red-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('g:i A', strtotime($interview['scheduled_date'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo $interview['duration']; ?> min</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold">
                                            <?php echo strtoupper(substr($interview['candidate_first'], 0, 1) . substr($interview['candidate_last'], 0, 1)); ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($interview['candidate_email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($interview['job_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($interview['interviewer_first'] . ' ' . $interview['interviewer_last']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <i class="fas fa-<?php echo $interview['interview_type'] == 'video' ? 'video' : ($interview['interview_type'] == 'phone' ? 'phone' : ($interview['interview_type'] == 'technical' ? 'code' : 'building')); ?> mr-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $interview['interview_type'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'scheduled' => 'bg-blue-100 text-blue-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        'rescheduled' => 'bg-yellow-100 text-yellow-800',
                                        'overdue' => 'bg-red-100 text-red-800'
                                    ];
                                    $color_class = $status_colors[$interview['display_status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $color_class; ?>">
                                        <?php if ($interview['display_status'] == 'overdue'): ?>
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                        <?php else: ?>
                                            <?php echo ucfirst($interview['status']); ?>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-print">
                                    <div class="flex space-x-2">
                                        <a href="view.php?id=<?php echo $interview['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($interview['status'] == 'scheduled'): ?>
                                        <a href="edit.php?id=<?php echo $interview['id']; ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="markCompleted(<?php echo $interview['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="Mark Complete">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($interview['status'] == 'completed'): ?>
                                        <a href="feedback.php?id=<?php echo $interview['id']; ?>" class="text-orange-600 hover:text-orange-900" title="Feedback">
                                            <i class="fas fa-comment"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($interview['meeting_link']): ?>
                                        <a href="<?php echo htmlspecialchars($interview['meeting_link']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900" title="Join Meeting">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Interviews (Next 3 Days) -->
            <?php
            $upcoming_query = "
                SELECT i.*, 
                       c.first_name as candidate_first, c.last_name as candidate_last,
                       j.title as job_title,
                       u.first_name as interviewer_first, u.last_name as interviewer_last
                FROM interviews i
                JOIN candidates c ON i.candidate_id = c.id
                JOIN job_postings j ON i.job_id = j.id
                JOIN users u ON i.interviewer_id = u.id
                WHERE DATE(i.scheduled_date) BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                AND i.status = 'scheduled'
                ORDER BY i.scheduled_date ASC
                LIMIT 5
            ";
            $upcoming_stmt = $conn->prepare($upcoming_query);
            $upcoming_stmt->execute();
            $upcoming_interviews = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if (!empty($upcoming_interviews)): ?>
            <div class="mt-8 bg-white rounded-lg shadow-md p-6 no-print">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Upcoming Interviews (Next 3 Days)</h3>
                <div class="space-y-3">
                    <?php foreach ($upcoming_interviews as $upcoming): ?>
                    <div class="flex items-center justify-between border-l-4 border-blue-500 pl-4 py-2">
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($upcoming['candidate_first'] . ' ' . $upcoming['candidate_last']); ?> 
                                - <?php echo htmlspecialchars($upcoming['job_title']); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo date('l, M j \a\t g:i A', strtotime($upcoming['scheduled_date'])); ?>
                                with <?php echo htmlspecialchars($upcoming['interviewer_first'] . ' ' . $upcoming['interviewer_last']); ?>
                            </p>
                        </div>
                        <a href="view.php?id=<?php echo $upcoming['id']; ?>" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function markCompleted(id) {
            if (confirm('Mark this interview as completed?')) {
                window.location.href = 'update_status.php?id=' + id + '&status=completed';
            }
        }
    </script>
</body>
</html> 