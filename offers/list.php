<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$job_id = $_GET['job_id'] ?? '';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR j.title LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status;
}

if (!empty($job_id)) {
    $where_conditions[] = "o.job_id = ?";
    $params[] = $job_id;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$db = new Database();
$conn = $db->getConnection();

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get offers
$query = "
    SELECT o.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
           j.title as job_title, j.department,
           creator.first_name as creator_first, creator.last_name as creator_last,
           approver.first_name as approver_first, approver.last_name as approver_last,
           CASE 
               WHEN o.valid_until < CURDATE() AND o.status = 'sent' THEN 'expired'
               ELSE o.status 
           END as display_status,
           DATEDIFF(o.valid_until, CURDATE()) as days_remaining
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    JOIN users creator ON o.created_by = creator.id
    LEFT JOIN users approver ON o.approved_by = approver.id
    $where_clause
    ORDER BY o.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get jobs for filter
$jobs_stmt = $conn->query("SELECT id, title FROM job_postings WHERE status = 'active' ORDER BY title");
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offers - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Job Offers</h1>
                <p class="text-gray-600">Manage offer letters and approval workflows</p>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Candidate name or job title..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo $status == 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="accepted" <?php echo $status == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="expired" <?php echo $status == 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Job Position</label>
                        <select name="job_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Positions</option>
                            <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['id']; ?>" <?php echo $job_id == $job['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-file-alt text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Draft</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($offers, fn($o) => $o['status'] == 'draft')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-paper-plane text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Sent</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($offers, fn($o) => $o['status'] == 'sent')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Accepted</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($offers, fn($o) => $o['status'] == 'accepted')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Rejected</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($offers, fn($o) => $o['status'] == 'rejected')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-gray-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Expired</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($offers, fn($o) => $o['display_status'] == 'expired')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <a href="create.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create Offer
                    </a>
                    <a href="templates.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-file-alt mr-2"></i>Templates
                    </a>
                    <?php if (hasPermission('hiring_manager')): ?>
                    <a href="approvals.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-clipboard-check mr-2"></i>Approvals
                        <?php
                        // Get pending approval count
                        $pending_count_stmt = $conn->query("SELECT COUNT(*) as count FROM offers WHERE approval_status = 'pending'");
                        $pending_count = $pending_count_stmt->fetch()['count'];
                        if ($pending_count > 0):
                        ?>
                        <span class="bg-white text-purple-600 text-xs px-2 py-1 rounded-full ml-1"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="text-sm text-gray-600">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> offers
                </div>
            </div>

            <!-- Offers Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Offer Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Validity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($offers)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-file-contract text-4xl mb-4"></i>
                                <p>No offers found</p>
                                <a href="create.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-plus mr-2"></i>Create Your First Offer
                                </a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($offers as $offer): ?>
                        <tr class="hover:bg-gray-50">
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
                                <div class="text-sm text-gray-900">
                                    <strong>$<?php echo number_format($offer['salary_offered'], 0); ?></strong>
                                </div>
                                <?php if ($offer['start_date']): ?>
                                <div class="text-sm text-gray-500">
                                    Start: <?php echo date('M j, Y', strtotime($offer['start_date'])); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($offer['benefits']): ?>
                                <div class="text-xs text-gray-400">+ Benefits</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'draft' => 'bg-yellow-100 text-yellow-800',
                                    'sent' => 'bg-blue-100 text-blue-800',
                                    'accepted' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'expired' => 'bg-gray-100 text-gray-800'
                                ];
                                $color_class = $status_colors[$offer['display_status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $color_class; ?>">
                                    <?php if ($offer['display_status'] == 'expired'): ?>
                                        <i class="fas fa-clock mr-1"></i>Expired
                                    <?php else: ?>
                                        <?php echo ucfirst($offer['status']); ?>
                                    <?php endif; ?>
                                </span>
                                
                                <?php if ($offer['status'] == 'draft' && !$offer['approved_by']): ?>
                                <div class="mt-1">
                                    <span class="px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-full">
                                        Needs Approval
                                    </span>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($offer['valid_until']): ?>
                                <div><?php echo date('M j, Y', strtotime($offer['valid_until'])); ?></div>
                                <?php if ($offer['days_remaining'] !== null): ?>
                                    <?php if ($offer['days_remaining'] > 0): ?>
                                    <div class="text-xs text-green-600"><?php echo $offer['days_remaining']; ?> days left</div>
                                    <?php elseif ($offer['days_remaining'] == 0): ?>
                                    <div class="text-xs text-orange-600">Expires today</div>
                                    <?php else: ?>
                                    <div class="text-xs text-red-600">Expired</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="text-gray-400">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div><?php echo date('M j, Y', strtotime($offer['created_at'])); ?></div>
                                <div class="text-xs text-gray-400">
                                    by <?php echo htmlspecialchars($offer['creator_first'] . ' ' . $offer['creator_last']); ?>
                                </div>
                                <?php if ($offer['approved_by']): ?>
                                <div class="text-xs text-green-600">
                                    <i class="fas fa-check mr-1"></i>Approved by <?php echo htmlspecialchars($offer['approver_first'] . ' ' . $offer['approver_last']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="view.php?id=<?php echo $offer['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($offer['status'] == 'draft'): ?>
                                    <a href="edit.php?id=<?php echo $offer['id']; ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($offer['status'] == 'draft' && !$offer['approved_by'] && hasPermission('admin')): ?>
                                    <button onclick="approveOffer(<?php echo $offer['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="Approve">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($offer['status'] == 'draft' && $offer['approved_by']): ?>
                                    <button onclick="sendOffer(<?php echo $offer['id']; ?>)" class="text-indigo-600 hover:text-indigo-900" title="Send Offer">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($offer['offer_letter_path']): ?>
                                    <a href="<?php echo htmlspecialchars($offer['offer_letter_path']); ?>" target="_blank" class="text-orange-600 hover:text-orange-900" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button onclick="duplicateOffer(<?php echo $offer['id']; ?>)" class="text-gray-600 hover:text-gray-900" title="Duplicate">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&job_id=<?php echo $job_id; ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&job_id=<?php echo $job_id; ?>" 
                       class="px-3 py-2 border rounded-lg <?php echo $i == $page ? 'bg-blue-500 text-white border-blue-500' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&job_id=<?php echo $job_id; ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function approveOffer(offerId) {
            if (confirm('Approve this offer for sending?')) {
                window.location.href = 'approve.php?id=' + offerId;
            }
        }

        function sendOffer(offerId) {
            if (confirm('Send this offer to the candidate?')) {
                window.location.href = 'send.php?id=' + offerId;
            }
        }

        function duplicateOffer(offerId) {
            if (confirm('Create a copy of this offer?')) {
                window.location.href = 'duplicate.php?id=' + offerId;
            }
        }
    </script>
</body>
</html> 