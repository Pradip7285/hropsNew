<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>URL Test</title>
</head>
<body>
    <h1>URL Configuration Test</h1>
    
    <h2>Current Configuration:</h2>
    <p><strong>BASE_URL:</strong> <?php echo BASE_URL; ?></p>
    
    <h2>URL Tests:</h2>
    <ul>
        <li>Direct BASE_URL: <a href="<?php echo BASE_URL; ?>"><?php echo BASE_URL; ?></a></li>
        <li>BASE_URL + /dashboard.php: <a href="<?php echo BASE_URL; ?>/dashboard.php"><?php echo BASE_URL; ?>/dashboard.php</a></li>
        <li>BASE_URL + /interviews/list.php: <a href="<?php echo BASE_URL; ?>/interviews/list.php"><?php echo BASE_URL; ?>/interviews/list.php</a></li>
        <li>BASE_URL + /interviews/update_status.php: <a href="<?php echo BASE_URL; ?>/interviews/update_status.php"><?php echo BASE_URL; ?>/interviews/update_status.php</a></li>
    </ul>
    
    <h2>JavaScript URL Test:</h2>
    <button onclick="testNavigation()">Test Interview Update Status URL</button>
    
    <script>
    function testNavigation() {
        const testUrl = '<?php echo BASE_URL; ?>/interviews/update_status.php?id=1&status=test';
        console.log('Generated URL:', testUrl);
        alert('Generated URL: ' + testUrl);
    }
    </script>
</body>
</html> 