<?php
/**
 * Interview Email Notification System
 * Handles all email communications for interview management
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

class InterviewEmailNotifications {
    private $conn;
    
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        
        // Create email templates table if not exists
        $this->initializeEmailTemplates();
    }
    
    private function initializeEmailTemplates() {
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS interview_email_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type ENUM('interview_scheduled', 'interview_reminder', 'interview_cancelled', 'interview_rescheduled', 'feedback_request') NOT NULL,
                recipient_type ENUM('candidate', 'interviewer', 'hr') NOT NULL,
                subject_template TEXT NOT NULL,
                body_template TEXT NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default templates if they don't exist
        $this->insertDefaultTemplates();
    }
    
    private function insertDefaultTemplates() {
        $templates = [
            [
                'name' => 'Interview Scheduled - Candidate',
                'type' => 'interview_scheduled',
                'recipient_type' => 'candidate',
                'subject' => 'Interview Scheduled: {{job_title}} at {{company_name}}',
                'body' => $this->getCandidateScheduledTemplate()
            ],
            [
                'name' => 'Interview Scheduled - Interviewer',
                'type' => 'interview_scheduled',
                'recipient_type' => 'interviewer',
                'subject' => 'Interview Assigned: {{candidate_name}} for {{job_title}}',
                'body' => $this->getInterviewerScheduledTemplate()
            ],
            [
                'name' => 'Interview Reminder - Candidate',
                'type' => 'interview_reminder',
                'recipient_type' => 'candidate',
                'subject' => 'Reminder: Interview Tomorrow for {{job_title}}',
                'body' => $this->getCandidateReminderTemplate()
            ],
            [
                'name' => 'Interview Reminder - Interviewer',
                'type' => 'interview_reminder',
                'recipient_type' => 'interviewer',
                'subject' => 'Reminder: Interview with {{candidate_name}}',
                'body' => $this->getInterviewerReminderTemplate()
            ],
            [
                'name' => 'Interview Cancelled - Candidate',
                'type' => 'interview_cancelled',
                'recipient_type' => 'candidate',
                'subject' => 'Interview Cancelled: {{job_title}}',
                'body' => $this->getCandidateCancelledTemplate()
            ],
            [
                'name' => 'Interview Rescheduled - Candidate',
                'type' => 'interview_rescheduled',
                'recipient_type' => 'candidate',
                'subject' => 'Interview Rescheduled: {{job_title}}',
                'body' => $this->getCandidateRescheduledTemplate()
            ]
        ];
        
        foreach ($templates as $template) {
            $check_stmt = $this->conn->prepare("
                SELECT id FROM interview_email_templates 
                WHERE type = ? AND recipient_type = ?
            ");
            $check_stmt->execute([$template['type'], $template['recipient_type']]);
            
            if (!$check_stmt->fetch()) {
                $insert_stmt = $this->conn->prepare("
                    INSERT INTO interview_email_templates 
                    (name, type, recipient_type, subject_template, body_template)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([
                    $template['name'],
                    $template['type'],
                    $template['recipient_type'],
                    $template['subject'],
                    $template['body']
                ]);
            }
        }
    }
    
    /**
     * Send interview scheduled notification
     */
    public function sendInterviewScheduledNotification($interview_id) {
        $interview = $this->getInterviewDetails($interview_id);
        if (!$interview) return false;
        
        $success = true;
        
        // Send to candidate
        $candidate_sent = $this->sendNotification(
            $interview, 
            'interview_scheduled', 
            'candidate'
        );
        
        // Send to interviewer
        $interviewer_sent = $this->sendNotification(
            $interview, 
            'interview_scheduled', 
            'interviewer'
        );
        
        // Log notifications
        $this->logNotification($interview_id, 'interview_scheduled', 'candidate', $candidate_sent);
        $this->logNotification($interview_id, 'interview_scheduled', 'interviewer', $interviewer_sent);
        
        return $candidate_sent && $interviewer_sent;
    }
    
    /**
     * Send interview reminder
     */
    public function sendInterviewReminder($interview_id, $recipient_type = 'both') {
        $interview = $this->getInterviewDetails($interview_id);
        if (!$interview) return false;
        
        $success = true;
        
        if ($recipient_type === 'candidate' || $recipient_type === 'both') {
            $candidate_sent = $this->sendNotification(
                $interview, 
                'interview_reminder', 
                'candidate'
            );
            $this->logNotification($interview_id, 'interview_reminder', 'candidate', $candidate_sent);
            $success = $success && $candidate_sent;
        }
        
        if ($recipient_type === 'interviewer' || $recipient_type === 'both') {
            $interviewer_sent = $this->sendNotification(
                $interview, 
                'interview_reminder', 
                'interviewer'
            );
            $this->logNotification($interview_id, 'interview_reminder', 'interviewer', $interviewer_sent);
            $success = $success && $interviewer_sent;
        }
        
        return $success;
    }
    
    /**
     * Send interview cancellation notification
     */
    public function sendInterviewCancelledNotification($interview_id, $reason = '') {
        $interview = $this->getInterviewDetails($interview_id);
        if (!$interview) return false;
        
        $interview['cancellation_reason'] = $reason;
        
        $candidate_sent = $this->sendNotification(
            $interview, 
            'interview_cancelled', 
            'candidate'
        );
        
        $interviewer_sent = $this->sendNotification(
            $interview, 
            'interview_cancelled', 
            'interviewer'
        );
        
        $this->logNotification($interview_id, 'interview_cancelled', 'candidate', $candidate_sent);
        $this->logNotification($interview_id, 'interview_cancelled', 'interviewer', $interviewer_sent);
        
        return $candidate_sent && $interviewer_sent;
    }
    
    /**
     * Send interview rescheduled notification
     */
    public function sendInterviewRescheduledNotification($interview_id, $old_datetime) {
        $interview = $this->getInterviewDetails($interview_id);
        if (!$interview) return false;
        
        $interview['old_datetime'] = $old_datetime;
        
        $candidate_sent = $this->sendNotification(
            $interview, 
            'interview_rescheduled', 
            'candidate'
        );
        
        $interviewer_sent = $this->sendNotification(
            $interview, 
            'interview_rescheduled', 
            'interviewer'
        );
        
        $this->logNotification($interview_id, 'interview_rescheduled', 'candidate', $candidate_sent);
        $this->logNotification($interview_id, 'interview_rescheduled', 'interviewer', $interviewer_sent);
        
        return $candidate_sent && $interviewer_sent;
    }
    
    /**
     * Send bulk reminders for upcoming interviews
     */
    public function sendBulkReminders($hours_before = 24) {
        $stmt = $this->conn->prepare("
            SELECT id FROM interviews 
            WHERE status = 'scheduled'
            AND scheduled_date > NOW()
            AND scheduled_date <= DATE_ADD(NOW(), INTERVAL ? HOUR)
            AND id NOT IN (
                SELECT interview_id FROM interview_email_logs 
                WHERE notification_type = 'interview_reminder'
                AND DATE(sent_at) = CURDATE()
            )
        ");
        $stmt->execute([$hours_before]);
        $interview_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $sent_count = 0;
        foreach ($interview_ids as $interview_id) {
            if ($this->sendInterviewReminder($interview_id)) {
                $sent_count++;
            }
        }
        
        return $sent_count;
    }
    
    /**
     * Get interview details for email templates
     */
    private function getInterviewDetails($interview_id) {
        $stmt = $this->conn->prepare("
            SELECT i.*, 
                   c.first_name as candidate_first, c.last_name as candidate_last, 
                   c.email as candidate_email, c.phone as candidate_phone,
                   j.title as job_title, j.department, j.location as job_location,
                   u.first_name as interviewer_first, u.last_name as interviewer_last,
                   u.email as interviewer_email
            FROM interviews i
            JOIN candidates c ON i.candidate_id = c.id
            JOIN job_postings j ON i.job_id = j.id
            JOIN users u ON i.interviewer_id = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$interview_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Send notification based on template
     */
    private function sendNotification($interview, $type, $recipient_type) {
        $template = $this->getEmailTemplate($type, $recipient_type);
        if (!$template) return false;
        
        $recipient_email = $recipient_type === 'candidate' 
            ? $interview['candidate_email'] 
            : $interview['interviewer_email'];
            
        $recipient_name = $recipient_type === 'candidate'
            ? $interview['candidate_first'] . ' ' . $interview['candidate_last']
            : $interview['interviewer_first'] . ' ' . $interview['interviewer_last'];
        
        $subject = $this->processTemplate($template['subject_template'], $interview);
        $body = $this->processTemplate($template['body_template'], $interview);
        
        return $this->sendEmail($recipient_email, $recipient_name, $subject, $body);
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($type, $recipient_type) {
        $stmt = $this->conn->prepare("
            SELECT * FROM interview_email_templates 
            WHERE type = ? AND recipient_type = ? AND is_active = TRUE
        ");
        $stmt->execute([$type, $recipient_type]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process template with placeholders
     */
    private function processTemplate($template, $interview) {
        $placeholders = [
            '{{company_name}}' => APP_NAME,
            '{{candidate_name}}' => $interview['candidate_first'] . ' ' . $interview['candidate_last'],
            '{{candidate_first_name}}' => $interview['candidate_first'],
            '{{interviewer_name}}' => $interview['interviewer_first'] . ' ' . $interview['interviewer_last'],
            '{{job_title}}' => $interview['job_title'],
            '{{department}}' => $interview['department'],
            '{{interview_date}}' => date('F j, Y', strtotime($interview['scheduled_date'])),
            '{{interview_time}}' => date('g:i A', strtotime($interview['scheduled_date'])),
            '{{interview_datetime}}' => date('F j, Y \a\t g:i A', strtotime($interview['scheduled_date'])),
            '{{duration}}' => $interview['duration'] . ' minutes',
            '{{location}}' => $interview['location'] ?: 'TBD',
            '{{meeting_link}}' => $interview['meeting_link'] ?: '',
            '{{interview_type}}' => ucfirst(str_replace('_', ' ', $interview['interview_type'])),
            '{{old_datetime}}' => isset($interview['old_datetime']) ? date('F j, Y \a\t g:i A', strtotime($interview['old_datetime'])) : '',
            '{{cancellation_reason}}' => $interview['cancellation_reason'] ?? ''
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Send email (placeholder - integrate with your email service)
     */
    private function sendEmail($to_email, $to_name, $subject, $body) {
        // In production, integrate with your email service (SMTP, SendGrid, etc.)
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . APP_NAME . ' HR Team <hr@' . $_SERVER['HTTP_HOST'] . '>',
            'Reply-To: hr@' . $_SERVER['HTTP_HOST']
        ];
        
        // Log the email for debugging
        error_log("Email would be sent to: {$to_email}");
        error_log("Subject: {$subject}");
        
        // Uncomment for actual email sending:
        // return mail($to_email, $subject, $body, implode("\r\n", $headers));
        
        // For demo purposes, return true
        return true;
    }
    
    /**
     * Log notification
     */
    private function logNotification($interview_id, $type, $recipient_type, $success) {
        // Create log table if not exists
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS interview_email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                interview_id INT NOT NULL,
                notification_type VARCHAR(50) NOT NULL,
                recipient_type ENUM('candidate', 'interviewer', 'hr') NOT NULL,
                delivery_status ENUM('sent', 'failed') NOT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (interview_id) REFERENCES interviews(id)
            )
        ");
        
        $stmt = $this->conn->prepare("
            INSERT INTO interview_email_logs 
            (interview_id, notification_type, recipient_type, delivery_status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $interview_id, 
            $type, 
            $recipient_type, 
            $success ? 'sent' : 'failed'
        ]);
    }
    
    // Email Template Methods
    private function getCandidateScheduledTemplate() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h2 style="color: #333; margin-bottom: 20px;">Interview Scheduled</h2>
                
                <p>Dear {{candidate_first_name}},</p>
                
                <p>Congratulations! We are pleased to inform you that your interview for the <strong>{{job_title}}</strong> position at {{company_name}} has been scheduled.</p>
                
                <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">Interview Details</h3>
                    <p><strong>Position:</strong> {{job_title}}</p>
                    <p><strong>Department:</strong> {{department}}</p>
                    <p><strong>Date & Time:</strong> {{interview_datetime}}</p>
                    <p><strong>Duration:</strong> {{duration}}</p>
                    <p><strong>Type:</strong> {{interview_type}}</p>
                    <p><strong>Location:</strong> {{location}}</p>
                    <p><strong>Meeting Link:</strong> {{meeting_link}}</p>
                    <p><strong>Interviewer:</strong> {{interviewer_name}}</p>
                </div>
                
                <p><strong>What to expect:</strong></p>
                <ul>
                    <li>Please arrive 10 minutes early</li>
                    <li>Bring a copy of your resume and any relevant documents</li>
                    <li>Be prepared to discuss your experience and ask questions</li>
                    <li>Dress professionally</li>
                </ul>
                
                <p>If you need to reschedule or have any questions, please contact us immediately.</p>
                
                <p>We look forward to meeting you!</p>
                
                <p>Best regards,<br>
                HR Team<br>
                {{company_name}}</p>
            </div>
        </body>
        </html>';
    }
    
    private function getInterviewerScheduledTemplate() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h2 style="color: #333; margin-bottom: 20px;">New Interview Assignment</h2>
                
                <p>Dear {{interviewer_name}},</p>
                
                <p>You have been assigned to conduct an interview for the <strong>{{job_title}}</strong> position.</p>
                
                <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #333;">Interview Details</h3>
                    <p><strong>Candidate:</strong> {{candidate_name}}</p>
                    <p><strong>Position:</strong> {{job_title}}</p>
                    <p><strong>Department:</strong> {{department}}</p>
                    <p><strong>Date & Time:</strong> {{interview_datetime}}</p>
                    <p><strong>Duration:</strong> {{duration}}</p>
                    <p><strong>Type:</strong> {{interview_type}}</p>
                    <p><strong>Location:</strong> {{location}}</p>
                    <p><strong>Meeting Link:</strong> {{meeting_link}}</p>
                </div>
                
                <p><strong>Preparation:</strong></p>
                <ul>
                    <li>Review the candidate\'s resume in the system</li>
                    <li>Prepare relevant interview questions</li>
                    <li>Ensure technical setup for virtual interviews</li>
                    <li>Complete feedback form after the interview</li>
                </ul>
                
                <p>Please confirm your availability and reach out if you have any questions.</p>
                
                <p>Thank you,<br>
                HR Team<br>
                {{company_name}}</p>
            </div>
        </body>
        </html>';
    }
    
    private function getCandidateReminderTemplate() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h2 style="color: #333; margin-bottom: 20px;">Interview Reminder</h2>
                
                <p>Dear {{candidate_first_name}},</p>
                
                <p>This is a friendly reminder about your upcoming interview for the <strong>{{job_title}}</strong> position at {{company_name}}.</p>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0;"><strong>Tomorrow:</strong> {{interview_datetime}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Location:</strong> {{location}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Meeting Link:</strong> {{meeting_link}}</p>
                </div>
                
                <p><strong>Final reminders:</strong></p>
                <ul>
                    <li>Arrive 10 minutes early</li>
                    <li>Test your technology if it\'s a virtual interview</li>
                    <li>Have your questions ready</li>
                    <li>Bring necessary documents</li>
                </ul>
                
                <p>We\'re excited to meet you tomorrow!</p>
                
                <p>Best regards,<br>
                HR Team<br>
                {{company_name}}</p>
            </div>
        </body>
        </html>';
    }
    
    private function getInterviewerReminderTemplate() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h2 style="color: #333; margin-bottom: 20px;">Interview Reminder</h2>
                
                <p>Dear {{interviewer_name}},</p>
                
                <p>This is a reminder about your upcoming interview with <strong>{{candidate_name}}</strong> for the {{job_title}} position.</p>
                
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0;"><strong>Tomorrow:</strong> {{interview_datetime}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Candidate:</strong> {{candidate_name}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Position:</strong> {{job_title}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Duration:</strong> {{duration}}</p>
                </div>
                
                <p><strong>Don\'t forget to:</strong></p>
                <ul>
                    <li>Review the candidate\'s profile</li>
                    <li>Prepare your interview questions</li>
                    <li>Submit feedback after the interview</li>
                </ul>
                
                <p>Thank you for your time!</p>
                
                <p>Best regards,<br>
                HR Team<br>
                {{company_name}}</p>
            </div>
        </body>
        </html>';
    }
    
    private function getCandidateCancelledTemplate() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h2 style="color: #333; margin-bottom: 20px;">Interview Cancelled</h2>
                
                <p>Dear {{candidate_first_name}},</p>
                
                <p>We regret to inform you that your interview for the <strong>{{job_title}}</strong> position scheduled for {{interview_datetime}} has been cancelled.</p>
                
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0;"><strong>Reason:</strong> {{cancellation_reason}}</p>
                </div>
                
                <p>We apologize for any inconvenience this may cause. Our HR team will be in touch soon to discuss next steps.</p>
                
                <p>Thank you for your understanding.</p>
                
                <p>Best regards,<br>
                HR Team<br>
                {{company_name}}</p>
            </div>
        </body>
        </html>';
    }
    
    private function getCandidateRescheduledTemplate() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h2 style="color: #333; margin-bottom: 20px;">Interview Rescheduled</h2>
                
                <p>Dear {{candidate_first_name}},</p>
                
                <p>Your interview for the <strong>{{job_title}}</strong> position has been rescheduled.</p>
                
                <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0;"><strong>Previous Time:</strong> {{old_datetime}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>New Time:</strong> {{interview_datetime}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Location:</strong> {{location}}</p>
                    <p style="margin: 5px 0 0 0;"><strong>Meeting Link:</strong> {{meeting_link}}</p>
                </div>
                
                <p>Please update your calendar accordingly. If you have any conflicts with the new time, please contact us immediately.</p>
                
                <p>We look forward to meeting you at the new scheduled time.</p>
                
                <p>Best regards,<br>
                HR Team<br>
                {{company_name}}</p>
            </div>
        </body>
        </html>';
    }
}

// Helper functions for easy access
function sendInterviewScheduledNotification($interview_id) {
    $emailService = new InterviewEmailNotifications();
    return $emailService->sendInterviewScheduledNotification($interview_id);
}

function sendInterviewReminder($interview_id, $recipient_type = 'both') {
    $emailService = new InterviewEmailNotifications();
    return $emailService->sendInterviewReminder($interview_id, $recipient_type);
}

function sendInterviewCancelledNotification($interview_id, $reason = '') {
    $emailService = new InterviewEmailNotifications();
    return $emailService->sendInterviewCancelledNotification($interview_id, $reason);
}

function sendInterviewRescheduledNotification($interview_id, $old_datetime) {
    $emailService = new InterviewEmailNotifications();
    return $emailService->sendInterviewRescheduledNotification($interview_id, $old_datetime);
}

function sendBulkInterviewReminders($hours_before = 24) {
    $emailService = new InterviewEmailNotifications();
    return $emailService->sendBulkReminders($hours_before);
}

// AJAX endpoint for sending test notifications
if (isset($_POST['action']) && $_POST['action'] === 'send_test_notification') {
    require_once '../includes/auth.php';
    requireRole('admin');
    
    header('Content-Type: application/json');
    
    $interview_id = $_POST['interview_id'];
    $type = $_POST['type'];
    
    $emailService = new InterviewEmailNotifications();
    
    switch ($type) {
        case 'scheduled':
            $result = $emailService->sendInterviewScheduledNotification($interview_id);
            break;
        case 'reminder':
            $result = $emailService->sendInterviewReminder($interview_id);
            break;
        default:
            $result = false;
    }
    
    echo json_encode(['success' => $result]);
    exit;
}
?> 