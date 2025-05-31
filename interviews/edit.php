<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$interview_id = $_GET['id'] ?? null;

if (!$interview_id) {
    header('Location: list.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get interview details
$query = "
    SELECT i.*, 
           c.first_name as candidate_first, c.last_name as candidate_last,
           j.title as job_title
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    WHERE i.id = ?
";

$stmt = $conn->prepare($query);
$stmt->execute([$interview_id]);
$interview = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$interview) {
    header('Location: list.php?error=Interview not found');
    exit;
}

// Only allow editing scheduled interviews
if ($interview['status'] !== 'scheduled') {
    header('Location: view.php?id=' . $interview_id . '&error=Only scheduled interviews can be edited');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $scheduled_date = $_POST['scheduled_date'];
    $interview_type = $_POST['interview_type'];
    $duration = $_POST['duration'];
    $location = $_POST['location'];
    $meeting_link = $_POST['meeting_link'];
    $interviewer_id = $_POST['interviewer_id'];
    $notes = $_POST['notes'];
    
    try {
        $update_query = "
            UPDATE interviews 
            SET scheduled_date = ?, interview_type = ?, duration = ?, location = ?, 
                meeting_link = ?, interviewer_id = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->execute([
            $scheduled_date, $interview_type, $duration, $location, 
            $meeting_link, $interviewer_id, $notes, $interview_id
        ]);
        
        // Log activity
        logActivity($conn, $_SESSION['user_id'], 'interview_updated', "Updated interview for {$interview['candidate_first']} {$interview['candidate_last']}");
        
        header('Location: view.php?id=' . $interview_id . '&success=Interview updated successfully');
        exit;
        
    } catch (Exception $e) {
        $error = "Error updating interview: " . $e->getMessage();
    }
}

// Get all candidates for dropdown
$candidates_stmt = $conn->query("
    SELECT id, first_name, last_name, email 
    FROM candidates 
    WHERE status IN ('new', 'shortlisted', 'interviewing')
    ORDER BY first_name, last_name
");
$candidates = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active jobs for dropdown
$jobs_stmt = $conn->query("
    SELECT id, title, department 
    FROM job_postings 
    WHERE status = 'active' 
    ORDER BY title
");
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all interviewers for dropdown
$interviewers_stmt = $conn->query("
    SELECT id, first_name, last_name 
    FROM users 
    WHERE role IN ('interviewer', 'hiring_manager', 'hr_recruiter', 'admin') 
    ORDER BY first_name, last_name
");
$interviewers = $interviewers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Interview - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Edit Interview</h1>
                        <p class="text-gray-600">
                            <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?> 
                            for <?php echo htmlspecialchars($interview['job_title']); ?>
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="view.php?id=<?php echo $interview_id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Details
                        </a>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-list mr-2"></i>Back to List
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" class="space-y-6">
                    <!-- Date and Time -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="scheduled_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Date & Time <span class="text-red-500">*</span>
                            </label>
                            <input type="datetime-local" 
                                   name="scheduled_date" 
                                   id="scheduled_date"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($interview['scheduled_date'])); ?>"
                                   min="<?php echo date('Y-m-d\TH:i'); ?>"
                                   required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label for="duration" class="block text-sm font-medium text-gray-700 mb-2">
                                Duration (minutes) <span class="text-red-500">*</span>
                            </label>
                            <select name="duration" 
                                    id="duration" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Duration</option>
                                <option value="15" <?php echo $interview['duration'] == 15 ? 'selected' : ''; ?>>15 minutes</option>
                                <option value="30" <?php echo $interview['duration'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                                <option value="45" <?php echo $interview['duration'] == 45 ? 'selected' : ''; ?>>45 minutes</option>
                                <option value="60" <?php echo $interview['duration'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                                <option value="90" <?php echo $interview['duration'] == 90 ? 'selected' : ''; ?>>1.5 hours</option>
                                <option value="120" <?php echo $interview['duration'] == 120 ? 'selected' : ''; ?>>2 hours</option>
                            </select>
                        </div>
                    </div>

                    <!-- Interview Type and Interviewer -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="interview_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Interview Type <span class="text-red-500">*</span>
                            </label>
                            <select name="interview_type" 
                                    id="interview_type" 
                                    required
                                    onchange="toggleLocationFields()"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Type</option>
                                <option value="phone" <?php echo $interview['interview_type'] == 'phone' ? 'selected' : ''; ?>>Phone Interview</option>
                                <option value="video" <?php echo $interview['interview_type'] == 'video' ? 'selected' : ''; ?>>Video Interview</option>
                                <option value="in_person" <?php echo $interview['interview_type'] == 'in_person' ? 'selected' : ''; ?>>In-Person Interview</option>
                                <option value="technical" <?php echo $interview['interview_type'] == 'technical' ? 'selected' : ''; ?>>Technical Interview</option>
                                <option value="panel" <?php echo $interview['interview_type'] == 'panel' ? 'selected' : ''; ?>>Panel Interview</option>
                            </select>
                        </div>

                        <div>
                            <label for="interviewer_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Interviewer <span class="text-red-500">*</span>
                            </label>
                            <select name="interviewer_id" 
                                    id="interviewer_id" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select Interviewer</option>
                                <?php foreach ($interviewers as $interviewer): ?>
                                <option value="<?php echo $interviewer['id']; ?>" 
                                        <?php echo $interview['interviewer_id'] == $interviewer['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($interviewer['first_name'] . ' ' . $interviewer['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Location / Meeting Details -->
                    <div id="location-field">
                        <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
                            Location
                        </label>
                        <input type="text" 
                               name="location" 
                               id="location"
                               value="<?php echo htmlspecialchars($interview['location']); ?>"
                               placeholder="Enter interview location (office address, room number, etc.)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div id="meeting-link-field">
                        <label for="meeting_link" class="block text-sm font-medium text-gray-700 mb-2">
                            Meeting Link
                        </label>
                        <input type="url" 
                               name="meeting_link" 
                               id="meeting_link"
                               value="<?php echo htmlspecialchars($interview['meeting_link']); ?>"
                               placeholder="Enter video meeting link (Zoom, Google Meet, etc.)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Interview Notes
                        </label>
                        <textarea name="notes" 
                                  id="notes" 
                                  rows="4"
                                  placeholder="Add any additional notes or preparation instructions..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($interview['notes']); ?></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <a href="view.php?id=<?php echo $interview_id; ?>" 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            Cancel
                        </a>
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i>Update Interview
                        </button>
                    </div>
                </form>
            </div>

            <!-- Interview History -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Interview Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-700">Candidate:</span>
                        <span class="text-gray-900">
                            <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?>
                        </span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Position:</span>
                        <span class="text-gray-900"><?php echo htmlspecialchars($interview['job_title']); ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-700">Status:</span>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                            <?php echo ucfirst($interview['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleLocationFields() {
            const type = document.getElementById('interview_type').value;
            const locationField = document.getElementById('location-field');
            const meetingField = document.getElementById('meeting-link-field');
            
            if (type === 'video' || type === 'technical') {
                locationField.style.display = 'none';
                meetingField.style.display = 'block';
                document.getElementById('location').required = false;
                document.getElementById('meeting_link').required = true;
            } else if (type === 'in_person' || type === 'panel') {
                locationField.style.display = 'block';
                meetingField.style.display = 'none';
                document.getElementById('location').required = true;
                document.getElementById('meeting_link').required = false;
            } else if (type === 'phone') {
                locationField.style.display = 'none';
                meetingField.style.display = 'none';
                document.getElementById('location').required = false;
                document.getElementById('meeting_link').required = false;
            } else {
                locationField.style.display = 'block';
                meetingField.style.display = 'block';
                document.getElementById('location').required = false;
                document.getElementById('meeting_link').required = false;
            }
        }

        // Initialize field visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleLocationFields();
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const scheduledDate = new Date(document.getElementById('scheduled_date').value);
            const now = new Date();

            if (scheduledDate <= now) {
                e.preventDefault();
                alert('Please select a future date and time for the interview.');
                return false;
            }

            const type = document.getElementById('interview_type').value;
            const location = document.getElementById('location').value.trim();
            const meetingLink = document.getElementById('meeting_link').value.trim();

            if ((type === 'in_person' || type === 'panel') && !location) {
                e.preventDefault();
                alert('Please provide a location for in-person interviews.');
                return false;
            }

            if ((type === 'video' || type === 'technical') && !meetingLink) {
                e.preventDefault();
                alert('Please provide a meeting link for video interviews.');
                return false;
            }

            return true;
        });
    </script>
</body>
</html> 