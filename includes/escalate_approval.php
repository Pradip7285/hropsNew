<?php
/**
 * Escalate Approval AJAX Endpoint
 * Allows HR to manually escalate overdue approvals
 */

require_once '../config/config.php';
require_once 'auth.php';
require_once 'approval_engine.php';

// Require HR role
if (!in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin', 'hr_director'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $step_id = intval($input['step_id'] ?? 0);
    
    if (!$step_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid step ID']);
        exit();
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    $approval_engine = new ApprovalEngine($conn);
    
    // Get step details
    $step = $conn->query("
        SELECT ap.*, ai.entity_type, ai.entity_id
        FROM approval_steps ap
        JOIN approval_instances ai ON ap.instance_id = ai.id
        WHERE ap.id = $step_id
    ")->fetch(PDO::FETCH_ASSOC);
    
    if (!$step) {
        echo json_encode(['success' => false, 'message' => 'Step not found']);
        exit();
    }
    
    if ($step['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Step is not pending']);
        exit();
    }
    
    // Find escalation target
    $escalation_target = $approval_engine->findEscalationTarget($step_id);
    
    if (!$escalation_target) {
        echo json_encode(['success' => false, 'message' => 'No escalation target found']);
        exit();
    }
    
    // Update step status to escalated
    $conn->exec("
        UPDATE approval_steps 
        SET status = 'escalated', 
            escalated_at = NOW(),
            escalated_by = {$_SESSION['user_id']}
        WHERE id = $step_id
    ");
    
    // Update SLA tracking
    $conn->exec("
        UPDATE approval_sla_tracking 
        SET escalation_triggered_at = NOW(),
            escalated_to = $escalation_target
        WHERE approval_step_id = $step_id
    ");
    
    // Send escalation notification
    $escalation_user = $conn->query("
        SELECT first_name, last_name, email 
        FROM users 
        WHERE id = $escalation_target
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($escalation_user) {
        // In a real system, you would send an email here
        // For now, just log the escalation
        logActivity($_SESSION['user_id'], 'approval_escalated', 'approval_steps', $step_id,
            "Escalated {$step['step_name']} to {$escalation_user['first_name']} {$escalation_user['last_name']}");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Approval escalated successfully',
        'escalated_to' => $escalation_user['first_name'] . ' ' . $escalation_user['last_name']
    ]);
    
} catch (Exception $e) {
    error_log("Escalation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 