-- =============================================================================
-- JAGAPADI — Complete Database Schema (Reference)
-- Target: MySQL 8.0+ / MariaDB 10.6+
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- =============================================================================
--
-- Cara pakai:
--   1. Buat database: CREATE DATABASE jagapadi_local ...;
--   2. php scripts/migrate.php  (disarankan)
--   3. Atau: mysql -u root -p jagapadi_local < database/schema.sql
--
-- =============================================================================

-- Schema migration tracker
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT UNSIGNED NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wilayah
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

-- Users
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `nama_lengkap` VARCHAR(150) NOT NULL,
    `role` ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
    `aktif` TINYINT(1) NOT NULL DEFAULT 1,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `last_password_change_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_role` (`role`),
    KEY `idx_aktif` (`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Master OPT (Organisme Pengganggu Tanaman)
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

-- Laporan Hama
CREATE TABLE IF NOT EXISTS `laporan_hama` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nomor_laporan` VARCHAR(20) NULL DEFAULT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `master_opt_id` INT UNSIGNED NULL DEFAULT NULL,
    `tanggal` DATE NULL DEFAULT NULL,
    `kabupaten_id` INT UNSIGNED NULL DEFAULT NULL,
    `kecamatan_id` INT UNSIGNED NULL DEFAULT NULL,
    `desa_id` INT UNSIGNED NULL DEFAULT NULL,
    `lokasi` VARCHAR(255) NULL DEFAULT NULL,
    `alamat_lengkap` VARCHAR(300) NULL DEFAULT NULL,
    `latitude` DECIMAL(10,7) NULL DEFAULT NULL,
    `longitude` DECIMAL(10,7) NULL DEFAULT NULL,
    `tingkat_keparahan` ENUM('Ringan','Sedang','Berat') NULL DEFAULT NULL,
    `luas_serangan` DECIMAL(8,2) NULL DEFAULT NULL,
    `populasi` DECIMAL(10,2) NULL DEFAULT NULL,
    `foto_url` VARCHAR(300) NULL DEFAULT NULL,
    `catatan` TEXT NULL DEFAULT NULL,
    `status` ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan') NOT NULL DEFAULT 'Draf',
    `verified_by` INT UNSIGNED NULL DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `catatan_verifikasi` TEXT NULL DEFAULT NULL,
    `ip_pengirim` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_nomor_laporan` (`nomor_laporan`),
    KEY `idx_user` (`user_id`),
    KEY `idx_opt` (`master_opt_id`),
    KEY `idx_status` (`status`),
    KEY `idx_tanggal` (`tanggal`),
    KEY `idx_kecamatan` (`kecamatan_id`),
    KEY `idx_tingkat` (`tingkat_keparahan`),
    KEY `idx_status_tanggal` (`status`, `tanggal`),
    CONSTRAINT `fk_lh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_lh_opt` FOREIGN KEY (`master_opt_id`) REFERENCES `master_opt` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_lh_kabupaten` FOREIGN KEY (`kabupaten_id`) REFERENCES `master_kabupaten` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_lh_kecamatan` FOREIGN KEY (`kecamatan_id`) REFERENCES `master_kecamatan` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_lh_desa` FOREIGN KEY (`desa_id`) REFERENCES `master_desa` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_lh_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `ck_lh_luas_serangan` CHECK (`luas_serangan` IS NULL OR (`luas_serangan` >= 0 AND `luas_serangan` <= 9999.99)),
    CONSTRAINT `ck_lh_latitude` CHECK (`latitude` IS NULL OR (`latitude` >= -90 AND `latitude` <= 90)),
    CONSTRAINT `ck_lh_longitude` CHECK (`longitude` IS NULL OR (`longitude` >= -180 AND `longitude` <= 180))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Laporan Irigasi
CREATE TABLE IF NOT EXISTS `laporan_irigasi` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nomor_laporan` VARCHAR(20) NULL DEFAULT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `tanggal` DATE NULL DEFAULT NULL,
    `kabupaten_id` INT UNSIGNED NULL DEFAULT NULL,
    `kecamatan_id` INT UNSIGNED NULL DEFAULT NULL,
    `desa_id` INT UNSIGNED NULL DEFAULT NULL,
    `nama_saluran` VARCHAR(200) NULL DEFAULT NULL,
    `daerah_irigasi` VARCHAR(200) NULL DEFAULT NULL,
    `latitude` DECIMAL(10,7) NULL DEFAULT NULL,
    `longitude` DECIMAL(10,7) NULL DEFAULT NULL,
    `kondisi_fisik` ENUM('Bagus','Sedang','Tidak Bagus','Rusak') NULL DEFAULT NULL,
    `debit_air` ENUM('Cukup','Kurang','Kering') NULL DEFAULT NULL,
    `foto_url` VARCHAR(300) NULL DEFAULT NULL,
    `catatan` TEXT NULL DEFAULT NULL,
    `status` ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan') NOT NULL DEFAULT 'Draf',
    `verified_by` INT UNSIGNED NULL DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `catatan_verifikasi` TEXT NULL DEFAULT NULL,
    `ip_pengirim` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_nomor_laporan` (`nomor_laporan`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_tanggal` (`tanggal`),
    KEY `idx_kecamatan` (`kecamatan_id`),
    KEY `idx_kondisi` (`kondisi_fisik`),
    KEY `idx_status_tanggal` (`status`, `tanggal`),
    CONSTRAINT `fk_li_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_li_kabupaten` FOREIGN KEY (`kabupaten_id`) REFERENCES `master_kabupaten` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_li_kecamatan` FOREIGN KEY (`kecamatan_id`) REFERENCES `master_kecamatan` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_li_desa` FOREIGN KEY (`desa_id`) REFERENCES `master_desa` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_li_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `ck_li_latitude` CHECK (`latitude` IS NULL OR (`latitude` >= -90 AND `latitude` <= 90)),
    CONSTRAINT `ck_li_longitude` CHECK (`longitude` IS NULL OR (`longitude` >= -180 AND `longitude` <= 180))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log
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

-- Audit log wilayah
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

-- Nomor laporan counter
CREATE TABLE IF NOT EXISTS `nomor_laporan_counter` (
    `prefix` VARCHAR(10) NOT NULL,
    `tanggal` DATE NOT NULL,
    `counter` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`prefix`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
