<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Get managers for dropdown
$managers_stmt = $conn->prepare("
    SELECT id, first_name, last_name, role 
    FROM users 
    WHERE role IN ('hiring_manager', 'admin') AND is_active = 1
    ORDER BY first_name, last_name
");
$managers_stmt->execute();
$managers = $managers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get potential buddies (all active users)
$buddies_stmt = $conn->prepare("
    SELECT id, first_name, last_name, role 
    FROM users 
    WHERE is_active = 1
    ORDER BY first_name, last_name
");
$buddies_stmt->execute();
$buddies = $buddies_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get job postings for reference
$jobs_stmt = $conn->query("
    SELECT id, title, department, salary_range 
    FROM job_postings 
    WHERE status = 'active'
    ORDER BY department, title
");
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get onboarding templates
$templates_stmt = $conn->query("
    SELECT id, name, description, department, position_level, duration_days
    FROM onboarding_templates 
    WHERE is_active = 1
    ORDER BY name
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $employee_id = trim($_POST['employee_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $job_id = $_POST['job_id'] ?: null;
    $department = trim($_POST['department']);
    $position_title = trim($_POST['position_title']);
    $start_date = $_POST['start_date'];
    $employment_type = $_POST['employment_type'];
    $salary = trim($_POST['salary']);
    $manager_id = $_POST['manager_id'] ?: null;
    $buddy_id = $_POST['buddy_id'] ?: null;
    $office_location = trim($_POST['office_location']);
    $work_arrangement = $_POST['work_arrangement'];
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    $template_id = $_POST['template_id'] ?: null;
    $auto_start_onboarding = isset($_POST['auto_start_onboarding']);
    $notes = trim($_POST['notes']);
    
    // Validation
    if (empty($employee_id)) {
        $error = 'Employee ID is required.';
    } elseif (empty($first_name)) {
        $error = 'First name is required.';
    } elseif (empty($last_name)) {
        $error = 'Last name is required.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email address is required.';
    } elseif (empty($department)) {
        $error = 'Department is required.';
    } elseif (empty($position_title)) {
        $error = 'Position title is required.';
    } elseif (empty($start_date)) {
        $error = 'Start date is required.';
    } elseif ($salary && !is_numeric($salary)) {
        $error = 'Salary must be a valid number.';
    } else {
        try {
            // Check if employee ID or email already exists
            $check_stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ? OR email = ?");
            $check_stmt->execute([$employee_id, $email]);
            if ($check_stmt->fetch()) {
                $error = 'Employee ID or email already exists.';
            } else {
                // Insert new employee
                $insert_stmt = $conn->prepare("
                    INSERT INTO employees (
                        employee_id, first_name, last_name, email, phone, job_id,
                        department, position_title, start_date, employment_type, salary,
                        manager_id, buddy_id, office_location, work_arrangement,
                        emergency_contact_name, emergency_contact_phone, notes, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insert_stmt->execute([
                    $employee_id, $first_name, $last_name, $email, $phone, $job_id,
                    $department, $position_title, $start_date, $employment_type, $salary,
                    $manager_id, $buddy_id, $office_location, $work_arrangement,
                    $emergency_contact_name, $emergency_contact_phone, $notes, $_SESSION['user_id']
                ]);
                
                $new_employee_id = $conn->lastInsertId();
                
                // If template is selected or auto-start is enabled, create initial tasks
                if ($template_id || $auto_start_onboarding) {
                    $template_to_use = $template_id ?: 1; // Use first template as default
                    createOnboardingTasks($new_employee_id, $template_to_use, $start_date, $conn);
                    
                    // Update onboarding status if auto-starting
                    if ($auto_start_onboarding) {
                        $conn->prepare("UPDATE employees SET onboarding_status = 'in_progress', onboarding_start_date = NOW() WHERE id = ?")
                              ->execute([$new_employee_id]);
                    }
                }
                
                // Log activity
                logActivity(
                    $_SESSION['user_id'], 
                    'employee_created', 
                    'employee', 
                    $new_employee_id,
                    "Created new employee: $first_name $last_name"
                );
                
                $success = "Employee $first_name $last_name has been successfully added.";
                
                // Clear form data after successful submission
                $_POST = [];
            }
        } catch (Exception $e) {
            $error = 'Error creating employee: ' . $e->getMessage();
        }
    }
}

// Function to create onboarding tasks from template
function createOnboardingTasks($employee_id, $template_id, $start_date, $conn) {
    $tasks_stmt = $conn->prepare("
        SELECT * FROM onboarding_template_tasks 
        WHERE template_id = ? 
        ORDER BY sort_order, due_days
    ");
    $tasks_stmt->execute([$template_id]);
    $template_tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($template_tasks as $task) {
        $due_date = date('Y-m-d', strtotime($start_date . ' + ' . $task['due_days'] . ' days'));
        
        $insert_task = $conn->prepare("
            INSERT INTO onboarding_tasks (
                employee_id, template_task_id, task_name, description, category,
                priority, due_date, estimated_hours, assignee_role, is_required,
                sort_order, instructions
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insert_task->execute([
            $employee_id, $task['id'], $task['task_name'], $task['description'],
            $task['category'], $task['priority'], $due_date, $task['estimated_hours'],
            $task['assignee_role'], $task['is_required'], $task['sort_order'], $task['instructions']
        ]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Add New Employee</h1>
                        <p class="text-gray-600">Create a new employee record and set up onboarding</p>
                    </div>
                    <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Employee List
                    </a>
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
                <div class="mt-2 flex space-x-2">
                    <a href="list.php" class="text-green-700 underline">View Employee List</a>
                    <span class="text-green-600">|</span>
                    <button onclick="location.reload()" class="text-green-700 underline">Add Another Employee</button>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Information -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Personal Information -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-user text-blue-500 mr-2"></i>
                                Personal Information
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID *</label>
                                    <input type="text" name="employee_id" value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>" 
                                           placeholder="EMP001" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="john.doe@company.com" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" 
                                           placeholder="John" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" 
                                           placeholder="Doe" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                           placeholder="(555) 123-4567"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Work Arrangement</label>
                                    <select name="work_arrangement" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="on_site" <?php echo ($_POST['work_arrangement'] ?? '') == 'on_site' ? 'selected' : ''; ?>>On-Site</option>
                                        <option value="remote" <?php echo ($_POST['work_arrangement'] ?? '') == 'remote' ? 'selected' : ''; ?>>Remote</option>
                                        <option value="hybrid" <?php echo ($_POST['work_arrangement'] ?? '') == 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Position Information -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-briefcase text-green-500 mr-2"></i>
                                Position Information
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Job Posting (Optional)</label>
                                    <select name="job_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select a job posting...</option>
                                        <?php foreach ($jobs as $job): ?>
                                        <option value="<?php echo $job['id']; ?>" <?php echo ($_POST['job_id'] ?? '') == $job['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($job['title'] . ' - ' . $job['department']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
                                    <input type="text" name="department" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>" 
                                           placeholder="Engineering, Sales, Marketing..." required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Position Title *</label>
                                    <input type="text" name="position_title" value="<?php echo htmlspecialchars($_POST['position_title'] ?? ''); ?>" 
                                           placeholder="Software Engineer, Sales Manager..." required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type</label>
                                    <select name="employment_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="full_time" <?php echo ($_POST['employment_type'] ?? '') == 'full_time' ? 'selected' : ''; ?>>Full-Time</option>
                                        <option value="part_time" <?php echo ($_POST['employment_type'] ?? '') == 'part_time' ? 'selected' : ''; ?>>Part-Time</option>
                                        <option value="contract" <?php echo ($_POST['employment_type'] ?? '') == 'contract' ? 'selected' : ''; ?>>Contract</option>
                                        <option value="intern" <?php echo ($_POST['employment_type'] ?? '') == 'intern' ? 'selected' : ''; ?>>Intern</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date *</label>
                                    <input type="date" name="start_date" value="<?php echo $_POST['start_date'] ?? ''; ?>" 
                                           min="<?php echo date('Y-m-d'); ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Annual Salary</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" name="salary" value="<?php echo $_POST['salary'] ?? ''; ?>" 
                                               step="1000" min="0" placeholder="75000"
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Office Location</label>
                                    <input type="text" name="office_location" value="<?php echo htmlspecialchars($_POST['office_location'] ?? ''); ?>" 
                                           placeholder="New York Office, Building A, Floor 3..."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Management & Support -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-users text-purple-500 mr-2"></i>
                                Management & Support
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Direct Manager</label>
                                    <select name="manager_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select a manager...</option>
                                        <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo $manager['id']; ?>" <?php echo ($_POST['manager_id'] ?? '') == $manager['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name'] . ' (' . ucfirst($manager['role']) . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Onboarding Buddy</label>
                                    <select name="buddy_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select a buddy...</option>
                                        <?php foreach ($buddies as $buddy): ?>
                                        <option value="<?php echo $buddy['id']; ?>" <?php echo ($_POST['buddy_id'] ?? '') == $buddy['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($buddy['first_name'] . ' ' . $buddy['last_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-phone text-red-500 mr-2"></i>
                                Emergency Contact
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Name</label>
                                    <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>" 
                                           placeholder="Jane Doe"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                                    <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>" 
                                           placeholder="(555) 987-6543"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-sticky-note text-yellow-500 mr-2"></i>
                                Additional Notes
                            </h2>
                            
                            <textarea name="notes" rows="4" 
                                      placeholder="Any additional information or special requirements..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Onboarding Setup -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Onboarding Setup</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Onboarding Template</label>
                                    <select name="template_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Select template...</option>
                                        <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>" 
                                                data-duration="<?php echo $template['duration_days']; ?>"
                                                <?php echo ($_POST['template_id'] ?? '') == $template['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($template['name'] . ' (' . $template['duration_days'] . ' days)'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Choose a template to automatically create onboarding tasks</p>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" name="auto_start_onboarding" id="auto_start_onboarding" 
                                           <?php echo isset($_POST['auto_start_onboarding']) ? 'checked' : ''; ?>
                                           class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="auto_start_onboarding" class="text-sm text-gray-700">
                                        Automatically start onboarding process
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                            
                            <div class="space-y-3">
                                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-plus mr-2"></i>Create Employee
                                </button>
                                
                                <button type="button" onclick="resetForm()" class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-undo mr-2"></i>Reset Form
                                </button>
                                
                                <a href="list.php" class="block w-full text-center bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                            </div>
                        </div>

                        <!-- Help -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-blue-800 mb-2">
                                <i class="fas fa-info-circle mr-1"></i>Employee Creation Tips
                            </h4>
                            <ul class="text-xs text-blue-700 space-y-1">
                                <li>• Employee ID should be unique (e.g., EMP001)</li>
                                <li>• Select an onboarding template to auto-create tasks</li>
                                <li>• Assign a manager and buddy for better support</li>
                                <li>• Start date can be in the future for planning</li>
                                <li>• Auto-start onboarding for immediate processing</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.querySelector('form').reset();
            }
        }

        // Auto-populate department when job is selected
        document.querySelector('select[name="job_id"]').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const jobText = selectedOption.text;
                const department = jobText.split(' - ')[1];
                if (department && !document.querySelector('input[name="department"]').value) {
                    document.querySelector('input[name="department"]').value = department;
                }
            }
        });

        // Generate employee ID suggestion
        document.addEventListener('DOMContentLoaded', function() {
            const deptField = document.querySelector('input[name="department"]');
            const empIdField = document.querySelector('input[name="employee_id"]');
            
            deptField.addEventListener('blur', function() {
                if (!empIdField.value && this.value) {
                    const deptCode = this.value.substring(0, 3).toUpperCase();
                    const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
                    empIdField.placeholder = deptCode + randomNum;
                }
            });
        });
    </script>
</body>
</html> 