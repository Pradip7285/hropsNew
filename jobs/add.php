<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Get job templates for quick creation
$templates_stmt = $conn->query("
    SELECT * FROM job_templates 
    WHERE is_active = 1 
    ORDER BY name
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $department = trim($_POST['department']);
    $location = trim($_POST['location']);
    $employment_type = $_POST['employment_type'];
    $description = trim($_POST['description']);
    $requirements = trim($_POST['requirements']);
    $responsibilities = trim($_POST['responsibilities']);
    $salary_range = trim($_POST['salary_range']);
    $experience_level = $_POST['experience_level'];
    $education_level = $_POST['education_level'];
    $skills_required = trim($_POST['skills_required']);
    $benefits = trim($_POST['benefits']);
    $application_deadline = $_POST['application_deadline'];
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $notes = trim($_POST['notes']);
    
    // Validation
    if (empty($title) || empty($department) || empty($location) || empty($description)) {
        $error = 'Please fill in all required fields (Title, Department, Location, Description).';
    } elseif (!empty($application_deadline) && strtotime($application_deadline) <= time()) {
        $error = 'Application deadline must be in the future.';
    } else {
        try {
            // Insert job posting
            $stmt = $conn->prepare("
                INSERT INTO job_postings (
                    title, department, location, employment_type, description, requirements,
                    responsibilities, salary_range, experience_level, education_level,
                    skills_required, benefits, application_deadline, status, priority,
                    notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title, $department, $location, $employment_type, $description, $requirements,
                $responsibilities, $salary_range, $experience_level, $education_level,
                $skills_required, $benefits, $application_deadline, $status, $priority,
                $notes, $_SESSION['user_id']
            ]);
            
            $job_id = $conn->lastInsertId();
            
            // Log activity
            logActivity(
                $_SESSION['user_id'], 
                'created', 
                'job_posting', 
                $job_id,
                "Created new job posting: $title"
            );
            
            $success = 'Job posting created successfully!';
            
            // Clear form data
            $_POST = [];
        } catch (Exception $e) {
            $error = 'Error creating job posting: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post New Job - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Post New Job</h1>
                        <p class="text-gray-600">Create a new job posting to attract top talent</p>
                    </div>
                    <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Jobs
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                    <div class="mt-2">
                        <a href="list.php" class="text-green-800 underline">View all jobs</a> or 
                        <a href="add.php" class="text-green-800 underline">post another job</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Job Templates (if available) -->
            <?php if (!empty($templates)): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="text-lg font-medium text-blue-900 mb-2">
                    <i class="fas fa-magic mr-2"></i>Quick Start with Templates
                </h3>
                <p class="text-blue-700 mb-3">Choose a template to pre-fill job details:</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($templates as $template): ?>
                    <button onclick="loadTemplate(<?php echo $template['id']; ?>)" 
                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                        <?php echo htmlspecialchars($template['name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Job Posting Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" class="space-y-6">
                    <!-- Basic Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-briefcase mr-2"></i>Basic Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Job Title *</label>
                                <input type="text" name="title" required
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="e.g., Senior Software Engineer">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Department *</label>
                                <input type="text" name="department" required
                                       value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="e.g., Engineering, Marketing">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Location *</label>
                                <input type="text" name="location" required
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="e.g., New York, NY / Remote">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type</label>
                                <select name="employment_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="full_time" <?php echo ($_POST['employment_type'] ?? '') == 'full_time' ? 'selected' : ''; ?>>Full Time</option>
                                    <option value="part_time" <?php echo ($_POST['employment_type'] ?? '') == 'part_time' ? 'selected' : ''; ?>>Part Time</option>
                                    <option value="contract" <?php echo ($_POST['employment_type'] ?? '') == 'contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="internship" <?php echo ($_POST['employment_type'] ?? '') == 'internship' ? 'selected' : ''; ?>>Internship</option>
                                    <option value="remote" <?php echo ($_POST['employment_type'] ?? '') == 'remote' ? 'selected' : ''; ?>>Remote</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Salary Range</label>
                                <input type="text" name="salary_range"
                                       value="<?php echo htmlspecialchars($_POST['salary_range'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="e.g., $80,000 - $120,000">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Deadline</label>
                                <input type="date" name="application_deadline"
                                       value="<?php echo htmlspecialchars($_POST['application_deadline'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Job Details -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-file-alt mr-2"></i>Job Details
                        </h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Job Description *</label>
                            <textarea name="description" rows="6" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Provide a detailed description of the role, what the successful candidate will be doing, and what makes this position unique..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Key Responsibilities</label>
                            <textarea name="responsibilities" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="• Lead development of new features&#10;• Collaborate with cross-functional teams&#10;• Mentor junior developers"><?php echo htmlspecialchars($_POST['responsibilities'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Requirements & Qualifications</label>
                            <textarea name="requirements" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="• Bachelor's degree in Computer Science or related field&#10;• 5+ years of software development experience&#10;• Experience with modern frameworks"><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Skills Required</label>
                            <textarea name="skills_required" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="List key technical skills, separated by commas (e.g., PHP, JavaScript, React, SQL)"><?php echo htmlspecialchars($_POST['skills_required'] ?? ''); ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Benefits & Perks</label>
                            <textarea name="benefits" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="• Health, dental, and vision insurance&#10;• 401(k) with company matching&#10;• Flexible PTO policy"><?php echo htmlspecialchars($_POST['benefits'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Additional Settings -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-cog mr-2"></i>Additional Settings
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Experience Level</label>
                                <select name="experience_level" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Not specified</option>
                                    <option value="entry" <?php echo ($_POST['experience_level'] ?? '') == 'entry' ? 'selected' : ''; ?>>Entry Level (0-2 years)</option>
                                    <option value="mid" <?php echo ($_POST['experience_level'] ?? '') == 'mid' ? 'selected' : ''; ?>>Mid Level (3-5 years)</option>
                                    <option value="senior" <?php echo ($_POST['experience_level'] ?? '') == 'senior' ? 'selected' : ''; ?>>Senior Level (6+ years)</option>
                                    <option value="executive" <?php echo ($_POST['experience_level'] ?? '') == 'executive' ? 'selected' : ''; ?>>Executive Level</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Education Level</label>
                                <select name="education_level" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Not specified</option>
                                    <option value="high_school" <?php echo ($_POST['education_level'] ?? '') == 'high_school' ? 'selected' : ''; ?>>High School</option>
                                    <option value="associates" <?php echo ($_POST['education_level'] ?? '') == 'associates' ? 'selected' : ''; ?>>Associate's Degree</option>
                                    <option value="bachelors" <?php echo ($_POST['education_level'] ?? '') == 'bachelors' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                    <option value="masters" <?php echo ($_POST['education_level'] ?? '') == 'masters' ? 'selected' : ''; ?>>Master's Degree</option>
                                    <option value="doctorate" <?php echo ($_POST['education_level'] ?? '') == 'doctorate' ? 'selected' : ''; ?>>Doctorate</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="normal" <?php echo ($_POST['priority'] ?? 'normal') == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="high" <?php echo ($_POST['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High Priority</option>
                                    <option value="urgent" <?php echo ($_POST['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Publishing Status</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="status" value="draft" <?php echo ($_POST['status'] ?? 'draft') == 'draft' ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700">Save as Draft (not visible to candidates)</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="status" value="active" <?php echo ($_POST['status'] ?? '') == 'active' ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700">Publish Immediately (live and accepting applications)</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Internal Notes</label>
                            <textarea name="notes" rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Internal notes for HR team (not visible to candidates)"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Create Job Posting
                            </button>
                            <button type="button" onclick="previewJob()" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-eye mr-2"></i>Preview
                            </button>
                            <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function loadTemplate(templateId) {
            if (confirm('This will replace the current form content. Continue?')) {
                // In a real implementation, this would load template data via AJAX
                alert('Template loading functionality would be implemented here.');
            }
        }

        function previewJob() {
            const title = document.querySelector('[name="title"]').value;
            const description = document.querySelector('[name="description"]').value;
            
            if (!title || !description) {
                alert('Please fill in at least the job title and description to preview.');
                return;
            }
            
            // Create preview window
            const previewWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes');
            const content = `
                <html>
                <head>
                    <title>Job Preview: ${title}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                        h1 { color: #2563eb; }
                        .section { margin: 20px 0; }
                        .label { font-weight: bold; color: #374151; }
                    </style>
                </head>
                <body>
                    <h1>${title}</h1>
                    <div class="section">
                        <div class="label">Department:</div>
                        <div>${document.querySelector('[name="department"]').value}</div>
                    </div>
                    <div class="section">
                        <div class="label">Location:</div>
                        <div>${document.querySelector('[name="location"]').value}</div>
                    </div>
                    <div class="section">
                        <div class="label">Description:</div>
                        <div>${description.replace(/\n/g, '<br>')}</div>
                    </div>
                    <div style="margin-top: 30px; padding: 10px; background: #f3f4f6; border-radius: 5px;">
                        <strong>Note:</strong> This is a preview. The actual job posting may include additional formatting and styling.
                    </div>
                </body>
                </html>
            `;
            previewWindow.document.write(content);
        }
    </script>
</body>
</html> 