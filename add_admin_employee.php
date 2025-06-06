<?php
require_once 'config/config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "Adding admin employee record...\n";
    
    // Check if admin already has an employee record
    $check = $conn->prepare("SELECT e.id FROM employees e JOIN users u ON e.user_id = u.id WHERE u.username = 'admin'");
    $check->execute();
    if ($check->fetch()) {
        echo "Admin already has an employee record.\n";
        exit;
    }
    
    // Get job posting ID
    $job_id = $conn->query("SELECT id FROM job_postings LIMIT 1")->fetch()['id'];
    
    // Create candidate for admin
    $cand_sql = "INSERT INTO candidates (first_name, last_name, email, status, applied_for) VALUES ('System', 'Administrator', 'admin@hrops.com', 'hired', ?)";
    $cand_stmt = $conn->prepare($cand_sql);
    $cand_stmt->execute([$job_id]);
    $candidate_id = $conn->lastInsertId();
    echo "Created candidate record: $candidate_id\n";
    
    // Create offer for admin
    $offer_sql = "INSERT INTO offers (candidate_id, job_id, salary_offered, status, created_by) VALUES (?, ?, 100000, 'accepted', 1)";
    $offer_stmt = $conn->prepare($offer_sql);
    $offer_stmt->execute([$candidate_id, $job_id]);
    $offer_id = $conn->lastInsertId();
    echo "Created offer record: $offer_id\n";
    
    // Create employee record for admin (user_id = 1)
    $emp_sql = "INSERT INTO employees (candidate_id, employee_id, user_id, offer_id, start_date, department, position, onboarding_status) VALUES (?, 'EMP001', 1, ?, '2022-01-01', 'Administration', 'System Administrator', 'completed')";
    $emp_stmt = $conn->prepare($emp_sql);
    $emp_stmt->execute([$candidate_id, $offer_id]);
    echo "Created employee record: EMP001\n";
    
    echo "\nSuccess! Admin user now has employee record EMP001\n";
    
    // Verify
    echo "\nVerifying all users with employee records:\n";
    $result = $conn->query("
        SELECT u.id, u.username, u.role, e.employee_id, e.department, e.position 
        FROM users u 
        LEFT JOIN employees e ON u.id = e.user_id 
        ORDER BY u.id
    ");
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $emp_info = $row['employee_id'] ? "({$row['employee_id']}) - {$row['position']} in {$row['department']}" : "(No employee record)";
        echo "- {$row['username']} ({$row['role']}) $emp_info\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 