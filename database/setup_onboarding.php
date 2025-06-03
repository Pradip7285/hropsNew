<?php
require_once __DIR__ . '/../config/config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = file_get_contents(__DIR__ . '/onboarding_schema.sql');
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
    
    echo "Onboarding database schema created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 