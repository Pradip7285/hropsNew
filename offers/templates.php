<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        $name = trim($_POST['name']);
        $content = trim($_POST['content']);
        $variables = trim($_POST['variables']);
        
        if (empty($name) || empty($content)) {
            $error = 'Template name and content are required.';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO offer_templates (name, content, variables, created_by) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$name, $content, $variables, $_SESSION['user_id']]);
                
                logActivity($_SESSION['user_id'], 'template_created', 'template', $conn->lastInsertId(), "Created offer template: $name");
                $success = 'Template created successfully.';
            } catch (Exception $e) {
                $error = 'Error creating template: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'update') {
        $template_id = $_POST['template_id'];
        $name = trim($_POST['name']);
        $content = trim($_POST['content']);
        $variables = trim($_POST['variables']);
        
        if (empty($name) || empty($content)) {
            $error = 'Template name and content are required.';
        } else {
            try {
                $stmt = $conn->prepare("
                    UPDATE offer_templates 
                    SET name = ?, content = ?, variables = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $content, $variables, $template_id]);
                
                logActivity($_SESSION['user_id'], 'template_updated', 'template', $template_id, "Updated offer template: $name");
                $success = 'Template updated successfully.';
            } catch (Exception $e) {
                $error = 'Error updating template: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'toggle_status') {
        $template_id = $_POST['template_id'];
        $is_active = $_POST['is_active'] ? 1 : 0;
        
        try {
            $stmt = $conn->prepare("UPDATE offer_templates SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $template_id]);
            
            $status = $is_active ? 'activated' : 'deactivated';
            logActivity($_SESSION['user_id'], 'template_status_changed', 'template', $template_id, "Template $status");
            $success = "Template $status successfully.";
        } catch (Exception $e) {
            $error = 'Error updating template status: ' . $e->getMessage();
        }
    } elseif ($action == 'delete') {
        $template_id = $_POST['template_id'];
        
        try {
            // Check if template is being used
            $usage_check = $conn->prepare("SELECT COUNT(*) as count FROM offers WHERE template_id = ?");
            $usage_check->execute([$template_id]);
            $usage_count = $usage_check->fetch()['count'];
            
            if ($usage_count > 0) {
                $error = 'Cannot delete template as it is being used in active offers.';
            } else {
                $stmt = $conn->prepare("DELETE FROM offer_templates WHERE id = ?");
                $stmt->execute([$template_id]);
                
                logActivity($_SESSION['user_id'], 'template_deleted', 'template', $template_id, "Deleted offer template");
                $success = 'Template deleted successfully.';
            }
        } catch (Exception $e) {
            $error = 'Error deleting template: ' . $e->getMessage();
        }
    }
}

// Get all templates
$templates_stmt = $conn->query("
    SELECT ot.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM offers WHERE template_id = ot.id) as usage_count
    FROM offer_templates ot
    LEFT JOIN users u ON ot.created_by = u.id
    ORDER BY ot.is_active DESC, ot.name ASC
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get template for editing if requested
$edit_template = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_stmt = $conn->prepare("SELECT * FROM offer_templates WHERE id = ?");
    $edit_stmt->execute([$edit_id]);
    $edit_template = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Templates - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Offer Templates</h1>
                        <p class="text-gray-600">Create and manage offer letter templates</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Offers
                        </a>
                        <button onclick="showCreateModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>New Template
                        </button>
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

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($templates as $template): ?>
                <div class="bg-white rounded-lg shadow-md p-6 <?php echo !$template['is_active'] ? 'opacity-75' : ''; ?>">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($template['name']); ?></h3>
                            <p class="text-sm text-gray-600">
                                Created by <?php echo htmlspecialchars($template['first_name'] . ' ' . $template['last_name']); ?>
                            </p>
                            <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($template['created_at'])); ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($template['is_active']): ?>
                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Active</span>
                            <?php else: ?>
                            <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="text-sm text-gray-700 line-clamp-3">
                            <?php echo htmlspecialchars(substr(strip_tags($template['content']), 0, 150)) . '...'; ?>
                        </div>
                    </div>

                    <?php if ($template['variables']): ?>
                    <div class="mb-4">
                        <p class="text-xs font-medium text-gray-700 mb-1">Variables:</p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (explode(',', $template['variables']) as $variable): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                {<?php echo trim($variable); ?>}
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between text-sm text-gray-600 mb-4">
                        <span><i class="fas fa-file-alt mr-1"></i><?php echo $template['usage_count']; ?> offers</span>
                        <span><i class="fas fa-clock mr-1"></i><?php echo timeAgo($template['updated_at']); ?></span>
                    </div>

                    <div class="flex space-x-2">
                        <button onclick="previewTemplate(<?php echo $template['id']; ?>)" 
                                class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded text-sm transition duration-200">
                            <i class="fas fa-eye mr-1"></i>Preview
                        </button>
                        <button onclick="editTemplate(<?php echo $template['id']; ?>)" 
                                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm transition duration-200">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <div class="relative">
                            <button onclick="toggleDropdown(<?php echo $template['id']; ?>)" 
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-2 rounded text-sm transition duration-200">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div id="dropdown-<?php echo $template['id']; ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                <div class="py-1">
                                    <button onclick="toggleStatus(<?php echo $template['id']; ?>, <?php echo $template['is_active'] ? 'false' : 'true'; ?>)"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-power-off mr-2"></i>
                                        <?php echo $template['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    <button onclick="duplicateTemplate(<?php echo $template['id']; ?>)"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-copy mr-2"></i>Duplicate
                                    </button>
                                    <?php if ($template['usage_count'] == 0): ?>
                                    <button onclick="deleteTemplate(<?php echo $template['id']; ?>)"
                                            class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fas fa-trash mr-2"></i>Delete
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($templates)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No templates yet</h3>
                    <p class="text-gray-500 mb-4">Create your first offer letter template to get started.</p>
                    <button onclick="showCreateModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create Template
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create/Edit Template Modal -->
    <div id="templateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Create New Template</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" id="templateForm">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="template_id" id="templateId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Template Name *</label>
                            <input type="text" name="name" id="templateName" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Available Variables</label>
                            <input type="text" name="variables" id="templateVariables"
                                   placeholder="candidate_name, job_title, salary, start_date, benefits"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Comma-separated list of variables that can be used in this template</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Template Content *</label>
                            <textarea name="content" id="templateContent" rows="15" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">Template Variables Help</h4>
                            <p class="text-sm text-blue-700 mb-2">Use these variables in your template content:</p>
                            <div class="grid grid-cols-2 gap-2 text-xs text-blue-600">
                                <span>• {candidate_name} - Full candidate name</span>
                                <span>• {job_title} - Position title</span>
                                <span>• {salary} - Salary amount</span>
                                <span>• {start_date} - Employment start date</span>
                                <span>• {benefits} - Benefits package</span>
                                <span>• {company_name} - Company name</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i><span id="submitText">Create Template</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Template Preview</h3>
                    <button onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="previewContent" class="bg-gray-50 p-6 rounded-lg max-h-96 overflow-y-auto"></div>
                <div class="flex justify-end mt-4">
                    <button onclick="closePreviewModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#templateContent',
            height: 400,
            menubar: false,
            plugins: 'advlist autolink lists link image charmap print preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
        });

        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Template';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitText').textContent = 'Create Template';
            document.getElementById('templateForm').reset();
            document.getElementById('templateModal').classList.remove('hidden');
        }

        function editTemplate(id) {
            // Fetch template data via AJAX and populate form
            fetch(`templates.php?ajax=get_template&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTitle').textContent = 'Edit Template';
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('templateId').value = id;
                    document.getElementById('templateName').value = data.name;
                    document.getElementById('templateVariables').value = data.variables;
                    document.getElementById('submitText').textContent = 'Update Template';
                    tinymce.get('templateContent').setContent(data.content);
                    document.getElementById('templateModal').classList.remove('hidden');
                });
        }

        function closeModal() {
            document.getElementById('templateModal').classList.add('hidden');
        }

        function previewTemplate(id) {
            fetch(`templates.php?ajax=preview&id=${id}`)
                .then(response => response.text())
                .then(content => {
                    document.getElementById('previewContent').innerHTML = content;
                    document.getElementById('previewModal').classList.remove('hidden');
                });
        }

        function closePreviewModal() {
            document.getElementById('previewModal').classList.add('hidden');
        }

        function toggleDropdown(id) {
            const dropdown = document.getElementById(`dropdown-${id}`);
            dropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
                if (el.id !== `dropdown-${id}`) {
                    el.classList.add('hidden');
                }
            });
        }

        function toggleStatus(id, isActive) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="template_id" value="${id}">
                <input type="hidden" name="is_active" value="${isActive}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function deleteTemplate(id) {
            if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="template_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[onclick*="toggleDropdown"]')) {
                document.querySelectorAll('[id^="dropdown-"]').forEach(el => {
                    el.classList.add('hidden');
                });
            }
        });
    </script>
</body>
</html>

<?php
// Handle AJAX requests
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'get_template' && isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT * FROM offer_templates WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode($template);
        exit;
    } elseif ($_GET['ajax'] === 'preview' && isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT content FROM offer_templates WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $template['content'];
        exit;
    }
}
?> 