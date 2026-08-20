<?php
/**
 * BPS Data Standardization Script
 * 
 * Fixes GKG-to-Beras conversion ratio for manual data records.
 * Legacy data used 0.5744 ratio; current standard uses 0.577.
 *
 * Usage:
 *   php scripts/standardize_bps_data.php
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

define('ROOT_PATH', dirname(__DIR__));

$envPath = ROOT_PATH . '/.env.local';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) continue;
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;
        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))) { $value = substr($value, 1, -1); }
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

require_once ROOT_PATH . '/app/core/Database.php';

echo "=== BPS Data Standardization ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Check for affected records
    $checkSql = "SELECT COUNT(*) FROM data_pertanian_bps 
                 WHERE sumber_data_type = 'manual' 
                 AND produksi_gabah > 0 
                 AND ABS(produksi_beras - (produksi_gabah * 0.577)) > 1";
    $affectedCount = (int)$db->query($checkSql)->fetchColumn();
    
    echo "Records needing update: {$affectedCount}\n\n";
    
    if ($affectedCount === 0) {
        echo "No records to update. All manual records are already standardized.\n";
        exit;
    }
    
    // Show sample of affected records before update
    $sampleSql = "SELECT id, kabupaten_kota, tahun, produksi_gabah, produksi_beras,
                  ROUND(produksi_gabah * 0.577, 2) as expected_beras,
                  (produksi_beras - ROUND(produksi_gabah * 0.577, 2)) as diff
                  FROM data_pertanian_bps 
                  WHERE sumber_data_type = 'manual' 
                  AND produksi_gabah > 0 
                  AND ABS(produksi_beras - (produksi_gabah * 0.577)) > 1
                  LIMIT 5";
    
    echo "Sample affected records (before):\n";
    $samples = $db->query($sampleSql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($samples as $row) {
        echo "  ID={$row['id']}, {$row['kabupaten_kota']} {$row['tahun']}: " .
             "gabah={$row['produksi_gabah']}, beras={$row['produksi_beras']}, " .
             "expected=" . $row['expected_beras'] . ", diff={$row['diff']}\n";
    }
    echo "\n";
    
    // Execute the fix
    $updateSql = "UPDATE data_pertanian_bps 
                  SET produksi_beras = ROUND(produksi_gabah * 0.577, 2) 
                  WHERE sumber_data_type = 'manual' 
                  AND produksi_gabah > 0 
                  AND ABS(produksi_beras - (produksi_gabah * 0.577)) > 1";
    
    $stmt = $db->prepare($updateSql);
    $stmt->execute();
    $updatedCount = $stmt->rowCount();
    
    echo "{$updatedCount} records updated successfully.\n\n";
    
    // Verify
    $verifySql = "SELECT COUNT(*) FROM data_pertanian_bps 
                  WHERE sumber_data_type = 'manual' 
                  AND produksi_gabah > 0 
                  AND ABS(produksi_beras - (produksi_gabah * 0.577)) > 1";
    $remaining = (int)$db->query($verifySql)->fetchColumn();
    
    echo "Verification:\n";
    echo "  Remaining non-standard records: {$remaining}\n";
    echo "  Status: " . ($remaining === 0 ? "PASSED" : "INCOMPLETE") . "\n";
    
    // Show sample after update
    echo "\nSample records (after):\n";
    $samplesAfter = $db->query("
        SELECT id, kabupaten_kota, tahun, produksi_gabah, produksi_beras,
               ROUND(produksi_gabah * 0.577, 2) as expected_beras
        FROM data_pertanian_bps 
        WHERE sumber_data_type = 'manual' 
        AND produksi_gabah > 0
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($samplesAfter as $row) {
        echo "  ID={$row['id']}, {$row['kabupaten_kota']} {$row['tahun']}: " .
             "gabah={$row['produksi_gabah']}, beras={$row['produksi_beras']}, " .
             "expected=" . $row['expected_beras'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
