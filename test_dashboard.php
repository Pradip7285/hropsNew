<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Interview Management System - Testing Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <i class="fas fa-flask text-blue-600 mr-2"></i>
                            Interview Management System - Testing Dashboard
                        </h1>
                        <p class="text-gray-600 mt-2">Phase 2 Testing Suite - Test all interview management features</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Testing Environment</div>
                        <div class="text-lg font-semibold text-green-600">✅ Ready</div>
                    </div>
                </div>
            </div>

            <!-- Setup Section -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-yellow-800 mb-3">
                    <i class="fas fa-cog mr-2"></i>Setup Required
                </h2>
                <p class="text-yellow-700 mb-4">Before testing, make sure you have set up the test data:</p>
                <div class="flex space-x-4">
                    <a href="test_setup.php" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-database mr-2"></i>Setup Test Data
                    </a>
                    <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login (admin/admin123)
                    </a>
                </div>
            </div>

            <!-- Core Testing Scenarios -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">
                
                <!-- Dashboard Testing -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-home text-blue-600 mr-2"></i>Dashboard
                        </h3>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">Core</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">Test main dashboard, statistics, and quick actions</p>
                    <div class="space-y-2">
                        <a href="dashboard.php" class="block w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition duration-200 text-center">
                            <i class="fas fa-chart-line mr-2"></i>Open Dashboard
                        </a>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <div class="font-medium">Test Cases:</div>
                        <ul class="list-disc list-inside">
                            <li>Statistics display</li>
                            <li>Quick actions work</li>
                            <li>Charts render</li>
                        </ul>
                    </div>
                </div>

                <!-- Interview List -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-list text-green-600 mr-2"></i>Interview List
                        </h3>
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">Core</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">Test listing, filtering, and bulk operations</p>
                    <div class="space-y-2">
                        <a href="interviews/list.php" class="block w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded transition duration-200 text-center">
                            <i class="fas fa-table mr-2"></i>View All Interviews
                        </a>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <div class="font-medium">Test Cases:</div>
                        <ul class="list-disc list-inside">
                            <li>Search & filtering</li>
                            <li>Status indicators</li>
                            <li>Bulk actions</li>
                        </ul>
                    </div>
                </div>

                <!-- Today's Interviews -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-calendar-day text-purple-600 mr-2"></i>Today's Interviews
                        </h3>
                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-sm">Core</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">Test daily interview management</p>
                    <div class="space-y-2">
                        <a href="interviews/today.php" class="block w-full bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded transition duration-200 text-center">
                            <i class="fas fa-clock mr-2"></i>Today's Schedule
                        </a>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <div class="font-medium">Test Cases:</div>
                        <ul class="list-disc list-inside">
                            <li>Real-time countdown</li>
                            <li>Status updates</li>
                            <li>Priority indicators</li>
                        </ul>
                    </div>
                </div>

                <!-- Interview Scheduling -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-calendar-plus text-orange-600 mr-2"></i>Scheduling
                        </h3>
                        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-sm">Critical</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">Test smart scheduling with conflict detection</p>
                    <div class="space-y-2">
                        <a href="interviews/schedule.php" class="block w-full bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded transition duration-200 text-center">
                            <i class="fas fa-plus mr-2"></i>Schedule Interview
                        </a>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <div class="font-medium">Test Cases:</div>
                        <ul class="list-disc list-inside">
                            <li>Conflict detection</li>
                            <li>Smart suggestions</li>
                            <li>Email notifications</li>
                        </ul>
                    </div>
                </div>

                <!-- Interview Feedback -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-comments text-red-600 mr-2"></i>Feedback System
                        </h3>
                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">Critical</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">Test feedback forms with bias detection</p>
                    <div class="space-y-2">
                        <a href="interviews/feedback.php" class="block w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded transition duration-200 text-center">
                            <i class="fas fa-edit mr-2"></i>Submit Feedback
                        </a>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <div class="font-medium">Test Cases:</div>
                        <ul class="list-disc list-inside">
                            <li>Bias detection</li>
                            <li>Draft saving</li>
                            <li>Auto-save feature</li>
                        </ul>
                    </div>
                </div>

                <!-- Calendar View -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-calendar text-indigo-600 mr-2"></i>Calendar View
                        </h3>
                        <span class="bg-indigo-100 text-indigo-800 px-2 py-1 rounded text-sm">Core</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">Test interactive calendar with multiple views</p>
                    <div class="space-y-2">
                        <a href="interviews/calendar.php" class="block w-full bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded transition duration-200 text-center">
                            <i class="fas fa-calendar-alt mr-2"></i>Open Calendar
                        </a>
                    </div>
                    <div class="mt-4 text-xs text-gray-500">
                        <div class="font-medium">Test Cases:</div>
                        <ul class="list-disc list-inside">
                            <li>Day/Week/Month views</li>
                            <li>Interactive modals</li>
                            <li>Color coding</li>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Advanced Features -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-cogs text-gray-600 mr-2"></i>Advanced Features Testing
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    <a href="interviews/reminders.php" class="bg-yellow-500 hover:bg-yellow-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-bell text-2xl mb-2"></i>
                        <div class="font-semibold">Reminders</div>
                        <div class="text-xs">Bulk & individual</div>
                    </a>
                    
                    <a href="interviews/questions.php" class="bg-pink-500 hover:bg-pink-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-question-circle text-2xl mb-2"></i>
                        <div class="font-semibold">Question Bank</div>
                        <div class="text-xs">Templates & categories</div>
                    </a>
                    
                    <a href="interviews/reports.php" class="bg-teal-500 hover:bg-teal-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-chart-bar text-2xl mb-2"></i>
                        <div class="font-semibold">Analytics</div>
                        <div class="text-xs">Reports & metrics</div>
                    </a>
                    
                    <a href="interviews/edit.php" class="bg-gray-500 hover:bg-gray-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-edit text-2xl mb-2"></i>
                        <div class="font-semibold">Interview Editing</div>
                        <div class="text-xs">Modify & reschedule</div>
                    </a>
                    
                </div>
            </div>

            <!-- Test Data Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-blue-800 mb-3">
                    <i class="fas fa-info-circle mr-2"></i>Test Data Overview
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-blue-700">
                    <div>
                        <div class="font-semibold">📋 Interviews Created</div>
                        <ul class="text-sm mt-1">
                            <li>• 2 interviews today</li>
                            <li>• 2 interviews tomorrow</li>
                            <li>• 2 completed interviews</li>
                            <li>• 1 future interview</li>
                            <li>• 1 overdue interview</li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">👥 Test Candidates</div>
                        <ul class="text-sm mt-1">
                            <li>• John Smith (Engineer)</li>
                            <li>• Sarah Johnson (Marketing)</li>
                            <li>• Michael Davis (Analyst)</li>
                            <li>• Emily Chen (Designer)</li>
                            <li>• David Wilson (Senior Dev)</li>
                            <li>• Lisa Anderson (Marketing)</li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">💼 Test Jobs</div>
                        <ul class="text-sm mt-1">
                            <li>• Senior Software Engineer</li>
                            <li>• Marketing Manager</li>
                            <li>• Data Analyst</li>
                            <li>• UX Designer</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Testing Checklist -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-tasks text-gray-600 mr-2"></i>Testing Checklist
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Core Functionality</h3>
                        <div class="space-y-1 text-sm">
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Dashboard loads correctly
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Interview list displays
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Scheduling works
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Feedback system operational
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Calendar view functional
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Advanced Features</h3>
                        <div class="space-y-1 text-sm">
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Bias detection working
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Draft saving functional
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Conflict detection active
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Email notifications sent
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Reminders system works
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">User Experience</h3>
                        <div class="space-y-1 text-sm">
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Mobile responsive
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Navigation intuitive
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Forms user-friendly
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Error handling graceful
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2"> Performance acceptable
                            </label>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 text-center text-gray-500 text-sm">
                <p>🧪 Interview Management System Testing Dashboard</p>
                <p>Use this dashboard to systematically test all Phase 2 features</p>
            </div>
        </div>
    </div>

    <script>
        // Simple checklist functionality
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const total = checkboxes.length;
                    const checked = document.querySelectorAll('input[type="checkbox"]:checked').length;
                    const percentage = Math.round((checked / total) * 100);
                    
                    // You could add a progress indicator here
                    console.log(`Testing Progress: ${checked}/${total} (${percentage}%)`);
                });
            });
        });
    </script>
</body>
</html> 