<?php
/**
 * Migration: Create table data_ksa_bulanan
 * Tabel penyimpan data KSA (Survei Kerangka Sampel Area) dengan granularitas
 * bulanan per kabupaten/kota Jawa Timur. Berbeda dari data_pertanian_bps yang
 * berisi agregat tahunan.
 *
 * Jalankan:
 *   php database/migrations/2026_08_07_create_data_ksa_bulanan.php
 * Rollback:
 *   php database/migrations/2026_08_07_create_data_ksa_bulanan.php --rollback
 */

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
    $db->exec("DROP TABLE IF EXISTS `data_ksa_bulanan`");
    echo "Rollback selesai. Tabel data_ksa_bulanan dihapus.\n";
    exit(0);
}

$db->exec("CREATE TABLE IF NOT EXISTS `data_ksa_bulanan` (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun          SMALLINT UNSIGNED NOT NULL,
    bulan          TINYINT UNSIGNED NOT NULL,
    kabupaten_kota VARCHAR(100) NOT NULL,
    kode_wilayah   VARCHAR(10)  NOT NULL,
    luas_panen     DECIMAL(15,4) NULL,
    produksi_gabah DECIMAL(15,4) NULL,
    produksi_beras DECIMAL(15,4) NULL,
    produktivitas  DECIMAL(10,4) NULL,
    status_data    ENUM('tetap','sementara','potensi') NOT NULL DEFAULT 'tetap',
    sumber_file    VARCHAR(255) NULL,
    sumber_sheet   VARCHAR(100) NULL,
    keterangan     TEXT NULL,
    imported_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tahun_bulan (tahun, bulan),
    INDEX idx_kabupaten (kabupaten_kota),
    INDEX idx_kode_wilayah (kode_wilayah),
    UNIQUE KEY uk_ksa (tahun, bulan, kode_wilayah)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "[OK] Tabel data_ksa_bulanan berhasil dibuat.\n";
