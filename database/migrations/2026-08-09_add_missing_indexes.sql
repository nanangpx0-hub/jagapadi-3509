--
-- Migration: Tambah secondary index pada tabel tanpa index
-- Tanggal: 2026-08-09
-- Catatan: Semua ALTER bersifat idempotent-friendly (menggunakan IF NOT EXISTS checks oleh runner/script manual).
--

ALTER TABLE harga_alerts ADD INDEX idx_created_at (created_at);
ALTER TABLE harga_alerts ADD INDEX idx_jenis_tanggal (jenis_komoditas, tanggal);
ALTER TABLE nomor_laporan_counter ADD INDEX idx_tanggal_counter (tanggal, counter);
ALTER TABLE bps_scraping_logs ADD INDEX idx_action_status (action, status);
ALTER TABLE bps_scraping_logs ADD INDEX idx_created_at (created_at);
ALTER TABLE curah_hujan_logs ADD INDEX idx_created_at (created_at);
ALTER TABLE harga_komoditas_logs ADD INDEX idx_created_at (created_at);
ALTER TABLE kecepatan_angin_logs ADD INDEX idx_created_at (created_at);