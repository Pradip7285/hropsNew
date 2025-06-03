<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

$document_id = (int)($_GET['id'] ?? 0);

if (!$document_id) {
    http_response_code(404);
    die('Document not found.');
}

try {
    // Get document information
    $stmt = $conn->prepare("
        SELECT d.*, e.first_name, e.last_name, e.employee_id 
        FROM onboarding_documents d
        JOIN employees e ON d.employee_id = e.id
        WHERE d.id = ?
    ");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        die('Document not found.');
    }
    
    $file_path = $document['file_path'];
    
    // Check if file exists
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found on server.');
    }
    
    // Log the download activity
    logActivity(
        $_SESSION['user_id'],
        'document_downloaded',
        'document',
        $document_id,
        "Downloaded document: {$document['document_name']} for {$document['first_name']} {$document['last_name']}"
    );
    
    // Set headers for file download
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
    header('Content-Length: ' . $document['file_size']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output the file
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error downloading file: ' . $e->getMessage());
}
?> 