<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Email notification functions for offer management

/**
 * Send offer letter to candidate
 */
function sendOfferEmail($offer_id) {
    global $conn;
    
    // Get offer details
    $offer_stmt = $conn->prepare("
        SELECT o.*, 
               c.first_name, c.last_name, c.email as candidate_email,
               j.title as job_title, j.department,
               ot.content as template_content
        FROM offers o
        JOIN candidates c ON o.candidate_id = c.id
        JOIN job_postings j ON o.job_id = j.id
        LEFT JOIN offer_templates ot ON o.template_id = ot.id
        WHERE o.id = ?
    ");
    $offer_stmt->execute([$offer_id]);
    $offer = $offer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        return false;
    }
    
    // Generate secure response link
    $response_url = BASE_URL . '/offers/response.php?token=' . $offer['response_token'];
    
    // Email subject
    $subject = "Job Offer - {$offer['job_title']} at " . APP_NAME;
    
    // Email body
    $email_body = generateOfferEmailContent($offer, $response_url);
    
    // Send email (placeholder - implement with your email system)
    $email_sent = sendEmail(
        $offer['candidate_email'],
        $offer['first_name'] . ' ' . $offer['last_name'],
        $subject,
        $email_body
    );
    
    if ($email_sent) {
        // Log notification
        $notification_stmt = $conn->prepare("
            INSERT INTO offer_notifications (offer_id, notification_type, recipient_email, delivery_status)
            VALUES (?, 'offer_sent', ?, 'sent')
        ");
        $notification_stmt->execute([$offer_id, $offer['candidate_email']]);
        
        // Update offer status
        $conn->prepare("UPDATE offers SET status = 'sent' WHERE id = ?")->execute([$offer_id]);
        
        return true;
    }
    
    return false;
}

/**
 * Send offer reminder to candidate
 */
function sendOfferReminder($offer_id) {
    global $conn;
    
    $offer_stmt = $conn->prepare("
        SELECT o.*, 
               c.first_name, c.last_name, c.email as candidate_email,
               j.title as job_title,
               DATEDIFF(o.valid_until, CURDATE()) as days_remaining
        FROM offers o
        JOIN candidates c ON o.candidate_id = c.id
        JOIN job_postings j ON o.job_id = j.id
        WHERE o.id = ? AND o.status = 'sent'
    ");
    $offer_stmt->execute([$offer_id]);
    $offer = $offer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        return false;
    }
    
    $response_url = BASE_URL . '/offers/response.php?token=' . $offer['response_token'];
    $days_remaining = max(0, $offer['days_remaining']);
    
    $subject = "Reminder: Job Offer Response Required - {$offer['job_title']}";
    
    $email_body = "
    <html>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>
            <h2 style='color: #333; margin-bottom: 20px;'>Offer Response Reminder</h2>
            
            <p>Dear {$offer['first_name']},</p>
            
            <p>This is a friendly reminder that your job offer for the position of <strong>{$offer['job_title']}</strong> at " . APP_NAME . " is awaiting your response.</p>
            
            <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <strong>Time Remaining:</strong> {$days_remaining} day(s)
            </div>
            
            <p>Please take a moment to review and respond to your offer:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$response_url}' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    Review & Respond to Offer
                </a>
            </div>
            
            <p>If you have any questions about the offer, please don't hesitate to contact our HR team.</p>
            
            <p>Best regards,<br>
            HR Team<br>
            " . APP_NAME . "</p>
        </div>
    </body>
    </html>
    ";
    
    $email_sent = sendEmail(
        $offer['candidate_email'],
        $offer['first_name'] . ' ' . $offer['last_name'],
        $subject,
        $email_body
    );
    
    if ($email_sent) {
        $notification_stmt = $conn->prepare("
            INSERT INTO offer_notifications (offer_id, notification_type, recipient_email, delivery_status)
            VALUES (?, 'reminder', ?, 'sent')
        ");
        $notification_stmt->execute([$offer_id, $offer['candidate_email']]);
        return true;
    }
    
    return false;
}

/**
 * Send notification to HR about candidate response
 */
function sendResponseNotificationToHR($offer_id, $response_type, $comments = '') {
    global $conn;
    
    $offer_stmt = $conn->prepare("
        SELECT o.*, 
               c.first_name, c.last_name, c.email as candidate_email,
               j.title as job_title,
               creator.email as creator_email, creator.first_name as creator_first, creator.last_name as creator_last
        FROM offers o
        JOIN candidates c ON o.candidate_id = c.id
        JOIN job_postings j ON o.job_id = j.id
        JOIN users creator ON o.created_by = creator.id
        WHERE o.id = ?
    ");
    $offer_stmt->execute([$offer_id]);
    $offer = $offer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        return false;
    }
    
    $response_labels = [
        'accept' => 'Accepted',
        'reject' => 'Declined',
        'negotiate' => 'Requested Negotiation'
    ];
    
    $response_label = $response_labels[$response_type] ?? 'Responded to';
    $subject = "Candidate Response: {$offer['first_name']} {$offer['last_name']} {$response_label} Offer";
    
    $email_body = "
    <html>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>
            <h2 style='color: #333; margin-bottom: 20px;'>Candidate Offer Response</h2>
            
            <div style='background: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;'>
                <h3 style='margin-top: 0;'>Offer Details</h3>
                <ul style='list-style: none; padding: 0;'>
                    <li><strong>Candidate:</strong> {$offer['first_name']} {$offer['last_name']}</li>
                    <li><strong>Position:</strong> {$offer['job_title']}</li>
                    <li><strong>Response:</strong> <span style='color: " . ($response_type == 'accept' ? '#28a745' : ($response_type == 'reject' ? '#dc3545' : '#ffc107')) . ";'>{$response_label}</span></li>
                    <li><strong>Salary Offered:</strong> $" . number_format($offer['salary_offered'], 0) . "</li>
                </ul>
            </div>
            
            " . ($comments ? "
            <div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>
                <h4 style='margin-top: 0;'>Candidate Comments:</h4>
                <p style='margin-bottom: 0;'>" . nl2br(htmlspecialchars($comments)) . "</p>
            </div>
            " : "") . "
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . BASE_URL . "/offers/view.php?id={$offer_id}' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                    View Full Offer Details
                </a>
            </div>
            
            <p style='color: #666; font-size: 14px;'>
                This notification was sent automatically when the candidate responded to their offer.
            </p>
        </div>
    </body>
    </html>
    ";
    
    // Send to offer creator and HR team
    $recipients = [
        $offer['creator_email'] => $offer['creator_first'] . ' ' . $offer['creator_last']
    ];
    
    // Add additional HR emails from settings (in production)
    $hr_emails = ['hr@company.com']; // Get from system settings
    foreach ($hr_emails as $hr_email) {
        $recipients[$hr_email] = 'HR Team';
    }
    
    $success = true;
    foreach ($recipients as $email => $name) {
        $email_sent = sendEmail($email, $name, $subject, $email_body);
        if (!$email_sent) {
            $success = false;
        }
    }
    
    if ($success) {
        $notification_stmt = $conn->prepare("
            INSERT INTO offer_notifications (offer_id, notification_type, recipient_email, delivery_status)
            VALUES (?, ?, 'hr-team', 'sent')
        ");
        $notification_stmt->execute([$offer_id, $response_type]);
    }
    
    return $success;
}

/**
 * Generate offer email content
 */
function generateOfferEmailContent($offer, $response_url) {
    $content = "
    <html>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #333; margin-bottom: 10px;'>" . APP_NAME . "</h1>
                <h2 style='color: #007bff; margin-top: 0;'>Job Offer</h2>
            </div>
            
            <p>Dear {$offer['first_name']},</p>
            
            <p>We are pleased to offer you the position of <strong>{$offer['job_title']}</strong> with our " . (isset($offer['department']) ? $offer['department'] . " team at " : "") . APP_NAME . ".</p>
            
            <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #333;'>Offer Summary</h3>
                <ul style='list-style: none; padding: 0;'>
                    <li style='padding: 5px 0; border-bottom: 1px solid #eee;'><strong>Position:</strong> {$offer['job_title']}</li>
                    " . (isset($offer['department']) ? "<li style='padding: 5px 0; border-bottom: 1px solid #eee;'><strong>Department:</strong> {$offer['department']}</li>" : "") . "
                    <li style='padding: 5px 0; border-bottom: 1px solid #eee;'><strong>Annual Salary:</strong> $" . number_format($offer['salary_offered'], 0) . "</li>
                    " . ($offer['start_date'] ? "<li style='padding: 5px 0; border-bottom: 1px solid #eee;'><strong>Start Date:</strong> " . date('F j, Y', strtotime($offer['start_date'])) . "</li>" : "") . "
                    " . ($offer['valid_until'] ? "<li style='padding: 5px 0;'><strong>Response Required By:</strong> " . date('F j, Y', strtotime($offer['valid_until'])) . "</li>" : "") . "
                </ul>
            </div>
            
            " . ($offer['benefits'] ? "
            <div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4 style='margin-top: 0;'>Benefits Package</h4>
                <p style='margin-bottom: 0;'>" . nl2br(htmlspecialchars($offer['benefits'])) . "</p>
            </div>
            " : "") . "
            
            " . ($offer['custom_terms'] ? "
            <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4 style='margin-top: 0;'>Additional Terms</h4>
                <p style='margin-bottom: 0;'>" . nl2br(htmlspecialchars($offer['custom_terms'])) . "</p>
            </div>
            " : "") . "
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$response_url}' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 16px; font-weight: bold;'>
                    Review & Respond to Offer
                </a>
            </div>
            
            <div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4 style='margin-top: 0; color: #0c5460;'>Next Steps</h4>
                <ul style='margin-bottom: 0; color: #0c5460;'>
                    <li>Click the link above to review the complete offer details</li>
                    <li>You can accept, decline, or request negotiation</li>
                    <li>Please respond by " . ($offer['valid_until'] ? date('F j, Y', strtotime($offer['valid_until'])) : 'the specified deadline') . "</li>
                    <li>Contact us if you have any questions</li>
                </ul>
            </div>
            
            <p>We're excited about the possibility of you joining our team and look forward to your response.</p>
            
            <p>Best regards,<br>
            HR Department<br>
            " . APP_NAME . "</p>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;'>
                <p>This offer is confidential and intended solely for the named recipient. Please do not share this information.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $content;
}

/**
 * Send bulk reminders for expiring offers
 */
function sendBulkOfferReminders($days_before_expiry = 3) {
    global $conn;
    
    $offers_stmt = $conn->prepare("
        SELECT id FROM offers 
        WHERE status = 'sent' 
        AND valid_until IS NOT NULL 
        AND DATEDIFF(valid_until, CURDATE()) = ?
        AND id NOT IN (
            SELECT offer_id FROM offer_notifications 
            WHERE notification_type = 'reminder' 
            AND DATE(sent_at) = CURDATE()
        )
    ");
    $offers_stmt->execute([$days_before_expiry]);
    $offers = $offers_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $sent_count = 0;
    foreach ($offers as $offer_id) {
        if (sendOfferReminder($offer_id)) {
            $sent_count++;
        }
    }
    
    return $sent_count;
}

/**
 * Placeholder email sending function
 * In production, integrate with your email service (SMTP, SendGrid, etc.)
 */
function sendEmail($to_email, $to_name, $subject, $body, $from_email = null, $from_name = null) {
    // Configuration
    $from_email = $from_email ?: 'hr@company.com';
    $from_name = $from_name ?: APP_NAME . ' HR Team';
    
    // Headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: {$from_name} <{$from_email}>",
        "Reply-To: {$from_email}",
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // In production, use proper email service
    // For now, this is a placeholder that logs the email
    error_log("Email would be sent to: {$to_email}");
    error_log("Subject: {$subject}");
    
    // Uncomment for actual email sending:
    // return mail($to_email, $subject, $body, implode("\r\n", $headers));
    
    // For demo purposes, return true
    return true;
}

// Auto-reminder system (can be called via cron job)
if (isset($_GET['action']) && $_GET['action'] === 'auto_reminders') {
    require_once '../includes/auth.php';
    requireRole('admin'); // Only admin can trigger auto-reminders
    
    $sent_count = sendBulkOfferReminders(3); // 3 days before expiry
    echo json_encode(['sent' => $sent_count]);
    exit;
}
?> 