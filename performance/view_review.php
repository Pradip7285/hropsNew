<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission - allow both HR and employees to view reviews
requireLogin();

$db = new Database();
$conn = $db->getConnection();

$review_id = $_GET['id'] ?? null;

if (!$review_id) {
    header('Location: reviews.php');
    exit;
}

// Get review details
$stmt = $conn->prepare("
    SELECT pr.*, pc.cycle_name, pc.cycle_year,
           e.first_name as employee_first_name, e.last_name as employee_last_name,
           e.employee_id as employee_number, e.department, e.position, e.hire_date,
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

// Check if user can view this review
$can_view = false;
if (hasRole('hr_recruiter') || $review['reviewer_id'] == $_SESSION['user_id'] || $review['employee_id'] == $_SESSION['user_id']) {
    $can_view = true;
}

if (!$can_view) {
    header('Location: reviews.php');
    exit;
}

// Get detailed ratings
$ratings_stmt = $conn->prepare("
    SELECT * FROM performance_ratings 
    WHERE review_id = ? 
    ORDER BY rating_category, rating_name
");
$ratings_stmt->execute([$review_id]);
$all_ratings = $ratings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group ratings by category
$ratings = [];
foreach ($all_ratings as $rating) {
    $ratings[$rating['rating_category']][] = $rating;
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

// Calculate rating statistics
$rating_stats = [
    'total_ratings' => count($all_ratings),
    'avg_rating' => 0,
    'category_averages' => []
];

if (!empty($all_ratings)) {
    $total_score = 0;
    $category_totals = [];
    $category_counts = [];
    
    foreach ($all_ratings as $rating) {
        $total_score += $rating['rating_value'];
        
        if (!isset($category_totals[$rating['rating_category']])) {
            $category_totals[$rating['rating_category']] = 0;
            $category_counts[$rating['rating_category']] = 0;
        }
        
        $category_totals[$rating['rating_category']] += $rating['rating_value'];
        $category_counts[$rating['rating_category']]++;
    }
    
    $rating_stats['avg_rating'] = round($total_score / count($all_ratings), 2);
    
    foreach ($category_totals as $category => $total) {
        $rating_stats['category_averages'][$category] = round($total / $category_counts[$category], 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Review - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Performance Review</h1>
                        <p class="text-gray-600">
                            <?php echo htmlspecialchars($review['employee_first_name'] . ' ' . $review['employee_last_name']); ?> - 
                            <?php echo htmlspecialchars($review['cycle_name'] . ' ' . $review['cycle_year']); ?>
                        </p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="reviews.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Reviews
                        </a>
                        <?php if ($review['status'] != 'completed' && (hasRole('hr_recruiter') || $review['reviewer_id'] == $_SESSION['user_id'])): ?>
                        <a href="conduct_review.php?id=<?php echo $review_id; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-edit mr-2"></i>Continue Review
                        </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>

            <!-- Review Summary Card -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Employee Information</h3>
                        <p class="font-medium"><?php echo htmlspecialchars($review['employee_first_name'] . ' ' . $review['employee_last_name']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['employee_number']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['department']); ?></p>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['position']); ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Review Details</h3>
                        <p class="text-sm text-gray-600">Type: <span class="font-medium"><?php echo ucfirst($review['review_type']); ?> Review</span></p>
                        <p class="text-sm text-gray-600">Reviewer: <span class="font-medium"><?php echo htmlspecialchars($review['reviewer_first_name'] . ' ' . $review['reviewer_last_name']); ?></span></p>
                        <p class="text-sm text-gray-600">Due Date: <span class="font-medium"><?php echo date('M j, Y', strtotime($review['due_date'])); ?></span></p>
                        <p class="text-sm text-gray-600">Status: 
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $review['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $review['status'])); ?>
                            </span>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Performance Summary</h3>
                        <?php if ($review['overall_rating']): ?>
                            <div class="flex items-center mb-2">
                                <span class="text-2xl font-bold text-blue-600 mr-2"><?php echo $review['overall_rating']; ?></span>
                                <span class="text-gray-600">/5.0</span>
                            </div>
                            <div class="flex items-center text-yellow-500 mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $review['overall_rating'] ? '' : '-o'; ?> mr-1"></i>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500">No rating provided</p>
                        <?php endif; ?>
                        
                        <?php if ($rating_stats['total_ratings'] > 0): ?>
                            <p class="text-sm text-gray-600">Detailed Ratings: <?php echo $rating_stats['total_ratings']; ?></p>
                            <p class="text-sm text-gray-600">Average Score: <?php echo $rating_stats['avg_rating']; ?>/5.0</p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Timeline</h3>
                        <p class="text-sm text-gray-600">Created: <?php echo date('M j, Y', strtotime($review['created_at'])); ?></p>
                        <?php if ($review['submitted_at']): ?>
                            <p class="text-sm text-gray-600">Submitted: <?php echo date('M j, Y', strtotime($review['submitted_at'])); ?></p>
                        <?php endif; ?>
                        <?php if ($review['reviewed_at']): ?>
                            <p class="text-sm text-gray-600">Reviewed: <?php echo date('M j, Y', strtotime($review['reviewed_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Goal Performance Section -->
            <?php if (!empty($ratings['goal'])): ?>
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-bullseye text-blue-600 mr-2"></i>Goal Performance
                    </h2>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <?php foreach ($ratings['goal'] as $rating): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($rating['rating_name']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($rating['rating_description']); ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center">
                                        <span class="text-2xl font-bold text-blue-600 mr-1"><?php echo $rating['rating_value']; ?></span>
                                        <span class="text-gray-600">/<?php echo $rating['max_rating']; ?></span>
                                    </div>
                                    <?php if ($rating['weight_percentage'] > 0): ?>
                                        <p class="text-sm text-gray-500">Weight: <?php echo $rating['weight_percentage']; ?>%</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Rating visualization -->
                            <div class="mb-3">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600">Performance Level</span>
                                    <span class="text-sm font-medium text-gray-800">
                                        <?php 
                                        $performance_levels = [1 => 'Does Not Meet', 2 => 'Partially Meets', 3 => 'Meets', 4 => 'Exceeds', 5 => 'Outstanding'];
                                        echo $performance_levels[$rating['rating_value']] ?? 'Not Rated';
                                        ?>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="h-3 rounded-full <?php echo $rating['rating_value'] >= 4 ? 'bg-green-500' : ($rating['rating_value'] >= 3 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                                         style="width: <?php echo ($rating['rating_value'] / $rating['max_rating']) * 100; ?>%"></div>
                                </div>
                            </div>
                            
                            <?php if ($rating['comments']): ?>
                            <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-700"><strong>Comments:</strong> <?php echo nl2br(htmlspecialchars($rating['comments'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Competency Assessment Section -->
            <?php if (!empty($ratings['competency'])): ?>
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-star text-yellow-600 mr-2"></i>Competency Assessment
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($ratings['competency'] as $rating): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($rating['rating_name']); ?></h3>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($rating['rating_description']); ?></p>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center">
                                        <span class="text-xl font-bold text-yellow-600 mr-1"><?php echo $rating['rating_value']; ?></span>
                                        <span class="text-gray-600">/<?php echo $rating['max_rating']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Competency level visualization -->
                            <div class="mb-3">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm text-gray-600">Proficiency Level</span>
                                    <span class="text-sm font-medium text-gray-800">
                                        <?php 
                                        $proficiency_levels = [1 => 'Beginner', 2 => 'Developing', 3 => 'Proficient', 4 => 'Advanced', 5 => 'Expert'];
                                        echo $proficiency_levels[$rating['rating_value']] ?? 'Not Rated';
                                        ?>
                                    </span>
                                </div>
                                <div class="flex space-x-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="flex-1 h-2 rounded <?php echo $i <= $rating['rating_value'] ? 'bg-yellow-500' : 'bg-gray-200'; ?>"></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <?php if ($rating['comments']): ?>
                            <div class="mt-3 p-3 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($rating['comments'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Overall Assessment Section -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-clipboard-check text-green-600 mr-2"></i>Overall Assessment
                    </h2>
                </div>
                <div class="p-6">
                    <?php if ($review['overall_comments']): ?>
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-800 mb-3">Overall Comments</h3>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['overall_comments'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if ($review['strengths']): ?>
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-3">
                                <i class="fas fa-thumbs-up text-green-600 mr-2"></i>Key Strengths
                            </h3>
                            <div class="p-4 bg-green-50 rounded-lg">
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['strengths'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($review['areas_for_improvement']): ?>
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-3">
                                <i class="fas fa-arrow-up text-blue-600 mr-2"></i>Areas for Improvement
                            </h3>
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['areas_for_improvement'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <?php if ($review['achievements']): ?>
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-3">
                                <i class="fas fa-trophy text-yellow-600 mr-2"></i>Key Achievements
                            </h3>
                            <div class="p-4 bg-yellow-50 rounded-lg">
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['achievements'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($review['development_needs']): ?>
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-3">
                                <i class="fas fa-graduation-cap text-purple-600 mr-2"></i>Development Needs
                            </h3>
                            <div class="p-4 bg-purple-50 rounded-lg">
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['development_needs'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($review['goals_for_next_period']): ?>
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-800 mb-3">
                            <i class="fas fa-bullseye text-indigo-600 mr-2"></i>Goals for Next Period
                        </h3>
                        <div class="p-4 bg-indigo-50 rounded-lg">
                            <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['goals_for_next_period'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Performance Analytics -->
            <?php if (!empty($rating_stats['category_averages'])): ?>
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-chart-bar text-indigo-600 mr-2"></i>Performance Analytics
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Rating Distribution Chart -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-4">Rating Distribution</h3>
                            <canvas id="ratingChart" width="400" height="200"></canvas>
                        </div>
                        
                        <!-- Category Performance -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-4">Category Performance</h3>
                            <div class="space-y-3">
                                <?php foreach ($rating_stats['category_averages'] as $category => $average): ?>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm font-medium text-gray-700 capitalize"><?php echo str_replace('_', ' ', $category); ?></span>
                                        <span class="text-sm text-gray-600"><?php echo $average; ?>/5.0</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="h-2 rounded-full <?php echo $average >= 4 ? 'bg-green-500' : ($average >= 3 ? 'bg-yellow-500' : 'bg-red-500'); ?>" 
                                             style="width: <?php echo ($average / 5) * 100; ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Rating Distribution Chart
        <?php if (!empty($rating_stats['category_averages'])): ?>
        const ctx = document.getElementById('ratingChart').getContext('2d');
        const ratingChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: [<?php echo "'" . implode("', '", array_map(function($cat) { return ucwords(str_replace('_', ' ', $cat)); }, array_keys($rating_stats['category_averages']))) . "'"; ?>],
                datasets: [{
                    label: 'Performance Rating',
                    data: [<?php echo implode(', ', array_values($rating_stats['category_averages'])); ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(59, 130, 246, 1)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    r: {
                        min: 0,
                        max: 5,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html> 