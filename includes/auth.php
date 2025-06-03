<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Function to require login only (no specific role)
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

// Function to check user role permissions
function hasPermission($required_role) {
    $role_hierarchy = [
        'admin' => 5,
        'hr_recruiter' => 4,
        'hiring_manager' => 3,
        'interviewer' => 2,
        'employee' => 1
    ];
    
    $user_level = $role_hierarchy[$_SESSION['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

// Function to require specific role
function requireRole($required_role) {
    if (!hasPermission($required_role)) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit();
    }
}
?> 