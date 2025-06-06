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

// Handle training actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'start_training') {
        $module_id = $_POST['module_id'];
        // Insert or update employee training record
        $stmt = $conn->prepare("INSERT INTO employee_training (employee_id, training_module_id, status, started_at) VALUES (?, ?, 'in_progress', NOW()) ON DUPLICATE KEY UPDATE status = 'in_progress', started_at = NOW()");
        $stmt->execute([$employee['id'], $module_id]);
        $success_message = "Training module started!";
    }
    
    if ($_POST['action'] == 'complete_training') {
        $module_id = $_POST['module_id'];
        $stmt = $conn->prepare("UPDATE employee_training SET status = 'completed', completed_at = NOW() WHERE employee_id = ? AND training_module_id = ?");
        $stmt->execute([$employee['id'], $module_id]);
        $success_message = "Training module completed!";
    }
}

// Get training modules with progress
try {
    $training_query = "
        SELECT tm.*, et.status as progress_status, et.started_at, et.completed_at,
               CASE 
                   WHEN et.status IS NULL THEN 'not_started'
                   ELSE et.status
               END as current_status
        FROM training_modules tm
        LEFT JOIN employee_training et ON tm.id = et.training_module_id AND et.employee_id = ?
        ORDER BY tm.is_mandatory DESC, tm.order_sequence ASC, tm.title ASC
    ";
    $training_stmt = $conn->prepare($training_query);
    $training_stmt->execute([$employee['id']]);
    $training_modules = $training_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If tables don't exist, create sample data
    $training_modules = [
        [
            'id' => 1,
            'title' => 'Company Orientation',
            'description' => 'Learn about our company culture, values, and mission',
            'content_type' => 'video',
            'duration_minutes' => 45,
            'is_mandatory' => 1,
            'current_status' => 'completed'
        ],
        [
            'id' => 2,
            'title' => 'Cybersecurity Awareness',
            'description' => 'Essential security practices and protocols for all employees',
            'content_type' => 'interactive',
            'duration_minutes' => 30,
            'is_mandatory' => 1,
            'current_status' => 'in_progress'
        ],
        [
            'id' => 3,
            'title' => 'Communication Skills',
            'description' => 'Enhance your professional communication abilities',
            'content_type' => 'course',
            'duration_minutes' => 120,
            'is_mandatory' => 0,
            'current_status' => 'not_started'
        ],
        [
            'id' => 4,
            'title' => 'Time Management',
            'description' => 'Boost productivity with effective time management techniques',
            'content_type' => 'workshop',
            'duration_minutes' => 90,
            'is_mandatory' => 0,
            'current_status' => 'not_started'
        ]
    ];
}

// Group modules by status
$mandatory_modules = array_filter($training_modules, function($module) { return $module['is_mandatory']; });
$optional_modules = array_filter($training_modules, function($module) { return !$module['is_mandatory']; });

// Calculate progress
$total_mandatory = count($mandatory_modules);
$completed_mandatory = count(array_filter($mandatory_modules, function($module) { return $module['current_status'] == 'completed'; }));
$mandatory_progress = $total_mandatory > 0 ? round(($completed_mandatory / $total_mandatory) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training & Development - Employee Portal</title>
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
                    <h1 class="text-2xl font-bold text-gray-900">Training & Development</h1>
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

        <!-- Progress Overview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Training Progress Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $completed_mandatory; ?>/<?php echo $total_mandatory; ?></div>
                    <p class="text-gray-600">Mandatory Training Completed</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600"><?php echo $mandatory_progress; ?>%</div>
                    <p class="text-gray-600">Completion Rate</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600"><?php echo count($training_modules); ?></div>
                    <p class="text-gray-600">Total Modules Available</p>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Mandatory Training Progress</span>
                    <span><?php echo $mandatory_progress; ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: <?php echo $mandatory_progress; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Mandatory Training -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                Mandatory Training
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($mandatory_modules as $module): ?>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $module['current_status'] == 'completed' ? 'border-green-500' : ($module['current_status'] == 'in_progress' ? 'border-blue-500' : 'border-red-500'); ?>">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($module['title']); ?></h3>
                            <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($module['description']); ?></p>
                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                <span><i class="fas fa-clock mr-1"></i><?php echo $module['duration_minutes']; ?> minutes</span>
                                <span><i class="fas fa-tag mr-1"></i><?php echo ucfirst($module['content_type']); ?></span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <?php if ($module['current_status'] == 'completed'): ?>
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-check mr-1"></i>Completed
                                </span>
                            <?php elseif ($module['current_status'] == 'in_progress'): ?>
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-play mr-1"></i>In Progress
                                </span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                                    Required
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <?php if ($module['current_status'] == 'not_started'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="start_training">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                    <i class="fas fa-play mr-2"></i>Start Training
                                </button>
                            </form>
                        <?php elseif ($module['current_status'] == 'in_progress'): ?>
                            <a href="training_content.php?id=<?php echo $module['id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                                <i class="fas fa-arrow-right mr-2"></i>Continue
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="complete_training">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                                    <i class="fas fa-check mr-2"></i>Mark Complete
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="training_content.php?id=<?php echo $module['id']; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                                <i class="fas fa-eye mr-2"></i>Review
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Optional Training -->
        <div>
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-star text-yellow-600 mr-2"></i>
                Professional Development (Optional)
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($optional_modules as $module): ?>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 <?php echo $module['current_status'] == 'completed' ? 'border-green-500' : ($module['current_status'] == 'in_progress' ? 'border-blue-500' : 'border-gray-300'); ?>">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($module['title']); ?></h3>
                            <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($module['description']); ?></p>
                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                <span><i class="fas fa-clock mr-1"></i><?php echo $module['duration_minutes']; ?> minutes</span>
                                <span><i class="fas fa-tag mr-1"></i><?php echo ucfirst($module['content_type']); ?></span>
                            </div>
                        </div>
                        <div class="ml-4">
                            <?php if ($module['current_status'] == 'completed'): ?>
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-check mr-1"></i>Completed
                                </span>
                            <?php elseif ($module['current_status'] == 'in_progress'): ?>
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-play mr-1"></i>In Progress
                                </span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                                    Optional
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <?php if ($module['current_status'] == 'not_started'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="start_training">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                    <i class="fas fa-play mr-2"></i>Start Training
                                </button>
                            </form>
                        <?php elseif ($module['current_status'] == 'in_progress'): ?>
                            <a href="training_content.php?id=<?php echo $module['id']; ?>" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200">
                                <i class="fas fa-arrow-right mr-2"></i>Continue
                            </a>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="complete_training">
                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                                    <i class="fas fa-check mr-2"></i>Mark Complete
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="training_content.php?id=<?php echo $module['id']; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                                <i class="fas fa-eye mr-2"></i>Review
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Training Tips -->
        <div class="mt-8 bg-blue-50 border-l-4 border-blue-400 p-6 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-lightbulb text-blue-400 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-blue-800">Training Tips</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Complete mandatory training modules first to ensure compliance</li>
                            <li>Set aside dedicated time for learning without distractions</li>
                            <li>Take notes during training sessions for future reference</li>
                            <li>Reach out to your manager if you have questions about any training content</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 