<?php
// Get dashboard statistics
function getDashboardStats() {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Initialize stats array
    $stats = [
        'total_candidates' => 0,
        'new_candidates_today' => 0,
        'active_jobs' => 0,
        'total_applications' => 0,
        'interviews_today' => 0,
        'pending_interviews' => 0,
        'pending_offers' => 0,
        'accepted_offers' => 0,
        'pipeline_data' => [0, 0, 0, 0, 0]
    ];
    
    try {
        // Total candidates
        $stmt = $conn->query("SELECT COUNT(*) as count FROM candidates");
        $stats['total_candidates'] = $stmt->fetch()['count'];
        
        // New candidates today
        $stmt = $conn->query("SELECT COUNT(*) as count FROM candidates WHERE DATE(created_at) = CURDATE()");
        $stats['new_candidates_today'] = $stmt->fetch()['count'];
        
        // Active jobs
        $stmt = $conn->query("SELECT COUNT(*) as count FROM job_postings WHERE status = 'active'");
        $stats['active_jobs'] = $stmt->fetch()['count'];
        
        // Total applications (candidates)
        $stats['total_applications'] = $stats['total_candidates'];
        
        // Interviews today
        $stmt = $conn->query("SELECT COUNT(*) as count FROM interviews WHERE DATE(scheduled_date) = CURDATE() AND status = 'scheduled'");
        $stats['interviews_today'] = $stmt->fetch()['count'];
        
        // Pending interviews
        $stmt = $conn->query("SELECT COUNT(*) as count FROM interviews WHERE status = 'scheduled' AND scheduled_date > NOW()");
        $stats['pending_interviews'] = $stmt->fetch()['count'];
        
        // Pending offers
        $stmt = $conn->query("SELECT COUNT(*) as count FROM offers WHERE status = 'sent'");
        $stats['pending_offers'] = $stmt->fetch()['count'];
        
        // Accepted offers
        $stmt = $conn->query("SELECT COUNT(*) as count FROM offers WHERE status = 'accepted'");
        $stats['accepted_offers'] = $stmt->fetch()['count'];
        
        // Pipeline data
        $pipeline_statuses = ['new', 'shortlisted', 'interviewing', 'offered', 'hired'];
        foreach ($pipeline_statuses as $index => $status) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates WHERE status = ?");
            $stmt->execute([$status]);
            $stats['pipeline_data'][$index] = $stmt->fetch()['count'];
        }
        
    } catch (Exception $e) {
        // If tables don't exist yet, return default values
        error_log("Dashboard stats error: " . $e->getMessage());
    }
    
    return $stats;
}

// Get recent activity
function getRecentActivity() {
    $db = new Database();
    $conn = $db->getConnection();
    
    $activities = [];
    
    try {
        $stmt = $conn->query("
            SELECT action, entity_type, description, created_at 
            FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['icon'] = getActivityIcon($row['action'], $row['entity_type']);
            $activities[] = $row;
        }
    } catch (Exception $e) {
        // If table doesn't exist, return empty array
        error_log("Recent activity error: " . $e->getMessage());
    }
    
    return $activities;
}

// Get activity icon based on action and entity type
function getActivityIcon($action, $entity_type) {
    $icons = [
        'candidate' => 'user-plus',
        'job' => 'briefcase',
        'interview' => 'calendar',
        'offer' => 'file-contract',
        'employee' => 'users'
    ];
    
    return $icons[$entity_type] ?? 'info-circle';
}

// Time ago function
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

// Log activity
function logActivity($user_id, $action, $entity_type, $entity_id, $description) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action, $entity_type, $entity_id, $description]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Upload file function
function uploadFile($file, $upload_dir = 'uploads/') {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error');
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, ALLOWED_FILE_TYPES)) {
        throw new Exception('File type not allowed');
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size too large');
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    return $upload_path;
}
?> 