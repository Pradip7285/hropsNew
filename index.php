<?php
require_once 'config/config.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
} else {
    header('Location: login.php');
    exit();
}
?> 