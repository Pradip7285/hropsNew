<?php
require_once 'config/config.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== Dashboard Approval Tracking Test ===\n\n";

// Test 1: Count pending approvals
$pending_count = $conn->query("
    SELECT COUNT(*) as count
    FROM approval_instances 
    WHERE overall_status = 'pending'
")->fetch()['count'];

echo "✅ Pending approval instances: $pending_count\n";

// Test 2: Get detailed pending approval info
$pending_details = $conn->query("
    SELECT 
        ai.id, ai.entity_type, ai.entity_id, ai.current_step,
        ap.step_name, ap.assigned_to,
        u.first_name, u.last_name
    FROM approval_instances ai
    JOIN approval_steps ap ON ai.id = ap.instance_id AND ap.step_number = ai.current_step
    LEFT JOIN users u ON ap.assigned_to = u.id
    WHERE ai.overall_status = 'pending'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

echo "\n✅ Pending approval details:\n";
foreach ($pending_details as $approval) {
    echo "  - Instance #{$approval['id']}: {$approval['entity_type']} #{$approval['entity_id']}\n";
    echo "    Step: {$approval['step_name']}\n";
    echo "    Assigned to: {$approval['first_name']} {$approval['last_name']}\n\n";
}

// Test 3: SLA status check
$sla_stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(ast.started_at)) / 3600 > ast.sla_target_hours THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(ast.started_at)) / 3600 > (ast.sla_target_hours * 0.8) 
             AND (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(ast.started_at)) / 3600 <= ast.sla_target_hours THEN 1 ELSE 0 END) as warning
    FROM approval_instances ai
    JOIN approval_steps ap ON ai.id = ap.instance_id AND ap.step_number = ai.current_step
    LEFT JOIN approval_sla_tracking ast ON ap.id = ast.approval_step_id
    WHERE ai.overall_status = 'pending'
")->fetch(PDO::FETCH_ASSOC);

echo "✅ SLA Status Summary:\n";
echo "  - Total active: {$sla_stats['total']}\n";
echo "  - Overdue: {$sla_stats['overdue']}\n";
echo "  - Warning: {$sla_stats['warning']}\n";
echo "  - On track: " . ($sla_stats['total'] - $sla_stats['overdue'] - $sla_stats['warning']) . "\n\n";

// Test 4: Check if dashboard query works
echo "✅ Testing dashboard query...\n";
$dashboard_query = "
    SELECT 
        ai.id as approval_instance_id,
        ai.entity_type,
        ai.entity_id,
        ap.step_name,
        u.first_name as assigned_first,
        u.last_name as assigned_last,
        ap.due_date
    FROM approval_instances ai
    JOIN approval_steps ap ON ai.id = ap.instance_id AND ap.status = 'pending'
    LEFT JOIN users u ON ap.assigned_to = u.id
    WHERE ai.overall_status = 'pending'
    AND ap.step_number = ai.current_step
    LIMIT 3
";

$dashboard_data = $conn->query($dashboard_query)->fetchAll(PDO::FETCH_ASSOC);

if (!empty($dashboard_data)) {
    echo "  ✅ Dashboard query successful - found " . count($dashboard_data) . " items\n";
    foreach ($dashboard_data as $item) {
        echo "    - {$item['entity_type']} #{$item['entity_id']}: {$item['step_name']}\n";
        echo "      Waiting for: {$item['assigned_first']} {$item['assigned_last']}\n";
        echo "      Due: {$item['due_date']}\n\n";
    }
} else {
    echo "  ℹ️  No pending approvals found (this is OK if no workflows are active)\n";
}

echo "=== Test Complete ===\n";
echo "Dashboard approval tracking should now show detailed information!\n";
?> 