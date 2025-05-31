<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hiring_manager');

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $offer_id = $_POST['offer_id'] ?? '';
    
    if ($action == 'approve' && $offer_id) {
        try {
            $stmt = $conn->prepare("
                UPDATE offers 
                SET approval_status = 'approved', approved_by = ?, approved_at = NOW(), status = 'sent'
                WHERE id = ? AND approval_status = 'pending'
            ");
            $stmt->execute([$_SESSION['user_id'], $offer_id]);
            
            // Get offer details for logging
            $offer_details = $conn->prepare("
                SELECT c.first_name, c.last_name, j.title 
                FROM offers o
                JOIN candidates c ON o.candidate_id = c.id
                JOIN job_postings j ON o.job_id = j.id
                WHERE o.id = ?
            ");
            $offer_details->execute([$offer_id]);
            $details = $offer_details->fetch();
            
            logActivity($_SESSION['user_id'], 'offer_approved', 'offer', $offer_id, 
                "Approved offer for {$details['first_name']} {$details['last_name']} - {$details['title']}");
            
            $success = 'Offer approved and sent to candidate successfully.';
        } catch (Exception $e) {
            $error = 'Error approving offer: ' . $e->getMessage();
        }
    } elseif ($action == 'reject' && $offer_id) {
        $rejection_reason = trim($_POST['rejection_reason']);
        
        if (empty($rejection_reason)) {
            $error = 'Please provide a reason for rejection.';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE offers 
                    SET approval_status = 'rejected', approved_by = ?, approved_at = NOW(), 
                        rejection_reason = ?
                    WHERE id = ? AND approval_status = 'pending'
                ");
                $stmt->execute([$_SESSION['user_id'], $rejection_reason, $offer_id]);
                
                // Get offer details for logging
                $offer_details = $conn->prepare("
                    SELECT c.first_name, c.last_name, j.title 
                    FROM offers o
                    JOIN candidates c ON o.candidate_id = c.id
                    JOIN job_postings j ON o.job_id = j.id
                    WHERE o.id = ?
                ");
                $offer_details->execute([$offer_id]);
                $details = $offer_details->fetch();
                
                logActivity($_SESSION['user_id'], 'offer_rejected', 'offer', $offer_id, 
                    "Rejected offer for {$details['first_name']} {$details['last_name']} - {$details['title']}");
                
                $success = 'Offer rejected. The requester has been notified.';
            } catch (Exception $e) {
                $error = 'Error rejecting offer: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'bulk_approve') {
        $offer_ids = $_POST['offer_ids'] ?? [];
        
        if (empty($offer_ids)) {
            $error = 'Please select offers to approve.';
        } else {
            try {
                $placeholders = str_repeat('?,', count($offer_ids) - 1) . '?';
                $params = array_merge([$_SESSION['user_id']], $offer_ids, ['pending']);
                
                $stmt = $conn->prepare("
                    UPDATE offers 
                    SET approval_status = 'approved', approved_by = ?, approved_at = NOW(), status = 'sent'
                    WHERE id IN ($placeholders) AND approval_status = ?
                ");
                $stmt->execute($params);
                
                $affected_rows = $stmt->rowCount();
                logActivity($_SESSION['user_id'], 'bulk_offer_approval', 'offer', 0, 
                    "Bulk approved $affected_rows offers");
                
                $success = "$affected_rows offers approved successfully.";
            } catch (Exception $e) {
                $error = 'Error with bulk approval: ' . $e->getMessage();
            }
        }
    }
}

// Get pending offers for approval
$pending_offers_stmt = $conn->query("
    SELECT o.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
           j.title as job_title, j.department,
           creator.first_name as creator_first, creator.last_name as creator_last,
           DATEDIFF(NOW(), o.created_at) as days_pending
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    JOIN users creator ON o.created_by = creator.id
    WHERE o.approval_status = 'pending'
    ORDER BY o.created_at ASC
");
$pending_offers = $pending_offers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved/rejected offers (recent)
$recent_decisions_stmt = $conn->query("
    SELECT o.*, 
           c.first_name as candidate_first, c.last_name as candidate_last,
           j.title as job_title,
           creator.first_name as creator_first, creator.last_name as creator_last,
           approver.first_name as approver_first, approver.last_name as approver_last
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    JOIN users creator ON o.created_by = creator.id
    LEFT JOIN users approver ON o.approved_by = approver.id
    WHERE o.approval_status IN ('approved', 'rejected')
    ORDER BY o.approved_at DESC
    LIMIT 20
");
$recent_decisions = $recent_decisions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approval statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_offers,
        SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        AVG(CASE WHEN approval_status != 'pending' THEN DATEDIFF(approved_at, created_at) END) as avg_approval_time
    FROM offers 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Approvals - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Offer Approvals</h1>
                        <p class="text-gray-600">Review and approve pending offer letters</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-list mr-2"></i>All Offers
                        </a>
                        <a href="templates.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-file-alt mr-2"></i>Templates
                        </a>
                    </div>
                </div>
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
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['pending_count']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-check text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Approved</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['approved_count']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-times text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Rejected</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['rejected_count']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-file-alt text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total (30 days)</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_offers']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-2 rounded-full mr-3">
                            <i class="fas fa-stopwatch text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Avg. Time</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo $stats['avg_approval_time'] ? round($stats['avg_approval_time'], 1) . 'd' : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-clock text-yellow-500 mr-2"></i>
                            Pending Approvals (<?php echo count($pending_offers); ?>)
                        </h2>
                        <?php if (!empty($pending_offers)): ?>
                        <div class="flex space-x-2">
                            <button onclick="selectAll()" class="text-sm text-blue-600 hover:text-blue-800">
                                Select All
                            </button>
                            <button onclick="bulkApprove()" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-check mr-1"></i>Bulk Approve
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($pending_offers)): ?>
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-check-circle text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold mb-2">All caught up!</h3>
                    <p>No offers pending approval at this time.</p>
                </div>
                <?php else: ?>
                <form method="POST" id="bulkApprovalForm">
                    <input type="hidden" name="action" value="bulk_approve">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidate</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salary</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Pending</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pending_offers as $offer): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="offer_ids[]" value="<?php echo $offer['id']; ?>" class="offer-checkbox">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="bg-blue-500 text-white w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold">
                                                <?php echo strtoupper(substr($offer['candidate_first'], 0, 1) . substr($offer['candidate_last'], 0, 1)); ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($offer['candidate_first'] . ' ' . $offer['candidate_last']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($offer['candidate_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($offer['job_title']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($offer['department']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            $<?php echo number_format($offer['salary_offered'], 0); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($offer['creator_first'] . ' ' . $offer['creator_last']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($offer['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="<?php echo $offer['days_pending'] > 3 ? 'bg-red-100 text-red-800' : ($offer['days_pending'] > 1 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?> px-2 py-1 text-xs font-semibold rounded-full">
                                            <?php echo $offer['days_pending']; ?> day<?php echo $offer['days_pending'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="previewOffer(<?php echo $offer['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-900" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="approveOffer(<?php echo $offer['id']; ?>)" 
                                                    class="text-green-600 hover:text-green-900" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="rejectOffer(<?php echo $offer['id']; ?>)" 
                                                    class="text-red-600 hover:text-red-900" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <!-- Recent Decisions -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-history text-gray-500 mr-2"></i>
                        Recent Decisions
                    </h2>
                </div>

                <?php if (empty($recent_decisions)): ?>
                <div class="p-8 text-center text-gray-500">
                    <p>No recent approval decisions.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decision</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_decisions as $decision): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($decision['candidate_first'] . ' ' . $decision['candidate_last']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($decision['job_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($decision['approval_status'] == 'approved'): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 text-xs font-semibold rounded-full">
                                        <i class="fas fa-check mr-1"></i>Approved
                                    </span>
                                    <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-2 py-1 text-xs font-semibold rounded-full">
                                        <i class="fas fa-times mr-1"></i>Rejected
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($decision['approver_first'] . ' ' . $decision['approver_last']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"><?php echo date('M j, Y', strtotime($decision['approved_at'])); ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Reject Offer</h3>
                    <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="offer_id" id="rejectOfferId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection *</label>
                        <textarea name="rejection_reason" id="rejectionReason" rows="4" required
                                  placeholder="Please provide a detailed reason for rejecting this offer..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRejectModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times mr-2"></i>Reject Offer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function approveOffer(offerId) {
            if (confirm('Are you sure you want to approve this offer? It will be sent to the candidate immediately.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="offer_id" value="${offerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectOffer(offerId) {
            document.getElementById('rejectOfferId').value = offerId;
            document.getElementById('rejectModal').classList.remove('hidden');
            document.getElementById('rejectionReason').focus();
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
            document.getElementById('rejectionReason').value = '';
        }

        function previewOffer(offerId) {
            window.open(`view.php?id=${offerId}`, '_blank');
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.offer-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = true);
            document.getElementById('selectAllCheckbox').checked = true;
        }

        function toggleAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.offer-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
        }

        function bulkApprove() {
            const checked = document.querySelectorAll('.offer-checkbox:checked');
            if (checked.length === 0) {
                alert('Please select offers to approve.');
                return;
            }
            
            if (confirm(`Are you sure you want to approve ${checked.length} offer(s)? They will be sent to candidates immediately.`)) {
                document.getElementById('bulkApprovalForm').submit();
            }
        }
    </script>
</body>
</html> 