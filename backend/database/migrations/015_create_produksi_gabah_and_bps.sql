CREATE TABLE IF NOT EXISTS `produksi_gabah` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kecamatan_id` INT UNSIGNED NOT NULL,
    `tahun` INT NOT NULL,
    `luas_panen` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `produksi_total` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'draft, pending, verified, rejected',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_produksi_kecamatan` (`kecamatan_id`),
    KEY `idx_produksi_tahun` (`tahun`),
    KEY `idx_produksi_status` (`status`),
    CONSTRAINT `fk_produksi_kecamatan` FOREIGN KEY (`kecamatan_id`) REFERENCES `master_kecamatan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `data_pertanian_bps` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tahun` INT NOT NULL,
    `kabupaten_kota` VARCHAR(100) NOT NULL,
    `luas_panen` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `produksi_gabah` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `produktivitas` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `sumber_data` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bps_tahun` (`tahun`),
    KEY `idx_bps_kabupaten` (`kabupaten_kota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
