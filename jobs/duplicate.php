<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$job_id = $_GET['id'] ?? 0;

if (!$job_id) {
    header('Location: list.php?error=' . urlencode('Invalid job ID'));
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Get original job details
    $stmt = $conn->prepare("SELECT * FROM job_postings WHERE id = ?");
    $stmt->execute([$job_id]);
    $original_job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$original_job) {
        header('Location: list.php?error=' . urlencode('Job not found'));
        exit;
    }
    
    // Create duplicate with modified title
    $new_title = $original_job['title'] . ' (Copy)';
    
    $insert_stmt = $conn->prepare("
        INSERT INTO job_postings (
            title, department, location, employment_type, description, requirements,
            responsibilities, salary_range, experience_level, education_level,
            skills_required, benefits, application_deadline, status, priority,
            notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)
    ");
    
    $insert_stmt->execute([
        $new_title,
        $original_job['department'],
        $original_job['location'],
        $original_job['employment_type'],
        $original_job['description'],
        $original_job['requirements'],
        $original_job['responsibilities'],
        $original_job['salary_range'],
        $original_job['experience_level'],
        $original_job['education_level'],
        $original_job['skills_required'],
        $original_job['benefits'],
        $original_job['application_deadline'],
        $original_job['priority'],
        $original_job['notes'],
        $_SESSION['user_id']
    ]);
    
    $new_job_id = $conn->lastInsertId();
    
    // Log activity
    logActivity(
        $_SESSION['user_id'], 
        'created', 
        'job_posting', 
        $new_job_id,
        "Duplicated job posting: $new_title (from {$original_job['title']})"
    );
    
    header('Location: edit.php?id=' . $new_job_id . '&success=' . urlencode('Job duplicated successfully! Please review and modify as needed.'));
    
} catch (Exception $e) {
    header('Location: list.php?error=' . urlencode('Error duplicating job: ' . $e->getMessage()));
}
?> 