<?php
/**
 * BPS Auto-Scrape CLI Script
 *
 * Automatically triggers a BPS scraping job via the background queue.
 * Intended to be run via cron job.
 *
 * Usage:
 *   php scripts/bps_auto_scrape.php --tahun=2025 --source=auto
 *
 * Cron example (every 1st of month at 02:00):
 *   0 2 1 * * php /path/to/scripts/bps_auto_scrape.php --tahun=$(date +\%Y) --source=auto
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_ENV', 'cli');

// Load .env.local
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

$configPath = ROOT_PATH . '/config/config.php';
if (file_exists($configPath)) require_once $configPath;

require_once ROOT_PATH . '/app/core/Database.php';
require_once ROOT_PATH . '/app/models/DataPertanianBps.php';

// Parse arguments
$options = getopt('', ['tahun:', 'source:', 'kabupaten::', 'skenario::']);
$tahun = isset($options['tahun']) ? (int)$options['tahun'] : (int)date('Y');
$source = $options['source'] ?? 'auto';
$kabupaten = $options['kabupaten'] ?? null;
$skenario = $options['skenario'] ?? 'baseline';

$logFile = ROOT_PATH . '/logs/bps_auto_scrape.log';
$log = function(string $msg, string $level = 'INFO') use ($logFile) {
    $ts = date('Y-m-d H:i:s');
    $entry = "[{$ts}] [{$level}] {$msg}\n";
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    echo $entry;
};

try {
    $db = Database::getInstance()->getConnection();
    $model = new DataPertanianBps();
    
    $log("Auto-scrape triggered: tahun={$tahun}, source={$source}, kabupaten=" . ($kabupaten ?? 'all'));
    
    // Check for anomalies before scraping
    $anomalies = $model->getAnomalies($tahun);
    if (!empty($anomalies)) {
        $log("ANOMALY DETECTED: " . count($anomalies) . " records with data issues for tahun={$tahun}", 'WARNING');
        foreach ($anomalies as $a) {
            $log("  - {$a['kabupaten_kota']}: luas={$a['luas_panen']}, produksi={$a['produksi_gabah']}", 'WARNING');
        }
    }
    
    // Queue the background job
    $sql = "INSERT INTO bps_scraping_queue (tahun, kabupaten, source, skenario, created_by, status, progress)
            VALUES (?, ?, ?, ?, ?, 'pending', 0)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$tahun, $kabupaten, $source, $skenario, null]);
    $jobId = (int)$db->lastInsertId();
    
    $log("Background scraping job #{$jobId} queued successfully");
    
    // Log to bps_scraping_logs
    $model->logActivity(
        'auto_scrape',
        'success',
        "Auto-scrape job #{$jobId} queued: tahun={$tahun}, source={$source}",
        [
            'job_id' => $jobId,
            'tahun' => $tahun,
            'source' => $source,
            'kabupaten' => $kabupaten ?? 'all',
            'skenario' => $skenario
        ]
    );
    
    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'message' => 'Auto-scrape job queued',
        'tahun' => $tahun,
        'source' => $source
    ], JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    $log("Auto-scrape FAILED: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT) . "\n";
    exit(1);
}
