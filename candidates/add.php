<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

// Get job postings for dropdown
$db = new Database();
$conn = $db->getConnection();

$jobs_stmt = $conn->query("SELECT id, title, department FROM job_postings WHERE status = 'active' ORDER BY title");
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get HR recruiters for assignment
$recruiters_stmt = $conn->query("
    SELECT id, first_name, last_name 
    FROM users 
    WHERE role IN ('hr_recruiter', 'admin') AND is_active = 1
    ORDER BY first_name, last_name
");
$recruiters = $recruiters_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $linkedin_url = trim($_POST['linkedin_url']);
    $skills = trim($_POST['skills']);
    $experience_years = $_POST['experience_years'];
    $current_location = trim($_POST['current_location']);
    $source = $_POST['source'];
    $applied_for = $_POST['applied_for'];
    $assigned_to = $_POST['assigned_to'];
    $notes = trim($_POST['notes']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields (Name and Email).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!empty($experience_years) && (!is_numeric($experience_years) || $experience_years < 0)) {
        $error = 'Please enter a valid number of years of experience.';
    } else {
        try {
            // Check if email already exists
            $email_check = $conn->prepare("SELECT id FROM candidates WHERE email = ?");
            $email_check->execute([$email]);
            
            if ($email_check->fetch()) {
                $error = 'A candidate with this email address already exists.';
            } else {
                // Handle resume upload
                $resume_path = null;
                if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
                    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $file_type = $_FILES['resume']['type'];
                    
                    if (in_array($file_type, $allowed_types)) {
                        $upload_dir = '../uploads/resumes/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                        $filename = 'resume_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $resume_path = $upload_dir . $filename;
                        
                        if (!move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path)) {
                            $error = 'Failed to upload resume file.';
                        } else {
                            $resume_path = 'uploads/resumes/' . $filename; // Store relative path
                        }
                    } else {
                        $error = 'Please upload a valid resume file (PDF, DOC, or DOCX).';
                    }
                }
                
                if (!$error) {
                    // Calculate AI score (simplified scoring)
                    $ai_score = calculateAIScore($skills, $experience_years, $applied_for);
                    
                    // Insert candidate
                    $stmt = $conn->prepare("
                        INSERT INTO candidates (
                            first_name, last_name, email, phone, linkedin_url, resume_path,
                            skills, experience_years, current_location, source, applied_for,
                            assigned_to, notes, ai_score, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
                    ");
                    
                    $stmt->execute([
                        $first_name, $last_name, $email, $phone, $linkedin_url, $resume_path,
                        $skills, $experience_years, $current_location, $source, $applied_for,
                        $assigned_to, $notes, $ai_score
                    ]);
                    
                    $candidate_id = $conn->lastInsertId();
                    
                    // Log activity
                    logActivity(
                        $_SESSION['user_id'], 
                        'created', 
                        'candidate', 
                        $candidate_id,
                        "Added new candidate: $first_name $last_name"
                    );
                    
                    $success = 'Candidate added successfully!';
                    
                    // Clear form data
                    $_POST = [];
                }
            }
        } catch (Exception $e) {
            $error = 'Error adding candidate: ' . $e->getMessage();
        }
    }
}

// Simple AI scoring function
function calculateAIScore($skills, $experience, $job_id) {
    $score = 0.5; // Base score
    
    // Experience factor
    if ($experience > 5) $score += 0.3;
    elseif ($experience > 2) $score += 0.2;
    elseif ($experience > 0) $score += 0.1;
    
    // Skills factor (check for relevant keywords)
    $relevant_keywords = ['php', 'javascript', 'python', 'react', 'node', 'sql', 'java', 'angular', 'vue'];
    $skills_lower = strtolower($skills);
    $matches = 0;
    
    foreach ($relevant_keywords as $keyword) {
        if (strpos($skills_lower, $keyword) !== false) {
            $matches++;
        }
    }
    
    $score += min($matches * 0.05, 0.2); // Max 0.2 points for skills
    
    return min($score, 1.0); // Cap at 1.0
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Candidate - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Add New Candidate</h1>
                        <p class="text-gray-600">Add a new candidate to your hiring pipeline</p>
                    </div>
                    <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Candidates
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
                        <a href="list.php" class="text-green-800 underline">View all candidates</a> or 
                        <a href="add.php" class="text-green-800 underline">add another candidate</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Add Candidate Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Personal Information -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-user mr-2"></i>Personal Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                <input type="text" name="first_name" required
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter first name">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                <input type="text" name="last_name" required
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter last name">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <input type="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="candidate@example.com">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="+1 (555) 123-4567">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn Profile</label>
                                <input type="url" name="linkedin_url"
                                       value="<?php echo htmlspecialchars($_POST['linkedin_url'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="https://linkedin.com/in/candidate">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Location</label>
                                <input type="text" name="current_location"
                                       value="<?php echo htmlspecialchars($_POST['current_location'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="City, State/Country">
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-briefcase mr-2"></i>Professional Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Years of Experience</label>
                                <input type="number" name="experience_years" min="0" max="50"
                                       value="<?php echo htmlspecialchars($_POST['experience_years'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="0">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Applied Position</label>
                                <select name="applied_for" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select a position...</option>
                                    <?php foreach ($jobs as $job): ?>
                                    <option value="<?php echo $job['id']; ?>" <?php echo ($_POST['applied_for'] ?? '') == $job['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($job['title'] . ' - ' . $job['department']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Skills & Technologies</label>
                            <textarea name="skills" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="List key skills, technologies, and expertise areas..."><?php echo htmlspecialchars($_POST['skills'] ?? ''); ?></textarea>
                            <p class="mt-1 text-sm text-gray-500">Separate skills with commas (e.g., PHP, JavaScript, React, Node.js)</p>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Resume Upload</label>
                            <input type="file" name="resume" accept=".pdf,.doc,.docx"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="mt-1 text-sm text-gray-500">Upload PDF, DOC, or DOCX files only (Max 5MB)</p>
                        </div>
                    </div>

                    <!-- Application Details -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-clipboard mr-2"></i>Application Details
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                                <select name="source" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select source...</option>
                                    <option value="linkedin" <?php echo ($_POST['source'] ?? '') == 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                                    <option value="indeed" <?php echo ($_POST['source'] ?? '') == 'indeed' ? 'selected' : ''; ?>>Indeed</option>
                                    <option value="company_website" <?php echo ($_POST['source'] ?? '') == 'company_website' ? 'selected' : ''; ?>>Company Website</option>
                                    <option value="referral" <?php echo ($_POST['source'] ?? '') == 'referral' ? 'selected' : ''; ?>>Employee Referral</option>
                                    <option value="job_fair" <?php echo ($_POST['source'] ?? '') == 'job_fair' ? 'selected' : ''; ?>>Job Fair</option>
                                    <option value="recruiter" <?php echo ($_POST['source'] ?? '') == 'recruiter' ? 'selected' : ''; ?>>Recruiter</option>
                                    <option value="other" <?php echo ($_POST['source'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Recruiter</label>
                                <select name="assigned_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Auto-assign...</option>
                                    <?php foreach ($recruiters as $recruiter): ?>
                                    <option value="<?php echo $recruiter['id']; ?>" <?php echo ($_POST['assigned_to'] ?? '') == $recruiter['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($recruiter['first_name'] . ' ' . $recruiter['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Initial Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Add any initial notes or observations about this candidate..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Add Candidate
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
        // Auto-assign recruiter if only one available
        document.addEventListener('DOMContentLoaded', function() {
            const recruiterSelect = document.querySelector('[name="assigned_to"]');
            if (recruiterSelect.options.length === 2) { // Auto-assign + 1 recruiter
                recruiterSelect.selectedIndex = 1;
            }
        });

        // File upload validation
        document.querySelector('[name="resume"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                }
            }
        });
    </script>
</body>
</html> 