<?php
/**
 * JAGAPADI — Prune Old Notifications
 *
 * CLI script to delete notifications older than a given number of days.
 * Usage: php scripts/prune-notifications.php [days=90]
 *
 * Schedule (cron): 0 3 * * * php /path/to/scripts/prune-notifications.php >> storage/logs/prune.log 2>&1
 */

declare(strict_types=1);

// Bootstrap
define('BASE_PATH', dirname(__DIR__) . '/backend');
require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\NotificationService;

// Load .env
$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

// Determine retention days
$days = isset($argv[1]) ? (int) $argv[1] : 90;
if ($days < 1) {
    fwrite(STDERR, "ERROR: days must be a positive integer.\n");
    exit(1);
}

// Prune
try {
    $service = new NotificationService();
    $deleted = $service->pruneOlderThan($days);
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Pruned $deleted notifications older than $days days.\n";
} catch (\Throwable $e) {
    $timestamp = date('Y-m-d H:i:s');
    fwrite(STDERR, "[$timestamp] ERROR: {$e->getMessage()}\n");
    exit(1);
}
