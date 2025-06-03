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
        case 'update_plan_status':
            $stmt = $conn->prepare("UPDATE development_plans SET status = ? WHERE id = ?");
            $result = $stmt->execute([$_POST['status'], $_POST['plan_id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_goal_progress':
            $stmt = $conn->prepare("
                UPDATE development_goals 
                SET progress_percentage = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $_POST['progress'], $_POST['notes'], $_POST['goal_id']
            ]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'assign_resource':
            $stmt = $conn->prepare("
                INSERT INTO development_resources (plan_id, resource_type, resource_title, resource_url, description, assigned_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $_POST['plan_id'], $_POST['resource_type'], $_POST['resource_title'],
                $_POST['resource_url'], $_POST['description'], $_SESSION['user_id']
            ]);
            echo json_encode(['success' => $result, 'message' => 'Resource assigned successfully']);
            exit;
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$employee_filter = $_GET['employee'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "dp.status = ?";
    $params[] = $status_filter;
}
if (!empty($employee_filter)) {
    $where_conditions[] = "dp.employee_id = ?";
    $params[] = $employee_filter;
}
if (!empty($search)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR dp.plan_title LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$where_clause = implode(" AND ", $where_conditions);

// Get development plans with detailed information
$plans_query = "
    SELECT dp.*, 
           e.first_name, e.last_name, e.employee_id, e.department, e.position,
           cb.first_name as created_by_first_name, cb.last_name as created_by_last_name,
           (SELECT COUNT(*) FROM development_goals WHERE plan_id = dp.id) as total_goals,
           (SELECT COUNT(*) FROM development_goals WHERE plan_id = dp.id AND status = 'completed') as completed_goals,
           (SELECT AVG(progress_percentage) FROM development_goals WHERE plan_id = dp.id) as avg_progress,
           (SELECT COUNT(*) FROM development_resources WHERE plan_id = dp.id) as total_resources,
           CASE 
               WHEN dp.target_completion_date < CURDATE() AND dp.status != 'completed' THEN 'overdue'
               WHEN dp.target_completion_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND dp.status = 'active' THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status,
           DATEDIFF(dp.target_completion_date, CURDATE()) as days_remaining
    FROM development_plans dp
    JOIN employees e ON dp.employee_id = e.id
    LEFT JOIN users cb ON dp.created_by = cb.id
    WHERE $where_clause
    ORDER BY dp.created_at DESC
";

$plans_stmt = $conn->prepare($plans_query);
$plans_stmt->execute($params);
$plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$employees_stmt = $conn->query("
    SELECT id, first_name, last_name, employee_id, department 
    FROM employees 
    WHERE status = 'active' 
    ORDER BY first_name, last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_plans,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_plans,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_plans,
        SUM(CASE WHEN target_completion_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_plans,
        ROUND(AVG(
            (SELECT AVG(progress_percentage) FROM development_goals WHERE plan_id = dp.id)
        ), 2) as avg_progress
    FROM development_plans dp
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
    <title>Development Plans Management - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Development Plans Management</h1>
                        <p class="text-gray-600">Create and manage employee development plans and career growth</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="create_development_plan.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create Development Plan
                        </a>
                        <a href="development_templates.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-file-alt mr-2"></i>Plan Templates
                        </a>
                        <a href="my_development_plan.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-user-graduate mr-2"></i>My Development Plan
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
                            <p class="text-sm text-gray-600">Total Plans</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_plans']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-play-circle text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Active Plans</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_plans']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_plans']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['overdue_plans']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg. Progress</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['avg_progress'] ?? 0; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search plans or employees..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="on_hold" <?php echo $status_filter == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                                <a href="development.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Development Plans List -->
            <div class="space-y-4">
                <?php if (empty($plans)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-user-graduate text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Development Plans</h3>
                    <p class="text-gray-500">No development plans match your current filters.</p>
                    <a href="create_development_plan.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create First Plan
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 mr-3">
                                    <?php echo htmlspecialchars($plan['plan_title']); ?>
                                </h3>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch($plan['status']) {
                                        case 'active': echo 'bg-green-100 text-green-800'; break;
                                        case 'completed': echo 'bg-purple-100 text-purple-800'; break;
                                        case 'on_hold': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                        default: echo 'bg-red-100 text-red-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $plan['status'])); ?>
                                </span>

                                <?php if ($plan['urgency_status'] == 'overdue'): ?>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </span>
                                <?php elseif ($plan['urgency_status'] == 'due_soon'): ?>
                                    <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Due Soon
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <span class="text-sm text-gray-500">Employee</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($plan['first_name'] . ' ' . $plan['last_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($plan['employee_id'] . ' - ' . $plan['department']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Development Focus</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($plan['development_focus']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($plan['position']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Goals Progress</span>
                                    <p class="font-medium"><?php echo $plan['completed_goals']; ?>/<?php echo $plan['total_goals']; ?> completed</p>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <?php $completion_rate = $plan['total_goals'] > 0 ? ($plan['completed_goals'] / $plan['total_goals']) * 100 : 0; ?>
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Target Date</span>
                                    <p class="font-medium <?php echo $plan['urgency_status'] == 'overdue' ? 'text-red-600' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($plan['target_completion_date'])); ?>
                                    </p>
                                    <?php if ($plan['days_remaining'] > 0): ?>
                                        <p class="text-sm text-gray-600"><?php echo $plan['days_remaining']; ?> days left</p>
                                    <?php elseif ($plan['days_remaining'] < 0): ?>
                                        <p class="text-sm text-red-600"><?php echo abs($plan['days_remaining']); ?> days overdue</p>
                                    <?php else: ?>
                                        <p class="text-sm text-yellow-600">Due today</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($plan['description']): ?>
                            <div class="mb-3">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($plan['description']); ?></p>
                            </div>
                            <?php endif; ?>

                            <!-- Progress Overview -->
                            <div class="mb-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Overall Progress</span>
                                    <span class="text-sm text-gray-600"><?php echo round($plan['avg_progress'] ?? 0); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-blue-500 h-3 rounded-full" style="width: <?php echo round($plan['avg_progress'] ?? 0); ?>%"></div>
                                </div>
                            </div>

                            <div class="text-sm text-gray-600">
                                <i class="fas fa-user mr-1"></i>
                                Created by: <?php echo htmlspecialchars($plan['created_by_first_name'] . ' ' . $plan['created_by_last_name']); ?>
                                <span class="ml-4">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Created: <?php echo date('M j, Y', strtotime($plan['created_at'])); ?>
                                </span>
                                <span class="ml-4">
                                    <i class="fas fa-book mr-1"></i>
                                    Resources: <?php echo $plan['total_resources']; ?>
                                </span>
                            </div>
                        </div>

                        <div class="ml-6 flex flex-col space-y-2">
                            <a href="view_development_plan.php?id=<?php echo $plan['id']; ?>" 
                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </a>
                            
                            <a href="edit_development_plan.php?id=<?php echo $plan['id']; ?>" 
                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                <i class="fas fa-edit mr-1"></i>Edit Plan
                            </a>
                            
                            <button onclick="updatePlanStatus(<?php echo $plan['id']; ?>)" 
                                    class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-tasks mr-1"></i>Update Status
                            </button>
                            
                            <button onclick="assignResource(<?php echo $plan['id']; ?>)" 
                                    class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-book mr-1"></i>Add Resource
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
                    <h3 class="text-lg font-medium text-gray-900">Update Plan Status</h3>
                    <button onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="statusForm" class="space-y-4">
                    <input type="hidden" id="statusPlanId" name="plan_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select id="newStatus" name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
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

    <!-- Resource Assignment Modal -->
    <div id="resourceModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Assign Learning Resource</h3>
                    <button onclick="closeResourceModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="resourceForm" class="space-y-4">
                    <input type="hidden" id="resourcePlanId" name="plan_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Resource Type</label>
                        <select name="resource_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="course">Online Course</option>
                            <option value="book">Book</option>
                            <option value="article">Article</option>
                            <option value="video">Video Tutorial</option>
                            <option value="workshop">Workshop</option>
                            <option value="certification">Certification</option>
                            <option value="mentoring">Mentoring</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Resource Title</label>
                        <input type="text" name="resource_title" required 
                               placeholder="Enter resource title..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Resource URL (Optional)</label>
                        <input type="url" name="resource_url" 
                               placeholder="https://example.com/resource"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" 
                                  placeholder="Brief description of the resource..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeResourceModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Assign Resource
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updatePlanStatus(planId) {
            document.getElementById('statusPlanId').value = planId;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function assignResource(planId) {
            document.getElementById('resourcePlanId').value = planId;
            document.getElementById('resourceModal').classList.remove('hidden');
        }

        function closeResourceModal() {
            document.getElementById('resourceModal').classList.add('hidden');
        }

        // Status form submission
        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_plan_status');

            fetch('development.php', {
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

        // Resource form submission
        document.getElementById('resourceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'assign_resource');

            fetch('development.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeResourceModal();
                    location.reload();
                } else {
                    alert('Error assigning resource');
                }
            });
        });

        // Close modals when clicking outside
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStatusModal();
            }
        });

        document.getElementById('resourceModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResourceModal();
            }
        });
    </script>
</body>
</html> 