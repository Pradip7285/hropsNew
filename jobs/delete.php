<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$job_id = $_GET['id'] ?? 0;
$confirm = $_GET['confirm'] ?? '';

if (!$job_id) {
    header('Location: list.php?error=' . urlencode('Invalid job ID'));
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    // Get job details
    $stmt = $conn->prepare("SELECT * FROM job_postings WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        header('Location: list.php?error=' . urlencode('Job not found'));
        exit;
    }
    
    // Check for associated applications
    $app_stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates WHERE applied_for = ?");
    $app_stmt->execute([$job_id]);
    $app_count = $app_stmt->fetch()['count'];
    
    if ($confirm === 'yes') {
        // User confirmed deletion
        $conn->beginTransaction();
        
        try {
            // If there are applications, we need to handle them
            if ($app_count > 0) {
                // Option 1: Set applied_for to NULL (preserve candidate data)
                $update_candidates = $conn->prepare("UPDATE candidates SET applied_for = NULL WHERE applied_for = ?");
                $update_candidates->execute([$job_id]);
                
                // Option 2: Or delete related interviews and offers
                $delete_interviews = $conn->prepare("DELETE FROM interviews WHERE job_id = ?");
                $delete_interviews->execute([$job_id]);
                
                $delete_offers = $conn->prepare("DELETE FROM offers WHERE job_id = ?");
                $delete_offers->execute([$job_id]);
            }
            
            // Delete the job posting
            $delete_stmt = $conn->prepare("DELETE FROM job_postings WHERE id = ?");
            $delete_stmt->execute([$job_id]);
            
            // Log activity
            logActivity(
                $_SESSION['user_id'], 
                'deleted', 
                'job_posting', 
                $job_id,
                "Deleted job posting: {$job['title']} (had $app_count applications)"
            );
            
            $conn->commit();
            header('Location: list.php?success=' . urlencode('Job posting deleted successfully'));
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } else {
        // Show confirmation page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Delete Job - <?php echo APP_NAME; ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        </head>
        <body class="bg-gray-100">
            <?php include '../includes/header.php'; ?>
            
            <div class="flex">
                <?php include '../includes/sidebar.php'; ?>
                
                <main class="flex-1 p-6">
                    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 mt-20">
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
                            <h2 class="text-2xl font-bold text-gray-800 mb-4">Delete Job Posting</h2>
                            <p class="text-gray-600 mb-4">
                                Are you sure you want to delete the job posting:
                            </p>
                            <p class="font-semibold text-lg text-gray-800 mb-4">
                                "<?php echo htmlspecialchars($job['title']); ?>"
                            </p>
                            
                            <?php if ($app_count > 0): ?>
                            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded mb-4">
                                <i class="fas fa-warning mr-2"></i>
                                <strong>Warning:</strong> This job has <?php echo $app_count; ?> associated application(s). 
                                Deleting this job will remove the job reference from these applications.
                            </div>
                            <?php endif; ?>
                            
                            <p class="text-sm text-gray-500 mb-6">
                                This action cannot be undone.
                            </p>
                            
                            <div class="flex space-x-4">
                                <a href="delete.php?id=<?php echo $job_id; ?>&confirm=yes" 
                                   class="bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-trash mr-2"></i>Yes, Delete
                                </a>
                                <a href="view.php?id=<?php echo $job_id; ?>" 
                                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition duration-200">
                                    <i class="fas fa-arrow-left mr-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
} catch (Exception $e) {
    header('Location: list.php?error=' . urlencode('Error deleting job: ' . $e->getMessage()));
}
?> 