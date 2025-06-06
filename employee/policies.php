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

// Handle policy acknowledgment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'acknowledge_policy') {
        $policy_id = $_POST['policy_id'];
        try {
            $stmt = $conn->prepare("INSERT INTO policy_acknowledgments (employee_id, policy_id, acknowledged_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE acknowledged_at = NOW()");
            $stmt->execute([$employee['id'], $policy_id]);
            $success_message = "Policy acknowledged successfully!";
        } catch (Exception $e) {
            $error_message = "Error acknowledging policy: " . $e->getMessage();
        }
    }
}

// Sample policies data (would normally come from database)
$policies = [
    [
        'id' => 1,
        'title' => 'Employee Code of Conduct',
        'category' => 'Ethics',
        'description' => 'Guidelines for professional behavior and ethical standards',
        'content' => 'Our company is committed to maintaining the highest standards of professional conduct. All employees are expected to act with integrity, honesty, and respect in all business dealings...',
        'mandatory' => true,
        'effective_date' => '2024-01-01',
        'last_updated' => '2024-01-01',
        'acknowledged' => true
    ],
    [
        'id' => 2,
        'title' => 'Information Security Policy',
        'category' => 'Security',
        'description' => 'Procedures for handling confidential information and cybersecurity',
        'content' => 'This policy establishes guidelines for protecting company and customer data. All employees must follow these security protocols to ensure data protection...',
        'mandatory' => true,
        'effective_date' => '2024-01-01',
        'last_updated' => '2024-01-15',
        'acknowledged' => false
    ],
    [
        'id' => 3,
        'title' => 'Remote Work Policy',
        'category' => 'Work Arrangements',
        'description' => 'Guidelines for working remotely and hybrid work arrangements',
        'content' => 'This policy outlines the expectations and requirements for employees working remotely. It covers equipment, communication, and productivity standards...',
        'mandatory' => true,
        'effective_date' => '2024-01-01',
        'last_updated' => '2024-01-01',
        'acknowledged' => false
    ],
    [
        'id' => 4,
        'title' => 'Anti-Harassment Policy',
        'category' => 'HR',
        'description' => 'Zero tolerance policy for harassment and discrimination',
        'content' => 'Our company maintains a zero-tolerance policy for harassment of any kind. This policy applies to all employees and outlines reporting procedures...',
        'mandatory' => true,
        'effective_date' => '2024-01-01',
        'last_updated' => '2024-01-01',
        'acknowledged' => true
    ],
    [
        'id' => 5,
        'title' => 'Social Media Guidelines',
        'category' => 'Communications',
        'description' => 'Guidelines for professional social media use',
        'content' => 'These guidelines help employees understand appropriate social media use that protects both personal and company interests...',
        'mandatory' => false,
        'effective_date' => '2024-01-01',
        'last_updated' => '2024-01-01',
        'acknowledged' => false
    ],
    [
        'id' => 6,
        'title' => 'Professional Development Policy',
        'category' => 'Learning',
        'description' => 'Company support for employee growth and development',
        'content' => 'We encourage continuous learning and professional development. This policy outlines available resources and reimbursement procedures...',
        'mandatory' => false,
        'effective_date' => '2024-01-01',
        'last_updated' => '2024-01-01',
        'acknowledged' => false
    ]
];

// Group policies
$mandatory_policies = array_filter($policies, function($p) { return $p['mandatory']; });
$optional_policies = array_filter($policies, function($p) { return !$p['mandatory']; });

// Calculate acknowledgment stats
$total_mandatory = count($mandatory_policies);
$acknowledged_mandatory = count(array_filter($mandatory_policies, function($p) { return $p['acknowledged']; }));
$acknowledgment_percentage = $total_mandatory > 0 ? round(($acknowledged_mandatory / $total_mandatory) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Policies - Employee Portal</title>
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
                    <h1 class="text-2xl font-bold text-gray-900">Company Policies</h1>
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

        <?php if (isset($error_message)): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Acknowledgment Progress -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Policy Acknowledgment Progress</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $acknowledged_mandatory; ?>/<?php echo $total_mandatory; ?></div>
                    <p class="text-gray-600">Mandatory Policies Acknowledged</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600"><?php echo $acknowledgment_percentage; ?>%</div>
                    <p class="text-gray-600">Compliance Rate</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-purple-600"><?php echo count($policies); ?></div>
                    <p class="text-gray-600">Total Policies</p>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Mandatory Policy Acknowledgment</span>
                    <span><?php echo $acknowledgment_percentage; ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: <?php echo $acknowledgment_percentage; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Mandatory Policies -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                Mandatory Policies
            </h2>
            <div class="space-y-4">
                <?php foreach ($mandatory_policies as $policy): ?>
                <div class="bg-white rounded-lg shadow-md border-l-4 <?php echo $policy['acknowledged'] ? 'border-green-500' : 'border-red-500'; ?>">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($policy['title']); ?></h3>
                                <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($policy['description']); ?></p>
                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                    <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($policy['category']); ?></span>
                                    <span><i class="fas fa-calendar mr-1"></i>Effective: <?php echo date('M j, Y', strtotime($policy['effective_date'])); ?></span>
                                    <span><i class="fas fa-edit mr-1"></i>Updated: <?php echo date('M j, Y', strtotime($policy['last_updated'])); ?></span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <?php if ($policy['acknowledged']): ?>
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-check mr-1"></i>Acknowledged
                                    </span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-exclamation mr-1"></i>Required
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button onclick="openPolicyModal(<?php echo $policy['id']; ?>, '<?php echo htmlspecialchars($policy['title']); ?>', '<?php echo htmlspecialchars($policy['content']); ?>', <?php echo $policy['acknowledged'] ? 'true' : 'false'; ?>)" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                <i class="fas fa-eye mr-2"></i>Read Policy
                            </button>
                            <?php if (!$policy['acknowledged']): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="acknowledge_policy">
                                <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-200"
                                        onclick="return confirm('I confirm that I have read and understood this policy.')">
                                    <i class="fas fa-check mr-2"></i>Acknowledge
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Optional Policies -->
        <div>
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Additional Policies (Optional Reading)
            </h2>
            <div class="space-y-4">
                <?php foreach ($optional_policies as $policy): ?>
                <div class="bg-white rounded-lg shadow-md border-l-4 <?php echo $policy['acknowledged'] ? 'border-green-500' : 'border-gray-300'; ?>">
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($policy['title']); ?></h3>
                                <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($policy['description']); ?></p>
                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                    <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($policy['category']); ?></span>
                                    <span><i class="fas fa-calendar mr-1"></i>Effective: <?php echo date('M j, Y', strtotime($policy['effective_date'])); ?></span>
                                </div>
                            </div>
                            <div class="ml-4">
                                <?php if ($policy['acknowledged']): ?>
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-check mr-1"></i>Read
                                    </span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                                        Optional
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button onclick="openPolicyModal(<?php echo $policy['id']; ?>, '<?php echo htmlspecialchars($policy['title']); ?>', '<?php echo htmlspecialchars($policy['content']); ?>', <?php echo $policy['acknowledged'] ? 'true' : 'false'; ?>)" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                <i class="fas fa-eye mr-2"></i>Read Policy
                            </button>
                            <?php if (!$policy['acknowledged']): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="acknowledge_policy">
                                <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-200">
                                    <i class="fas fa-check mr-2"></i>Mark as Read
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Policy Guidelines -->
        <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-400 p-6 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-yellow-400 text-xl"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-medium text-yellow-800">Important Information</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>All mandatory policies must be acknowledged within 30 days of employment</li>
                            <li>Policies are updated periodically - check back regularly for changes</li>
                            <li>If you have questions about any policy, contact your manager or HR</li>
                            <li>Acknowledgment indicates you have read, understood, and agree to comply with the policy</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Policy Modal -->
    <div id="policyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-xl font-medium text-gray-900" id="modalTitle">Policy Title</h3>
                        <button onclick="closePolicyModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div id="modalContent" class="prose max-w-none">
                        <!-- Policy content will be loaded here -->
                    </div>
                </div>
                <div class="p-6 border-t border-gray-200" id="modalActions">
                    <!-- Action buttons will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function openPolicyModal(policyId, title, content, acknowledged) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalContent').innerHTML = '<p class="text-gray-700 leading-relaxed">' + content + '</p>';
            
            let actionsHtml = '<button onclick="closePolicyModal()" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 mr-3">Close</button>';
            
            if (!acknowledged) {
                actionsHtml += '<form method="POST" class="inline">' +
                              '<input type="hidden" name="action" value="acknowledge_policy">' +
                              '<input type="hidden" name="policy_id" value="' + policyId + '">' +
                              '<button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700" ' +
                              'onclick="return confirm(\'I confirm that I have read and understood this policy.\');">' +
                              '<i class="fas fa-check mr-2"></i>Acknowledge Policy</button></form>';
            }
            
            document.getElementById('modalActions').innerHTML = actionsHtml;
            document.getElementById('policyModal').classList.remove('hidden');
        }

        function closePolicyModal() {
            document.getElementById('policyModal').classList.add('hidden');
        }
    </script>
</body>
</html> 