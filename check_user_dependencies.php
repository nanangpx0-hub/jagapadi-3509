<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check FKs referencing users table
    $sql = "
        SELECT 
            TABLE_NAME, 
            COLUMN_NAME, 
            CONSTRAINT_NAME, 
            REFERENCED_TABLE_NAME, 
            REFERENCED_COLUMN_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = '" . DB_NAME . "' 
        AND REFERENCED_TABLE_NAME = 'users'
    ";
    
    $stmt = $db->query($sql);
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Foreign Keys referencing 'users' table:\n";
    foreach ($fks as $fk) {
        echo "- Table: {$fk['TABLE_NAME']} | Column: {$fk['COLUMN_NAME']} | Constraint: {$fk['CONSTRAINT_NAME']}\n";
    }
    
    // Also check if the users exist to confirm target
    $targets = ['admin_jagapadi', 'operator1', 'viewer1', 'petugas'];
    echo "\nTarget Users Status:\n";
    $placeholders = str_repeat('?,', count($targets) - 1) . '?';
    $stmt = $db->prepare("SELECT id, username, role FROM users WHERE username IN ($placeholders)");
    $stmt->execute($targets);
    $found = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($found as $user) {
        echo "- Found: {$user['username']} (ID: {$user['id']}, Role: {$user['role']})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
