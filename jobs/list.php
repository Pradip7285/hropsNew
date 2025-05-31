<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$department = $_GET['department'] ?? '';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(j.title LIKE ? OR j.description LIKE ? OR j.location LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status)) {
    $where_conditions[] = "j.status = ?";
    $params[] = $status;
}

if (!empty($department)) {
    $where_conditions[] = "j.department = ?";
    $params[] = $department;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$db = new Database();
$conn = $db->getConnection();

// Get total count
$count_query = "
    SELECT COUNT(*) as total 
    FROM job_postings j
    JOIN users u ON j.created_by = u.id
    $where_clause
";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get jobs with application counts
$query = "
    SELECT j.*, 
           u.first_name as creator_first, u.last_name as creator_last,
           COUNT(c.id) as application_count,
           COUNT(CASE WHEN c.status = 'shortlisted' THEN 1 END) as shortlisted_count,
           COUNT(CASE WHEN c.status = 'hired' THEN 1 END) as hired_count
    FROM job_postings j
    JOIN users u ON j.created_by = u.id
    LEFT JOIN candidates c ON j.id = c.applied_for
    $where_clause
    GROUP BY j.id
    ORDER BY j.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$departments_stmt = $conn->query("SELECT DISTINCT department FROM job_postings ORDER BY department");
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Postings - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Job Postings</h1>
                <p class="text-gray-600">Manage open positions and track applications</p>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Job title, description, location..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="closed" <?php echo $status == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-briefcase text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Active Jobs</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo count(array_filter($jobs, fn($j) => $j['status'] == 'active')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Applications</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo array_sum(array_column($jobs, 'application_count')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-star text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Shortlisted</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo array_sum(array_column($jobs, 'shortlisted_count')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-2 rounded-full mr-3">
                            <i class="fas fa-user-check text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Hired</p>
                            <p class="text-xl font-bold text-gray-800">
                                <?php echo array_sum(array_column($jobs, 'hired_count')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2">
                    <a href="add.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Post New Job
                    </a>
                    <a href="templates.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-file-alt mr-2"></i>Job Templates
                    </a>
                    <button onclick="bulkAction()" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-tasks mr-2"></i>Bulk Actions
                    </button>
                </div>
                
                <div class="text-sm text-gray-600">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> jobs
                </div>
            </div>

            <!-- Jobs Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left">
                                <input type="checkbox" id="selectAll" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applications</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($jobs)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-briefcase text-4xl mb-4"></i>
                                <p>No job postings found</p>
                                <a href="add.php" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-plus mr-2"></i>Post Your First Job
                                </a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($jobs as $job): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" name="selected_jobs[]" value="<?php echo $job['id']; ?>" 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-blue-500 text-white w-12 h-12 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <div class="text-sm font-medium text-gray-900">
                                            <a href="view.php?id=<?php echo $job['id']; ?>" class="hover:text-blue-600">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </div>
                                        <div class="text-sm text-gray-500 mt-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-clock mr-1"></i><?php echo ucfirst(str_replace('_', ' ', $job['employment_type'])); ?>
                                        </div>
                                        <?php if ($job['salary_range']): ?>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-dollar-sign mr-1"></i><?php echo htmlspecialchars($job['salary_range']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <?php echo htmlspecialchars($job['department']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <div class="flex items-center space-x-4">
                                        <div class="text-center">
                                            <div class="text-lg font-semibold text-blue-600"><?php echo $job['application_count']; ?></div>
                                            <div class="text-xs text-gray-500">Total</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-lg font-semibold text-yellow-600"><?php echo $job['shortlisted_count']; ?></div>
                                            <div class="text-xs text-gray-500">Shortlisted</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-lg font-semibold text-green-600"><?php echo $job['hired_count']; ?></div>
                                            <div class="text-xs text-gray-500">Hired</div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'closed' => 'bg-red-100 text-red-800',
                                    'draft' => 'bg-yellow-100 text-yellow-800'
                                ];
                                $color_class = $status_colors[$job['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $color_class; ?>">
                                    <?php echo ucfirst($job['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div><?php echo date('M j, Y', strtotime($job['created_at'])); ?></div>
                                <div class="text-xs text-gray-400">
                                    by <?php echo htmlspecialchars($job['creator_first'] . ' ' . $job['creator_last']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="view.php?id=<?php echo $job['id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $job['id']; ?>" class="text-green-600 hover:text-green-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="../candidates/list.php?job_id=<?php echo $job['id']; ?>" class="text-purple-600 hover:text-purple-900" title="View Applications">
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <button onclick="toggleJobStatus(<?php echo $job['id']; ?>, '<?php echo $job['status']; ?>')" 
                                            class="text-orange-600 hover:text-orange-900" title="Change Status">
                                        <i class="fas fa-toggle-<?php echo $job['status'] == 'active' ? 'on' : 'off'; ?>"></i>
                                    </button>
                                    <button onclick="duplicateJob(<?php echo $job['id']; ?>)" class="text-indigo-600 hover:text-indigo-900" title="Duplicate">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button onclick="deleteJob(<?php echo $job['id']; ?>)" class="text-red-600 hover:text-red-900" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6 flex justify-center">
                <nav class="flex space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&department=<?php echo urlencode($department); ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&department=<?php echo urlencode($department); ?>" 
                       class="px-3 py-2 border rounded-lg <?php echo $i == $page ? 'bg-blue-500 text-white border-blue-500' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&department=<?php echo urlencode($department); ?>" 
                       class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_jobs[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

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
            if (confirm('Are you sure you want to delete this job? This action cannot be undone.')) {
                window.location.href = `delete.php?id=${jobId}`;
            }
        }

        function bulkAction() {
            const selected = document.querySelectorAll('input[name="selected_jobs[]"]:checked');
            if (selected.length === 0) {
                alert('Please select at least one job posting.');
                return;
            }
            
            const action = prompt('Enter action (close/activate/delete):');
            if (!action) return;
            
            const jobIds = Array.from(selected).map(cb => cb.value);
            // In a real implementation, this would make an AJAX call or form submission
            alert(`Bulk action "${action}" would be performed on ${jobIds.length} jobs.`);
        }
    </script>
</body>
</html> 