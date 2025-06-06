<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Check if user has HR access
if (!in_array($_SESSION['role'], ['admin', 'hr_recruiter', 'hiring_manager'])) {
    header('Location: ../dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get organizational statistics
$org_stats = $conn->query("
    SELECT 
        COUNT(DISTINCT op.id) as total_positions,
        COUNT(DISTINCT epa.employee_id) as employees_with_positions,
        COUNT(DISTINCT CASE WHEN op.is_management_role = 1 THEN epa.employee_id END) as employees_in_management,
        COUNT(DISTINCT rt.id) as pending_transitions,
        COUNT(DISTINCT t.id) as active_teams
    FROM organizational_positions op
    LEFT JOIN employee_position_assignments epa ON op.id = epa.position_id AND epa.status = 'active'
    LEFT JOIN role_transitions rt ON rt.transition_status IN ('submitted', 'under_review')
    LEFT JOIN teams t ON t.status = 'active'
")->fetch(PDO::FETCH_ASSOC);

// Get current management hierarchy
$management_hierarchy = $conn->query("
    SELECT * FROM management_hierarchy 
    WHERE position_level <= 3 
    ORDER BY position_level, department_name, position_title
")->fetchAll(PDO::FETCH_ASSOC);

// Get pending role transitions
$pending_transitions = $conn->query("
    SELECT rt.*, 
           CONCAT(emp_u.first_name, ' ', emp_u.last_name) as employee_name,
           curr_pos.position_title as current_position,
           prop_pos.position_title as proposed_position,
           curr_dept.department_name as current_department,
           prop_dept.department_name as proposed_department
    FROM role_transitions rt
    JOIN employees emp ON rt.employee_id = emp.id
    JOIN users emp_u ON emp.user_id = emp_u.id
    JOIN organizational_positions curr_pos ON rt.current_position_id = curr_pos.id
    JOIN organizational_positions prop_pos ON rt.proposed_position_id = prop_pos.id
    JOIN departments curr_dept ON rt.current_department_id = curr_dept.id
    JOIN departments prop_dept ON rt.proposed_department_id = prop_dept.id
    WHERE rt.transition_status IN ('submitted', 'under_review', 'hr_approved')
    ORDER BY rt.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Organizational Management';
include '../includes/header.php';
?>

<div class="min-h-screen bg-gray-100 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    <i class="fas fa-sitemap mr-3 text-purple-600"></i>Organizational Management
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Enterprise-grade organizational structure, role transitions, and team management
                </p>
            </div>
            <div class="mt-4 flex space-x-3 md:mt-0 md:ml-4">
                <a href="../dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
                <button onclick="showTransitionModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700">
                    <i class="fas fa-exchange-alt mr-2"></i>Initiate Role Transition
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-layer-group text-2xl text-blue-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Positions</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $org_stats['total_positions'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-2xl text-green-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Assigned Employees</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $org_stats['employees_with_positions'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-tie text-2xl text-purple-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Managers</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $org_stats['employees_in_management'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exchange-alt text-2xl text-orange-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Transitions</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $org_stats['pending_transitions'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users-cog text-2xl text-indigo-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Active Teams</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $org_stats['active_teams'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="bg-white shadow rounded-lg">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex" aria-label="Tabs">
                    <button onclick="showTab('hierarchy')" id="tab-hierarchy" class="tab-button active w-1/4 py-2 px-1 text-center border-b-2 font-medium text-sm">
                        <i class="fas fa-sitemap mr-2"></i>Hierarchy
                    </button>
                    <button onclick="showTab('positions')" id="tab-positions" class="tab-button w-1/4 py-2 px-1 text-center border-b-2 font-medium text-sm">
                        <i class="fas fa-layer-group mr-2"></i>Positions
                    </button>
                    <button onclick="showTab('transitions')" id="tab-transitions" class="tab-button w-1/4 py-2 px-1 text-center border-b-2 font-medium text-sm">
                        <i class="fas fa-exchange-alt mr-2"></i>Transitions
                    </button>
                    <button onclick="showTab('teams')" id="tab-teams" class="tab-button w-1/4 py-2 px-1 text-center border-b-2 font-medium text-sm">
                        <i class="fas fa-users-cog mr-2"></i>Teams
                    </button>
                </nav>
            </div>

            <!-- Hierarchy Tab -->
            <div id="content-hierarchy" class="tab-content p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-6">Management Hierarchy</h3>
                
                <div class="space-y-6">
                    <?php 
                    $current_level = null;
                    $level_names = [1 => 'Executive Level', 2 => 'Director Level', 3 => 'Manager Level'];
                    
                    foreach ($management_hierarchy as $position): 
                        if ($position['position_level'] !== $current_level):
                            if ($current_level !== null) echo "</div></div>";
                            $current_level = $position['position_level'];
                    ?>
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 bg-purple-100 text-purple-600 rounded-full text-sm font-medium mr-3">
                                <?= $current_level ?>
                            </span>
                            <?= $level_names[$current_level] ?>
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php endif; ?>
                        
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h5 class="font-medium text-gray-900"><?= htmlspecialchars($position['position_title']) ?></h5>
                                <?php if ($position['is_functional_head']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <i class="fas fa-crown mr-1"></i>Head
                                </span>
                                <?php elseif ($position['is_management_role']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-users mr-1"></i>Manager
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-3">
                                <i class="fas fa-building mr-1"></i><?= htmlspecialchars($position['department_name']) ?>
                            </p>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <?php if ($position['current_incumbent']): ?>
                                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center mr-2">
                                            <span class="text-white text-sm font-medium">
                                                <?= substr($position['current_incumbent'], 0, 2) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($position['current_incumbent']) ?></p>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-red-500 text-sm">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Vacant
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($position['direct_reports_count'] > 0): ?>
                                <span class="text-sm text-gray-500">
                                    <?= $position['direct_reports_count'] ?> reports
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                    <?php endforeach; ?>
                    <?php if ($current_level !== null): ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Positions Tab -->
            <div id="content-positions" class="tab-content p-6 hidden">
                <h3 class="text-lg font-medium text-gray-900 mb-6">Position Management</h3>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-yellow-500 mr-3 mt-1"></i>
                        <div>
                            <h4 class="font-medium text-yellow-900 mb-2">Enterprise Position Framework</h4>
                            <div class="text-sm text-yellow-800 space-y-1">
                                <p>✅ <strong>36 organizational positions</strong> defined across 6 levels</p>
                                <p>✅ <strong>Clear reporting structure</strong> with management authorities</p>
                                <p>✅ <strong>Career progression paths</strong> between positions</p>
                                <p>🔄 <strong>Next:</strong> Assign employees to specific positions and create teams</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-medium text-gray-900">Available Actions</h4>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center">
                                <i class="fas fa-user-plus text-blue-500 mr-3 text-lg"></i>
                                <div>
                                    <h5 class="font-medium text-gray-900">Assign Positions</h5>
                                    <p class="text-sm text-gray-500">Assign employees to organizational positions</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center">
                                <i class="fas fa-layer-group text-green-500 mr-3 text-lg"></i>
                                <div>
                                    <h5 class="font-medium text-gray-900">Manage Positions</h5>
                                    <p class="text-sm text-gray-500">Create, edit, and deactivate positions</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center">
                                <i class="fas fa-route text-purple-500 mr-3 text-lg"></i>
                                <div>
                                    <h5 class="font-medium text-gray-900">Career Paths</h5>
                                    <p class="text-sm text-gray-500">Define progression paths between roles</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transitions Tab -->
            <div id="content-transitions" class="tab-content p-6 hidden">
                <h3 class="text-lg font-medium text-gray-900 mb-6">Role Transitions</h3>
                
                <?php if (empty($pending_transitions)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-exchange-alt text-4xl text-gray-300 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-900 mb-2">No Pending Transitions</h4>
                    <p class="text-gray-500 mb-6">All role transitions have been processed</p>
                    <button onclick="showTransitionModal()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700">
                        <i class="fas fa-plus mr-2"></i>Initiate New Transition
                    </button>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pending_transitions as $transition): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <h4 class="text-lg font-medium text-gray-900 mr-3">
                                        <?= htmlspecialchars($transition['employee_name']) ?>
                                    </h4>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?= $transition['transition_type'] === 'promotion' ? 'bg-green-100 text-green-800' : 
                                           ($transition['transition_type'] === 'transfer' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $transition['transition_type'])) ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Current Role</p>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($transition['current_position']) ?></p>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($transition['current_department']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Proposed Role</p>
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($transition['proposed_position']) ?></p>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($transition['proposed_department']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="text-sm text-gray-500 mb-1">Reason</p>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($transition['reason_for_transition']) ?></p>
                                </div>
                                
                                <div class="flex items-center text-sm text-gray-500">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Effective Date: <?= date('M j, Y', strtotime($transition['effective_date'])) ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-clock mr-1"></i>
                                    Submitted: <?= date('M j, Y', strtotime($transition['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="ml-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    <?= $transition['transition_status'] === 'submitted' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($transition['transition_status'] === 'hr_approved' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $transition['transition_status'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4 flex space-x-3">
                            <button class="inline-flex items-center px-3 py-1 border border-green-300 text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100">
                                <i class="fas fa-check mr-1"></i>Approve
                            </button>
                            <button class="inline-flex items-center px-3 py-1 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100">
                                <i class="fas fa-times mr-1"></i>Reject
                            </button>
                            <button class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-eye mr-1"></i>Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Teams Tab -->
            <div id="content-teams" class="tab-content p-6 hidden">
                <h3 class="text-lg font-medium text-gray-900 mb-6">Team Management</h3>
                
                <div class="text-center py-8">
                    <i class="fas fa-users-cog text-4xl text-gray-300 mb-4"></i>
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Team Management Ready</h4>
                    <p class="text-gray-500 mb-6">Enterprise team structure is ready for implementation</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-2xl mx-auto">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <i class="fas fa-users text-blue-500 text-2xl mb-2"></i>
                            <h5 class="font-medium text-gray-900 mb-1">Create Teams</h5>
                            <p class="text-sm text-gray-500">Set up project and permanent teams</p>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4">
                            <i class="fas fa-user-plus text-green-500 text-2xl mb-2"></i>
                            <h5 class="font-medium text-gray-900 mb-1">Assign Members</h5>
                            <p class="text-sm text-gray-500">Add employees to teams with roles</p>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4">
                            <i class="fas fa-cogs text-purple-500 text-2xl mb-2"></i>
                            <h5 class="font-medium text-gray-900 mb-1">Manage Structure</h5>
                            <p class="text-sm text-gray-500">Cross-functional team coordination</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enterprise Features Summary -->
        <div class="mt-8 bg-purple-50 border border-purple-200 rounded-lg p-6">
            <div class="flex items-start">
                <i class="fas fa-rocket text-purple-500 mr-3 mt-1"></i>
                <div>
                    <h3 class="font-medium text-purple-900 mb-2">Enterprise-Grade Organizational Management</h3>
                    <div class="text-sm text-purple-800 grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>✅ 6-level organizational hierarchy (Executive → IC)</div>
                        <div>✅ 36 predefined positions with clear reporting</div>
                        <div>✅ Role transition workflows with approval chains</div>
                        <div>✅ Historical tracking of all organizational changes</div>
                        <div>✅ Team management with cross-functional support</div>
                        <div>✅ Management authorities and permission matrices</div>
                        <div>✅ Career progression paths and succession planning</div>
                        <div>✅ Enterprise-grade audit trails and compliance</div>
                    </div>
                    <div class="mt-3 text-sm text-purple-700">
                        🚀 <strong>Ready for:</strong> Employee promotions, department transfers, team restructuring, and complex organizational changes
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-purple-500', 'text-purple-600');
        button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    });
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab button
    const activeButton = document.getElementById('tab-' + tabName);
    activeButton.classList.add('active', 'border-purple-500', 'text-purple-600');
    activeButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
}

function showTransitionModal() {
    alert('Role transition workflow coming soon! This will allow HR to initiate promotions, transfers, and organizational changes.');
}

// Initialize first tab as active
document.addEventListener('DOMContentLoaded', function() {
    showTab('hierarchy');
});
</script>

<style>
.tab-button.active {
    border-color: #9333EA;
    color: #9333EA;
}

.tab-button:not(.active) {
    border-color: transparent;
    color: #6B7280;
}

.tab-button:not(.active):hover {
    color: #374151;
    border-color: #D1D5DB;
}
</style>

<?php include '../includes/footer.php'; ?> 