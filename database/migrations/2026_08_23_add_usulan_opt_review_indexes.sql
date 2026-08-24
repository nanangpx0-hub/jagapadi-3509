-- Runtime root/integrated: indeks pencarian/filter modul Usulan OPT.
-- Append-only; jalankan pada database target setelah backup dan audit schema_migrations.
-- Status/user/master sudah terindeks oleh 2026_08_21_add_hama_observation_media_and_opt_proposals.sql.

ALTER TABLE `usulan_opt`
  ADD KEY `idx_usulan_opt_nama_nasional` (`nama_nasional`),
  ADD KEY `idx_usulan_opt_nama_lokal` (`nama_lokal`),
  ADD KEY `idx_usulan_opt_created_at` (`created_at`);
