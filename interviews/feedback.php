<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$interview_id = $_GET['id'] ?? null;

if (!$interview_id) {
    header('Location: list.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get interview details
$interview_stmt = $conn->prepare("
    SELECT i.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
           j.title as job_title, j.requirements,
           u.first_name as interviewer_first, u.last_name as interviewer_last
    FROM interviews i
    JOIN candidates c ON i.candidate_id = c.id
    JOIN job_postings j ON i.job_id = j.id
    JOIN users u ON i.interviewer_id = u.id
    WHERE i.id = ?
");
$interview_stmt->execute([$interview_id]);
$interview = $interview_stmt->fetch();

if (!$interview) {
    header('Location: list.php');
    exit();
}

// Check if feedback already exists
$existing_feedback_stmt = $conn->prepare("
    SELECT * FROM interview_feedback 
    WHERE interview_id = ? AND interviewer_id = ?
");
$existing_feedback_stmt->execute([$interview_id, $_SESSION['user_id']]);
$existing_feedback = $existing_feedback_stmt->fetch();

// Check permissions (only interviewer or admin can submit feedback)
if ($_SESSION['user_id'] != $interview['interviewer_id'] && !hasPermission('admin')) {
    header('Location: ../unauthorized.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $technical_rating = $_POST['technical_rating'];
    $communication_rating = $_POST['communication_rating'];
    $cultural_fit_rating = $_POST['cultural_fit_rating'];
    $overall_rating = $_POST['overall_rating'];
    $strengths = $_POST['strengths'];
    $weaknesses = $_POST['weaknesses'];
    $recommendation = $_POST['recommendation'];
    $feedback_notes = $_POST['feedback_notes'];
    
    // Validate ratings
    if (!$technical_rating || !$communication_rating || !$cultural_fit_rating || !$overall_rating) {
        $error = 'Please provide all ratings.';
    } elseif (empty($strengths) || empty($feedback_notes)) {
        $error = 'Please provide strengths and detailed feedback notes.';
    } else {
        try {
            // Bias detection
            $bias_flags = detectBias($strengths, $weaknesses, $feedback_notes);
            
            if ($existing_feedback) {
                // Update existing feedback
                $stmt = $conn->prepare("
                    UPDATE interview_feedback SET
                        technical_rating = ?, communication_rating = ?, cultural_fit_rating = ?, 
                        overall_rating = ?, strengths = ?, weaknesses = ?, 
                        recommendation = ?, feedback_notes = ?, submitted_at = CURRENT_TIMESTAMP
                    WHERE interview_id = ? AND interviewer_id = ?
                ");
                $stmt->execute([
                    $technical_rating, $communication_rating, $cultural_fit_rating,
                    $overall_rating, $strengths, $weaknesses,
                    $recommendation, $feedback_notes, $interview_id, $_SESSION['user_id']
                ]);
                $success = 'Feedback updated successfully!';
            } else {
                // Insert new feedback
                $stmt = $conn->prepare("
                    INSERT INTO interview_feedback (
                        interview_id, interviewer_id, technical_rating, communication_rating, 
                        cultural_fit_rating, overall_rating, strengths, weaknesses, 
                        recommendation, feedback_notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $interview_id, $_SESSION['user_id'], $technical_rating, $communication_rating,
                    $cultural_fit_rating, $overall_rating, $strengths, $weaknesses,
                    $recommendation, $feedback_notes
                ]);
                $success = 'Feedback submitted successfully!';
            }
            
            // Update interview status to completed if not already
            if ($interview['status'] == 'scheduled') {
                $update_interview = $conn->prepare("UPDATE interviews SET status = 'completed' WHERE id = ?");
                $update_interview->execute([$interview_id]);
            }
            
            // Log activity
            logActivity(
                $_SESSION['user_id'], 
                'feedback', 
                'interview', 
                $interview_id,
                "Submitted feedback for interview with {$interview['candidate_first']} {$interview['candidate_last']}"
            );
            
            // Show bias warnings if any
            if (!empty($bias_flags)) {
                $success .= "\n\nNote: Potential bias indicators detected in feedback. Please review your comments for objectivity.";
            }
            
        } catch (Exception $e) {
            $error = 'Error submitting feedback: ' . $e->getMessage();
        }
    }
}

function detectBias($strengths, $weaknesses, $notes) {
    $bias_indicators = [];
    $text = strtolower($strengths . ' ' . $weaknesses . ' ' . $notes);
    
    // Common bias keywords/phrases
    $bias_patterns = [
        'gender' => ['he/she', 'bossy', 'aggressive', 'emotional', 'nurturing'],
        'age' => ['young', 'old', 'experienced', 'fresh', 'seasoned'],
        'appearance' => ['professional appearance', 'well-dressed', 'presentation'],
        'cultural' => ['accent', 'foreign', 'different background', 'cultural fit'],
        'personal' => ['family', 'children', 'married', 'single', 'pregnant']
    ];
    
    foreach ($bias_patterns as $category => $patterns) {
        foreach ($patterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                $bias_indicators[] = $category;
                break;
            }
        }
    }
    
    return array_unique($bias_indicators);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Feedback - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Interview Feedback</h1>
                <p class="text-gray-600">Provide detailed feedback for the completed interview</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo nl2br(htmlspecialchars($success)); ?>
                <div class="mt-2">
                    <a href="list.php" class="text-green-800 hover:text-green-900 underline">Back to interviews</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Interview Details -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-info-circle mr-2"></i>Interview Details
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Candidate</p>
                        <p class="font-medium"><?php echo htmlspecialchars($interview['candidate_first'] . ' ' . $interview['candidate_last']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($interview['candidate_email']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Position</p>
                        <p class="font-medium"><?php echo htmlspecialchars($interview['job_title']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Interview Date</p>
                        <p class="font-medium"><?php echo date('M j, Y g:i A', strtotime($interview['scheduled_date'])); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Type</p>
                        <p class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $interview['interview_type'])); ?></p>
                    </div>
                </div>
                <?php if ($interview['notes']): ?>
                <div class="mt-4">
                    <p class="text-sm text-gray-600">Interview Notes</p>
                    <p class="text-gray-800"><?php echo htmlspecialchars($interview['notes']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Feedback Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">
                    <i class="fas fa-comment mr-2"></i>Feedback Evaluation
                    <?php if ($existing_feedback): ?>
                        <span class="text-sm text-yellow-600 font-normal">(Editing existing feedback)</span>
                    <?php endif; ?>
                </h2>

                <form method="POST" class="space-y-6" id="feedbackForm">
                    <!-- Ratings Section -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Technical Rating -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-code mr-2"></i>Technical Skills *
                            </label>
                            <div class="space-y-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="flex items-center">
                                    <input type="radio" name="technical_rating" value="<?php echo $i; ?>" 
                                           <?php echo ($existing_feedback && $existing_feedback['technical_rating'] == $i) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm">
                                        <?php echo $i; ?> - <?php echo ['Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$i-1]; ?>
                                    </span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Communication Rating -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-comments mr-2"></i>Communication Skills *
                            </label>
                            <div class="space-y-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="flex items-center">
                                    <input type="radio" name="communication_rating" value="<?php echo $i; ?>" 
                                           <?php echo ($existing_feedback && $existing_feedback['communication_rating'] == $i) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm">
                                        <?php echo $i; ?> - <?php echo ['Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$i-1]; ?>
                                    </span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Cultural Fit Rating -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-users mr-2"></i>Team Fit *
                            </label>
                            <div class="space-y-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="flex items-center">
                                    <input type="radio" name="cultural_fit_rating" value="<?php echo $i; ?>" 
                                           <?php echo ($existing_feedback && $existing_feedback['cultural_fit_rating'] == $i) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm">
                                        <?php echo $i; ?> - <?php echo ['Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$i-1]; ?>
                                    </span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Overall Rating -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-star mr-2"></i>Overall Rating *
                            </label>
                            <div class="space-y-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label class="flex items-center">
                                    <input type="radio" name="overall_rating" value="<?php echo $i; ?>" 
                                           <?php echo ($existing_feedback && $existing_feedback['overall_rating'] == $i) ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm">
                                        <?php echo $i; ?> - <?php echo ['Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$i-1]; ?>
                                    </span>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Feedback -->
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-thumbs-up mr-2"></i>Strengths *
                            </label>
                            <textarea name="strengths" rows="4" required
                                      placeholder="What did the candidate do well? Be specific about skills, responses, and behaviors..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo $existing_feedback ? htmlspecialchars($existing_feedback['strengths']) : ''; ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Focus on specific examples and objective observations</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Areas for Improvement
                            </label>
                            <textarea name="weaknesses" rows="3"
                                      placeholder="What areas could the candidate improve? Provide constructive feedback..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo $existing_feedback ? htmlspecialchars($existing_feedback['weaknesses']) : ''; ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Be constructive and avoid personal characteristics</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lightbulb mr-2"></i>Recommendation *
                            </label>
                            <select name="recommendation" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select recommendation...</option>
                                <option value="strong_hire" <?php echo ($existing_feedback && $existing_feedback['recommendation'] == 'strong_hire') ? 'selected' : ''; ?>>Strong Hire</option>
                                <option value="hire" <?php echo ($existing_feedback && $existing_feedback['recommendation'] == 'hire') ? 'selected' : ''; ?>>Hire</option>
                                <option value="neutral" <?php echo ($existing_feedback && $existing_feedback['recommendation'] == 'neutral') ? 'selected' : ''; ?>>Neutral</option>
                                <option value="no_hire" <?php echo ($existing_feedback && $existing_feedback['recommendation'] == 'no_hire') ? 'selected' : ''; ?>>No Hire</option>
                                <option value="strong_no_hire" <?php echo ($existing_feedback && $existing_feedback['recommendation'] == 'strong_no_hire') ? 'selected' : ''; ?>>Strong No Hire</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-clipboard mr-2"></i>Additional Notes *
                            </label>
                            <textarea name="feedback_notes" rows="6" required
                                      placeholder="Provide detailed feedback about the interview, specific answers, problem-solving approach, questions asked, etc..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo $existing_feedback ? htmlspecialchars($existing_feedback['feedback_notes']) : ''; ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Include specific examples and evidence to support your ratings</p>
                        </div>
                    </div>

                    <!-- Bias Detection Warning -->
                    <div id="biasWarning" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 hidden">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">Potential Bias Detected</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Our system has detected language that may indicate unconscious bias. Please review your feedback to ensure it focuses on job-related skills and behaviors.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        
                        <div class="space-x-4">
                            <button type="button" onclick="saveDraft()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Save Draft
                            </button>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-paper-plane mr-2"></i><?php echo $existing_feedback ? 'Update' : 'Submit'; ?> Feedback
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Bias detection on text input
        function checkForBias() {
            const strengths = document.querySelector('[name="strengths"]').value.toLowerCase();
            const weaknesses = document.querySelector('[name="weaknesses"]').value.toLowerCase();
            const notes = document.querySelector('[name="feedback_notes"]').value.toLowerCase();
            
            const text = strengths + ' ' + weaknesses + ' ' + notes;
            const biasWords = ['bossy', 'aggressive', 'emotional', 'young', 'old', 'accent', 'foreign', 'family', 'children', 'married'];
            
            let biasFound = false;
            for (let word of biasWords) {
                if (text.includes(word)) {
                    biasFound = true;
                    break;
                }
            }
            
            const warningDiv = document.getElementById('biasWarning');
            if (biasFound) {
                warningDiv.classList.remove('hidden');
            } else {
                warningDiv.classList.add('hidden');
            }
        }

        // Add event listeners to text areas
        document.addEventListener('DOMContentLoaded', function() {
            const textAreas = document.querySelectorAll('textarea');
            textAreas.forEach(textarea => {
                textarea.addEventListener('input', checkForBias);
            });
        });

        function saveDraft() {
            // Here you would implement draft saving functionality
            alert('Draft saving functionality would be implemented here');
        }

        // Form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const requiredRatings = ['technical_rating', 'communication_rating', 'cultural_fit_rating', 'overall_rating'];
            let missingRatings = [];
            
            requiredRatings.forEach(rating => {
                if (!document.querySelector(`input[name="${rating}"]:checked`)) {
                    missingRatings.push(rating.replace('_', ' '));
                }
            });
            
            if (missingRatings.length > 0) {
                e.preventDefault();
                alert('Please provide ratings for: ' + missingRatings.join(', '));
            }
        });
    </script>
</body>
</html> 