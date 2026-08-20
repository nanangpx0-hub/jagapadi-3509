<?php
/**
 * Migration: Optimasi Performa Query Dashboard Charts
 * Menambah composite index untuk mempercepat query agregasi dashboard:
 *
 *  - laporan_hama   (scope personal & teritori kecamatan + status/tanggal)
 *  - curah_hujan    (filter tanggal + sumber data)
 *  - data_irigasi   (filter tanggal + daerah irigasi + debit)
 *  - laporan_lainnya(scoping petugas + jenis + status + tanggal kejadian)
 *
 * Catatan: `ADD INDEX IF NOT EXISTS` didukung oleh MariaDB (target DB).
 * Di MySQL klasik, jalankan dengan hati-hati (error "Duplicate key name"
 * harus diabaikan agar migration tetap idempoten).
 *
 * @version 1.0.0
 * @author JAGAPADI System
 */

-- Optimasi tabel Laporan Hama
ALTER TABLE `laporan_hama`
    ADD INDEX IF NOT EXISTS `idx_charts_hama_perf` (`user_id`, `status`, `tanggal`, `tingkat_keparahan`),
    ADD INDEX IF NOT EXISTS `idx_charts_hama_kecamatan` (`kecamatan_id`, `status`, `tanggal`);

-- Optimasi tabel Curah Hujan
ALTER TABLE `curah_hujan`
    ADD INDEX IF NOT EXISTS `idx_charts_hujan_perf` (`tanggal`, `sumber_data`, `curah_hujan`);

-- Optimasi tabel Data Irigasi
ALTER TABLE `data_irigasi`
    ADD INDEX IF NOT EXISTS `idx_charts_irigasi_perf` (`tanggal`, `daerah_irigasi`, `debit_air`);

-- Optimasi tabel Laporan Lainnya
ALTER TABLE `laporan_lainnya`
    ADD INDEX IF NOT EXISTS `idx_charts_ll_perf` (`user_id`, `jenis_id`, `status`, `tanggal_kejadian`);

-- ROLLBACK:
-- ALTER TABLE `laporan_hama` DROP INDEX IF EXISTS `idx_charts_hama_perf`;
-- ALTER TABLE `laporan_hama` DROP INDEX IF EXISTS `idx_charts_hama_kecamatan`;
-- ALTER TABLE `curah_hujan` DROP INDEX IF EXISTS `idx_charts_hujan_perf`;
-- ALTER TABLE `data_irigasi` DROP INDEX IF EXISTS `idx_charts_irigasi_perf`;
-- ALTER TABLE `laporan_lainnya` DROP INDEX IF EXISTS `idx_charts_ll_perf`;