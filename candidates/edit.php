<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$candidate_id = $_GET['id'] ?? null;

if (!$candidate_id) {
    header('Location: list.php');
    exit();
}

$error = '';
$success = '';

$db = new Database();
$conn = $db->getConnection();

// Get candidate details
$stmt = $conn->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    header('Location: list.php');
    exit();
}

// Get job postings for dropdown
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
    $status = $_POST['status'];
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Please fill in all required fields (Name and Email).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!empty($experience_years) && (!is_numeric($experience_years) || $experience_years < 0)) {
        $error = 'Please enter a valid number of years of experience.';
    } else {
        try {
            // Check if email already exists (excluding current candidate)
            $email_check = $conn->prepare("SELECT id FROM candidates WHERE email = ? AND id != ?");
            $email_check->execute([$email, $candidate_id]);
            
            if ($email_check->fetch()) {
                $error = 'A candidate with this email address already exists.';
            } else {
                // Handle resume upload
                $resume_path = $candidate['resume_path']; // Keep existing resume by default
                
                if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
                    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $file_type = $_FILES['resume']['type'];
                    
                    if (in_array($file_type, $allowed_types)) {
                        $upload_dir = '../uploads/resumes/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Delete old resume if exists
                        if ($candidate['resume_path'] && file_exists('../' . $candidate['resume_path'])) {
                            unlink('../' . $candidate['resume_path']);
                        }
                        
                        $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                        $filename = 'resume_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $full_path = $upload_dir . $filename;
                        
                        if (!move_uploaded_file($_FILES['resume']['tmp_name'], $full_path)) {
                            $error = 'Failed to upload resume file.';
                        } else {
                            $resume_path = 'uploads/resumes/' . $filename; // Store relative path
                        }
                    } else {
                        $error = 'Please upload a valid resume file (PDF, DOC, or DOCX).';
                    }
                }
                
                if (!$error) {
                    // Update candidate
                    $stmt = $conn->prepare("
                        UPDATE candidates SET 
                            first_name = ?, last_name = ?, email = ?, phone = ?, linkedin_url = ?, 
                            resume_path = ?, skills = ?, experience_years = ?, current_location = ?, 
                            source = ?, applied_for = ?, assigned_to = ?, notes = ?, status = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $first_name, $last_name, $email, $phone, $linkedin_url, $resume_path,
                        $skills, $experience_years, $current_location, $source, $applied_for,
                        $assigned_to, $notes, $status, $candidate_id
                    ]);
                    
                    // Log activity
                    logActivity(
                        $_SESSION['user_id'], 
                        'updated', 
                        'candidate', 
                        $candidate_id,
                        "Updated candidate information for $first_name $last_name"
                    );
                    
                    $success = 'Candidate updated successfully!';
                    
                    // Refresh candidate data
                    $stmt = $conn->prepare("SELECT * FROM candidates WHERE id = ?");
                    $stmt->execute([$candidate_id]);
                    $candidate = $stmt->fetch();
                }
            }
        } catch (Exception $e) {
            $error = 'Error updating candidate: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Candidate - <?php echo APP_NAME; ?></title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Edit Candidate</h1>
                        <p class="text-gray-600">Update candidate information</p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="view.php?id=<?php echo $candidate['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-eye mr-2"></i>View Profile
                        </a>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
                    </div>
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
                </div>
            <?php endif; ?>

            <!-- Edit Candidate Form -->
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
                                       value="<?php echo htmlspecialchars($candidate['first_name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                <input type="text" name="last_name" required
                                       value="<?php echo htmlspecialchars($candidate['last_name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <input type="email" name="email" required
                                       value="<?php echo htmlspecialchars($candidate['email']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="tel" name="phone"
                                       value="<?php echo htmlspecialchars($candidate['phone'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn Profile</label>
                                <input type="url" name="linkedin_url"
                                       value="<?php echo htmlspecialchars($candidate['linkedin_url'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Location</label>
                                <input type="text" name="current_location"
                                       value="<?php echo htmlspecialchars($candidate['current_location'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                                       value="<?php echo htmlspecialchars($candidate['experience_years'] ?? ''); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Applied Position</label>
                                <select name="applied_for" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select a position...</option>
                                    <?php foreach ($jobs as $job): ?>
                                    <option value="<?php echo $job['id']; ?>" <?php echo $candidate['applied_for'] == $job['id'] ? 'selected' : ''; ?>>
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
                                      placeholder="List key skills, technologies, and expertise areas..."><?php echo htmlspecialchars($candidate['skills'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Resume Upload</label>
                            <?php if ($candidate['resume_path']): ?>
                                <div class="mb-2 p-3 bg-gray-50 rounded-lg">
                                    <i class="fas fa-file-pdf text-red-600 mr-2"></i>
                                    <span class="text-sm text-gray-700">Current resume uploaded</span>
                                    <a href="../<?php echo htmlspecialchars($candidate['resume_path']); ?>" target="_blank" class="ml-2 text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-external-link-alt"></i> View
                                    </a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="resume" accept=".pdf,.doc,.docx"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="mt-1 text-sm text-gray-500">Upload new resume to replace existing (PDF, DOC, or DOCX files only)</p>
                        </div>
                    </div>

                    <!-- Application Details -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-clipboard mr-2"></i>Application Details
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="new" <?php echo $candidate['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="shortlisted" <?php echo $candidate['status'] == 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                    <option value="interviewing" <?php echo $candidate['status'] == 'interviewing' ? 'selected' : ''; ?>>Interviewing</option>
                                    <option value="offered" <?php echo $candidate['status'] == 'offered' ? 'selected' : ''; ?>>Offered</option>
                                    <option value="hired" <?php echo $candidate['status'] == 'hired' ? 'selected' : ''; ?>>Hired</option>
                                    <option value="rejected" <?php echo $candidate['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                                <select name="source" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select source...</option>
                                    <option value="linkedin" <?php echo $candidate['source'] == 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                                    <option value="indeed" <?php echo $candidate['source'] == 'indeed' ? 'selected' : ''; ?>>Indeed</option>
                                    <option value="company_website" <?php echo $candidate['source'] == 'company_website' ? 'selected' : ''; ?>>Company Website</option>
                                    <option value="referral" <?php echo $candidate['source'] == 'referral' ? 'selected' : ''; ?>>Employee Referral</option>
                                    <option value="job_fair" <?php echo $candidate['source'] == 'job_fair' ? 'selected' : ''; ?>>Job Fair</option>
                                    <option value="recruiter" <?php echo $candidate['source'] == 'recruiter' ? 'selected' : ''; ?>>Recruiter</option>
                                    <option value="other" <?php echo $candidate['source'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Assigned Recruiter</label>
                                <select name="assigned_to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Auto-assign...</option>
                                    <?php foreach ($recruiters as $recruiter): ?>
                                    <option value="<?php echo $recruiter['id']; ?>" <?php echo $candidate['assigned_to'] == $recruiter['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($recruiter['first_name'] . ' ' . $recruiter['last_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Add any notes or observations about this candidate..."><?php echo htmlspecialchars($candidate['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="flex space-x-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Update Candidate
                            </button>
                            <a href="view.php?id=<?php echo $candidate['id']; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html> 