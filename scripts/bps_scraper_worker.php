#!/usr/bin/env php
<?php
/**
 * BPS Scraper Background Worker
 *
 * Polls the bps_scraping_queue table for pending jobs, executes scraping
 * in the background, and updates progress/result/status.
 *
 * Usage:
 *   php scripts/bps_scraper_worker.php [--once] [--poll-interval=30]
 *
 * Options:
 *   --once              Process only one pending job, then exit (cron-safe)
 *   --poll-interval=N   Seconds between queue polls (default 30)
 *
 * @version 1.0
 * @author JAGAPADI System
 */

define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL', 'https://localhost/' . basename(ROOT_PATH));
define('APP_ENV', 'cli');
define('APP_DEBUG', getenv('APP_DEBUG') ?: false);

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

// Load config constants (defines BPS_API_KEY, etc.)
$configPath = ROOT_PATH . '/config/config.php';
if (file_exists($configPath)) require_once $configPath;

// Autoload core dependencies
require_once ROOT_PATH . '/app/core/Database.php';
require_once ROOT_PATH . '/app/core/CacheManager.php';
require_once ROOT_PATH . '/app/models/DataPertanianBps.php';
require_once ROOT_PATH . '/app/services/BpsScraper.php';

$options = getopt('', ['once', 'poll-interval:']);
$once = isset($options['once']);
$pollInterval = isset($options['poll-interval']) ? (int)$options['poll-interval'] : 30;

$logFile = ROOT_PATH . '/logs/bps_worker.log';
$log = function($msg) use ($logFile) {
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND | LOCK_EX);
};

$db = Database::getInstance()->getConnection();
$model = new DataPertanianBps();

$log("Worker started" . ($once ? " (once mode)" : " (poll mode, interval={$pollInterval}s)") . " PID=" . getmypid());

do {
    // Lock and claim one exact row so concurrent workers cannot run the same job.
    $row = null;
    try {
        $db->beginTransaction();
        $stmt = $db->query(
            "SELECT * FROM bps_scraping_queue
             WHERE status = 'pending'
             ORDER BY id ASC
             LIMIT 1 FOR UPDATE"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row !== null) {
            $claim = $db->prepare(
                "UPDATE bps_scraping_queue
                 SET status = 'running', progress = 10, started_at = NOW()
                 WHERE id = ? AND status = 'pending'"
            );
            $claim->execute([(int) $row['id']]);
            if ($claim->rowCount() !== 1) {
                $row = null;
            }
        }
        $db->commit();
    } catch (Throwable $claimError) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $log('Failed to claim job: ' . $claimError->getMessage());
        $row = null;
    }

    if ($row !== null) {
        $jobId = (int)$row['id'];
        
        $log("Job #{$jobId} started: tahun={$row['tahun']}, source={$row['source']}, kabupaten=" . ($row['kabupaten'] ?? 'all'));
        
        try {
            $scraper = new BpsScraper();
            $result = $scraper->run([
                'tahun' => (int)$row['tahun'],
                'kabupaten' => $row['kabupaten'],
                'source' => $row['source'],
                'skenario' => $row['skenario'],
                'force_refresh' => (bool) ($row['force_refresh'] ?? false),
            ]);
            
            // Update job as completed
            $updateSql = "UPDATE bps_scraping_queue 
                          SET status = ?, progress = 100, result = ?, error_message = ?, completed_at = NOW() 
                          WHERE id = ?";
            $stmt = $db->prepare($updateSql);
            $stmt->execute([
                $result['success'] ? 'completed' : 'failed',
                json_encode($result),
                $result['success'] ? null : ($result['error'] ?? implode('; ', $result['errors'] ?? [])),
                $jobId
            ]);
            
            // Log activity
            $model->logActivity(
                'scrape_run',
                $result['success'] ? 'success' : 'error',
                "Background scraping job #{$jobId}: " . ($result['message'] ?? ''),
                array_merge($result, ['job_id' => $jobId])
            );
            
            // Invalidate cache
            $cache = CacheManager::getInstance();
            if ($cache->isAvailable()) {
                $cache->clearPrefix('bps_stats_');
                $cache->clearPrefix('bps_chart_');
            }
            
            $log("Job #{$jobId} completed: " . 
                ($result['records_success'] ?? 0) . " success, " . 
                ($result['records_failed'] ?? 0) . " failed");
                
        } catch (Throwable $e) {
            // Mark job as failed
            $updateSql = "UPDATE bps_scraping_queue 
                          SET status = 'failed', progress = 0, error_message = ?, completed_at = NOW() 
                          WHERE id = ?";
            $stmt = $db->prepare($updateSql);
            $stmt->execute([$e->getMessage(), $jobId]);
            
            $log("Job #{$jobId} FAILED: " . $e->getMessage());
        }
    }
    
    if ($once) {
        break;
    }
    
    // Sleep before next poll
    sleep($pollInterval);
} while (true);

$log("Worker stopped");
