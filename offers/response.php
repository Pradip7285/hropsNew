<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Get offer token from URL
$token = $_GET['token'] ?? null;
$error = '';
$success = '';

if (!$token) {
    header('Location: ' . BASE_URL);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get offer details by token (we'll need to add token to offers table)
$offer_stmt = $conn->prepare("
    SELECT o.*, 
           c.first_name as candidate_first, c.last_name as candidate_last, c.email as candidate_email,
           j.title as job_title, j.department, j.description as job_description,
           ot.name as template_name
    FROM offers o
    JOIN candidates c ON o.candidate_id = c.id
    JOIN job_postings j ON o.job_id = j.id
    LEFT JOIN offer_templates ot ON o.template_id = ot.id
    WHERE o.response_token = ? AND o.status = 'sent'
");
$offer_stmt->execute([$token]);
$offer = $offer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    $error = 'Invalid or expired offer link.';
} elseif ($offer['valid_until'] && strtotime($offer['valid_until']) < time()) {
    // Update offer to expired
    $conn->prepare("UPDATE offers SET status = 'expired' WHERE id = ?")->execute([$offer['id']]);
    $error = 'This offer has expired.';
} elseif ($offer['status'] != 'sent') {
    $error = 'This offer is no longer available for response.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error && $offer) {
    $response = $_POST['response'] ?? '';
    $comments = trim($_POST['comments']);
    $negotiation_salary = $_POST['negotiation_salary'] ?? '';
    $negotiation_terms = trim($_POST['negotiation_terms']);
    
    if (empty($response)) {
        $error = 'Please select your response to the offer.';
    } elseif ($response == 'negotiate' && (empty($negotiation_salary) && empty($negotiation_terms))) {
        $error = 'Please specify what you would like to negotiate.';
    } else {
        try {
            // Record the response
            $response_stmt = $conn->prepare("
                INSERT INTO offer_responses (offer_id, response, comments, negotiation_details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $negotiation_details = null;
            if ($response == 'negotiate') {
                $negotiation_details = json_encode([
                    'salary' => $negotiation_salary,
                    'terms' => $negotiation_terms
                ]);
            }
            
            $response_stmt->execute([
                $offer['id'],
                $response,
                $comments,
                $negotiation_details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            // Update offer status
            $new_status = ($response == 'accept') ? 'accepted' : (($response == 'reject') ? 'rejected' : 'negotiating');
            
            $update_stmt = $conn->prepare("
                UPDATE offers 
                SET status = ?, candidate_response_at = NOW(), response_notes = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$new_status, $comments, $offer['id']]);
            
            // Update candidate status
            $candidate_status = ($response == 'accept') ? 'hired' : (($response == 'reject') ? 'rejected' : 'offered');
            $conn->prepare("UPDATE candidates SET status = ? WHERE id = ?")->execute([$candidate_status, $offer['candidate_id']]);
            
            // Log notification
            $notification_type = ($response == 'accept') ? 'accepted' : (($response == 'reject') ? 'rejected' : 'negotiating');
            $notification_stmt = $conn->prepare("
                INSERT INTO offer_notifications (offer_id, notification_type, recipient_email)
                VALUES (?, ?, ?)
            ");
            $notification_stmt->execute([$offer['id'], $notification_type, 'hr@company.com']); // In production, get from settings
            
            // Send notification to HR (placeholder)
            // sendOfferResponseNotification($offer, $response, $comments, $negotiation_details);
            
            $success = true;
            
        } catch (Exception $e) {
            $error = 'Error processing your response: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Offer Response - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-12">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-800 mb-2"><?php echo APP_NAME; ?></h1>
                <p class="text-xl text-gray-600">Job Offer Response</p>
            </div>

            <?php if ($success): ?>
            <!-- Success Message -->
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <div class="mb-6">
                    <i class="fas fa-check-circle text-green-500 text-6xl mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Response Submitted Successfully!</h2>
                    <p class="text-gray-600">Thank you for your response. Our HR team has been notified and will be in touch with you soon.</p>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4 text-left">
                    <h3 class="font-semibold text-gray-800 mb-2">What happens next:</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php if ($_POST['response'] == 'accept'): ?>
                        <li>• Our HR team will contact you within 24 hours</li>
                        <li>• You'll receive onboarding information and next steps</li>
                        <li>• We'll schedule your first day and orientation</li>
                        <?php elseif ($_POST['response'] == 'negotiate'): ?>
                        <li>• Our HR team will review your negotiation request</li>
                        <li>• We'll respond within 2-3 business days</li>
                        <li>• You may receive a revised offer or further discussion</li>
                        <?php else: ?>
                        <li>• Your decision has been recorded</li>
                        <li>• Thank you for considering our offer</li>
                        <li>• We wish you the best in your career journey</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <?php elseif ($error): ?>
            <!-- Error Message -->
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <div class="mb-6">
                    <i class="fas fa-exclamation-triangle text-red-500 text-6xl mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Unable to Process Response</h2>
                    <p class="text-red-600"><?php echo htmlspecialchars($error); ?></p>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600">
                        If you believe this is an error, please contact our HR department directly at 
                        <a href="mailto:hr@company.com" class="text-blue-600 hover:underline">hr@company.com</a>
                    </p>
                </div>
            </div>

            <?php else: ?>
            <!-- Offer Response Form -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Offer Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">
                            Job Offer Details
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-2">Position Information</h3>
                                <div class="space-y-2">
                                    <p><span class="text-gray-600">Position:</span> <span class="font-medium"><?php echo htmlspecialchars($offer['job_title']); ?></span></p>
                                    <p><span class="text-gray-600">Department:</span> <span class="font-medium"><?php echo htmlspecialchars($offer['department']); ?></span></p>
                                    <p><span class="text-gray-600">Annual Salary:</span> <span class="font-medium text-green-600">$<?php echo number_format($offer['salary_offered'], 0); ?></span></p>
                                    <?php if ($offer['start_date']): ?>
                                    <p><span class="text-gray-600">Start Date:</span> <span class="font-medium"><?php echo date('F j, Y', strtotime($offer['start_date'])); ?></span></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="font-semibold text-gray-700 mb-2">Offer Timeline</h3>
                                <div class="space-y-2">
                                    <p><span class="text-gray-600">Offer Date:</span> <span class="font-medium"><?php echo date('F j, Y', strtotime($offer['created_at'])); ?></span></p>
                                    <?php if ($offer['valid_until']): ?>
                                    <p><span class="text-gray-600">Valid Until:</span> 
                                        <span class="font-medium <?php echo strtotime($offer['valid_until']) < strtotime('+3 days') ? 'text-red-600' : ''; ?>">
                                            <?php echo date('F j, Y', strtotime($offer['valid_until'])); ?>
                                        </span>
                                    </p>
                                    <?php 
                                    $days_remaining = ceil((strtotime($offer['valid_until']) - time()) / (60 * 60 * 24));
                                    if ($days_remaining > 0): 
                                    ?>
                                    <p class="text-sm <?php echo $days_remaining <= 3 ? 'text-red-600' : 'text-gray-600'; ?>">
                                        <?php echo $days_remaining; ?> day<?php echo $days_remaining != 1 ? 's' : ''; ?> remaining
                                    </p>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($offer['benefits']): ?>
                        <div class="mb-6">
                            <h3 class="font-semibold text-gray-700 mb-2">Benefits Package</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($offer['benefits'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($offer['custom_terms']): ?>
                        <div class="mb-6">
                            <h3 class="font-semibold text-gray-700 mb-2">Additional Terms</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($offer['custom_terms'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Response Form -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Your Response</h2>
                        
                        <form method="POST" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3">Please select your response:</label>
                                <div class="space-y-3">
                                    <label class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="response" value="accept" class="mr-3" required>
                                        <div>
                                            <div class="font-medium text-green-700">
                                                <i class="fas fa-check-circle mr-2"></i>Accept Offer
                                            </div>
                                            <div class="text-sm text-gray-600">I accept this job offer as presented</div>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="response" value="negotiate" class="mr-3" required>
                                        <div>
                                            <div class="font-medium text-blue-700">
                                                <i class="fas fa-handshake mr-2"></i>Request Negotiation
                                            </div>
                                            <div class="text-sm text-gray-600">I would like to discuss terms before accepting</div>
                                        </div>
                                    </label>
                                    
                                    <label class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="response" value="reject" class="mr-3" required>
                                        <div>
                                            <div class="font-medium text-red-700">
                                                <i class="fas fa-times-circle mr-2"></i>Decline Offer
                                            </div>
                                            <div class="text-sm text-gray-600">I respectfully decline this job offer</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Negotiation Fields (shown when negotiate is selected) -->
                            <div id="negotiationFields" class="hidden space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Salary Request</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                                        <input type="number" name="negotiation_salary" step="1000" min="0"
                                               placeholder="<?php echo $offer['salary_offered']; ?>"
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Other Terms to Negotiate</label>
                                    <textarea name="negotiation_terms" rows="4"
                                              placeholder="Please describe any other terms you'd like to discuss (start date, benefits, remote work, etc.)"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Comments (Optional)</label>
                                <textarea name="comments" rows="4"
                                          placeholder="Any additional comments or questions..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-8 py-3 rounded-lg font-medium transition duration-200">
                                    <i class="fas fa-paper-plane mr-2"></i>Submit Response
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div>
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h3 class="font-semibold text-gray-800 mb-4">Important Information</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex items-start">
                                <i class="fas fa-clock text-blue-500 mr-2 mt-1"></i>
                                <div>
                                    <p class="font-medium">Response Time</p>
                                    <p class="text-gray-600">Please respond by <?php echo date('F j, Y', strtotime($offer['valid_until'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-green-500 mr-2 mt-1"></i>
                                <div>
                                    <p class="font-medium">Secure Response</p>
                                    <p class="text-gray-600">Your response is encrypted and secure</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fas fa-phone text-purple-500 mr-2 mt-1"></i>
                                <div>
                                    <p class="font-medium">Questions?</p>
                                    <p class="text-gray-600">Contact HR at hr@company.com</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2">
                            <i class="fas fa-lightbulb mr-1"></i>Tips
                        </h4>
                        <ul class="text-xs text-blue-700 space-y-1">
                            <li>• Take time to review all details</li>
                            <li>• Consider the complete package</li>
                            <li>• Negotiation is often welcome</li>
                            <li>• Ask questions if anything is unclear</li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Show/hide negotiation fields based on response selection
        document.addEventListener('DOMContentLoaded', function() {
            const responseRadios = document.querySelectorAll('input[name="response"]');
            const negotiationFields = document.getElementById('negotiationFields');
            
            responseRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'negotiate') {
                        negotiationFields.classList.remove('hidden');
                    } else {
                        negotiationFields.classList.add('hidden');
                    }
                });
            });
        });

        // Confirmation for final submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const response = document.querySelector('input[name="response"]:checked').value;
            const responseText = {
                'accept': 'accept this job offer',
                'negotiate': 'request negotiations for this offer',
                'reject': 'decline this job offer'
            };
            
            if (!confirm(`Are you sure you want to ${responseText[response]}? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html> 