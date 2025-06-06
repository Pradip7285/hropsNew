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
           c.email as candidate_email, c.phone as candidate_phone,
           c.linkedin_url, c.skills as candidate_skills,
           j.title as job_title, j.department, j.location as job_location,
           u.first_name as interviewer_first, u.last_name as interviewer_last,
           u.email as interviewer_email,
           creator.first_name as created_by_first, creator.last_name as created_by_last
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN users u ON i.interviewer_id = u.id
    LEFT JOIN users creator ON i.created_by = creator.id
    WHERE i.id = ?
";

$stmt = $conn->prepare($query);
$stmt->execute([$interview_id]);
$interview = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$interview) {
    header('Location: list.php?error=Interview not found');
    exit;
}

// Get feedback if exists
$feedback_stmt = $conn->prepare("SELECT * FROM interview_feedback WHERE interview_id = ?");
$feedback_stmt->execute([$interview_id]);
$feedback = $feedback_stmt->fetch(PDO::FETCH_ASSOC);

// Get related interviews for this candidate
$related_stmt = $conn->prepare("
    SELECT i.id, i.scheduled_date, i.interview_type, i.status, j.title 
    FROM interviews i 
    JOIN job_postings j ON i.job_id = j.id
    WHERE i.candidate_id = ? AND i.id != ?
    ORDER BY i.scheduled_date DESC
");
$related_stmt->execute([$interview['candidate_id'], $interview_id]);
$related_interviews = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Details - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Interview Details</h1>
                        <p class="text-gray-600">
                            <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?> 
                            for <?php echo htmlspecialchars($interview['job_title']); ?>
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        <?php if ($interview['status'] == 'scheduled'): ?>
                        <a href="edit.php?id=<?php echo $interview['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>Edit Interview
                        </a>
                        <?php endif; ?>
                        <?php if ($interview['status'] == 'completed' && !$feedback): ?>
                        <a href="feedback.php?id=<?php echo $interview['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-comment mr-2"></i>Add Feedback
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Interview Information -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Details -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Interview Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Date & Time</label>
                                <p class="text-lg text-gray-900">
                                    <?php echo date('F j, Y \a\t g:i A', strtotime($interview['scheduled_date'])); ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Duration</label>
                                <p class="text-lg text-gray-900"><?php echo $interview['duration']; ?> minutes</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Interview Type</label>
                                <p class="text-lg text-gray-900">
                                    <i class="fas fa-<?php echo $interview['interview_type'] == 'video' ? 'video' : ($interview['interview_type'] == 'phone' ? 'phone' : ($interview['interview_type'] == 'technical' ? 'code' : 'building')); ?> mr-2"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $interview['interview_type'])); ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <?php
                                $status_colors = [
                                    'scheduled' => 'bg-blue-100 text-blue-800',
                                    'completed' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'rescheduled' => 'bg-yellow-100 text-yellow-800'
                                ];
                                $color_class = $status_colors[$interview['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php echo $color_class; ?>">
                                    <?php echo ucfirst($interview['status']); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($interview['location']): ?>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Location</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($interview['location']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($interview['meeting_link']): ?>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Meeting Link</label>
                            <a href="<?php echo htmlspecialchars($interview['meeting_link']); ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 break-all">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                <?php echo htmlspecialchars($interview['meeting_link']); ?>
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if ($interview['notes']): ?>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <p class="text-gray-900 whitespace-pre-wrap"><?php echo htmlspecialchars($interview['notes']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Candidate Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Candidate Details</h3>
                        <div class="flex items-start space-x-4">
                            <div class="bg-blue-500 text-white w-16 h-16 rounded-full flex items-center justify-center text-xl font-semibold">
                                <?php echo strtoupper(substr($interview['candidate_first'], 0, 1) . substr($interview['candidate_last'], 0, 1)); ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-medium text-gray-900">
                                    <?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?>
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email</label>
                                        <a href="mailto:<?php echo htmlspecialchars($interview['candidate_email']); ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <?php echo htmlspecialchars($interview['candidate_email']); ?>
                                        </a>
                                    </div>
                                    <?php if ($interview['candidate_phone']): ?>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                                        <a href="tel:<?php echo htmlspecialchars($interview['candidate_phone']); ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <?php echo htmlspecialchars($interview['candidate_phone']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($interview['linkedin_url']): ?>
                                <div class="mt-2">
                                    <label class="block text-sm font-medium text-gray-700">LinkedIn</label>
                                    <a href="<?php echo htmlspecialchars($interview['linkedin_url']); ?>" target="_blank"
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fab fa-linkedin mr-2"></i>
                                        View Profile
                                    </a>
                                </div>
                                <?php endif; ?>

                                <?php if ($interview['candidate_skills']): ?>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Skills</label>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach (explode(',', $interview['candidate_skills']) as $skill): ?>
                                        <span class="bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full">
                                            <?php echo htmlspecialchars(trim($skill)); ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Job Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Position Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Job Title</label>
                                <p class="text-lg text-gray-900"><?php echo htmlspecialchars($interview['job_title']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Department</label>
                                <p class="text-lg text-gray-900"><?php echo htmlspecialchars($interview['department']); ?></p>
                            </div>
                            <?php if ($interview['job_location']): ?>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Location</label>
                                <p class="text-lg text-gray-900"><?php echo htmlspecialchars($interview['job_location']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Feedback Section -->
                    <?php if ($feedback): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Interview Feedback</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Overall Rating</label>
                                <div class="flex items-center mt-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="ml-2 text-sm text-gray-600">(<?php echo $feedback['rating']; ?>/5)</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Recommendation</label>
                                <?php
                                $rec_colors = [
                                    'hire' => 'bg-green-100 text-green-800',
                                    'maybe' => 'bg-yellow-100 text-yellow-800',
                                    'no_hire' => 'bg-red-100 text-red-800'
                                ];
                                $rec_color = $rec_colors[$feedback['recommendation']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php echo $rec_color; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $feedback['recommendation'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if ($feedback['comments']): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Comments</label>
                            <p class="text-gray-900 whitespace-pre-wrap"><?php echo htmlspecialchars($feedback['comments']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($feedback['strengths']): ?>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Strengths</label>
                            <p class="text-gray-900 whitespace-pre-wrap"><?php echo htmlspecialchars($feedback['strengths']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($feedback['weaknesses']): ?>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Areas for Improvement</label>
                            <p class="text-gray-900 whitespace-pre-wrap"><?php echo htmlspecialchars($feedback['weaknesses']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-2">
                            <?php if ($interview['status'] == 'scheduled'): ?>
                            <button onclick="markCompleted(<?php echo $interview['id']; ?>)" 
                                    class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-check mr-2"></i>Mark Completed
                            </button>
                            <button onclick="rescheduleInterview(<?php echo $interview['id']; ?>)"
                                    class="w-full bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-calendar-alt mr-2"></i>Reschedule
                            </button>
                            <button onclick="cancelInterview(<?php echo $interview['id']; ?>)"
                                    class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel Interview
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($interview['meeting_link']): ?>
                            <a href="<?php echo htmlspecialchars($interview['meeting_link']); ?>" target="_blank"
                               class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200 block text-center">
                                <i class="fas fa-external-link-alt mr-2"></i>Join Meeting
                            </a>
                            <?php endif; ?>
                            
                            <a href="../candidates/view.php?id=<?php echo $interview['candidate_id']; ?>"
                               class="w-full bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg transition duration-200 block text-center">
                                <i class="fas fa-user mr-2"></i>View Candidate
                            </a>
                        </div>
                    </div>

                    <!-- Interview History -->
                    <?php if (!empty($related_interviews)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Other Interviews</h3>
                        <div class="space-y-3">
                            <?php foreach ($related_interviews as $related): ?>
                            <div class="border-l-4 border-blue-500 pl-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($related['title']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($related['scheduled_date'])); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo ucfirst($related['interview_type']); ?></p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full 
                                        <?php echo $related['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                   ($related['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'); ?>">
                                        <?php echo ucfirst($related['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Interview Details -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Interviewer</h3>
                        <div class="text-center">
                            <div class="bg-gray-500 text-white w-12 h-12 rounded-full flex items-center justify-center text-lg font-semibold mx-auto mb-2">
                                <?php echo strtoupper(substr($interview['interviewer_first'], 0, 1) . substr($interview['interviewer_last'], 0, 1)); ?>
                            </div>
                            <p class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($interview['interviewer_first'] . ' ' . $interview['interviewer_last']); ?>
                            </p>
                            <a href="mailto:<?php echo htmlspecialchars($interview['interviewer_email']); ?>" 
                               class="text-sm text-blue-600 hover:text-blue-800">
                                <?php echo htmlspecialchars($interview['interviewer_email']); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Metadata -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Information</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Created:</span>
                                <span class="text-gray-900"><?php echo date('M j, Y', strtotime($interview['created_at'])); ?></span>
                            </div>
                            <?php if ($interview['created_by_first']): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Created by:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($interview['created_by_first'] . ' ' . $interview['created_by_last']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Updated:</span>
                                <span class="text-gray-900"><?php echo date('M j, Y', strtotime($interview['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function markCompleted(id) {
            if (confirm('Mark this interview as completed? You will be able to add feedback afterward.')) {
                window.location.href = '<?php echo BASE_URL; ?>/interviews/update_status.php?id=' + id + '&status=completed&redirect=view';
            }
        }
        
        function cancelInterview(id) {
            const reason = prompt('Please provide a reason for cancellation:');
            if (reason !== null && reason.trim() !== '') {
                window.location.href = '<?php echo BASE_URL; ?>/interviews/update_status.php?id=' + id + '&status=cancelled&reason=' + encodeURIComponent(reason) + '&redirect=view';
            }
        }
        
        function rescheduleInterview(id) {
            if (confirm('Reschedule this interview? You will be redirected to the edit page.')) {
                window.location.href = '<?php echo BASE_URL; ?>/interviews/edit.php?id=' + id;
            }
        }
    </script>
</body>
</html> 