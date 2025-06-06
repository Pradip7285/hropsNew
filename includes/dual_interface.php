<?php
// Dual Interface Management for HR Users
// Allows HR staff to access both HR management tools AND employee portal features

function canAccessEmployeePortal() {
    // HR users should be able to access employee portal if they have an employee record
    if (in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin'])) {
        return hasEmployeeRecord($_SESSION['user_id']);
    }
    return $_SESSION['role'] === 'employee';
}

function hasEmployeeRecord($user_id) {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Exception $e) {
        return false;
    }
}

function getCurrentInterface() {
    return $_SESSION['interface_mode'] ?? 'hr';
}

function setInterface($mode) {
    if (in_array($mode, ['hr', 'employee'])) {
        $_SESSION['interface_mode'] = $mode;
        return true;
    }
    return false;
}

function getInterfaceSwitchHTML() {
    if (!in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin'])) {
        return '';
    }
    
    $current_mode = getCurrentInterface();
    $switch_mode = $current_mode === 'hr' ? 'employee' : 'hr';
    $switch_label = $current_mode === 'hr' ? 'Switch to Employee View' : 'Switch to HR Management';
    $current_label = $current_mode === 'hr' ? 'HR Management Mode' : 'Employee Portal Mode';
    
    return "
    <div class='bg-gradient-to-r from-blue-500 to-purple-600 text-white p-3 rounded-lg mb-4'>
        <div class='flex items-center justify-between'>
            <div class='flex items-center'>
                <i class='fas fa-" . ($current_mode === 'hr' ? 'user-tie' : 'user') . " mr-2'></i>
                <span class='font-medium'>$current_label</span>
            </div>
            <a href='" . BASE_URL . "/switch_interface.php?mode=$switch_mode' 
               class='bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-full text-sm transition duration-200'>
                <i class='fas fa-exchange-alt mr-1'></i>
                $switch_label
            </a>
        </div>
    </div>";
}

function getNavigationForInterface() {
    $current_mode = getCurrentInterface();
    $role = $_SESSION['role'];
    
    if ($current_mode === 'employee' && in_array($role, ['hr_recruiter', 'hiring_manager', 'admin'])) {
        // HR user in employee mode - show employee navigation
        return getEmployeeNavigation();
    } else {
        // Regular HR navigation
        return getHRNavigation();
    }
}

function getEmployeeNavigation() {
    return [
        [
            'icon' => 'tachometer-alt',
            'label' => 'My Dashboard',
            'url' => BASE_URL . '/employee/dashboard.php',
            'active_check' => 'dashboard.php'
        ],
        [
            'icon' => 'tasks',
            'label' => 'My Tasks',
            'url' => BASE_URL . '/employee/tasks.php',
            'active_check' => 'tasks.php'
        ],
        [
            'icon' => 'graduation-cap',
            'label' => 'Training',
            'url' => BASE_URL . '/employee/training.php',
            'active_check' => 'training.php'
        ],
        [
            'icon' => 'file-alt',
            'label' => 'Documents',
            'url' => BASE_URL . '/employee/documents.php',
            'active_check' => 'documents.php'
        ],
        [
            'icon' => 'clipboard-list',
            'label' => 'Policies',
            'url' => BASE_URL . '/employee/policies.php',
            'active_check' => 'policies.php'
        ],
        [
            'icon' => 'bullseye',
            'label' => 'My Goals',
            'url' => BASE_URL . '/performance/my_goals.php',
            'active_check' => 'my_goals.php'
        ]
    ];
}

function getHRNavigation() {
    return [
        [
            'icon' => 'chart-pie',
            'label' => 'Dashboard',
            'url' => BASE_URL . '/dashboard.php'
        ],
        [
            'icon' => 'briefcase',
            'label' => 'Job Postings',
            'url' => BASE_URL . '/jobs/list.php'
        ],
        [
            'icon' => 'users',
            'label' => 'Candidates',
            'url' => BASE_URL . '/candidates/list.php'
        ],
        [
            'icon' => 'calendar-check',
            'label' => 'Interviews',
            'url' => BASE_URL . '/interviews/list.php'
        ],
        [
            'icon' => 'file-contract',
            'label' => 'Offers',
            'url' => BASE_URL . '/offers/list.php'
        ],
        [
            'icon' => 'user-plus',
            'label' => 'Onboarding',
            'url' => BASE_URL . '/onboarding/list.php'
        ],
        [
            'icon' => 'chart-line',
            'label' => 'Performance',
            'url' => BASE_URL . '/performance/goals.php'
        ],
        [
            'icon' => 'chart-bar',
            'label' => 'Reports',
            'url' => BASE_URL . '/reports/analytics.php'
        ]
    ];
}

function shouldRedirectToEmployeePortal() {
    // Only redirect pure employees, not HR users
    return $_SESSION['role'] === 'employee';
}

function ensureHREmployeeRecord($user_id) {
    global $conn;
    
    try {
        // Check if HR user has employee record
        $stmt = $conn->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $employee ? $employee['employee_id'] : null;
        
    } catch (Exception $e) {
        error_log("Error checking HR employee record: " . $e->getMessage());
        return null;
    }
}

function isInEmployeeMode() {
    return getCurrentInterface() === 'employee' && in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin']);
}
?> 