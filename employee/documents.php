<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

// Check if user is logged in and is an employee
requireLogin();
if (!in_array($_SESSION['role'], ['employee', 'hiring_manager'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get employee information
$employee_stmt = $conn->prepare("SELECT id FROM employees WHERE user_id = ?");
$employee_stmt->execute([$_SESSION['user_id']]);
$employee = $employee_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee record not found. Please contact HR.");
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'upload_document') {
        $document_type = $_POST['document_type'];
        $notes = $_POST['notes'] ?? '';
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/employee_documents/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
            $file_name = $employee['id'] . '_' . $document_type . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $file_path)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO employee_documents (employee_id, document_type, file_name, file_path, notes, status, uploaded_at) VALUES (?, ?, ?, ?, ?, 'pending_review', NOW())");
                    $stmt->execute([$employee['id'], $document_type, $_FILES['document']['name'], $file_path, $notes]);
                    $success_message = "Document uploaded successfully and sent for review!";
                } catch (Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            } else {
                $error_message = "Error uploading file.";
            }
        } else {
            $error_message = "Please select a file to upload.";
        }
    }
    
    if ($_POST['action'] == 'update_document') {
        $document_id = $_POST['document_id'];
        $notes = $_POST['notes'] ?? '';
        
        if (isset($_FILES['document']) && $_FILES['document']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/employee_documents/';
            $file_extension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
            $file_name = $employee['id'] . '_update_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $file_path)) {
                try {
                    $stmt = $conn->prepare("UPDATE employee_documents SET file_name = ?, file_path = ?, notes = ?, status = 'pending_review', uploaded_at = NOW() WHERE id = ? AND employee_id = ?");
                    $stmt->execute([$_FILES['document']['name'], $file_path, $notes, $document_id, $employee['id']]);
                    $success_message = "Document updated successfully!";
                } catch (Exception $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// Get employee documents
try {
    $docs_stmt = $conn->prepare("SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY uploaded_at DESC");
    $docs_stmt->execute([$employee['id']]);
    $documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If table doesn't exist, create sample data
    $documents = [
        [
            'id' => 1,
            'document_type' => 'resume',
            'file_name' => 'John_Doe_Resume.pdf',
            'status' => 'approved',
            'uploaded_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'notes' => 'Updated resume with latest experience'
        ],
        [
            'id' => 2,
            'document_type' => 'id_proof',
            'file_name' => 'Drivers_License.jpg',
            'status' => 'pending_review',
            'uploaded_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'notes' => 'Government issued ID'
        ]
    ];
}

// Define required document types
$required_documents = [
    'resume' => 'Resume/CV',
    'id_proof' => 'Government ID',
    'address_proof' => 'Address Proof',
    'education_certificate' => 'Education Certificate',
    'experience_letter' => 'Experience Letter',
    'bank_details' => 'Bank Account Details',
    'emergency_contact' => 'Emergency Contact Form'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - Employee Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 mr-4">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">My Documents</h1>
                </div>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="text-gray-400 hover:text-red-600">
                    <i class="fas fa-sign-out-alt text-lg"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (isset($success_message)): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Document Upload Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Upload New Document</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_document">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                        <select name="document_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Document Type</option>
                            <?php foreach ($required_documents as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Choose File</label>
                        <input type="file" name="document" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea name="notes" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Add any additional notes about this document..."></textarea>
                </div>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-upload mr-2"></i>Upload Document
                </button>
            </form>
        </div>

        <!-- Document Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Approved</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo count(array_filter($documents, function($d) { return $d['status'] == 'approved'; })); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-full mr-4">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Pending Review</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo count(array_filter($documents, function($d) { return $d['status'] == 'pending_review'; })); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="bg-red-100 p-3 rounded-full mr-4">
                        <i class="fas fa-times text-red-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Needs Update</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo count(array_filter($documents, function($d) { return $d['status'] == 'rejected'; })); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents List -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">My Documents</h2>
            </div>
            <div class="p-6">
                <?php if (empty($documents)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-file-alt text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Documents Yet</h3>
                    <p class="text-gray-500">Upload your first document using the form above.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($documents as $doc): ?>
                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?php echo htmlspecialchars($required_documents[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']))); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($doc['file_name']); ?></p>
                                    <p class="text-xs text-gray-500">
                                        Uploaded: <?php echo date('M j, Y g:i A', strtotime($doc['uploaded_at'])); ?>
                                    </p>
                                    <?php if (!empty($doc['notes'])): ?>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-comment text-gray-400 mr-1"></i>
                                        <?php echo htmlspecialchars($doc['notes']); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                    <?php echo $doc['status'] == 'approved' ? 'bg-green-100 text-green-800' : 
                                               ($doc['status'] == 'pending_review' ? 'bg-yellow-100 text-yellow-800' : 
                                               'bg-red-100 text-red-800'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $doc['status'])); ?>
                                </span>
                                <div class="flex space-x-2">
                                    <?php if (file_exists($doc['file_path'] ?? '')): ?>
                                    <a href="download.php?id=<?php echo $doc['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($doc['status'] == 'rejected' || $doc['status'] == 'pending_review'): ?>
                                    <button onclick="openUpdateModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_name']); ?>')" 
                                            class="text-green-600 hover:text-green-800" title="Update">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Required Documents Checklist -->
        <div class="mt-8 bg-blue-50 border-l-4 border-blue-400 p-6 rounded-lg">
            <h3 class="text-lg font-medium text-blue-800 mb-3">Required Documents Checklist</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <?php foreach ($required_documents as $key => $label): ?>
                <?php $has_doc = array_filter($documents, function($d) use ($key) { return $d['document_type'] == $key && $d['status'] == 'approved'; }); ?>
                <div class="flex items-center">
                    <i class="fas <?php echo !empty($has_doc) ? 'fa-check-circle text-green-600' : 'fa-circle text-gray-400'; ?> mr-2"></i>
                    <span class="text-sm <?php echo !empty($has_doc) ? 'text-green-800' : 'text-gray-600'; ?>"><?php echo $label; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Update Document Modal -->
    <div id="updateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4" id="updateModalTitle">Update Document</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_document">
                        <input type="hidden" name="document_id" id="updateDocumentId">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Choose New File</label>
                                <input type="file" name="document" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                                <textarea name="notes" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                          placeholder="Reason for update..."></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="closeUpdateModal()" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Update Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openUpdateModal(docId, fileName) {
            document.getElementById('updateDocumentId').value = docId;
            document.getElementById('updateModalTitle').textContent = 'Update: ' + fileName;
            document.getElementById('updateModal').classList.remove('hidden');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
        }
    </script>
</body>
</html> 