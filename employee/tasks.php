<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an employee
requireLogin();
if (!in_array($_SESSION['role'], ['employee', 'hiring_manager'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get employee information
$employee_stmt = $conn->prepare("SELECT id FROM employees WHERE user_id = ?");
$employee_stmt->execute([$_SESSION['user_id']]);
$employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee record not found. Please contact HR.");
}

// Handle task updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'complete_task') {
        $task_id = $_POST['task_id'];
        $stmt = $conn->prepare("UPDATE onboarding_tasks SET status = 'completed', completed_at = NOW() WHERE id = ? AND employee_id = ?");
        $stmt->execute([$task_id, $employee['id']]);
        $success_message = "Task marked as completed!";
    }
    
    if ($_POST['action'] == 'submit_feedback') {
        $task_id = $_POST['task_id'];
        $feedback = $_POST['feedback'];
        $stmt = $conn->prepare("UPDATE onboarding_tasks SET employee_feedback = ?, status = 'pending_review' WHERE id = ? AND employee_id = ?");
        $stmt->execute([$feedback, $task_id, $employee['id']]);
        $success_message = "Feedback submitted for review!";
    }
}

// Get tasks
$tasks_query = "
    SELECT ot.*, tm.name as module_name
    FROM onboarding_tasks ot
    LEFT JOIN training_modules tm ON ot.training_module_id = tm.id
    WHERE ot.employee_id = ?
    ORDER BY ot.priority DESC, ot.due_date ASC
";

try {
    $tasks_stmt = $conn->prepare($tasks_query);
    $tasks_stmt->execute([$employee['id']]);
    $tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If onboarding_tasks table doesn't exist, create sample data
    $tasks = [
        [
            'id' => 1,
            'task_name' => 'Complete Profile Setup',
            'description' => 'Fill in your personal information and upload profile photo',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => date('Y-m-d', strtotime('+3 days')),
            'module_name' => 'Onboarding'
        ],
        [
            'id' => 2,
            'task_name' => 'Security Training',
            'description' => 'Complete mandatory cybersecurity awareness training',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => date('Y-m-d', strtotime('+7 days')),
            'module_name' => 'Security'
        ],
        [
            'id' => 3,
            'task_name' => 'Company Handbook Review',
            'description' => 'Read and acknowledge the employee handbook',
            'status' => 'completed',
            'priority' => 'medium',
            'due_date' => date('Y-m-d', strtotime('+5 days')),
            'module_name' => 'Policies'
        ]
    ];
}

// Group tasks by status
$pending_tasks = array_filter($tasks, function($task) { return $task['status'] == 'pending'; });
$in_progress_tasks = array_filter($tasks, function($task) { return $task['status'] == 'in_progress'; });
$completed_tasks = array_filter($tasks, function($task) { return $task['status'] == 'completed'; });
$review_tasks = array_filter($tasks, function($task) { return $task['status'] == 'pending_review'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Employee Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">My Tasks</h1>
                </div>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="text-gray-400 hover:text-red-600">
                    <i class="fas fa-sign-out-alt text-lg"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (isset($success_message)): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <!-- Task Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-orange-100 p-3 rounded-full mr-4">
                        <i class="fas fa-clock text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Pending</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo count($pending_tasks); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-play text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">In Progress</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo count($in_progress_tasks); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-full mr-4">
                        <i class="fas fa-eye text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Under Review</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo count($review_tasks); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Completed</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo count($completed_tasks); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Sections -->
        <div class="space-y-8">
            <!-- Pending Tasks -->
            <?php if (!empty($pending_tasks)): ?>
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-clock text-orange-600 mr-2"></i>
                    Pending Tasks
                </h2>
                <div class="space-y-4">
                    <?php foreach ($pending_tasks as $task): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($task['task_name']); ?></h3>
                                <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($task['description'] ?? ''); ?></p>
                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                    <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($task['module_name'] ?? 'General'); ?></span>
                                    <span><i class="fas fa-calendar mr-1"></i>Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></span>
                                    <span class="<?php echo $task['priority'] == 'high' ? 'text-red-600' : ($task['priority'] == 'medium' ? 'text-yellow-600' : 'text-green-600'); ?>">
                                        <i class="fas fa-flag mr-1"></i><?php echo ucfirst($task['priority']); ?> Priority
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="complete_task">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                                    <i class="fas fa-check mr-2"></i>Mark Complete
                                </button>
                            </form>
                            <button onclick="openFeedbackModal(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['task_name']); ?>')" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                <i class="fas fa-comment mr-2"></i>Submit Feedback
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- In Progress Tasks -->
            <?php if (!empty($in_progress_tasks)): ?>
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-play text-blue-600 mr-2"></i>
                    In Progress
                </h2>
                <div class="space-y-4">
                    <?php foreach ($in_progress_tasks as $task): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                        <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($task['task_name']); ?></h3>
                        <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($task['description'] ?? ''); ?></p>
                        <div class="flex items-center space-x-4 text-sm text-gray-500">
                            <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($task['module_name'] ?? 'General'); ?></span>
                            <span><i class="fas fa-calendar mr-1"></i>Due: <?php echo date('M j, Y', strtotime($task['due_date'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Under Review Tasks -->
            <?php if (!empty($review_tasks)): ?>
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-eye text-yellow-600 mr-2"></i>
                    Under Review
                </h2>
                <div class="space-y-4">
                    <?php foreach ($review_tasks as $task): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                        <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($task['task_name']); ?></h3>
                        <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($task['description'] ?? ''); ?></p>
                        <p class="text-sm text-yellow-600"><i class="fas fa-clock mr-1"></i>Waiting for manager review</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Completed Tasks -->
            <?php if (!empty($completed_tasks)): ?>
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-check text-green-600 mr-2"></i>
                    Completed Tasks
                </h2>
                <div class="space-y-4">
                    <?php foreach ($completed_tasks as $task): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500 opacity-75">
                        <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($task['task_name']); ?></h3>
                        <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($task['description'] ?? ''); ?></p>
                        <p class="text-sm text-green-600"><i class="fas fa-check mr-1"></i>Completed</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Submit Feedback</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="submit_feedback">
                        <input type="hidden" name="task_id" id="modalTaskId">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Your Feedback:</label>
                            <textarea name="feedback" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                      placeholder="Please provide your feedback or questions about this task..." required></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeFeedbackModal()" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Submit Feedback
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openFeedbackModal(taskId, taskName) {
            document.getElementById('modalTaskId').value = taskId;
            document.getElementById('modalTitle').textContent = 'Submit Feedback for: ' + taskName;
            document.getElementById('feedbackModal').classList.remove('hidden');
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').classList.add('hidden');
        }
    </script>
</body>
</html> 