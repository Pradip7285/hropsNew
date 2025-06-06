<?php
require_once 'config/config.php';

$db = new Database();
$conn = $db->getConnection();

echo "Starting employee creation process...\n";

try {
    // Check if job postings exist
    $job_check = $conn->query("SELECT COUNT(*) as count FROM job_postings")->fetch();
    if ($job_check['count'] == 0) {
        echo "Creating default job posting...\n";
        $conn->exec("
            INSERT INTO job_postings (title, description, requirements, department, location, status, created_by) 
            VALUES ('General Position', 'Default position', 'Basic requirements', 'General', 'Office', 'active', 1)
        ");
    }
    
    $job_id = $conn->query("SELECT id FROM job_postings LIMIT 1")->fetch()['id'];
    echo "Using job ID: $job_id\n";

    // Simple employee data
    $employees = [
        ['john.doe', 'john.doe@hrops.com', 'John', 'Doe', 'employee', 'Engineering', 'EMP001', 'Software Engineer'],
        ['jane.smith', 'jane.smith@hrops.com', 'Jane', 'Smith', 'employee', 'Marketing', 'EMP002', 'Marketing Manager'],
        ['mike.johnson', 'mike.johnson@hrops.com', 'Mike', 'Johnson', 'employee', 'Sales', 'EMP003', 'Sales Rep'],
        ['sarah.wilson', 'sarah.wilson@hrops.com', 'Sarah', 'Wilson', 'hiring_manager', 'Engineering', 'EMP004', 'Engineering Manager'],
        ['david.brown', 'david.brown@hrops.com', 'David', 'Brown', 'employee', 'Finance', 'EMP005', 'Financial Analyst']
    ];

    $password = password_hash('password123', PASSWORD_DEFAULT);

    foreach ($employees as $emp) {
        list($username, $email, $first_name, $last_name, $role, $department, $emp_id, $position) = $emp;
        
        echo "Creating employee: $first_name $last_name...\n";
        
        // Check if user exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            echo "  User $username already exists, skipping.\n";
            continue;
        }

        // Create candidate
        $cand_stmt = $conn->prepare("INSERT INTO candidates (first_name, last_name, email, status, applied_for) VALUES (?, ?, ?, 'hired', ?)");
        $cand_stmt->execute([$first_name, $last_name, $email, $job_id]);
        $candidate_id = $conn->lastInsertId();

        // Create offer
        $offer_stmt = $conn->prepare("INSERT INTO offers (candidate_id, job_id, salary_offered, status, created_by) VALUES (?, ?, 70000, 'accepted', 1)");
        $offer_stmt->execute([$candidate_id, $job_id]);
        $offer_id = $conn->lastInsertId();

        // Create user
        $user_stmt = $conn->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, department, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $user_stmt->execute([$username, $email, $password, $first_name, $last_name, $role, $department]);
        $user_id = $conn->lastInsertId();

        // Create employee
        $emp_stmt = $conn->prepare("INSERT INTO employees (candidate_id, employee_id, user_id, offer_id, start_date, department, position, onboarding_status) VALUES (?, ?, ?, ?, '2023-01-15', ?, ?, 'completed')");
        $emp_stmt->execute([$candidate_id, $emp_id, $user_id, $offer_id, $department, $position]);

        echo "  ✅ Successfully created $first_name $last_name ($emp_id)\n";
    }

    echo "\n🎉 Employee creation complete!\n";
    echo "\nLogin credentials:\n";
    echo "Password for all: password123\n\n";
    
    // Show summary
    $result = $conn->query("
        SELECT e.employee_id, u.first_name, u.last_name, u.username, u.role, e.department 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        ORDER BY e.employee_id
    ");
    
    echo "Created employees:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['first_name']} {$row['last_name']} ({$row['employee_id']}) - {$row['role']} in {$row['department']}\n";
        echo "  Username: {$row['username']}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?> 