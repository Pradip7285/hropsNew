<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$offer_id = $_GET['id'] ?? null;

if (!$offer_id) {
    header('Location: list.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get offer details
$stmt = $conn->prepare("
    SELECT o.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, 
           c.email as candidate_email, c.phone as candidate_phone,
           j.title as job_title, j.department, j.description as job_description,
           creator.first_name as creator_first, creator.last_name as creator_last,
           approver.first_name as approver_first, approver.last_name as approver_last,
           ot.name as template_name, ot.content as template_content,
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
    LEFT JOIN offer_templates ot ON o.template_id = ot.id
    WHERE o.id = ?
");
$stmt->execute([$offer_id]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    header('Location: list.php');
    exit;
}

// Generate offer letter content with variables replaced
$offer_content = $offer['template_content'] ?? '';
if ($offer_content) {
    $variables = [
        '{candidate_name}' => $offer['candidate_first'] . ' ' . $offer['candidate_last'],
        '{job_title}' => $offer['job_title'],
        '{department}' => $offer['department'],
        '{salary}' => '$' . number_format($offer['salary_offered'], 2),
        '{start_date}' => $offer['start_date'] ? date('F j, Y', strtotime($offer['start_date'])) : 'To be determined',
        '{benefits}' => $offer['benefits'] ?? 'Standard benefits package',
        '{offer_deadline}' => $offer['valid_until'] ? date('F j, Y', strtotime($offer['valid_until'])) : 'N/A',
        '{company_name}' => APP_NAME
    ];
    
    $offer_content = str_replace(array_keys($variables), array_values($variables), $offer_content);
}

// Get offer activity log
$activity_stmt = $conn->prepare("
    SELECT al.*, u.first_name, u.last_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    WHERE al.entity_type = 'offer' AND al.entity_id = ?
    ORDER BY al.created_at DESC
");
$activity_stmt->execute([$offer_id]);
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Offer - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Offer Details</h1>
                        <p class="text-gray-600">
                            <?php echo htmlspecialchars($offer['candidate_first'] . ' ' . $offer['candidate_last']); ?> - 
                            <?php echo htmlspecialchars($offer['job_title']); ?>
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        <?php if ($offer['approval_status'] == 'pending' && hasPermission('hiring_manager')): ?>
                        <a href="approvals.php" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-clock mr-2"></i>Approve
                        </a>
                        <?php endif; ?>
                        <?php if ($offer['status'] == 'draft'): ?>
                        <a href="edit.php?id=<?php echo $offer['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>Edit
                        </a>
                        <?php endif; ?>
                        <button onclick="printOffer()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Offer Details -->
                <div class="lg:col-span-2">
                    <!-- Status Card -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-800">Offer Status</h2>
                            <div class="flex space-x-2">
                                <?php
                                $status_colors = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'sent' => 'bg-blue-100 text-blue-800',
                                    'accepted' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                    'expired' => 'bg-red-100 text-red-800'
                                ];
                                $approval_colors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800'
                                ];
                                ?>
                                <span class="<?php echo $status_colors[$offer['display_status']] ?? 'bg-gray-100 text-gray-800'; ?> px-3 py-1 text-sm font-semibold rounded-full">
                                    <?php echo ucfirst($offer['display_status']); ?>
                                </span>
                                <span class="<?php echo $approval_colors[$offer['approval_status']] ?? 'bg-gray-100 text-gray-800'; ?> px-3 py-1 text-sm font-semibold rounded-full">
                                    <?php echo ucfirst($offer['approval_status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Created</p>
                                <p class="font-medium"><?php echo date('M j, Y', strtotime($offer['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Valid Until</p>
                                <p class="font-medium <?php echo $offer['days_remaining'] < 0 ? 'text-red-600' : ($offer['days_remaining'] < 3 ? 'text-yellow-600' : ''); ?>">
                                    <?php echo $offer['valid_until'] ? date('M j, Y', strtotime($offer['valid_until'])) : 'N/A'; ?>
                                    <?php if ($offer['valid_until'] && $offer['days_remaining'] >= 0): ?>
                                    <span class="text-xs">(<?php echo $offer['days_remaining']; ?> days)</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Created By</p>
                                <p class="font-medium"><?php echo htmlspecialchars($offer['creator_first'] . ' ' . $offer['creator_last']); ?></p>
                            </div>
                            <?php if ($offer['approved_by']): ?>
                            <div>
                                <p class="text-sm text-gray-600">Approved By</p>
                                <p class="font-medium"><?php echo htmlspecialchars($offer['approver_first'] . ' ' . $offer['approver_last']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($offer['rejection_reason']): ?>
                        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <h4 class="text-sm font-medium text-red-800 mb-2">Rejection Reason:</h4>
                            <p class="text-sm text-red-700"><?php echo htmlspecialchars($offer['rejection_reason']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Offer Letter Content -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-gray-800">Offer Letter</h2>
                            <?php if ($offer['template_name']): ?>
                            <span class="text-sm text-gray-500">Template: <?php echo htmlspecialchars($offer['template_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div id="offerContent" class="prose max-w-none">
                            <?php if ($offer_content): ?>
                                <?php echo $offer_content; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-file-alt text-4xl mb-4"></i>
                                    <p>No offer letter content available.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Candidate Info -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Candidate Information</h3>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <div class="bg-blue-500 text-white w-12 h-12 rounded-full flex items-center justify-center text-lg font-semibold mr-4">
                                    <?php echo strtoupper(substr($offer['candidate_first'], 0, 1) . substr($offer['candidate_last'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($offer['candidate_first'] . ' ' . $offer['candidate_last']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($offer['candidate_email']); ?></p>
                                </div>
                            </div>
                            <?php if ($offer['candidate_phone']): ?>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-phone mr-2"></i>
                                <?php echo htmlspecialchars($offer['candidate_phone']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="pt-2">
                                <a href="../candidates/view.php?id=<?php echo $offer['candidate_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-user mr-1"></i>View Full Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Job Details -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Position Details</h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-gray-600">Position</p>
                                <p class="font-medium"><?php echo htmlspecialchars($offer['job_title']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Department</p>
                                <p class="font-medium"><?php echo htmlspecialchars($offer['department']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Salary Offered</p>
                                <p class="font-medium text-green-600 text-lg">
                                    $<?php echo number_format($offer['salary_offered'], 2); ?>
                                </p>
                            </div>
                            <?php if ($offer['start_date']): ?>
                            <div>
                                <p class="text-sm text-gray-600">Start Date</p>
                                <p class="font-medium"><?php echo date('F j, Y', strtotime($offer['start_date'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="pt-2">
                                <a href="../jobs/view.php?id=<?php echo $offer['job_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-briefcase mr-1"></i>View Job Details
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Benefits -->
                    <?php if ($offer['benefits']): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Benefits Package</h3>
                        <div class="text-sm text-gray-700">
                            <?php echo nl2br(htmlspecialchars($offer['benefits'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Activity Log -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Activity Log</h3>
                        <div class="space-y-3">
                            <?php if (empty($activities)): ?>
                            <p class="text-sm text-gray-500">No activity recorded.</p>
                            <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                            <div class="flex items-start space-x-3">
                                <div class="bg-blue-100 p-1 rounded-full">
                                    <i class="fas fa-circle text-blue-600 text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?> • 
                                        <?php echo timeAgo($activity['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function printOffer() {
            const content = document.getElementById('offerContent').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Offer Letter</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 40px; }
                        h1 { color: #333; }
                        .prose { max-width: none; }
                        @media print {
                            body { margin: 20px; }
                        }
                    </style>
                </head>
                <body>
                    ${content}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html> 