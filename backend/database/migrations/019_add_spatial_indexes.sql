-- Migration 019: Add spatial indexes for map performance
-- WARNING: MariaDB/MySQL SPATIAL index requires both columns to be NOT NULL
-- We use B-tree indexes instead since latitude/longitude can be NULL

ALTER TABLE `laporan_hama`
  ADD INDEX `idx_lh_coords` (`latitude`, `longitude`);

ALTER TABLE `laporan_irigasi`
  ADD INDEX `idx_li_coords` (`latitude`, `longitude`);

-- Curah hujan coordinate index (for weather overlay on map)
ALTER TABLE `curah_hujan`
  ADD INDEX `idx_ch_coords` (`latitude`, `longitude`);
