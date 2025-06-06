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

// Get department statistics
$dept_stats = $conn->query("
    SELECT 
        COUNT(*) as total_departments,
        SUM(budget_allocated) as total_budget,
        COUNT(CASE WHEN head_employee_id IS NOT NULL THEN 1 END) as departments_with_heads,
        COUNT(CASE WHEN head_employee_id IS NULL THEN 1 END) as departments_without_heads
    FROM departments WHERE is_active = 1
")->fetch(PDO::FETCH_ASSOC);

// Get detailed department information
$departments = $conn->query("
    SELECT d.*, 
           CONCAT(u.first_name, ' ', u.last_name) as head_name,
           u.email as head_email,
           COUNT(e.id) as employee_count,
           COALESCE(SUM(db.allocated_amount), 0) as total_budget_detailed
    FROM departments d
    LEFT JOIN employees head_emp ON d.head_employee_id = head_emp.id
    LEFT JOIN users u ON head_emp.user_id = u.id
    LEFT JOIN employees e ON d.id = e.department_id
    LEFT JOIN department_budgets db ON d.id = db.department_id AND db.fiscal_year = YEAR(NOW())
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY d.department_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get department goals
$goals = $conn->query("
    SELECT dg.*, d.department_name
    FROM department_goals dg
    JOIN departments d ON dg.department_id = d.id
    WHERE dg.status = 'active'
    ORDER BY d.department_name, dg.priority DESC
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Department Management';
include '../includes/header.php';
?>

<div class="min-h-screen bg-gray-100 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    <i class="fas fa-building mr-3 text-blue-600"></i>Department Management
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Manage departments, heads, budgets, and organizational structure
                </p>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="../dashboard.php" class="mr-3 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-building text-2xl text-blue-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Departments</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $dept_stats['total_departments'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-dollar-sign text-2xl text-green-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Budget</dt>
                                <dd class="text-lg font-medium text-gray-900">$<?= number_format($dept_stats['total_budget']) ?></dd>
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
                                <dt class="text-sm font-medium text-gray-500 truncate">With Heads</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $dept_stats['departments_with_heads'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-2xl text-red-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Need Heads</dt>
                                <dd class="text-lg font-medium text-gray-900"><?= $dept_stats['departments_without_heads'] ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Overview -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Department Structure</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($departments as $dept): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span class="text-blue-600 font-semibold text-sm"><?= substr($dept['department_code'], 0, 3) ?></span>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($dept['department_name']) ?></h4>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($dept['department_code']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-2 mb-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Employees:</span>
                                <span class="font-medium"><?= $dept['employee_count'] ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Budget:</span>
                                <span class="font-medium text-green-600">$<?= number_format($dept['budget_allocated']) ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Cost Center:</span>
                                <span class="font-medium"><?= htmlspecialchars($dept['cost_center_code']) ?></span>
                            </div>
                        </div>
                        
                        <div class="pt-3 border-t border-gray-100">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <span class="text-xs text-gray-500">Department Head:</span>
                                    <p class="text-sm font-medium <?= $dept['head_name'] ? 'text-gray-900' : 'text-red-500' ?>">
                                        <?= $dept['head_name'] ?: 'Not Assigned' ?>
                                    </p>
                                </div>
                                <?php if (!$dept['head_name']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>No Head
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-check mr-1"></i>Active
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Current Issues & Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Issues -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Issues Requiring Attention
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php 
                        $issues = [];
                        foreach ($departments as $dept) {
                            if (!$dept['head_name']) {
                                $issues[] = "Department {$dept['department_name']} needs a department head";
                            }
                        }
                        
                        if (empty($issues)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-2"></i>
                            <p class="text-gray-500">No issues found! All departments are properly managed.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($issues as $issue): ?>
                        <div class="flex items-center p-3 bg-red-50 border border-red-200 rounded-lg">
                            <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                            <span class="text-red-700"><?= htmlspecialchars($issue) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-blue-600">
                        <i class="fas fa-tools mr-2"></i>Management Tools
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-user-plus text-blue-500 mr-3 text-lg"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Assign Department Heads</h4>
                                    <p class="text-sm text-gray-500">Designate leadership for departments</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-calculator text-green-500 mr-3 text-lg"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Budget Management</h4>
                                    <p class="text-sm text-gray-500">Manage department budgets and spending</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-sitemap text-purple-500 mr-3 text-lg"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Organizational Chart</h4>
                                    <p class="text-sm text-gray-500">View department hierarchy and structure</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-center">
                                <i class="fas fa-bullseye text-red-500 mr-3 text-lg"></i>
                                <div>
                                    <h4 class="font-medium text-gray-900">Department Goals</h4>
                                    <p class="text-sm text-gray-500">Set and track department objectives</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Information -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-500 mr-3 mt-1"></i>
                <div>
                    <h3 class="font-medium text-blue-900 mb-2">Department Management Status</h3>
                    <div class="text-sm text-blue-800 space-y-1">
                        <p>✅ <strong><?= $dept_stats['total_departments'] ?> departments</strong> configured with formal structure</p>
                        <p>✅ <strong>$<?= number_format($dept_stats['total_budget']) ?></strong> total budget allocated across departments</p>
                        <p>✅ <strong><?= $dept_stats['departments_with_heads'] ?> departments</strong> have assigned heads</p>
                        <?php if ($dept_stats['departments_without_heads'] > 0): ?>
                        <p>❌ <strong><?= $dept_stats['departments_without_heads'] ?> departments</strong> still need department heads</p>
                        <?php endif; ?>
                        <p>🎯 Next steps: Complete department head assignments and implement goal tracking</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 