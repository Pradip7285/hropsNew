<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Get all templates
$templates_stmt = $conn->query("
    SELECT jt.*, u.first_name, u.last_name 
    FROM job_templates jt
    JOIN users u ON jt.created_by = u.id
    ORDER BY jt.created_at DESC
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle template creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $template_data = trim($_POST['template_data']);
    
    if (empty($name)) {
        $error = 'Template name is required.';
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO job_templates (name, description, template_data, created_by, is_active) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$name, $description, $template_data, $_SESSION['user_id']]);
            $success = 'Template created successfully!';
        } catch (Exception $e) {
            $error = 'Error creating template: ' . $e->getMessage();
        }
    }
}

// Handle template toggle
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $template_id = $_GET['id'];
    $stmt = $conn->prepare("UPDATE job_templates SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$template_id]);
    header('Location: templates.php?success=' . urlencode('Template status updated'));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Templates - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Job Templates</h1>
                        <p class="text-gray-600">Create and manage job posting templates for faster job creation</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="showCreateModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>New Template
                        </button>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Jobs
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success || isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success ?: $_GET['success']; ?>
                </div>
            <?php endif; ?>

            <!-- Templates Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($templates)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-medium text-gray-500 mb-2">No Templates Yet</h3>
                    <p class="text-gray-400 mb-4">Create your first job template to speed up job posting</p>
                    <button onclick="showCreateModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create First Template
                    </button>
                </div>
                <?php else: ?>
                <?php foreach ($templates as $template): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($template['name']); ?></h3>
                            <?php if ($template['description']): ?>
                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($template['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($template['is_active']): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                            <?php else: ?>
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">Inactive</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="text-xs text-gray-500 mb-4">
                        Created by <?php echo htmlspecialchars($template['first_name'] . ' ' . $template['last_name']); ?>
                        on <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button onclick="useTemplate(<?php echo $template['id']; ?>)" 
                                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm transition duration-200">
                            <i class="fas fa-plus mr-1"></i>Use Template
                        </button>
                        <button onclick="viewTemplate(<?php echo $template['id']; ?>)" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded text-sm transition duration-200">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a href="templates.php?toggle=1&id=<?php echo $template['id']; ?>" 
                           class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded text-sm transition duration-200"
                           title="<?php echo $template['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                            <i class="fas fa-toggle-<?php echo $template['is_active'] ? 'on' : 'off'; ?>"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Template Modal -->
    <div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Create New Template</h3>
                    <button onclick="hideCreateModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template Name *</label>
                        <input type="text" name="name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g., Software Engineer Template">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" name="description"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Brief description of this template">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template Content</label>
                        <textarea name="template_data" rows="8"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Enter job description template, requirements, responsibilities, etc."></textarea>
                        <p class="mt-1 text-sm text-gray-500">This content will be used as a starting point for new job postings.</p>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i>Create Template
                        </button>
                        <button type="button" onclick="hideCreateModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showCreateModal() {
            document.getElementById('createModal').classList.remove('hidden');
        }

        function hideCreateModal() {
            document.getElementById('createModal').classList.add('hidden');
        }

        function useTemplate(templateId) {
            if (confirm('Create a new job posting using this template?')) {
                window.location.href = `add.php?template=${templateId}`;
            }
        }

        function viewTemplate(templateId) {
            // In a real implementation, this would show template details
            alert('Template details view would be implemented here.');
        }
    </script>
</body>
</html> 