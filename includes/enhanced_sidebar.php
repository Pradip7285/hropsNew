<?php
require_once 'dual_interface.php';

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$current_interface = getCurrentInterface();
$user_role = $_SESSION['role'];
?>

<aside class="w-64 bg-white shadow-md h-screen fixed left-0 top-0 overflow-y-auto">
    <div class="p-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800"><?php echo APP_NAME; ?></h2>
        <p class="text-sm text-gray-600">HR Management System</p>
    </div>
    
    <!-- Interface Switcher for HR Users -->
    <?php if (in_array($user_role, ['hr_recruiter', 'hiring_manager', 'admin'])): ?>
        <div class="p-4 border-b border-gray-200">
            <?php echo getInterfaceSwitchHTML(); ?>
        </div>
    <?php endif; ?>
    
    <nav class="mt-4">
        <ul class="space-y-1 px-4">
            
            <?php if ($current_interface === 'employee' && in_array($user_role, ['hr_recruiter', 'hiring_manager', 'admin'])): ?>
                <!-- Employee Portal Navigation for HR Users -->
                <li>
                    <div class="px-4 py-2 mb-4">
                        <span class="text-xs font-semibold text-purple-600 uppercase tracking-wider">Employee Portal</span>
                    </div>
                </li>
                
                <li>
                    <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" 
                       class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'dashboard.php' && $current_dir == 'employee') ? 'bg-purple-100 text-purple-700' : ''; ?>">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        My Dashboard
                    </a>
                </li>
                
                <li>
                    <a href="<?php echo BASE_URL; ?>/employee/tasks.php" 
                       class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'tasks.php' && $current_dir == 'employee') ? 'bg-purple-100 text-purple-700' : ''; ?>">
                        <i class="fas fa-tasks mr-3"></i>
                        My Tasks
                    </a>
                </li>
                
                <li>
                    <a href="<?php echo BASE_URL; ?>/employee/training.php" 
                       class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'training.php' && $current_dir == 'employee') ? 'bg-purple-100 text-purple-700' : ''; ?>">
                        <i class="fas fa-graduation-cap mr-3"></i>
                        Training
                    </a>
                </li>
                
                <li>
                    <a href="<?php echo BASE_URL; ?>/employee/documents.php" 
                       class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'documents.php' && $current_dir == 'employee') ? 'bg-purple-100 text-purple-700' : ''; ?>">
                        <i class="fas fa-file-alt mr-3"></i>
                        Documents
                    </a>
                </li>
                
                <li>
                    <a href="<?php echo BASE_URL; ?>/employee/policies.php" 
                       class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'policies.php' && $current_dir == 'employee') ? 'bg-purple-100 text-purple-700' : ''; ?>">
                        <i class="fas fa-clipboard-list mr-3"></i>
                        Policies
                    </a>
                </li>
                
                <li>
                    <a href="<?php echo BASE_URL; ?>/performance/my_goals.php" 
                       class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'my_goals.php') ? 'bg-purple-100 text-purple-700' : ''; ?>">
                        <i class="fas fa-bullseye mr-3"></i>
                        My Goals
                    </a>
                </li>
                
            <?php else: ?>
                <!-- Standard HR Management Navigation -->
                <li>
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" 
                       class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'dashboard.php' && $current_dir != 'employee') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                        <i class="fas fa-chart-pie mr-3"></i>
                        Dashboard
                    </a>
                </li>
                
                <!-- Include original HR navigation items -->
                <?php include 'sidebar_hr_items.php'; ?>
                
            <?php endif; ?>
            
            <!-- Help & Support (always visible) -->
            <li class="mt-8 pt-4 border-t border-gray-200">
                <a href="<?php echo BASE_URL; ?>/help.php" 
                   class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200">
                    <i class="fas fa-question-circle mr-3"></i>
                    Help & Support
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- User Info at Bottom -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200 bg-white">
        <div class="flex items-center">
            <div class="bg-<?php echo $current_interface === 'employee' ? 'purple' : 'blue'; ?>-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                <?php 
                $first_initial = isset($_SESSION['first_name']) ? substr($_SESSION['first_name'], 0, 1) : '';
                $last_initial = isset($_SESSION['last_name']) ? substr($_SESSION['last_name'], 0, 1) : '';
                if (!$first_initial && !$last_initial) {
                    echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));
                } else {
                    echo strtoupper($first_initial . $last_initial);
                }
                ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">
                    <?php 
                    $full_name = '';
                    if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
                        $full_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
                    } elseif (isset($_SESSION['full_name'])) {
                        $full_name = $_SESSION['full_name'];
                    } else {
                        $full_name = $_SESSION['username'] ?? 'User';
                    }
                    echo htmlspecialchars($full_name);
                    ?>
                </p>
                <p class="text-xs text-gray-500">
                    <?php echo ucfirst($_SESSION['role']); ?>
                    <?php if ($current_interface === 'employee'): ?>
                        <span class="text-purple-600">(Employee View)</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="mt-3 flex items-center justify-between">
            <a href="<?php echo BASE_URL; ?>/profile.php" class="text-xs text-gray-500 hover:text-gray-700">
                <i class="fas fa-user mr-1"></i>Profile
            </a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="text-xs text-red-500 hover:text-red-700">
                <i class="fas fa-sign-out-alt mr-1"></i>Logout
            </a>
        </div>
    </div>
</aside> 