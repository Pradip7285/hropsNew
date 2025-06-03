<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission - only HR recruiters and admins can manage reminders
requireRole('hr_recruiter');

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'send_reminder') {
        $interview_id = $_POST['interview_id'];
        $reminder_type = $_POST['reminder_type']; // 'interviewer' or 'candidate'
        $custom_message = $_POST['custom_message'] ?? '';
        
        try {
            // Get interview details
            $interview_stmt = $conn->prepare("
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
            $interview_stmt->execute([$interview_id]);
            $interview = $interview_stmt->fetch();
            
            if ($interview) {
                $to_email = $reminder_type == 'candidate' ? $interview['candidate_email'] : $interview['interviewer_email'];
                $to_name = $reminder_type == 'candidate' 
                    ? $interview['candidate_first'] . ' ' . $interview['candidate_last']
                    : $interview['interviewer_first'] . ' ' . $interview['interviewer_last'];
                
                // Send reminder email (placeholder - implement actual email sending)
                $email_sent = sendInterviewReminder($to_email, $to_name, $interview, $reminder_type, $custom_message);
                
                if ($email_sent) {
                    // Log the reminder
                    logActivity($_SESSION['user_id'], 'reminder_sent', 'interview', $interview_id, 
                        "Sent $reminder_type reminder for interview with {$interview['candidate_first']} {$interview['candidate_last']}");
                    
                    $success = "Reminder sent successfully to $to_name";
                } else {
                    $error = 'Failed to send reminder email.';
                }
            } else {
                $error = 'Interview not found.';
            }
        } catch (Exception $e) {
            $error = 'Error sending reminder: ' . $e->getMessage();
        }
    } elseif ($action == 'bulk_remind') {
        $interview_ids = $_POST['interview_ids'] ?? [];
        $reminder_type = $_POST['bulk_reminder_type'];
        
        if (empty($interview_ids)) {
            $error = 'Please select interviews to send reminders for.';
        } else {
            try {
                $sent_count = 0;
                foreach ($interview_ids as $interview_id) {
                    // Get interview details
                    $interview_stmt = $conn->prepare("
                        SELECT i.*, 
                               c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
                               u.first_name as interviewer_first, u.last_name as interviewer_last, u.email as interviewer_email
                        FROM interviews i
                        JOIN candidates c ON i.candidate_id = c.id
                        JOIN users u ON i.interviewer_id = u.id
                        WHERE i.id = ?
                    ");
                    $interview_stmt->execute([$interview_id]);
                    $interview = $interview_stmt->fetch();
                    
                    if ($interview) {
                        $to_email = $reminder_type == 'candidate' ? $interview['candidate_email'] : $interview['interviewer_email'];
                        $to_name = $reminder_type == 'candidate' 
                            ? $interview['candidate_first'] . ' ' . $interview['candidate_last']
                            : $interview['interviewer_first'] . ' ' . $interview['interviewer_last'];
                        
                        if (sendInterviewReminder($to_email, $to_name, $interview, $reminder_type)) {
                            $sent_count++;
                        }
                    }
                }
                
                logActivity($_SESSION['user_id'], 'bulk_reminder_sent', 'interview', 0, 
                    "Sent bulk $reminder_type reminders for $sent_count interviews");
                
                $success = "Successfully sent $sent_count reminder(s).";
            } catch (Exception $e) {
                $error = 'Error sending bulk reminders: ' . $e->getMessage();
            }
        }
    }
}

// Get upcoming interviews that need reminders (next 24-48 hours)
$upcoming_interviews_stmt = $conn->query("
    SELECT i.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
           j.title as job_title,
           u.first_name as interviewer_first, u.last_name as interviewer_last, u.email as interviewer_email,
           TIMESTAMPDIFF(HOUR, NOW(), i.scheduled_date) as hours_until
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN users u ON i.interviewer_id = u.id
    WHERE i.status = 'scheduled'
      AND i.scheduled_date > NOW()
      AND i.scheduled_date <= DATE_ADD(NOW(), INTERVAL 48 HOUR)
    ORDER BY i.scheduled_date ASC
");
$upcoming_interviews = $upcoming_interviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reminder statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_upcoming,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, NOW(), scheduled_date) <= 24 THEN 1 ELSE 0 END) as within_24h,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, NOW(), scheduled_date) <= 2 THEN 1 ELSE 0 END) as within_2h
    FROM interviews 
    WHERE status = 'scheduled' AND scheduled_date > NOW() AND scheduled_date <= DATE_ADD(NOW(), INTERVAL 48 HOUR)
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

function sendInterviewReminder($to_email, $to_name, $interview, $type, $custom_message = '') {
    // This is a placeholder function - implement actual email sending
    // You would integrate with your email service (SMTP, SendGrid, etc.)
    
    $subject = $type == 'candidate' 
        ? "Interview Reminder: {$interview['job_title']}"
        : "Interview Reminder: Interview with {$interview['candidate_first']} {$interview['candidate_last']}";
    
    $default_message = $type == 'candidate'
        ? "Hi {$to_name},\n\nThis is a reminder about your upcoming interview for the {$interview['job_title']} position scheduled for " . date('F j, Y \a\t g:i A', strtotime($interview['scheduled_date'])) . "."
        : "Hi {$to_name},\n\nThis is a reminder about your upcoming interview with {$interview['candidate_first']} {$interview['candidate_last']} for the {$interview['job_title']} position scheduled for " . date('F j, Y \a\t g:i A', strtotime($interview['scheduled_date'])) . ".";
    
    $message = $custom_message ?: $default_message;
    
    // Add interview details
    $message .= "\n\nInterview Details:\n";
    $message .= "Date & Time: " . date('F j, Y \a\t g:i A', strtotime($interview['scheduled_date'])) . "\n";
    $message .= "Duration: {$interview['duration']} minutes\n";
    $message .= "Type: " . ucfirst(str_replace('_', ' ', $interview['interview_type'])) . "\n";
    
    if ($interview['location']) {
        $message .= "Location: {$interview['location']}\n";
    }
    
    if ($interview['meeting_link']) {
        $message .= "Meeting Link: {$interview['meeting_link']}\n";
    }
    
    // For demo purposes, we'll return true
    // In real implementation, you would use mail() or a mail library
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Reminders - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Interview Reminders</h1>
                        <p class="text-gray-600">Send reminders for upcoming interviews</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        <a href="calendar.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-calendar mr-2"></i>Calendar
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <!-- Reminder Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-clock text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Next 48 Hours</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_upcoming']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Next 24 Hours</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['within_24h']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-red-100 p-2 rounded-full mr-3">
                            <i class="fas fa-bell text-red-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Next 2 Hours</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['within_2h']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Interviews -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-bell text-yellow-500 mr-2"></i>
                            Upcoming Interviews (<?php echo count($upcoming_interviews); ?>)
                        </h2>
                        <?php if (!empty($upcoming_interviews)): ?>
                        <div class="flex space-x-2">
                            <button onclick="selectAll()" class="text-sm text-blue-600 hover:text-blue-800">
                                Select All
                            </button>
                            <button onclick="showBulkReminderModal()" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-envelope mr-1"></i>Bulk Remind
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($upcoming_interviews)): ?>
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-check-circle text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold mb-2">All caught up!</h3>
                    <p>No upcoming interviews in the next 48 hours.</p>
                </div>
                <?php else: ?>
                <form method="POST" id="bulkReminderForm">
                    <input type="hidden" name="action" value="bulk_remind">
                    <input type="hidden" name="bulk_reminder_type" id="bulkReminderType">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Until</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interview</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidate</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interviewer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($upcoming_interviews as $interview): ?>
                                <tr class="hover:bg-gray-50 <?php echo $interview['hours_until'] <= 2 ? 'bg-red-50' : ($interview['hours_until'] <= 24 ? 'bg-yellow-50' : ''); ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="interview_ids[]" value="<?php echo $interview['id']; ?>" class="interview-checkbox">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php 
                                            if ($interview['hours_until'] < 1) {
                                                echo "< 1 hour";
                                            } elseif ($interview['hours_until'] < 24) {
                                                echo round($interview['hours_until']) . " hours";
                                            } else {
                                                echo round($interview['hours_until'] / 24, 1) . " days";
                                            }
                                            ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($interview['scheduled_date'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($interview['job_title']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo ucfirst(str_replace('_', ' ', $interview['interview_type'])); ?> • <?php echo $interview['duration']; ?> min</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($interview['candidate_email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($interview['interviewer_first'] . ' ' . $interview['interviewer_last']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($interview['interviewer_email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="showReminderModal(<?php echo $interview['id']; ?>, 'candidate')" 
                                                    class="text-blue-600 hover:text-blue-900" title="Remind Candidate">
                                                <i class="fas fa-user"></i>
                                            </button>
                                            <button onclick="showReminderModal(<?php echo $interview['id']; ?>, 'interviewer')" 
                                                    class="text-green-600 hover:text-green-900" title="Remind Interviewer">
                                                <i class="fas fa-user-tie"></i>
                                            </button>
                                            <a href="view.php?id=<?php echo $interview['id']; ?>" 
                                               class="text-gray-600 hover:text-gray-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Reminder Modal -->
    <div id="reminderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Send Reminder</h3>
                    <button onclick="closeReminderModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" id="reminderForm">
                    <input type="hidden" name="action" value="send_reminder">
                    <input type="hidden" name="interview_id" id="reminderInterviewId">
                    <input type="hidden" name="reminder_type" id="reminderType">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Custom Message (Optional)</label>
                        <textarea name="custom_message" id="customMessage" rows="4"
                                  placeholder="Leave blank to use default reminder message..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        <p class="text-xs text-gray-500 mt-1">If left blank, a standard reminder will be sent with interview details.</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeReminderModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-envelope mr-2"></i>Send Reminder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Reminder Modal -->
    <div id="bulkReminderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Send Bulk Reminders</h3>
                    <button onclick="closeBulkReminderModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Send To</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="bulk_type" value="candidate" checked class="mr-2">
                            <span>Candidates</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="bulk_type" value="interviewer" class="mr-2">
                            <span>Interviewers</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeBulkReminderModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        Cancel
                    </button>
                    <button onclick="sendBulkReminders()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-envelope mr-2"></i>Send Reminders
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showReminderModal(interviewId, type) {
            document.getElementById('reminderInterviewId').value = interviewId;
            document.getElementById('reminderType').value = type;
            document.getElementById('reminderModal').classList.remove('hidden');
            document.getElementById('customMessage').focus();
        }

        function closeReminderModal() {
            document.getElementById('reminderModal').classList.add('hidden');
            document.getElementById('customMessage').value = '';
        }

        function showBulkReminderModal() {
            const checked = document.querySelectorAll('.interview-checkbox:checked');
            if (checked.length === 0) {
                alert('Please select interviews to send reminders for.');
                return;
            }
            document.getElementById('bulkReminderModal').classList.remove('hidden');
        }

        function closeBulkReminderModal() {
            document.getElementById('bulkReminderModal').classList.add('hidden');
        }

        function sendBulkReminders() {
            const selectedType = document.querySelector('input[name="bulk_type"]:checked').value;
            document.getElementById('bulkReminderType').value = selectedType;
            document.getElementById('bulkReminderForm').submit();
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.interview-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = true);
            document.getElementById('selectAllCheckbox').checked = true;
        }

        function toggleAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.interview-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
        }
    </script>
</body>
</html> 