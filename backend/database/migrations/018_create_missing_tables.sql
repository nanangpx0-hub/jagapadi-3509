-- Migration 018: Create missing tables referenced by models
-- Tables: kabupaten, tags, laporan_hama_tags, pembacaan_sensor,
--          sensor_pengairan, irrigation_rules, irrigation_rule_logs,
--          gabah_beras_logs, pengairan_otomatis, irrigation_logs

CREATE TABLE IF NOT EXISTS `kabupaten` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_kabupaten` VARCHAR(10) DEFAULT NULL,
    `nama_kabupaten` VARCHAR(255) NOT NULL,
    `provinsi` VARCHAR(100) NOT NULL DEFAULT 'Jawa Timur',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kabupaten_kode` (`kode_kabupaten`),
    KEY `idx_kabupaten_nama` (`nama_kabupaten`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_tag` VARCHAR(100) NOT NULL,
    `deskripsi` TEXT,
    `warna` VARCHAR(10) NOT NULL DEFAULT '#007bff',
    `usage_count` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tags_nama` (`nama_tag`),
    KEY `idx_tags_usage` (`usage_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laporan_hama_tags` (
    `laporan_hama_id` BIGINT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`laporan_hama_id`, `tag_id`),
    KEY `idx_lht_tag_id` (`tag_id`),
    CONSTRAINT `fk_lht_laporan` FOREIGN KEY (`laporan_hama_id`) REFERENCES `laporan_hama` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lht_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sensor_pengairan` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_sensor` VARCHAR(100) DEFAULT NULL,
    `nama` VARCHAR(255) NOT NULL,
    `tipe_sensor` VARCHAR(100) DEFAULT NULL,
    `nilai_min` DECIMAL(10,2) DEFAULT NULL,
    `nilai_max` DECIMAL(10,2) DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Aktif',
    `last_reading_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sensor_kode` (`kode_sensor`),
    KEY `idx_sensor_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pembacaan_sensor` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sensor_id` INT UNSIGNED NOT NULL,
    `nilai` DECIMAL(10,2) DEFAULT NULL,
    `status_pembacaan` VARCHAR(50) DEFAULT NULL,
    `waktu_baca` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ps_sensor_id` (`sensor_id`),
    KEY `idx_ps_waktu` (`waktu_baca`),
    CONSTRAINT `fk_ps_sensor` FOREIGN KEY (`sensor_id`) REFERENCES `sensor_pengairan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `irrigation_rules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `irigasi_id` INT UNSIGNED NOT NULL,
    `rule_name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `conditions` JSON NOT NULL,
    `actions` JSON NOT NULL,
    `priority` INT NOT NULL DEFAULT 10,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `cooldown_minutes` INT NOT NULL DEFAULT 60,
    `execution_count` INT NOT NULL DEFAULT 0,
    `last_executed_at` DATETIME DEFAULT NULL,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ir_irigasi` (`irigasi_id`),
    KEY `idx_ir_active` (`is_active`),
    KEY `idx_ir_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `irrigation_rule_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rule_id` INT UNSIGNED NOT NULL,
    `irigasi_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `conditions_snapshot` JSON,
    `actions_executed` JSON,
    `execution_status` VARCHAR(50) NOT NULL DEFAULT 'success',
    `execution_duration_ms` INT DEFAULT NULL,
    `error_message` TEXT,
    `weather_data` JSON,
    `triggered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_irl_rule_id` (`rule_id`),
    KEY `idx_irl_irigasi` (`irigasi_id`),
    KEY `idx_irl_status` (`execution_status`),
    KEY `idx_irl_triggered` (`triggered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gabah_beras_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(100) NOT NULL,
    `status` VARCHAR(50) DEFAULT NULL,
    `message` TEXT,
    `details` JSON,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gbl_action` (`action`),
    KEY `idx_gbl_user` (`user_id`),
    KEY `idx_gbl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pengairan_otomatis` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `irigasi_id` INT UNSIGNED DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT NULL,
    `triggered_by` VARCHAR(100) DEFAULT NULL,
    `started_at` DATETIME DEFAULT NULL,
    `ended_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_po_irigasi` (`irigasi_id`),
    KEY `idx_po_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `irrigation_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `irigasi_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT NULL,
    `message` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_il_irigasi` (`irigasi_id`),
    KEY `idx_il_action` (`action`),
    KEY `idx_il_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
