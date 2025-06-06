<?php
/**
 * Enhanced Approval System Verification Script
 * Checks database tables and system readiness
 */

require_once 'config/config.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h1>Enhanced Approval System Verification</h1>\n";

// Check for required tables
$required_tables = [
    'approval_workflows',
    'approval_instances', 
    'approval_steps',
    'committee_votes',
    'interview_panels',
    'interview_panel_members',
    'panel_interview_feedback',
    'approval_delegations',
    'approval_sla_tracking',
    'role_transitions'
];

echo "<h2>Database Table Verification</h2>\n";
$missing_tables = [];

foreach ($required_tables as $table) {
    try {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✅ Table '$table' exists\n<br>";
        } else {
            echo "❌ Table '$table' is MISSING\n<br>";
            $missing_tables[] = $table;
        }
    } catch (Exception $e) {
        echo "❌ Error checking table '$table': " . $e->getMessage() . "\n<br>";
        $missing_tables[] = $table;
    }
}

// Check if approval engine class loads
echo "<h2>System Component Verification</h2>\n";

try {
    require_once 'includes/approval_engine.php';
    $approval_engine = new ApprovalEngine($conn);
    echo "✅ ApprovalEngine class loaded successfully\n<br>";
} catch (Exception $e) {
    echo "❌ Error loading ApprovalEngine: " . $e->getMessage() . "\n<br>";
}

// Check for sample workflows
echo "<h2>Workflow Configuration Check</h2>\n";

try {
    $workflows = $conn->query("SELECT * FROM approval_workflows WHERE is_active = TRUE")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($workflows) > 0) {
        echo "✅ Found " . count($workflows) . " active approval workflows:\n<br>";
        foreach ($workflows as $workflow) {
            echo "&nbsp;&nbsp;- " . htmlspecialchars($workflow['workflow_name']) . " (" . $workflow['entity_type'] . ")\n<br>";
        }
    } else {
        echo "⚠️ No active approval workflows found. You may need to set up initial workflows.\n<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking workflows: " . $e->getMessage() . "\n<br>";
}

// Check for users with approval roles
echo "<h2>User Permission Check</h2>\n";

try {
    $approval_roles = ['hiring_manager', 'department_head', 'director', 'hr_director', 'admin'];
    $roles_found = [];
    
    foreach ($approval_roles as $role) {
        $users = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND is_active = TRUE");
        $users->execute([$role]);
        $count = $users->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            echo "✅ Found $count active users with '$role' role\n<br>";
            $roles_found[] = $role;
        }
    }
    
    if (empty($roles_found)) {
        echo "⚠️ No users found with approval roles. You may need to assign appropriate roles.\n<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking user roles: " . $e->getMessage() . "\n<br>";
}

// Check file accessibility
echo "<h2>File Accessibility Check</h2>\n";

$required_files = [
    'includes/approval_engine.php',
    'offers/enhanced_approvals.php',
    'interviews/panel_management.php',
    'admin/delegation_management.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ File '$file' exists and is accessible\n<br>";
    } else {
        echo "❌ File '$file' is MISSING\n<br>";
    }
}

// Summary
echo "<h2>System Status Summary</h2>\n";

if (empty($missing_tables)) {
    echo "✅ <strong>Database Schema:</strong> All required tables are present\n<br>";
} else {
    echo "❌ <strong>Database Schema:</strong> " . count($missing_tables) . " tables are missing\n<br>";
    echo "<strong>Missing tables:</strong> " . implode(', ', $missing_tables) . "\n<br>";
    echo "<br><strong>To fix:</strong> Run the enhanced_approval_schema.sql file\n<br>";
}

echo "<br><strong>System Status:</strong> ";
if (empty($missing_tables)) {
    echo "🚀 <span style='color: green;'>READY TO USE!</span>\n<br>";
    echo "<br><strong>Next Steps:</strong>\n<br>";
    echo "1. Visit /offers/enhanced_approvals.php to manage offer approvals\n<br>";
    echo "2. Visit /interviews/panel_management.php to create panel interviews\n<br>";
    echo "3. Visit /admin/delegation_management.php to set up approval delegations\n<br>";
} else {
    echo "⚠️ <span style='color: orange;'>REQUIRES SETUP</span>\n<br>";
    echo "<br><strong>Setup Required:</strong> Apply database schema first\n<br>";
}

echo "\n<br><hr><br>";
echo "<strong>Enhanced Approval System Implementation Complete!</strong>\n<br>";
echo "For detailed documentation, see: ENHANCED_APPROVAL_SYSTEM.md\n<br>";
?> 