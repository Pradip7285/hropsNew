<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Include dual interface support
require_once 'includes/dual_interface.php';

// Include approval engine for approval tracking
require_once 'includes/approval_engine.php';

// Redirect employees to their dedicated portal (but not HR users in employee mode)
if (shouldRedirectToEmployeePortal()) {
    header('Location: ' . BASE_URL . '/employee/dashboard.php');
    exit();
}

// Get dashboard statistics
$stats = getDashboardStats();

// Get approval tracking data
$db = new Database();
$conn = $db->getConnection();
$approval_engine = new ApprovalEngine($conn);

// Get all pending approvals with detailed information
$pending_approvals_query = "
    SELECT 
        ai.id as approval_instance_id,
        ai.entity_type,
        ai.entity_id,
        ai.overall_status,
        ai.initiated_at,
        ap.id as step_id,
        ap.step_number,
        ap.step_name,
        ap.status as step_status,
        ap.due_date,
        ap.assigned_to,
        ap.delegated_to,
        u.first_name as assigned_first,
        u.last_name as assigned_last,
        u.email as assigned_email,
        u.role as assigned_role,
        d.first_name as delegate_first,
        d.last_name as delegate_last,
        ast.sla_target_hours,
        ast.started_at as sla_started,
        ast.escalation_triggered_at,
        CASE 
            WHEN ai.entity_type = 'offer' THEN CONCAT(c.first_name, ' ', c.last_name, ' - ', j.title, ' ($', o.salary_offered, ')')
            WHEN ai.entity_type = 'interview' THEN CONCAT(c2.first_name, ' ', c2.last_name, ' - ', j2.title, ' Interview')
            ELSE CONCAT(ai.entity_type, ' #', ai.entity_id)
        END as entity_description,
        CASE 
            WHEN ai.entity_type = 'offer' THEN o.offer_complexity
            WHEN ai.entity_type = 'interview' THEN i.interview_complexity
            ELSE 'standard'
        END as complexity
    FROM approval_instances ai
    JOIN approval_steps ap ON ai.id = ap.instance_id AND ap.status = 'pending'
    LEFT JOIN users u ON ap.assigned_to = u.id
    LEFT JOIN users d ON ap.delegated_to = d.id
    LEFT JOIN approval_sla_tracking ast ON ap.id = ast.approval_step_id
    LEFT JOIN offers o ON ai.entity_type = 'offer' AND ai.entity_id = o.id
    LEFT JOIN candidates c ON o.candidate_id = c.id
    LEFT JOIN job_postings j ON o.job_id = j.id
    LEFT JOIN interviews i ON ai.entity_type = 'interview' AND ai.entity_id = i.id
    LEFT JOIN candidates c2 ON i.candidate_id = c2.id
    LEFT JOIN job_postings j2 ON i.job_id = j2.id
    WHERE ai.overall_status = 'pending'
    AND ap.step_number = ai.current_step
    ORDER BY ap.due_date ASC, ai.initiated_at ASC
";

$pending_approvals = $conn->query($pending_approvals_query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate SLA status for each approval
foreach ($pending_approvals as &$approval) {
    if ($approval['sla_started']) {
        $hours_elapsed = (time() - strtotime($approval['sla_started'])) / 3600;
        $sla_target = $approval['sla_target_hours'] ?? 48;
        $approval['hours_elapsed'] = round($hours_elapsed, 1);
        $approval['sla_percentage'] = min(100, round(($hours_elapsed / $sla_target) * 100, 1));
        $approval['sla_status'] = $hours_elapsed > $sla_target ? 'overdue' : 
                                 ($hours_elapsed > ($sla_target * 0.8) ? 'warning' : 'on_track');
    } else {
        $approval['hours_elapsed'] = 0;
        $approval['sla_percentage'] = 0;
        $approval['sla_status'] = 'on_track';
    }
}

// Get approval summary statistics
$approval_stats = [
    'total_pending' => count($pending_approvals),
    'overdue' => count(array_filter($pending_approvals, fn($a) => $a['sla_status'] === 'overdue')),
    'warning' => count(array_filter($pending_approvals, fn($a) => $a['sla_status'] === 'warning')),
    'on_track' => count(array_filter($pending_approvals, fn($a) => $a['sla_status'] === 'on_track'))
];
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
        .sla-progress {
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
        }
        .sla-progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .sla-on-track { background-color: #10b981; }
        .sla-warning { background-color: #f59e0b; }
        .sla-overdue { background-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    
    <div class="flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <!-- Interface Switcher for HR Users -->
            <?php if (in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin'])): ?>
                <div class="mb-6">
                    <?php echo getInterfaceSwitchHTML(); ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
                <p class="text-gray-600">Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
            </div>

            <!-- Approval Status Overview -->
            <div class="mb-8 bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Approval Workflow Status</h3>
                    <a href="offers/enhanced_approvals.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm transition duration-200">
                        <i class="fas fa-cog mr-1"></i>Manage Approvals
                    </a>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Pending</p>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $approval_stats['total_pending']; ?></p>
                            </div>
                            <div class="bg-gray-200 p-3 rounded-full">
                                <i class="fas fa-hourglass-half text-gray-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-green-600">On Track</p>
                                <p class="text-2xl font-bold text-green-700"><?php echo $approval_stats['on_track']; ?></p>
                            </div>
                            <div class="bg-green-200 p-3 rounded-full">
                                <i class="fas fa-check-circle text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-yellow-600">Warning</p>
                                <p class="text-2xl font-bold text-yellow-700"><?php echo $approval_stats['warning']; ?></p>
                            </div>
                            <div class="bg-yellow-200 p-3 rounded-full">
                                <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-red-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-red-600">Overdue</p>
                                <p class="text-2xl font-bold text-red-700"><?php echo $approval_stats['overdue']; ?></p>
                            </div>
                            <div class="bg-red-200 p-3 rounded-full">
                                <i class="fas fa-clock text-red-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Approvals Details -->
                <?php if (!empty($pending_approvals)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Step</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waiting For</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SLA Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pending_approvals as $approval): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($approval['entity_description']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo ucfirst($approval['entity_type']); ?> #<?php echo $approval['entity_id']; ?>
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <?php echo ucfirst($approval['complexity']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        Step <?php echo $approval['step_number']; ?>: <?php echo htmlspecialchars($approval['step_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Started <?php echo timeAgo($approval['initiated_at']); ?>
                                    </div>
                                </td>
                                
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <?php if ($approval['delegated_to']): ?>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($approval['delegate_first'] . ' ' . $approval['delegate_last']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-exchange-alt mr-1"></i>Delegated from <?php echo htmlspecialchars($approval['assigned_first'] . ' ' . $approval['assigned_last']); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($approval['assigned_first'] . ' ' . $approval['assigned_last']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($approval['assigned_role']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-1 mr-2">
                                            <div class="sla-progress">
                                                <div class="sla-progress-bar sla-<?php echo $approval['sla_status']; ?>" 
                                                     style="width: <?php echo min(100, $approval['sla_percentage']); ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="text-sm">
                                            <span class="font-medium 
                                                <?php echo $approval['sla_status'] === 'overdue' ? 'text-red-600' : 
                                                          ($approval['sla_status'] === 'warning' ? 'text-yellow-600' : 'text-green-600'); ?>">
                                                <?php echo $approval['hours_elapsed']; ?>h
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo ucfirst(str_replace('_', ' ', $approval['sla_status'])); ?>
                                    </div>
                                </td>
                                
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M j, g:i A', strtotime($approval['due_date'])); ?>
                                </td>
                                
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="mailto:<?php echo htmlspecialchars($approval['assigned_email']); ?>?subject=Approval Required: <?php echo urlencode($approval['step_name']); ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Send Email Reminder">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                        <?php if ($approval['sla_status'] === 'overdue'): ?>
                                        <button onclick="escalateApproval(<?php echo $approval['step_id']; ?>)" 
                                                class="text-red-600 hover:text-red-900" title="Escalate Approval">
                                            <i class="fas fa-level-up-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                        <a href="<?php echo $approval['entity_type'] === 'offer' ? 'offers/view.php?id=' . $approval['entity_id'] : 'interviews/view.php?id=' . $approval['entity_id']; ?>" 
                                           class="text-gray-600 hover:text-gray-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">No pending approvals. All workflows are up to date!</p>
                </div>
                <?php endif; ?>
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
                    <a href="offers/enhanced_approvals.php" class="bg-indigo-500 hover:bg-indigo-600 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-tasks text-2xl mb-2"></i>
                        <p class="text-sm">Approvals</p>
                    </a>
                    <a href="admin/delegation_management.php" class="bg-blue-600 hover:bg-blue-700 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-exchange-alt text-2xl mb-2"></i>
                        <p class="text-sm">Delegations</p>
                    </a>
                    <a href="interviews/panel_management.php" class="bg-purple-600 hover:bg-purple-700 text-white p-4 rounded-lg text-center transition duration-200">
                        <i class="fas fa-users text-2xl mb-2"></i>
                        <p class="text-sm">Panel Interviews</p>
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
        // Escalate approval function
        function escalateApproval(stepId) {
            if (confirm('Are you sure you want to escalate this approval? This will notify the next level approver.')) {
                fetch('includes/escalate_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        step_id: stepId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Approval escalated successfully.');
                        location.reload();
                    } else {
                        alert('Error escalating approval: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error escalating approval.');
                });
            }
        }

        // Auto-refresh approval status every 30 seconds
        setInterval(function() {
            // Only refresh the approval section
            fetch('includes/refresh_approval_status.php')
                .then(response => response.text())
                .then(html => {
                    // Update approval status section if needed
                    const approvalSection = document.querySelector('.approval-status-section');
                    if (approvalSection) {
                        approvalSection.innerHTML = html;
                    }
                })
                .catch(error => console.error('Error refreshing approval status:', error));
        }, 30000);

        // Pipeline Chart
        const ctx = document.getElementById('pipelineChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['New', 'Shortlisted', 'Interviewing', 'Offered', 'Hired'],
                datasets: [{
                    data: [<?php echo implode(',', $stats['pipeline_data']); ?>],
                    backgroundColor: [
                        '#3B82F6',
                        '#10B981',
                        '#F59E0B',
                        '#EF4444',
                        '#8B5CF6'
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
                    }
                }
            }
        });
    </script>
</body>
</html> 