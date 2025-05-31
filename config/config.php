<?php
// Application configuration
define('APP_NAME', 'HR Operations Portal');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/hrops/');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hrops_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx']);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('BCRYPT_COST', 12);

// Email settings (for future use)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

// Start session
session_start();

// Include database class
require_once 'database.php';

// Auto-logout after timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}
$_SESSION['last_activity'] = time();
?> 