<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$interview_id = $_GET['id'] ?? null;
$new_status = $_GET['status'] ?? null;
$redirect = $_GET['redirect'] ?? 'list';
$reason = $_GET['reason'] ?? null;

if (!$interview_id || !$new_status) {
    header('Location: list.php');
    exit();
}

// Validate status
$valid_statuses = ['scheduled', 'completed', 'cancelled', 'rescheduled'];
if (!in_array($new_status, $valid_statuses)) {
    header('Location: list.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Get interview details for permission check and logging
    $interview_stmt = $conn->prepare("
        SELECT i.*, c.first_name, c.last_name 
        FROM interviews i 
        JOIN candidates c ON i.candidate_id = c.id 
        WHERE i.id = ?
    ");
    $interview_stmt->execute([$interview_id]);
    $interview = $interview_stmt->fetch();
    
    if (!$interview) {
        header('Location: list.php');
        exit();
    }
    
    // Check permissions
    if (!hasPermission('hr_recruiter') && $_SESSION['user_id'] != $interview['interviewer_id']) {
        header('Location: ../unauthorized.php');
        exit();
    }
    
    // Prepare update query
    $update_query = "UPDATE interviews SET status = ?, updated_at = CURRENT_TIMESTAMP";
    $update_params = [$new_status];
    
    // Add reason if provided (for cancellations)
    if ($reason && $new_status == 'cancelled') {
        $update_query .= ", notes = CONCAT(COALESCE(notes, ''), '\n\nCancellation reason: ', ?)";
        $update_params[] = $reason;
    }
    
    $update_query .= " WHERE id = ?";
    $update_params[] = $interview_id;
    
    // Update interview status
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->execute($update_params);
    
    // Update candidate status if interview is completed
    if ($new_status == 'completed') {
        // Check if there are more interviews scheduled for this candidate
        $remaining_interviews = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM interviews 
            WHERE candidate_id = ? AND status = 'scheduled' AND id != ?
        ");
        $remaining_interviews->execute([$interview['candidate_id'], $interview_id]);
        
        if ($remaining_interviews->fetch()['count'] == 0) {
            // No more interviews, update candidate status based on feedback
            $feedback_check = $conn->prepare("
                SELECT recommendation 
                FROM interview_feedback 
                WHERE interview_id = ?
            ");
            $feedback_check->execute([$interview_id]);
            $feedback = $feedback_check->fetch();
            
            if ($feedback) {
                $candidate_status = ($feedback['recommendation'] == 'strong_hire' || $feedback['recommendation'] == 'hire') 
                    ? 'offered' : 'rejected';
                
                $update_candidate = $conn->prepare("UPDATE candidates SET status = ? WHERE id = ?");
                $update_candidate->execute([$candidate_status, $interview['candidate_id']]);
            }
        }
    } elseif ($new_status == 'cancelled') {
        // If interview is cancelled, revert candidate status if appropriate
        $other_interviews = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM interviews 
            WHERE candidate_id = ? AND status IN ('scheduled', 'completed') AND id != ?
        ");
        $other_interviews->execute([$interview['candidate_id'], $interview_id]);
        
        if ($other_interviews->fetch()['count'] == 0) {
            $update_candidate = $conn->prepare("UPDATE candidates SET status = 'shortlisted' WHERE id = ?");
            $update_candidate->execute([$interview['candidate_id']]);
        }
    }
    
    // Log activity
    $status_action_map = [
        'completed' => 'interview_completed',
        'cancelled' => 'interview_cancelled',
        'rescheduled' => 'interview_rescheduled'
    ];
    
    if (isset($status_action_map[$new_status])) {
        $activity_description = ucfirst($new_status) . " interview for {$interview['first_name']} {$interview['last_name']}";
        if ($reason && $new_status == 'cancelled') {
            $activity_description .= " (Reason: $reason)";
        }
        
        logActivity($conn, $_SESSION['user_id'], $status_action_map[$new_status], $activity_description);
    }
    
    // Set success message
    $_SESSION['success_message'] = 'Interview status updated successfully.';
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error updating interview status: ' . $e->getMessage();
}

// Redirect based on the redirect parameter
if ($redirect == 'view') {
    header('Location: view.php?id=' . $interview_id);
} else {
    header('Location: list.php');
}
exit();
?> 