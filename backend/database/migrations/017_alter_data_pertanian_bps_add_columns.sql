ALTER TABLE `data_pertanian_bps`
    ADD COLUMN `produksi_beras` DECIMAL(15,2) DEFAULT NULL COMMENT 'dalam ton' AFTER `produksi_gabah`,
    ADD COLUMN `kode_wilayah` VARCHAR(20) DEFAULT NULL AFTER `kabupaten_kota`,
    ADD COLUMN `sumber_data_type` ENUM('simulasi', 'resmi_webapi', 'manual') DEFAULT 'simulasi' AFTER `sumber_data`,
    ADD COLUMN `tipe_skenario` ENUM('baseline', 'optimis', 'pesimis') DEFAULT 'baseline' AFTER `sumber_data_type`,
    ADD COLUMN `is_validated` TINYINT(1) DEFAULT 0 AFTER `tipe_skenario`,
    ADD COLUMN `validation_notes` TEXT DEFAULT NULL AFTER `is_validated`,
    ADD COLUMN `keterangan` TEXT DEFAULT NULL AFTER `validation_notes`,
    ADD UNIQUE KEY `unique_data` (`tahun`, `kabupaten_kota`);
