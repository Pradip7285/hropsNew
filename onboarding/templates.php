<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Handle template actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_template':
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $department = trim($_POST['department']);
            $position_level = $_POST['position_level'];
            $duration_days = (int)$_POST['duration_days'];
            
            if (empty($name)) {
                $error = 'Template name is required.';
            } else {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO onboarding_templates (name, description, department, position_level, duration_days, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $department, $position_level, $duration_days, $_SESSION['user_id']]);
                    $success = "Template '$name' created successfully.";
                } catch (Exception $e) {
                    $error = 'Error creating template: ' . $e->getMessage();
                }
            }
            break;
            
        case 'add_task':
            $template_id = (int)$_POST['template_id'];
            $task_name = trim($_POST['task_name']);
            $description = trim($_POST['task_description']);
            $category = $_POST['category'];
            $priority = $_POST['priority'];
            $due_days = (int)$_POST['due_days'];
            $estimated_hours = (float)$_POST['estimated_hours'];
            $assignee_role = $_POST['assignee_role'];
            $is_required = isset($_POST['is_required']);
            $instructions = trim($_POST['instructions']);
            
            if (empty($task_name)) {
                $error = 'Task name is required.';
            } else {
                try {
                    // Get next sort order
                    $sort_stmt = $conn->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_sort FROM onboarding_template_tasks WHERE template_id = ?");
                    $sort_stmt->execute([$template_id]);
                    $sort_order = $sort_stmt->fetch()['next_sort'];
                    
                    $stmt = $conn->prepare("
                        INSERT INTO onboarding_template_tasks (
                            template_id, task_name, description, category, priority, due_days,
                            estimated_hours, assignee_role, is_required, sort_order, instructions
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $template_id, $task_name, $description, $category, $priority, $due_days,
                        $estimated_hours, $assignee_role, $is_required, $sort_order, $instructions
                    ]);
                    $success = "Task '$task_name' added to template.";
                } catch (Exception $e) {
                    $error = 'Error adding task: ' . $e->getMessage();
                }
            }
            break;
            
        case 'delete_template':
            $template_id = (int)$_POST['template_id'];
            try {
                $conn->prepare("DELETE FROM onboarding_templates WHERE id = ?")->execute([$template_id]);
                $success = "Template deleted successfully.";
            } catch (Exception $e) {
                $error = 'Error deleting template: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_template':
            $template_id = (int)$_POST['template_id'];
            $is_active = (int)$_POST['is_active'];
            try {
                $conn->prepare("UPDATE onboarding_templates SET is_active = ? WHERE id = ?")->execute([$is_active, $template_id]);
                $success = $is_active ? "Template activated." : "Template deactivated.";
            } catch (Exception $e) {
                $error = 'Error updating template: ' . $e->getMessage();
            }
            break;
    }
}

// Get templates with task counts
$templates_stmt = $conn->query("
    SELECT t.*,
           u.first_name, u.last_name,
           COUNT(tt.id) as task_count
    FROM onboarding_templates t
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN onboarding_template_tasks tt ON t.id = tt.template_id
    GROUP BY t.id
    ORDER BY t.is_active DESC, t.created_at DESC
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique departments
$dept_stmt = $conn->query("SELECT DISTINCT department FROM onboarding_templates WHERE department IS NOT NULL");
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding Templates - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Onboarding Templates</h1>
                        <p class="text-gray-600">Create and manage reusable onboarding task templates</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="openCreateTemplateModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create Template
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-users mr-2"></i>Back to Employees
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <!-- Template Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clipboard-list text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Templates</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo count($templates); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Active Templates</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo count(array_filter($templates, fn($t) => $t['is_active'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-2 rounded-full mr-3">
                            <i class="fas fa-building text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Departments</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo count($departments); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-tasks text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Tasks</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo array_sum(array_column($templates, 'task_count')); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($templates as $template): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden <?php echo $template['is_active'] ? '' : 'opacity-75'; ?>">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($template['name']); ?></h3>
                            <div class="flex items-center space-x-2">
                                <?php if ($template['is_active']): ?>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">Active</span>
                                <?php else: ?>
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs font-medium rounded-full">Inactive</span>
                                <?php endif; ?>
                                
                                <div class="relative">
                                    <button onclick="toggleDropdown(<?php echo $template['id']; ?>)" class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div id="dropdown-<?php echo $template['id']; ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50">
                                        <div class="py-1">
                                            <button onclick="viewTemplate(<?php echo $template['id']; ?>)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-eye mr-2"></i>View Tasks
                                            </button>
                                            <button onclick="addTaskToTemplate(<?php echo $template['id']; ?>)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-plus mr-2"></i>Add Task
                                            </button>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_template">
                                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                <input type="hidden" name="is_active" value="<?php echo $template['is_active'] ? 0 : 1; ?>">
                                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <i class="fas fa-<?php echo $template['is_active'] ? 'pause' : 'play'; ?> mr-2"></i>
                                                    <?php echo $template['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            <button onclick="deleteTemplate(<?php echo $template['id']; ?>)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash mr-2"></i>Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($template['description']); ?></p>
                        
                        <div class="space-y-2 mb-4">
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-building mr-2 w-4"></i>
                                <span><?php echo htmlspecialchars($template['department'] ?: 'All Departments'); ?></span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-star mr-2 w-4"></i>
                                <span><?php echo ucfirst($template['position_level']); ?> Level</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-calendar mr-2 w-4"></i>
                                <span><?php echo $template['duration_days']; ?> days</span>
                            </div>
                            <div class="flex items-center text-sm text-gray-500">
                                <i class="fas fa-tasks mr-2 w-4"></i>
                                <span><?php echo $template['task_count']; ?> tasks</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                            <div class="text-xs text-gray-500">
                                Created by <?php echo htmlspecialchars($template['first_name'] . ' ' . $template['last_name']); ?>
                            </div>
                            <button onclick="viewTemplate(<?php echo $template['id']; ?>)" class="text-blue-600 hover:text-blue-800 text-sm">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($templates)): ?>
                <div class="col-span-full bg-white rounded-lg shadow-md p-12 text-center">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">No templates found</h3>
                    <p class="text-gray-600 mb-4">Create your first onboarding template to get started.</p>
                    <button onclick="openCreateTemplateModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create Template
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Template Modal -->
    <div id="createTemplateModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Template</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_template">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template Name *</label>
                        <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                        <input type="text" name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Position Level</label>
                        <select name="position_level" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="entry">Entry Level</option>
                            <option value="mid">Mid Level</option>
                            <option value="senior">Senior Level</option>
                            <option value="executive">Executive</option>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Duration (Days)</label>
                        <input type="number" name="duration_days" value="30" min="1" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeCreateTemplateModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Create Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Task Modal -->
    <div id="addTaskModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Task to Template</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_task">
                    <input type="hidden" name="template_id" id="task_template_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Task Name *</label>
                        <input type="text" name="task_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="task_description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="documentation">Documentation</option>
                                <option value="equipment">Equipment</option>
                                <option value="training">Training</option>
                                <option value="orientation">Orientation</option>
                                <option value="compliance">Compliance</option>
                                <option value="social">Social</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Days</label>
                            <input type="number" name="due_days" value="1" min="0" max="365" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Est. Hours</label>
                            <input type="number" name="estimated_hours" value="1" step="0.5" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                        <select name="assignee_role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="employee">Employee</option>
                            <option value="hr">HR</option>
                            <option value="manager">Manager</option>
                            <option value="buddy">Buddy</option>
                            <option value="it">IT</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_required" checked class="mr-2">
                            <span class="text-sm text-gray-700">Required Task</span>
                        </label>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Instructions</label>
                        <textarea name="instructions" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeAddTaskModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Add Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateTemplateModal() {
            document.getElementById('createTemplateModal').classList.remove('hidden');
        }

        function closeCreateTemplateModal() {
            document.getElementById('createTemplateModal').classList.add('hidden');
        }

        function addTaskToTemplate(templateId) {
            document.getElementById('task_template_id').value = templateId;
            document.getElementById('addTaskModal').classList.remove('hidden');
            closeAllDropdowns();
        }

        function closeAddTaskModal() {
            document.getElementById('addTaskModal').classList.add('hidden');
        }

        function viewTemplate(templateId) {
            window.location.href = 'template_details.php?id=' + templateId;
        }

        function toggleDropdown(templateId) {
            closeAllDropdowns();
            document.getElementById('dropdown-' + templateId).classList.toggle('hidden');
        }

        function closeAllDropdowns() {
            document.querySelectorAll('[id^="dropdown-"]').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        }

        function deleteTemplate(templateId) {
            if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_template">
                    <input type="hidden" name="template_id" value="${templateId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.relative')) {
                closeAllDropdowns();
            }
        });

        // Close modals when clicking outside
        document.getElementById('createTemplateModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeCreateTemplateModal();
            }
        });

        document.getElementById('addTaskModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeAddTaskModal();
            }
        });
    </script>
</body>
</html> 