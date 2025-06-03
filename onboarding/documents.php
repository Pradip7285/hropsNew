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

// Handle document actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload_document':
            $employee_id = (int)$_POST['employee_id'];
            $document_name = trim($_POST['document_name']);
            $document_type = $_POST['document_type'];
            $is_required = isset($_POST['is_required']);
            $due_date = $_POST['due_date'] ?: null;
            $notes = trim($_POST['notes']);
            
            if (empty($document_name)) {
                $error = 'Document name is required.';
            } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a file to upload.';
            } else {
                $file = $_FILES['document_file'];
                $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_ext, $allowed_types)) {
                    $error = 'File type not allowed. Please upload: ' . implode(', ', $allowed_types);
                } elseif ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                    $error = 'File size must be less than 10MB.';
                } else {
                    try {
                        // Create upload directory
                        $upload_dir = '../uploads/documents/' . $employee_id . '/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Generate unique filename
                        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                        $file_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            // Save to database
                            $stmt = $conn->prepare("
                                INSERT INTO onboarding_documents (
                                    employee_id, document_name, document_type, is_required, status,
                                    file_path, original_filename, file_size, mime_type, uploaded_at, due_date, notes
                                ) VALUES (?, ?, ?, ?, 'submitted', ?, ?, ?, ?, NOW(), ?, ?)
                            ");
                            
                            $stmt->execute([
                                $employee_id, $document_name, $document_type, $is_required,
                                $file_path, $file['name'], $file['size'], $file['type'], $due_date, $notes
                            ]);
                            
                            $success = "Document '$document_name' uploaded successfully.";
                        } else {
                            $error = 'Failed to upload file.';
                        }
                    } catch (Exception $e) {
                        $error = 'Error uploading document: ' . $e->getMessage();
                    }
                }
            }
            break;
            
        case 'review_document':
            $document_id = (int)$_POST['document_id'];
            $status = $_POST['status'];
            $rejection_reason = trim($_POST['rejection_reason']);
            $notes = trim($_POST['notes']);
            
            try {
                $stmt = $conn->prepare("
                    UPDATE onboarding_documents 
                    SET status = ?, reviewed_by = ?, reviewed_at = NOW(), 
                        rejection_reason = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$status, $_SESSION['user_id'], $rejection_reason, $notes, $document_id]);
                
                $success = "Document " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully.";
            } catch (Exception $e) {
                $error = 'Error reviewing document: ' . $e->getMessage();
            }
            break;
            
        case 'delete_document':
            $document_id = (int)$_POST['document_id'];
            try {
                // Get file path before deleting
                $file_stmt = $conn->prepare("SELECT file_path FROM onboarding_documents WHERE id = ?");
                $file_stmt->execute([$document_id]);
                $file_data = $file_stmt->fetch();
                
                if ($file_data && file_exists($file_data['file_path'])) {
                    unlink($file_data['file_path']);
                }
                
                $conn->prepare("DELETE FROM onboarding_documents WHERE id = ?")->execute([$document_id]);
                $success = "Document deleted successfully.";
            } catch (Exception $e) {
                $error = 'Error deleting document: ' . $e->getMessage();
            }
            break;
    }
}

// Get filter parameters
$employee_filter = $_GET['employee_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['document_type'] ?? '';

// Build query conditions
$where_conditions = ['1=1'];
$params = [];

if (!empty($employee_filter)) {
    $where_conditions[] = "d.employee_id = ?";
    $params[] = $employee_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "d.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "d.document_type = ?";
    $params[] = $type_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get documents with employee info
$documents_query = "
    SELECT d.*, 
           e.first_name, e.last_name, e.employee_id as emp_id, e.department,
           reviewer.first_name as reviewer_first, reviewer.last_name as reviewer_last,
           CASE 
               WHEN d.due_date IS NOT NULL AND d.due_date < CURDATE() AND d.status IN ('pending', 'submitted') THEN 1
               ELSE 0
           END as is_overdue
    FROM onboarding_documents d
    JOIN employees e ON d.employee_id = e.id
    LEFT JOIN users reviewer ON d.reviewed_by = reviewer.id
    WHERE $where_clause
    ORDER BY d.is_required DESC, d.due_date ASC, d.uploaded_at DESC
";

$documents_stmt = $conn->prepare($documents_query);
$documents_stmt->execute($params);
$documents = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for filter
$employees_stmt = $conn->query("
    SELECT id, first_name, last_name, employee_id 
    FROM employees 
    ORDER BY first_name, last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get document statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_documents,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status IN ('pending', 'submitted') THEN 1 ELSE 0 END) as overdue
    FROM onboarding_documents d
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Management - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Document Management</h1>
                        <p class="text-gray-600">Manage employee onboarding documents and approvals</p>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="openUploadModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-upload mr-2"></i>Upload Document
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

            <!-- Document Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-file-alt text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_documents']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-gray-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Pending</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['pending']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-paper-plane text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Submitted</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['submitted']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Approved</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['approved']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-times-circle text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Rejected</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['rejected']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-orange-100 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-orange-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Overdue</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['overdue']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
                        <select name="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="missing" <?php echo $status_filter == 'missing' ? 'selected' : ''; ?>>Missing</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                        <select name="document_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Types</option>
                            <option value="form" <?php echo $type_filter == 'form' ? 'selected' : ''; ?>>Form</option>
                            <option value="contract" <?php echo $type_filter == 'contract' ? 'selected' : ''; ?>>Contract</option>
                            <option value="policy" <?php echo $type_filter == 'policy' ? 'selected' : ''; ?>>Policy</option>
                            <option value="handbook" <?php echo $type_filter == 'handbook' ? 'selected' : ''; ?>>Handbook</option>
                            <option value="certificate" <?php echo $type_filter == 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                            <option value="id" <?php echo $type_filter == 'id' ? 'selected' : ''; ?>>ID Document</option>
                            <option value="tax" <?php echo $type_filter == 'tax' ? 'selected' : ''; ?>>Tax Form</option>
                            <option value="benefits" <?php echo $type_filter == 'benefits' ? 'selected' : ''; ?>>Benefits</option>
                            <option value="other" <?php echo $type_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="documents.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Documents List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-file-alt text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">No documents found</p>
                                    <p class="text-sm">Upload documents to start the approval process.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($documents as $document): ?>
                            <tr class="hover:bg-gray-50 <?php echo $document['is_overdue'] ? 'bg-red-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                            <i class="fas fa-file-alt text-blue-600"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($document['document_name']); ?>
                                                <?php if ($document['is_required']): ?>
                                                <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">Required</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($document['original_filename']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo formatFileSize($document['file_size']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($document['first_name'] . ' ' . $document['last_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($document['emp_id']); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($document['department']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs font-medium rounded-full">
                                        <?php echo ucfirst($document['document_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'pending' => 'bg-gray-100 text-gray-800',
                                        'submitted' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'missing' => 'bg-orange-100 text-orange-800'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_colors[$document['status']]; ?>">
                                        <?php echo ucfirst($document['status']); ?>
                                    </span>
                                    <?php if ($document['is_overdue']): ?>
                                    <div class="text-xs text-red-600 mt-1">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>Overdue
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($document['due_date']): ?>
                                        <?php echo date('M j, Y', strtotime($document['due_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">No due date</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($document['uploaded_at']): ?>
                                        <?php echo date('M j, Y g:i A', strtotime($document['uploaded_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <?php if ($document['file_path'] && file_exists($document['file_path'])): ?>
                                        <a href="download_document.php?id=<?php echo $document['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($document['status'] == 'submitted'): ?>
                                        <button onclick="reviewDocument(<?php echo $document['id']; ?>)" 
                                                class="text-green-600 hover:text-green-900" title="Review">
                                            <i class="fas fa-gavel"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button onclick="viewDocumentDetails(<?php echo $document['id']; ?>)" 
                                                class="text-purple-600 hover:text-purple-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <button onclick="deleteDocument(<?php echo $document['id']; ?>)" 
                                                class="text-red-600 hover:text-red-900" title="Delete">
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
            </div>
        </main>
    </div>

    <!-- Upload Document Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Upload Document</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_document">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                        <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select employee...</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Name *</label>
                        <input type="text" name="document_name" required placeholder="I-9 Form, Employment Contract..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                        <select name="document_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="form">Form</option>
                            <option value="contract">Contract</option>
                            <option value="policy">Policy</option>
                            <option value="handbook">Handbook</option>
                            <option value="certificate">Certificate</option>
                            <option value="id">ID Document</option>
                            <option value="tax">Tax Form</option>
                            <option value="benefits">Benefits</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">File *</label>
                        <input type="file" name="document_file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Allowed: PDF, DOC, DOCX, JPG, PNG, GIF (Max 10MB)</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                        <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_required" class="mr-2">
                            <span class="text-sm text-gray-700">This is a required document</span>
                        </label>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional information..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeUploadModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Review Document Modal -->
    <div id="reviewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Review Document</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="review_document">
                    <input type="hidden" name="document_id" id="review_document_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Decision *</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" name="status" value="approved" required class="mr-2">
                                <span class="text-sm text-green-700">Approve</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="status" value="rejected" required class="mr-2">
                                <span class="text-sm text-red-700">Reject</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-4" id="rejection_reason_div" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason</label>
                        <textarea name="rejection_reason" rows="3" placeholder="Please specify why this document is being rejected..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Review Notes</label>
                        <textarea name="notes" rows="3" placeholder="Additional review notes..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3">
                        <button type="button" onclick="closeReviewModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200">
                            Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
        }

        function reviewDocument(documentId) {
            document.getElementById('review_document_id').value = documentId;
            document.getElementById('reviewModal').classList.remove('hidden');
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').classList.add('hidden');
        }

        function viewDocumentDetails(documentId) {
            window.location.href = 'document_details.php?id=' + documentId;
        }

        function deleteDocument(documentId) {
            if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_document">
                    <input type="hidden" name="document_id" value="${documentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Show/hide rejection reason field
        document.querySelectorAll('input[name="status"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const rejectionDiv = document.getElementById('rejection_reason_div');
                if (this.value === 'rejected') {
                    rejectionDiv.style.display = 'block';
                } else {
                    rejectionDiv.style.display = 'none';
                }
            });
        });

        // Close modals when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeUploadModal();
            }
        });

        document.getElementById('reviewModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeReviewModal();
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?> 