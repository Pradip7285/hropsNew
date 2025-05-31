<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<aside class="w-64 bg-white shadow-md h-screen fixed left-0 top-0 overflow-y-auto">
    <div class="p-4 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800"><?php echo APP_NAME; ?></h2>
        <p class="text-sm text-gray-600">HR Management System</p>
    </div>
    
    <nav class="mt-4">
        <ul class="space-y-1 px-4">
            <!-- Dashboard -->
            <li>
                <a href="<?php echo BASE_URL; ?>/dashboard.php" 
                   class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_page == 'dashboard.php') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                    <i class="fas fa-chart-pie mr-3"></i>
                    Dashboard
                </a>
            </li>
            
            <!-- Job Postings -->
            <li>
                <a href="<?php echo BASE_URL; ?>/jobs/list.php" 
                   class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_dir == 'jobs') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                    <i class="fas fa-briefcase mr-3"></i>
                    Job Postings
                </a>
            </li>
            
            <!-- Candidates -->
            <li>
                <a href="<?php echo BASE_URL; ?>/candidates/list.php" 
                   class="flex items-center px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_dir == 'candidates') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                    <i class="fas fa-users mr-3"></i>
                    Candidates
                </a>
            </li>
            
            <!-- Interviews -->
            <li class="group">
                <div class="flex items-center justify-between px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_dir == 'interviews') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-check mr-3"></i>
                        Interviews
                    </div>
                    <i class="fas fa-chevron-down text-xs group-hover:rotate-180 transition-transform duration-200"></i>
                </div>
                <ul class="ml-8 mt-1 space-y-1 <?php echo ($current_dir == 'interviews') ? 'block' : 'hidden group-hover:block'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>/interviews/list.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">All Interviews</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/interviews/schedule.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Schedule New</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/interviews/calendar.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Calendar View</a></li>
                </ul>
            </li>
            
            <!-- Offers -->
            <li class="group">
                <div class="flex items-center justify-between px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_dir == 'offers') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                    <div class="flex items-center">
                        <i class="fas fa-file-contract mr-3"></i>
                        Offers
                    </div>
                    <i class="fas fa-chevron-down text-xs group-hover:rotate-180 transition-transform duration-200"></i>
                </div>
                <ul class="ml-8 mt-1 space-y-1 <?php echo ($current_dir == 'offers') ? 'block' : 'hidden group-hover:block'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>/offers/list.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">All Offers</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/offers/create.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Create Offer</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/offers/templates.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Templates</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/offers/approvals.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Pending Approvals</a></li>
                </ul>
            </li>
            
            <!-- Onboarding -->
            <li class="group">
                <div class="flex items-center justify-between px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_dir == 'onboarding') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                    <div class="flex items-center">
                        <i class="fas fa-user-plus mr-3"></i>
                        Onboarding
                    </div>
                    <i class="fas fa-chevron-down text-xs group-hover:rotate-180 transition-transform duration-200"></i>
                </div>
                <ul class="ml-8 mt-1 space-y-1 <?php echo ($current_dir == 'onboarding') ? 'block' : 'hidden group-hover:block'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>/onboarding/list.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">All Employees</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/onboarding/create.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Add Employee</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/onboarding/templates.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Task Templates</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/onboarding/documents.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Documents</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/onboarding/training.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Training Modules</a></li>
                </ul>
            </li>
            
            <!-- Reports & Analytics -->
            <li class="group">
                <div class="flex items-center justify-between px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200 <?php echo ($current_dir == 'reports') ? 'bg-blue-100 text-blue-700' : ''; ?>">
                    <div class="flex items-center">
                        <i class="fas fa-chart-bar mr-3"></i>
                        Reports
                    </div>
                    <i class="fas fa-chevron-down text-xs group-hover:rotate-180 transition-transform duration-200"></i>
                </div>
                <ul class="ml-8 mt-1 space-y-1 <?php echo ($current_dir == 'reports') ? 'block' : 'hidden group-hover:block'; ?>">
                    <li><a href="<?php echo BASE_URL; ?>/reports/analytics.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Analytics Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/reports/recruitment.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Recruitment Report</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/reports/onboarding.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Onboarding Report</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/reports/performance.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Performance Metrics</a></li>
                </ul>
            </li>
            
            <!-- Settings (Admin Only) -->
            <?php if (hasPermission('admin')): ?>
            <li class="mt-8 pt-4 border-t border-gray-200">
                <div class="px-4 py-2">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Administration</span>
                </div>
            </li>
            
            <li class="group">
                <div class="flex items-center justify-between px-4 py-2 text-gray-700 rounded-lg hover:bg-gray-100 transition duration-200">
                    <div class="flex items-center">
                        <i class="fas fa-cog mr-3"></i>
                        Settings
                    </div>
                    <i class="fas fa-chevron-down text-xs group-hover:rotate-180 transition-transform duration-200"></i>
                </div>
                <ul class="ml-8 mt-1 space-y-1 hidden group-hover:block">
                    <li><a href="<?php echo BASE_URL; ?>/admin/users.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">User Management</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/system.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">System Settings</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/integrations.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Integrations</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/backup.php" class="block px-4 py-1 text-sm text-gray-600 hover:text-blue-600">Backup & Restore</a></li>
                </ul>
            </li>
            <?php endif; ?>
            
            <!-- Help & Support -->
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
            <div class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">
                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                </p>
                <p class="text-xs text-gray-500 truncate">
                    <?php echo ucfirst($_SESSION['role']); ?>
                </p>
            </div>
            <a href="<?php echo BASE_URL; ?>/logout.php" 
               class="text-gray-400 hover:text-red-600 transition duration-200" 
               title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Main content wrapper with left margin to account for fixed sidebar -->
<div class="ml-64">
    <!-- This div ensures content doesn't overlap with the fixed sidebar -->
</div>

<script>
// Enhanced sidebar interactions
document.addEventListener('DOMContentLoaded', function() {
    const groups = document.querySelectorAll('.group');
    
    groups.forEach(group => {
        const trigger = group.querySelector('div');
        const submenu = group.querySelector('ul');
        const chevron = group.querySelector('.fa-chevron-down');
        
        if (trigger && submenu) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Close other open submenus
                groups.forEach(otherGroup => {
                    if (otherGroup !== group) {
                        const otherSubmenu = otherGroup.querySelector('ul');
                        const otherChevron = otherGroup.querySelector('.fa-chevron-down');
                        if (otherSubmenu) {
                            otherSubmenu.classList.add('hidden');
                            otherSubmenu.classList.remove('block');
                        }
                        if (otherChevron) {
                            otherChevron.classList.remove('rotate-180');
                        }
                    }
                });
                
                // Toggle current submenu
                if (submenu.classList.contains('hidden')) {
                    submenu.classList.remove('hidden');
                    submenu.classList.add('block');
                    if (chevron) chevron.classList.add('rotate-180');
                } else {
                    submenu.classList.add('hidden');
                    submenu.classList.remove('block');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            });
        }
    });
    
    // Keep active section expanded
    const activeSection = document.querySelector('.group .bg-blue-100');
    if (activeSection) {
        const parentGroup = activeSection.closest('.group');
        if (parentGroup) {
            const submenu = parentGroup.querySelector('ul');
            const chevron = parentGroup.querySelector('.fa-chevron-down');
            if (submenu) {
                submenu.classList.remove('hidden');
                submenu.classList.add('block');
            }
            if (chevron) {
                chevron.classList.add('rotate-180');
            }
        }
    }
});
</script> 