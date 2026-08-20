CREATE TABLE IF NOT EXISTS `analisis_produksi_bulanan` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `periode_bulan` INT NOT NULL,
    `periode_tahun` INT NOT NULL,
    `wilayah_id` INT UNSIGNED NOT NULL,
    `total_luas_panen` DECIMAL(15,2) DEFAULT 0.00,
    `faktor_penyebab_utama` VARCHAR(100) NOT NULL,
    `skor_risiko_cuaca` INT DEFAULT 0,
    `skor_risiko_hama` INT DEFAULT 0,
    `skor_risiko_total` INT DEFAULT 0,
    `avg_curah_hujan_lag1` DECIMAL(10,2) DEFAULT 0.00,
    `total_laporan_hama_lag1` INT DEFAULT 0,
    `laporan_hama_berat_lag1` INT DEFAULT 0,
    `narasi_otomatis` TEXT,
    `narasi_final` TEXT,
    `status_analisis` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_periode_wilayah` (`periode_bulan`, `periode_tahun`, `wilayah_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analisis_produksi_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `analisis_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `old_values` TEXT NULL,
    `new_values` TEXT NULL,
    `notes` TEXT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
