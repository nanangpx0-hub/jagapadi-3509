<?php
declare(strict_types=1);

/**
 * Migration: Create early_warning_alerts and pest_detection_logs tables
 * Date: 2026-09-17
 */

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/app/core/Database.php';

foreach ([$rootPath . '/.env', $rootPath . '/.env.local'] as $envPath) {
    if (!is_file($envPath)) {
        continue;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

$db = Database::getInstance()->getConnection();
$rollback = in_array('--rollback', $argv ?? [], true);

if ($rollback) {
    $db->exec("DROP TABLE IF EXISTS pest_detection_logs");
    $db->exec("DROP TABLE IF EXISTS early_warning_alerts");
    echo "[OK] Rollback selesai.\n";
    exit(0);
}

$sqlAlerts = "CREATE TABLE IF NOT EXISTS early_warning_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_code VARCHAR(50) NOT NULL UNIQUE,
    master_opt_id INT UNSIGNED NULL,
    kecamatan_id INT UNSIGNED NULL,
    desa_id INT UNSIGNED NULL,
    tingkat_risiko ENUM('Aman','Waspada','Bahaya') NOT NULL DEFAULT 'Waspada',
    skor_risiko DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    faktor_cuaca_skor DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    faktor_citra_skor DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    faktor_populasi_skor DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    faktor_spasial_skor DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    prediksi_outbreak_at DATETIME NOT NULL,
    lead_time_jam INT UNSIGNED NOT NULL DEFAULT 72,
    ringkasan_ancaman VARCHAR(255) NOT NULL,
    rekomendasi_penanganan TEXT NOT NULL,
    data_lingkungan JSON NULL,
    saluran_distribusi VARCHAR(255) NULL,
    status ENUM('Aktif','Selesai','Dibatalkan') NOT NULL DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ewa_risiko (tingkat_risiko),
    INDEX idx_ewa_status (status),
    INDEX idx_ewa_prediksi (prediksi_outbreak_at),
    INDEX idx_ewa_kecamatan (kecamatan_id),
    INDEX idx_ewa_opt (master_opt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$db->exec($sqlAlerts);
echo "[OK] Tabel early_warning_alerts berhasil dibuat.\n";

$sqlDetection = "CREATE TABLE IF NOT EXISTS pest_detection_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    laporan_hama_id BIGINT UNSIGNED NULL,
    master_opt_id INT UNSIGNED NULL,
    predicted_opt_name VARCHAR(150) NOT NULL,
    confidence DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    image_path VARCHAR(300) NULL,
    visual_features JSON NULL,
    classification_model VARCHAR(100) NOT NULL DEFAULT 'Hybrid-CV-BioKlimatik-v1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pdl_opt (master_opt_id),
    INDEX idx_pdl_laporan (laporan_hama_id),
    INDEX idx_pdl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$db->exec($sqlDetection);
echo "[OK] Tabel pest_detection_logs berhasil dibuat.\n";
