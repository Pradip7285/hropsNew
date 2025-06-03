<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in (employees can access this)
requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get current user's employee ID
$user_id = $_SESSION['user_id'];
$employee_stmt = $conn->prepare("SELECT id FROM employees WHERE user_id = ?");
$employee_stmt->execute([$user_id]);
$employee_data = $employee_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee_data) {
    // User is not an employee, redirect to main reviews page
    header('Location: reviews.php');
    exit;
}

$employee_id = $employee_data['id'];

// Get filters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$cycle_filter = $_GET['cycle'] ?? '';

// Build query conditions
$where_conditions = ["pr.employee_id = ?"];
$params = [$employee_id];

if (!empty($status_filter)) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
}
if (!empty($type_filter)) {
    $where_conditions[] = "pr.review_type = ?";
    $params[] = $type_filter;
}
if (!empty($cycle_filter)) {
    $where_conditions[] = "pr.cycle_id = ?";
    $params[] = $cycle_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get employee's reviews
$reviews_query = "
    SELECT pr.*, 
           pc.cycle_name, pc.cycle_year, pc.review_deadline as cycle_deadline,
           r.first_name as reviewer_first_name, r.last_name as reviewer_last_name,
           CASE 
               WHEN pr.due_date < CURDATE() AND pr.status NOT IN ('completed', 'reviewed') THEN 'overdue'
               WHEN pr.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND pr.status NOT IN ('completed', 'reviewed') THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status,
           DATEDIFF(pr.due_date, CURDATE()) as days_remaining,
           (SELECT COUNT(*) FROM performance_ratings WHERE review_id = pr.id) as rating_count,
           (SELECT AVG(rating_value) FROM performance_ratings WHERE review_id = pr.id) as average_rating
    FROM performance_reviews pr
    JOIN performance_cycles pc ON pr.cycle_id = pc.id
    JOIN employees r ON pr.reviewer_id = r.id
    WHERE $where_clause
    ORDER BY pr.due_date DESC, pr.created_at DESC
";

$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->execute($params);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available cycles for filter
$cycles_stmt = $conn->query("
    SELECT DISTINCT pc.id, pc.cycle_name, pc.cycle_year 
    FROM performance_cycles pc
    JOIN performance_reviews pr ON pc.id = pr.cycle_id
    WHERE pr.employee_id = $employee_id
    ORDER BY pc.cycle_year DESC, pc.cycle_name
");
$cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_reviews,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_reviews,
        SUM(CASE WHEN status IN ('not_started', 'in_progress') THEN 1 ELSE 0 END) as pending_reviews,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'reviewed') THEN 1 ELSE 0 END) as overdue_reviews,
        ROUND(AVG(CASE WHEN overall_rating IS NOT NULL THEN overall_rating END), 2) as average_rating
    FROM performance_reviews pr
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent performance feedback/notes
$notes_stmt = $conn->prepare("
    SELECT pn.*, u.first_name, u.last_name 
    FROM performance_notes pn
    JOIN users u ON pn.noted_by = u.id
    WHERE pn.employee_id = ? AND pn.visibility IN ('employee', 'manager')
    ORDER BY pn.created_at DESC
    LIMIT 5
");
$notes_stmt->execute([$employee_id]);
$recent_notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance Reviews - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">My Performance Reviews</h1>
                        <p class="text-gray-600">Track your performance reviews and feedback</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="my_goals.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-bullseye mr-2"></i>My Goals
                        </a>
                        <?php if (hasRole('hr_recruiter')): ?>
                        <a href="reviews.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-list mr-2"></i>All Reviews
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Reviews</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_reviews']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_reviews']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_reviews']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg. Rating</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['average_rating'] ?? 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Review Cycle</label>
                        <select name="cycle" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Cycles</option>
                            <?php foreach ($cycles as $cycle): ?>
                            <option value="<?php echo $cycle['id']; ?>" <?php echo $cycle_filter == $cycle['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cycle['cycle_name'] . ' (' . $cycle['cycle_year'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="not_started" <?php echo $status_filter == 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="reviewed" <?php echo $status_filter == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Review Type</label>
                        <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            <option value="self" <?php echo $type_filter == 'self' ? 'selected' : ''; ?>>Self Review</option>
                            <option value="manager" <?php echo $type_filter == 'manager' ? 'selected' : ''; ?>>Manager Review</option>
                            <option value="peer" <?php echo $type_filter == 'peer' ? 'selected' : ''; ?>>Peer Review</option>
                            <option value="360" <?php echo $type_filter == '360' ? 'selected' : ''; ?>>360° Feedback</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <div class="flex space-x-2 w-full">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 flex-1">
                                <i class="fas fa-filter mr-2"></i>Filter
                            </button>
                            <a href="my_reviews.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Reviews List -->
                <div class="lg:col-span-2">
                    <div class="space-y-4">
                        <?php if (empty($reviews)): ?>
                        <div class="bg-white rounded-lg shadow-md p-8 text-center">
                            <i class="fas fa-clipboard-list text-gray-400 text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Performance Reviews</h3>
                            <p class="text-gray-500">You don't have any performance reviews matching the current filters.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <h3 class="text-lg font-semibold text-gray-900 mr-3">
                                            <?php echo htmlspecialchars($review['cycle_name'] . ' ' . $review['cycle_year']); ?>
                                        </h3>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $review['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                       ($review['status'] == 'submitted' ? 'bg-blue-100 text-blue-800' : 
                                                       ($review['status'] == 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $review['status'])); ?>
                                        </span>
                                        
                                        <span class="ml-2 px-2 py-1 text-xs font-medium rounded-full
                                            <?php echo $review['review_type'] == 'self' ? 'bg-purple-100 text-purple-800' : 
                                                       ($review['review_type'] == 'manager' ? 'bg-indigo-100 text-indigo-800' : 
                                                       ($review['review_type'] == 'peer' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800')); ?>">
                                            <?php echo ucfirst($review['review_type']); ?> Review
                                        </span>

                                        <?php if ($review['urgency_status'] == 'overdue'): ?>
                                            <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                            </span>
                                        <?php elseif ($review['urgency_status'] == 'due_soon'): ?>
                                            <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                                <i class="fas fa-clock mr-1"></i>Due Soon
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                                        <div>
                                            <span class="text-sm text-gray-500">Reviewer</span>
                                            <p class="font-medium"><?php echo htmlspecialchars($review['reviewer_first_name'] . ' ' . $review['reviewer_last_name']); ?></p>
                                        </div>
                                        <div>
                                            <span class="text-sm text-gray-500">Due Date</span>
                                            <p class="font-medium <?php echo $review['urgency_status'] == 'overdue' ? 'text-red-600' : ''; ?>">
                                                <?php echo date('M j, Y', strtotime($review['due_date'])); ?>
                                            </p>
                                            <?php if ($review['days_remaining'] > 0): ?>
                                                <p class="text-sm text-gray-600"><?php echo $review['days_remaining']; ?> days left</p>
                                            <?php elseif ($review['days_remaining'] < 0): ?>
                                                <p class="text-sm text-red-600"><?php echo abs($review['days_remaining']); ?> days overdue</p>
                                            <?php else: ?>
                                                <p class="text-sm text-yellow-600">Due today</p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="text-sm text-gray-500">Overall Rating</span>
                                            <?php if ($review['overall_rating']): ?>
                                                <div class="flex items-center">
                                                    <span class="font-medium mr-2"><?php echo $review['overall_rating']; ?>/5.0</span>
                                                    <div class="flex text-yellow-500">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star<?php echo $i <= $review['overall_rating'] ? '' : '-o'; ?> text-xs"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-500">Not rated yet</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($review['submitted_at']): ?>
                                    <div class="text-sm text-gray-600 mb-3">
                                        <i class="fas fa-calendar-check mr-1"></i>
                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($review['submitted_at'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="ml-6 flex flex-col space-y-2">
                                    <a href="view_review.php?id=<?php echo $review['id']; ?>" 
                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    
                                    <?php if ($review['review_type'] == 'self' && $review['status'] != 'completed' && $review['status'] != 'submitted'): ?>
                                    <a href="conduct_review.php?id=<?php echo $review['id']; ?>" 
                                       class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                        <i class="fas fa-edit mr-1"></i><?php echo $review['status'] == 'not_started' ? 'Start' : 'Continue'; ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-bolt text-yellow-600 mr-2"></i>Quick Actions
                        </h3>
                        <div class="space-y-3">
                            <a href="my_goals.php" class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition duration-200">
                                <i class="fas fa-bullseye text-blue-600 mr-3"></i>
                                <span class="text-sm font-medium text-gray-700">View My Goals</span>
                            </a>
                            <?php 
                            $pending_self_reviews = array_filter($reviews, function($review) {
                                return $review['review_type'] == 'self' && $review['status'] != 'completed' && $review['status'] != 'submitted';
                            });
                            if (!empty($pending_self_reviews)): 
                            ?>
                            <a href="conduct_review.php?id=<?php echo $pending_self_reviews[0]['id']; ?>" class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-lg transition duration-200">
                                <i class="fas fa-edit text-green-600 mr-3"></i>
                                <span class="text-sm font-medium text-gray-700">Complete Self Review</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Feedback -->
                    <?php if (!empty($recent_notes)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-comments text-green-600 mr-2"></i>Recent Feedback
                        </h3>
                        <div class="space-y-4">
                            <?php foreach ($recent_notes as $note): ?>
                            <div class="border-l-4 border-blue-500 pl-4">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-800 capitalize"><?php echo str_replace('_', ' ', $note['note_type']); ?></span>
                                    <span class="text-xs text-gray-500"><?php echo date('M j', strtotime($note['created_at'])); ?></span>
                                </div>
                                <h4 class="text-sm font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($note['note_title']); ?></h4>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars(substr($note['note_content'], 0, 100)) . (strlen($note['note_content']) > 100 ? '...' : ''); ?></p>
                                <p class="text-xs text-gray-500 mt-1">by <?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Performance Tips -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-lightbulb text-yellow-600 mr-2"></i>Performance Tips
                        </h3>
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                <span>Complete self-reviews honestly and thoughtfully</span>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                <span>Set SMART goals for better performance tracking</span>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                <span>Ask for feedback regularly, not just during reviews</span>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check text-green-500 mr-2 mt-1 text-xs"></i>
                                <span>Document your achievements throughout the year</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 