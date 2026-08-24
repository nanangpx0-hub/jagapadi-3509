INSERT INTO master_jenis_laporan (kode, nama, deskripsi, fields_json, is_active)
VALUES
(
  'gangguan_sosial',
  'Gangguan Sosial',
  'Laporan gangguan aktivitas manusia atau hewan ternak terhadap kegiatan dan lahan pertanian.',
  JSON_ARRAY(
    JSON_OBJECT('name', 'nama_gangguan', 'type', 'text', 'label', 'Nama Gangguan', 'required', TRUE),
    JSON_OBJECT('name', 'sumber_gangguan', 'type', 'text', 'label', 'Sumber Gangguan', 'required', FALSE),
    JSON_OBJECT('name', 'komoditas', 'type', 'text', 'label', 'Komoditas Terdampak', 'required', TRUE),
    JSON_OBJECT('name', 'luas_terdampak_ha', 'type', 'number', 'label', 'Luas Terdampak (Ha)', 'required', FALSE)
  ),
  1
),
(
  'faktor_abiotik',
  'Faktor Abiotik',
  'Laporan gangguan tanaman akibat kondisi non-hayati seperti kekeringan, genangan, suhu, atau kondisi tanah.',
  JSON_ARRAY(
    JSON_OBJECT('name', 'nama_faktor', 'type', 'text', 'label', 'Nama Faktor Abiotik', 'required', TRUE),
    JSON_OBJECT('name', 'komoditas', 'type', 'text', 'label', 'Komoditas Terdampak', 'required', TRUE),
    JSON_OBJECT('name', 'fase_tanaman', 'type', 'text', 'label', 'Fase Tanaman', 'required', FALSE),
    JSON_OBJECT('name', 'luas_terdampak_ha', 'type', 'number', 'label', 'Luas Terdampak (Ha)', 'required', FALSE)
  ),
  1
),
(
  'bencana_cuaca',
  'Bencana Cuaca',
  'Laporan kerusakan pertanian akibat angin kencang, puting beliung, hujan ekstrem, banjir, atau kejadian cuaca lainnya.',
  JSON_ARRAY(
    JSON_OBJECT('name', 'jenis_bencana', 'type', 'text', 'label', 'Jenis Bencana Cuaca', 'required', TRUE),
    JSON_OBJECT('name', 'komoditas', 'type', 'text', 'label', 'Komoditas Terdampak', 'required', TRUE),
    JSON_OBJECT('name', 'tingkat_kerusakan', 'type', 'text', 'label', 'Tingkat Kerusakan', 'required', FALSE),
    JSON_OBJECT('name', 'luas_terdampak_ha', 'type', 'number', 'label', 'Luas Terdampak (Ha)', 'required', FALSE)
  ),
  1
),
(
  'gangguan_fisiologis',
  'Gangguan Fisiologis',
  'Laporan gangguan pertumbuhan tanaman akibat ketidakseimbangan hara, keracunan unsur, pH, atau kondisi fisiologis lainnya.',
  JSON_ARRAY(
    JSON_OBJECT('name', 'nama_gangguan', 'type', 'text', 'label', 'Nama Gangguan Fisiologis', 'required', TRUE),
    JSON_OBJECT('name', 'komoditas', 'type', 'text', 'label', 'Komoditas Terdampak', 'required', TRUE),
    JSON_OBJECT('name', 'faktor_pemicu', 'type', 'text', 'label', 'Faktor Pemicu', 'required', FALSE),
    JSON_OBJECT('name', 'luas_terdampak_ha', 'type', 'number', 'label', 'Luas Terdampak (Ha)', 'required', FALSE)
  ),
  1
)
ON DUPLICATE KEY UPDATE
  nama = VALUES(nama),
  deskripsi = VALUES(deskripsi),
  fields_json = VALUES(fields_json),
  is_active = VALUES(is_active);
