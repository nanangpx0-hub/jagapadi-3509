ALTER TABLE `master_opt`
    ADD COLUMN `kode_opt` VARCHAR(50) DEFAULT NULL AFTER `id`,
    ADD COLUMN `nama_ilmiah` VARCHAR(200) DEFAULT NULL AFTER `nama_opt`,
    ADD COLUMN `nama_lokal` VARCHAR(200) DEFAULT NULL AFTER `nama_ilmiah`,
    ADD COLUMN `status_karantina` VARCHAR(50) DEFAULT NULL AFTER `jenis`,
    ADD COLUMN `tingkat_bahaya` VARCHAR(50) DEFAULT NULL AFTER `status_karantina`,
    ADD COLUMN `kategori` VARCHAR(50) DEFAULT NULL AFTER `tingkat_bahaya`,
    ADD COLUMN `kingdom` VARCHAR(100) DEFAULT NULL AFTER `kategori`,
    ADD INDEX `idx_kode_opt` (`kode_opt`),
    ADD INDEX `idx_status_karantina` (`status_karantina`),
    ADD INDEX `idx_tingkat_bahaya` (`tingkat_bahaya`),
    ADD INDEX `idx_kategori` (`kategori`);
