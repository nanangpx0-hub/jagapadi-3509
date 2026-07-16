CREATE TABLE IF NOT EXISTS `master_kabupaten` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode` VARCHAR(10) NOT NULL,
    `nama_kabupaten` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode` (`kode`),
    KEY `idx_nama` (`nama_kabupaten`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `master_kecamatan` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kabupaten_id` INT UNSIGNED NOT NULL,
    `kode` VARCHAR(10) NOT NULL,
    `nama_kecamatan` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode` (`kode`),
    KEY `idx_kabupaten` (`kabupaten_id`),
    KEY `idx_nama` (`nama_kecamatan`),
    CONSTRAINT `fk_kecamatan_kabupaten` FOREIGN KEY (`kabupaten_id`) REFERENCES `master_kabupaten` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `master_desa` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kecamatan_id` INT UNSIGNED NOT NULL,
    `kode` VARCHAR(10) NOT NULL,
    `nama_desa` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode` (`kode`),
    KEY `idx_kecamatan` (`kecamatan_id`),
    KEY `idx_nama` (`nama_desa`),
    CONSTRAINT `fk_desa_kecamatan` FOREIGN KEY (`kecamatan_id`) REFERENCES `master_kecamatan` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
