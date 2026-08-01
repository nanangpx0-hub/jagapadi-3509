-- JAGAPADI maintenance script
-- File: database/maintenance/import_missing_mfd_jatim_2025_1.sql
--
-- Tujuan:
--   Menambahkan master wilayah Jawa Timur dari MFD BPS 2025_1.2025
--   hanya untuk kode BPS yang belum ada di JAGAPADI.
--
-- Sumber data:
--   data/mfd/mfd_jawa_timur_2025_1.csv
--   SIG BPS Kode Relasi BPS-Kemendagri
--   Periode: 2025_1.2025
--
-- Ringkasan hasil compare saat script ini dibuat:
--   - Kabupaten/kota only_in_mfd : 0
--   - Kecamatan only_in_mfd     : 83
--   - Desa only_in_mfd          : 1.342
--   - Jember saat ini           : 246 desa aktif
--   - Jember target MFD         : 248 desa aktif
--   - Desa Jember hilang        :
--       3509100008 JATIMULYO, parent 3509100 JENGGAWAH
--       3509730008 BANJAR SENGON, parent 3509730 PATRANG
--
-- Batasan:
--   Script ini INSERT-only untuk data yang kodenya belum ada.
--   Script ini tidak UPDATE nama yang sudah ada.
--   Script ini tidak mengubah kode yang sudah ada.
--   Skema master_*: tidak ada kolom deleted_at (tidak ada soft-delete).
--   Untuk kode yang sudah ada, script ini tidak membuat duplikat.
--   Review SELECT BEFORE dan SELECT AFTER sebelum COMMIT.
--
-- Cara menjalankan manual:
--   1. Pastikan MySQL mengizinkan LOCAL INFILE:
--        SHOW VARIABLES LIKE 'local_infile';
--      Jika masih OFF:
--        SET GLOBAL local_infile = 1;
--
--   2. Keluar dari MySQL, lalu masuk ulang dari PowerShell:
--        mysql --local-infile=1 -u root jagapadi_local
--
--   3. Jalankan script:
--        SOURCE C:/laragon/www/jagapadi-3509/database/maintenance/import_missing_mfd_jatim_2025_1.sql;
--
--   4. Review semua hasil SELECT AFTER.
--
--   5. Jika validasi benar, jalankan manual:
--        COMMIT;
--
--   6. Jika tidak benar, jalankan manual:
--        ROLLBACK;
--
-- Catatan path CSV:
--   Jika path lokal berbeda, ubah path pada perintah LOAD DATA LOCAL INFILE.


-- =========================================================
-- 0. PARAMETER DAN STAGING MFD
-- =========================================================

SET @maintenance_run_id = 'import_missing_mfd_jatim_2025_1';

-- Staging menggunakan GROUP BY pada ekspresi TRIM(); nonaktifkan ONLY_FULL_GROUP_BY
-- untuk session ini agar pembuatan temporary table tidak gagal.
SET SESSION sql_mode = REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', '');

DROP TEMPORARY TABLE IF EXISTS tmp_mfd_jatim_2025_1_raw;
DROP TEMPORARY TABLE IF EXISTS tmp_mfd_jatim_2025_1_kabupaten;
DROP TEMPORARY TABLE IF EXISTS tmp_mfd_jatim_2025_1_kecamatan;
DROP TEMPORARY TABLE IF EXISTS tmp_mfd_jatim_2025_1_desa;

CREATE TEMPORARY TABLE tmp_mfd_jatim_2025_1_raw (
    periode VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    kode_provinsi_bps VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    nama_provinsi VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    kode_kabupaten_bps VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    nama_kabupaten VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    kode_kecamatan_bps VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    nama_kecamatan VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    kode_desa_bps VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    nama_desa_bps VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    kode_dagri VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
    nama_desa_dagri VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
    sumber VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
    scraped_at VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

LOAD DATA LOCAL INFILE 'C:/laragon/www/jagapadi-3509/data/mfd/mfd_jawa_timur_2025_1.csv'
INTO TABLE tmp_mfd_jatim_2025_1_raw
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ',' ENCLOSED BY '"' ESCAPED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(
    periode,
    kode_provinsi_bps,
    nama_provinsi,
    kode_kabupaten_bps,
    nama_kabupaten,
    kode_kecamatan_bps,
    nama_kecamatan,
    kode_desa_bps,
    nama_desa_bps,
    kode_dagri,
    nama_desa_dagri,
    sumber,
    scraped_at
);

CREATE TEMPORARY TABLE tmp_mfd_jatim_2025_1_kabupaten
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
AS
SELECT
    TRIM(kode_kabupaten_bps) COLLATE utf8mb4_0900_ai_ci AS kode,
    MAX(TRIM(nama_kabupaten)) COLLATE utf8mb4_0900_ai_ci AS nama_kabupaten
FROM tmp_mfd_jatim_2025_1_raw
WHERE TRIM(periode) = '2025_1.2025'
  AND TRIM(kode_provinsi_bps) = '35'
  AND TRIM(kode_kabupaten_bps) REGEXP '^[0-9]{4}$'
GROUP BY TRIM(kode_kabupaten_bps);

ALTER TABLE tmp_mfd_jatim_2025_1_kabupaten
    MODIFY kode VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    MODIFY nama_kabupaten VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    ADD PRIMARY KEY (kode);

CREATE TEMPORARY TABLE tmp_mfd_jatim_2025_1_kecamatan
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
AS
SELECT
    TRIM(kode_kecamatan_bps) COLLATE utf8mb4_0900_ai_ci AS kode,
    MAX(TRIM(nama_kecamatan)) COLLATE utf8mb4_0900_ai_ci AS nama_kecamatan,
    LEFT(TRIM(kode_kecamatan_bps), 4) COLLATE utf8mb4_0900_ai_ci AS kode_kabupaten
FROM tmp_mfd_jatim_2025_1_raw
WHERE TRIM(periode) = '2025_1.2025'
  AND TRIM(kode_provinsi_bps) = '35'
  AND TRIM(kode_kecamatan_bps) REGEXP '^[0-9]{7}$'
  AND TRIM(kode_kabupaten_bps) = LEFT(TRIM(kode_kecamatan_bps), 4)
GROUP BY
    TRIM(kode_kecamatan_bps),
    LEFT(TRIM(kode_kecamatan_bps), 4);

ALTER TABLE tmp_mfd_jatim_2025_1_kecamatan
    MODIFY kode VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    MODIFY nama_kecamatan VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    MODIFY kode_kabupaten VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    ADD PRIMARY KEY (kode),
    ADD INDEX idx_tmp_mfd_kec_kabupaten (kode_kabupaten);

CREATE TEMPORARY TABLE tmp_mfd_jatim_2025_1_desa
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
AS
SELECT
    TRIM(kode_desa_bps) COLLATE utf8mb4_0900_ai_ci AS kode,
    MAX(TRIM(nama_desa_bps)) COLLATE utf8mb4_0900_ai_ci AS nama_desa,
    LEFT(TRIM(kode_desa_bps), 7) COLLATE utf8mb4_0900_ai_ci AS kode_kecamatan
FROM tmp_mfd_jatim_2025_1_raw
WHERE TRIM(periode) = '2025_1.2025'
  AND TRIM(kode_provinsi_bps) = '35'
  AND TRIM(kode_desa_bps) REGEXP '^[0-9]{10}$'
  AND TRIM(kode_kecamatan_bps) = LEFT(TRIM(kode_desa_bps), 7)
GROUP BY
    TRIM(kode_desa_bps),
    LEFT(TRIM(kode_desa_bps), 7);

ALTER TABLE tmp_mfd_jatim_2025_1_desa
    MODIFY kode VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    MODIFY nama_desa VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    MODIFY kode_kecamatan VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    ADD PRIMARY KEY (kode),
    ADD INDEX idx_tmp_mfd_desa_kecamatan (kode_kecamatan);


-- =========================================================
-- 1. SELECT BEFORE
-- Review bagian ini sebelum lanjut COMMIT setelah insert.
-- =========================================================

SELECT
    'target_mfd' AS kategori,
    (SELECT COUNT(*) FROM tmp_mfd_jatim_2025_1_kabupaten) AS jumlah_kabupaten_kota_mfd,
    (SELECT COUNT(*) FROM tmp_mfd_jatim_2025_1_kecamatan) AS jumlah_kecamatan_mfd,
    (SELECT COUNT(*) FROM tmp_mfd_jatim_2025_1_desa) AS jumlah_desa_mfd;

SELECT
    'kabupaten' AS tipe,
    COUNT(*) AS target_mfd,
    SUM(CASE WHEN EXISTS (
        SELECT 1
        FROM master_kabupaten mk
        WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS kode_sudah_ada,
    SUM(CASE WHEN EXISTS (
        SELECT 1
        FROM master_kabupaten mk
        WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS aktif_sudah_ada,
    SUM(CASE WHEN NOT EXISTS (
        SELECT 1
        FROM master_kabupaten mk
        WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS kandidat_insert
FROM tmp_mfd_jatim_2025_1_kabupaten t
UNION ALL
SELECT
    'kecamatan' AS tipe,
    COUNT(*) AS target_mfd,
    SUM(CASE WHEN EXISTS (
        SELECT 1
        FROM master_kecamatan mk
        WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS kode_sudah_ada,
    SUM(CASE WHEN EXISTS (
        SELECT 1
        FROM master_kecamatan mk
        WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS aktif_sudah_ada,
    SUM(CASE WHEN NOT EXISTS (
        SELECT 1
        FROM master_kecamatan mk
        WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS kandidat_insert
FROM tmp_mfd_jatim_2025_1_kecamatan t
UNION ALL
SELECT
    'desa' AS tipe,
    COUNT(*) AS target_mfd,
    SUM(CASE WHEN EXISTS (
        SELECT 1
        FROM master_desa md
        WHERE md.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS kode_sudah_ada,
    SUM(CASE WHEN EXISTS (
        SELECT 1
        FROM master_desa md
        WHERE md.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS aktif_sudah_ada,
    SUM(CASE WHEN NOT EXISTS (
        SELECT 1
        FROM master_desa md
        WHERE md.kode COLLATE utf8mb4_0900_ai_ci = t.kode
    ) THEN 1 ELSE 0 END) AS kandidat_insert
FROM tmp_mfd_jatim_2025_1_desa t;

SELECT
    t.kode AS kode_desa,
    t.nama_desa,
    t.kode_kecamatan AS kode_kecamatan_parent_mfd,
    k.id AS kecamatan_id_jagapadi,
    k.nama_kecamatan AS nama_kecamatan_jagapadi,
    CASE
        WHEN d.id IS NOT NULL THEN 'SKIP_KODE_SUDAH_ADA'
        WHEN k.id IS NULL THEN 'BLOCKED_PARENT_KECAMATAN_TIDAK_AKTIF'
        ELSE 'AKAN_INSERT'
    END AS status_rencana
FROM tmp_mfd_jatim_2025_1_desa t
LEFT JOIN master_desa d
  ON d.kode COLLATE utf8mb4_0900_ai_ci = t.kode
LEFT JOIN master_kecamatan k
  ON k.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 7)
WHERE t.kode IN ('3509100008', '3509730008')
ORDER BY t.kode;

SELECT
    t.kode AS kode_kabupaten,
    t.nama_kabupaten
FROM tmp_mfd_jatim_2025_1_kabupaten t
WHERE NOT EXISTS (
    SELECT 1
    FROM master_kabupaten mk
    WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
)
ORDER BY t.kode;

SELECT
    t.kode AS kode_kecamatan,
    t.nama_kecamatan,
    t.kode_kabupaten,
    kab.id AS kabupaten_id_jagapadi,
    kab.nama_kabupaten AS nama_kabupaten_jagapadi
FROM tmp_mfd_jatim_2025_1_kecamatan t
LEFT JOIN master_kecamatan k
  ON k.kode COLLATE utf8mb4_0900_ai_ci = t.kode
LEFT JOIN master_kabupaten kab
  ON kab.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 4)
WHERE k.id IS NULL
ORDER BY t.kode;

SELECT
    t.kode AS kode_desa,
    t.nama_desa,
    t.kode_kecamatan,
    kec.id AS kecamatan_id_jagapadi,
    kec.nama_kecamatan AS nama_kecamatan_jagapadi
FROM tmp_mfd_jatim_2025_1_desa t
LEFT JOIN master_desa d
  ON d.kode COLLATE utf8mb4_0900_ai_ci = t.kode
LEFT JOIN master_kecamatan kec
  ON kec.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 7)
WHERE d.id IS NULL
ORDER BY t.kode;

SELECT
    t.kode AS kode_kecamatan,
    t.nama_kecamatan,
    t.kode_kabupaten
FROM tmp_mfd_jatim_2025_1_kecamatan t
LEFT JOIN master_kecamatan k
  ON k.kode COLLATE utf8mb4_0900_ai_ci = t.kode
LEFT JOIN master_kabupaten kab
  ON kab.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 4)
WHERE k.id IS NULL
  AND kab.id IS NULL
ORDER BY t.kode;

SELECT
    t.kode AS kode_desa,
    t.nama_desa,
    t.kode_kecamatan
FROM tmp_mfd_jatim_2025_1_desa t
LEFT JOIN master_desa d
  ON d.kode COLLATE utf8mb4_0900_ai_ci = t.kode
LEFT JOIN master_kecamatan kec
  ON kec.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 7)
WHERE d.id IS NULL
  AND kec.id IS NULL
ORDER BY t.kode;


-- =========================================================
-- 2. ROLLBACK MARKER
-- Tidak ada data lama yang diubah, jadi backup record lama tidak diperlukan.
-- Tabel ini menyimpan daftar kode yang benar-benar diinsert oleh script
-- agar rollback khusus bisa menghapus hanya data hasil script ini.
-- =========================================================

CREATE TABLE IF NOT EXISTS backup_import_missing_mfd_jatim_2025_1 (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    tipe VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    kode VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    nama VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    kode_parent VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL,
    record_id BIGINT UNSIGNED NULL,
    source_file VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'data/mfd/mfd_jawa_timur_2025_1.csv',
    source_periode VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '2025_1.2025',
    inserted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_backup_import_missing_mfd_jatim_2025_1 (run_id, tipe, kode),
    KEY idx_backup_import_missing_mfd_jatim_2025_1_tipe_kode (tipe, kode),
    KEY idx_backup_import_missing_mfd_jatim_2025_1_record (tipe, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE backup_import_missing_mfd_jatim_2025_1
CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;


-- =========================================================
-- 3. INSERT DATA MISSING
-- Semua INSERT memakai NOT EXISTS berdasarkan kode, aman dijalankan ulang.
-- Jangan COMMIT sebelum SELECT AFTER divalidasi.
-- =========================================================

START TRANSACTION;

-- 3.1 Kabupaten/kota: insert hanya jika kode belum ada.
INSERT INTO backup_import_missing_mfd_jatim_2025_1
    (run_id, tipe, kode, nama, kode_parent)
SELECT
    @maintenance_run_id,
    'kabupaten',
    t.kode,
    t.nama_kabupaten,
    '35'
FROM tmp_mfd_jatim_2025_1_kabupaten t
WHERE NOT EXISTS (
    SELECT 1
    FROM master_kabupaten mk
    WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
)
  AND NOT EXISTS (
    SELECT 1
    FROM backup_import_missing_mfd_jatim_2025_1 b
    WHERE b.run_id = @maintenance_run_id
      AND b.tipe = 'kabupaten'
      AND b.kode = t.kode
);

INSERT INTO master_kabupaten
    (kode, nama_kabupaten)
SELECT
    t.kode,
    t.nama_kabupaten
FROM tmp_mfd_jatim_2025_1_kabupaten t
JOIN backup_import_missing_mfd_jatim_2025_1 b
  ON b.run_id = @maintenance_run_id
 AND b.tipe = 'kabupaten'
 AND b.kode = t.kode
WHERE NOT EXISTS (
    SELECT 1
    FROM master_kabupaten mk
    WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
);

SELECT ROW_COUNT() AS inserted_kabupaten;

UPDATE backup_import_missing_mfd_jatim_2025_1 b
JOIN master_kabupaten mk
  ON mk.kode COLLATE utf8mb4_0900_ai_ci = b.kode
SET b.record_id = mk.id
WHERE b.run_id = @maintenance_run_id
  AND b.tipe = 'kabupaten'
  AND b.record_id IS NULL;

-- 3.2 Kecamatan: parent kabupaten dicari dari LEFT(kode, 4).
INSERT INTO backup_import_missing_mfd_jatim_2025_1
    (run_id, tipe, kode, nama, kode_parent)
SELECT
    @maintenance_run_id,
    'kecamatan',
    t.kode,
    t.nama_kecamatan,
    LEFT(t.kode, 4)
FROM tmp_mfd_jatim_2025_1_kecamatan t
JOIN master_kabupaten kab
  ON kab.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 4)
WHERE NOT EXISTS (
    SELECT 1
    FROM master_kecamatan mk
    WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
)
  AND NOT EXISTS (
    SELECT 1
    FROM backup_import_missing_mfd_jatim_2025_1 b
    WHERE b.run_id = @maintenance_run_id
      AND b.tipe = 'kecamatan'
      AND b.kode = t.kode
);

INSERT INTO master_kecamatan
    (kabupaten_id, nama_kecamatan, kode)
SELECT
    kab.id,
    t.nama_kecamatan,
    t.kode
FROM tmp_mfd_jatim_2025_1_kecamatan t
JOIN master_kabupaten kab
  ON kab.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 4)
JOIN backup_import_missing_mfd_jatim_2025_1 b
  ON b.run_id = @maintenance_run_id
 AND b.tipe = 'kecamatan'
 AND b.kode = t.kode
WHERE NOT EXISTS (
    SELECT 1
    FROM master_kecamatan mk
    WHERE mk.kode COLLATE utf8mb4_0900_ai_ci = t.kode
);

SELECT ROW_COUNT() AS inserted_kecamatan;

UPDATE backup_import_missing_mfd_jatim_2025_1 b
JOIN master_kecamatan mk
  ON mk.kode COLLATE utf8mb4_0900_ai_ci = b.kode
SET b.record_id = mk.id
WHERE b.run_id = @maintenance_run_id
  AND b.tipe = 'kecamatan'
  AND b.record_id IS NULL;

-- 3.3 Desa: parent kecamatan dicari dari LEFT(kode, 7).
INSERT INTO backup_import_missing_mfd_jatim_2025_1
    (run_id, tipe, kode, nama, kode_parent)
SELECT
    @maintenance_run_id,
    'desa',
    t.kode,
    t.nama_desa,
    LEFT(t.kode, 7)
FROM tmp_mfd_jatim_2025_1_desa t
JOIN master_kecamatan kec
  ON kec.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 7)
WHERE NOT EXISTS (
    SELECT 1
    FROM master_desa md
    WHERE md.kode COLLATE utf8mb4_0900_ai_ci = t.kode
)
  AND NOT EXISTS (
    SELECT 1
    FROM backup_import_missing_mfd_jatim_2025_1 b
    WHERE b.run_id = @maintenance_run_id
      AND b.tipe = 'desa'
      AND b.kode = t.kode
);

INSERT INTO master_desa
    (kecamatan_id, nama_desa, kode)
SELECT
    kec.id,
    t.nama_desa,
    t.kode
FROM tmp_mfd_jatim_2025_1_desa t
JOIN master_kecamatan kec
  ON kec.kode COLLATE utf8mb4_0900_ai_ci = LEFT(t.kode, 7)
JOIN backup_import_missing_mfd_jatim_2025_1 b
  ON b.run_id = @maintenance_run_id
 AND b.tipe = 'desa'
 AND b.kode = t.kode
WHERE NOT EXISTS (
    SELECT 1
    FROM master_desa md
    WHERE md.kode COLLATE utf8mb4_0900_ai_ci = t.kode
);

SELECT ROW_COUNT() AS inserted_desa;

UPDATE backup_import_missing_mfd_jatim_2025_1 b
JOIN master_desa md
  ON md.kode COLLATE utf8mb4_0900_ai_ci = b.kode
SET b.record_id = md.id
WHERE b.run_id = @maintenance_run_id
  AND b.tipe = 'desa'
  AND b.record_id IS NULL;


-- =========================================================
-- 4. SELECT AFTER / VALIDASI SEBELUM COMMIT
-- Hasil relasi salah harus empty set.
-- Jumlah desa Jember harus 248.
-- =========================================================

SELECT
    tipe,
    COUNT(*) AS jumlah_marker_insert,
    SUM(CASE WHEN record_id IS NULL THEN 1 ELSE 0 END) AS marker_tanpa_record_id
FROM backup_import_missing_mfd_jatim_2025_1
WHERE run_id = @maintenance_run_id
GROUP BY tipe
ORDER BY tipe;

SELECT
    'kabupaten_kota' AS metrik,
    COUNT(*) AS jumlah
FROM master_kabupaten
UNION ALL
SELECT
    'kecamatan' AS metrik,
    COUNT(*) AS jumlah
FROM master_kecamatan
UNION ALL
SELECT
    'desa' AS metrik,
    COUNT(*) AS jumlah
FROM master_desa;

SELECT
    kab.kode,
    kab.nama_kabupaten,
    COUNT(DISTINCT k.id) AS jumlah_kecamatan,
    COUNT(DISTINCT d.id) AS jumlah_desa,
    CASE
        WHEN COUNT(DISTINCT d.id) = 248 THEN 'OK'
        ELSE 'CHECK'
    END AS status_jumlah_desa_jember
FROM master_kabupaten kab
LEFT JOIN master_kecamatan k
  ON k.kabupaten_id = kab.id
LEFT JOIN master_desa d
  ON d.kecamatan_id = k.id
WHERE kab.kode = '3509'
GROUP BY
    kab.kode,
    kab.nama_kabupaten;

SELECT
    d.id,
    d.kode,
    d.nama_desa,
    k.kode AS kode_kecamatan,
    k.nama_kecamatan,
    kab.kode AS kode_kabupaten,
    kab.nama_kabupaten
FROM master_desa d
JOIN master_kecamatan k
  ON k.id = d.kecamatan_id
JOIN master_kabupaten kab
  ON kab.id = k.kabupaten_id
WHERE d.kode IN ('3509100008', '3509730008')
ORDER BY d.kode;

-- Harus empty set: kecamatan yang parent kabupatennya salah/tidak ada.
SELECT
    k.id,
    k.kode AS kode_kecamatan,
    k.nama_kecamatan,
    k.kabupaten_id,
    kab.kode AS kode_kabupaten,
    kab.nama_kabupaten,
    CASE
        WHEN kab.id IS NULL THEN 'PARENT_KABUPATEN_TIDAK_ADA'
        WHEN LEFT(k.kode, 4) <> kab.kode THEN 'PARENT_KABUPATEN_SALAH'
        ELSE 'OK'
    END AS status_relasi
FROM master_kecamatan k
LEFT JOIN master_kabupaten kab
  ON kab.id = k.kabupaten_id
WHERE
    kab.id IS NULL
    OR LEFT(k.kode, 4) <> kab.kode
ORDER BY k.kode;

-- Harus empty set: desa yang parent kecamatannya salah/tidak ada.
SELECT
    d.id,
    d.kode AS kode_desa,
    d.nama_desa,
    d.kecamatan_id,
    k.kode AS kode_kecamatan,
    k.nama_kecamatan,
    kab.kode AS kode_kabupaten,
    kab.nama_kabupaten,
    CASE
        WHEN k.id IS NULL THEN 'PARENT_KECAMATAN_TIDAK_ADA'
        WHEN LEFT(d.kode, 7) <> k.kode THEN 'PARENT_KECAMATAN_SALAH'
        ELSE 'OK'
    END AS status_relasi
FROM master_desa d
LEFT JOIN master_kecamatan k
  ON k.id = d.kecamatan_id
LEFT JOIN master_kabupaten kab
  ON kab.id = k.kabupaten_id
WHERE
    k.id IS NULL
    OR LEFT(d.kode, 7) <> k.kode
ORDER BY d.kode;

-- Jika semua SELECT AFTER benar, jalankan manual:
-- COMMIT;
--
-- Jika hasil tidak sesuai, jalankan manual:
-- ROLLBACK;


-- =========================================================
-- 5. ROLLBACK KHUSUS DATA INSERT SCRIPT
-- Jalankan hanya jika transaksi sudah pernah COMMIT dan perlu
-- menghapus kembali data yang diinsert oleh run_id ini.
-- Review SELECT rollback dulu. Jalankan dalam sesi baru jika perlu.
-- =========================================================

-- SET @maintenance_run_id = 'import_missing_mfd_jatim_2025_1';
--
-- SELECT
--     b.tipe,
--     b.kode,
--     b.nama,
--     b.kode_parent,
--     b.record_id,
--     b.inserted_at
-- FROM backup_import_missing_mfd_jatim_2025_1 b
-- WHERE b.run_id = @maintenance_run_id
-- ORDER BY b.tipe, b.kode;
--
-- START TRANSACTION;
--
-- DELETE d
-- FROM master_desa d
-- JOIN backup_import_missing_mfd_jatim_2025_1 b
--   ON b.record_id = d.id
--  AND b.kode = d.kode
-- WHERE b.run_id = @maintenance_run_id
--   AND b.tipe = 'desa';
--
-- SELECT ROW_COUNT() AS rollback_deleted_desa;
--
-- DELETE k
-- FROM master_kecamatan k
-- JOIN backup_import_missing_mfd_jatim_2025_1 b
--   ON b.record_id = k.id
--  AND b.kode = k.kode
-- WHERE b.run_id = @maintenance_run_id
--   AND b.tipe = 'kecamatan';
--
-- SELECT ROW_COUNT() AS rollback_deleted_kecamatan;
--
-- DELETE kab
-- FROM master_kabupaten kab
-- JOIN backup_import_missing_mfd_jatim_2025_1 b
--   ON b.record_id = kab.id
--  AND b.kode = kab.kode
-- WHERE b.run_id = @maintenance_run_id
--   AND b.tipe = 'kabupaten';
--
-- SELECT ROW_COUNT() AS rollback_deleted_kabupaten;
--
-- DELETE FROM backup_import_missing_mfd_jatim_2025_1
-- WHERE run_id = @maintenance_run_id;
--
-- SELECT ROW_COUNT() AS rollback_marker_deleted;
--
-- COMMIT;
