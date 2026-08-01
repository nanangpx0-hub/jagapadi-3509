<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Core\Database;
use App\Core\Logger;

$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

$timezone = Env::get('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set($timezone);

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
Logger::init($logDir);

$migrationDir = BASE_PATH . '/database/migrations';
$trackDir = BASE_PATH . '/storage/migrations';
if (!is_dir($trackDir)) {
    @mkdir($trackDir, 0775, true);
}

echo "=== JAGAPADI Database Migration ===" . PHP_EOL;
echo PHP_EOL;

try {
    $pdo = Database::connect();
} catch (\Throwable $e) {
    echo "[ERROR] Database tidak dapat diakses: " . $e->getMessage() . PHP_EOL;
    echo "Pastikan server database berjalan dan konfigurasi .env benar." . PHP_EOL;
    exit(1);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT UNSIGNED NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$stmt = $pdo->query("SELECT `migration` FROM `schema_migrations` ORDER BY `migration`");
$executed = $stmt->fetchAll(\PDO::FETCH_COLUMN);
$executedMap = array_flip($executed);

$migrationFiles = glob($migrationDir . '/*.sql');
sort($migrationFiles);

$stmt = $pdo->query("SELECT COALESCE(MAX(`batch`), 0) FROM `schema_migrations`");
$batch = (int) $stmt->fetchColumn() + 1;

$ran = 0;
$skipped = 0;

foreach ($migrationFiles as $file) {
    $filename = basename($file);

    if (isset($executedMap[$filename])) {
        echo "  [SKIP] $filename (already executed)" . PHP_EOL;
        $skipped++;
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "  [WARN] $filename is empty, skipping" . PHP_EOL;
        continue;
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);

        $insert = $pdo->prepare("INSERT INTO `schema_migrations` (`migration`, `batch`) VALUES (?, ?)");
        $insert->execute([$filename, $batch]);

        $pdo->commit();

        echo "  [OK]   $filename" . PHP_EOL;
        $ran++;
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        echo "  [FAIL] $filename" . PHP_EOL;
        echo "         " . $e->getMessage() . PHP_EOL;
        echo PHP_EOL;
        echo "Migration dihentikan. Perbaiki error dan jalankan ulang." . PHP_EOL;
        exit(1);
    }
}

echo PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "  Executed: $ran" . PHP_EOL;
echo "  Skipped:  $skipped" . PHP_EOL;
echo "  Batch:    $batch" . PHP_EOL;
echo PHP_EOL;

if ($ran === 0 && $skipped > 0) {
    echo "Database sudah terupdate. Tidak ada migration baru." . PHP_EOL;
}

exit(0);
