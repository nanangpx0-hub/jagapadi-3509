<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

foreach ([ROOT_PATH . '/.env', ROOT_PATH . '/.env.local'] as $envPath) {
    if (!is_file($envPath)) {
        continue;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
    }
}

require_once ROOT_PATH . '/app/core/Database.php';
require_once ROOT_PATH . '/app/core/Cache.php';
require_once ROOT_PATH . '/app/services/MasterOptService.php';
require_once ROOT_PATH . '/app/services/OptAutoPhotoService.php';

$service = new OptAutoPhotoService(Database::getInstance()->getConnection());
$total = ['processed' => 0, 'updated' => 0, 'failed' => 0];
$cursor = 0;

do {
    $result = $service->fillMissing(8, $cursor);
    $cursor = $result['last_id'];
    foreach ($total as $key => $value) {
        $total[$key] += $result[$key];
    }
    foreach ($result['errors'] as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    if ($result['processed'] > 0) {
        usleep(250000);
    }
} while ($result['processed'] > 0);

echo sprintf(
    "Diproses: %d; berhasil: %d; gagal: %d%s",
    $total['processed'],
    $total['updated'],
    $total['failed'],
    PHP_EOL
);
