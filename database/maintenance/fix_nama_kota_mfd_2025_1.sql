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
--   kode 3574 -> KOTA PROBOLINGGO
--   kode 3575 -> KOTA PASURUAN
--   kode 3578 -> KOTA SURABAYA
--   kode 3579 -> KOTA BATU
--
-- Catatan penting:
--   Skema master_kabupaten: id, kode, nama_kabupaten, created_at, updated_at.
--   Tidak ada kolom kode_kabupaten atau deleted_at (tidak ada soft-delete).
--   Script ini hanya menyamakan nama kab/kota dengan MFD BPS resmi.
--   Script ini tidak mengubah struktur wilayah.
--   Script ini tidak mengubah id.
--   Script ini tidak mengubah kode.
--   Script ini tidak menyentuh master_kecamatan atau master_desa.
--   Jalankan backup database sebelum eksekusi di environment penting.


-- =========================================================
-- 1. SELECT BEFORE
-- =========================================================

SELECT
    mk.id,
    mk.kode,
    mk.nama_kabupaten AS nama_sekarang,
    target.nama_mfd_2025_1 AS nama_seharusnya,
    CASE
        WHEN UPPER(TRIM(mk.nama_kabupaten)) = target.nama_mfd_2025_1 THEN 'MATCH'
        ELSE 'MISMATCH'
    END AS status_validasi
FROM master_kabupaten mk
JOIN (
    SELECT '3574' AS kode, 'KOTA PROBOLINGGO' AS nama_mfd_2025_1
    UNION ALL SELECT '3575', 'KOTA PASURUAN'
    UNION ALL SELECT '3578', 'KOTA SURABAYA'
    UNION ALL SELECT '3579', 'KOTA BATU'
) target ON target.kode = mk.kode
ORDER BY mk.kode;


-- =========================================================
-- 2. BACKUP KHUSUS 4 RECORD TARGET
-- Backup mempertahankan nilai pertama. Jika script dijalankan ulang,
-- INSERT IGNORE tidak menimpa backup awal.
-- =========================================================

CREATE TABLE IF NOT EXISTS backup_master_kabupaten_fix_nama_kota_mfd_2025_1 LIKE master_kabupaten;

INSERT IGNORE INTO backup_master_kabupaten_fix_nama_kota_mfd_2025_1
SELECT mk.*
FROM master_kabupaten mk
WHERE mk.kode IN ('3574', '3575', '3578', '3579');

SELECT
    id,
    kode,
    nama_kabupaten
FROM backup_master_kabupaten_fix_nama_kota_mfd_2025_1
WHERE kode IN ('3574', '3575', '3578', '3579')
ORDER BY kode;


-- =========================================================
-- 3. UPDATE TERARAH
-- Hanya menyentuh master_kabupaten untuk 4 kode target.
-- Tidak mengubah id atau kode.
-- =========================================================

START TRANSACTION;

UPDATE master_kabupaten
SET
    nama_kabupaten = CASE kode
        WHEN '3574' THEN 'KOTA PROBOLINGGO'
        WHEN '3575' THEN 'KOTA PASURUAN'
        WHEN '3578' THEN 'KOTA SURABAYA'
        WHEN '3579' THEN 'KOTA BATU'
    END,
    updated_at = CURRENT_TIMESTAMP
WHERE kode IN ('3574', '3575', '3578', '3579')
  AND UPPER(TRIM(nama_kabupaten)) <> CASE kode
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
    mk.kode,
    mk.nama_kabupaten AS nama_setelah_update,
    target.nama_mfd_2025_1 AS nama_seharusnya,
    CASE
        WHEN UPPER(TRIM(mk.nama_kabupaten)) = target.nama_mfd_2025_1 THEN 'MATCH'
        ELSE 'MISMATCH'
    END AS status_validasi
FROM master_kabupaten mk
JOIN (
    SELECT '3574' AS kode, 'KOTA PROBOLINGGO' AS nama_mfd_2025_1
    UNION ALL SELECT '3575', 'KOTA PASURUAN'
    UNION ALL SELECT '3578', 'KOTA SURABAYA'
    UNION ALL SELECT '3579', 'KOTA BATU'
) target ON target.kode = mk.kode
ORDER BY mk.kode;

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
-- WHERE kode IN ('3574', '3575', '3578', '3579');
--
-- SELECT
--     mk.id,
--     mk.kode,
--     mk.nama_kabupaten,
--     mk.updated_at
-- FROM master_kabupaten mk
-- WHERE mk.kode IN ('3574', '3575', '3578', '3579')
-- ORDER BY mk.kode;


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
--  AND b.kode = mk.kode
-- SET
--     mk.nama_kabupaten = b.nama_kabupaten,
--     mk.updated_at = b.updated_at
-- WHERE mk.kode IN ('3574', '3575', '3578', '3579');
--
-- SELECT ROW_COUNT() AS rows_rolled_back;
--
-- SELECT
--     mk.id,
--     mk.kode,
--     mk.nama_kabupaten AS nama_setelah_rollback,
--     b.nama_kabupaten AS nama_backup
-- FROM master_kabupaten mk
-- JOIN backup_master_kabupaten_fix_nama_kota_mfd_2025_1 b
--   ON b.id = mk.id
--  AND b.kode = mk.kode
-- WHERE mk.kode IN ('3574', '3575', '3578', '3579')
-- ORDER BY mk.kode;
--
-- COMMIT;
