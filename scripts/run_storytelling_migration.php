<?php
/**
 * Script untuk menjalankan migration Data Storytelling
 * 
 * Usage: php scripts/run_storytelling_migration.php
 */

// Define ROOT_PATH
define('ROOT_PATH', dirname(__DIR__));

// Load database connection
require_once ROOT_PATH . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Read migration file
    $migrationFile = ROOT_PATH . '/database/migrations/2026-01-01_create_analisis_produksi_bulanan.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    
    echo "Starting Data Storytelling migration...\n";
    echo "=====================================\n";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $db->exec($statement);
                $preview = substr(preg_replace('/\s+/', ' ', $statement), 0, 60);
                echo "✓ Executed: {$preview}...\n";
            } catch (Exception $e) {
                echo "✗ Error: " . $e->getMessage() . "\n";
                echo "Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "=====================================\n";
    echo "Migration completed successfully!\n";
    echo "\nTables created:\n";
    echo "- analisis_produksi_bulanan\n";
    echo "- analisis_produksi_logs\n";
    echo "\nYou can now access the Data Storytelling dashboard at:\n";
    echo "http://localhost/jagapadi/storytelling\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>