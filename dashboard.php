<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Get dashboard statistics
$stats = getDashboardStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
                <p class="text-gray-600">Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Candidates</p>
                            <p class="text-3xl font-bold text-blue-600"><?php echo $stats['total_candidates']; ?></p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-user-plus text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-500 text-sm">
                            <i class="fas fa-arrow-up"></i> +<?php echo $stats['new_candidates_today']; ?> today
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Active Jobs</p>
                            <p class="text-3xl font-bold text-green-600"><?php echo $stats['active_jobs']; ?></p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-briefcase text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-blue-500 text-sm">
                            <i class="fas fa-eye"></i> <?php echo $stats['total_applications']; ?> applications
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Interviews Today</p>
                            <p class="text-3xl font-bold text-purple-600"><?php echo $stats['interviews_today']; ?></p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-calendar-alt text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-orange-500 text-sm">
                            <i class="fas fa-clock"></i> <?php echo $stats['pending_interviews']; ?> pending
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Pending Offers</p>
                            <p class="text-3xl font-bold text-orange-600"><?php echo $stats['pending_offers']; ?></p>
                        </div>
                        <div class="bg-orange-100 p-3 rounded-full">
                            <i class="fas fa-file-contract text-orange-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-green-500 text-sm">
                            <i class="fas fa-check"></i> <?php echo $stats['accepted_offers']; ?> accepted
                        </span>
                    </div>
                </div>
            </div>

            <!-- Charts and Recent Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Hiring Pipeline Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Hiring Pipeline</h3>
                    <div class="chart-container">
                        <canvas id="pipelineChart"></canvas>
                    </div>
                    <div class="mt-4 text-sm text-gray-600">
                        Pipeline Data: New(<?php echo $stats['pipeline_data'][0]; ?>), 
                        Shortlisted(<?php echo $stats['pipeline_data'][1]; ?>), 
                        Interviewing(<?php echo $stats['pipeline_data'][2]; ?>), 
                        Offered(<?php echo $stats['pipeline_data'][3]; ?>), 
                        Hired(<?php echo $stats['pipeline_data'][4]; ?>)
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Activity</h3>
                    <div class="space-y-4">
                        <?php foreach (getRecentActivity() as $activity): ?>
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-100 p-2 rounded-full">
                                <i class="fas fa-<?php echo $activity['icon']; ?> text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-800"><?php echo $activity['description']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo timeAgo($activity['created_at']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <a href="candidates/add.php" class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-user-plus text-2xl mb-2"></i>
                        <p class="text-sm">Add Candidate</p>
                    </a>
                    <a href="jobs/add.php" class="bg-green-500 hover:bg-green-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-briefcase text-2xl mb-2"></i>
                        <p class="text-sm">Post Job</p>
                    </a>
                    <a href="interviews/schedule.php" class="bg-purple-500 hover:bg-purple-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-calendar-plus text-2xl mb-2"></i>
                        <p class="text-sm">Schedule Interview</p>
                    </a>
                    <a href="offers/create.php" class="bg-orange-500 hover:bg-orange-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-file-contract text-2xl mb-2"></i>
                        <p class="text-sm">Create Offer</p>
                    </a>
                    <a href="employees/list.php" class="bg-indigo-500 hover:bg-indigo-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-users text-2xl mb-2"></i>
                        <p class="text-sm">View Employees</p>
                    </a>
                    <a href="reports/index.php" class="bg-gray-500 hover:bg-gray-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-chart-bar text-2xl mb-2"></i>
                        <p class="text-sm">Reports</p>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            try {
                // Pipeline Chart
                const ctx = document.getElementById('pipelineChart');
                if (ctx) {
                    const chartContext = ctx.getContext('2d');
                    const pipelineData = [<?php echo implode(',', $stats['pipeline_data']); ?>];
                    
                    console.log('Creating chart with data:', pipelineData);
                    
                    const pipelineChart = new Chart(chartContext, {
                        type: 'doughnut',
                        data: {
                            labels: ['New', 'Shortlisted', 'Interviewing', 'Offered', 'Hired'],
                            datasets: [{
                                data: pipelineData,
                                backgroundColor: [
                                    '#3B82F6',
                                    '#10B981',
                                    '#8B5CF6',
                                    '#F59E0B',
                                    '#EF4444'
                                ],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.label + ': ' + context.parsed + ' candidates';
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    console.log('Chart created successfully!');
                } else {
                    console.error('Chart canvas element not found!');
                }
            } catch (error) {
                console.error('Error creating chart:', error);
                // Show fallback message
                const chartContainer = document.querySelector('.chart-container');
                if (chartContainer) {
                    chartContainer.innerHTML = '<div class="text-center text-gray-500 py-8">Chart could not be loaded</div>';
                }
            }
        });
    </script>
</body>
</html> 