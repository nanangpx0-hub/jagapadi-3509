-- =============================================================================
-- JAGAPDI Petugas KSA assignment
-- Add a column to record the KSA (Survei Kerangka Sampel Area / BPS commodity
-- group) that each petugas is assigned to: 'KSA Padi' or 'KSA Jagung'.
-- Append-only migration. Run by backend/scripts/migrate.php.
-- =============================================================================
ALTER TABLE `users`
    ADD COLUMN `ksa` VARCHAR(20) NULL DEFAULT NULL
    COMMENT 'KSA komoditas group assignment for petugas (KSA Padi / KSA Jagung)'
    AFTER `aktif`,
    ADD KEY `idx_users_ksa` (`ksa`);