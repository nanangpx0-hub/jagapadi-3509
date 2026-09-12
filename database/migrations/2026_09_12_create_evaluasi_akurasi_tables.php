<?php
/**
 * Migration: Create evaluasi_akurasi_panen + evaluasi_akurasi_logs (baseline).
 *
 * Latar belakang: kedua tabel selama ini dibuat otomatis per-request via
 * EvaluasiAkurasi::createTablesIfNotExist(). Sejak perbaikan 2026-09-12,
 * auto-DDL per-request dihapus; skema dikelola via migration append-only ini.
 *
 * Bersifat idempoten (CREATE TABLE IF NOT EXISTS) sehingga aman dijalankan
 * pada database yang tabelnya sudah ada maupun yang masih kosong.
 *
 * Jalankan:
 *   php database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php
 * Rollback (dokumentatif, jangan dieksekusi otomatis di production tanpa backup):
 *   php database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php --rollback
 */

declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/app/core/Database.php';

foreach ([$rootPath . '/.env', $rootPath . '/.env.local'] as $envPath) {
    if (!file_exists($envPath)) {
        continue;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            if ($key === '') {
                continue;
            }
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
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
    $db->exec('DROP TABLE IF EXISTS `evaluasi_akurasi_logs`');
    $db->exec('DROP TABLE IF EXISTS `evaluasi_akurasi_panen`');
    echo "Rollback selesai. Tabel evaluasi_akurasi_* dihapus.\n";
    exit(0);
}

$db->exec('CREATE TABLE IF NOT EXISTS `evaluasi_akurasi_panen` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `periode_bulan` INT(2) NOT NULL,
    `periode_tahun` YEAR NOT NULL,
    `wilayah_id` INT(11) NOT NULL,
    `nama_wilayah` VARCHAR(100) DEFAULT NULL,
    `luas_estimasi_daerah` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `luas_rilis_bps` DECIMAL(10,2) DEFAULT NULL,
    `deviasi_absolut` DECIMAL(10,2) DEFAULT NULL,
    `persentase_bias` DECIMAL(5,2) DEFAULT NULL,
    `status_akurasi` ENUM(\'Sangat Akurat\',\'Perlu Perhatian\',\'Bias Tinggi\') DEFAULT NULL,
    `catatan_analisis` TEXT DEFAULT NULL,
    `snapshot_locked` TINYINT(1) DEFAULT 0,
    `snapshot_date` DATE DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `updated_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_periode_wilayah` (`periode_bulan`, `periode_tahun`, `wilayah_id`),
    INDEX `idx_tahun` (`periode_tahun`),
    INDEX `idx_status` (`status_akurasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$db->exec('CREATE TABLE IF NOT EXISTS `evaluasi_akurasi_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(50) NOT NULL,
    `status` ENUM(\'success\',\'failed\',\'partial\') NOT NULL,
    `message` TEXT,
    `details` JSON,
    `user_id` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

echo "[OK] Tabel evaluasi_akurasi_panen dan evaluasi_akurasi_logs tersedia.\n";
