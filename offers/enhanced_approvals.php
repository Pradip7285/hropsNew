<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/approval_engine.php';

requireRole('hiring_manager');

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();
$approval_engine = new ApprovalEngine($conn);

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'process_approval') {
        $step_id = $_POST['step_id'];
        $decision = $_POST['decision'];
        $comments = trim($_POST['comments']);
        
        try {
            $approval_engine->processApproval($step_id, $decision, $comments);
            $success = 'Approval decision processed successfully.';
        } catch (Exception $e) {
            $error = 'Error processing approval: ' . $e->getMessage();
        }
    }
}

// Get pending approvals for current user
$pending_approvals = $approval_engine->getPendingApprovals($_SESSION['user_id']);

// Get approval analytics
$analytics = $approval_engine->getApprovalAnalytics();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Approvals - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Enhanced Approval Workflows</h1>
                <p class="text-gray-600">Multi-level approvals with delegation, SLA tracking, and committee voting</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <!-- Approval Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-clock text-blue-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Pending Approvals</h3>
                            <p class="text-3xl font-bold text-blue-600"><?php echo count($pending_approvals); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Total Processed</h3>
                            <p class="text-3xl font-bold text-green-600">
                                <?php 
                                $total = 0;
                                foreach ($analytics as $stat) {
                                    $total += $stat['total_approvals'];
                                }
                                echo $total;
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-percentage text-purple-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Approval Rate</h3>
                            <p class="text-3xl font-bold text-purple-600">
                                <?php 
                                $approved = 0;
                                $total = 0;
                                foreach ($analytics as $stat) {
                                    $approved += $stat['approved_count'];
                                    $total += $stat['total_approvals'];
                                }
                                echo $total > 0 ? round(($approved / $total) * 100, 1) . '%' : '0%';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Pending Approvals</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($pending_approvals)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">No pending approvals. You're all caught up!</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_approvals as $approval): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-200">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($approval['step_name']); ?></h3>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($approval['entity_description']); ?></p>
                                    <div class="flex items-center space-x-4 mt-2">
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-clock mr-1"></i>
                                            Due: <?php echo date('M j, Y g:i A', strtotime($approval['due_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="approveStep(<?php echo $approval['id']; ?>)" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm transition duration-200">
                                        <i class="fas fa-check mr-1"></i>Approve
                                    </button>
                                    <button onclick="rejectStep(<?php echo $approval['id']; ?>)" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm transition duration-200">
                                        <i class="fas fa-times mr-1"></i>Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approval Analytics -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Approval Analytics</h2>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entity Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Time (hrs)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($analytics as $stat): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo ucfirst($stat['entity_type']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $stat['total_approvals']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                                        <?php echo $stat['approved_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">
                                        <?php echo $stat['rejected_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo round($stat['avg_completion_hours'] ?? 0, 1); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900" id="approvalModalTitle">Process Approval</h3>
                </div>
                <form id="approvalForm" method="POST" class="p-6">
                    <input type="hidden" name="action" value="process_approval">
                    <input type="hidden" name="step_id" id="approvalStepId">
                    <input type="hidden" name="decision" id="approvalDecision">
                    
                    <div class="mb-4">
                        <label for="comments" class="block text-sm font-medium text-gray-700 mb-2">Comments</label>
                        <textarea name="comments" id="comments" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Add your comments..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeApprovalModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" id="approvalSubmitBtn" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function approveStep(stepId) {
            document.getElementById('approvalModalTitle').textContent = 'Approve Request';
            document.getElementById('approvalStepId').value = stepId;
            document.getElementById('approvalDecision').value = 'approved';
            document.getElementById('approvalSubmitBtn').className = 'px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700';
            document.getElementById('approvalSubmitBtn').innerHTML = '<i class="fas fa-check mr-1"></i>Approve';
            document.getElementById('approvalModal').classList.remove('hidden');
        }
        
        function rejectStep(stepId) {
            document.getElementById('approvalModalTitle').textContent = 'Reject Request';
            document.getElementById('approvalStepId').value = stepId;
            document.getElementById('approvalDecision').value = 'rejected';
            document.getElementById('approvalSubmitBtn').className = 'px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700';
            document.getElementById('approvalSubmitBtn').innerHTML = '<i class="fas fa-times mr-1"></i>Reject';
            document.getElementById('approvalModal').classList.remove('hidden');
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.getElementById('comments').value = '';
        }
    </script>
</body>
</html>
