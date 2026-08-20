#!/usr/bin/env php
<?php
/**
 * Generic Scraping Background Worker
 *
 * Polls the scraping_job_queue table for pending jobs, executes the
 * appropriate scraper in the background (CLI), and updates progress/result.
 *
 * Usage:
 *   php scripts/scraper_worker.php [--once] [--poll-interval=30]
 *
 * Options:
 *   --once              Process only one pending job, then exit (cron-safe)
 *   --poll-interval=N   Seconds between queue polls (default 30)
 *
 * Supported job_type: curah_hujan | angin | harga | bps
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
require_once ROOT_PATH . '/app/services/CurahHujanScraper.php';
require_once ROOT_PATH . '/app/services/KecepatanAnginScraper.php';
require_once ROOT_PATH . '/app/services/HargaKomoditasScraper.php';
require_once ROOT_PATH . '/app/services/BpsScraper.php';

$options = getopt('', ['once', 'poll-interval:']);
$once = isset($options['once']);
$pollInterval = isset($options['poll-interval']) ? (int)$options['poll-interval'] : 30;

$logFile = ROOT_PATH . '/logs/scraper_worker.log';
$log = function ($msg) use ($logFile) {
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$ts}] {$msg}\n", FILE_APPEND | LOCK_EX);
};

$db = Database::getInstance()->getConnection();

$log("Worker started" . ($once ? " (once mode)" : " (poll mode, interval={$pollInterval}s)") . " PID=" . getmypid());

do {
    // Claim a pending job (atomic UPDATE)
    // MySQL rejects selecting directly from the same table being updated
    // (error 1093). The extra derived-table level keeps the claim atomic and
    // works on both MySQL and MariaDB.
    $claimSql = "UPDATE scraping_job_queue
                 SET status = 'running', started_at = NOW()
                 WHERE id = (
                     SELECT pending_job.id
                     FROM (
                         SELECT id
                         FROM scraping_job_queue
                         WHERE status = 'pending'
                         ORDER BY id ASC
                         LIMIT 1
                     ) pending_job
                 )";
    $stmt = $db->prepare($claimSql);
    $affected = $stmt->execute() ? $stmt->rowCount() : 0;

    if ($affected > 0) {
        // Fetch the job we claimed
        $fetchSql = "SELECT * FROM scraping_job_queue WHERE status = 'running' ORDER BY id ASC LIMIT 1";
        $row = $db->query($fetchSql)->fetch(PDO::FETCH_ASSOC);
        $jobId = (int)$row['id'];
        $jobType = $row['job_type'];
        $params = json_decode($row['parameters'] ?? '{}', true) ?: [];

        $log("Job #{$jobId} started: type={$jobType}, params=" . json_encode($params));

        try {
            $result = dispatchScraper($jobType, $params);

            // Update job as completed
            $updateSql = "UPDATE scraping_job_queue
                          SET status = ?, progress = 100, result = ?, error_message = ?, completed_at = NOW()
                          WHERE id = ?";
            $stmt = $db->prepare($updateSql);
            $stmt->execute([
                !empty($result['success']) ? 'completed' : 'failed',
                json_encode($result),
                !empty($result['success']) ? null : ($result['error'] ?? $result['message'] ?? 'Unknown error'),
                $jobId
            ]);

            $log("Job #{$jobId} completed: " . json_encode($result));
        } catch (Throwable $e) {
            // Mark job as failed
            $updateSql = "UPDATE scraping_job_queue
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

/**
 * Dispatch a scraper job based on job_type.
 *
 * @param string $jobType  curah_hujan | angin | harga | bps
 * @param array  $params   Scraper parameters
 * @return array Result summary from the scraper
 */
function dispatchScraper(string $jobType, array $params): array {
    switch ($jobType) {
        case 'curah_hujan':
            $scraper = new CurahHujanScraper();
            return $scraper->run([
                'year' => (int)($params['year'] ?? date('Y')),
                'month' => (int)($params['month'] ?? date('m')),
                'source' => $params['source'] ?? 'nasa',
                'force_simulation' => (bool)($params['force_simulation'] ?? false),
            ]);

        case 'angin':
        case 'kecepatan_angin':
            $scraper = new KecepatanAnginScraper();
            return $scraper->run([
                'year' => (int)($params['year'] ?? date('Y')),
                'month' => (int)($params['month'] ?? date('m')),
                'source' => $params['source'] ?? 'nasa',
                'force_simulation' => (bool)($params['force_simulation'] ?? false),
            ]);

        case 'harga':
        case 'harga_komoditas':
            $scraper = new HargaKomoditasScraper();
            return $scraper->run([
                'year' => (int)($params['year'] ?? date('Y')),
                'month' => (int)($params['month'] ?? date('m')),
                'source' => $params['source'] ?? 'siskaperbapo',
            ]);

        case 'bps':
            $scraper = new BpsScraper();
            return $scraper->run([
                'tahun' => (int)($params['tahun'] ?? date('Y')),
                'kabupaten' => $params['kabupaten'] ?? null,
                'source' => $params['source'] ?? 'auto',
                'skenario' => $params['skenario'] ?? 'baseline',
                'fallback' => true,
            ]);

        default:
            throw new RuntimeException("Unknown job_type: {$jobType}");
    }
}

$log("Worker stopped");
