-- Backend v1: penyelarasan kolom media & pengukuran laporan_hama.
--
-- Kolom-kolom ini sebelumnya hanya dibuat oleh migration runtime root
-- (database/migrations/2026_08_21_add_hama_observation_media_and_opt_proposals.sql)
-- sehingga deployment Backend v1 yang baru berpotensi gagal saat endpoint
-- video dan field mode pengukuran persentase dipakai.
--
-- Semua statement idempoten (guard INFORMATION_SCHEMA) sehingga aman
-- dijalankan pada database target mana pun. Append-only; jangan edit file ini
-- setelah tercatat di schema_migrations.

SET @sch := DATABASE();
SET @tbl := 'laporan_hama';

-- metode_pengukuran
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND COLUMN_NAME = 'metode_pengukuran') = 0,
    'ALTER TABLE `laporan_hama` ADD COLUMN `metode_pengukuran` ENUM(''absolut'',''persentase'') NOT NULL DEFAULT ''absolut'' AFTER `populasi`',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- persentase_serangan
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND COLUMN_NAME = 'persentase_serangan') = 0,
    'ALTER TABLE `laporan_hama` ADD COLUMN `persentase_serangan` DECIMAL(5,2) NULL AFTER `luas_serangan`',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- luas_areal_diamati
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND COLUMN_NAME = 'luas_areal_diamati') = 0,
    'ALTER TABLE `laporan_hama` ADD COLUMN `luas_areal_diamati` DECIMAL(10,2) NULL AFTER `persentase_serangan`',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- luas_serangan_estimasi
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND COLUMN_NAME = 'luas_serangan_estimasi') = 0,
    'ALTER TABLE `laporan_hama` ADD COLUMN `luas_serangan_estimasi` DECIMAL(10,2) NULL AFTER `luas_areal_diamati`',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- video_url
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND COLUMN_NAME = 'video_url') = 0,
    'ALTER TABLE `laporan_hama` ADD COLUMN `video_url` VARCHAR(300) NULL AFTER `foto_url`',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_at (recycle bin)
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND COLUMN_NAME = 'deleted_at') = 0,
    'ALTER TABLE `laporan_hama` ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- deleted_by
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND COLUMN_NAME = 'deleted_by') = 0,
    'ALTER TABLE `laporan_hama` ADD COLUMN `deleted_by` INT UNSIGNED NULL DEFAULT NULL AFTER `deleted_at`',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Constraint CHECK (idempoten per nama constraint)
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = @sch AND TABLE_NAME = @tbl
        AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'ck_lh_persentase_serangan') = 0,
    'ALTER TABLE `laporan_hama` ADD CONSTRAINT `ck_lh_persentase_serangan` CHECK (`persentase_serangan` IS NULL OR (`persentase_serangan` >= 0 AND `persentase_serangan` <= 100))',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = @sch AND TABLE_NAME = @tbl
        AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'ck_lh_luas_areal_diamati') = 0,
    'ALTER TABLE `laporan_hama` ADD CONSTRAINT `ck_lh_luas_areal_diamati` CHECK (`luas_areal_diamati` IS NULL OR `luas_areal_diamati` >= 0)',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = @sch AND TABLE_NAME = @tbl
        AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'ck_lh_luas_estimasi') = 0,
    'ALTER TABLE `laporan_hama` ADD CONSTRAINT `ck_lh_luas_estimasi` CHECK (`luas_serangan_estimasi` IS NULL OR `luas_serangan_estimasi` >= 0)',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index deleted_at untuk filter recycle bin
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @sch AND TABLE_NAME = @tbl AND INDEX_NAME = 'idx_lh_deleted_at') = 0,
    'ALTER TABLE `laporan_hama` ADD KEY `idx_lh_deleted_at` (`deleted_at`)',
    'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
