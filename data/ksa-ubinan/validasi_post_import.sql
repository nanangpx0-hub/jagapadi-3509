-- ============================================================
-- TASK 6: Validasi Post-Import KSA Ubinan 2025
-- Database target: jagapadi_local
-- Jalankan: mysql -u root jagapadi_local < validasi_post_import.sql
--           atau jalankan langsung di phpMyAdmin / MySQL CLI
--
-- Gunakan kolom filter sumber_data = 'KSA Ubinan 2025' agar tidak
-- tercampur dengan data scraper/simulasi lain.
-- ============================================================

-- ------------------------------------------------------------------
-- 1. Jumlah record KSA Ubinan per tahun
--    Harus 38 kabupaten untuk tahun 2025.
-- ------------------------------------------------------------------
SELECT tahun, COUNT(*) AS jumlah_kabupaten
FROM data_pertanian_bps
WHERE sumber_data = 'KSA Ubinan 2025'
GROUP BY tahun
ORDER BY tahun;

-- ------------------------------------------------------------------
-- 2. Daftar kabupaten yang BELUM terisi untuk tahun 2025 (dari 38)
--    Tidak ada baris = semua 38 terisi (komplit).
-- ------------------------------------------------------------------
SELECT k.kabupaten_kota
FROM (
    SELECT 'Bangkalan' AS kabupaten_kota UNION SELECT 'Banyuwangi' UNION SELECT 'Blitar'
    UNION SELECT 'Bojonegoro' UNION SELECT 'Bondowoso' UNION SELECT 'Gresik'
    UNION SELECT 'Jember' UNION SELECT 'Jombang' UNION SELECT 'Kediri'
    UNION SELECT 'Kota Batu' UNION SELECT 'Kota Blitar' UNION SELECT 'Kota Kediri'
    UNION SELECT 'Kota Madiun' UNION SELECT 'Kota Malang' UNION SELECT 'Kota Mojokerto'
    UNION SELECT 'Kota Pasuruan' UNION SELECT 'Kota Probolinggo' UNION SELECT 'Kota Surabaya'
    UNION SELECT 'Lamongan' UNION SELECT 'Lumajang' UNION SELECT 'Madiun'
    UNION SELECT 'Magetan' UNION SELECT 'Malang' UNION SELECT 'Mojokerto'
    UNION SELECT 'Nganjuk' UNION SELECT 'Ngawi' UNION SELECT 'Pacitan'
    UNION SELECT 'Pamekasan' UNION SELECT 'Pasuruan' UNION SELECT 'Ponorogo'
    UNION SELECT 'Probolinggo' UNION SELECT 'Sampang' UNION SELECT 'Sidoarjo'
    UNION SELECT 'Situbondo' UNION SELECT 'Sumenep' UNION SELECT 'Trenggalek'
    UNION SELECT 'Tuban' UNION SELECT 'Tulungagung'
) k
LEFT JOIN data_pertanian_bps d
    ON d.kabupaten_kota = k.kabupaten_kota
    AND d.tahun = 2025
    AND d.sumber_data = 'KSA Ubinan 2025'
WHERE d.id IS NULL;

-- ------------------------------------------------------------------
-- 3. Statistik agregat tahun 2025 (KSA Ubinan)
--    Referensi BRS: luas ~1,84 juta Ha, gabah ~10,44 juta ton GKG,
--    beras ~6,03 juta ton (konversi 57,7%).
-- ------------------------------------------------------------------
SELECT
    COUNT(DISTINCT kabupaten_kota) AS jumlah_kabupaten,
    SUM(luas_panen) AS total_luas_panen_ha,
    SUM(produksi_gabah) AS total_produksi_gabah_ton,
    SUM(produksi_beras) AS total_produksi_beras_ton,
    ROUND(AVG(produktivitas), 2) AS rata_produktivitas_ku_ha
FROM data_pertanian_bps
WHERE tahun = 2025 AND sumber_data = 'KSA Ubinan 2025';

-- ------------------------------------------------------------------
-- 4. Rasio konversi gabah -> beras per kabupaten (harus ~57,7%)
--    Di luar rentang 55-60% patut dicurigai.
-- ------------------------------------------------------------------
SELECT
    kabupaten_kota,
    produksi_gabah,
    produksi_beras,
    ROUND((produksi_beras / NULLIF(produksi_gabah, 0)) * 100, 2) AS rasio_konversi_persen
FROM data_pertanian_bps
WHERE tahun = 2025 AND sumber_data = 'KSA Ubinan 2025'
HAVING rasio_konversi_persen < 55 OR rasio_konversi_persen > 60;

-- ------------------------------------------------------------------
-- 5. Anomali produktivitas (di luar rentang wajar 30-100 ku/ha)
-- ------------------------------------------------------------------
SELECT kabupaten_kota, luas_panen, produksi_gabah, produktivitas
FROM data_pertanian_bps
WHERE tahun = 2025 AND sumber_data = 'KSA Ubinan 2025'
  AND (produktivitas > 100 OR produktivitas < 30);

-- ------------------------------------------------------------------
-- 6. Baris dengan nilai nol/kosong (luas atau gabah = 0)
--    Indikasi data belum terisi penuh.
-- ------------------------------------------------------------------
SELECT kabupaten_kota, luas_panen, produksi_gabah, produksi_beras, produktivitas
FROM data_pertanian_bps
WHERE tahun = 2025 AND sumber_data = 'KSA Ubinan 2025'
  AND (luas_panen = 0 OR produksi_gabah = 0 OR produksi_beras = 0 OR produktivitas = 0);

-- ------------------------------------------------------------------
-- 7. Duplikat (tahun + kabupaten_kota) - seharusnya tidak ada
-- ------------------------------------------------------------------
SELECT tahun, kabupaten_kota, COUNT(*) AS jumlah
FROM data_pertanian_bps
WHERE sumber_data = 'KSA Ubinan 2025'
GROUP BY tahun, kabupaten_kota
HAVING COUNT(*) > 1;

-- ------------------------------------------------------------------
-- 8. Validasi kode wilayah BPS (harus 3501-3529 / 3571-3579)
-- ------------------------------------------------------------------
SELECT kabupaten_kota, kode_wilayah
FROM data_pertanian_bps
WHERE tahun = 2025 AND sumber_data = 'KSA Ubinan 2025'
  AND (
      kode_wilayah NOT REGEXP '^35[0-9]{2}$'
      OR kode_wilayah IS NULL
      OR (CAST(kode_wilayah AS UNSIGNED) BETWEEN 3530 AND 3570)
  );

-- ------------------------------------------------------------------
-- 9. 10 kabupaten dengan produksi gabah tertinggi (sanity check)
-- ------------------------------------------------------------------
SELECT kabupaten_kota, luas_panen, produksi_gabah, produktivitas
FROM data_pertanian_bps
WHERE tahun = 2025 AND sumber_data = 'KSA Ubinan 2025'
ORDER BY produksi_gabah DESC
LIMIT 10;

-- ------------------------------------------------------------------
-- 10. Log aktivitas import terakhir (audit trail)
--     Harus ada entry action = 'import_ksa_ubinan' status success.
-- ------------------------------------------------------------------
SELECT id, action, status, message, created_at
FROM bps_scraping_logs
WHERE action = 'import_ksa_ubinan'
ORDER BY created_at DESC
LIMIT 5;

-- ============================================================
-- KESIMPULAN VALIDASI:
--   1. Jumlah kabupaten = 38  -> import komplit
--   2. Query 2 kosong         -> semua kabupaten terisi
--   3. Total gabah ~10,44 jt  -> sesuai BRS KSA 2025
--   4/5/6. Kosong             -> tidak ada anomali
--   7. Kosong                 -> tidak ada duplikat
--   8. Kosong                 -> kode wilayah valid semua
--   10. Ada log success       -> import tercatat
-- ============================================================
