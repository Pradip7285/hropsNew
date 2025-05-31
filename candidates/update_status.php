<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$candidate_id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;

if (!$candidate_id || !$new_status) {
    header('Location: list.php');
    exit();
}

// Validate status
$valid_statuses = ['new', 'shortlisted', 'interviewing', 'offered', 'hired', 'rejected'];
if (!in_array($new_status, $valid_statuses)) {
    header('Location: list.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Get candidate info
    $candidate_stmt = $conn->prepare("SELECT first_name, last_name, status FROM candidates WHERE id = ?");
    $candidate_stmt->execute([$candidate_id]);
    $candidate = $candidate_stmt->fetch();
    
    if (!$candidate) {
        header('Location: list.php');
        exit();
    }
    
    // Update status
    $update_stmt = $conn->prepare("UPDATE candidates SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update_stmt->execute([$new_status, $candidate_id]);
    
    // Log activity
    $old_status = $candidate['status'];
    logActivity(
        $_SESSION['user_id'], 
        'updated', 
        'candidate', 
        $candidate_id,
        "Changed status from '{$old_status}' to '{$new_status}' for {$candidate['first_name']} {$candidate['last_name']}"
    );
    
    // Redirect back to view page
    header('Location: view.php?id=' . $candidate_id . '&msg=status_updated');
    exit();
    
} catch (Exception $e) {
    // Redirect with error
    header('Location: view.php?id=' . $candidate_id . '&error=' . urlencode($e->getMessage()));
    exit();
}
?> 