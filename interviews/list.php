<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$interviewer_id = $_GET['interviewer_id'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR j.title LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status;
}

if (!empty($interviewer_id)) {
    $where_conditions[] = "i.interviewer_id = ?";
    $params[] = $interviewer_id;
}

if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(i.scheduled_date) = CURDATE()";
            break;
        case 'this_week':
            $where_conditions[] = "WEEK(i.scheduled_date) = WEEK(CURDATE())";
            break;
        case 'next_week':
            $where_conditions[] = "WEEK(i.scheduled_date) = WEEK(CURDATE()) + 1";
            break;
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$db = new Database();
$conn = $db->getConnection();

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN users u ON i.interviewer_id = u.id
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get interviews
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
    $where_clause
    ORDER BY i.scheduled_date ASC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$interviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get interviewers for filter
$interviewers_stmt = $conn->query("
    SELECT id, first_name, last_name 
    FROM users 
    WHERE role IN ('interviewer', 'hiring_manager', 'hr_recruiter', 'admin') 
    ORDER BY first_name, last_name
");
$interviewers = $interviewers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interviews - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Interviews</h1>
                <p class="text-gray-600">Manage and track all interview schedules</p>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Candidate or job title..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="scheduled" <?php echo $status == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="rescheduled" <?php echo $status == 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Interviewer</label>
                        <select name="interviewer_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Interviewers</option>
                            <?php foreach ($interviewers as $interviewer): ?>
                            <option value="<?php echo $interviewer['id']; ?>" <?php echo $interviewer_id == $interviewer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($interviewer['first_name'] . ' ' . $interviewer['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Filter</label>
                        <select name="date_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Dates</option>
                            <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="this_week" <?php echo $date_filter == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="next_week" <?php echo $date_filter == 'next_week' ? 'selected' : ''; ?>>Next Week</option>
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
                            <i class="fas fa-calendar-day text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Today</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php
                                $today_count = 0;
                                foreach ($interviews as $interview) {
                                    if (date('Y-m-d', strtotime($interview['scheduled_date'])) == date('Y-m-d')) {
                                        $today_count++;
                                    }
                                }
                                echo $today_count;
                                ?>
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
                                <?php echo count(array_filter($interviews, fn($i) => $i['status'] == 'completed')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($interviews, fn($i) => $i['status'] == 'scheduled')); ?>
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
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($interviews, fn($i) => $i['display_status'] == 'overdue')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <a href="schedule.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Schedule Interview
                    </a>
                    <a href="calendar.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-calendar mr-2"></i>Calendar View
                    </a>
                    <a href="today.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-calendar-day mr-2"></i>Today's Interviews
                    </a>
                </div>
                
                <div class="text-sm text-gray-600">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> interviews
                </div>
            </div>

            <!-- Interviews Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interviewer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($interviews)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-calendar-times text-4xl mb-4"></i>
                                <p>No interviews scheduled</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($interviews as $interview): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="bg-blue-500 text-white w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold">
                                        <?php echo strtoupper(substr($interview['candidate_first'], 0, 1) . substr($interview['candidate_last'], 0, 1)); ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($interview['candidate_email']); ?></div>
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
                                <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($interview['scheduled_date'])); ?></div>
                                <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($interview['scheduled_date'])); ?></div>
                                <div class="text-xs text-gray-400"><?php echo $interview['duration']; ?> min</div>
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
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
                                    <button onclick="cancelInterview(<?php echo $interview['id']; ?>)" class="text-red-600 hover:text-red-900" title="Cancel">
                                        <i class="fas fa-times"></i>
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
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&interviewer_id=<?php echo $interviewer_id; ?>&date_filter=<?php echo $date_filter; ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&interviewer_id=<?php echo $interviewer_id; ?>&date_filter=<?php echo $date_filter; ?>" 
                       class="px-3 py-2 border rounded-lg <?php echo $i == $page ? 'bg-blue-500 text-white border-blue-500' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&interviewer_id=<?php echo $interviewer_id; ?>&date_filter=<?php echo $date_filter; ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </nav>
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
        
        function cancelInterview(id) {
            if (confirm('Are you sure you want to cancel this interview?')) {
                window.location.href = 'update_status.php?id=' + id + '&status=cancelled';
            }
        }
    </script>
</body>
</html> 