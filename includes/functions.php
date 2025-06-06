<?php
/**
 * Utility functions for HR Operations
 */

/**
 * URL Helper function to prevent double slashes
 */
function url($path = '') {
    $baseUrl = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    return $baseUrl . ($path ? '/' . $path : '');
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user's role
 */
function getUserRole() {
    return $_SESSION['role'] ?? null;
}



/**
 * Get dashboard statistics
 */
function getDashboardStats() {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Total candidates
        $total_candidates = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch()['count'];
        
        // New candidates today
        $new_candidates_today = $conn->query("
            SELECT COUNT(*) as count FROM candidates 
            WHERE DATE(created_at) = CURDATE()
        ")->fetch()['count'];
        
        // Active jobs
        $active_jobs = $conn->query("
            SELECT COUNT(*) as count FROM job_postings 
            WHERE status = 'active'
        ")->fetch()['count'];
        
        // Total applications
        $total_applications = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch()['count'];
        
        // Interviews today
        $interviews_today = $conn->query("
            SELECT COUNT(*) as count FROM interviews 
            WHERE DATE(scheduled_date) = CURDATE() AND status IN ('scheduled', 'rescheduled')
        ")->fetch()['count'];
        
        // Pending interviews
        $pending_interviews = $conn->query("
            SELECT COUNT(*) as count FROM interviews 
            WHERE status = 'scheduled' AND scheduled_date > NOW()
        ")->fetch()['count'];
        
        // Pending offers
        $pending_offers = $conn->query("
            SELECT COUNT(*) as count FROM offers 
            WHERE status = 'sent'
        ")->fetch()['count'] ?? 0;
        
        // Accepted offers
        $accepted_offers = $conn->query("
            SELECT COUNT(*) as count FROM offers 
            WHERE status = 'accepted'
        ")->fetch()['count'] ?? 0;
        
        // Pipeline data
        $pipeline_data = [];
        $statuses = ['new', 'shortlisted', 'interviewing', 'offered', 'hired'];
        foreach ($statuses as $status) {
            $count = $conn->query("
                SELECT COUNT(*) as count FROM candidates WHERE status = '$status'
            ")->fetch()['count'];
            $pipeline_data[] = $count;
        }
        
        return [
            'total_candidates' => $total_candidates,
            'new_candidates_today' => $new_candidates_today,
            'active_jobs' => $active_jobs,
            'total_applications' => $total_applications,
            'interviews_today' => $interviews_today,
            'pending_interviews' => $pending_interviews,
            'pending_offers' => $pending_offers,
            'accepted_offers' => $accepted_offers,
            'pipeline_data' => $pipeline_data
        ];
    } catch (Exception $e) {
        // Return default values if there's an error
        return [
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
    }
}

/**
 * Get recent activity
 */
function getRecentActivity() {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->query("
            SELECT 
                action,
                entity_type,
                entity_id,
                description,
                created_at,
                CASE 
                    WHEN action LIKE '%created%' THEN 'plus'
                    WHEN action LIKE '%updated%' THEN 'edit'
                    WHEN action LIKE '%deleted%' THEN 'trash'
                    WHEN action LIKE '%scheduled%' THEN 'calendar'
                    WHEN action LIKE '%completed%' THEN 'check'
                    ELSE 'info'
                END as icon
            FROM activity_logs 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Log user activity
 */
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
        // Log error but don't stop execution
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Format time ago
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Handle file upload
 */
function handleFileUpload($file, $allowed_types = null, $max_size = null) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    $allowed_types = $allowed_types ?: ALLOWED_FILE_TYPES;
    $max_size = $max_size ?: MAX_FILE_SIZE;
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Generate unique filename
    $filename = uniqid() . '.' . $file_extension;
    $upload_path = UPLOAD_PATH . $filename;
    
    // Create upload directory if it doesn't exist
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $filename, 'path' => $upload_path];
    } else {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get current page name
 */
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF'], '.php');
}

/**
 * Get current directory
 */
function getCurrentDirectory() {
    return basename(dirname($_SERVER['PHP_SELF']));
}

/**
 * Check if current page is active
 */
function isActivePage($page) {
    return getCurrentPage() === $page;
}

/**
 * Check if current directory is active
 */
function isActiveDirectory($directory) {
    return getCurrentDirectory() === $directory;
}

/**
 * Generate pagination links
 */
function generatePagination($current_page, $total_pages, $base_url) {
    $pagination = [];
    
    // Previous page
    if ($current_page > 1) {
        $pagination['prev'] = $base_url . '&page=' . ($current_page - 1);
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $pagination['pages'][$i] = [
            'number' => $i,
            'url' => $base_url . '&page=' . $i,
            'active' => $i == $current_page
        ];
    }
    
    // Next page
    if ($current_page < $total_pages) {
        $pagination['next'] = $base_url . '&page=' . ($current_page + 1);
    }
    
    return $pagination;
}
?> 