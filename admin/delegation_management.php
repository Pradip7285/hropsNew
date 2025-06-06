<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Handle delegation actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create_delegation') {
        $delegator_id = $_POST['delegator_id'];
        $delegate_id = $_POST['delegate_id'];
        $delegation_scope = $_POST['delegation_scope'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        $reason = trim($_POST['reason']);
        
        try {
            $sql = "
                INSERT INTO approval_delegations 
                (delegator_id, delegate_id, delegation_scope, start_date, end_date, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$delegator_id, $delegate_id, $delegation_scope, $start_date, $end_date, $reason]);
            
            logActivity($_SESSION['user_id'], 'delegation_created', 'approval_delegations', $conn->lastInsertId(),
                "Created delegation from user $delegator_id to $delegate_id");
            
            $success = 'Approval delegation created successfully.';
        } catch (Exception $e) {
            $error = 'Error creating delegation: ' . $e->getMessage();
        }
    }
}

// Get all delegations
$delegations = $conn->query("
    SELECT ad.*, 
           u1.first_name as delegator_first, u1.last_name as delegator_last, u1.role as delegator_role,
           u2.first_name as delegate_first, u2.last_name as delegate_last, u2.role as delegate_role
    FROM approval_delegations ad
    JOIN users u1 ON ad.delegator_id = u1.id
    JOIN users u2 ON ad.delegate_id = u2.id
    ORDER BY ad.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get users for dropdown
$users = $conn->query("
    SELECT id, first_name, last_name, role, department
    FROM users 
    WHERE role IN ('hiring_manager', 'department_head', 'director', 'admin', 'hr_director')
    AND is_active = TRUE
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delegation Management - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Approval Delegation Management</h1>
                        <p class="text-gray-600">Manage backup approvers and temporary delegation of approval authority</p>
                    </div>
                    <button onclick="openCreateDelegationModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create Delegation
                    </button>
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

            <!-- Delegations List -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">Approval Delegations</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($delegations)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-exchange-alt text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-500">No delegations found.</p>
                        <button onclick="openCreateDelegationModal()" class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            Create First Delegation
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delegator</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Delegate</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scope</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($delegations as $delegation): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($delegation['delegator_first'] . ' ' . $delegation['delegator_last']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($delegation['delegator_role']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($delegation['delegate_first'] . ' ' . $delegation['delegate_last']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($delegation['delegate_role']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo ucfirst(str_replace('_', ' ', $delegation['delegation_scope'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M j, Y', strtotime($delegation['start_date'])); ?>
                                        </div>
                                        <?php if ($delegation['end_date']): ?>
                                        <div class="text-sm text-gray-500">
                                            to <?php echo date('M j, Y', strtotime($delegation['end_date'])); ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-sm text-gray-500">Ongoing</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($delegation['is_active']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Active
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Inactive
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Delegation Modal -->
    <div id="createDelegationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Create Approval Delegation</h3>
                    <button onclick="closeCreateDelegationModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="create_delegation">
                    
                    <div class="mb-4">
                        <label for="delegator_id" class="block text-sm font-medium text-gray-700 mb-2">Delegator</label>
                        <select name="delegator_id" id="delegator_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select Delegator</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="delegate_id" class="block text-sm font-medium text-gray-700 mb-2">Delegate To</label>
                        <select name="delegate_id" id="delegate_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select Delegate</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="delegation_scope" class="block text-sm font-medium text-gray-700 mb-2">Delegation Scope</label>
                        <select name="delegation_scope" id="delegation_scope" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="all">All Approvals</option>
                            <option value="department">Department Specific</option>
                            <option value="salary_range">Salary Range</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date (Optional)</label>
                        <input type="date" name="end_date" id="end_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">Reason for Delegation</label>
                        <textarea name="reason" id="reason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Explain the reason for this delegation..." required></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="closeCreateDelegationModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                            Create Delegation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateDelegationModal() {
            document.getElementById('createDelegationModal').classList.remove('hidden');
            document.getElementById('start_date').value = new Date().toISOString().split('T')[0];
        }
        
        function closeCreateDelegationModal() {
            document.getElementById('createDelegationModal').classList.add('hidden');
            document.querySelector('#createDelegationModal form').reset();
        }
    </script>
</body>
</html>
