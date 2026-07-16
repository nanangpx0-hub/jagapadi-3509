CREATE TABLE IF NOT EXISTS `master_opt` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_opt` VARCHAR(150) NOT NULL,
    `jenis` ENUM('hama','penyakit','gulma') NOT NULL,
    `etl_acuan` DECIMAL(10,2) NULL DEFAULT NULL,
    `satuan_etl` VARCHAR(30) NULL DEFAULT NULL,
    `foto_url` VARCHAR(300) NULL DEFAULT NULL,
    `deskripsi` TEXT NULL DEFAULT NULL,
    `aktif` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_nama_opt` (`nama_opt`),
    KEY `idx_jenis` (`jenis`),
    KEY `idx_aktif` (`aktif`),
    FULLTEXT KEY `ft_nama` (`nama_opt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
