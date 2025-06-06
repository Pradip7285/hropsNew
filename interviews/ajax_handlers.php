<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Ensure user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$db = new Database();
$conn = $db->getConnection();

try {
    switch ($action) {
        case 'save_draft_feedback':
            requireRole('interviewer');
            
            $interview_id = $_POST['interview_id'];
            $technical_rating = $_POST['technical_rating'] ?? null;
            $communication_rating = $_POST['communication_rating'] ?? null;
            $cultural_fit_rating = $_POST['cultural_fit_rating'] ?? null;
            $overall_rating = $_POST['overall_rating'] ?? null;
            $strengths = $_POST['strengths'] ?? '';
            $weaknesses = $_POST['weaknesses'] ?? '';
            $feedback_notes = $_POST['feedback_notes'] ?? '';
            $recommendation = $_POST['recommendation'] ?? null;
            
            // Create drafts table if not exists
            $conn->exec("
                CREATE TABLE IF NOT EXISTS interview_feedback_drafts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    interview_id INT NOT NULL,
                    interviewer_id INT NOT NULL,
                    technical_rating INT,
                    communication_rating INT,
                    cultural_fit_rating INT,
                    overall_rating INT,
                    strengths TEXT,
                    weaknesses TEXT,
                    feedback_notes TEXT,
                    recommendation ENUM('strong_hire', 'hire', 'neutral', 'no_hire', 'strong_no_hire'),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_draft (interview_id, interviewer_id)
                )
            ");
            
            // Upsert draft
            $stmt = $conn->prepare("
                INSERT INTO interview_feedback_drafts 
                (interview_id, interviewer_id, technical_rating, communication_rating, 
                 cultural_fit_rating, overall_rating, strengths, weaknesses, 
                 feedback_notes, recommendation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    technical_rating = VALUES(technical_rating),
                    communication_rating = VALUES(communication_rating),
                    cultural_fit_rating = VALUES(cultural_fit_rating),
                    overall_rating = VALUES(overall_rating),
                    strengths = VALUES(strengths),
                    weaknesses = VALUES(weaknesses),
                    feedback_notes = VALUES(feedback_notes),
                    recommendation = VALUES(recommendation),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $interview_id, $_SESSION['user_id'], $technical_rating, 
                $communication_rating, $cultural_fit_rating, $overall_rating,
                $strengths, $weaknesses, $feedback_notes, $recommendation
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Draft saved successfully']);
            break;
            
        case 'load_draft_feedback':
            requireRole('interviewer');
            
            $interview_id = $_GET['interview_id'];
            $stmt = $conn->prepare("
                SELECT * FROM interview_feedback_drafts 
                WHERE interview_id = ? AND interviewer_id = ?
            ");
            $stmt->execute([$interview_id, $_SESSION['user_id']]);
            $draft = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'draft' => $draft]);
            break;
            
        case 'check_interviewer_availability':
            requireRole('hr_recruiter');
            
            $interviewer_id = $_GET['interviewer_id'];
            $date = $_GET['date'];
            $start_time = $_GET['start_time'];
            $duration = $_GET['duration'] ?? 60;
            $exclude_interview = $_GET['exclude_interview'] ?? null;
            
            $end_time = date('H:i', strtotime($start_time . ' + ' . $duration . ' minutes'));
            
            // Check for conflicts
            $conflict_query = "
                SELECT COUNT(*) as conflicts 
                FROM interviews 
                WHERE interviewer_id = ? 
                AND DATE(scheduled_date) = ? 
                AND status IN ('scheduled', 'rescheduled')
                AND (
                    (TIME(scheduled_date) < ? AND TIME(DATE_ADD(scheduled_date, INTERVAL duration MINUTE)) > ?) OR
                    (TIME(scheduled_date) < ? AND TIME(DATE_ADD(scheduled_date, INTERVAL duration MINUTE)) > ?) OR
                    (TIME(scheduled_date) >= ? AND TIME(scheduled_date) < ?)
                )
            ";
            
            $params = [$interviewer_id, $date, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time];
            
            if ($exclude_interview) {
                $conflict_query .= " AND id != ?";
                $params[] = $exclude_interview;
            }
            
            $stmt = $conn->prepare($conflict_query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            $available = $result['conflicts'] == 0;
            
            echo json_encode([
                'success' => true, 
                'available' => $available,
                'conflicts' => $result['conflicts']
            ]);
            break;
            
        case 'get_interview_details':
            $interview_id = $_GET['interview_id'];
            
            $stmt = $conn->prepare("
                SELECT i.*, 
                       c.first_name as candidate_first, c.last_name as candidate_last, 
                       c.email as candidate_email, c.phone as candidate_phone,
                       j.title as job_title, j.department,
                       u.first_name as interviewer_first, u.last_name as interviewer_last,
                       u.email as interviewer_email,
                       CASE 
                           WHEN i.scheduled_date < NOW() AND i.status = 'scheduled' THEN 'overdue'
                           ELSE i.status 
                       END as display_status
                FROM interviews i
                JOIN candidates c ON i.candidate_id = c.id
                JOIN job_postings j ON i.job_id = j.id
                JOIN users u ON i.interviewer_id = u.id
                WHERE i.id = ?
            ");
            $stmt->execute([$interview_id]);
            $interview = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($interview) {
                // Get feedback if exists
                $feedback_stmt = $conn->prepare("
                    SELECT * FROM interview_feedback 
                    WHERE interview_id = ?
                ");
                $feedback_stmt->execute([$interview_id]);
                $feedback = $feedback_stmt->fetch(PDO::FETCH_ASSOC);
                
                $interview['feedback'] = $feedback;
                
                echo json_encode(['success' => true, 'interview' => $interview]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Interview not found']);
            }
            break;
            
        case 'update_interview_status':
            requireRole('hr_recruiter');
            
            $interview_id = $_POST['interview_id'];
            $status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';
            
            $stmt = $conn->prepare("
                UPDATE interviews SET status = ?, notes = ? WHERE id = ?
            ");
            $result = $stmt->execute([$status, $notes, $interview_id]);
            
            if ($result) {
                logActivity($_SESSION['user_id'], 'status_updated', 'interview', $interview_id, 
                    "Updated interview status to: $status");
                
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            break;
            
        case 'get_available_time_slots':
            requireRole('hr_recruiter');
            
            $date = $_GET['date'];
            $interviewer_ids = explode(',', $_GET['interviewer_ids']);
            $duration = $_GET['duration'] ?? 60;
            
            // Define working hours (9 AM to 6 PM)
            $start_hour = 9;
            $end_hour = 18;
            $slot_duration = 30; // 30-minute slots
            
            $available_slots = [];
            
            for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                for ($minute = 0; $minute < 60; $minute += $slot_duration) {
                    $time_slot = sprintf('%02d:%02d', $hour, $minute);
                    $end_time = date('H:i', strtotime($time_slot . ' + ' . $duration . ' minutes'));
                    
                    // Don't suggest slots that would end after working hours
                    if (strtotime($end_time) > strtotime('18:00')) {
                        continue;
                    }
                    
                    $all_available = true;
                    
                    foreach ($interviewer_ids as $interviewer_id) {
                        // Check availability for this interviewer
                        $conflict_stmt = $conn->prepare("
                            SELECT COUNT(*) as conflicts 
                            FROM interviews 
                            WHERE interviewer_id = ? 
                            AND DATE(scheduled_date) = ? 
                            AND status IN ('scheduled', 'rescheduled')
                            AND (
                                (TIME(scheduled_date) < ? AND TIME(DATE_ADD(scheduled_date, INTERVAL duration MINUTE)) > ?) OR
                                (TIME(scheduled_date) < ? AND TIME(DATE_ADD(scheduled_date, INTERVAL duration MINUTE)) > ?) OR
                                (TIME(scheduled_date) >= ? AND TIME(scheduled_date) < ?)
                            )
                        ");
                        $conflict_stmt->execute([
                            $interviewer_id, $date, $time_slot, $time_slot, 
                            $end_time, $end_time, $time_slot, $end_time
                        ]);
                        $conflicts = $conflict_stmt->fetch()['conflicts'];
                        
                        if ($conflicts > 0) {
                            $all_available = false;
                            break;
                        }
                    }
                    
                    if ($all_available) {
                        $available_slots[] = [
                            'time' => $time_slot,
                            'end_time' => $end_time,
                            'display' => date('g:i A', strtotime($time_slot)) . ' - ' . date('g:i A', strtotime($end_time))
                        ];
                    }
                }
            }
            
            echo json_encode(['success' => true, 'slots' => $available_slots]);
            break;
            
        case 'send_test_notification':
            requireRole('admin');
            
            $interview_id = $_POST['interview_id'];
            $type = $_POST['type']; // 'candidate' or 'interviewer'
            
            // Get interview details
            $stmt = $conn->prepare("
                SELECT i.*, 
                       c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
                       j.title as job_title,
                       u.first_name as interviewer_first, u.last_name as interviewer_last, u.email as interviewer_email
                FROM interviews i
                JOIN candidates c ON i.candidate_id = c.id
                JOIN job_postings j ON i.job_id = j.id
                JOIN users u ON i.interviewer_id = u.id
                WHERE i.id = ?
            ");
            $stmt->execute([$interview_id]);
            $interview = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($interview) {
                $notification_sent = sendInterviewNotification($interview, $type);
                echo json_encode([
                    'success' => $notification_sent, 
                    'message' => $notification_sent ? 'Test notification sent successfully' : 'Failed to send notification'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Interview not found']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// Helper function for sending interview notifications
function sendInterviewNotification($interview, $type) {
    // This would integrate with your email service
    // For now, we'll log the notification
    $log_message = "Interview notification would be sent to ";
    $log_message .= $type == 'candidate' ? $interview['candidate_email'] : $interview['interviewer_email'];
    $log_message .= " for interview on " . date('M j, Y g:i A', strtotime($interview['scheduled_date']));
    
    error_log($log_message);
    
    // Return true for demo purposes
    return true;
}
?> 