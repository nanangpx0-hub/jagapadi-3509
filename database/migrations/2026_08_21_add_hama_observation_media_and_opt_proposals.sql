-- Runtime root/integrated: pengukuran serangan, video laporan, dan usulan OPT.
-- Append-only; jalankan pada database target setelah backup dan audit schema_migrations.

ALTER TABLE `laporan_hama`
  ADD COLUMN `metode_pengukuran` ENUM('absolut','persentase') NOT NULL DEFAULT 'absolut' AFTER `populasi`,
  ADD COLUMN `persentase_serangan` DECIMAL(5,2) NULL AFTER `luas_serangan`,
  ADD COLUMN `luas_areal_diamati` DECIMAL(10,2) NULL AFTER `persentase_serangan`,
  ADD COLUMN `luas_serangan_estimasi` DECIMAL(10,2) NULL AFTER `luas_areal_diamati`,
  ADD COLUMN `video_url` VARCHAR(300) NULL AFTER `foto_url`,
  ADD CONSTRAINT `ck_lh_persentase_serangan`
    CHECK (`persentase_serangan` IS NULL OR (`persentase_serangan` >= 0 AND `persentase_serangan` <= 100)),
  ADD CONSTRAINT `ck_lh_luas_areal_diamati`
    CHECK (`luas_areal_diamati` IS NULL OR `luas_areal_diamati` >= 0),
  ADD CONSTRAINT `ck_lh_luas_estimasi`
    CHECK (`luas_serangan_estimasi` IS NULL OR `luas_serangan_estimasi` >= 0);

CREATE TABLE `usulan_opt` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `nama_nasional` VARCHAR(150) NULL,
  `nama_lokal` VARCHAR(200) NOT NULL,
  `jenis` ENUM('hama','penyakit','gulma') NOT NULL DEFAULT 'hama',
  `komoditas` VARCHAR(150) NULL,
  `ciri_ciri` TEXT NULL,
  `wilayah` VARCHAR(255) NULL,
  `foto_url` VARCHAR(300) NULL,
  `status` ENUM('Menunggu Review','Disetujui','Digabungkan','Ditolak') NOT NULL DEFAULT 'Menunggu Review',
  `master_opt_id` INT UNSIGNED NULL,
  `catatan_review` TEXT NULL,
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usulan_opt_user_status` (`user_id`, `status`),
  KEY `idx_usulan_opt_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_usulan_opt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_usulan_opt_master` FOREIGN KEY (`master_opt_id`) REFERENCES `master_opt` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_usulan_opt_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `laporan_hama`
  ADD COLUMN `usulan_opt_id` BIGINT UNSIGNED NULL AFTER `master_opt_id`,
  ADD KEY `idx_lh_usulan_opt` (`usulan_opt_id`),
  ADD CONSTRAINT `fk_lh_usulan_opt` FOREIGN KEY (`usulan_opt_id`) REFERENCES `usulan_opt` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;
