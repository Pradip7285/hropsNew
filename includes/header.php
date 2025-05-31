<header class="bg-white shadow-lg border-b border-gray-200">
    <div class="flex items-center justify-between px-6 py-4">
        <div class="flex items-center space-x-4">
            <div class="bg-blue-600 p-2 rounded-lg">
                <i class="fas fa-users text-white text-xl"></i>
            </div>
            <h1 class="text-xl font-bold text-gray-800"><?php echo APP_NAME; ?></h1>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Notifications -->
            <div class="relative">
                <button class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-full transition duration-200">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
                </button>
            </div>
            
            <!-- Search -->
            <div class="relative hidden md:block">
                <input type="text" placeholder="Search..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent w-64">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </div>
            
            <!-- User Menu -->
            <div class="relative">
                <button id="userMenuButton" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 transition duration-200">
                    <div class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-sm font-medium text-gray-800"><?php echo $_SESSION['full_name']; ?></p>
                        <p class="text-xs text-gray-500 capitalize"><?php echo str_replace('_', ' ', $_SESSION['role']); ?></p>
                    </div>
                    <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 hidden z-50">
                    <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-user mr-3"></i>Profile
                    </a>
                    <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-cog mr-3"></i>Settings
                    </a>
                    <div class="border-t border-gray-200 my-1"></div>
                    <a href="logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <i class="fas fa-sign-out-alt mr-3"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// User menu toggle
document.getElementById('userMenuButton').addEventListener('click', function() {
    const menu = document.getElementById('userMenu');
    menu.classList.toggle('hidden');
});

// Close menu when clicking outside
document.addEventListener('click', function(event) {
    const button = document.getElementById('userMenuButton');
    const menu = document.getElementById('userMenu');
    
    if (!button.contains(event.target) && !menu.contains(event.target)) {
        menu.classList.add('hidden');
    }
});
</script> 