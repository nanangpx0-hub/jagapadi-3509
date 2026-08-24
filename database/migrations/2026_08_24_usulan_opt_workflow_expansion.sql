-- Runtime root/integrated: ekspansi workflow Usulan OPT (Draf, Perlu Perbaikan,
-- Ditolak Permanen), field identifikasi, tabel foto, dan riwayat status.
-- Append-only; jalankan pada database target setelah backup dan audit schema_migrations.
-- Rollback plan: lihat docs/IMPLEMENTASI_USULAN_OPT_REVIEW.md (bagian Migration).

ALTER TABLE `usulan_opt`
  MODIFY `status` ENUM('Draf','Menunggu Review','Perlu Perbaikan','Disetujui','Digabungkan','Ditolak','Ditolak Permanen')
    NOT NULL DEFAULT 'Menunggu Review';

UPDATE `usulan_opt` SET `status` = 'Ditolak Permanen' WHERE `status` = 'Ditolak';

ALTER TABLE `usulan_opt`
  MODIFY `status` ENUM('Draf','Menunggu Review','Perlu Perbaikan','Disetujui','Digabungkan','Ditolak Permanen')
    NOT NULL DEFAULT 'Menunggu Review',
  ADD COLUMN `tanggal_ditemukan` DATE NULL AFTER `komoditas`,
  ADD COLUMN `kabupaten_id` INT UNSIGNED NULL AFTER `tanggal_ditemukan`,
  ADD COLUMN `kecamatan_id` INT UNSIGNED NULL AFTER `kabupaten_id`,
  ADD COLUMN `desa_id` INT UNSIGNED NULL AFTER `kecamatan_id`,
  ADD COLUMN `alamat_lokasi` VARCHAR(300) NULL AFTER `desa_id`,
  ADD COLUMN `latitude` DECIMAL(10,7) NULL AFTER `alamat_lokasi`,
  ADD COLUMN `longitude` DECIMAL(10,7) NULL AFTER `latitude`,
  ADD COLUMN `bagian_terserang` VARCHAR(150) NULL AFTER `longitude`,
  ADD COLUMN `pola_gejala` VARCHAR(300) NULL AFTER `bagian_terserang`,
  ADD COLUMN `estimasi_terdampak` DECIMAL(12,2) NULL AFTER `pola_gejala`,
  ADD COLUMN `satuan_terdampak` VARCHAR(30) NULL AFTER `estimasi_terdampak`,
  ADD COLUMN `tingkat_keyakinan` ENUM('Rendah','Sedang','Tinggi') NULL AFTER `satuan_terdampak`,
  ADD COLUMN `sumber_identifikasi` VARCHAR(255) NULL AFTER `tingkat_keyakinan`,
  ADD COLUMN `submitted_at` DATETIME NULL AFTER `sumber_identifikasi`,
  ADD KEY `idx_usulan_opt_submitted` (`submitted_at`),
  ADD CONSTRAINT `ck_usulan_opt_estimasi`
    CHECK (`estimasi_terdampak` IS NULL OR `estimasi_terdampak` >= 0),
  ADD CONSTRAINT `ck_usulan_opt_lat`
    CHECK (`latitude` IS NULL OR (`latitude` >= -90 AND `latitude` <= 90)),
  ADD CONSTRAINT `ck_usulan_opt_lng`
    CHECK (`longitude` IS NULL OR (`longitude` >= -180 AND `longitude` <= 180)),
  ADD CONSTRAINT `fk_usulan_opt_kabupaten`
    FOREIGN KEY (`kabupaten_id`) REFERENCES `master_kabupaten` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usulan_opt_kecamatan`
    FOREIGN KEY (`kecamatan_id`) REFERENCES `master_kecamatan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usulan_opt_desa`
    FOREIGN KEY (`desa_id`) REFERENCES `master_desa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE `usulan_opt_photos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usulan_opt_id` BIGINT UNSIGNED NOT NULL,
  `file_path` VARCHAR(300) NOT NULL,
  `mime_type` VARCHAR(100) NULL,
  `size_bytes` INT UNSIGNED NULL,
  `checksum` CHAR(64) NULL,
  `caption` VARCHAR(200) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uop_usulan_created` (`usulan_opt_id`, `created_at`),
  KEY `idx_uop_checksum` (`checksum`),
  CONSTRAINT `fk_uop_usulan` FOREIGN KEY (`usulan_opt_id`) REFERENCES `usulan_opt` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uop_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usulan_opt_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usulan_opt_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(30) NULL,
  `to_status` VARCHAR(30) NOT NULL,
  `changed_by` INT UNSIGNED NULL,
  `catatan` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uosh_usulan_created` (`usulan_opt_id`, `created_at`),
  KEY `idx_uosh_changed_created` (`changed_by`, `created_at`),
  CONSTRAINT `fk_uosh_usulan` FOREIGN KEY (`usulan_opt_id`) REFERENCES `usulan_opt` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uosh_actor` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
