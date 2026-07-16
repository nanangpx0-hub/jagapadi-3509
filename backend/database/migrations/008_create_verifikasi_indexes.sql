ALTER TABLE `laporan_hama`
    ADD INDEX `idx_verified_by` (`verified_by`),
    ADD INDEX `idx_verified_at` (`verified_at`);

ALTER TABLE `laporan_irigasi`
    ADD INDEX `idx_verified_by` (`verified_by`),
    ADD INDEX `idx_verified_at` (`verified_at`);
