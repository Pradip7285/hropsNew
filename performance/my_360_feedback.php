<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication
requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["fp.provider_id = ?"];
$params = [$_SESSION['user_id']];

if (!empty($status_filter)) {
    $where_conditions[] = "fp.status = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR fr.title LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$where_clause = implode(" AND ", $where_conditions);

// Get my 360 feedback assignments
$assignments_query = "
    SELECT fr.*, 
           pc.cycle_name, pc.cycle_year,
           e.first_name as employee_first_name, e.last_name as employee_last_name, 
           e.employee_id as employee_number, e.department as employee_department,
           fp.id as provider_id, fp.relationship_type, fp.status as provider_status,
           fp.invited_at, fp.completed_at,
           CASE 
               WHEN fr.deadline < CURDATE() AND fp.status != 'completed' THEN 'overdue'
               WHEN fr.deadline <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND fp.status != 'completed' THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status,
           DATEDIFF(fr.deadline, CURDATE()) as days_remaining,
           (SELECT COUNT(*) FROM feedback_360_questions WHERE request_id = fr.id) as total_questions
    FROM feedback_360_providers fp
    JOIN feedback_360_requests fr ON fp.request_id = fr.id
    JOIN performance_cycles pc ON fr.cycle_id = pc.id
    JOIN employees e ON fr.employee_id = e.id
    WHERE $where_clause
    ORDER BY fr.deadline ASC, fp.invited_at DESC
";

$assignments_stmt = $conn->prepare($assignments_query);
$assignments_stmt->execute($params);
$assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_assignments,
        SUM(CASE WHEN fp.status = 'completed' THEN 1 ELSE 0 END) as completed_assignments,
        SUM(CASE WHEN fp.status = 'pending' THEN 1 ELSE 0 END) as pending_assignments,
        SUM(CASE WHEN fr.deadline < CURDATE() AND fp.status != 'completed' THEN 1 ELSE 0 END) as overdue_assignments
    FROM feedback_360_providers fp
    JOIN feedback_360_requests fr ON fp.request_id = fr.id
    WHERE fp.provider_id = ?
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My 360° Feedback - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">My 360° Feedback</h1>
                        <p class="text-gray-600">Complete feedback requests assigned to you</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="feedback_360.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-users mr-2"></i>All Feedback Requests
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Assignments</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_assignments']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_assignments']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_assignments']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['overdue_assignments']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search employees or request titles..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <div class="flex space-x-2 w-full">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 flex-1">
                                    <i class="fas fa-search mr-2"></i>Search
                                </button>
                                <a href="my_360_feedback.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Feedback Assignments List -->
            <div class="space-y-4">
                <?php if (empty($assignments)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-clipboard-list text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Feedback Assignments</h3>
                    <p class="text-gray-500">You don't have any 360° feedback assignments at the moment.</p>
                </div>
                <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 mr-3">
                                    <?php echo htmlspecialchars($assignment['title']); ?>
                                </h3>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($assignment['provider_status']) {
                                        case 'completed': echo 'bg-green-100 text-green-800'; break;
                                        case 'in_progress': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'pending': echo 'bg-blue-100 text-blue-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $assignment['provider_status'])); ?>
                                </span>

                                <?php if ($assignment['urgency_status'] == 'overdue'): ?>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </span>
                                <?php elseif ($assignment['urgency_status'] == 'due_soon'): ?>
                                    <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Due Soon
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <span class="text-sm text-gray-500">Feedback For</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($assignment['employee_first_name'] . ' ' . $assignment['employee_last_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($assignment['employee_number']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Cycle</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($assignment['cycle_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $assignment['cycle_year']; ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Your Relationship</span>
                                    <p class="font-medium capitalize"><?php echo htmlspecialchars($assignment['relationship_type']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $assignment['total_questions']; ?> questions</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Deadline</span>
                                    <p class="font-medium <?php echo $assignment['urgency_status'] == 'overdue' ? 'text-red-600' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($assignment['deadline'])); ?>
                                    </p>
                                    <?php if ($assignment['days_remaining'] > 0): ?>
                                        <p class="text-sm text-gray-600"><?php echo $assignment['days_remaining']; ?> days left</p>
                                    <?php elseif ($assignment['days_remaining'] < 0): ?>
                                        <p class="text-sm text-red-600"><?php echo abs($assignment['days_remaining']); ?> days overdue</p>
                                    <?php else: ?>
                                        <p class="text-sm text-yellow-600">Due today</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($assignment['description']): ?>
                            <div class="mb-3">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($assignment['description']); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="text-sm text-gray-600">
                                <i class="fas fa-calendar mr-1"></i>
                                Assigned: <?php echo date('M j, Y', strtotime($assignment['invited_at'])); ?>
                                <?php if ($assignment['completed_at']): ?>
                                    <span class="ml-4">
                                        <i class="fas fa-check mr-1"></i>
                                        Completed: <?php echo date('M j, Y', strtotime($assignment['completed_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ml-6 flex flex-col space-y-2">
                            <?php if ($assignment['provider_status'] == 'completed'): ?>
                                <span class="bg-green-500 text-white px-3 py-1 rounded text-sm text-center">
                                    <i class="fas fa-check mr-1"></i>Completed
                                </span>
                                <a href="view_my_feedback_response.php?id=<?php echo $assignment['id']; ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                    <i class="fas fa-eye mr-1"></i>View Response
                                </a>
                            <?php else: ?>
                                <a href="submit_360_feedback.php?id=<?php echo $assignment['id']; ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                    <i class="fas fa-edit mr-1"></i>
                                    <?php echo $assignment['provider_status'] == 'in_progress' ? 'Continue' : 'Start'; ?> Feedback
                                </a>
                                
                                <?php if ($assignment['urgency_status'] == 'overdue'): ?>
                                    <span class="bg-red-500 text-white px-3 py-1 rounded text-sm text-center">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </span>
                                <?php elseif ($assignment['urgency_status'] == 'due_soon'): ?>
                                    <span class="bg-yellow-500 text-white px-3 py-1 rounded text-sm text-center">
                                        <i class="fas fa-clock mr-1"></i>Due Soon
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html> 