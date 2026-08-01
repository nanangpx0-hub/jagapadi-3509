-- JAGAPADI maintenance script
-- File: database/maintenance/fix_wareng_mfd.sql
--
-- Tujuan:
--   Mendokumentasikan validasi dan perbaikan relasi desa WARENG sesuai MFD BPS 2025_1.2025.
--
-- Sumber validasi:
--   SIG BPS Kode Relasi BPS-Kemendagri
--   Endpoint:
--   https://sig.bps.go.id/rest-bridging/getwilayah?level=desa&parent=3501020&periode_merge=2025_1.2025
--
-- Kesimpulan:
--   WARENG dengan kode BPS 3501020008 berada di:
--     Kabupaten  : PACITAN
--     Kecamatan  : PUNUNG
--     Kode Kec   : 3501020
--
-- Catatan penting:
--   Skema: master_desa(id, kecamatan_id, kode, nama_desa, created_at, updated_at)
--          master_kecamatan(id, kabupaten_id, kode, nama_kecamatan, ...)
--          master_kabupaten(id, kode, nama_kabupaten, ...)
--   Tidak ada kolom kode_desa/kode_kecamatan/kode_kabupaten atau deleted_at.
--   Jangan mengubah kode desa menjadi kode Jember.
--   Jangan mencampur kode_bps 3501020008 dengan kode_dagri 35.01.03.2007.
--   Di database lokal yang sudah diperbaiki, WARENG harus memiliki kecamatan_id yang menunjuk ke PUNUNG.
--
-- Script ini aman dijalankan di environment yang masih salah karena UPDATE hanya berjalan
-- jika kecamatan_id WARENG belum menunjuk ke PUNUNG.

-- =========================================================
-- 1. BACKUP KHUSUS RECORD WARENG
-- =========================================================

CREATE TABLE IF NOT EXISTS backup_master_desa_wareng_41_20260502 LIKE master_desa;

INSERT INTO backup_master_desa_wareng_41_20260502
SELECT d.*
FROM master_desa d
WHERE d.id = 41
  AND d.nama_desa = 'WARENG'
  AND NOT EXISTS (
      SELECT 1
      FROM backup_master_desa_wareng_41_20260502 b
      WHERE b.id = d.id
  );

SELECT *
FROM backup_master_desa_wareng_41_20260502
WHERE id = 41;


-- =========================================================
-- 2. BEFORE CHECK
-- =========================================================

SELECT
    d.id,
    d.nama_desa,
    d.kode,
    d.kecamatan_id,
    k.kode AS kode_kecamatan,
    k.nama_kecamatan,
    kab.kode AS kode_kabupaten,
    kab.nama_kabupaten,
    CASE
        WHEN LEFT(d.kode, 7) = k.kode THEN 'MATCH'
        ELSE 'MISMATCH'
    END AS prefix_status
FROM master_desa d
JOIN master_kecamatan k ON k.id = d.kecamatan_id
JOIN master_kabupaten kab ON kab.id = k.kabupaten_id
WHERE d.id = 41
  AND d.nama_desa = 'WARENG';


-- =========================================================
-- 3. GUARD UPDATE
-- Hanya update jika WARENG belum menunjuk ke PUNUNG/PACITAN.
-- =========================================================

START TRANSACTION;

UPDATE master_desa d
JOIN master_kecamatan k
  ON k.kode = '3501020'
 AND k.nama_kecamatan = 'PUNUNG'
JOIN master_kabupaten kab
  ON kab.id = k.kabupaten_id
 AND kab.kode = '3501'
SET
    d.kecamatan_id = k.id,
    d.updated_at = CURRENT_TIMESTAMP
WHERE d.id = 41
  AND d.nama_desa = 'WARENG'
  AND d.kode = '3501020008'
  AND d.kecamatan_id <> k.id;

SELECT ROW_COUNT() AS rows_changed;

-- Jika hasil validasi setelah update benar, jalankan:
-- COMMIT;
--
-- Jika tidak sesuai, jalankan:
-- ROLLBACK;


-- =========================================================
-- 4. AFTER CHECK
-- =========================================================

SELECT
    d.id,
    d.nama_desa,
    d.kode,
    d.kecamatan_id,
    k.kode AS kode_kecamatan,
    k.nama_kecamatan,
    kab.kode AS kode_kabupaten,
    kab.nama_kabupaten,
    CASE
        WHEN LEFT(d.kode, 7) = k.kode THEN 'MATCH'
        ELSE 'MISMATCH'
    END AS prefix_status
FROM master_desa d
JOIN master_kecamatan k ON k.id = d.kecamatan_id
JOIN master_kabupaten kab ON kab.id = k.kabupaten_id
WHERE d.id = 41
  AND d.nama_desa = 'WARENG';


-- =========================================================
-- 5. ROLLBACK DARI BACKUP
-- Jalankan hanya jika perlu mengembalikan record ke kondisi sebelum script.
-- =========================================================

-- START TRANSACTION;
--
-- UPDATE master_desa d
-- JOIN backup_master_desa_wareng_41_20260502 b ON b.id = d.id
-- SET
--     d.kecamatan_id = b.kecamatan_id,
--     d.kode = b.kode,
--     d.nama_desa = b.nama_desa,
--     d.created_at = b.created_at,
--     d.updated_at = b.updated_at
-- WHERE d.id = 41
--   AND d.nama_desa = 'WARENG';
--
-- SELECT ROW_COUNT() AS rows_rolled_back;
--
-- COMMIT;
