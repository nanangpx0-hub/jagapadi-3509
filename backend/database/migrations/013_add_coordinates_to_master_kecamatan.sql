ALTER TABLE `master_kecamatan`
    ADD COLUMN `latitude` DECIMAL(10,7) NULL DEFAULT NULL AFTER `nama_kecamatan`,
    ADD COLUMN `longitude` DECIMAL(10,7) NULL DEFAULT NULL AFTER `latitude`;
