<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check permission
requireRole('hr_recruiter');

$offer_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$offer_id) {
    header('Location: list.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get offer details
$offer_stmt = $conn->prepare("
    SELECT o.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
           j.title as job_title, j.id as job_id, j.department, j.salary_range,
           ot.name as template_name
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    LEFT JOIN offer_templates ot ON o.template_id = ot.id
    WHERE o.id = ?
");
$offer_stmt->execute([$offer_id]);
$offer = $offer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    header('Location: list.php');
    exit;
}

// Check if offer can be edited
if ($offer['status'] != 'draft') {
    $_SESSION['error'] = 'Only draft offers can be edited.';
    header('Location: view.php?id=' . $offer_id);
    exit;
}

// Get available templates
$templates_stmt = $conn->query("
    SELECT * FROM offer_templates 
    WHERE is_active = 1 
    ORDER BY name
");
$templates = $templates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $salary_offered = trim($_POST['salary_offered']);
    $benefits = trim($_POST['benefits']);
    $start_date = $_POST['start_date'];
    $valid_until = $_POST['valid_until'];
    $template_id = $_POST['template_id'] ?: null;
    $custom_terms = trim($_POST['custom_terms']);
    
    if (empty($salary_offered)) {
        $error = 'Salary is required.';
    } elseif (!is_numeric($salary_offered) || $salary_offered <= 0) {
        $error = 'Please enter a valid salary amount.';
    } elseif (!empty($start_date) && strtotime($start_date) <= time()) {
        $error = 'Start date must be in the future.';
    } elseif (!empty($valid_until) && strtotime($valid_until) <= time()) {
        $error = 'Offer validity date must be in the future.';
    } else {
        try {
            // Update offer
            $stmt = $conn->prepare("
                UPDATE offers 
                SET salary_offered = ?, benefits = ?, start_date = ?, valid_until = ?, 
                    template_id = ?, custom_terms = ?, updated_at = NOW()
                WHERE id = ? AND status = 'draft'
            ");
            
            $stmt->execute([
                $salary_offered, $benefits, $start_date, $valid_until, 
                $template_id, $custom_terms, $offer_id
            ]);
            
            // Regenerate offer letter if template changed
            if ($template_id != $offer['template_id']) {
                $offer_letter_content = generateOfferLetter(
                    $offer['candidate_id'], 
                    $offer['job_id'], 
                    $salary_offered, 
                    $benefits, 
                    $start_date, 
                    $template_id, 
                    $custom_terms
                );
                
                $offer_letter_filename = 'offer_' . $offer['candidate_id'] . '_' . time() . '.pdf';
                $offer_letter_path = UPLOAD_PATH . 'offers/' . $offer_letter_filename;
                
                // Create offers directory if it doesn't exist
                if (!file_exists(UPLOAD_PATH . 'offers/')) {
                    mkdir(UPLOAD_PATH . 'offers/', 0755, true);
                }
                
                file_put_contents($offer_letter_path, $offer_letter_content);
                
                // Update offer letter path
                $update_path_stmt = $conn->prepare("UPDATE offers SET offer_letter_path = ? WHERE id = ?");
                $update_path_stmt->execute([$offer_letter_path, $offer_id]);
            }
            
            // Log activity
            logActivity(
                $_SESSION['user_id'], 
                'offer_updated', 
                'offer', 
                $offer_id,
                "Updated offer for {$offer['candidate_first']} {$offer['candidate_last']}"
            );
            
            $success = 'Offer updated successfully.';
            
            // Refresh offer data
            $offer_stmt->execute([$offer_id]);
            $offer = $offer_stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = 'Error updating offer: ' . $e->getMessage();
        }
    }
}

function generateOfferLetter($candidate_id, $job_id, $salary, $benefits, $start_date, $template_id, $custom_terms) {
    global $conn;
    
    // Get candidate details
    $candidate_stmt = $conn->prepare("SELECT * FROM candidates WHERE id = ?");
    $candidate_stmt->execute([$candidate_id]);
    $candidate = $candidate_stmt->fetch();
    
    // Get job details
    $job_stmt = $conn->prepare("SELECT * FROM job_postings WHERE id = ?");
    $job_stmt->execute([$job_id]);
    $job = $job_stmt->fetch();
    
    // Get template content if specified
    $template_content = '';
    if ($template_id) {
        $template_stmt = $conn->prepare("SELECT content FROM offer_templates WHERE id = ?");
        $template_stmt->execute([$template_id]);
        $template = $template_stmt->fetch();
        $template_content = $template['content'] ?? '';
    }
    
    // Replace variables in template
    $variables = [
        '{candidate_name}' => $candidate['first_name'] . ' ' . $candidate['last_name'],
        '{candidate_first_name}' => $candidate['first_name'],
        '{job_title}' => $job['title'],
        '{department}' => $job['department'],
        '{salary}' => '$' . number_format($salary, 2),
        '{start_date}' => $start_date ? date('F j, Y', strtotime($start_date)) : 'To be determined',
        '{benefits}' => $benefits,
        '{custom_terms}' => $custom_terms,
        '{company_name}' => APP_NAME,
        '{current_date}' => date('F j, Y')
    ];
    
    if ($template_content) {
        $content = str_replace(array_keys($variables), array_values($variables), $template_content);
    } else {
        // Default offer letter format
        $content = "
        <h1>Job Offer Letter</h1>
        <p>Date: {$variables['{current_date}']}</p>
        
        <p>Dear {$variables['{candidate_first_name}']},</p>
        
        <p>We are pleased to offer you the position of <strong>{$variables['{job_title}']}</strong> at {$variables['{company_name}']}.</p>
        
        <h3>Offer Details:</h3>
        <ul>
            <li>Position: {$variables['{job_title}']}</li>
            <li>Department: {$variables['{department}']}</li>
            <li>Annual Salary: {$variables['{salary}']}</li>
            <li>Start Date: {$variables['{start_date}']}</li>
        </ul>
        
        " . ($benefits ? "<h3>Benefits:</h3><p>{$benefits}</p>" : "") . "
        
        " . ($custom_terms ? "<h3>Additional Terms:</h3><p>{$custom_terms}</p>" : "") . "
        
        <p>This offer is contingent upon successful completion of background checks and any other conditions outlined in our employment policies.</p>
        
        <p>We look forward to having you join our team!</p>
        
        <p>Sincerely,<br>HR Department<br>{$variables['{company_name}']}</p>
        ";
    }
    
    return $content;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Offer - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/header.php'; ?>
    
    <div class="flex">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="flex-1 p-6">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Edit Offer</h1>
                        <p class="text-gray-600">
                            <?php echo htmlspecialchars($offer['candidate_first'] . ' ' . $offer['candidate_last']); ?> - 
                            <?php echo htmlspecialchars($offer['job_title']); ?>
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="view.php?id=<?php echo $offer_id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-eye mr-2"></i>View Offer
                        </a>
                        <a href="list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i>Back to List
                        </a>
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

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Candidate & Position Info (Read-only) -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-user text-blue-500 mr-2"></i>
                                Candidate & Position
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Candidate</label>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <p class="font-medium"><?php echo htmlspecialchars($offer['candidate_first'] . ' ' . $offer['candidate_last']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($offer['candidate_email']); ?></p>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <p class="font-medium"><?php echo htmlspecialchars($offer['job_title']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($offer['department']); ?></p>
                                        <?php if ($offer['salary_range']): ?>
                                        <p class="text-xs text-gray-500">Range: <?php echo htmlspecialchars($offer['salary_range']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Offer Details -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">
                                <i class="fas fa-file-contract text-green-500 mr-2"></i>
                                Offer Details
                            </h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Annual Salary *</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" name="salary_offered" value="<?php echo $offer['salary_offered']; ?>" 
                                               step="1000" min="0" required
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Template</label>
                                    <select name="template_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="">Default Template</option>
                                        <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>" <?php echo $offer['template_id'] == $template['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($template['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                    <input type="date" name="start_date" value="<?php echo $offer['start_date']; ?>"
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Offer Valid Until</label>
                                    <input type="date" name="valid_until" value="<?php echo $offer['valid_until']; ?>"
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Benefits Package</label>
                                <textarea name="benefits" rows="4" 
                                          placeholder="Describe the benefits package, healthcare, vacation time, etc..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($offer['benefits']); ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Custom Terms & Conditions</label>
                                <textarea name="custom_terms" rows="4" 
                                          placeholder="Any additional terms, conditions, or special arrangements..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?php echo htmlspecialchars($offer['custom_terms']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Current Status -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Status</h3>
                            
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Offer Status:</span>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 text-xs font-semibold rounded-full">
                                        <?php echo ucfirst($offer['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Approval:</span>
                                    <span class="bg-orange-100 text-orange-800 px-2 py-1 text-xs font-semibold rounded-full">
                                        <?php echo ucfirst($offer['approval_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="text-xs text-gray-500">
                                    <p>Created: <?php echo date('M j, Y', strtotime($offer['created_at'])); ?></p>
                                    <p>Last Updated: <?php echo date('M j, Y g:i A', strtotime($offer['updated_at'])); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Preview</h3>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Current Salary:</span>
                                    <span class="font-medium">$<?php echo number_format($offer['salary_offered'], 0); ?></span>
                                </div>
                                
                                <?php if ($offer['start_date']): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Start Date:</span>
                                    <span class="font-medium"><?php echo date('M j, Y', strtotime($offer['start_date'])); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($offer['valid_until']): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Valid Until:</span>
                                    <span class="font-medium"><?php echo date('M j, Y', strtotime($offer['valid_until'])); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($offer['template_name']): ?>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Template:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($offer['template_name']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Actions</h3>
                            
                            <div class="space-y-3">
                                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-save mr-2"></i>Update Offer
                                </button>
                                
                                <button type="button" onclick="previewOffer()" class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-eye mr-2"></i>Preview Changes
                                </button>
                                
                                <a href="view.php?id=<?php echo $offer_id; ?>" class="block w-full text-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-arrow-right mr-2"></i>View Full Offer
                                </a>
                            </div>
                        </div>

                        <!-- Help -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-blue-800 mb-2">
                                <i class="fas fa-info-circle mr-1"></i>Editing Guidelines
                            </h4>
                            <ul class="text-xs text-blue-700 space-y-1">
                                <li>• Only draft offers can be edited</li>
                                <li>• Changes require re-approval</li>
                                <li>• Template changes regenerate the offer letter</li>
                                <li>• All dates must be in the future</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        function previewOffer() {
            // Get current form values
            const salary = document.querySelector('input[name="salary_offered"]').value;
            const startDate = document.querySelector('input[name="start_date"]').value;
            const validUntil = document.querySelector('input[name="valid_until"]').value;
            const benefits = document.querySelector('textarea[name="benefits"]').value;
            
            // Create preview content
            let previewContent = `
                <div class="space-y-4">
                    <h3 class="text-lg font-semibold">Offer Preview</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>Salary:</strong> $${parseInt(salary).toLocaleString()}</div>
                        <div><strong>Start Date:</strong> ${startDate ? new Date(startDate).toLocaleDateString() : 'TBD'}</div>
                        <div><strong>Valid Until:</strong> ${validUntil ? new Date(validUntil).toLocaleDateString() : 'TBD'}</div>
                    </div>
                    ${benefits ? '<div><strong>Benefits:</strong><br>' + benefits + '</div>' : ''}
                </div>
            `;
            
            // Show in modal or alert
            alert('Salary: $' + parseInt(salary).toLocaleString() + '\n' +
                  'Start Date: ' + (startDate ? new Date(startDate).toLocaleDateString() : 'TBD') + '\n' +
                  'Valid Until: ' + (validUntil ? new Date(validUntil).toLocaleDateString() : 'TBD'));
        }

        // Auto-update preview when form values change
        document.addEventListener('DOMContentLoaded', function() {
            const salaryInput = document.querySelector('input[name="salary_offered"]');
            const previewSalary = document.querySelector('.preview-salary');
            
            if (salaryInput && previewSalary) {
                salaryInput.addEventListener('input', function() {
                    previewSalary.textContent = '$' + parseInt(this.value || 0).toLocaleString();
                });
            }
        });
    </script>
</body>
</html> 