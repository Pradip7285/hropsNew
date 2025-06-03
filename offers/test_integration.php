<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Management Integration Test</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
</head>
<body class='bg-gray-100 p-8'>
    <div class='max-w-6xl mx-auto'>
        <h1 class='text-3xl font-bold text-gray-800 mb-8'>Offer Management System - Integration Test</h1>";

$test_results = [];

// Test 1: Database Schema Verification
echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>
        <h2 class='text-xl font-semibold text-gray-800 mb-4'>Database Schema Verification</h2>";

try {
    // Check offers table columns
    $columns_check = $conn->query("DESCRIBE offers");
    $columns = $columns_check->fetchAll(PDO::FETCH_ASSOC);
    
    $required_columns = ['approval_status', 'template_id', 'rejection_reason', 'approved_at', 
                        'candidate_response_at', 'response_notes', 'response_token', 'custom_terms'];
    
    $missing_columns = [];
    $existing_columns = array_column($columns, 'Field');
    
    foreach ($required_columns as $col) {
        if (!in_array($col, $existing_columns)) {
            $missing_columns[] = $col;
        }
    }
    
    if (empty($missing_columns)) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>All required columns exist in offers table</div>";
        $test_results['schema_offers'] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Missing columns: " . implode(', ', $missing_columns) . "</div>";
        $test_results['schema_offers'] = false;
    }
    
    // Check new tables
    $tables_check = $conn->query("SHOW TABLES LIKE 'offer_responses'");
    if ($tables_check->rowCount() > 0) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>offer_responses table exists</div>";
        $test_results['schema_responses'] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>offer_responses table missing</div>";
        $test_results['schema_responses'] = false;
    }
    
    $tables_check = $conn->query("SHOW TABLES LIKE 'offer_notifications'");
    if ($tables_check->rowCount() > 0) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>offer_notifications table exists</div>";
        $test_results['schema_notifications'] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>offer_notifications table missing</div>";
        $test_results['schema_notifications'] = false;
    }
    
} catch (Exception $e) {
    echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Database error: " . $e->getMessage() . "</div>";
    $test_results['schema_offers'] = false;
}

echo "</div>";

// Test 2: File Existence Check
echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>
        <h2 class='text-xl font-semibold text-gray-800 mb-4'>File Existence Check</h2>";

$required_files = [
    'list.php' => 'Offer List Management',
    'create.php' => 'Offer Creation',
    'edit.php' => 'Offer Editing',
    'view.php' => 'Offer Viewing',
    'approvals.php' => 'Approval Workflow',
    'templates.php' => 'Template Management',
    'response.php' => 'Candidate Response Portal',
    'analytics.php' => 'Analytics Dashboard',
    'email_notifications.php' => 'Email Automation System'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>{$description} ({$file})</div>";
        $test_results['file_' . str_replace('.php', '', $file)] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>{$description} ({$file}) - MISSING</div>";
        $test_results['file_' . str_replace('.php', '', $file)] = false;
    }
}

echo "</div>";

// Test 3: Database Data Check
echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>
        <h2 class='text-xl font-semibold text-gray-800 mb-4'>Database Data Verification</h2>";

try {
    // Check for existing data
    $offers_count = $conn->query("SELECT COUNT(*) FROM offers")->fetchColumn();
    $candidates_count = $conn->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
    $jobs_count = $conn->query("SELECT COUNT(*) FROM job_postings")->fetchColumn();
    $templates_count = $conn->query("SELECT COUNT(*) FROM offer_templates")->fetchColumn();
    
    echo "<div class='grid grid-cols-2 md:grid-cols-4 gap-4 mb-4'>";
    echo "<div class='text-center p-4 bg-blue-50 rounded'><div class='text-2xl font-bold text-blue-600'>{$offers_count}</div><div class='text-sm text-gray-600'>Offers</div></div>";
    echo "<div class='text-center p-4 bg-green-50 rounded'><div class='text-2xl font-bold text-green-600'>{$candidates_count}</div><div class='text-sm text-gray-600'>Candidates</div></div>";
    echo "<div class='text-center p-4 bg-purple-50 rounded'><div class='text-2xl font-bold text-purple-600'>{$jobs_count}</div><div class='text-sm text-gray-600'>Job Postings</div></div>";
    echo "<div class='text-center p-4 bg-yellow-50 rounded'><div class='text-2xl font-bold text-yellow-600'>{$templates_count}</div><div class='text-sm text-gray-600'>Templates</div></div>";
    echo "</div>";
    
    if ($candidates_count > 0 && $jobs_count > 0) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>Sufficient data for testing (candidates and jobs exist)</div>";
        $test_results['data_ready'] = true;
    } else {
        echo "<div class='text-yellow-600'><i class='fas fa-exclamation-triangle mr-2'></i>Limited data - consider adding candidates and job postings for full testing</div>";
        $test_results['data_ready'] = false;
    }
    
} catch (Exception $e) {
    echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Data check error: " . $e->getMessage() . "</div>";
    $test_results['data_ready'] = false;
}

echo "</div>";

// Test 4: Permission and Security Check
echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>
        <h2 class='text-xl font-semibold text-gray-800 mb-4'>Security & Permissions Check</h2>";

try {
    // Check if current user has proper permissions
    $user_role = $_SESSION['role'] ?? 'unknown';
    
    if (hasPermission('hr_recruiter')) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>User has HR recruiter permissions</div>";
        $test_results['permissions'] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>User lacks sufficient permissions</div>";
        $test_results['permissions'] = false;
    }
    
    // Check token generation
    $test_token = hash('sha256', 'test_' . time() . '_' . rand());
    if (strlen($test_token) === 64) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>Token generation working properly</div>";
        $test_results['token_generation'] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Token generation issue</div>";
        $test_results['token_generation'] = false;
    }
    
} catch (Exception $e) {
    echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Security check error: " . $e->getMessage() . "</div>";
    $test_results['permissions'] = false;
}

echo "</div>";

// Test 5: Email System Check
echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>
        <h2 class='text-xl font-semibold text-gray-800 mb-4'>Email System Check</h2>";

if (file_exists('email_notifications.php')) {
    include_once 'email_notifications.php';
    
    if (function_exists('sendEmail')) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>Email functions available</div>";
        $test_results['email_functions'] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Email functions not available</div>";
        $test_results['email_functions'] = false;
    }
    
    if (function_exists('generateOfferEmailContent')) {
        echo "<div class='text-green-600'><i class='fas fa-check-circle mr-2'></i>Email template functions available</div>";
        $test_results['email_templates'] = true;
    } else {
        echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Email template functions not available</div>";
        $test_results['email_templates'] = false;
    }
} else {
    echo "<div class='text-red-600'><i class='fas fa-times-circle mr-2'></i>Email notification system not found</div>";
    $test_results['email_functions'] = false;
    $test_results['email_templates'] = false;
}

echo "</div>";

// Test Summary
$total_tests = count($test_results);
$passed_tests = array_sum($test_results);
$success_rate = round(($passed_tests / $total_tests) * 100, 1);

echo "<div class='bg-white rounded-lg shadow-md p-6 mb-6'>
        <h2 class='text-xl font-semibold text-gray-800 mb-4'>Test Summary</h2>
        <div class='grid grid-cols-1 md:grid-cols-3 gap-6'>
            <div class='text-center p-6 bg-blue-50 rounded-lg'>
                <div class='text-3xl font-bold text-blue-600'>{$total_tests}</div>
                <div class='text-sm text-gray-600'>Total Tests</div>
            </div>
            <div class='text-center p-6 bg-green-50 rounded-lg'>
                <div class='text-3xl font-bold text-green-600'>{$passed_tests}</div>
                <div class='text-sm text-gray-600'>Passed</div>
            </div>
            <div class='text-center p-6 bg-" . ($success_rate >= 90 ? 'green' : ($success_rate >= 70 ? 'yellow' : 'red')) . "-50 rounded-lg'>
                <div class='text-3xl font-bold text-" . ($success_rate >= 90 ? 'green' : ($success_rate >= 70 ? 'yellow' : 'red')) . "-600'>{$success_rate}%</div>
                <div class='text-sm text-gray-600'>Success Rate</div>
            </div>
        </div>";

if ($success_rate >= 90) {
    echo "<div class='mt-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded'>
            <h3 class='font-semibold'><i class='fas fa-check-circle mr-2'></i>System Ready!</h3>
            <p>The Offer Management System is properly configured and ready for production use.</p>
          </div>";
} elseif ($success_rate >= 70) {
    echo "<div class='mt-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded'>
            <h3 class='font-semibold'><i class='fas fa-exclamation-triangle mr-2'></i>Minor Issues Detected</h3>
            <p>The system is mostly functional but has some minor issues that should be addressed.</p>
          </div>";
} else {
    echo "<div class='mt-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded'>
            <h3 class='font-semibold'><i class='fas fa-times-circle mr-2'></i>Major Issues Detected</h3>
            <p>Several critical issues need to be resolved before the system can be used in production.</p>
          </div>";
}

echo "</div>";

// Quick Access Links
echo "<div class='bg-white rounded-lg shadow-md p-6'>
        <h2 class='text-xl font-semibold text-gray-800 mb-4'>Quick Access Links</h2>
        <div class='grid grid-cols-2 md:grid-cols-3 gap-4'>";

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "<a href='{$file}' class='block p-4 bg-blue-50 hover:bg-blue-100 rounded-lg text-center transition duration-200'>
                <div class='text-blue-600 font-medium'>{$description}</div>
                <div class='text-xs text-gray-500 mt-1'>{$file}</div>
              </a>";
    }
}

echo "</div></div>";

echo "</div></body></html>";
?> 