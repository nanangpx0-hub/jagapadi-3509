INSERT INTO `master_kabupaten` (`kode`, `nama_kabupaten`) VALUES
('3509', 'Jember')
ON DUPLICATE KEY UPDATE `nama_kabupaten` = VALUES(`nama_kabupaten`);

INSERT INTO `master_kecamatan` (`kabupaten_id`, `kode`, `nama_kecamatan`) VALUES
((SELECT `id` FROM `master_kabupaten` WHERE `kode` = '3509'), '3509010', 'Kaliwates'),
((SELECT `id` FROM `master_kabupaten` WHERE `kode` = '3509'), '3509020', 'Sumbersari'),
((SELECT `id` FROM `master_kabupaten` WHERE `kode` = '3509'), '3509030', 'Patrang'),
((SELECT `id` FROM `master_kabupaten` WHERE `kode` = '3509'), '3509040', 'Ajung'),
((SELECT `id` FROM `master_kabupaten` WHERE `kode` = '3509'), '3509050', 'Rambipuji')
ON DUPLICATE KEY UPDATE `nama_kecamatan` = VALUES(`nama_kecamatan`);

INSERT INTO `master_desa` (`kecamatan_id`, `kode`, `nama_desa`) VALUES
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509010'), '3509010001', 'Kebonagung'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509010'), '3509010002', 'Kaliwates'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509020'), '3509020001', 'Kebonsari'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509020'), '3509020002', 'Kranjingan'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509030'), '3509030001', 'Baratan'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509030'), '3509030002', 'Bintoro'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509040'), '3509040001', 'Ajung'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509040'), '3509040002', 'Klompangan'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509050'), '3509050001', 'Rambipuji'),
((SELECT `id` FROM `master_kecamatan` WHERE `kode` = '3509050'), '3509050002', 'Pekalongan')
ON DUPLICATE KEY UPDATE `nama_desa` = VALUES(`nama_desa`);
