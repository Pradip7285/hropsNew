<?php
// HR Operations Installation Script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_POST) {
    if ($step == 1) {
        // Database setup
        $host = $_POST['host'] ?? 'localhost';
        $username = $_POST['username'] ?? 'root';
        $password = $_POST['password'] ?? '';
        $database = $_POST['database'] ?? 'hrops_db';
        
        try {
            // Test connection
            $pdo = new PDO("mysql:host=$host", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database`");
            $pdo->exec("USE `$database`");
            
            // Read and execute schema
            $schema = file_get_contents('database/schema.sql');
            $statements = explode(';', $schema);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            
            // Update config file
            $config_content = file_get_contents('config/config.php');
            $config_content = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', '$host');", $config_content);
            $config_content = str_replace("define('DB_NAME', 'hrops_db');", "define('DB_NAME', '$database');", $config_content);
            $config_content = str_replace("define('DB_USER', 'root');", "define('DB_USER', '$username');", $config_content);
            $config_content = str_replace("define('DB_PASS', '');", "define('DB_PASS', '$password');", $config_content);
            
            file_put_contents('config/config.php', $config_content);
            
            $success = "Database setup completed successfully!";
            $step = 2;
            
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    if ($step == 2 && $_POST['admin_setup']) {
        // Admin user setup
        $admin_username = $_POST['admin_username'];
        $admin_email = $_POST['admin_email'];
        $admin_password = $_POST['admin_password'];
        $admin_first_name = $_POST['admin_first_name'];
        $admin_last_name = $_POST['admin_last_name'];
        
        if (!empty($admin_username) && !empty($admin_email) && !empty($admin_password)) {
            try {
                require_once 'config/database.php';
                $db = new Database();
                $conn = $db->getConnection();
                
                $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                
                // Update admin user
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, first_name = ?, last_name = ? WHERE role = 'admin'");
                $stmt->execute([$admin_username, $admin_email, $hashed_password, $admin_first_name, $admin_last_name]);
                
                $success = "Admin user created successfully! You can now login.";
                $step = 3;
                
            } catch (Exception $e) {
                $error = "Admin setup error: " . $e->getMessage();
            }
        } else {
            $error = "Please fill in all fields";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Operations - Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-500 to-purple-600 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-8">
        <div class="text-center mb-8">
            <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-cog text-blue-600 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">HR Operations Setup</h1>
            <p class="text-gray-600 mt-2">Let's get your HR system up and running</p>
        </div>

        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700">Step <?php echo $step; ?> of 3</span>
                <span class="text-sm text-gray-500"><?php echo round(($step/3)*100); ?>% Complete</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo ($step/3)*100; ?>%"></div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
        <!-- Database Configuration -->
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Database Configuration</h2>
            <p class="text-gray-600 mb-6">Enter your database connection details:</p>
        </div>

        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Database Host</label>
                    <input type="text" name="host" value="localhost" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>
                    <input type="text" name="database" value="hrops_db" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" value="root" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>
            
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded-lg transition duration-200 font-semibold">
                <i class="fas fa-database mr-2"></i>Setup Database
            </button>
        </form>
        <?php endif; ?>

        <?php if ($step == 2): ?>
        <!-- Admin User Setup -->
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Admin User Setup</h2>
            <p class="text-gray-600 mb-6">Create your admin account:</p>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="admin_setup" value="1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                    <input type="text" name="admin_first_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                    <input type="text" name="admin_last_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" name="admin_username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="admin_email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" name="admin_password" required minlength="6"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
            </div>
            
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded-lg transition duration-200 font-semibold">
                <i class="fas fa-user-shield mr-2"></i>Create Admin Account
            </button>
        </form>
        <?php endif; ?>

        <?php if ($step == 3): ?>
        <!-- Installation Complete -->
        <div class="text-center">
            <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Installation Complete!</h2>
            <p class="text-gray-600 mb-8">Your HR Operations system has been successfully installed and configured.</p>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <h3 class="font-semibold text-gray-800 mb-2">Next Steps:</h3>
                <ul class="space-y-1 text-sm text-gray-600">
                    <li>• Delete or rename the install.php file for security</li>
                    <li>• Set up your job postings</li>
                    <li>• Configure additional users</li>
                    <li>• Start adding candidates</li>
                </ul>
            </div>
            
            <a href="login.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white py-3 px-6 rounded-lg transition duration-200 font-semibold">
                <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 