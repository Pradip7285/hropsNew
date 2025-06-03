<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_review_status':
            $stmt = $conn->prepare("UPDATE performance_reviews SET status = ? WHERE id = ?");
            $result = $stmt->execute([$_POST['status'], $_POST['review_id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'delete_review':
            $stmt = $conn->prepare("DELETE FROM performance_reviews WHERE id = ?");
            $result = $stmt->execute([$_POST['review_id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'send_reminder':
            // Placeholder for reminder functionality
            $stmt = $conn->prepare("
                INSERT INTO performance_notes (employee_id, noted_by, note_type, note_title, note_content, visibility) 
                VALUES (?, ?, 'feedback', 'Review Reminder', 'Review reminder sent', 'hr')
            ");
            $result = $stmt->execute([$_POST['employee_id'], $_SESSION['user_id']]);
            echo json_encode(['success' => $result, 'message' => 'Reminder sent successfully']);
            exit;
    }
}

// Get filters
$cycle_filter = $_GET['cycle'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$employee_filter = $_GET['employee'] ?? '';
$reviewer_filter = $_GET['reviewer'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($cycle_filter)) {
    $where_conditions[] = "pr.cycle_id = ?";
    $params[] = $cycle_filter;
}
if (!empty($status_filter)) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
}
if (!empty($type_filter)) {
    $where_conditions[] = "pr.review_type = ?";
    $params[] = $type_filter;
}
if (!empty($employee_filter)) {
    $where_conditions[] = "pr.employee_id = ?";
    $params[] = $employee_filter;
}
if (!empty($reviewer_filter)) {
    $where_conditions[] = "pr.reviewer_id = ?";
    $params[] = $reviewer_filter;
}
if (!empty($search)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ? OR pc.cycle_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$where_clause = implode(" AND ", $where_conditions);

// Get reviews with detailed information
$reviews_query = "
    SELECT pr.*, 
           pc.cycle_name, pc.cycle_year, pc.review_deadline as cycle_deadline,
           e.first_name as employee_first_name, e.last_name as employee_last_name, 
           e.employee_id as employee_number, e.department as employee_department, e.position as employee_position,
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
    JOIN employees e ON pr.employee_id = e.id
    JOIN employees r ON pr.reviewer_id = r.id
    WHERE $where_clause
    ORDER BY pr.due_date ASC, pr.status ASC, pr.created_at DESC
";

$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->execute($params);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$cycles_stmt = $conn->query("SELECT id, cycle_name, cycle_year FROM performance_cycles ORDER BY cycle_year DESC, cycle_name");
$cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);

$employees_stmt = $conn->query("SELECT id, first_name, last_name, employee_id, department FROM employees WHERE status = 'active' ORDER BY first_name, last_name");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_reviews,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_reviews,
        SUM(CASE WHEN status IN ('not_started', 'in_progress') THEN 1 ELSE 0 END) as pending_reviews,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'reviewed') THEN 1 ELSE 0 END) as overdue_reviews,
        ROUND(AVG(CASE WHEN overall_rating IS NOT NULL THEN overall_rating END), 2) as average_rating
    FROM performance_reviews pr
    JOIN performance_cycles pc ON pr.cycle_id = pc.id
    JOIN employees e ON pr.employee_id = e.id
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Reviews - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Performance Reviews</h1>
                        <p class="text-gray-600">Manage and track performance review submissions</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="conduct_review.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Conduct Review
                        </a>
                        <a href="my_reviews.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-user-check mr-2"></i>My Reviews
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
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
                        <div class="bg-red-100 p-3 rounded-full mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['overdue_reviews']; ?></p>
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

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search employees, reviewers, or cycles..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

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
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Review Type</label>
                            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Types</option>
                                <option value="self" <?php echo $type_filter == 'self' ? 'selected' : ''; ?>>Self Review</option>
                                <option value="manager" <?php echo $type_filter == 'manager' ? 'selected' : ''; ?>>Manager Review</option>
                                <option value="peer" <?php echo $type_filter == 'peer' ? 'selected' : ''; ?>>Peer Review</option>
                                <option value="360" <?php echo $type_filter == '360' ? 'selected' : ''; ?>>360° Feedback</option>
                                <option value="skip_level" <?php echo $type_filter == 'skip_level' ? 'selected' : ''; ?>>Skip Level</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
                            <select name="employee" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['department'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <div class="flex space-x-2 w-full">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 flex-1">
                                    <i class="fas fa-search mr-2"></i>Search
                                </button>
                                <a href="reviews.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Reviews List -->
            <div class="space-y-4">
                <?php if (empty($reviews)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-clipboard-list text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Performance Reviews</h3>
                    <p class="text-gray-500">No reviews match your current filters.</p>
                </div>
                <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 mr-3">
                                    <?php echo htmlspecialchars($review['employee_first_name'] . ' ' . $review['employee_last_name']); ?>
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

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <span class="text-sm text-gray-500">Employee</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($review['employee_number']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['employee_department']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Reviewer</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($review['reviewer_first_name'] . ' ' . $review['reviewer_last_name']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Review Cycle</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($review['cycle_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $review['cycle_year']; ?></p>
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
                            </div>

                            <?php if ($review['overall_rating']): ?>
                            <div class="mb-3">
                                <span class="text-sm text-gray-500">Overall Rating: </span>
                                <span class="font-semibold text-lg">
                                    <?php echo $review['overall_rating']; ?>/5.0
                                    <span class="text-yellow-500">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star<?php echo $i <= $review['overall_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if ($review['rating_count'] > 0): ?>
                            <div class="text-sm text-gray-600 mb-3">
                                <i class="fas fa-chart-bar mr-1"></i>
                                <?php echo $review['rating_count']; ?> detailed ratings provided
                                <?php if ($review['average_rating']): ?>
                                    (Avg: <?php echo round($review['average_rating'], 2); ?>/5.0)
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($review['submitted_at']): ?>
                            <div class="text-sm text-gray-600">
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
                            
                            <?php if ($review['status'] == 'not_started' || $review['status'] == 'in_progress'): ?>
                            <a href="conduct_review.php?id=<?php echo $review['id']; ?>" 
                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                <i class="fas fa-edit mr-1"></i><?php echo $review['status'] == 'not_started' ? 'Start' : 'Continue'; ?>
                            </a>
                            <?php endif; ?>

                            <?php if ($review['status'] != 'completed'): ?>
                            <button onclick="sendReminder(<?php echo $review['id']; ?>, <?php echo $review['employee_id']; ?>)" 
                                    class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-bell mr-1"></i>Remind
                            </button>
                            <?php endif; ?>

                            <button onclick="updateReviewStatus(<?php echo $review['id']; ?>)" 
                                    class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-edit mr-1"></i>Status
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Update Review Status</h3>
                    <button onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="statusForm" class="space-y-4">
                    <input type="hidden" id="statusReviewId" name="review_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select id="newStatus" name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="submitted">Submitted</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeStatusModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateReviewStatus(reviewId) {
            document.getElementById('statusReviewId').value = reviewId;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function sendReminder(reviewId, employeeId) {
            if (confirm('Send a reminder to complete this review?')) {
                const formData = new FormData();
                formData.append('action', 'send_reminder');
                formData.append('review_id', reviewId);
                formData.append('employee_id', employeeId);

                fetch('reviews.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Reminder sent successfully');
                    } else {
                        alert('Error sending reminder');
                    }
                });
            }
        }

        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_review_status');

            fetch('reviews.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating status');
                }
            });
        });

        // Close modal when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });
    </script>
</body>
</html> 