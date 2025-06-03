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
        case 'create_cycle':
            $stmt = $conn->prepare("
                INSERT INTO performance_cycles 
                (cycle_name, cycle_type, cycle_year, cycle_period, start_date, end_date, 
                 review_deadline, description, instructions, is_360_enabled, is_self_review_enabled, 
                 is_manager_review_enabled, is_peer_review_enabled, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $_POST['cycle_name'], $_POST['cycle_type'], $_POST['cycle_year'],
                $_POST['cycle_period'], $_POST['start_date'], $_POST['end_date'],
                $_POST['review_deadline'], $_POST['description'], $_POST['instructions'],
                isset($_POST['is_360_enabled']) ? 1 : 0,
                isset($_POST['is_self_review_enabled']) ? 1 : 0,
                isset($_POST['is_manager_review_enabled']) ? 1 : 0,
                isset($_POST['is_peer_review_enabled']) ? 1 : 0,
                $_SESSION['user_id']
            ]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_cycle':
            $stmt = $conn->prepare("
                UPDATE performance_cycles SET 
                cycle_name = ?, cycle_type = ?, cycle_year = ?, cycle_period = ?, 
                start_date = ?, end_date = ?, review_deadline = ?, status = ?,
                description = ?, instructions = ?, is_360_enabled = ?, 
                is_self_review_enabled = ?, is_manager_review_enabled = ?, is_peer_review_enabled = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $_POST['cycle_name'], $_POST['cycle_type'], $_POST['cycle_year'],
                $_POST['cycle_period'], $_POST['start_date'], $_POST['end_date'],
                $_POST['review_deadline'], $_POST['status'], $_POST['description'],
                $_POST['instructions'], isset($_POST['is_360_enabled']) ? 1 : 0,
                isset($_POST['is_self_review_enabled']) ? 1 : 0,
                isset($_POST['is_manager_review_enabled']) ? 1 : 0,
                isset($_POST['is_peer_review_enabled']) ? 1 : 0,
                $_POST['cycle_id']
            ]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'delete_cycle':
            $stmt = $conn->prepare("DELETE FROM performance_cycles WHERE id = ?");
            $result = $stmt->execute([$_POST['cycle_id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'assign_employees':
            $cycle_id = $_POST['cycle_id'];
            $employee_ids = json_decode($_POST['employee_ids'], true);
            
            $success_count = 0;
            foreach ($employee_ids as $employee_id) {
                // Create self review
                $stmt = $conn->prepare("
                    INSERT IGNORE INTO performance_reviews 
                    (cycle_id, employee_id, reviewer_id, review_type, due_date) 
                    VALUES (?, ?, ?, 'self', (SELECT review_deadline FROM performance_cycles WHERE id = ?))
                ");
                if ($stmt->execute([$cycle_id, $employee_id, $employee_id, $cycle_id])) {
                    $success_count++;
                }
                
                // Get manager and create manager review
                $manager_stmt = $conn->prepare("SELECT manager_id FROM employees WHERE id = ?");
                $manager_stmt->execute([$employee_id]);
                $manager = $manager_stmt->fetch();
                
                if ($manager && $manager['manager_id']) {
                    $stmt = $conn->prepare("
                        INSERT IGNORE INTO performance_reviews 
                        (cycle_id, employee_id, reviewer_id, review_type, due_date) 
                        VALUES (?, ?, ?, 'manager', (SELECT review_deadline FROM performance_cycles WHERE id = ?))
                    ");
                    $stmt->execute([$cycle_id, $employee_id, $manager['manager_id'], $cycle_id]);
                }
            }
            
            echo json_encode(['success' => true, 'assigned_count' => $success_count]);
            exit;
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$year_filter = $_GET['year'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}
if (!empty($type_filter)) {
    $where_conditions[] = "cycle_type = ?";
    $params[] = $type_filter;
}
if (!empty($year_filter)) {
    $where_conditions[] = "cycle_year = ?";
    $params[] = $year_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get cycles with review counts
$cycles_query = "
    SELECT c.*, 
           u.first_name as created_by_name, u.last_name as created_by_lastname,
           COUNT(DISTINCT pr.employee_id) as assigned_employees,
           COUNT(CASE WHEN pr.status = 'completed' THEN 1 END) as completed_reviews,
           COUNT(pr.id) as total_reviews,
           CASE 
               WHEN c.end_date < CURDATE() AND c.status = 'active' THEN 'overdue'
               WHEN c.review_deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND c.status = 'active' THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status
    FROM performance_cycles c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN performance_reviews pr ON c.id = pr.cycle_id
    WHERE $where_clause
    GROUP BY c.id
    ORDER BY c.cycle_year DESC, c.start_date DESC
";

$cycles_stmt = $conn->prepare($cycles_query);
$cycles_stmt->execute($params);
$cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for assignment
$employees_stmt = $conn->query("
    SELECT id, first_name, last_name, employee_id, department, position 
    FROM employees 
    WHERE status = 'active' 
    ORDER BY first_name, last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_cycles,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_cycles,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_cycles,
        SUM(CASE WHEN review_deadline < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as overdue_cycles
    FROM performance_cycles
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get available years
$years_stmt = $conn->query("SELECT DISTINCT cycle_year FROM performance_cycles ORDER BY cycle_year DESC");
$years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Review Cycles - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Performance Review Cycles</h1>
                        <p class="text-gray-600">Create and manage performance review periods</p>
                    </div>
                    <button onclick="openCreateCycleModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create New Cycle
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-sync text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Cycles</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_cycles']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-3 rounded-full mr-4">
                            <i class="fas fa-play text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Active</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_cycles']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-gray-100 p-3 rounded-full mr-4">
                            <i class="fas fa-check text-gray-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Completed</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_cycles']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['overdue_cycles']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="in_review" <?php echo $status_filter == 'in_review' ? 'selected' : ''; ?>>In Review</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="archived" <?php echo $status_filter == 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            <option value="annual" <?php echo $type_filter == 'annual' ? 'selected' : ''; ?>>Annual</option>
                            <option value="semi_annual" <?php echo $type_filter == 'semi_annual' ? 'selected' : ''; ?>>Semi-Annual</option>
                            <option value="quarterly" <?php echo $type_filter == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="monthly" <?php echo $type_filter == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="project_based" <?php echo $type_filter == 'project_based' ? 'selected' : ''; ?>>Project-Based</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                        <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">All Years</option>
                            <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year_filter == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="cycles.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Cycles List -->
            <div class="space-y-6">
                <?php if (empty($cycles)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-sync text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Review Cycles</h3>
                    <p class="text-gray-500">Create your first performance review cycle to get started.</p>
                </div>
                <?php else: ?>
                <?php foreach ($cycles as $cycle): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center mb-2">
                                <h3 class="text-xl font-semibold text-gray-900 mr-3"><?php echo htmlspecialchars($cycle['cycle_name']); ?></h3>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $cycle['status'] == 'active' ? 'bg-green-100 text-green-800' : 
                                               ($cycle['status'] == 'completed' ? 'bg-gray-100 text-gray-800' : 
                                               ($cycle['status'] == 'in_review' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800')); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $cycle['status'])); ?>
                                </span>
                                <?php if ($cycle['urgency_status'] == 'overdue'): ?>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </span>
                                <?php elseif ($cycle['urgency_status'] == 'due_soon'): ?>
                                    <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Due Soon
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <span class="text-sm text-gray-500">Type</span>
                                    <p class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $cycle['cycle_type'])); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Period</span>
                                    <p class="font-medium"><?php echo date('M j', strtotime($cycle['start_date'])) . ' - ' . date('M j, Y', strtotime($cycle['end_date'])); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Review Deadline</span>
                                    <p class="font-medium <?php echo $cycle['urgency_status'] == 'overdue' ? 'text-red-600' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($cycle['review_deadline'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Progress</span>
                                    <p class="font-medium">
                                        <?php 
                                        $completion_rate = $cycle['total_reviews'] > 0 ? 
                                            round(($cycle['completed_reviews'] / $cycle['total_reviews']) * 100) : 0;
                                        echo $cycle['completed_reviews'] . '/' . $cycle['total_reviews'] . ' (' . $completion_rate . '%)';
                                        ?>
                                    </p>
                                </div>
                            </div>

                            <?php if ($cycle['description']): ?>
                            <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($cycle['description']); ?></p>
                            <?php endif; ?>

                            <!-- Review Types Enabled -->
                            <div class="flex items-center space-x-4 text-sm">
                                <span class="text-gray-500">Review Types:</span>
                                <?php if ($cycle['is_self_review_enabled']): ?>
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">Self Review</span>
                                <?php endif; ?>
                                <?php if ($cycle['is_manager_review_enabled']): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Manager Review</span>
                                <?php endif; ?>
                                <?php if ($cycle['is_peer_review_enabled']): ?>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">Peer Review</span>
                                <?php endif; ?>
                                <?php if ($cycle['is_360_enabled']): ?>
                                    <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full text-xs">360° Feedback</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="ml-6 flex flex-col space-y-2">
                            <button onclick="editCycle(<?php echo htmlspecialchars(json_encode($cycle)); ?>)" 
                                    class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="openAssignModal(<?php echo $cycle['id']; ?>, '<?php echo htmlspecialchars($cycle['cycle_name']); ?>')" 
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-users mr-1"></i>Assign
                            </button>
                            <button onclick="deleteCycle(<?php echo $cycle['id']; ?>)" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <?php if ($cycle['total_reviews'] > 0): ?>
                    <div class="mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Review Completion</span>
                            <span class="text-sm text-gray-500"><?php echo $completion_rate; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: <?php echo $completion_rate; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create/Edit Cycle Modal -->
    <div id="cycleModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="cycleModalTitle">Create New Review Cycle</h3>
                    <button onclick="closeCycleModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="cycleForm" class="space-y-4">
                    <input type="hidden" id="cycleId" name="cycle_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cycle Name *</label>
                            <input type="text" id="cycleName" name="cycle_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cycle Type *</label>
                            <select id="cycleType" name="cycle_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="annual">Annual</option>
                                <option value="semi_annual">Semi-Annual</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="monthly">Monthly</option>
                                <option value="project_based">Project-Based</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Year *</label>
                            <input type="number" id="cycleYear" name="cycle_year" required min="2020" max="2030"
                                   value="<?php echo date('Y'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Period</label>
                            <input type="text" id="cyclePeriod" name="cycle_period" 
                                   placeholder="e.g., Q1, H1, Jan-Mar"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div id="statusField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="cycleStatus" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="in_review">In Review</option>
                                <option value="completed">Completed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date *</label>
                            <input type="date" id="startDate" name="start_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date *</label>
                            <input type="date" id="endDate" name="end_date" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Review Deadline *</label>
                            <input type="date" id="reviewDeadline" name="review_deadline" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="cycleDescription" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Instructions for Reviewers</label>
                        <textarea id="cycleInstructions" name="instructions" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <!-- Review Type Configuration -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Review Types to Enable</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="flex items-center">
                                <input id="selfReviewEnabled" name="is_self_review_enabled" type="checkbox" checked
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="selfReviewEnabled" class="ml-2 text-sm text-gray-700">Self Review</label>
                            </div>

                            <div class="flex items-center">
                                <input id="managerReviewEnabled" name="is_manager_review_enabled" type="checkbox" checked
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="managerReviewEnabled" class="ml-2 text-sm text-gray-700">Manager Review</label>
                            </div>

                            <div class="flex items-center">
                                <input id="peerReviewEnabled" name="is_peer_review_enabled" type="checkbox"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="peerReviewEnabled" class="ml-2 text-sm text-gray-700">Peer Review</label>
                            </div>

                            <div class="flex items-center">
                                <input id="is360Enabled" name="is_360_enabled" type="checkbox"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is360Enabled" class="ml-2 text-sm text-gray-700">360° Feedback</label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeCycleModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            <span id="cycleSubmitButtonText">Create Cycle</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Employee Assignment Modal -->
    <div id="assignModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="assignModalTitle">Assign Employees to Review Cycle</h3>
                    <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="assignForm" class="space-y-4">
                    <input type="hidden" id="assignCycleId" name="cycle_id">
                    
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-sm font-medium text-gray-700">Select Employees</label>
                            <div class="space-x-2">
                                <button type="button" onclick="selectAllEmployees()" class="text-blue-600 hover:text-blue-800 text-sm">Select All</button>
                                <button type="button" onclick="clearAllEmployees()" class="text-gray-600 hover:text-gray-800 text-sm">Clear All</button>
                            </div>
                        </div>
                        <div class="max-h-64 overflow-y-auto border border-gray-300 rounded-lg p-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <?php foreach ($employees as $employee): ?>
                                <div class="flex items-center">
                                    <input type="checkbox" id="emp_<?php echo $employee['id']; ?>" 
                                           name="employee_ids[]" value="<?php echo $employee['id']; ?>"
                                           class="employee-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="emp_<?php echo $employee['id']; ?>" class="ml-2 text-sm text-gray-700">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        <span class="text-gray-500">(<?php echo htmlspecialchars($employee['department']); ?>)</span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" onclick="closeAssignModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition duration-200">
                            <i class="fas fa-users mr-2"></i>Assign Employees
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateCycleModal() {
            document.getElementById('cycleModalTitle').textContent = 'Create New Review Cycle';
            document.getElementById('cycleSubmitButtonText').textContent = 'Create Cycle';
            document.getElementById('cycleForm').reset();
            document.getElementById('cycleId').value = '';
            document.getElementById('statusField').classList.add('hidden');
            
            // Set default year
            document.getElementById('cycleYear').value = new Date().getFullYear();
            
            document.getElementById('cycleModal').classList.remove('hidden');
        }

        function editCycle(cycle) {
            document.getElementById('cycleModalTitle').textContent = 'Edit Review Cycle';
            document.getElementById('cycleSubmitButtonText').textContent = 'Update Cycle';
            document.getElementById('statusField').classList.remove('hidden');
            
            // Populate form
            document.getElementById('cycleId').value = cycle.id;
            document.getElementById('cycleName').value = cycle.cycle_name;
            document.getElementById('cycleType').value = cycle.cycle_type;
            document.getElementById('cycleYear').value = cycle.cycle_year;
            document.getElementById('cyclePeriod').value = cycle.cycle_period || '';
            document.getElementById('startDate').value = cycle.start_date;
            document.getElementById('endDate').value = cycle.end_date;
            document.getElementById('reviewDeadline').value = cycle.review_deadline;
            document.getElementById('cycleDescription').value = cycle.description || '';
            document.getElementById('cycleInstructions').value = cycle.instructions || '';
            document.getElementById('cycleStatus').value = cycle.status;
            
            // Set checkboxes
            document.getElementById('selfReviewEnabled').checked = cycle.is_self_review_enabled == 1;
            document.getElementById('managerReviewEnabled').checked = cycle.is_manager_review_enabled == 1;
            document.getElementById('peerReviewEnabled').checked = cycle.is_peer_review_enabled == 1;
            document.getElementById('is360Enabled').checked = cycle.is_360_enabled == 1;
            
            document.getElementById('cycleModal').classList.remove('hidden');
        }

        function closeCycleModal() {
            document.getElementById('cycleModal').classList.add('hidden');
        }

        function openAssignModal(cycleId, cycleName) {
            document.getElementById('assignModalTitle').textContent = 'Assign Employees to: ' + cycleName;
            document.getElementById('assignCycleId').value = cycleId;
            
            // Clear all checkboxes
            document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
            
            document.getElementById('assignModal').classList.remove('hidden');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.add('hidden');
        }

        function selectAllEmployees() {
            document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = true);
        }

        function clearAllEmployees() {
            document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
        }

        function deleteCycle(cycleId) {
            if (confirm('Are you sure you want to delete this review cycle? This will also delete all associated reviews.')) {
                const formData = new FormData();
                formData.append('action', 'delete_cycle');
                formData.append('cycle_id', cycleId);

                fetch('cycles.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error deleting cycle');
                    }
                });
            }
        }

        document.getElementById('cycleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const cycleId = document.getElementById('cycleId').value;
            formData.append('action', cycleId ? 'update_cycle' : 'create_cycle');

            fetch('cycles.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error saving cycle');
                }
            });
        });

        document.getElementById('assignForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const checkedEmployees = Array.from(document.querySelectorAll('.employee-checkbox:checked')).map(cb => cb.value);
            
            if (checkedEmployees.length === 0) {
                alert('Please select at least one employee');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'assign_employees');
            formData.append('cycle_id', document.getElementById('assignCycleId').value);
            formData.append('employee_ids', JSON.stringify(checkedEmployees));

            fetch('cycles.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Successfully assigned ${data.assigned_count} employees to the review cycle`);
                    closeAssignModal();
                    location.reload();
                } else {
                    alert('Error assigning employees');
                }
            });
        });

        // Close modals when clicking outside
        document.getElementById('cycleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCycleModal();
            }
        });

        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAssignModal();
            }
        });
    </script>
</body>
</html> 