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
        case 'create_feedback_request':
            try {
                $conn->beginTransaction();
                
                // Create main feedback request
                $stmt = $conn->prepare("
                    INSERT INTO feedback_360_requests (employee_id, cycle_id, requested_by, title, description, deadline, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $_POST['employee_id'], $_POST['cycle_id'], $_SESSION['user_id'],
                    $_POST['title'], $_POST['description'], $_POST['deadline']
                ]);
                
                $request_id = $conn->lastInsertId();
                
                // Add feedback providers
                if (isset($_POST['providers']) && is_array($_POST['providers'])) {
                    $provider_stmt = $conn->prepare("
                        INSERT INTO feedback_360_providers (request_id, provider_id, relationship_type, status)
                        VALUES (?, ?, ?, 'pending')
                    ");
                    
                    foreach ($_POST['providers'] as $provider) {
                        $provider_stmt->execute([
                            $request_id, $provider['provider_id'], $provider['relationship_type']
                        ]);
                    }
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'request_id' => $request_id]);
            } catch (Exception $e) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'update_request_status':
            $stmt = $conn->prepare("UPDATE feedback_360_requests SET status = ? WHERE id = ?");
            $result = $stmt->execute([$_POST['status'], $_POST['request_id']]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'send_reminder':
            $stmt = $conn->prepare("
                INSERT INTO feedback_360_reminders (provider_id, request_id, sent_by, reminder_message)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $_POST['provider_id'], $_POST['request_id'], $_SESSION['user_id'], $_POST['message']
            ]);
            echo json_encode(['success' => $result, 'message' => 'Reminder sent successfully']);
            exit;
    }
}

// Get filters
$cycle_filter = $_GET['cycle'] ?? '';
$status_filter = $_GET['status'] ?? '';
$employee_filter = $_GET['employee'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($cycle_filter)) {
    $where_conditions[] = "fr.cycle_id = ?";
    $params[] = $cycle_filter;
}
if (!empty($status_filter)) {
    $where_conditions[] = "fr.status = ?";
    $params[] = $status_filter;
}
if (!empty($employee_filter)) {
    $where_conditions[] = "fr.employee_id = ?";
    $params[] = $employee_filter;
}
if (!empty($search)) {
    $where_conditions[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR fr.title LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$where_clause = implode(" AND ", $where_conditions);

// Get 360 feedback requests with detailed information
$requests_query = "
    SELECT fr.*, 
           pc.cycle_name, pc.cycle_year,
           e.first_name as employee_first_name, e.last_name as employee_last_name, 
           e.employee_id as employee_number, e.department as employee_department,
           rb.first_name as requested_by_first_name, rb.last_name as requested_by_last_name,
           (SELECT COUNT(*) FROM feedback_360_providers WHERE request_id = fr.id) as total_providers,
           (SELECT COUNT(*) FROM feedback_360_providers WHERE request_id = fr.id AND status = 'completed') as completed_providers,
           (SELECT COUNT(*) FROM feedback_360_responses WHERE request_id = fr.id) as total_responses,
           CASE 
               WHEN fr.deadline < CURDATE() AND fr.status = 'active' THEN 'overdue'
               WHEN fr.deadline <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND fr.status = 'active' THEN 'due_soon'
               ELSE 'on_track'
           END as urgency_status,
           DATEDIFF(fr.deadline, CURDATE()) as days_remaining
    FROM feedback_360_requests fr
    JOIN performance_cycles pc ON fr.cycle_id = pc.id
    JOIN employees e ON fr.employee_id = e.id
    JOIN users rb ON fr.requested_by = rb.id
    WHERE $where_clause
    ORDER BY fr.deadline ASC, fr.created_at DESC
";

$requests_stmt = $conn->prepare($requests_query);
$requests_stmt->execute($params);
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$cycles_stmt = $conn->query("SELECT id, cycle_name, cycle_year FROM performance_cycles ORDER BY cycle_year DESC, cycle_name");
$cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);

$employees_stmt = $conn->query("SELECT id, first_name, last_name, employee_id, department FROM employees WHERE status = 'active' ORDER BY first_name, last_name");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_requests,
        SUM(CASE WHEN deadline < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as overdue_requests,
        ROUND(AVG(
            (SELECT COUNT(*) FROM feedback_360_providers WHERE request_id = fr.id AND status = 'completed') * 100.0 / 
            NULLIF((SELECT COUNT(*) FROM feedback_360_providers WHERE request_id = fr.id), 0)
        ), 2) as avg_completion_rate
    FROM feedback_360_requests fr
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
    <title>360° Feedback System - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">360° Feedback System</h1>
                        <p class="text-gray-600">Comprehensive multi-source feedback collection and analysis</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="create_360_request.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>New 360° Request
                        </a>
                        <a href="my_360_feedback.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-user-check mr-2"></i>My Feedback
                        </a>
                        <a href="feedback_templates.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-file-alt mr-2"></i>Templates
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-3 rounded-full mr-4">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Requests</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_requests']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['completed_requests']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-3 rounded-full mr-4">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Active</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['active_requests']; ?></p>
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
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['overdue_requests']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-3 rounded-full mr-4">
                            <i class="fas fa-percentage text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg. Completion</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['avg_completion_rate'] ?? 0; ?>%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search employees or request titles..."
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Performance Cycle</label>
                            <select name="cycle" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Cycles</option>
                                <?php foreach ($cycles as $cycle): ?>
                                <option value="<?php echo $cycle['id']; ?>" <?php echo $cycle_filter == $cycle['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cycle['cycle_name'] . ' (' . $cycle['cycle_year'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
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
                                <a href="feedback_360.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 360° Feedback Requests List -->
            <div class="space-y-4">
                <?php if (empty($requests)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <i class="fas fa-users text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No 360° Feedback Requests</h3>
                    <p class="text-gray-500">No feedback requests match your current filters.</p>
                    <a href="create_360_request.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create First Request
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($requests as $request): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center mb-3">
                                <h3 class="text-lg font-semibold text-gray-900 mr-3">
                                    <?php echo htmlspecialchars($request['title']); ?>
                                </h3>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $request['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                               ($request['status'] == 'active' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>

                                <?php if ($request['urgency_status'] == 'overdue'): ?>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </span>
                                <?php elseif ($request['urgency_status'] == 'due_soon'): ?>
                                    <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full">
                                        <i class="fas fa-clock mr-1"></i>Due Soon
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <span class="text-sm text-gray-500">Employee</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($request['employee_first_name'] . ' ' . $request['employee_last_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($request['employee_number']); ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Cycle</span>
                                    <p class="font-medium"><?php echo htmlspecialchars($request['cycle_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $request['cycle_year']; ?></p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Deadline</span>
                                    <p class="font-medium <?php echo $request['urgency_status'] == 'overdue' ? 'text-red-600' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($request['deadline'])); ?>
                                    </p>
                                    <?php if ($request['days_remaining'] > 0): ?>
                                        <p class="text-sm text-gray-600"><?php echo $request['days_remaining']; ?> days left</p>
                                    <?php elseif ($request['days_remaining'] < 0): ?>
                                        <p class="text-sm text-red-600"><?php echo abs($request['days_remaining']); ?> days overdue</p>
                                    <?php else: ?>
                                        <p class="text-sm text-yellow-600">Due today</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">Progress</span>
                                    <p class="font-medium"><?php echo $request['completed_providers']; ?>/<?php echo $request['total_providers']; ?> completed</p>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <?php $completion_rate = $request['total_providers'] > 0 ? ($request['completed_providers'] / $request['total_providers']) * 100 : 0; ?>
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $completion_rate; ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($request['description']): ?>
                            <div class="mb-3">
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($request['description']); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="text-sm text-gray-600">
                                <i class="fas fa-user mr-1"></i>
                                Requested by: <?php echo htmlspecialchars($request['requested_by_first_name'] . ' ' . $request['requested_by_last_name']); ?>
                                <span class="ml-4">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Created: <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="ml-6 flex flex-col space-y-2">
                            <a href="view_360_feedback.php?id=<?php echo $request['id']; ?>" 
                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                <i class="fas fa-eye mr-1"></i>View Details
                            </a>
                            
                            <?php if ($request['status'] == 'active'): ?>
                            <a href="manage_360_providers.php?id=<?php echo $request['id']; ?>" 
                               class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200 text-center">
                                <i class="fas fa-users mr-1"></i>Manage Providers
                            </a>
                            
                            <button onclick="sendReminderToAll(<?php echo $request['id']; ?>)" 
                                    class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-bell mr-1"></i>Send Reminders
                            </button>
                            <?php endif; ?>

                            <button onclick="updateRequestStatus(<?php echo $request['id']; ?>)" 
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
                    <h3 class="text-lg font-medium text-gray-900">Update Request Status</h3>
                    <button onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="statusForm" class="space-y-4">
                    <input type="hidden" id="statusRequestId" name="request_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select id="newStatus" name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="active">Active</option>
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

    <script>
        function updateRequestStatus(requestId) {
            document.getElementById('statusRequestId').value = requestId;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        function sendReminderToAll(requestId) {
            if (confirm('Send reminders to all pending feedback providers?')) {
                const formData = new FormData();
                formData.append('action', 'send_reminder');
                formData.append('request_id', requestId);
                formData.append('message', 'Please complete your 360° feedback submission by the deadline.');

                fetch('feedback_360.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Reminders sent successfully');
                    } else {
                        alert('Error sending reminders');
                    }
                });
            }
        }

        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_request_status');

            fetch('feedback_360.php', {
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