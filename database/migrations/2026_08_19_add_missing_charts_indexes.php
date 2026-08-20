<?php
/**
 * Migration: Add missing indexes for chart query optimization
 * Menambah index untuk mempercepat query grafik dashboard
 * 
 * @version 1.0.0
 * @author JAGAPADI System
 */

-- Index 1: query curah_hujan by tanggal + sumber_data (getRainfallSummary)
ALTER TABLE curah_hujan
    ADD INDEX IF NOT EXISTS idx_ch_tanggal_sumber (tanggal, sumber_data(20));

-- Index 2: query kecepatan_angin by tanggal + sumber_data (getWindSummary)
ALTER TABLE kecepatan_angin
    ADD INDEX IF NOT EXISTS idx_ka_tanggal_sumber (tanggal, sumber_data(20));

-- Index 3: query hama charts (tanggal range + status + user_id untuk petugas)
ALTER TABLE laporan_hama
    ADD INDEX IF NOT EXISTS idx_lh_tanggal_status_user (tanggal, status, user_id);

-- Index 4: query data_irigasi by tanggal (getIrrigationTrend, getIrrigationStats)
ALTER TABLE data_irigasi
    ADD INDEX IF NOT EXISTS idx_di_tanggal (tanggal);

-- Index 5: query harga_komoditas untuk tren harga (getPriceTrend)
ALTER TABLE harga_komoditas
    ADD INDEX IF NOT EXISTS idx_hk_komoditas_tanggal (jenis_komoditas, tanggal);

-- Index 6: query harga_alerts untuk getPriceAlerts
ALTER TABLE harga_alerts
    ADD INDEX IF NOT EXISTS idx_ha_is_read_tanggal (is_read, tanggal);

-- ROLLBACK:
-- ALTER TABLE curah_hujan DROP INDEX IF EXISTS idx_ch_tanggal_sumber;
-- ALTER TABLE kecepatan_angin DROP INDEX IF EXISTS idx_ka_tanggal_sumber;
-- ALTER TABLE laporan_hama DROP INDEX IF EXISTS idx_lh_tanggal_status_user;
-- ALTER TABLE data_irigasi DROP INDEX IF EXISTS idx_di_tanggal;
-- ALTER TABLE harga_komoditas DROP INDEX IF EXISTS idx_hk_komoditas_tanggal;
-- ALTER TABLE harga_alerts DROP INDEX IF EXISTS idx_ha_is_read_tanggal;