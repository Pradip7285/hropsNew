<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission - allow both HR and employees for reviews
requireLogin();

$db = new Database();
$conn = $db->getConnection();

// Get review ID from URL or create new review
$review_id = $_GET['id'] ?? null;
$cycle_id = $_GET['cycle_id'] ?? null;
$employee_id = $_GET['employee_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_review':
            $review_id = $_POST['review_id'];
            $status = $_POST['save_type'] == 'submit' ? 'submitted' : 'in_progress';
            $submitted_at = $status == 'submitted' ? 'NOW()' : 'NULL';
            
            // Update main review
            $stmt = $conn->prepare("
                UPDATE performance_reviews SET 
                overall_rating = ?, overall_comments = ?, strengths = ?, 
                areas_for_improvement = ?, achievements = ?, development_needs = ?, 
                goals_for_next_period = ?, status = ?, 
                submitted_at = " . ($submitted_at == 'NOW()' ? 'NOW()' : 'NULL') . "
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $_POST['overall_rating'], $_POST['overall_comments'], $_POST['strengths'],
                $_POST['areas_for_improvement'], $_POST['achievements'], $_POST['development_needs'],
                $_POST['goals_for_next_period'], $status, $review_id
            ]);
            
            // Delete existing ratings
            $stmt = $conn->prepare("DELETE FROM performance_ratings WHERE review_id = ?");
            $stmt->execute([$review_id]);
            
            // Save new ratings
            if (isset($_POST['ratings']) && is_array($_POST['ratings'])) {
                foreach ($_POST['ratings'] as $category => $ratings) {
                    foreach ($ratings as $rating) {
                        $stmt = $conn->prepare("
                            INSERT INTO performance_ratings 
                            (review_id, rating_category, category_id, rating_name, rating_description, 
                             rating_value, max_rating, weight_percentage, comments) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $review_id, $category, $rating['id'] ?? null, $rating['name'],
                            $rating['description'] ?? '', $rating['value'], $rating['max_value'],
                            $rating['weight'] ?? 0, $rating['comments'] ?? ''
                        ]);
                    }
                }
            }
            
            echo json_encode(['success' => $result, 'status' => $status]);
            exit;
    }
}

// Get or create review
$review = null;
if ($review_id) {
    $stmt = $conn->prepare("
        SELECT pr.*, pc.cycle_name, pc.cycle_year,
               e.first_name as employee_first_name, e.last_name as employee_last_name,
               e.employee_id as employee_number, e.department, e.position,
               r.first_name as reviewer_first_name, r.last_name as reviewer_last_name
        FROM performance_reviews pr
        JOIN performance_cycles pc ON pr.cycle_id = pc.id
        JOIN employees e ON pr.employee_id = e.id
        JOIN employees r ON pr.reviewer_id = r.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$review) {
        header('Location: reviews.php');
        exit;
    }
} else {
    // Redirect to review selection if no review specified
    header('Location: reviews.php');
    exit;
}

// Check if user can edit this review
$can_edit = false;
if (hasRole('hr_recruiter') || $review['reviewer_id'] == $_SESSION['user_id']) {
    $can_edit = true;
}

if (!$can_edit) {
    header('Location: view_review.php?id=' . $review_id);
    exit;
}

// Get employee goals for this cycle
$goals_stmt = $conn->prepare("
    SELECT * FROM performance_goals 
    WHERE employee_id = ? AND (
        cycle_id = ? OR 
        (start_date <= (SELECT end_date FROM performance_cycles WHERE id = ?) AND 
         due_date >= (SELECT start_date FROM performance_cycles WHERE id = ?))
    )
    ORDER BY priority DESC, created_at
");
$goals_stmt->execute([$review['employee_id'], $review['cycle_id'], $review['cycle_id'], $review['cycle_id']]);
$goals = $goals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get competencies
$competencies_stmt = $conn->query("
    SELECT * FROM performance_competencies 
    WHERE is_active = 1 
    ORDER BY competency_category, competency_name
");
$competencies = $competencies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing ratings
$ratings = [];
if ($review_id) {
    $ratings_stmt = $conn->prepare("
        SELECT * FROM performance_ratings 
        WHERE review_id = ? 
        ORDER BY rating_category, rating_name
    ");
    $ratings_stmt->execute([$review_id]);
    $existing_ratings = $ratings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($existing_ratings as $rating) {
        $ratings[$rating['rating_category']][] = $rating;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conduct Performance Review - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Conduct Performance Review</h1>
                        <p class="text-gray-600">
                            <?php echo htmlspecialchars($review['employee_first_name'] . ' ' . $review['employee_last_name']); ?> - 
                            <?php echo htmlspecialchars($review['cycle_name'] . ' ' . $review['cycle_year']); ?>
                        </p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="reviews.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Reviews
                        </a>
                        <a href="view_review.php?id=<?php echo $review_id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-eye mr-2"></i>Preview
                        </a>
                    </div>
                </div>
            </div>

            <!-- Review Info Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <span class="text-sm text-gray-500">Employee</span>
                        <p class="font-medium"><?php echo htmlspecialchars($review['employee_first_name'] . ' ' . $review['employee_last_name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['employee_number']); ?></p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">Department & Position</span>
                        <p class="font-medium"><?php echo htmlspecialchars($review['department']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['position']); ?></p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">Review Type</span>
                        <p class="font-medium"><?php echo ucfirst($review['review_type']); ?> Review</p>
                    </div>
                    <div>
                        <span class="text-sm text-gray-500">Due Date</span>
                        <p class="font-medium"><?php echo date('M j, Y', strtotime($review['due_date'])); ?></p>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $review['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $review['status'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <form id="reviewForm" class="space-y-6">
                <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                
                <!-- Goal Performance Section -->
                <?php if (!empty($goals)): ?>
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-bullseye text-blue-600 mr-2"></i>Goal Performance
                        </h2>
                        <p class="text-gray-600">Rate the employee's performance on their assigned goals</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php foreach ($goals as $index => $goal): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($goal['goal_title']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($goal['goal_description']); ?></p>
                                    <div class="flex items-center mt-2 text-sm text-gray-500">
                                        <span class="mr-4">Priority: <?php echo ucfirst($goal['priority']); ?></span>
                                        <span class="mr-4">Weight: <?php echo $goal['weight_percentage']; ?>%</span>
                                        <span>Progress: <?php echo $goal['current_value'] . '/' . $goal['target_value'] . ' ' . $goal['unit_of_measure']; ?></span>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $goal['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($goal['status']); ?>
                                </span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Rating (1-5)</label>
                                    <select name="ratings[goal][<?php echo $index; ?>][value]" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select Rating</option>
                                        <option value="1" <?php echo (isset($ratings['goal'][$index]) && $ratings['goal'][$index]['rating_value'] == 1) ? 'selected' : ''; ?>>1 - Does Not Meet Expectations</option>
                                        <option value="2" <?php echo (isset($ratings['goal'][$index]) && $ratings['goal'][$index]['rating_value'] == 2) ? 'selected' : ''; ?>>2 - Partially Meets Expectations</option>
                                        <option value="3" <?php echo (isset($ratings['goal'][$index]) && $ratings['goal'][$index]['rating_value'] == 3) ? 'selected' : ''; ?>>3 - Meets Expectations</option>
                                        <option value="4" <?php echo (isset($ratings['goal'][$index]) && $ratings['goal'][$index]['rating_value'] == 4) ? 'selected' : ''; ?>>4 - Exceeds Expectations</option>
                                        <option value="5" <?php echo (isset($ratings['goal'][$index]) && $ratings['goal'][$index]['rating_value'] == 5) ? 'selected' : ''; ?>>5 - Outstanding Performance</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Weight (%)</label>
                                    <input type="number" name="ratings[goal][<?php echo $index; ?>][weight]" 
                                           value="<?php echo $ratings['goal'][$index]['weight_percentage'] ?? $goal['weight_percentage']; ?>"
                                           min="0" max="100" step="0.1" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Comments</label>
                                <textarea name="ratings[goal][<?php echo $index; ?>][comments]" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                          placeholder="Provide specific feedback on goal achievement..."><?php echo htmlspecialchars($ratings['goal'][$index]['comments'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Hidden fields for goal data -->
                            <input type="hidden" name="ratings[goal][<?php echo $index; ?>][id]" value="<?php echo $goal['id']; ?>">
                            <input type="hidden" name="ratings[goal][<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($goal['goal_title']); ?>">
                            <input type="hidden" name="ratings[goal][<?php echo $index; ?>][description]" value="<?php echo htmlspecialchars($goal['goal_description']); ?>">
                            <input type="hidden" name="ratings[goal][<?php echo $index; ?>][max_value]" value="5">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Competency Assessment Section -->
                <?php if (!empty($competencies)): ?>
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-star text-yellow-600 mr-2"></i>Competency Assessment
                        </h2>
                        <p class="text-gray-600">Evaluate the employee's skills and competencies</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <?php 
                        $competency_categories = [];
                        foreach ($competencies as $competency) {
                            $competency_categories[$competency['competency_category']][] = $competency;
                        }
                        ?>
                        
                        <?php foreach ($competency_categories as $category => $category_competencies): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h3 class="font-medium text-gray-900 mb-4 capitalize">
                                <i class="fas fa-cogs text-indigo-600 mr-2"></i><?php echo str_replace('_', ' ', $category); ?> Skills
                            </h3>
                            
                            <div class="space-y-4">
                                <?php foreach ($category_competencies as $index => $competency): ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                    <div class="col-span-1">
                                        <h4 class="font-medium text-sm text-gray-800"><?php echo htmlspecialchars($competency['competency_name']); ?></h4>
                                        <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($competency['competency_description']); ?></p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Rating (1-5)</label>
                                        <select name="ratings[competency][<?php echo $competency['id']; ?>][value]" 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                            <option value="">Select Rating</option>
                                            <option value="1" <?php echo (isset($ratings['competency']) && isset($ratings['competency'][$competency['id']]) && $ratings['competency'][$competency['id']]['rating_value'] == 1) ? 'selected' : ''; ?>>1 - Beginner</option>
                                            <option value="2" <?php echo (isset($ratings['competency']) && isset($ratings['competency'][$competency['id']]) && $ratings['competency'][$competency['id']]['rating_value'] == 2) ? 'selected' : ''; ?>>2 - Developing</option>
                                            <option value="3" <?php echo (isset($ratings['competency']) && isset($ratings['competency'][$competency['id']]) && $ratings['competency'][$competency['id']]['rating_value'] == 3) ? 'selected' : ''; ?>>3 - Proficient</option>
                                            <option value="4" <?php echo (isset($ratings['competency']) && isset($ratings['competency'][$competency['id']]) && $ratings['competency'][$competency['id']]['rating_value'] == 4) ? 'selected' : ''; ?>>4 - Advanced</option>
                                            <option value="5" <?php echo (isset($ratings['competency']) && isset($ratings['competency'][$competency['id']]) && $ratings['competency'][$competency['id']]['rating_value'] == 5) ? 'selected' : ''; ?>>5 - Expert</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Comments</label>
                                        <textarea name="ratings[competency][<?php echo $competency['id']; ?>][comments]" rows="2" 
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                                  placeholder="Specific examples..."><?php echo htmlspecialchars($ratings['competency'][$competency['id']]['comments'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <!-- Hidden fields -->
                                    <input type="hidden" name="ratings[competency][<?php echo $competency['id']; ?>][id]" value="<?php echo $competency['id']; ?>">
                                    <input type="hidden" name="ratings[competency][<?php echo $competency['id']; ?>][name]" value="<?php echo htmlspecialchars($competency['competency_name']); ?>">
                                    <input type="hidden" name="ratings[competency][<?php echo $competency['id']; ?>][description]" value="<?php echo htmlspecialchars($competency['competency_description']); ?>">
                                    <input type="hidden" name="ratings[competency][<?php echo $competency['id']; ?>][max_value]" value="5">
                                    <input type="hidden" name="ratings[competency][<?php echo $competency['id']; ?>][weight]" value="0">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Overall Assessment Section -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-clipboard-check text-green-600 mr-2"></i>Overall Assessment
                        </h2>
                        <p class="text-gray-600">Provide comprehensive feedback and overall rating</p>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Overall Performance Rating</label>
                                <select name="overall_rating" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Overall Rating</option>
                                    <option value="1" <?php echo $review['overall_rating'] == 1 ? 'selected' : ''; ?>>1 - Unsatisfactory</option>
                                    <option value="2" <?php echo $review['overall_rating'] == 2 ? 'selected' : ''; ?>>2 - Needs Improvement</option>
                                    <option value="3" <?php echo $review['overall_rating'] == 3 ? 'selected' : ''; ?>>3 - Meets Expectations</option>
                                    <option value="4" <?php echo $review['overall_rating'] == 4 ? 'selected' : ''; ?>>4 - Exceeds Expectations</option>
                                    <option value="5" <?php echo $review['overall_rating'] == 5 ? 'selected' : ''; ?>>5 - Outstanding</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Overall Comments</label>
                            <textarea name="overall_comments" rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                      placeholder="Provide an overall assessment of the employee's performance during this review period..."><?php echo htmlspecialchars($review['overall_comments'] ?? ''); ?></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Key Strengths</label>
                                <textarea name="strengths" rows="4" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                          placeholder="What are the employee's key strengths and positive contributions?"><?php echo htmlspecialchars($review['strengths'] ?? ''); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Areas for Improvement</label>
                                <textarea name="areas_for_improvement" rows="4" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                          placeholder="What areas should the employee focus on improving?"><?php echo htmlspecialchars($review['areas_for_improvement'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Key Achievements</label>
                                <textarea name="achievements" rows="4" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                          placeholder="What significant achievements or accomplishments should be highlighted?"><?php echo htmlspecialchars($review['achievements'] ?? ''); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Development Needs</label>
                                <textarea name="development_needs" rows="4" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                          placeholder="What training, skills, or development opportunities would benefit the employee?"><?php echo htmlspecialchars($review['development_needs'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Goals for Next Period</label>
                            <textarea name="goals_for_next_period" rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                      placeholder="What goals and objectives should be set for the next review period?"><?php echo htmlspecialchars($review['goals_for_next_period'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between items-center bg-white rounded-lg shadow-md p-6">
                    <div class="flex space-x-3">
                        <button type="button" onclick="saveReview('draft')" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i>Save Draft
                        </button>
                        <button type="button" onclick="saveReview('submit')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Review
                        </button>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Auto-saves every 5 minutes
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        let autoSaveInterval;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-save every 5 minutes
            autoSaveInterval = setInterval(function() {
                saveReview('draft', true);
            }, 5 * 60 * 1000);
        });

        function saveReview(saveType = 'draft', isAutoSave = false) {
            const formData = new FormData(document.getElementById('reviewForm'));
            formData.append('action', 'save_review');
            formData.append('save_type', saveType);

            if (!isAutoSave) {
                // Show loading state
                const submitBtn = event.target;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                submitBtn.disabled = true;
            }

            fetch('conduct_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (!isAutoSave) {
                        if (saveType === 'submit') {
                            alert('Review submitted successfully!');
                            window.location.href = 'reviews.php';
                        } else {
                            alert('Review saved as draft');
                        }
                    } else {
                        // Show auto-save indicator
                        console.log('Auto-saved at ' + new Date().toLocaleTimeString());
                    }
                } else {
                    if (!isAutoSave) {
                        alert('Error saving review');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (!isAutoSave) {
                    alert('Error saving review');
                }
            })
            .finally(() => {
                if (!isAutoSave) {
                    // Restore button state
                    const submitBtn = event.target;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }

        // Prevent leaving page with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (document.querySelector('input, textarea, select').value !== '') {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html> 