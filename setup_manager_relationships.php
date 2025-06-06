<?php
require_once 'config/config.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== HR Employee Lifecycle Management - Manager Relationships Status ===\n\n";

// Check current employee data
try {
    $query = "SELECT e.id, e.employee_id, e.first_name, e.last_name, e.department, e.position, e.manager_id, m.first_name as manager_first, m.last_name as manager_last, m.username as manager_username FROM employees e LEFT JOIN users m ON e.manager_id = m.id ORDER BY e.employee_id";
    
    $stmt = $conn->query($query);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "CURRENT EMPLOYEES:\n";
    foreach ($employees as $emp) {
        $manager_info = $emp['manager_first'] ? $emp['manager_first'] . ' ' . $emp['manager_last'] : 'NO MANAGER';
        echo "• {$emp['employee_id']}: {$emp['first_name']} {$emp['last_name']} ({$emp['department']}) - Manager: $manager_info\n";
    }
    
    // Count employees with/without managers
    $with_manager = count(array_filter($employees, function($e) { return $e['manager_id'] !== null; }));
    $without_manager = count($employees) - $with_manager;
    
    echo "\nSUMMARY: " . count($employees) . " total employees\n";
    echo "- With managers: $with_manager\n";
    echo "- Without managers: $without_manager\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Show available managers
echo "AVAILABLE MANAGERS:\n";
try {
    $query = "SELECT id, username, first_name, last_name, role FROM users WHERE role IN ('admin', 'hr_recruiter', 'hiring_manager') AND is_active = 1";
    $stmt = $conn->query($query);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($managers as $manager) {
        echo "• {$manager['first_name']} {$manager['last_name']} ({$manager['username']}) - {$manager['role']}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "=== COMPLETE ===\n";
?> 