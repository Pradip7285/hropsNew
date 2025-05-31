<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$candidate_id = $_GET['candidate_id'] ?? null;

$db = new Database();
$conn = $db->getConnection();

// Get qualified candidates for offer
$candidates_stmt = $conn->query("
    SELECT c.*, j.title as job_title, j.id as job_id, j.department
    FROM candidates c 
    LEFT JOIN job_postings j ON c.applied_for = j.id
    WHERE c.status IN ('interviewing', 'shortlisted') 
    AND c.id NOT IN (SELECT candidate_id FROM offers WHERE status IN ('sent', 'accepted'))
    ORDER BY c.first_name, c.last_name
");
$candidates = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get offer templates
$templates_stmt = $conn->query("
    SELECT * FROM offer_templates 
    WHERE is_active = 1 
    ORDER BY name
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected candidate details if pre-selected
$selected_candidate = null;
if ($candidate_id) {
    $selected_stmt = $conn->prepare("
        SELECT c.*, j.title as job_title, j.id as job_id, j.department, j.salary_range
        FROM candidates c 
        LEFT JOIN job_postings j ON c.applied_for = j.id
        WHERE c.id = ?
    ");
    $selected_stmt->execute([$candidate_id]);
    $selected_candidate = $selected_stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $candidate_id = $_POST['candidate_id'];
    $job_id = $_POST['job_id'];
    $salary_offered = $_POST['salary_offered'];
    $benefits = $_POST['benefits'];
    $start_date = $_POST['start_date'];
    $valid_until = $_POST['valid_until'];
    $offer_template = $_POST['offer_template'] ?? '';
    $custom_terms = $_POST['custom_terms'] ?? '';
    $save_as_draft = isset($_POST['save_as_draft']);
    
    if (empty($candidate_id) || empty($job_id) || empty($salary_offered)) {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($salary_offered) || $salary_offered <= 0) {
        $error = 'Please enter a valid salary amount.';
    } elseif (!empty($start_date) && strtotime($start_date) <= time()) {
        $error = 'Start date must be in the future.';
    } elseif (!empty($valid_until) && strtotime($valid_until) <= time()) {
        $error = 'Offer validity date must be in the future.';
    } else {
        try {
            // Check if candidate already has an active offer
            $existing_offer = $conn->prepare("
                SELECT id FROM offers 
                WHERE candidate_id = ? AND status IN ('sent', 'draft') 
                LIMIT 1
            ");
            $existing_offer->execute([$candidate_id]);
            
            if ($existing_offer->fetch()) {
                $error = 'This candidate already has an active offer.';
            } else {
                // Generate offer letter
                $offer_letter_content = generateOfferLetter($candidate_id, $job_id, $salary_offered, $benefits, $start_date, $offer_template, $custom_terms);
                $offer_letter_filename = 'offer_' . $candidate_id . '_' . time() . '.pdf';
                $offer_letter_path = UPLOAD_PATH . 'offers/' . $offer_letter_filename;
                
                // Create offers directory if it doesn't exist
                if (!file_exists(UPLOAD_PATH . 'offers/')) {
                    mkdir(UPLOAD_PATH . 'offers/', 0755, true);
                }
                
                // Save offer letter (in production, generate actual PDF)
                file_put_contents($offer_letter_path, $offer_letter_content);
                
                // Insert offer
                $stmt = $conn->prepare("
                    INSERT INTO offers (
                        candidate_id, job_id, salary_offered, benefits, start_date, 
                        offer_letter_path, status, valid_until, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $status = $save_as_draft ? 'draft' : 'draft'; // All offers start as draft for approval
                
                $stmt->execute([
                    $candidate_id, $job_id, $salary_offered, $benefits, $start_date,
                    $offer_letter_path, $status, $valid_until, $_SESSION['user_id']
                ]);
                
                $offer_id = $conn->lastInsertId();
                
                // Update candidate status
                $update_candidate = $conn->prepare("UPDATE candidates SET status = 'offered' WHERE id = ?");
                $update_candidate->execute([$candidate_id]);
                
                // Log activity
                $candidate_info = $conn->prepare("SELECT first_name, last_name FROM candidates WHERE id = ?");
                $candidate_info->execute([$candidate_id]);
                $candidate = $candidate_info->fetch();
                
                logActivity(
                    $_SESSION['user_id'], 
                    'created', 
                    'offer', 
                    $offer_id,
                    "Created offer for {$candidate['first_name']} {$candidate['last_name']}"
                );
                
                $success = 'Offer created successfully and is pending approval.';
                
                // Clear form data
                $_POST = [];
            }
        } catch (Exception $e) {
            $error = 'Error creating offer: ' . $e->getMessage();
        }
    }
}

function generateOfferLetter($candidate_id, $job_id, $salary, $benefits, $start_date, $template, $custom_terms) {
    // In production, this would generate a proper PDF using libraries like TCPDF or mPDF
    // For now, return HTML content
    global $conn;
    
    $candidate_stmt = $conn->prepare("SELECT * FROM candidates WHERE id = ?");
    $candidate_stmt->execute([$candidate_id]);
    $candidate = $candidate_stmt->fetch();
    
    $job_stmt = $conn->prepare("SELECT * FROM job_postings WHERE id = ?");
    $job_stmt->execute([$job_id]);
    $job = $job_stmt->fetch();
    
    $content = "
    <h1>Job Offer Letter</h1>
    <p>Dear {$candidate['first_name']} {$candidate['last_name']},</p>
    
    <p>We are pleased to offer you the position of <strong>{$job['title']}</strong> at our company.</p>
    
    <h3>Offer Details:</h3>
    <ul>
        <li>Position: {$job['title']}</li>
        <li>Department: {$job['department']}</li>
        <li>Annual Salary: $" . number_format($salary, 2) . "</li>
        <li>Start Date: " . ($start_date ? date('F j, Y', strtotime($start_date)) : 'To be determined') . "</li>
    </ul>
    
    " . ($benefits ? "<h3>Benefits:</h3><p>{$benefits}</p>" : "") . "
    
    " . ($custom_terms ? "<h3>Additional Terms:</h3><p>{$custom_terms}</p>" : "") . "
    
    <p>This offer is contingent upon successful completion of background checks and any other conditions outlined in our employment policies.</p>
    
    <p>We look forward to having you join our team!</p>
    
    <p>Sincerely,<br>HR Department</p>
    ";
    
    return $content;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Offer - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Create Job Offer</h1>
                <p class="text-gray-600">Generate a professional offer letter for qualified candidates</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                <div class="mt-2">
                    <a href="list.php" class="text-green-800 hover:text-green-900 underline">View all offers</a> |
                    <a href="create.php" class="text-green-800 hover:text-green-900 underline">Create another offer</a>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" class="space-y-6" id="offerForm">
                    <!-- Candidate Selection -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-2"></i>Select Candidate *
                            </label>
                            <select name="candidate_id" required onchange="loadCandidateInfo(this.value)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Choose a candidate...</option>
                                <?php foreach ($candidates as $candidate): ?>
                                <option value="<?php echo $candidate['id']; ?>" 
                                        data-job-id="<?php echo $candidate['job_id']; ?>"
                                        data-job-title="<?php echo htmlspecialchars($candidate['job_title']); ?>"
                                        data-department="<?php echo htmlspecialchars($candidate['department']); ?>"
                                        <?php echo ($candidate_id == $candidate['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                    <?php if ($candidate['job_title']): ?>
                                        - <?php echo htmlspecialchars($candidate['job_title']); ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="candidateInfo" class="mt-2 text-sm text-gray-600"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-briefcase mr-2"></i>Position *
                            </label>
                            <input type="hidden" name="job_id" id="jobId" value="<?php echo $selected_candidate ? $selected_candidate['job_id'] : ''; ?>">
                            <input type="text" id="jobTitle" readonly
                                   value="<?php echo $selected_candidate ? htmlspecialchars($selected_candidate['job_title']) : ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                            <div id="departmentInfo" class="mt-1 text-sm text-gray-500">
                                <?php echo $selected_candidate ? htmlspecialchars($selected_candidate['department']) : ''; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Offer Details -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Offer Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-dollar-sign mr-2"></i>Annual Salary *
                                </label>
                                <input type="number" name="salary_offered" required min="1" step="1000"
                                       value="<?php echo $_POST['salary_offered'] ?? ''; ?>"
                                       placeholder="e.g., 75000"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Enter the annual salary amount</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar mr-2"></i>Start Date
                                </label>
                                <input type="date" name="start_date"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo $_POST['start_date'] ?? ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-clock mr-2"></i>Offer Valid Until
                                </label>
                                <input type="date" name="valid_until"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                       value="<?php echo $_POST['valid_until'] ?? date('Y-m-d', strtotime('+2 weeks')); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Default: 2 weeks from today</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-file-alt mr-2"></i>Offer Template
                                </label>
                                <select name="offer_template" onchange="loadTemplate(this.value)"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Standard Template</option>
                                    <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo $template['id']; ?>">
                                        <?php echo htmlspecialchars($template['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Benefits Section -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-heart mr-2"></i>Benefits & Perks
                        </label>
                        <textarea name="benefits" rows="4"
                                  placeholder="Health insurance, dental, vision, 401k matching, vacation days, etc..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo $_POST['benefits'] ?? ''; ?></textarea>
                        
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" onclick="addBenefit('Health Insurance')" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm hover:bg-blue-200">
                                + Health Insurance
                            </button>
                            <button type="button" onclick="addBenefit('401(k) Matching')" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm hover:bg-blue-200">
                                + 401(k) Matching
                            </button>
                            <button type="button" onclick="addBenefit('Flexible PTO')" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm hover:bg-blue-200">
                                + Flexible PTO
                            </button>
                            <button type="button" onclick="addBenefit('Remote Work Options')" class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm hover:bg-blue-200">
                                + Remote Work
                            </button>
                        </div>
                    </div>

                    <!-- Custom Terms -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-file-contract mr-2"></i>Additional Terms & Conditions
                        </label>
                        <textarea name="custom_terms" rows="4"
                                  placeholder="Any additional terms, conditions, or special arrangements..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo $_POST['custom_terms'] ?? ''; ?></textarea>
                    </div>

                    <!-- Preview Section -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Offer Preview</h3>
                        <div id="offerPreview" class="bg-gray-50 rounded-lg p-4 min-h-32">
                            <p class="text-gray-500 italic">Select a candidate and enter offer details to see preview...</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Offers
                        </a>
                        
                        <div class="space-x-4">
                            <button type="submit" name="save_as_draft" value="1" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Save as Draft
                            </button>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                <i class="fas fa-file-contract mr-2"></i>Create Offer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        function loadCandidateInfo(candidateId) {
            const select = document.querySelector('[name="candidate_id"]');
            const selectedOption = select.options[select.selectedIndex];
            
            if (candidateId && selectedOption.dataset.jobId) {
                // Update job information
                document.getElementById('jobId').value = selectedOption.dataset.jobId;
                document.getElementById('jobTitle').value = selectedOption.dataset.jobTitle;
                document.getElementById('departmentInfo').textContent = selectedOption.dataset.department;
                
                // Show candidate info
                document.getElementById('candidateInfo').innerHTML = 
                    '<i class="fas fa-info-circle mr-1"></i>Selected candidate information loaded';
                    
                updateOfferPreview();
            } else {
                // Clear job information
                document.getElementById('jobId').value = '';
                document.getElementById('jobTitle').value = '';
                document.getElementById('departmentInfo').textContent = '';
                document.getElementById('candidateInfo').innerHTML = '';
            }
        }

        function loadTemplate(templateId) {
            if (templateId) {
                // In a real implementation, this would load template content via AJAX
                console.log('Loading template:', templateId);
            }
        }

        function addBenefit(benefit) {
            const textarea = document.querySelector('[name="benefits"]');
            const currentValue = textarea.value.trim();
            const newValue = currentValue ? currentValue + '\n• ' + benefit : '• ' + benefit;
            textarea.value = newValue;
            updateOfferPreview();
        }

        function updateOfferPreview() {
            const candidateSelect = document.querySelector('[name="candidate_id"]');
            const salary = document.querySelector('[name="salary_offered"]').value;
            const startDate = document.querySelector('[name="start_date"]').value;
            const benefits = document.querySelector('[name="benefits"]').value;
            
            if (candidateSelect.value && salary) {
                const candidateName = candidateSelect.options[candidateSelect.selectedIndex].text.split(' - ')[0];
                const jobTitle = document.getElementById('jobTitle').value;
                
                const preview = `
                    <h4 class="font-semibold text-gray-800">Job Offer Preview</h4>
                    <p class="mt-2">Dear ${candidateName},</p>
                    <p class="mt-2">We are pleased to offer you the position of <strong>${jobTitle}</strong>.</p>
                    <div class="mt-3">
                        <p><strong>Annual Salary:</strong> $${parseInt(salary).toLocaleString()}</p>
                        ${startDate ? `<p><strong>Start Date:</strong> ${new Date(startDate).toLocaleDateString()}</p>` : ''}
                        ${benefits ? `<p><strong>Benefits:</strong> ${benefits.substring(0, 100)}${benefits.length > 100 ? '...' : ''}</p>` : ''}
                    </div>
                `;
                
                document.getElementById('offerPreview').innerHTML = preview;
            }
        }

        // Add event listeners for real-time preview updates
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = ['candidate_id', 'salary_offered', 'start_date', 'benefits'];
            formInputs.forEach(name => {
                const element = document.querySelector(`[name="${name}"]`);
                if (element) {
                    element.addEventListener('input', updateOfferPreview);
                    element.addEventListener('change', updateOfferPreview);
                }
            });
            
            // Load candidate info if pre-selected
            const candidateSelect = document.querySelector('[name="candidate_id"]');
            if (candidateSelect.value) {
                loadCandidateInfo(candidateSelect.value);
            }
        });
    </script>
</body>
</html> 