<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in
requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get current user's employee record
$employee_stmt = $conn->prepare("SELECT id FROM employees WHERE email = ?");
$employee_stmt->execute([$_SESSION['email']]);
$employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee record not found. Please contact HR.");
}

$employee_id = $employee['id'];

// Handle progress updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_progress') {
    header('Content-Type: application/json');
    
    $stmt = $conn->prepare("
        UPDATE performance_goals SET 
        current_value = ?, progress_percentage = ?, status = ?
        WHERE id = ? AND employee_id = ?
    ");
    $result = $stmt->execute([
        $_POST['current_value'], $_POST['progress_percentage'], 
        $_POST['status'], $_POST['goal_id'], $employee_id
    ]);
    echo json_encode(['success' => $result]);
    exit;
}

// Get employee's goals
$goals_query = "
    SELECT g.*, 
           m.first_name as manager_first_name, m.last_name as manager_last_name,
           CASE 
               WHEN g.due_date < CURDATE() AND g.status NOT IN ('completed', 'cancelled') THEN 'overdue'
               WHEN g.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND g.status NOT IN ('completed', 'cancelled') THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status,
           DATEDIFF(g.due_date, CURDATE()) as days_remaining
    FROM performance_goals g
    JOIN employees m ON g.manager_id = m.id
    WHERE g.employee_id = ?
    ORDER BY g.due_date ASC, g.priority DESC
";

$goals_stmt = $conn->prepare($goals_query);
$goals_stmt->execute([$employee_id]);
$goals = $goals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get goal statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_goals,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_goals,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_goals,
        AVG(progress_percentage) as avg_progress
    FROM performance_goals
    WHERE employee_id = ?
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$employee_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance Goals - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">My Performance Goals</h1>
                <p class="text-gray-600">Track and update progress on your performance goals</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-bullseye text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Goals</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_goals']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_goals']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">In Progress</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['in_progress_goals']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg Progress</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo round($stats['avg_progress'] ?? 0, 1); ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Goals List -->
            <div class="space-y-6">
                <?php if (empty($goals)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-bullseye text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Goals Assigned</h3>
                    <p class="text-gray-500">You don't have any performance goals assigned yet. Contact your manager to set up your goals.</p>
                </div>
                <?php else: ?>
                <?php foreach ($goals as $goal): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 mr-3"><?php echo htmlspecialchars($goal['goal_title']); ?></h3>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $goal['priority'] == 'critical' ? 'bg-red-100 text-red-800' : 
                                               ($goal['priority'] == 'high' ? 'bg-orange-100 text-orange-800' : 
                                               ($goal['priority'] == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')); ?>">
                                    <?php echo ucfirst($goal['priority']); ?> Priority
                                </span>
                            </div>
                            <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($goal['goal_description']); ?></p>
                            <div class="flex items-center text-sm text-gray-500 space-x-4">
                                <span><i class="fas fa-user mr-1"></i>Manager: <?php echo htmlspecialchars($goal['manager_first_name'] . ' ' . $goal['manager_last_name']); ?></span>
                                <span><i class="fas fa-calendar mr-1"></i>Due: <?php echo date('M j, Y', strtotime($goal['due_date'])); ?></span>
                                <?php if ($goal['days_remaining'] !== null): ?>
                                <span class="<?php echo $goal['urgency_status'] == 'overdue' ? 'text-red-600 font-semibold' : 
                                                     ($goal['urgency_status'] == 'due_soon' ? 'text-yellow-600 font-semibold' : ''); ?>">
                                    <i class="fas fa-hourglass-half mr-1"></i>
                                    <?php 
                                    if ($goal['days_remaining'] < 0) {
                                        echo abs($goal['days_remaining']) . ' days overdue';
                                    } else {
                                        echo $goal['days_remaining'] . ' days remaining';
                                    }
                                    ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-6">
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $goal['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                       ($goal['status'] == 'in_progress' ? 'bg-blue-100 text-blue-800' : 
                                       ($goal['status'] == 'active' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $goal['status'])); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Progress Section -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-medium text-gray-700">Progress</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo $goal['progress_percentage']; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 mb-4">
                            <div class="bg-blue-500 h-3 rounded-full transition-all duration-300" style="width: <?php echo $goal['progress_percentage']; ?>%"></div>
                        </div>

                        <?php if ($goal['target_value']): ?>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <span class="text-xs text-gray-500">Target Value</span>
                                <p class="text-sm font-medium"><?php echo $goal['target_value'] . ($goal['unit_of_measure'] ? ' ' . $goal['unit_of_measure'] : ''); ?></p>
                            </div>
                            <div>
                                <span class="text-xs text-gray-500">Current Value</span>
                                <p class="text-sm font-medium"><?php echo ($goal['current_value'] ?? 0) . ($goal['unit_of_measure'] ? ' ' . $goal['unit_of_measure'] : ''); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Update Progress Button -->
                        <button onclick="openProgressModal(<?php echo htmlspecialchars(json_encode($goal)); ?>)" 
                                class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>Update Progress
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Progress Update Modal -->
    <div id="progressModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="progressModalTitle">Update Goal Progress</h3>
                    <button onclick="closeProgressModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="progressForm" class="space-y-4">
                    <input type="hidden" id="progressGoalId" name="goal_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Goal Title</label>
                        <input type="text" id="progressGoalTitle" readonly 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Value</label>
                            <input type="number" id="progressCurrentValue" name="current_value" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Progress (%)</label>
                            <input type="number" id="progressPercentage" name="progress_percentage" min="0" max="100" step="0.1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select id="progressStatus" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="active">Active</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="paused">Paused</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeProgressModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Update Progress
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openProgressModal(goal) {
            document.getElementById('progressModalTitle').textContent = 'Update Progress: ' + goal.goal_title;
            document.getElementById('progressGoalId').value = goal.id;
            document.getElementById('progressGoalTitle').value = goal.goal_title;
            document.getElementById('progressCurrentValue').value = goal.current_value || '';
            document.getElementById('progressPercentage').value = goal.progress_percentage || '';
            document.getElementById('progressStatus').value = goal.status;
            
            document.getElementById('progressModal').classList.remove('hidden');
        }

        function closeProgressModal() {
            document.getElementById('progressModal').classList.add('hidden');
        }

        document.getElementById('progressForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_progress');

            fetch('my_goals.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating progress');
                }
            });
        });

        // Auto-calculate progress percentage based on current vs target value
        document.getElementById('progressCurrentValue').addEventListener('input', function() {
            const currentValue = parseFloat(this.value) || 0;
            const goalData = JSON.parse(document.getElementById('progressGoalId').getAttribute('data-goal') || '{}');
            const targetValue = parseFloat(goalData.target_value) || 0;
            
            if (targetValue > 0) {
                const percentage = Math.min(100, (currentValue / targetValue) * 100);
                document.getElementById('progressPercentage').value = percentage.toFixed(1);
            }
        });

        // Close modal when clicking outside
        document.getElementById('progressModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProgressModal();
            }
        });
    </script>
</body>
</html> 