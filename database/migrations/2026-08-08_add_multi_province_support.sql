CREATE TABLE IF NOT EXISTS `master_provinsi` (
    `kode_provinsi` VARCHAR(10) PRIMARY KEY,
    `nama_provinsi` VARCHAR(100) NOT NULL,
    `is_active` TINYINT DEFAULT 1,
    INDEX idx_nama (`nama_provinsi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `master_kabupaten_by_province` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `kode_provinsi` VARCHAR(10) NOT NULL,
    `kode_kabupaten` VARCHAR(10) NOT NULL,
    `nama_kabupaten` VARCHAR(100) NOT NULL,
    `tipe_kabupaten` ENUM('kabupaten','kota') DEFAULT 'kabupaten',
    UNIQUE KEY `unique_prov_kab` (`kode_provinsi`, `kode_kabupaten`),
    INDEX idx_provinsi (`kode_provinsi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: Jawa Timur (35)
INSERT IGNORE INTO `master_provinsi` (`kode_provinsi`, `nama_provinsi`) VALUES
('35', 'JAWA TIMUR'),
('01', 'JAKARTA (DKI)');

INSERT IGNORE INTO `master_kabupaten_by_province` (`kode_provinsi`, `kode_kabupaten`, `nama_kabupaten`, `tipe_kabupaten`) VALUES
('35', '011', 'KABUPATEN BANGKALAN', 'kabupaten'),
('35', '020', 'KABUPATEN BANYUWANGI', 'kabupaten'),
('35', '021', 'KABUPATEN BATU', 'kota'),
('35', '022', 'KABUPATEN BLAIKES', 'kabupaten'),
('35', '031', 'KABUPATEN BOJONEGORO', 'kabupaten'),
('35', '032', 'KABUPATEN BONDOWOSO', 'kabupaten'),
('35', '041', 'KABUPATEN PROBOLINGGO', 'kabupaten'),
('35', '051', 'KABUPATEN JEMBER', 'kabupaten'),
('35', '061', 'KOTA SURABAYA', 'kota'),
('35', '071', 'KOTA SIDOARJO', 'kota'),
('35', '081', 'KOTA MALANG', 'kota'),
('35', '731', 'KABUPATEN KEDIRI', 'kabupaten');

-- Add kode_provinsi column to data_pertanian_bps if missing
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'data_pertanian_bps'
      AND COLUMN_NAME = 'kode_provinsi'
);

SET @sql := IF(@col_exists = 0,
    'ALTER TABLE data_pertanian_bps ADD COLUMN kode_provinsi VARCHAR(10) NOT NULL DEFAULT \'35\' AFTER tahun,
     ADD UNIQUE KEY unique_tahun_prov_kab (tahun, kode_provinsi, kabupaten_kota)',
    'SELECT "Column kode_provinsi already exists"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
