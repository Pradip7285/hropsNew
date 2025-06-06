<?php
require_once 'config/config.php';
require_once 'includes/dual_interface.php';

// Only allow HR users to switch interfaces
if (!in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$mode = $_GET['mode'] ?? 'hr';

if (setInterface($mode)) {
    // Redirect to appropriate dashboard
    if ($mode === 'employee') {
        header('Location: ' . BASE_URL . '/employee/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/dashboard.php');
    }
} else {
    header('Location: ' . BASE_URL . '/dashboard.php');
}

exit();
?> 