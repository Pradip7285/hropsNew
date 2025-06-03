<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->beginTransaction();
        
        // Create main feedback request
        $stmt = $conn->prepare("
            INSERT INTO feedback_360_requests (employee_id, cycle_id, requested_by, title, description, deadline, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $_POST['employee_id'], $_POST['cycle_id'], $_SESSION['user_id'],
            $_POST['title'], $_POST['description'], $_POST['deadline']
        ]);
        
        $request_id = $conn->lastInsertId();
        
        // Add feedback providers
        if (isset($_POST['providers']) && is_array($_POST['providers'])) {
            $provider_stmt = $conn->prepare("
                INSERT INTO feedback_360_providers (request_id, provider_id, relationship_type, status)
                VALUES (?, ?, ?, 'pending')
            ");
            
            foreach ($_POST['providers'] as $provider) {
                if (!empty($provider['provider_id']) && !empty($provider['relationship_type'])) {
                    $provider_stmt->execute([
                        $request_id, $provider['provider_id'], $provider['relationship_type']
                    ]);
                }
            }
        }
        
        // Add feedback questions/template
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $question_stmt = $conn->prepare("
                INSERT INTO feedback_360_questions (request_id, question_text, question_type, question_order, required)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['questions'] as $index => $question) {
                if (!empty($question['text'])) {
                    $question_stmt->execute([
                        $request_id, $question['text'], $question['type'],
                        $index + 1, isset($question['required']) ? 1 : 0
                    ]);
                }
            }
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "360° feedback request created successfully!";
        header('Location: feedback_360.php');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error creating feedback request: " . $e->getMessage();
    }
}

// Get employees
$employees_stmt = $conn->query("
    SELECT id, first_name, last_name, employee_id, department, position 
    FROM employees 
    WHERE status = 'active' 
    ORDER BY first_name, last_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cycles
$cycles_stmt = $conn->query("
    SELECT id, cycle_name, cycle_year, start_date, end_date 
    FROM performance_cycles 
    WHERE status = 'active' 
    ORDER BY cycle_year DESC, cycle_name
");
$cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get feedback templates
$templates_stmt = $conn->query("
    SELECT id, template_name, description 
    FROM feedback_360_templates 
    WHERE is_active = 1 
    ORDER BY template_name
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create 360° Feedback Request - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Create 360° Feedback Request</h1>
                        <p class="text-gray-600">Set up comprehensive multi-source feedback collection</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="feedback_360.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to 360° Feedback
                        </a>
                        <a href="feedback_templates.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-file-alt mr-2"></i>Manage Templates
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-800">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>Basic Information
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Employee *</label>
                                <select name="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" onchange="updateProviders()">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" 
                                            data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                            data-position="<?php echo htmlspecialchars($employee['position']); ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_id'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Performance Cycle *</label>
                                <select name="cycle_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Cycle</option>
                                    <?php foreach ($cycles as $cycle): ?>
                                    <option value="<?php echo $cycle['id']; ?>">
                                        <?php echo htmlspecialchars($cycle['cycle_name'] . ' ' . $cycle['cycle_year']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Request Title *</label>
                            <input type="text" name="title" required 
                                   placeholder="e.g., Q4 2024 360° Feedback for John Doe"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea name="description" rows="3" 
                                      placeholder="Provide context and instructions for feedback providers..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Deadline *</label>
                            <input type="date" name="deadline" required 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Feedback Providers -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">
                                <i class="fas fa-users text-green-600 mr-2"></i>Feedback Providers
                            </h2>
                            <button type="button" onclick="addProvider()" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                <i class="fas fa-plus mr-1"></i>Add Provider
                            </button>
                        </div>
                        <p class="text-gray-600 mt-2">Select people who will provide feedback from different perspectives</p>
                    </div>
                    <div class="p-6">
                        <div id="providers-container" class="space-y-4">
                            <!-- Providers will be added here dynamically -->
                        </div>
                        
                        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                            <h4 class="font-medium text-blue-900 mb-2">Recommended Provider Mix:</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li><i class="fas fa-check mr-2"></i>1 Direct Manager (Manager)</li>
                                <li><i class="fas fa-check mr-2"></i>2-3 Peers/Colleagues (Peer)</li>
                                <li><i class="fas fa-check mr-2"></i>1-2 Direct Reports if applicable (Subordinate)</li>
                                <li><i class="fas fa-check mr-2"></i>1 Self-Assessment (Self)</li>
                                <li><i class="fas fa-check mr-2"></i>1-2 Internal Customers (Other)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Feedback Questions -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-800">
                                <i class="fas fa-question-circle text-purple-600 mr-2"></i>Feedback Questions
                            </h2>
                            <div class="flex space-x-2">
                                <select id="template-select" class="px-3 py-1 border border-gray-300 rounded text-sm" onchange="loadTemplate()">
                                    <option value="">Load Template</option>
                                    <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['template_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" onclick="addQuestion()" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                    <i class="fas fa-plus mr-1"></i>Add Question
                                </button>
                            </div>
                        </div>
                        <p class="text-gray-600 mt-2">Define the questions that feedback providers will answer</p>
                    </div>
                    <div class="p-6">
                        <div id="questions-container" class="space-y-4">
                            <!-- Questions will be added here -->
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between items-center bg-white rounded-lg shadow-md p-6">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        All fields marked with * are required
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="feedback_360.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create Request
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        let providerCount = 0;
        let questionCount = 0;
        const employees = <?php echo json_encode($employees); ?>;

        // Add initial provider and question
        document.addEventListener('DOMContentLoaded', function() {
            addProvider();
            addQuestion();
        });

        function addProvider() {
            const container = document.getElementById('providers-container');
            const providerHtml = `
                <div class="provider-row border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-800">Provider ${providerCount + 1}</h4>
                        <button type="button" onclick="removeProvider(this)" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee</label>
                            <select name="providers[${providerCount}][provider_id]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Employee</option>
                                ${employees.map(emp => `<option value="${emp.id}">${emp.first_name} ${emp.last_name} (${emp.department})</option>`).join('')}
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Relationship</label>
                            <select name="providers[${providerCount}][relationship_type]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Relationship</option>
                                <option value="self">Self</option>
                                <option value="manager">Manager</option>
                                <option value="peer">Peer</option>
                                <option value="subordinate">Subordinate</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', providerHtml);
            providerCount++;
        }

        function removeProvider(button) {
            button.closest('.provider-row').remove();
        }

        function addQuestion() {
            const container = document.getElementById('questions-container');
            const questionHtml = `
                <div class="question-row border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-800">Question ${questionCount + 1}</h4>
                        <button type="button" onclick="removeQuestion(this)" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Question Text</label>
                            <textarea name="questions[${questionCount}][text]" rows="3" 
                                      placeholder="Enter the feedback question..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Question Type</label>
                                <select name="questions[${questionCount}][type]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="text">Open Text</option>
                                    <option value="rating_5">5-Point Rating Scale</option>
                                    <option value="rating_10">10-Point Rating Scale</option>
                                    <option value="multiple_choice">Multiple Choice</option>
                                </select>
                            </div>
                            
                            <div class="flex items-center">
                                <label class="flex items-center">
                                    <input type="checkbox" name="questions[${questionCount}][required]" value="1" 
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Required Question</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', questionHtml);
            questionCount++;
        }

        function removeQuestion(button) {
            button.closest('.question-row').remove();
        }

        function updateProviders() {
            const employeeSelect = document.querySelector('select[name="employee_id"]');
            const selectedEmployeeId = employeeSelect.value;
            
            // Filter out the selected employee from provider options
            const providerSelects = document.querySelectorAll('select[name*="[provider_id]"]');
            providerSelects.forEach(select => {
                // Reset and repopulate options
                select.innerHTML = '<option value="">Select Employee</option>';
                employees.forEach(emp => {
                    if (emp.id != selectedEmployeeId) {
                        select.innerHTML += `<option value="${emp.id}">${emp.first_name} ${emp.last_name} (${emp.department})</option>`;
                    }
                });
            });
        }

        function loadTemplate() {
            const templateSelect = document.getElementById('template-select');
            const templateId = templateSelect.value;
            
            if (!templateId) return;
            
            // Fetch template questions via AJAX
            fetch(`get_template_questions.php?id=${templateId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear existing questions
                        document.getElementById('questions-container').innerHTML = '';
                        questionCount = 0;
                        
                        // Add template questions
                        data.questions.forEach(question => {
                            addQuestion();
                            const lastQuestion = document.querySelector('.question-row:last-child');
                            lastQuestion.querySelector('textarea[name*="[text]"]').value = question.question_text;
                            lastQuestion.querySelector('select[name*="[type]"]').value = question.question_type;
                            if (question.required) {
                                lastQuestion.querySelector('input[name*="[required]"]').checked = true;
                            }
                        });
                    }
                })
                .catch(error => console.error('Error loading template:', error));
        }

        // Auto-generate title when employee and cycle are selected
        document.addEventListener('change', function(e) {
            if (e.target.name === 'employee_id' || e.target.name === 'cycle_id') {
                generateTitle();
            }
        });

        function generateTitle() {
            const employeeSelect = document.querySelector('select[name="employee_id"]');
            const cycleSelect = document.querySelector('select[name="cycle_id"]');
            const titleInput = document.querySelector('input[name="title"]');
            
            if (employeeSelect.value && cycleSelect.value && !titleInput.value) {
                const employeeName = employeeSelect.options[employeeSelect.selectedIndex].text.split(' (')[0];
                const cycleName = cycleSelect.options[cycleSelect.selectedIndex].text;
                titleInput.value = `${cycleName} 360° Feedback for ${employeeName}`;
            }
        }
    </script>
</body>
</html> 