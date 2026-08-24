-- =============================================================================
-- Tahap 1 — Optimalisasi Indeks Database (Petugas query paths)
--
-- Composite index untuk pola query daftar milik Petugas yang berulang:
--   WHERE user_id = ? AND deleted_at IS NULL ORDER BY <kolom urut> DESC
-- Tujuan: menghapus filesort dan membatasi scan per-user.
--
-- Catatan desain: ORDER BY laporan hama/irigasi adalah
--   `tanggal DESC, created_at DESC`
-- sehingga indeks menyertakan created_at SETELAH tanggal agar optimizer
-- dapat melakukan backward index scan tanpa filesort:
--   (user_id, deleted_at, tanggal, created_at, id)
-- usulan_opt diurutkan hanya `created_at DESC` sehingga bentuknya:
--   (user_id, deleted_at, created_at)
--
-- Append-only migration. Idempotent saat penerapan manual dilakukan
-- melalui pengecekan information_schema oleh skrip penerap.
-- =============================================================================

ALTER TABLE `laporan_hama`
    ADD INDEX `idx_lh_user_deleted_tanggal_id` (`user_id`, `deleted_at`, `tanggal`, `created_at`, `id`);

ALTER TABLE `laporan_irigasi`
    ADD INDEX `idx_li_user_deleted_tanggal_id` (`user_id`, `deleted_at`, `tanggal`, `created_at`, `id`);

ALTER TABLE `usulan_opt`
    ADD INDEX `idx_uo_user_deleted_created` (`user_id`, `deleted_at`, `created_at`);