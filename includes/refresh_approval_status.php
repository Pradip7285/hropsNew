<?php
/**
 * Refresh Approval Status AJAX Endpoint
 * Returns updated approval status HTML for dashboard auto-refresh
 */

require_once '../config/config.php';
require_once 'auth.php';
require_once 'approval_engine.php';

// Require authorized role
if (!in_array($_SESSION['role'], ['hr_recruiter', 'hiring_manager', 'admin', 'hr_director'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$db = new Database();
$conn = $db->getConnection();

// Get pending approval count
$pending_count = $conn->query("
    SELECT COUNT(*) as count
    FROM approval_instances ai
    JOIN approval_steps ap ON ai.id = ap.instance_id AND ap.status = 'pending'
    WHERE ai.overall_status = 'pending'
    AND ap.step_number = ai.current_step
")->fetch()['count'];

// Get SLA status counts
$sla_status = $conn->query("
    SELECT 
        SUM(CASE WHEN (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(ast.started_at)) / 3600 > ast.sla_target_hours THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(ast.started_at)) / 3600 > (ast.sla_target_hours * 0.8) 
                 AND (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(ast.started_at)) / 3600 <= ast.sla_target_hours THEN 1 ELSE 0 END) as warning,
        SUM(CASE WHEN (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(ast.started_at)) / 3600 <= (ast.sla_target_hours * 0.8) THEN 1 ELSE 0 END) as on_track
    FROM approval_instances ai
    JOIN approval_steps ap ON ai.id = ap.instance_id AND ap.status = 'pending'
    JOIN approval_sla_tracking ast ON ap.id = ast.approval_step_id
    WHERE ai.overall_status = 'pending'
    AND ap.step_number = ai.current_step
")->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');

echo json_encode([
    'total_pending' => intval($pending_count),
    'overdue' => intval($sla_status['overdue'] ?? 0),
    'warning' => intval($sla_status['warning'] ?? 0), 
    'on_track' => intval($sla_status['on_track'] ?? 0),
    'timestamp' => time()
]);
?> 