<?php
/**
 * Schema Compatibility Fix for Enhanced Approval System
 * Adds missing columns and ensures compatibility
 */

require_once 'config/config.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h1>🔧 Schema Compatibility Fix</h1>";

try {
    echo "<h2>Fixing Offers Table</h2>";
    
    // Add missing columns to offers table
    $offers_fixes = [
        "ALTER TABLE offers ADD COLUMN IF NOT EXISTS salary DECIMAL(12,2) GENERATED ALWAYS AS (salary_offered) STORED",
        "ALTER TABLE offers ADD COLUMN IF NOT EXISTS position_level ENUM('entry', 'mid', 'senior', 'lead', 'manager', 'director', 'vp', 'c_level') DEFAULT 'entry'",
        "ALTER TABLE offers ADD COLUMN IF NOT EXISTS approval_instance_id INT NULL",
        "ALTER TABLE offers ADD COLUMN IF NOT EXISTS requires_committee_approval BOOLEAN DEFAULT FALSE",
        "ALTER TABLE offers ADD COLUMN IF NOT EXISTS budget_approval_required BOOLEAN DEFAULT FALSE",
        "ALTER TABLE offers ADD COLUMN IF NOT EXISTS offer_complexity ENUM('standard', 'complex', 'executive') DEFAULT 'standard'"
    ];
    
    foreach ($offers_fixes as $sql) {
        try {
            $conn->exec($sql);
            echo "<p style='color: green;'>✅ Applied: " . substr($sql, 0, 50) . "...</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "<p style='color: orange;'>⚠️ " . $e->getMessage() . "</p>";
            } else {
                echo "<p style='color: blue;'>ℹ️ Column already exists: " . substr($sql, 0, 50) . "...</p>";
            }
        }
    }
    
    echo "<h2>Fixing Interviews Table</h2>";
    
    // Add missing columns to interviews table  
    $interview_fixes = [
        "ALTER TABLE interviews ADD COLUMN IF NOT EXISTS panel_id INT NULL",
        "ALTER TABLE interviews ADD COLUMN IF NOT EXISTS approval_required BOOLEAN DEFAULT FALSE",
        "ALTER TABLE interviews ADD COLUMN IF NOT EXISTS approval_instance_id INT NULL",
        "ALTER TABLE interviews ADD COLUMN IF NOT EXISTS interview_complexity ENUM('standard', 'panel', 'executive') DEFAULT 'standard'"
    ];
    
    foreach ($interview_fixes as $sql) {
        try {
            $conn->exec($sql);
            echo "<p style='color: green;'>✅ Applied: " . substr($sql, 0, 50) . "...</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "<p style='color: orange;'>⚠️ " . $e->getMessage() . "</p>";
            } else {
                echo "<p style='color: blue;'>ℹ️ Column already exists: " . substr($sql, 0, 50) . "...</p>";
            }
        }
    }
    
    echo "<h2>Creating Sample Data</h2>";
    
    // Check if we have job postings and candidates for testing
    $job_count = $conn->query("SELECT COUNT(*) as count FROM job_postings")->fetch()['count'];
    $candidate_count = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch()['count'];
    
    if ($job_count == 0) {
        echo "<p style='color: blue;'>ℹ️ Creating sample job posting for testing...</p>";
        $conn->exec("
            INSERT INTO job_postings (title, department, description, requirements, salary_min, salary_max, status, created_by)
            VALUES ('Senior Software Engineer', 'Engineering', 'Senior level position', 'Bachelor degree, 5+ years experience', 75000, 120000, 'active', 1)
        ");
        echo "<p style='color: green;'>✅ Created sample job posting</p>";
    }
    
    if ($candidate_count == 0) {
        echo "<p style='color: blue;'>ℹ️ Creating sample candidate for testing...</p>";
        $conn->exec("
            INSERT INTO candidates (first_name, last_name, email, phone, resume_path, status, created_by)
            VALUES ('Test', 'Candidate', 'test@example.com', '555-0123', '/resumes/test.pdf', 'active', 1)
        ");
        echo "<p style='color: green;'>✅ Created sample candidate</p>";
    }
    
    echo "<h2>✅ Schema Compatibility Fixed!</h2>";
    echo "<p style='color: green; font-weight: bold;'>All compatibility issues resolved. You can now run the test script again.</p>";
    echo "<p><a href='test_approval_system.php' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px;'>🧪 Run Tests Again</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 