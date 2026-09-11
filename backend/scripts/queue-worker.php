<?php

declare(strict_types=1);

/**
 * JAGAPADI queue worker (Backend v1, ADR-011).
 *
 * Memproses job antrean FileQueue di latar belakang (CLI/cron/daemon)
 * agar request HTTP tidak membeku menunggu scraper eksternal:
 *
 *   php backend/scripts/queue-worker.php [--queue=scraper] [--once]
 *       [--max-jobs=50] [--timeout=30] [--sleep=1]
 *       [--enqueue-nasa-wind] [--year=2026] [--month=9] [--force-simulation]
 *       [--enqueue-bps] [--help]
 *
 * - Timeout per-job, retry limit (max 5, exponential backoff+jitter di
 *   FileQueue), DLQ otomatis, error logging tanpa secret/PII.
 * - Fallback simulasi deterministik tersimpan sebagai last-success JSON
 *   di storage/cache (stale=true) bila sumber eksternal gagal.
 * - Cron contoh (setiap 15 menit): php /path/backend/scripts/queue-worker.php --once
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\FileQueue;
use App\Core\Logger;
use App\Services\ScraperJobService;

$envPath = BASE_PATH . '/.env';
if (is_file($envPath)) {
    Env::load($envPath);
}

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Asia/Jakarta'));

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
Logger::init($logDir);

$opts = getopt('', [
    'queue::',
    'once',
    'max-jobs::',
    'timeout::',
    'sleep::',
    'enqueue-nasa-wind',
    'enqueue-bps',
    'year::',
    'month::',
    'force-simulation',
    'help',
]);

if (isset($opts['help'])) {
    echo "JAGAPADI queue worker" . PHP_EOL;
    echo "Usage: php backend/scripts/queue-worker.php [--queue=scraper] [--once] [--max-jobs=50] [--timeout=30] [--sleep=1]" . PHP_EOL;
    echo "       [--enqueue-nasa-wind] [--enqueue-bps] [--year=YYYY] [--month=M] [--force-simulation]" . PHP_EOL;
    exit(0);
}

$queueName = (string) ($opts['queue'] ?? 'scraper');
if (!preg_match('/^[A-Za-z0-9_\\-]{1,64}$/', $queueName)) {
    fwrite(STDERR, "[ERROR] Nama antrean tidak valid." . PHP_EOL);
    exit(2);
}

$maxJobs = max(1, min(1000, (int) ($opts['max-jobs'] ?? 50)));
$timeout = max(5, min(300, (int) ($opts['timeout'] ?? 30)));
$sleepSeconds = max(0, min(30, (int) ($opts['sleep'] ?? 1)));
$once = isset($opts['once']);

$queue = new FileQueue();
$jobs = new ScraperJobService();

// Mode enqueue: controller/cron hanya push lalu keluar (non-blocking).
if (isset($opts['enqueue-nasa-wind']) || isset($opts['enqueue-bps'])) {
    $type = isset($opts['enqueue-nasa-wind']) ? ScraperJobService::TYPE_NASA_WIND : ScraperJobService::TYPE_BPS_SYNC;
    $payload = [
        'type' => $type,
        'year' => (int) ($opts['year'] ?? date('Y')),
        'month' => (int) ($opts['month'] ?? date('n')),
        'force_simulation' => isset($opts['force-simulation']),
        'enqueued_at' => date('Y-m-d H:i:s'),
    ];
    $idempotency = $type . '-' . $payload['year'] . '-' . $payload['month'];
    $id = $queue->push($queueName, $payload, $idempotency);
    echo json_encode(['queued' => true, 'queue' => $queueName, 'id' => $id, 'payload' => $payload]) . PHP_EOL;
    exit(0);
}

$processed = 0;
$failed = 0;
$deadline = time() + 3600; // daemon iteration guard: maks 1 jam per invocasi

while ($processed < $maxJobs && time() < $deadline) {
    $job = $queue->pop($queueName);
    if ($job === null) {
        if ($once) {
            break;
        }
        if ($processed > 0) {
            break; // drain mode: berhenti saat antrean kosong
        }
        sleep($sleepSeconds);
        break;
    }

    $jobId = (string) ($job['id'] ?? '');
    $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
    $attempt = (int) ($job['attempts'] ?? 1);
    $jobStarted = microtime(true);

    // Timeout per-job: worker tidak menunggu lebih dari $timeout detik.
    set_time_limit($timeout + 10);

    try {
        if ((microtime(true) - $jobStarted) > $timeout) {
            throw new \RuntimeException('Job exceeded timeout before start.');
        }
        $result = $jobs->dispatch($payload, $timeout);
        $queue->ack($queueName, $jobId);
        $processed++;
        $line = [
            'job' => $jobId,
            'type' => $payload['type'] ?? '?',
            'attempt' => $attempt,
            'success' => true,
            'fallback_used' => $result['fallback_used'] ?? false,
            'records' => $result['records_count'] ?? 0,
            'elapsed' => round(microtime(true) - $jobStarted, 2),
        ];
        echo json_encode($line) . PHP_EOL;
        Logger::info('Queue job succeeded', $line);
    } catch (\Throwable $e) {
        $failed++;
        $reason = substr($e->getMessage(), 0, 500);
        $queue->retry($queueName, $jobId, $reason);
        $line = [
            'job' => $jobId,
            'type' => $payload['type'] ?? '?',
            'attempt' => $attempt,
            'success' => false,
            'error' => $reason,
            'elapsed' => round(microtime(true) - $jobStarted, 2),
        ];
        echo json_encode($line) . PHP_EOL;
        // Tanpa secret/PII: hanya pesan error generik + metadata job.
        Logger::warning('Queue job failed, scheduled retry', $line);
    }

    if ($once) {
        break;
    }
}

echo json_encode(['processed' => $processed, 'failed' => $failed, 'queue' => $queueName]) . PHP_EOL;
exit($failed > 0 ? 1 : 0);
