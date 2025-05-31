<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$job_id = $_GET['id'] ?? 0;

if (!$job_id) {
    header('Location: list.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get job details with creator info
$stmt = $conn->prepare("
    SELECT j.*, 
           u.first_name as creator_first, u.last_name as creator_last,
           COUNT(c.id) as application_count,
           COUNT(CASE WHEN c.status = 'new' THEN 1 END) as new_applications,
           COUNT(CASE WHEN c.status = 'reviewed' THEN 1 END) as reviewed_applications,
           COUNT(CASE WHEN c.status = 'shortlisted' THEN 1 END) as shortlisted_count,
           COUNT(CASE WHEN c.status = 'interviewed' THEN 1 END) as interviewed_count,
           COUNT(CASE WHEN c.status = 'hired' THEN 1 END) as hired_count,
           COUNT(CASE WHEN c.status = 'rejected' THEN 1 END) as rejected_count
    FROM job_postings j
    JOIN users u ON j.created_by = u.id
    LEFT JOIN candidates c ON j.id = c.applied_for
    WHERE j.id = ?
    GROUP BY j.id
");
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: list.php');
    exit;
}

// Get recent applications
$applications_stmt = $conn->prepare("
    SELECT c.*, 
           CASE 
               WHEN c.applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'recent'
               WHEN c.applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'month'
               ELSE 'older'
           END as recency
    FROM candidates c 
    WHERE c.applied_for = ? 
    ORDER BY c.applied_at DESC 
    LIMIT 10
");
$applications_stmt->execute([$job_id]);
$recent_applications = $applications_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <!-- Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($job['title']); ?></h1>
                        <p class="text-gray-600"><?php echo htmlspecialchars($job['department'] . ' • ' . $job['location']); ?></p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="edit.php?id=<?php echo $job['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>Edit Job
                        </a>
                        <a href="../candidates/list.php?job_id=<?php echo $job['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-users mr-2"></i>View Applications
                        </a>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Jobs
                        </a>
                    </div>
                </div>
            </div>

            <!-- Status & Quick Info -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Status</p>
                            <?php
                            $status_colors = [
                                'active' => 'bg-green-100 text-green-800',
                                'closed' => 'bg-red-100 text-red-800',
                                'draft' => 'bg-yellow-100 text-yellow-800'
                            ];
                            $color_class = $status_colors[$job['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color_class; ?>">
                                <?php echo ucfirst($job['status']); ?>
                            </span>
                        </div>
                        <i class="fas fa-toggle-<?php echo $job['status'] == 'active' ? 'on' : 'off'; ?> text-2xl text-gray-400"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Applications</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $job['application_count']; ?></p>
                        </div>
                        <i class="fas fa-users text-2xl text-blue-400"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Shortlisted</p>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo $job['shortlisted_count']; ?></p>
                        </div>
                        <i class="fas fa-star text-2xl text-yellow-400"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Hired</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $job['hired_count']; ?></p>
                        </div>
                        <i class="fas fa-user-check text-2xl text-green-400"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Job Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Job Description -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-file-alt mr-2"></i>Job Description
                        </h3>
                        <div class="prose max-w-none">
                            <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($job['description']); ?></p>
                        </div>
                    </div>

                    <?php if ($job['responsibilities']): ?>
                    <!-- Key Responsibilities -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-tasks mr-2"></i>Key Responsibilities
                        </h3>
                        <div class="prose max-w-none">
                            <div class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($job['responsibilities']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($job['requirements']): ?>
                    <!-- Requirements -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-check-circle mr-2"></i>Requirements & Qualifications
                        </h3>
                        <div class="prose max-w-none">
                            <div class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($job['requirements']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($job['benefits']): ?>
                    <!-- Benefits -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-gift mr-2"></i>Benefits & Perks
                        </h3>
                        <div class="prose max-w-none">
                            <div class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($job['benefits']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Applications -->
                    <?php if (!empty($recent_applications)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                <i class="fas fa-clock mr-2"></i>Recent Applications
                            </h3>
                            <a href="../candidates/list.php?job_id=<?php echo $job['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                View All →
                            </a>
                        </div>
                        
                        <div class="space-y-3">
                            <?php foreach ($recent_applications as $application): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center text-sm">
                                        <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Applied <?php echo date('M j, Y', strtotime($application['applied_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <?php if ($application['ai_score']): ?>
                                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                        AI Score: <?php echo round($application['ai_score'] * 100); ?>%
                                    </span>
                                    <?php endif; ?>
                                    <a href="../candidates/view.php?id=<?php echo $application['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Job Details Sidebar -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Job Details</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Employment Type</label>
                                <p class="text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $job['employment_type'])); ?></p>
                            </div>
                            
                            <?php if ($job['salary_range']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Salary Range</label>
                                <p class="text-gray-900"><?php echo htmlspecialchars($job['salary_range']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($job['experience_level']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Experience Level</label>
                                <p class="text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $job['experience_level'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($job['education_level']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Education Level</label>
                                <p class="text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $job['education_level'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($job['application_deadline']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Application Deadline</label>
                                <p class="text-gray-900"><?php echo date('M j, Y', strtotime($job['application_deadline'])); ?></p>
                                <?php if (strtotime($job['application_deadline']) <= time()): ?>
                                <span class="text-xs text-red-600">Expired</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">Priority</label>
                                <p class="text-gray-900">
                                    <?php
                                    $priority_icons = [
                                        'normal' => 'fas fa-circle text-green-500',
                                        'high' => 'fas fa-exclamation-circle text-yellow-500',
                                        'urgent' => 'fas fa-exclamation-triangle text-red-500'
                                    ];
                                    $icon = $priority_icons[$job['priority']] ?? $priority_icons['normal'];
                                    ?>
                                    <i class="<?php echo $icon; ?> mr-2"></i>
                                    <?php echo ucfirst($job['priority']); ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="text-sm font-medium text-gray-500">Created By</label>
                                <p class="text-gray-900"><?php echo htmlspecialchars($job['creator_first'] . ' ' . $job['creator_last']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($job['created_at'])); ?></p>
                            </div>
                            
                            <?php if ($job['updated_at'] && $job['updated_at'] != $job['created_at']): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Last Updated</label>
                                <p class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($job['updated_at'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Skills Required -->
                    <?php if ($job['skills_required']): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Skills Required</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $skills = array_map('trim', explode(',', $job['skills_required']));
                            foreach ($skills as $skill):
                                if (!empty($skill)):
                            ?>
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                                <?php echo htmlspecialchars($skill); ?>
                            </span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Application Pipeline -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Application Pipeline</h3>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">New Applications</span>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                    <?php echo $job['new_applications']; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Reviewed</span>
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">
                                    <?php echo $job['reviewed_applications']; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Shortlisted</span>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">
                                    <?php echo $job['shortlisted_count']; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Interviewed</span>
                                <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">
                                    <?php echo $job['interviewed_count']; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Hired</span>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                    <?php echo $job['hired_count']; ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Rejected</span>
                                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">
                                    <?php echo $job['rejected_count']; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                        
                        <div class="space-y-2">
                            <button onclick="toggleJobStatus(<?php echo $job['id']; ?>, '<?php echo $job['status']; ?>')" 
                                    class="w-full bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition duration-200 text-sm">
                                <i class="fas fa-toggle-<?php echo $job['status'] == 'active' ? 'off' : 'on'; ?> mr-2"></i>
                                <?php echo $job['status'] == 'active' ? 'Close Job' : 'Activate Job'; ?>
                            </button>
                            
                            <button onclick="duplicateJob(<?php echo $job['id']; ?>)" 
                                    class="w-full bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg transition duration-200 text-sm">
                                <i class="fas fa-copy mr-2"></i>Duplicate Job
                            </button>
                            
                            <a href="../interviews/schedule.php?job_id=<?php echo $job['id']; ?>" 
                               class="block w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200 text-sm text-center">
                                <i class="fas fa-calendar-plus mr-2"></i>Schedule Interview
                            </a>
                            
                            <button onclick="deleteJob(<?php echo $job['id']; ?>)" 
                                    class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200 text-sm">
                                <i class="fas fa-trash mr-2"></i>Delete Job
                            </button>
                        </div>
                    </div>

                    <?php if ($job['notes']): ?>
                    <!-- Internal Notes -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Internal Notes</h3>
                        <div class="text-sm text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($job['notes']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleJobStatus(jobId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'closed' : 'active';
            if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'close'} this job?`)) {
                window.location.href = `update_status.php?id=${jobId}&status=${newStatus}`;
            }
        }

        function duplicateJob(jobId) {
            if (confirm('Create a copy of this job posting?')) {
                window.location.href = `duplicate.php?id=${jobId}`;
            }
        }

        function deleteJob(jobId) {
            if (confirm('Are you sure you want to delete this job? This action cannot be undone and will affect all associated applications.')) {
                window.location.href = `delete.php?id=${jobId}`;
            }
        }
    </script>
</body>
</html> 