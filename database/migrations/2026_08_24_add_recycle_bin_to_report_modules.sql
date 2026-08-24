-- Recycle bin untuk empat modul runtime root/integrated.
-- Append-only: jangan mengubah migration yang telah tercatat.

ALTER TABLE usulan_opt
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at,
    ADD INDEX idx_usulan_opt_deleted_at (deleted_at),
    ADD CONSTRAINT fk_usulan_opt_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE laporan_hama
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at,
    ADD INDEX idx_laporan_hama_deleted_at (deleted_at),
    ADD CONSTRAINT fk_laporan_hama_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE laporan_irigasi
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at,
    ADD INDEX idx_laporan_irigasi_deleted_at (deleted_at),
    ADD CONSTRAINT fk_laporan_irigasi_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE laporan_lainnya
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at,
    ADD INDEX idx_laporan_lainnya_deleted_at (deleted_at),
    ADD CONSTRAINT fk_laporan_lainnya_deleted_by
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;
