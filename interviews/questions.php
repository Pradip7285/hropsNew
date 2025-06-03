<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Create interview_questions table if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS interview_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL,
            question_type ENUM('behavioral', 'technical', 'situational', 'general') NOT NULL,
            difficulty_level ENUM('entry', 'intermediate', 'senior', 'expert') NOT NULL,
            question TEXT NOT NULL,
            suggested_answer TEXT,
            follow_up_questions TEXT,
            tags VARCHAR(255),
            department VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS interview_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            department VARCHAR(100),
            position_level ENUM('entry', 'intermediate', 'senior', 'executive') NOT NULL,
            duration INT DEFAULT 60,
            question_ids TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");
} catch (Exception $e) {
    // Tables might already exist
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_question') {
        $category = trim($_POST['category']);
        $question_type = $_POST['question_type'];
        $difficulty_level = $_POST['difficulty_level'];
        $question = trim($_POST['question']);
        $suggested_answer = trim($_POST['suggested_answer']);
        $follow_up_questions = trim($_POST['follow_up_questions']);
        $tags = trim($_POST['tags']);
        $department = trim($_POST['department']);
        
        if (empty($category) || empty($question)) {
            $error = 'Category and question are required.';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO interview_questions 
                    (category, question_type, difficulty_level, question, suggested_answer, follow_up_questions, tags, department, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$category, $question_type, $difficulty_level, $question, $suggested_answer, $follow_up_questions, $tags, $department, $_SESSION['user_id']]);
                
                logActivity($_SESSION['user_id'], 'question_created', 'question', $conn->lastInsertId(), "Created interview question: " . substr($question, 0, 50) . "...");
                $success = 'Question added successfully!';
            } catch (Exception $e) {
                $error = 'Error adding question: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'create_template') {
        $name = trim($_POST['template_name']);
        $description = trim($_POST['template_description']);
        $department = trim($_POST['template_department']);
        $position_level = $_POST['position_level'];
        $duration = $_POST['template_duration'];
        $question_ids = $_POST['selected_questions'] ?? [];
        
        if (empty($name) || empty($question_ids)) {
            $error = 'Template name and at least one question are required.';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO interview_templates 
                    (name, description, department, position_level, duration, question_ids, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $department, $position_level, $duration, json_encode($question_ids), $_SESSION['user_id']]);
                
                logActivity($_SESSION['user_id'], 'template_created', 'template', $conn->lastInsertId(), "Created interview template: $name");
                $success = 'Template created successfully!';
            } catch (Exception $e) {
                $error = 'Error creating template: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'toggle_question_status') {
        $question_id = $_POST['question_id'];
        $is_active = $_POST['is_active'] ? 1 : 0;
        
        try {
            $stmt = $conn->prepare("UPDATE interview_questions SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $question_id]);
            
            $status = $is_active ? 'activated' : 'deactivated';
            logActivity($_SESSION['user_id'], 'question_status_changed', 'question', $question_id, "Question $status");
            $success = "Question $status successfully.";
        } catch (Exception $e) {
            $error = 'Error updating question status: ' . $e->getMessage();
        }
    }
}

// Get filters
$category_filter = $_GET['category'] ?? '';
$type_filter = $_GET['type'] ?? '';
$level_filter = $_GET['level'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Build where conditions for questions
$where_conditions = [];
$params = [];

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "question_type = ?";
    $params[] = $type_filter;
}

if (!empty($level_filter)) {
    $where_conditions[] = "difficulty_level = ?";
    $params[] = $level_filter;
}

if (!empty($department_filter)) {
    $where_conditions[] = "department = ?";
    $params[] = $department_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get questions
$questions_query = "
    SELECT q.*, u.first_name, u.last_name
    FROM interview_questions q
    JOIN users u ON q.created_by = u.id
    $where_clause
    ORDER BY q.created_at DESC
";

$questions_stmt = $conn->prepare($questions_query);
$questions_stmt->execute($params);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get templates
$templates_stmt = $conn->query("
    SELECT t.*, u.first_name, u.last_name
    FROM interview_templates t
    JOIN users u ON t.created_by = u.id
    ORDER BY t.created_at DESC
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories, departments for filters
$categories_stmt = $conn->query("SELECT DISTINCT category FROM interview_questions ORDER BY category");
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

$departments_stmt = $conn->query("SELECT DISTINCT department FROM job_postings ORDER BY department");
$departments = $departments_stmt->fetchAll(PDO::FETCH_COLUMN);

// Question statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_questions,
        SUM(CASE WHEN question_type = 'technical' THEN 1 ELSE 0 END) as technical_count,
        SUM(CASE WHEN question_type = 'behavioral' THEN 1 ELSE 0 END) as behavioral_count,
        SUM(CASE WHEN question_type = 'situational' THEN 1 ELSE 0 END) as situational_count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
        COUNT(DISTINCT category) as unique_categories
    FROM interview_questions
");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Questions & Templates - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Interview Questions & Templates</h1>
                        <p class="text-gray-600">Manage interview question bank and create templates</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                        <button onclick="showQuestionModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Add Question
                        </button>
                        <button onclick="showTemplateModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-layer-group mr-2"></i>Create Template
                        </button>
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

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-blue-100 p-2 rounded-full mr-3">
                            <i class="fas fa-question-circle text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Questions</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['total_questions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-green-100 p-2 rounded-full mr-3">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Active</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['active_count']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-purple-100 p-2 rounded-full mr-3">
                            <i class="fas fa-layer-group text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Templates</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo count($templates); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="bg-yellow-100 p-2 rounded-full mr-3">
                            <i class="fas fa-tags text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Categories</p>
                            <p class="text-xl font-bold text-gray-800"><?php echo $stats['unique_categories']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex">
                        <button onclick="showTab('questions')" id="questionsTab" class="px-6 py-3 text-sm font-medium text-blue-600 border-b-2 border-blue-500">
                            <i class="fas fa-question-circle mr-2"></i>Questions Bank
                        </button>
                        <button onclick="showTab('templates')" id="templatesTab" class="px-6 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700">
                            <i class="fas fa-layer-group mr-2"></i>Interview Templates
                        </button>
                    </nav>
                </div>

                <!-- Questions Tab -->
                <div id="questionsContent" class="p-6">
                    <!-- Filters -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter == $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Types</option>
                                    <option value="behavioral" <?php echo $type_filter == 'behavioral' ? 'selected' : ''; ?>>Behavioral</option>
                                    <option value="technical" <?php echo $type_filter == 'technical' ? 'selected' : ''; ?>>Technical</option>
                                    <option value="situational" <?php echo $type_filter == 'situational' ? 'selected' : ''; ?>>Situational</option>
                                    <option value="general" <?php echo $type_filter == 'general' ? 'selected' : ''; ?>>General</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                                <select name="level" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Levels</option>
                                    <option value="entry" <?php echo $level_filter == 'entry' ? 'selected' : ''; ?>>Entry</option>
                                    <option value="intermediate" <?php echo $level_filter == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="senior" <?php echo $level_filter == 'senior' ? 'selected' : ''; ?>>Senior</option>
                                    <option value="expert" <?php echo $level_filter == 'expert' ? 'selected' : ''; ?>>Expert</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter == $dept ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-filter mr-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Questions Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <?php foreach ($questions as $question): ?>
                        <div class="bg-white rounded-lg shadow-md p-6 <?php echo !$question['is_active'] ? 'opacity-75' : ''; ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center mb-2">
                                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mr-2">
                                            <?php echo ucfirst($question['question_type']); ?>
                                        </span>
                                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full mr-2">
                                            <?php echo ucfirst($question['difficulty_level']); ?>
                                        </span>
                                        <?php if ($question['is_active']): ?>
                                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Active</span>
                                        <?php else: ?>
                                        <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($question['category']); ?></h3>
                                </div>
                                <div class="relative">
                                    <button onclick="toggleQuestionDropdown(<?php echo $question['id']; ?>)" class="text-gray-400 hover:text-gray-600">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div id="questionDropdown-<?php echo $question['id']; ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                        <div class="py-1">
                                            <button onclick="toggleQuestionStatus(<?php echo $question['id']; ?>, <?php echo $question['is_active'] ? 'false' : 'true'; ?>)"
                                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-power-off mr-2"></i>
                                                <?php echo $question['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-gray-900 font-medium mb-2"><?php echo htmlspecialchars($question['question']); ?></p>
                                
                                <?php if ($question['suggested_answer']): ?>
                                <div class="bg-gray-50 p-3 rounded-lg mb-3">
                                    <p class="text-xs font-medium text-gray-700 mb-1">Suggested Answer:</p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars(substr($question['suggested_answer'], 0, 150)) . '...'; ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($question['tags']): ?>
                                <div class="flex flex-wrap gap-1 mb-3">
                                    <?php foreach (explode(',', $question['tags']) as $tag): ?>
                                    <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">
                                        <?php echo htmlspecialchars(trim($tag)); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span>
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($question['first_name'] . ' ' . $question['last_name']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php echo timeAgo($question['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($questions)): ?>
                        <div class="col-span-full text-center py-12">
                            <i class="fas fa-question-circle text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">No questions found</h3>
                            <p class="text-gray-500 mb-4">Start building your interview question bank.</p>
                            <button onclick="showQuestionModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition duration-200">
                                <i class="fas fa-plus mr-2"></i>Add First Question
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Templates Tab -->
                <div id="templatesContent" class="p-6 hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($templates as $template): ?>
                        <?php
                        $question_ids = json_decode($template['question_ids'], true) ?: [];
                        $question_count = count($question_ids);
                        ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($template['name']); ?></h3>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($template['description']); ?></p>
                                </div>
                                <span class="<?php echo $template['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> text-xs px-2 py-1 rounded-full">
                                    <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-building mr-2"></i>
                                    <?php echo htmlspecialchars($template['department'] ?: 'All Departments'); ?>
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-layer-group mr-2"></i>
                                    <?php echo ucfirst($template['position_level']); ?> Level
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-clock mr-2"></i>
                                    <?php echo $template['duration']; ?> minutes
                                </div>
                                <div class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-question-circle mr-2"></i>
                                    <?php echo $question_count; ?> questions
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span>
                                    <i class="fas fa-user mr-1"></i>
                                    <?php echo htmlspecialchars($template['first_name'] . ' ' . $template['last_name']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-calendar mr-1"></i>
                                    <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($templates)): ?>
                        <div class="col-span-full text-center py-12">
                            <i class="fas fa-layer-group text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">No templates yet</h3>
                            <p class="text-gray-500 mb-4">Create interview templates to streamline your process.</p>
                            <button onclick="showTemplateModal()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg transition duration-200">
                                <i class="fas fa-plus mr-2"></i>Create First Template
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Question Modal -->
    <div id="questionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Add Interview Question</h3>
                    <button onclick="closeQuestionModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_question">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                            <input type="text" name="category" required 
                                   placeholder="e.g., Leadership, Problem Solving"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                            <select name="question_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="behavioral">Behavioral</option>
                                <option value="technical">Technical</option>
                                <option value="situational">Situational</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Difficulty Level *</label>
                            <select name="difficulty_level" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="entry">Entry</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="senior">Senior</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select name="department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Question *</label>
                        <textarea name="question" rows="3" required 
                                  placeholder="Enter the interview question..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Suggested Answer</label>
                        <textarea name="suggested_answer" rows="3" 
                                  placeholder="Provide guidance on what to look for in answers..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Follow-up Questions</label>
                        <textarea name="follow_up_questions" rows="2" 
                                  placeholder="Additional questions to dive deeper..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                        <input type="text" name="tags" 
                               placeholder="Comma-separated tags (e.g., leadership, teamwork, communication)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeQuestionModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i>Add Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Template Modal -->
    <div id="templateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Create Interview Template</h3>
                    <button onclick="closeTemplateModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_template">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Template Name *</label>
                            <input type="text" name="template_name" required 
                                   placeholder="e.g., Senior Developer Interview"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes)</label>
                            <input type="number" name="template_duration" value="60" min="15" max="240"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                            <select name="template_department" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Position Level *</label>
                            <select name="position_level" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="entry">Entry Level</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="senior">Senior</option>
                                <option value="executive">Executive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="template_description" rows="2" 
                                  placeholder="Brief description of this interview template..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Questions *</label>
                        <div class="max-h-60 overflow-y-auto border border-gray-300 rounded-lg p-4">
                            <?php foreach ($questions as $question): ?>
                            <?php if ($question['is_active']): ?>
                            <label class="flex items-start space-x-3 mb-3">
                                <input type="checkbox" name="selected_questions[]" value="<?php echo $question['id']; ?>" 
                                       class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                            <?php echo ucfirst($question['question_type']); ?>
                                        </span>
                                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                            <?php echo ucfirst($question['difficulty_level']); ?>
                                        </span>
                                        <span class="text-xs text-gray-600"><?php echo htmlspecialchars($question['category']); ?></span>
                                    </div>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($question['question']); ?></p>
                                </div>
                            </label>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeTemplateModal()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i>Create Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all content
            document.getElementById('questionsContent').classList.add('hidden');
            document.getElementById('templatesContent').classList.add('hidden');
            
            // Reset all tabs
            document.getElementById('questionsTab').className = 'px-6 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700';
            document.getElementById('templatesTab').className = 'px-6 py-3 text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700';
            
            // Show selected content and highlight tab
            if (tabName === 'questions') {
                document.getElementById('questionsContent').classList.remove('hidden');
                document.getElementById('questionsTab').className = 'px-6 py-3 text-sm font-medium text-blue-600 border-b-2 border-blue-500';
            } else {
                document.getElementById('templatesContent').classList.remove('hidden');
                document.getElementById('templatesTab').className = 'px-6 py-3 text-sm font-medium text-blue-600 border-b-2 border-blue-500';
            }
        }

        function showQuestionModal() {
            document.getElementById('questionModal').classList.remove('hidden');
        }

        function closeQuestionModal() {
            document.getElementById('questionModal').classList.add('hidden');
        }

        function showTemplateModal() {
            document.getElementById('templateModal').classList.remove('hidden');
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').classList.add('hidden');
        }

        function toggleQuestionDropdown(id) {
            const dropdown = document.getElementById(`questionDropdown-${id}`);
            dropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            document.querySelectorAll('[id^="questionDropdown-"]').forEach(el => {
                if (el.id !== `questionDropdown-${id}`) {
                    el.classList.add('hidden');
                }
            });
        }

        function toggleQuestionStatus(id, isActive) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_question_status">
                <input type="hidden" name="question_id" value="${id}">
                <input type="hidden" name="is_active" value="${isActive}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[onclick*="toggleQuestionDropdown"]')) {
                document.querySelectorAll('[id^="questionDropdown-"]').forEach(el => {
                    el.classList.add('hidden');
                });
            }
        });
    </script>
</body>
</html> 