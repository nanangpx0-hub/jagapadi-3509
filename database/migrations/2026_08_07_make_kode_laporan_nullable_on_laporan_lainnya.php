<?php

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/app/core/Database.php';

foreach ([$rootPath . '/.env', $rootPath . '/.env.local'] as $envPath) {
    if (!file_exists($envPath)) continue;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            $eqPos = strpos($line, '=');
            if ($eqPos === false) continue;
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if (empty($key)) continue;
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$rollback = in_array('--rollback', $argv ?? [], true);
$db = Database::getInstance()->getConnection();

if ($rollback) {
    $db->exec("ALTER TABLE `laporan_lainnya` MODIFY COLUMN `kode_laporan` VARCHAR(30) NOT NULL");
    echo "Rollback selesai. kode_laporan dikembalikan menjadi NOT NULL.\n";
    exit(0);
}

$db->exec("ALTER TABLE `laporan_lainnya` MODIFY COLUMN `kode_laporan` VARCHAR(30) NULL DEFAULT NULL");
echo "[OK] Kolom kode_laporan pada laporan_lainnya kini NULLABLE.\n";
echo "Kode laporan hanya akan diisi saat laporan disubmit (AGENTS.md: nomor laporan hanya dibuat saat Submitted).\n";
