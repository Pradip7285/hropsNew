<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$job_id = $_GET['id'] ?? 0;
$new_status = $_GET['status'] ?? '';

// Validate inputs
if (!$job_id || !in_array($new_status, ['active', 'closed', 'draft'])) {
    header('Location: list.php?error=' . urlencode('Invalid job or status'));
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Get current job details
    $stmt = $conn->prepare("SELECT * FROM job_postings WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        header('Location: list.php?error=' . urlencode('Job not found'));
        exit;
    }
    
    // Update status
    $update_stmt = $conn->prepare("
        UPDATE job_postings 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $update_stmt->execute([$new_status, $job_id]);
    
    // Log activity
    logActivity(
        $_SESSION['user_id'], 
        'updated', 
        'job_posting', 
        $job_id,
        "Changed status from {$job['status']} to $new_status: {$job['title']}"
    );
    
    header('Location: view.php?id=' . $job_id . '&success=' . urlencode('Job status updated successfully'));
    
} catch (Exception $e) {
    header('Location: list.php?error=' . urlencode('Error updating job status: ' . $e->getMessage()));
}
?> 