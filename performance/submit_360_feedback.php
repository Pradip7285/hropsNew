<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check authentication
requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get request ID
$request_id = $_GET['id'] ?? 0;

// Verify access to this feedback request
$access_query = "
    SELECT fr.*, e.first_name, e.last_name, e.employee_id,
           pc.cycle_name, pc.cycle_year,
           fp.id as provider_id, fp.relationship_type, fp.status as provider_status
    FROM feedback_360_requests fr
    JOIN employees e ON fr.employee_id = e.id
    JOIN performance_cycles pc ON fr.cycle_id = pc.id
    JOIN feedback_360_providers fp ON fr.id = fp.request_id
    WHERE fr.id = ? AND fp.provider_id = ? AND fr.status = 'active'
";

$access_stmt = $conn->prepare($access_query);
$access_stmt->execute([$request_id, $_SESSION['user_id']]);
$feedback_request = $access_stmt->fetch(PDO::FETCH_ASSOC);

if (!$feedback_request) {
    header('Location: my_360_feedback.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->beginTransaction();
        
        // Check if response already exists
        $existing_stmt = $conn->prepare("
            SELECT id FROM feedback_360_responses 
            WHERE request_id = ? AND provider_id = ?
        ");
        $existing_stmt->execute([$request_id, $_SESSION['user_id']]);
        $existing_response = $existing_stmt->fetch();
        
        if ($existing_response) {
            // Update existing response
            $update_stmt = $conn->prepare("
                UPDATE feedback_360_responses 
                SET responses = ?, overall_rating = ?, comments = ?, submitted_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([
                json_encode($_POST['responses']),
                $_POST['overall_rating'] ?? null,
                $_POST['comments'] ?? '',
                $existing_response['id']
            ]);
        } else {
            // Create new response
            $response_stmt = $conn->prepare("
                INSERT INTO feedback_360_responses (request_id, provider_id, responses, overall_rating, comments)
                VALUES (?, ?, ?, ?, ?)
            ");
            $response_stmt->execute([
                $request_id, $_SESSION['user_id'],
                json_encode($_POST['responses']),
                $_POST['overall_rating'] ?? null,
                $_POST['comments'] ?? ''
            ]);
        }
        
        // Update provider status to completed
        $status_stmt = $conn->prepare("
            UPDATE feedback_360_providers 
            SET status = 'completed', completed_at = NOW()
            WHERE request_id = ? AND provider_id = ?
        ");
        $status_stmt->execute([$request_id, $_SESSION['user_id']]);
        
        $conn->commit();
        $_SESSION['success_message'] = "360° feedback submitted successfully!";
        header('Location: my_360_feedback.php');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error submitting feedback: " . $e->getMessage();
    }
}

// Get feedback questions for this request
$questions_stmt = $conn->prepare("
    SELECT * FROM feedback_360_questions 
    WHERE request_id = ? 
    ORDER BY question_order ASC
");
$questions_stmt->execute([$request_id]);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing response if any
$existing_response = null;
$existing_stmt = $conn->prepare("
    SELECT * FROM feedback_360_responses 
    WHERE request_id = ? AND provider_id = ?
");
$existing_stmt->execute([$request_id, $_SESSION['user_id']]);
$existing_response = $existing_stmt->fetch(PDO::FETCH_ASSOC);

$existing_answers = [];
if ($existing_response) {
    $existing_answers = json_decode($existing_response['responses'], true) ?: [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit 360° Feedback - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Submit 360° Feedback</h1>
                        <p class="text-gray-600">Provide feedback for <?php echo htmlspecialchars($feedback_request['first_name'] . ' ' . $feedback_request['last_name']); ?></p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="my_360_feedback.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to My Feedback
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <!-- Feedback Request Info -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <span class="text-sm text-gray-500">Employee</span>
                        <p class="font-medium"><?php echo htmlspecialchars($feedback_request['first_name'] . ' ' . $feedback_request['last_name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($feedback_request['employee_id']); ?></p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">Performance Cycle</span>
                        <p class="font-medium"><?php echo htmlspecialchars($feedback_request['cycle_name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo $feedback_request['cycle_year']; ?></p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">Your Relationship</span>
                        <p class="font-medium capitalize"><?php echo htmlspecialchars($feedback_request['relationship_type']); ?></p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">Deadline</span>
                        <p class="font-medium"><?php echo date('M j, Y', strtotime($feedback_request['deadline'])); ?></p>
                        <?php 
                        $days_remaining = floor((strtotime($feedback_request['deadline']) - time()) / (60 * 60 * 24));
                        if ($days_remaining > 0): ?>
                            <p class="text-sm text-green-600"><?php echo $days_remaining; ?> days remaining</p>
                        <?php elseif ($days_remaining == 0): ?>
                            <p class="text-sm text-yellow-600">Due today</p>
                        <?php else: ?>
                            <p class="text-sm text-red-600"><?php echo abs($days_remaining); ?> days overdue</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($feedback_request['description']): ?>
                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-medium text-blue-900 mb-2">Instructions:</h4>
                    <p class="text-blue-700"><?php echo htmlspecialchars($feedback_request['description']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Feedback Form -->
            <form method="POST" class="space-y-6" id="feedbackForm">
                <?php if (!empty($questions)): ?>
                    <?php foreach ($questions as $index => $question): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="mb-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">
                                        Question <?php echo $index + 1; ?>
                                        <?php if ($question['required']): ?>
                                            <span class="text-red-500">*</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-gray-600"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                </div>
                                <span class="ml-4 px-2 py-1 bg-gray-100 text-gray-600 text-sm rounded">
                                    <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?>
                                </span>
                            </div>
                        </div>

                        <?php 
                        $field_name = "responses[{$question['id']}]";
                        $existing_value = $existing_answers[$question['id']] ?? '';
                        ?>

                        <?php if ($question['question_type'] == 'text'): ?>
                            <textarea name="<?php echo $field_name; ?>" 
                                      rows="4" 
                                      placeholder="Enter your feedback..."
                                      <?php echo $question['required'] ? 'required' : ''; ?>
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($existing_value); ?></textarea>

                        <?php elseif ($question['question_type'] == 'rating_5'): ?>
                            <div class="space-y-2">
                                <div class="flex items-center space-x-4">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" 
                                               name="<?php echo $field_name; ?>" 
                                               value="<?php echo $i; ?>"
                                               <?php echo $existing_value == $i ? 'checked' : ''; ?>
                                               <?php echo $question['required'] ? 'required' : ''; ?>
                                               class="sr-only">
                                        <div class="rating-star text-2xl text-gray-300 hover:text-yellow-400 transition-colors">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <span class="ml-1 text-sm text-gray-600"><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Poor</span>
                                    <span>Excellent</span>
                                </div>
                            </div>

                        <?php elseif ($question['question_type'] == 'rating_10'): ?>
                            <div class="space-y-2">
                                <div class="flex items-center space-x-2">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <label class="flex flex-col items-center cursor-pointer">
                                        <input type="radio" 
                                               name="<?php echo $field_name; ?>" 
                                               value="<?php echo $i; ?>"
                                               <?php echo $existing_value == $i ? 'checked' : ''; ?>
                                               <?php echo $question['required'] ? 'required' : ''; ?>
                                               class="sr-only">
                                        <div class="rating-number w-8 h-8 border-2 border-gray-300 rounded-full flex items-center justify-center text-sm font-medium hover:border-blue-500 hover:bg-blue-50 transition-colors">
                                            <?php echo $i; ?>
                                        </div>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Strongly Disagree</span>
                                    <span>Strongly Agree</span>
                                </div>
                            </div>

                        <?php elseif ($question['question_type'] == 'multiple_choice'): ?>
                            <div class="space-y-2">
                                <?php 
                                $choices = ['Excellent', 'Good', 'Satisfactory', 'Needs Improvement', 'Poor'];
                                foreach ($choices as $choice): ?>
                                <label class="flex items-center">
                                    <input type="radio" 
                                           name="<?php echo $field_name; ?>" 
                                           value="<?php echo $choice; ?>"
                                           <?php echo $existing_value == $choice ? 'checked' : ''; ?>
                                           <?php echo $question['required'] ? 'required' : ''; ?>
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-gray-700"><?php echo $choice; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <!-- Overall Rating and Comments -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Overall Assessment</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Overall Rating</label>
                                <div class="flex items-center space-x-4">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" 
                                               name="overall_rating" 
                                               value="<?php echo $i; ?>"
                                               <?php echo ($existing_response['overall_rating'] ?? '') == $i ? 'checked' : ''; ?>
                                               class="sr-only">
                                        <div class="overall-star text-2xl text-gray-300 hover:text-yellow-400 transition-colors">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <span class="ml-1 text-sm text-gray-600"><?php echo $i; ?></span>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Comments</label>
                                <textarea name="comments" 
                                          rows="4" 
                                          placeholder="Any additional feedback or comments..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($existing_response['comments'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-between items-center bg-white rounded-lg shadow-md p-6">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-1"></i>
                            Fields marked with * are required
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="button" onclick="saveDraft()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Save Draft
                            </button>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>Submit Feedback
                            </button>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-8 text-center">
                        <i class="fas fa-question-circle text-gray-400 text-6xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Questions Available</h3>
                        <p class="text-gray-500">This feedback request doesn't have any questions configured yet.</p>
                    </div>
                <?php endif; ?>
            </form>
        </main>
    </div>

    <script>
        // Rating star interactions
        document.querySelectorAll('.rating-star').forEach((star, index) => {
            const radio = star.previousElementSibling;
            const container = star.closest('.flex');
            const allStars = container.querySelectorAll('.rating-star');
            
            // Set initial state
            updateStars(allStars, radio.checked ? parseInt(radio.value) : 0);
            
            star.addEventListener('click', function() {
                radio.checked = true;
                updateStars(allStars, parseInt(radio.value));
            });
            
            container.addEventListener('mouseover', function(e) {
                if (e.target.closest('.rating-star')) {
                    const hoverValue = parseInt(e.target.closest('label').querySelector('input').value);
                    updateStars(allStars, hoverValue, true);
                }
            });
            
            container.addEventListener('mouseleave', function() {
                const checkedValue = container.querySelector('input:checked')?.value || 0;
                updateStars(allStars, parseInt(checkedValue));
            });
        });

        // Overall rating stars
        document.querySelectorAll('.overall-star').forEach((star, index) => {
            const radio = star.previousElementSibling;
            const container = star.closest('.flex');
            const allStars = container.querySelectorAll('.overall-star');
            
            updateStars(allStars, radio.checked ? parseInt(radio.value) : 0);
            
            star.addEventListener('click', function() {
                radio.checked = true;
                updateStars(allStars, parseInt(radio.value));
            });
            
            container.addEventListener('mouseover', function(e) {
                if (e.target.closest('.overall-star')) {
                    const hoverValue = parseInt(e.target.closest('label').querySelector('input').value);
                    updateStars(allStars, hoverValue, true);
                }
            });
            
            container.addEventListener('mouseleave', function() {
                const checkedValue = container.querySelector('input:checked')?.value || 0;
                updateStars(allStars, parseInt(checkedValue));
            });
        });

        // Rating number interactions
        document.querySelectorAll('.rating-number').forEach(number => {
            const radio = number.previousElementSibling;
            
            // Set initial state
            if (radio.checked) {
                number.classList.add('border-blue-500', 'bg-blue-100', 'text-blue-700');
            }
            
            number.addEventListener('click', function() {
                // Reset all numbers in this group
                const container = number.closest('.flex');
                container.querySelectorAll('.rating-number').forEach(n => {
                    n.classList.remove('border-blue-500', 'bg-blue-100', 'text-blue-700');
                });
                
                // Highlight selected number
                radio.checked = true;
                number.classList.add('border-blue-500', 'bg-blue-100', 'text-blue-700');
            });
        });

        function updateStars(stars, rating, hover = false) {
            stars.forEach((star, index) => {
                const starIcon = star.querySelector('i');
                if (index < rating) {
                    starIcon.className = 'fas fa-star';
                    star.style.color = hover ? '#fbbf24' : '#f59e0b';
                } else {
                    starIcon.className = 'far fa-star';
                    star.style.color = '#d1d5db';
                }
            });
        }

        function saveDraft() {
            // Auto-save functionality would go here
            alert('Draft saved successfully!');
        }

        // Auto-save every 2 minutes
        setInterval(function() {
            const formData = new FormData(document.getElementById('feedbackForm'));
            // Implementation for auto-save would go here
        }, 120000);

        // Form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value) {
                    isValid = false;
                    field.closest('.bg-white').classList.add('border-red-500');
                } else {
                    field.closest('.bg-white').classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            return confirm('Are you sure you want to submit this feedback? You may not be able to modify it later.');
        });
    </script>
</body>
</html> 