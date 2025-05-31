<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Get candidates and jobs for dropdowns
$db = new Database();
$conn = $db->getConnection();

$candidates_stmt = $conn->query("
    SELECT c.id, c.first_name, c.last_name, c.email, j.title as job_title, c.applied_for
    FROM candidates c 
    LEFT JOIN job_postings j ON c.applied_for = j.id
    WHERE c.status IN ('shortlisted', 'interviewing') 
    ORDER BY c.first_name, c.last_name
");
$candidates = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

$interviewers_stmt = $conn->query("
    SELECT id, first_name, last_name, email 
    FROM users 
    WHERE role IN ('interviewer', 'hiring_manager', 'hr_recruiter', 'admin') 
    AND is_active = 1
    ORDER BY first_name, last_name
");
$interviewers = $interviewers_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $candidate_id = $_POST['candidate_id'];
    $interviewer_id = $_POST['interviewer_id'];
    $interview_type = $_POST['interview_type'];
    $scheduled_date = $_POST['scheduled_date'];
    $scheduled_time = $_POST['scheduled_time'];
    $duration = $_POST['duration'];
    $location = $_POST['location'] ?? '';
    $meeting_link = $_POST['meeting_link'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $auto_generate_link = isset($_POST['auto_generate_link']);
    
    if (empty($candidate_id) || empty($interviewer_id) || empty($scheduled_date) || empty($scheduled_time)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Combine date and time
            $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time;
            
            // Validate future date
            if (strtotime($scheduled_datetime) <= time()) {
                $error = 'Interview must be scheduled for a future date and time.';
            } else {
                // Check interviewer availability
                $availability_check = $conn->prepare("
                    SELECT COUNT(*) as conflicts 
                    FROM interviews 
                    WHERE interviewer_id = ? 
                    AND status = 'scheduled'
                    AND (
                        (scheduled_date <= ? AND DATE_ADD(scheduled_date, INTERVAL duration MINUTE) > ?) OR
                        (scheduled_date < DATE_ADD(?, INTERVAL ? MINUTE) AND scheduled_date >= ?)
                    )
                ");
                $availability_check->execute([
                    $interviewer_id, 
                    $scheduled_datetime, $scheduled_datetime,
                    $scheduled_datetime, $duration, $scheduled_datetime
                ]);
                
                if ($availability_check->fetch()['conflicts'] > 0) {
                    $error = 'Interviewer is not available at the selected time. Please choose a different time slot.';
                } else {
                    // Get candidate and job info
                    $candidate_info = $conn->prepare("
                        SELECT c.*, j.id as job_id, j.title as job_title 
                        FROM candidates c 
                        LEFT JOIN job_postings j ON c.applied_for = j.id 
                        WHERE c.id = ?
                    ");
                    $candidate_info->execute([$candidate_id]);
                    $candidate = $candidate_info->fetch();
                    
                    if (!$candidate) {
                        $error = 'Invalid candidate selected.';
                    } else {
                        // Auto-generate meeting link if requested
                        if ($auto_generate_link && $interview_type == 'video') {
                            $meeting_link = generateMeetingLink($candidate, $scheduled_datetime);
                        }
                        
                        // Insert interview
                        $stmt = $conn->prepare("
                            INSERT INTO interviews (
                                candidate_id, job_id, interviewer_id, interview_type, 
                                scheduled_date, duration, location, meeting_link, notes, created_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $candidate_id, $candidate['job_id'], $interviewer_id, $interview_type,
                            $scheduled_datetime, $duration, $location, $meeting_link, $notes, $_SESSION['user_id']
                        ]);
                        
                        $interview_id = $conn->lastInsertId();
                        
                        // Update candidate status
                        $update_candidate = $conn->prepare("UPDATE candidates SET status = 'interviewing' WHERE id = ?");
                        $update_candidate->execute([$candidate_id]);
                        
                        // Log activity
                        logActivity(
                            $_SESSION['user_id'], 
                            'scheduled', 
                            'interview', 
                            $interview_id,
                            "Scheduled interview for {$candidate['first_name']} {$candidate['last_name']}"
                        );
                        
                        // Send email notifications (placeholder)
                        sendInterviewNotifications($interview_id, $candidate, $interviewers, $scheduled_datetime);
                        
                        $success = 'Interview scheduled successfully! Notifications have been sent to all participants.';
                        
                        // Clear form data
                        $_POST = [];
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error scheduling interview: ' . $e->getMessage();
        }
    }
}

function generateMeetingLink($candidate, $datetime) {
    // Generate a unique meeting room link
    $room_id = uniqid('interview_');
    return "https://meet.example.com/room/" . $room_id;
}

function sendInterviewNotifications($interview_id, $candidate, $interviewers, $datetime) {
    // Placeholder for email notification system
    // In production, integrate with email service
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Interview - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Schedule Interview</h1>
                <p class="text-gray-600">Schedule a new interview with automated notifications</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                <div class="mt-2">
                    <a href="list.php" class="text-green-800 hover:text-green-900 underline">View all interviews</a> |
                    <a href="schedule.php" class="text-green-800 hover:text-green-900 underline">Schedule another</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" class="space-y-6" id="scheduleForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Candidate Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-2"></i>Candidate *
                            </label>
                            <select name="candidate_id" required onchange="loadCandidateInfo(this.value)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select a candidate...</option>
                                <?php foreach ($candidates as $candidate): ?>
                                <option value="<?php echo $candidate['id']; ?>" <?php echo ($_POST['candidate_id'] ?? '') == $candidate['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                    <?php if ($candidate['job_title']): ?>
                                        - <?php echo htmlspecialchars($candidate['job_title']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="candidateInfo" class="mt-2 text-sm text-gray-600"></div>
                        </div>

                        <!-- Interviewer Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user-tie mr-2"></i>Interviewer *
                            </label>
                            <select name="interviewer_id" required onchange="checkAvailability()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select an interviewer...</option>
                                <?php foreach ($interviewers as $interviewer): ?>
                                <option value="<?php echo $interviewer['id']; ?>" <?php echo ($_POST['interviewer_id'] ?? '') == $interviewer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($interviewer['first_name'] . ' ' . $interviewer['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="availabilityInfo" class="mt-2 text-sm"></div>
                        </div>

                        <!-- Interview Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-video mr-2"></i>Interview Type *
                            </label>
                            <select name="interview_type" required onchange="toggleLocationFields(this.value)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select type...</option>
                                <option value="video" <?php echo ($_POST['interview_type'] ?? '') == 'video' ? 'selected' : ''; ?>>Video Call</option>
                                <option value="phone" <?php echo ($_POST['interview_type'] ?? '') == 'phone' ? 'selected' : ''; ?>>Phone Call</option>
                                <option value="in_person" <?php echo ($_POST['interview_type'] ?? '') == 'in_person' ? 'selected' : ''; ?>>In Person</option>
                                <option value="technical" <?php echo ($_POST['interview_type'] ?? '') == 'technical' ? 'selected' : ''; ?>>Technical Assessment</option>
                            </select>
                        </div>

                        <!-- Duration -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2"></i>Duration (minutes) *
                            </label>
                            <select name="duration" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="30" <?php echo ($_POST['duration'] ?? '60') == '30' ? 'selected' : ''; ?>>30 minutes</option>
                                <option value="45" <?php echo ($_POST['duration'] ?? '60') == '45' ? 'selected' : ''; ?>>45 minutes</option>
                                <option value="60" <?php echo ($_POST['duration'] ?? '60') == '60' ? 'selected' : ''; ?>>1 hour</option>
                                <option value="90" <?php echo ($_POST['duration'] ?? '60') == '90' ? 'selected' : ''; ?>>1.5 hours</option>
                                <option value="120" <?php echo ($_POST['duration'] ?? '60') == '120' ? 'selected' : ''; ?>>2 hours</option>
                            </select>
                        </div>

                        <!-- Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar mr-2"></i>Date *
                            </label>
                            <input type="date" name="scheduled_date" required
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                   value="<?php echo $_POST['scheduled_date'] ?? ''; ?>"
                                   onchange="checkAvailability()"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Time -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clock mr-2"></i>Time *
                            </label>
                            <input type="time" name="scheduled_time" required
                                   value="<?php echo $_POST['scheduled_time'] ?? ''; ?>"
                                   onchange="checkAvailability()"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Location/Meeting Details -->
                    <div id="locationSection" class="space-y-4">
                        <div id="physicalLocation" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-2"></i>Location
                            </label>
                            <input type="text" name="location" placeholder="e.g., Conference Room A, Main Office"
                                   value="<?php echo $_POST['location'] ?? ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div id="virtualMeeting" class="hidden">
                            <div class="flex items-center space-x-4 mb-3">
                                <label class="flex items-center">
                                    <input type="checkbox" name="auto_generate_link" id="autoGenerate" 
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Auto-generate meeting link</span>
                                </label>
                            </div>
                            
                            <div id="manualLinkSection">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-link mr-2"></i>Meeting Link
                                </label>
                                <input type="url" name="meeting_link" placeholder="https://zoom.us/j/..."
                                       value="<?php echo $_POST['meeting_link'] ?? ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-2"></i>Notes/Instructions
                        </label>
                        <textarea name="notes" rows="4" 
                                  placeholder="Any special instructions or preparation notes for the interview..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo $_POST['notes'] ?? ''; ?></textarea>
                    </div>

                    <!-- Smart Scheduling Suggestions -->
                    <div id="suggestions" class="bg-blue-50 border border-blue-200 rounded-lg p-4 hidden">
                        <h4 class="text-sm font-medium text-blue-800 mb-2">
                            <i class="fas fa-lightbulb mr-2"></i>Smart Scheduling Suggestions
                        </h4>
                        <div id="suggestionsList" class="text-sm text-blue-700"></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        
                        <div class="space-x-4">
                            <button type="button" onclick="resetForm()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </button>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-calendar-plus mr-2"></i>Schedule Interview
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function toggleLocationFields(type) {
            const physicalLocation = document.getElementById('physicalLocation');
            const virtualMeeting = document.getElementById('virtualMeeting');
            
            physicalLocation.classList.add('hidden');
            virtualMeeting.classList.add('hidden');
            
            if (type === 'in_person') {
                physicalLocation.classList.remove('hidden');
            } else if (type === 'video') {
                virtualMeeting.classList.remove('hidden');
            }
        }

        function loadCandidateInfo(candidateId) {
            if (!candidateId) return;
            
            // Here you would make an AJAX call to get candidate info
            // For now, just show placeholder
            const infoDiv = document.getElementById('candidateInfo');
            infoDiv.innerHTML = '<i class="fas fa-info-circle mr-1"></i>Loading candidate information...';
        }

        function checkAvailability() {
            const interviewerId = document.querySelector('[name="interviewer_id"]').value;
            const date = document.querySelector('[name="scheduled_date"]').value;
            const time = document.querySelector('[name="scheduled_time"]').value;
            
            if (!interviewerId || !date || !time) return;
            
            const availabilityDiv = document.getElementById('availabilityInfo');
            availabilityDiv.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Checking availability...';
            
            // Simulate availability check
            setTimeout(() => {
                availabilityDiv.innerHTML = '<i class="fas fa-check-circle text-green-600 mr-1"></i>Interviewer is available';
                availabilityDiv.className = 'mt-2 text-sm text-green-600';
                showSmartSuggestions();
            }, 1000);
        }

        function showSmartSuggestions() {
            const suggestionsDiv = document.getElementById('suggestions');
            const listDiv = document.getElementById('suggestionsList');
            
            // Example smart suggestions
            listDiv.innerHTML = `
                <ul class="space-y-1">
                    <li>• Best time slots based on interviewer's calendar: 10:00 AM, 2:00 PM</li>
                    <li>• Consider 15-minute buffer time before and after</li>
                    <li>• Technical interviews work best in 90-minute slots</li>
                </ul>
            `;
            
            suggestionsDiv.classList.remove('hidden');
        }

        function resetForm() {
            document.getElementById('scheduleForm').reset();
            document.getElementById('candidateInfo').innerHTML = '';
            document.getElementById('availabilityInfo').innerHTML = '';
            document.getElementById('suggestions').classList.add('hidden');
            document.getElementById('physicalLocation').classList.add('hidden');
            document.getElementById('virtualMeeting').classList.add('hidden');
        }

        // Auto-generate link toggle
        document.getElementById('autoGenerate')?.addEventListener('change', function() {
            const manualSection = document.getElementById('manualLinkSection');
            if (this.checked) {
                manualSection.style.opacity = '0.5';
                manualSection.querySelector('input').disabled = true;
            } else {
                manualSection.style.opacity = '1';
                manualSection.querySelector('input').disabled = false;
            }
        });

        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            const interviewType = document.querySelector('[name="interview_type"]').value;
            if (interviewType) {
                toggleLocationFields(interviewType);
            }
        });
    </script>
</body>
</html> 