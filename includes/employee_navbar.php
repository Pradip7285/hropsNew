<?php
require_once 'dual_interface.php';
?>

<!-- Employee Portal Navigation -->
<nav class="bg-white shadow-lg mb-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Interface Switcher for HR Users -->
        <?php if (in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin'])): ?>
            <div class="border-b border-gray-200 pb-4 mb-4">
                <?php echo getInterfaceSwitchHTML(); ?>
            </div>
        <?php endif; ?>
        
        <div class="flex space-x-8">
            <a href="<?php echo BASE_URL; ?>/employee/dashboard.php" 
               class="flex items-center py-4 px-1 border-b-2 <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> transition duration-200">
                <i class="fas fa-tachometer-alt mr-2"></i>
                Dashboard
            </a>
            
            <a href="<?php echo BASE_URL; ?>/employee/tasks.php" 
               class="flex items-center py-4 px-1 border-b-2 <?php echo (basename($_SERVER['PHP_SELF']) == 'tasks.php') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> transition duration-200">
                <i class="fas fa-tasks mr-2"></i>
                My Tasks
            </a>
            
            <a href="<?php echo BASE_URL; ?>/employee/training.php" 
               class="flex items-center py-4 px-1 border-b-2 <?php echo (basename($_SERVER['PHP_SELF']) == 'training.php') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> transition duration-200">
                <i class="fas fa-graduation-cap mr-2"></i>
                Training
            </a>
            
            <a href="<?php echo BASE_URL; ?>/employee/documents.php" 
               class="flex items-center py-4 px-1 border-b-2 <?php echo (basename($_SERVER['PHP_SELF']) == 'documents.php') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> transition duration-200">
                <i class="fas fa-file-alt mr-2"></i>
                Documents
            </a>
            
            <a href="<?php echo BASE_URL; ?>/employee/policies.php" 
               class="flex items-center py-4 px-1 border-b-2 <?php echo (basename($_SERVER['PHP_SELF']) == 'policies.php') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> transition duration-200">
                <i class="fas fa-clipboard-list mr-2"></i>
                Policies
            </a>
            
            <a href="<?php echo BASE_URL; ?>/performance/my_goals.php" 
               class="flex items-center py-4 px-1 border-b-2 <?php echo (basename($_SERVER['PHP_SELF']) == 'my_goals.php') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> transition duration-200">
                <i class="fas fa-bullseye mr-2"></i>
                My Goals
            </a>
        </div>
    </div>
</nav> 