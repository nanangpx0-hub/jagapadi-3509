-- JAGAPADI maintenance script
-- File: database/maintenance/fix_nama_kota_mfd_2025_1.sql
--
-- Tujuan:
--   Memperbaiki nama kab/kota yang tertukar di master_kabupaten berdasarkan
--   hasil compare MFD Jawa Timur.
--
-- Sumber validasi:
--   SIG BPS Kode Relasi BPS-Kemendagri
--   Periode: 2025_1.2025
--
-- Target perubahan:
--   kode_kabupaten 3574 -> KOTA PROBOLINGGO
--   kode_kabupaten 3575 -> KOTA PASURUAN
--   kode_kabupaten 3578 -> KOTA SURABAYA
--   kode_kabupaten 3579 -> KOTA BATU
--
-- Catatan penting:
--   Script ini hanya menyamakan nama kab/kota dengan MFD BPS resmi.
--   Script ini tidak mengubah struktur wilayah.
--   Script ini tidak mengubah id.
--   Script ini tidak mengubah kode_kabupaten.
--   Script ini tidak menyentuh master_kecamatan atau master_desa.
--   Jalankan backup database sebelum eksekusi di environment penting.


-- =========================================================
-- 1. SELECT BEFORE
-- =========================================================

SELECT
    mk.id,
    mk.kode_kabupaten,
    mk.nama_kabupaten AS nama_sekarang,
    target.nama_mfd_2025_1 AS nama_seharusnya,
    mk.deleted_at,
    CASE
        WHEN UPPER(TRIM(mk.nama_kabupaten)) = target.nama_mfd_2025_1 THEN 'MATCH'
        ELSE 'MISMATCH'
    END AS status_validasi
FROM master_kabupaten mk
JOIN (
    SELECT '3574' AS kode_kabupaten, 'KOTA PROBOLINGGO' AS nama_mfd_2025_1
    UNION ALL SELECT '3575', 'KOTA PASURUAN'
    UNION ALL SELECT '3578', 'KOTA SURABAYA'
    UNION ALL SELECT '3579', 'KOTA BATU'
) target ON target.kode_kabupaten = mk.kode_kabupaten
ORDER BY mk.kode_kabupaten;


-- =========================================================
-- 2. BACKUP KHUSUS 4 RECORD TARGET
-- Backup mempertahankan nilai pertama. Jika script dijalankan ulang,
-- INSERT IGNORE tidak menimpa backup awal.
-- =========================================================

CREATE TABLE IF NOT EXISTS backup_master_kabupaten_fix_nama_kota_mfd_2025_1 LIKE master_kabupaten;

INSERT IGNORE INTO backup_master_kabupaten_fix_nama_kota_mfd_2025_1
SELECT mk.*
FROM master_kabupaten mk
WHERE mk.kode_kabupaten IN ('3574', '3575', '3578', '3579');

SELECT
    id,
    kode_kabupaten,
    nama_kabupaten,
    deleted_at
FROM backup_master_kabupaten_fix_nama_kota_mfd_2025_1
WHERE kode_kabupaten IN ('3574', '3575', '3578', '3579')
ORDER BY kode_kabupaten;


-- =========================================================
-- 3. UPDATE TERARAH
-- Hanya menyentuh master_kabupaten untuk 4 kode target.
-- Tidak mengubah id atau kode_kabupaten.
-- =========================================================

START TRANSACTION;

UPDATE master_kabupaten
SET
    nama_kabupaten = CASE kode_kabupaten
        WHEN '3574' THEN 'KOTA PROBOLINGGO'
        WHEN '3575' THEN 'KOTA PASURUAN'
        WHEN '3578' THEN 'KOTA SURABAYA'
        WHEN '3579' THEN 'KOTA BATU'
    END,
    updated_at = CURRENT_TIMESTAMP
WHERE kode_kabupaten IN ('3574', '3575', '3578', '3579')
  AND deleted_at IS NULL
  AND UPPER(TRIM(nama_kabupaten)) <> CASE kode_kabupaten
        WHEN '3574' THEN 'KOTA PROBOLINGGO'
        WHEN '3575' THEN 'KOTA PASURUAN'
        WHEN '3578' THEN 'KOTA SURABAYA'
        WHEN '3579' THEN 'KOTA BATU'
    END;

SELECT ROW_COUNT() AS rows_changed;


-- =========================================================
-- 4. SELECT AFTER
-- Review hasil ini sebelum COMMIT.
-- =========================================================

SELECT
    mk.id,
    mk.kode_kabupaten,
    mk.nama_kabupaten AS nama_setelah_update,
    target.nama_mfd_2025_1 AS nama_seharusnya,
    mk.deleted_at,
    CASE
        WHEN UPPER(TRIM(mk.nama_kabupaten)) = target.nama_mfd_2025_1 THEN 'MATCH'
        ELSE 'MISMATCH'
    END AS status_validasi
FROM master_kabupaten mk
JOIN (
    SELECT '3574' AS kode_kabupaten, 'KOTA PROBOLINGGO' AS nama_mfd_2025_1
    UNION ALL SELECT '3575', 'KOTA PASURUAN'
    UNION ALL SELECT '3578', 'KOTA SURABAYA'
    UNION ALL SELECT '3579', 'KOTA BATU'
) target ON target.kode_kabupaten = mk.kode_kabupaten
ORDER BY mk.kode_kabupaten;

-- Jika hasil SELECT AFTER sudah benar, jalankan:
-- COMMIT;
--
-- Jika hasil SELECT AFTER tidak sesuai, jalankan:
-- ROLLBACK;


-- =========================================================
-- 5. VALIDASI TAMBAHAN SETELAH COMMIT
-- Jalankan setelah transaksi di-commit untuk memastikan hanya nama
-- master_kabupaten yang berubah.
-- =========================================================

-- SELECT
--     COUNT(*) AS target_rows
-- FROM master_kabupaten
-- WHERE kode_kabupaten IN ('3574', '3575', '3578', '3579');
--
-- SELECT
--     mk.id,
--     mk.kode_kabupaten,
--     mk.nama_kabupaten,
--     mk.updated_at
-- FROM master_kabupaten mk
-- WHERE mk.kode_kabupaten IN ('3574', '3575', '3578', '3579')
-- ORDER BY mk.kode_kabupaten;


-- =========================================================
-- 6. ROLLBACK DARI BACKUP
-- Jalankan blok ini hanya jika perlu mengembalikan 4 record target
-- ke kondisi sebelum script maintenance ini.
-- =========================================================

-- START TRANSACTION;
--
-- UPDATE master_kabupaten mk
-- JOIN backup_master_kabupaten_fix_nama_kota_mfd_2025_1 b
--   ON b.id = mk.id
--  AND b.kode_kabupaten = mk.kode_kabupaten
-- SET
--     mk.nama_kabupaten = b.nama_kabupaten,
--     mk.updated_at = b.updated_at,
--     mk.deleted_at = b.deleted_at,
--     mk.created_by = b.created_by,
--     mk.updated_by = b.updated_by,
--     mk.deleted_by = b.deleted_by
-- WHERE mk.kode_kabupaten IN ('3574', '3575', '3578', '3579');
--
-- SELECT ROW_COUNT() AS rows_rolled_back;
--
-- SELECT
--     mk.id,
--     mk.kode_kabupaten,
--     mk.nama_kabupaten AS nama_setelah_rollback,
--     b.nama_kabupaten AS nama_backup,
--     mk.deleted_at
-- FROM master_kabupaten mk
-- JOIN backup_master_kabupaten_fix_nama_kota_mfd_2025_1 b
--   ON b.id = mk.id
--  AND b.kode_kabupaten = mk.kode_kabupaten
-- WHERE mk.kode_kabupaten IN ('3574', '3575', '3578', '3579')
-- ORDER BY mk.kode_kabupaten;
--
-- COMMIT;
