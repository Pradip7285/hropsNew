<?php
require_once 'config/config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents('database/create_development_pip_tables.sql');
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
    
    echo "Development Plans and PIP database tables created successfully!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
?> 