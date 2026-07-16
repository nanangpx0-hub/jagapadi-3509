CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(50) NULL DEFAULT NULL,
    `record_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log_wilayah` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` INT UNSIGNED NOT NULL,
    `tabel` VARCHAR(50) NOT NULL,
    `record_id` INT UNSIGNED NOT NULL,
    `aksi` ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    `data_lama` JSON NULL DEFAULT NULL,
    `data_baru` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin` (`admin_id`),
    KEY `idx_tabel_record` (`tabel`, `record_id`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_alw_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nomor_laporan_counter` (
    `prefix` VARCHAR(10) NOT NULL,
    `tanggal` DATE NOT NULL,
    `counter` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`prefix`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
