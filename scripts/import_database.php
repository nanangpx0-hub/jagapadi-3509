<?php
/**
 * Database Import Script
 * Imports full SQL dump from bpsjembe_jagapadi.sql
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$sqlFile = ROOT_PATH . '/bpsjembe_jagapadi.sql';
if (!file_exists($sqlFile)) {
    echo "SQL file not found: $sqlFile\n";
    exit(1);
}

echo "Starting database import...\n";
$startTime = microtime(true);

try {
    $db = Database::getInstance()->getConnection();
    
    // Read and execute SQL file
    $sqlContent = file_get_contents($sqlFile);
    
    // Remove comments and clean up
    $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
    $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
    
    // Split by semicolon but keep transaction statements
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    
    for ($i = 0; $i < strlen($sqlContent); $i++) {
        $char = $sqlContent[$i];
        
        // Handle string literals
        if (($char === "'" || $char === '"') && ($i === 0 || $sqlContent[$i-1] !== '\\')) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
        }
        
        if ($char === ';' && !$inString) {
            $stmt = trim($current);
            if (!empty($stmt) && stripos($stmt, 'SET NAMES') !== 0 && stripos($stmt, 'SET FOREIGN') !== 0) {
                $statements[] = $stmt;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }
    
    // Execute statements
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $stmt) {
        if (empty(trim($stmt))) continue;
        
        try {
            $db->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            // Ignore certain errors (e.g., duplicate key, data truncation warnings)
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate entry') !== false || 
                stripos($msg, 'truncated for column') !== false ||
                stripos($msg, 'Data truncated') !== false) {
                // Non-critical warnings
            } else {
                $errors++;
                // Log first few errors only
                if ($errors <= 5) {
                    echo "Warning: " . substr($msg, 0, 100) . "\n";
                }
            }
        }
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "\nImport completed!\n";
    echo "Executed statements: $executed\n";
    echo "Errors/Warnings: $errors\n";
    echo "Duration: {$duration}s\n";
    
} catch (Exception $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
    exit(1);
}