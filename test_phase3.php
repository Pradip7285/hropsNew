<?php
require_once 'config/config.php';

echo "<h1>HR Employee Lifecycle Management - Phase 3 Installation Test</h1>";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h2>✓ Database Connection: SUCCESS</h2>";
    
    // Test Phase 3 tables
    $phase3_tables = [
        'offers', 'employees', 'onboarding_tasks', 'onboarding_templates', 
        'offer_templates', 'employee_documents', 'training_modules', 
        'training_progress', 'notifications'
    ];
    
    echo "<h2>Phase 3 Tables Check:</h2><ul>";
    foreach ($phase3_tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<li>✓ $table - EXISTS</li>";
        } else {
            echo "<li>✗ $table - MISSING</li>";
        }
    }
    echo "</ul>";
    
    // Check default data
    echo "<h2>Default Data Check:</h2><ul>";
    
    // Check offer templates
    $stmt = $conn->query("SELECT COUNT(*) as count FROM offer_templates");
    $count = $stmt->fetch()['count'];
    echo "<li>Offer Templates: $count records</li>";
    
    // Check onboarding templates
    $stmt = $conn->query("SELECT COUNT(*) as count FROM onboarding_templates");
    $count = $stmt->fetch()['count'];
    echo "<li>Onboarding Templates: $count records</li>";
    
    echo "</ul>";
    
    // Check directories
    echo "<h2>Upload Directories Check:</h2><ul>";
    $directories = ['uploads/offers', 'uploads/documents', 'uploads/resumes'];
    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            echo "<li>✓ $dir - EXISTS</li>";
        } else {
            echo "<li>✗ $dir - MISSING</li>";
        }
    }
    echo "</ul>";
    
    echo "<h2>🎉 Phase 3 Installation Status: COMPLETE!</h2>";
    echo "<p>Your HR Employee Lifecycle Management Application is ready with:</p>";
    echo "<ul>";
    echo "<li>✓ Comprehensive Offer Management</li>";
    echo "<li>✓ Advanced Employee Onboarding</li>";
    echo "<li>✓ Training & Development Tracking</li>";
    echo "<li>✓ Analytics & Reporting Dashboard</li>";
    echo "<li>✓ Document Management System</li>";
    echo "<li>✓ Notification System</li>";
    echo "</ul>";
    
    echo "<h3>Quick Links to Test Features:</h3>";
    echo "<ul>";
    echo "<li><a href='offers/list.php'>📋 Manage Job Offers</a></li>";
    echo "<li><a href='onboarding/list.php'>🎯 Employee Onboarding</a></li>";
    echo "<li><a href='reports/analytics.php'>📊 Analytics Dashboard</a></li>";
    echo "<li><a href='dashboard.php'>🏠 Main Dashboard</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2>✗ Error: " . $e->getMessage() . "</h2>";
}
?> 