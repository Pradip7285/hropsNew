<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/dual_interface.php';

// Check if user is logged in
requireLogin();

// Allow employees and HR users (in employee mode) to access
if (!in_array($_SESSION['role'], ['employee', 'hiring_manager', 'hr_recruiter', 'admin'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get employee information
$employee_stmt = $conn->prepare("
    SELECT e.*, u.first_name, u.last_name, u.email, u.department as user_department
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    WHERE e.user_id = ?
");
$employee_stmt->execute([$_SESSION['user_id']]);
$employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee record not found. Please contact HR.");
}

// Get employee stats
$stats = [
    'pending_tasks' => 0,
    'completed_trainings' => 0,
    'pending_documents' => 0,
    'active_goals' => 0,
    'completion_percentage' => 0
];

// Get pending onboarding tasks
try {
    $tasks_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM onboarding_tasks 
        WHERE employee_id = ? AND status = 'pending'
    ");
    $tasks_stmt->execute([$employee['id']]);
    $stats['pending_tasks'] = $tasks_stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['pending_tasks'] = 0;
}

// Get completed trainings
try {
    $training_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM employee_training 
        WHERE employee_id = ? AND status = 'completed'
    ");
    $training_stmt->execute([$employee['id']]);
    $stats['completed_trainings'] = $training_stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['completed_trainings'] = 0;
}

// Get pending documents
try {
    $docs_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM employee_documents 
        WHERE employee_id = ? AND status = 'pending'
    ");
    $docs_stmt->execute([$employee['id']]);
    $stats['pending_documents'] = $docs_stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['pending_documents'] = 0;
}

// Get active performance goals
try {
    $goals_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM performance_goals 
        WHERE employee_id = ? AND status IN ('active', 'in_progress')
    ");
    $goals_stmt->execute([$employee['id']]);
    $stats['active_goals'] = $goals_stmt->fetch()['count'];
} catch (Exception $e) {
    $stats['active_goals'] = 0;
}

// Calculate onboarding completion percentage
$total_tasks = $stats['pending_tasks'] + 5; // Assume some completed tasks
$completed_tasks = max(0, $total_tasks - $stats['pending_tasks']);
$stats['completion_percentage'] = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo APP_NAME; ?></h1>
                    <span class="ml-4 px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">Employee Portal</span>
                    <?php if (in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin'])): ?>
                        <a href="<?php echo BASE_URL; ?>/switch_interface.php?mode=hr" 
                           class="ml-4 px-3 py-1 bg-purple-100 hover:bg-purple-200 text-purple-800 text-sm font-medium rounded-full transition duration-200">
                            <i class="fas fa-exchange-alt mr-1"></i>Switch to HR Management
                        </a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <div class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                            <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($employee['position']); ?></p>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="text-gray-400 hover:text-red-600 transition duration-200">
                        <i class="fas fa-sign-out-alt text-lg"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome back, <?php echo htmlspecialchars($employee['first_name']); ?>!</h2>
            <p class="text-gray-600">Here's your personal workspace to manage tasks, training, and professional development.</p>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-orange-100 p-3 rounded-full mr-4">
                        <i class="fas fa-tasks text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Pending Tasks</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_tasks']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-graduation-cap text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Completed Training</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_trainings']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Pending Documents</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_documents']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-full mr-4">
                        <i class="fas fa-bullseye text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Active Goals</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_goals']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Main Actions -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Action Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- My Tasks -->
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200">
                        <div class="flex items-center mb-4">
                            <div class="bg-orange-100 p-3 rounded-full mr-4">
                                <i class="fas fa-tasks text-orange-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">My Tasks</h3>
                        </div>
                        <p class="text-gray-600 mb-4">View and complete your onboarding and daily tasks</p>
                        <a href="tasks.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                            View Tasks <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>

                    <!-- Training & Development -->
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200">
                        <div class="flex items-center mb-4">
                            <div class="bg-green-100 p-3 rounded-full mr-4">
                                <i class="fas fa-graduation-cap text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Training</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Access training modules and track your progress</p>
                        <a href="training.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                            Start Learning <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>

                    <!-- Documents -->
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200">
                        <div class="flex items-center mb-4">
                            <div class="bg-blue-100 p-3 rounded-full mr-4">
                                <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">My Documents</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Upload, update and manage your documents</p>
                        <a href="documents.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                            Manage Documents <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>

                    <!-- Policies -->
                    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200">
                        <div class="flex items-center mb-4">
                            <div class="bg-indigo-100 p-3 rounded-full mr-4">
                                <i class="fas fa-book text-indigo-600 text-xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Company Policies</h3>
                        </div>
                        <p class="text-gray-600 mb-4">Read and acknowledge company policies</p>
                        <a href="policies.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                            View Policies <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>

                <!-- Performance Section -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance & Goals</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <a href="<?php echo BASE_URL; ?>/performance/my_goals.php" class="block p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <div class="flex items-center">
                                <i class="fas fa-bullseye text-purple-600 mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-900">My Goals</p>
                                    <p class="text-sm text-gray-600">Track your performance goals</p>
                                </div>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/performance/my_reviews.php" class="block p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                            <div class="flex items-center">
                                <i class="fas fa-star text-yellow-600 mr-3"></i>
                                <div>
                                    <p class="font-medium text-gray-900">My Reviews</p>
                                    <p class="text-sm text-gray-600">View performance reviews</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column - Side Info -->
            <div class="space-y-6">
                <!-- Onboarding Progress -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Onboarding Progress</h3>
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-1">
                            <span>Completion</span>
                            <span><?php echo $stats['completion_percentage']; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $stats['completion_percentage']; ?>%"></div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600">Complete your onboarding tasks to get fully settled in!</p>
                </div>

                <!-- Quick Links -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Links</h3>
                    <div class="space-y-3">
                        <a href="profile.php" class="flex items-center text-gray-700 hover:text-blue-600 transition duration-200">
                            <i class="fas fa-user mr-3"></i>
                            My Profile
                        </a>
                        <a href="attendance.php" class="flex items-center text-gray-700 hover:text-blue-600 transition duration-200">
                            <i class="fas fa-clock mr-3"></i>
                            Attendance (Coming Soon)
                        </a>
                        <a href="leave.php" class="flex items-center text-gray-700 hover:text-blue-600 transition duration-200">
                            <i class="fas fa-calendar-times mr-3"></i>
                            Leave Request (Coming Soon)
                        </a>
                        <a href="payroll.php" class="flex items-center text-gray-700 hover:text-blue-600 transition duration-200">
                            <i class="fas fa-money-bill mr-3"></i>
                            Payroll (Coming Soon)
                        </a>
                        <a href="help.php" class="flex items-center text-gray-700 hover:text-blue-600 transition duration-200">
                            <i class="fas fa-question-circle mr-3"></i>
                            HR Help Desk
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="bg-green-100 rounded-full p-1 mr-3 mt-1">
                                <i class="fas fa-check text-green-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-900">Profile setup completed</p>
                                <p class="text-xs text-gray-500">Today</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="bg-blue-100 rounded-full p-1 mr-3 mt-1">
                                <i class="fas fa-book text-blue-600 text-xs"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-900">Policy acknowledgment pending</p>
                                <p class="text-xs text-gray-500">2 days ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 