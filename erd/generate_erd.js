const fs = require('fs');
const path = require('path');

// 1. Definition of Clusters & Entities
const clusters = {
  users: { name: 'Pengguna, Keamanan & Audit', color: '#1E293B', bg: '#F8FAFC', accent: '#3B82F6' },
  wilayah: { name: 'Wilayah & Lahan Pertanian', color: '#0F766E', bg: '#F0FDFA', accent: '#14B8A6' },
  tanaman: { name: 'Tanaman, Hama & OPT', color: '#047857', bg: '#ECFDF5', accent: '#10B981' },
  laporan: { name: 'Pelaporan Lapangan Terpadu', color: '#B45309', bg: '#FFFBEB', accent: '#F59E0B' },
  panen: { name: 'Panen, KSA & Produksi BPS', color: '#C2410C', bg: '#FFF7ED', accent: '#EA580C' },
  keuangan: { name: 'Keuangan & Pasar Komoditas', color: '#4338CA', bg: '#EEF2FF', accent: '#6366F1' },
  inventaris: { name: 'Inventaris, Alsintan & IoT Irigasi', color: '#0369A1', bg: '#F0F9FF', accent: '#0EA5E9' },
  komunikasi: { name: 'Notifikasi & Komunikasi', color: '#BE185D', bg: '#FDF2F8', accent: '#EC4899' }
};

// 38 Entities definition
const tables = [
  // --- KLASTER 1: PENGGUNA & KEAMANAN ---
  {
    id: 'users',
    name: 'users',
    cluster: 'users',
    x: 1320, y: 130, w: 260,
    desc: 'Pusat otentikasi & otorisasi RBAC (Admin, Petugas, Operator, Statistisi, Viewer)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'username', type: 'VARCHAR(50)', uk: true },
      { name: 'password', type: 'VARCHAR(255)' },
      { name: 'email', type: 'VARCHAR(150)', uk: true },
      { name: 'nama_lengkap', type: 'VARCHAR(150)' },
      { name: 'role', type: 'ENUM(admin,petugas,...)' },
      { name: 'aktif', type: 'TINYINT(1)' },
      { name: 'must_change_password', type: 'TINYINT(1)' },
      { name: 'token_version', type: 'INT UNSIGNED' },
      { name: 'ksa', type: 'VARCHAR(20)' },
      { name: 'created_at', type: 'TIMESTAMP' },
      { name: 'updated_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'device_tokens',
    name: 'device_tokens',
    cluster: 'users',
    x: 1650, y: 130, w: 230,
    desc: 'Token FCM perangkat untuk push notifications',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'token', type: 'VARCHAR(512)', uk: true },
      { name: 'platform', type: 'ENUM(android,ios,web)' },
      { name: 'last_seen_at', type: 'TIMESTAMP' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'jwt_blacklist',
    name: 'jwt_blacklist',
    cluster: 'users',
    x: 1650, y: 340, w: 230,
    desc: 'Daftar token JWT yang telah di-revoke sebelum masa aktif habis',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'token_hash', type: 'CHAR(64)', uk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'expires_at', type: 'TIMESTAMP' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'idempotency_keys',
    name: 'idempotency_keys',
    cluster: 'users',
    x: 1650, y: 510, w: 230,
    desc: 'Mencegah mutasi data ganda akibat pengiriman ulang jaringan',
    columns: [
      { name: 'key', type: 'VARCHAR(64)', pk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'route', type: 'VARCHAR(255)' },
      { name: 'request_hash', type: 'CHAR(64)' },
      { name: 'response_code', type: 'INT' },
      { name: 'expires_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'activity_log',
    name: 'activity_log',
    cluster: 'users',
    x: 990, y: 130, w: 260,
    desc: 'Audit trail semua aktivitas penting sistem',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'action', type: 'VARCHAR(100)' },
      { name: 'table_name', type: 'VARCHAR(50)' },
      { name: 'record_id', type: 'BIGINT UNSIGNED' },
      { name: 'ip_address', type: 'VARCHAR(45)' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },

  // --- KLASTER 2: WILAYAH & LAHAN PERTANIAN ---
  {
    id: 'master_kabupaten',
    name: 'master_kabupaten',
    cluster: 'wilayah',
    x: 60, y: 130, w: 240,
    desc: 'Wilayah administratif Tingkat II (Kabupaten Jember)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'kode', type: 'VARCHAR(10)', uk: true },
      { name: 'nama_kabupaten', type: 'VARCHAR(100)' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'master_kecamatan',
    name: 'master_kecamatan',
    cluster: 'wilayah',
    x: 60, y: 310, w: 240,
    desc: 'Kecamatan di Kabupaten Jember (31 Kecamatan)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'kabupaten_id', type: 'INT UNSIGNED', fk: 'master_kabupaten.id' },
      { name: 'kode', type: 'VARCHAR(10)', uk: true },
      { name: 'nama_kecamatan', type: 'VARCHAR(100)' },
      { name: 'latitude', type: 'DECIMAL(10,7)' },
      { name: 'longitude', type: 'DECIMAL(10,7)' }
    ]
  },
  {
    id: 'master_desa',
    name: 'master_desa',
    cluster: 'wilayah',
    x: 60, y: 530, w: 240,
    desc: 'Desa/Kelurahan di Kabupaten Jember (248 Desa/Kelurahan)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'kecamatan_id', type: 'INT UNSIGNED', fk: 'master_kecamatan.id' },
      { name: 'kode', type: 'VARCHAR(10)', uk: true },
      { name: 'nama_desa', type: 'VARCHAR(100)' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'audit_log_wilayah',
    name: 'audit_log_wilayah',
    cluster: 'wilayah',
    x: 60, y: 730, w: 240,
    desc: 'Audit trail perubahan master wilayah oleh Admin',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'admin_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'tabel', type: 'VARCHAR(50)' },
      { name: 'record_id', type: 'INT UNSIGNED' },
      { name: 'aksi', type: 'ENUM(INSERT,UPDATE,DELETE)' },
      { name: 'data_lama', type: 'JSON' },
      { name: 'data_baru', type: 'JSON' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },

  // --- KLASTER 3: TANAMAN, HAMA & OPT ---
  {
    id: 'master_opt',
    name: 'master_opt',
    cluster: 'tanaman',
    x: 2000, y: 130, w: 250,
    desc: 'Katalog resmi Organisme Pengganggu Tanaman (Hama, Penyakit, Gulma)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'kode_opt', type: 'VARCHAR(20)', uk: true },
      { name: 'nama_opt', type: 'VARCHAR(150)', uk: true },
      { name: 'jenis', type: 'ENUM(hama,penyakit,gulma)' },
      { name: 'etl_acuan', type: 'DECIMAL(10,2)' },
      { name: 'satuan_etl', type: 'VARCHAR(30)' },
      { name: 'foto_url', type: 'VARCHAR(300)' },
      { name: 'aktif', type: 'TINYINT(1)' }
    ]
  },
  {
    id: 'usulan_opt',
    name: 'usulan_opt',
    cluster: 'tanaman',
    x: 2330, y: 130, w: 260,
    desc: 'Pengajuan identifikasi OPT baru di lapangan oleh petugas',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'master_opt_id', type: 'INT UNSIGNED', fk: 'master_opt.id' },
      { name: 'nama_opt', type: 'VARCHAR(150)' },
      { name: 'jenis', type: 'ENUM(hama,penyakit,gulma)' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'bagian_terserang', type: 'VARCHAR(100)' },
      { name: 'estimasi_terdampak', type: 'DECIMAL(10,2)' },
      { name: 'status', type: 'ENUM(Draf,Review,...)' },
      { name: 'reviewed_by', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'deleted_at', type: 'DATETIME' }
    ]
  },
  {
    id: 'usulan_opt_photos',
    name: 'usulan_opt_photos',
    cluster: 'tanaman',
    x: 2670, y: 130, w: 240,
    desc: 'Dokumentasi foto spesimen bukti identifikasi usulan OPT',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'usulan_opt_id', type: 'BIGINT UNSIGNED', fk: 'usulan_opt.id' },
      { name: 'file_path', type: 'VARCHAR(300)' },
      { name: 'mime_type', type: 'VARCHAR(50)' },
      { name: 'file_size', type: 'INT UNSIGNED' },
      { name: 'checksum', type: 'CHAR(64)' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'usulan_opt_status_history',
    name: 'usulan_opt_status_history',
    cluster: 'tanaman',
    x: 2670, y: 360, w: 240,
    desc: 'Riwayat verifikasi dan peninjauan usulan OPT',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'usulan_opt_id', type: 'BIGINT UNSIGNED', fk: 'usulan_opt.id' },
      { name: 'from_status', type: 'VARCHAR(30)' },
      { name: 'to_status', type: 'VARCHAR(30)' },
      { name: 'changed_by', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'catatan', type: 'TEXT' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'tags',
    name: 'tags',
    cluster: 'tanaman',
    x: 2000, y: 410, w: 250,
    desc: 'Kata kunci klasifikasi tematik pelaporan',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'nama_tag', type: 'VARCHAR(100)', uk: true },
      { name: 'deskripsi', type: 'TEXT' },
      { name: 'warna', type: 'VARCHAR(10)' },
      { name: 'usage_count', type: 'INT' }
    ]
  },
  {
    id: 'laporan_hama_tags',
    name: 'laporan_hama_tags',
    cluster: 'tanaman',
    x: 2000, y: 580, w: 250,
    desc: 'Tabel pivot Many-to-Many laporan hama dengan tags',
    columns: [
      { name: 'laporan_hama_id', type: 'BIGINT UNSIGNED', pk: true, fk: 'laporan_hama.id' },
      { name: 'tag_id', type: 'INT UNSIGNED', pk: true, fk: 'tags.id' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },

  // --- KLASTER 4: PELAPORAN LAPANGAN TERPADU ---
  {
    id: 'laporan_hama',
    name: 'laporan_hama',
    cluster: 'laporan',
    x: 1040, y: 730, w: 260,
    desc: 'Observasi serangan hama, populasi, & keparahan di lapangan',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'nomor_laporan', type: 'VARCHAR(20)', uk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'master_opt_id', type: 'INT UNSIGNED', fk: 'master_opt.id' },
      { name: 'tanggal', type: 'DATE' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'tingkat_keparahan', type: 'ENUM(Ringan,Sedang,Berat)' },
      { name: 'luas_serangan', type: 'DECIMAL(8,2)' },
      { name: 'populasi', type: 'DECIMAL(10,2)' },
      { name: 'foto_url', type: 'VARCHAR(300)' },
      { name: 'status', type: 'ENUM(Draf,Submitted,...)' },
      { name: 'verified_by', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'deleted_at', type: 'DATETIME' }
    ]
  },
  {
    id: 'laporan_irigasi',
    name: 'laporan_irigasi',
    cluster: 'laporan',
    x: 1370, y: 730, w: 250,
    desc: 'Observasi kondisi fisik saluran irigasi & ketersediaan debit air',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'nomor_laporan', type: 'VARCHAR(20)', uk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'tanggal', type: 'DATE' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'nama_saluran', type: 'VARCHAR(200)' },
      { name: 'kondisi_fisik', type: 'ENUM(Bagus,Sedang,Rusak)' },
      { name: 'debit_air', type: 'ENUM(Cukup,Kurang,Kering)' },
      { name: 'status', type: 'ENUM(Draf,Submitted,...)' },
      { name: 'verified_by', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'deleted_at', type: 'DATETIME' }
    ]
  },
  {
    id: 'laporan_panen',
    name: 'laporan_panen',
    cluster: 'laporan',
    x: 1700, y: 730, w: 260,
    desc: 'Pelaporan riil panen komoditas, varietas, hasil, & nilai jual',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'nomor_laporan', type: 'VARCHAR(20)', uk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'master_opt_id', type: 'INT UNSIGNED', fk: 'master_opt.id' },
      { name: 'tanggal', type: 'DATE' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'komoditas', type: 'VARCHAR(100)' },
      { name: 'varietas', type: 'VARCHAR(100)' },
      { name: 'luas_panen', type: 'DECIMAL(10,2)' },
      { name: 'hasil_panen', type: 'DECIMAL(10,2)' },
      { name: 'produktivitas', type: 'DECIMAL(10,2)' },
      { name: 'harga_per_unit', type: 'DECIMAL(15,2)' },
      { name: 'status', type: 'ENUM(Draf,Submitted,...)' },
      { name: 'verified_by', type: 'INT UNSIGNED', fk: 'users.id' }
    ]
  },
  {
    id: 'laporan_pupuk',
    name: 'laporan_pupuk',
    cluster: 'laporan',
    x: 2040, y: 730, w: 250,
    desc: 'Pencatatan jenis, dosis, dan luas pemupukan di sawah',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'nomor_laporan', type: 'VARCHAR(20)', uk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'tanggal', type: 'DATE' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'jenis_pupuk', type: 'ENUM(Urea,NPK,Organik,...)' },
      { name: 'dosis', type: 'DECIMAL(10,2)' },
      { name: 'satuan_dosis', type: 'VARCHAR(50)' },
      { name: 'luas_pemupukan', type: 'DECIMAL(8,2)' },
      { name: 'metode_aplikasi', type: 'ENUM(Tabur,Kocor,...)' },
      { name: 'status', type: 'ENUM(Draf,Submitted,...)' },
      { name: 'verified_by', type: 'INT UNSIGNED', fk: 'users.id' }
    ]
  },
  {
    id: 'laporan_alat_sarana',
    name: 'laporan_alat_sarana',
    cluster: 'laporan',
    x: 2370, y: 730, w: 250,
    desc: 'Inventarisasi Alsintan & sarana pendukung pertanian kelompok tani',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'nomor_laporan', type: 'VARCHAR(20)', uk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'tanggal', type: 'DATE' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'nama_alat', type: 'VARCHAR(200)' },
      { name: 'jenis_sarana', type: 'ENUM(Traktor,Pompa,...)' },
      { name: 'kondisi', type: 'ENUM(Baik,Rusak,...)' },
      { name: 'jumlah', type: 'INT UNSIGNED' },
      { name: 'tahun_pengadaan', type: 'YEAR' },
      { name: 'status', type: 'ENUM(Draf,Submitted,...)' }
    ]
  },
  {
    id: 'laporan_cuaca',
    name: 'laporan_cuaca',
    cluster: 'laporan',
    x: 710, y: 730, w: 250,
    desc: 'Laporan agrometeorologi harian dari lapangan (suhu, hujan, angin)',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'nomor_laporan', type: 'VARCHAR(20)', uk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'tanggal', type: 'DATE' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'kondisi_cuaca', type: 'VARCHAR(100)' },
      { name: 'suhu_min', type: 'DECIMAL(5,2)' },
      { name: 'suhu_max', type: 'DECIMAL(5,2)' },
      { name: 'curah_hujan', type: 'DECIMAL(8,2)' },
      { name: 'kecepatan_angin', type: 'DECIMAL(6,2)' },
      { name: 'status', type: 'ENUM(Draf,Submitted,...)' }
    ]
  },
  {
    id: 'master_jenis_laporan',
    name: 'master_jenis_laporan',
    cluster: 'laporan',
    x: 710, y: 1140, w: 250,
    desc: 'Katalog jenis laporan dinamis & definisi skema form JSON',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'kode', type: 'VARCHAR(50)', uk: true },
      { name: 'nama_jenis', type: 'VARCHAR(100)' },
      { name: 'deskripsi', type: 'TEXT' },
      { name: 'fields_json', type: 'JSON' },
      { name: 'aktif', type: 'TINYINT(1)' }
    ]
  },
  {
    id: 'laporan_lainnya',
    name: 'laporan_lainnya',
    cluster: 'laporan',
    x: 1040, y: 1140, w: 260,
    desc: 'Laporan multi-kategori fleksibel berbasis data_json',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'nomor_laporan', type: 'VARCHAR(20)', uk: true },
      { name: 'jenis_id', type: 'BIGINT UNSIGNED', fk: 'master_jenis_laporan.id' },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'desa_id', type: 'INT UNSIGNED', fk: 'master_desa.id' },
      { name: 'judul', type: 'VARCHAR(200)' },
      { name: 'data_json', type: 'JSON' },
      { name: 'status', type: 'ENUM(Draf,Submitted,...)' },
      { name: 'verified_by', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'deleted_at', type: 'DATETIME' }
    ]
  },
  {
    id: 'laporan_status_history',
    name: 'laporan_status_history',
    cluster: 'laporan',
    x: 1370, y: 1140, w: 250,
    desc: 'Audit log alur verifikasi laporan (Draf, Submitted, Verifikasi, Tolak)',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'laporan_id', type: 'BIGINT UNSIGNED', fk: 'laporan_hama.id' },
      { name: 'old_status', type: 'VARCHAR(30)' },
      { name: 'new_status', type: 'VARCHAR(30)' },
      { name: 'changed_by', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'komentar', type: 'TEXT' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'nomor_laporan_counter',
    name: 'nomor_laporan_counter',
    cluster: 'laporan',
    x: 1700, y: 1140, w: 250,
    desc: 'Pembangkit nomor laporan atomik harian bebas race condition',
    columns: [
      { name: 'prefix', type: 'VARCHAR(10)', pk: true },
      { name: 'tanggal', type: 'DATE', pk: true },
      { name: 'counter', type: 'INT UNSIGNED' }
    ]
  },

  // --- KLASTER 5: PANEN, KSA & PRODUKSI BPS ---
  {
    id: 'produksi_gabah',
    name: 'produksi_gabah',
    cluster: 'panen',
    x: 2370, y: 1140, w: 250,
    desc: 'Agregat luas panen dan produksi gabah tingkat kecamatan',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'kecamatan_id', type: 'INT UNSIGNED', fk: 'master_kecamatan.id' },
      { name: 'tahun', type: 'INT' },
      { name: 'luas_panen', type: 'DECIMAL(15,2)' },
      { name: 'produksi_total', type: 'DECIMAL(15,2)' },
      { name: 'status', type: 'VARCHAR(20)' }
    ]
  },
  {
    id: 'data_pertanian_bps',
    name: 'data_pertanian_bps',
    cluster: 'panen',
    x: 2700, y: 1140, w: 260,
    desc: 'Rilis resmi statistik tahunan BPS Jawa Timur / Kabupaten Jember',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'tahun', type: 'INT' },
      { name: 'kabupaten_kota', type: 'VARCHAR(100)' },
      { name: 'luas_panen', type: 'DECIMAL(15,2)' },
      { name: 'produksi_gabah', type: 'DECIMAL(15,2)' },
      { name: 'produktivitas', type: 'DECIMAL(10,2)' },
      { name: 'tipe_skenario', type: 'ENUM(baseline,...)' },
      { name: 'sumber_data', type: 'VARCHAR(100)' }
    ]
  },
  {
    id: 'data_ksa_bulanan',
    name: 'data_ksa_bulanan',
    cluster: 'panen',
    x: 2370, y: 1440, w: 250,
    desc: 'Survei Kerangka Sampel Area (KSA) bulanan citra satelit BPS',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'tahun', type: 'SMALLINT UNSIGNED' },
      { name: 'bulan', type: 'TINYINT UNSIGNED' },
      { name: 'kabupaten_kota', type: 'VARCHAR(100)' },
      { name: 'kode_wilayah', type: 'VARCHAR(10)' },
      { name: 'luas_panen', type: 'DECIMAL(15,4)' },
      { name: 'produksi_gabah', type: 'DECIMAL(15,4)' },
      { name: 'status_data', type: 'ENUM(tetap,potensi,...)' }
    ]
  },
  {
    id: 'evaluasi_akurasi_panen',
    name: 'evaluasi_akurasi_panen',
    cluster: 'panen',
    x: 2700, y: 1440, w: 260,
    desc: 'Evaluasi deviasi & persentase bias data daerah vs rilis resmi BPS',
    columns: [
      { name: 'id', type: 'INT', pk: true },
      { name: 'periode_bulan', type: 'INT(2)' },
      { name: 'periode_tahun', type: 'YEAR' },
      { name: 'wilayah_id', type: 'INT' },
      { name: 'nama_wilayah', type: 'VARCHAR(100)' },
      { name: 'luas_estimasi_daerah', type: 'DECIMAL(10,2)' },
      { name: 'luas_rilis_bps', type: 'DECIMAL(10,2)' },
      { name: 'deviasi_absolut', type: 'DECIMAL(10,2)' },
      { name: 'persentase_bias', type: 'DECIMAL(5,2)' },
      { name: 'status_akurasi', type: 'ENUM(Akurat,Bias...)' }
    ]
  },
  {
    id: 'analisis_produksi_bulanan',
    name: 'analisis_produksi_bulanan',
    cluster: 'panen',
    x: 2370, y: 1750, w: 250,
    desc: 'Komputasi gabungan KSA, hama, iklim, & narasi AI otomatis bulanan',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'periode_bulan', type: 'TINYINT UNSIGNED' },
      { name: 'periode_tahun', type: 'SMALLINT UNSIGNED' },
      { name: 'luas_panen_bersih', type: 'DECIMAL(15,2)' },
      { name: 'estimasi_produksi', type: 'DECIMAL(15,2)' },
      { name: 'skor_risiko_hama', type: 'DECIMAL(5,2)' },
      { name: 'narasi_otomatis', type: 'TEXT' },
      { name: 'metadata', type: 'JSON' }
    ]
  },
  {
    id: 'evaluasi_akurasi_logs',
    name: 'evaluasi_akurasi_logs',
    cluster: 'panen',
    x: 2700, y: 1750, w: 260,
    desc: 'Audit trail eksekusi komparasi data rilis BPS',
    columns: [
      { name: 'id', type: 'INT', pk: true },
      { name: 'action', type: 'VARCHAR(50)' },
      { name: 'status', type: 'ENUM(success,failed)' },
      { name: 'message', type: 'TEXT' },
      { name: 'user_id', type: 'INT', fk: 'users.id' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },

  // --- KLASTER 6: KEUANGAN & PASAR KOMODITAS ---
  {
    id: 'harga_komoditas',
    name: 'harga_komoditas',
    cluster: 'keuangan',
    x: 1200, y: 1530, w: 260,
    desc: 'Tren harga harian gabah kering panen/giling & beras medium/premium',
    columns: [
      { name: 'id', type: 'INT', pk: true },
      { name: 'tanggal', type: 'DATE' },
      { name: 'jenis_komoditas', type: 'ENUM(gabah,beras)' },
      { name: 'harga', type: 'DECIMAL(12,2)' },
      { name: 'satuan', type: 'VARCHAR(20)' },
      { name: 'lokasi', type: 'VARCHAR(100)' },
      { name: 'sumber_data', type: 'VARCHAR(100)' },
      { name: 'metode_data', type: 'ENUM(aktual,simulasi,...)' }
    ]
  },
  {
    id: 'harga_alerts',
    name: 'harga_alerts',
    cluster: 'keuangan',
    x: 1540, y: 1530, w: 250,
    desc: 'Notifikasi peringatan lonjakan / fluktuasi tajam harga komoditas',
    columns: [
      { name: 'id', type: 'INT', pk: true },
      { name: 'jenis_komoditas', type: 'VARCHAR(50)' },
      { name: 'tipe_alert', type: 'ENUM(naik,turun,fluktuasi)' },
      { name: 'persentase', type: 'DECIMAL(5,2)' },
      { name: 'harga_sebelum', type: 'DECIMAL(12,2)' },
      { name: 'harga_sesudah', type: 'DECIMAL(12,2)' },
      { name: 'tanggal', type: 'DATE' }
    ]
  },
  {
    id: 'harga_komoditas_logs',
    name: 'harga_komoditas_logs',
    cluster: 'keuangan',
    x: 1200, y: 1850, w: 260,
    desc: 'Log pembaruan & scraping data harga pasar komoditas',
    columns: [
      { name: 'id', type: 'INT', pk: true },
      { name: 'action', type: 'VARCHAR(50)' },
      { name: 'status', type: 'VARCHAR(20)' },
      { name: 'message', type: 'TEXT' },
      { name: 'details', type: 'JSON' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'gabah_beras_logs',
    name: 'gabah_beras_logs',
    cluster: 'keuangan',
    x: 1540, y: 1850, w: 250,
    desc: 'Audit log transaksi dan verifikasi data pasar gabah & beras',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'action', type: 'VARCHAR(100)' },
      { name: 'status', type: 'VARCHAR(50)' },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'ip_address', type: 'VARCHAR(45)' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },

  // --- KLASTER 7: INVENTARIS, ALSINTAN & IOT IRIGASI ---
  {
    id: 'sensor_pengairan',
    name: 'sensor_pengairan',
    cluster: 'inventaris',
    x: 60, y: 1530, w: 240,
    desc: 'Master sensor telemetri ketinggian air & kelembaban sawah (IoT)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'kode_sensor', type: 'VARCHAR(100)', uk: true },
      { name: 'nama', type: 'VARCHAR(255)' },
      { name: 'tipe_sensor', type: 'VARCHAR(100)' },
      { name: 'nilai_min', type: 'DECIMAL(10,2)' },
      { name: 'nilai_max', type: 'DECIMAL(10,2)' },
      { name: 'status', type: 'VARCHAR(50)' },
      { name: 'last_reading_at', type: 'DATETIME' }
    ]
  },
  {
    id: 'pembacaan_sensor',
    name: 'pembacaan_sensor',
    cluster: 'inventaris',
    x: 360, y: 1530, w: 240,
    desc: 'Time-series data telemetri pembacaan sensor berkala',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'sensor_id', type: 'INT UNSIGNED', fk: 'sensor_pengairan.id' },
      { name: 'nilai', type: 'DECIMAL(10,2)' },
      { name: 'status_pembacaan', type: 'VARCHAR(50)' },
      { name: 'waktu_baca', type: 'DATETIME' }
    ]
  },
  {
    id: 'irrigation_rules',
    name: 'irrigation_rules',
    cluster: 'inventaris',
    x: 60, y: 1850, w: 240,
    desc: 'Aturan logika otomasi buka-tutup pintu air irigasi',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'irigasi_id', type: 'INT UNSIGNED' },
      { name: 'rule_name', type: 'VARCHAR(255)' },
      { name: 'conditions', type: 'JSON' },
      { name: 'actions', type: 'JSON' },
      { name: 'priority', type: 'INT' },
      { name: 'is_active', type: 'TINYINT(1)' },
      { name: 'created_by', type: 'INT UNSIGNED', fk: 'users.id' }
    ]
  },
  {
    id: 'irrigation_rule_logs',
    name: 'irrigation_rule_logs',
    cluster: 'inventaris',
    x: 360, y: 1850, w: 240,
    desc: 'Catatan riwayat eksekusi sistem otomasi irigasi pintar',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'rule_id', type: 'INT UNSIGNED', fk: 'irrigation_rules.id' },
      { name: 'irigasi_id', type: 'INT UNSIGNED' },
      { name: 'execution_status', type: 'VARCHAR(50)' },
      { name: 'triggered_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'data_irigasi',
    name: 'data_irigasi',
    cluster: 'inventaris',
    x: 680, y: 1850, w: 240,
    desc: 'Master data teknis saluran & daerah irigasi eksternal',
    columns: [
      { name: 'id', type: 'INT', pk: true },
      { name: 'nama_daerah_irigasi', type: 'VARCHAR(200)' },
      { name: 'luas_baku_ha', type: 'DECIMAL(10,2)' },
      { name: 'panjang_saluran_m', type: 'DECIMAL(10,2)' },
      { name: 'sumber_air', type: 'VARCHAR(150)' }
    ]
  },

  // --- KLASTER 8: NOTIFIKASI & KOMUNIKASI ---
  {
    id: 'notifications',
    name: 'notifications',
    cluster: 'komunikasi',
    x: 990, y: 410, w: 260,
    desc: 'Pemberitahuan in-app per pengguna atas status laporan & info',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'type', type: 'VARCHAR(50)' },
      { name: 'title', type: 'VARCHAR(200)' },
      { name: 'body', type: 'VARCHAR(500)' },
      { name: 'read_at', type: 'TIMESTAMP' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'feedback',
    name: 'feedback',
    cluster: 'komunikasi',
    x: 670, y: 130, w: 250,
    desc: 'Kanal tiket aduan, usulan fitur, & kendala teknis petugas',
    columns: [
      { name: 'id', type: 'BIGINT UNSIGNED', pk: true },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'jenis_feedback', type: 'VARCHAR(50)' },
      { name: 'judul', type: 'VARCHAR(255)' },
      { name: 'prioritas', type: 'VARCHAR(20)' },
      { name: 'status', type: 'VARCHAR(20)' },
      { name: 'vote_count', type: 'INT' },
      { name: 'processed_by', type: 'INT UNSIGNED', fk: 'users.id' }
    ]
  },
  {
    id: 'feedback_votes',
    name: 'feedback_votes',
    cluster: 'komunikasi',
    x: 360, y: 130, w: 240,
    desc: 'Voting dukungan pengguna atas tiket masukan (1 vote/user)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'feedback_id', type: 'BIGINT UNSIGNED', fk: 'feedback.id' },
      { name: 'user_id', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'feedback_status_history',
    name: 'feedback_status_history',
    cluster: 'komunikasi',
    x: 360, y: 340, w: 240,
    desc: 'Riwayat penanganan aduan (diterima, proses, selesai, ditolak)',
    columns: [
      { name: 'id', type: 'INT UNSIGNED', pk: true },
      { name: 'feedback_id', type: 'BIGINT UNSIGNED', fk: 'feedback.id' },
      { name: 'old_status', type: 'VARCHAR(20)' },
      { name: 'new_status', type: 'VARCHAR(20)' },
      { name: 'changed_by', type: 'INT UNSIGNED', fk: 'users.id' },
      { name: 'created_at', type: 'TIMESTAMP' }
    ]
  },
  {
    id: 'curah_hujan',
    name: 'curah_hujan',
    cluster: 'komunikasi',
    x: 680, y: 1530, w: 240,
    desc: 'Data curah hujan harian per kecamatan dari BMKG / NASA POWER',
    columns: [
      { name: 'id', type: 'INT', pk: true },
      { name: 'tanggal', type: 'DATE' },
      { name: 'kecamatan_id', type: 'INT', fk: 'master_kecamatan.id' },
      { name: 'curah_hujan', type: 'DECIMAL(10,2)' },
      { name: 'satuan', type: 'VARCHAR(10)' },
      { name: 'sumber_data', type: 'VARCHAR(255)' }
    ]
  }
];

// Helper to compute height of each card based on column count
tables.forEach(t => {
  t.h = 44 + (t.columns.length * 20) + 12;
});

// Relationships definition for connection lines
const relations = [
  // Wilayah hierarchy
  { from: 'master_kabupaten', to: 'master_kecamatan', type: '1:N', color: '#0F766E' },
  { from: 'master_kecamatan', to: 'master_desa', type: '1:N', color: '#0F766E' },
  { from: 'master_kecamatan', to: 'produksi_gabah', type: '1:N', color: '#0F766E' },
  { from: 'master_kecamatan', to: 'curah_hujan', type: '1:N', color: '#0F766E' },
  
  // Users relations
  { from: 'users', to: 'device_tokens', type: '1:N', color: '#3B82F6' },
  { from: 'users', to: 'jwt_blacklist', type: '1:N', color: '#3B82F6' },
  { from: 'users', to: 'idempotency_keys', type: '1:N', color: '#3B82F6' },
  { from: 'users', to: 'activity_log', type: '1:N', color: '#3B82F6' },
  { from: 'users', to: 'notifications', type: '1:N', color: '#3B82F6' },
  { from: 'users', to: 'feedback', type: '1:N', color: '#EC4899' },
  { from: 'users', to: 'usulan_opt', type: '1:N', color: '#10B981' },

  // Tanaman & OPT
  { from: 'master_opt', to: 'laporan_hama', type: '1:N', color: '#10B981' },
  { from: 'master_opt', to: 'laporan_panen', type: '1:N', color: '#10B981' },
  { from: 'master_opt', to: 'usulan_opt', type: '1:N', color: '#10B981' },
  { from: 'usulan_opt', to: 'usulan_opt_photos', type: '1:N', color: '#10B981' },
  { from: 'usulan_opt', to: 'usulan_opt_status_history', type: '1:N', color: '#10B981' },
  { from: 'laporan_hama', to: 'laporan_hama_tags', type: '1:N', color: '#10B981' },
  { from: 'tags', to: 'laporan_hama_tags', type: '1:N', color: '#10B981' },

  // Laporan & Master
  { from: 'master_desa', to: 'laporan_hama', type: '1:N', color: '#F59E0B' },
  { from: 'master_desa', to: 'laporan_irigasi', type: '1:N', color: '#F59E0B' },
  { from: 'master_desa', to: 'laporan_panen', type: '1:N', color: '#F59E0B' },
  { from: 'master_desa', to: 'laporan_pupuk', type: '1:N', color: '#F59E0B' },
  { from: 'master_desa', to: 'laporan_alat_sarana', type: '1:N', color: '#F59E0B' },
  { from: 'master_desa', to: 'laporan_cuaca', type: '1:N', color: '#F59E0B' },
  { from: 'master_desa', to: 'laporan_lainnya', type: '1:N', color: '#F59E0B' },
  { from: 'master_jenis_laporan', to: 'laporan_lainnya', type: '1:N', color: '#F59E0B' },
  { from: 'laporan_hama', to: 'laporan_status_history', type: '1:N', color: '#F59E0B' },

  // Feedback
  { from: 'feedback', to: 'feedback_votes', type: '1:N', color: '#EC4899' },
  { from: 'feedback', to: 'feedback_status_history', type: '1:N', color: '#EC4899' },

  // IoT
  { from: 'sensor_pengairan', to: 'pembacaan_sensor', type: '1:N', color: '#0EA5E9' },
  { from: 'irrigation_rules', to: 'irrigation_rule_logs', type: '1:N', color: '#0EA5E9' }
];

console.log(`Loaded ${tables.length} entities and ${relations.length} relationships.`);

// 2. Generate SVG File
function generateSVG() {
  const width = 3050;
  const height = 2200;

  let svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg" font-family="'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="12">
  <defs>
    <filter id="cardShadow" x="-10%" y="-10%" width="120%" height="120%">
      <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#0F172A" flood-opacity="0.08"/>
      <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="#0F172A" flood-opacity="0.04"/>
    </filter>
    <filter id="headerShadow" x="-5%" y="-5%" width="110%" height="120%">
      <feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#000000" flood-opacity="0.12"/>
    </filter>
    <marker id="arrow" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto">
      <path d="M1,1.5 L7,4.5 L1,7.5 Z" fill="#64748B"/>
    </marker>
    <marker id="arrowTeal" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto">
      <path d="M1,1.5 L7,4.5 L1,7.5 Z" fill="#0F766E"/>
    </marker>
    <marker id="arrowBlue" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto">
      <path d="M1,1.5 L7,4.5 L1,7.5 Z" fill="#3B82F6"/>
    </marker>
    <marker id="arrowGreen" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto">
      <path d="M1,1.5 L7,4.5 L1,7.5 Z" fill="#10B981"/>
    </marker>
    <marker id="arrowAmber" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto">
      <path d="M1,1.5 L7,4.5 L1,7.5 Z" fill="#F59E0B"/>
    </marker>
    <marker id="arrowRose" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto">
      <path d="M1,1.5 L7,4.5 L1,7.5 Z" fill="#EC4899"/>
    </marker>
    <marker id="arrowCyan" markerWidth="9" markerHeight="9" refX="7" refY="4.5" orient="auto">
      <path d="M1,1.5 L7,4.5 L1,7.5 Z" fill="#0EA5E9"/>
    </marker>
  </defs>

  <!-- Background Grid Pattern -->
  <rect width="100%" height="100%" fill="#F1F5F9"/>
  <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#E2E8F0" stroke-width="1"/>
  </pattern>
  <rect width="100%" height="100%" fill="url(#grid)"/>

  <!-- Top System Header Banner -->
  <rect x="0" y="0" width="${width}" height="70" fill="#0F172A" filter="url(#headerShadow)"/>
  <text x="60" y="42" fill="#F8FAFC" font-size="22" font-weight="700" letter-spacing="-0.5">🌾 SISTEM INFORMASI PERTANIAN JAGAPADI — KABUPATEN JEMBER</text>
  <text x="${width - 60}" y="42" text-anchor="end" fill="#94A3B8" font-size="14" font-weight="500">ENTITY RELATIONSHIP DIAGRAM (ERD) v3.0.0 | September 2026 | BPS Kabupaten Jember</text>

  <!-- Legend Box -->
  <g id="legend" transform="translate(60, 85)">
    <rect width="640" height="34" rx="6" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1"/>
    <text x="14" y="22" font-weight="700" fill="#1E293B" font-size="12">LEGENDA:</text>
    
    <rect x="90" y="10" width="14" height="14" rx="3" fill="#FEF08A" stroke="#CA8A04" stroke-width="1"/>
    <text x="110" y="22" fill="#334155" font-size="11">Primary Key (PK)</text>

    <rect x="220" y="10" width="14" height="14" rx="3" fill="#BAE6FD" stroke="#0284C7" stroke-width="1"/>
    <text x="240" y="22" fill="#334155" font-size="11">Foreign Key (FK)</text>

    <rect x="350" y="10" width="14" height="14" rx="3" fill="#E9D5FF" stroke="#9333EA" stroke-width="1"/>
    <text x="370" y="22" fill="#334155" font-size="11">Unique Key (UK)</text>

    <line x1="480" y1="17" x2="520" y2="17" stroke="#64748B" stroke-width="2" marker-end="url(#arrow)"/>
    <text x="530" y="22" fill="#334155" font-size="11">Relasi 1 : Banyak (1:N)</text>
  </g>

  <!-- Relationship Lines -->
  <g id="connections" opacity="0.85">
`;

  // Draw relationship lines between table cards
  const tableMap = {};
  tables.forEach(t => { tableMap[t.id] = t; });

  relations.forEach(r => {
    const from = tableMap[r.from];
    const to = tableMap[r.to];
    if (!from || !to) return;

    let startX = from.x + from.w;
    let startY = from.y + 40;
    let endX = to.x;
    let endY = to.y + 40;

    // determine optimal connection side
    if (from.x > to.x) {
      startX = from.x;
      endX = to.x + to.w;
    } else if (Math.abs(from.x - to.x) < 50) {
      startX = from.x + (from.w / 2);
      endX = to.x + (to.w / 2);
      if (from.y < to.y) {
        startY = from.y + from.h;
        endY = to.y;
      } else {
        startY = from.y;
        endY = to.y + to.h;
      }
    }

    let midX = (startX + endX) / 2;
    let path = `M ${startX} ${startY} H ${midX} V ${endY} H ${endX}`;

    let marker = 'url(#arrow)';
    if (r.color === '#0F766E') marker = 'url(#arrowTeal)';
    else if (r.color === '#3B82F6') marker = 'url(#arrowBlue)';
    else if (r.color === '#10B981') marker = 'url(#arrowGreen)';
    else if (r.color === '#F59E0B') marker = 'url(#arrowAmber)';
    else if (r.color === '#EC4899') marker = 'url(#arrowRose)';
    else if (r.color === '#0EA5E9') marker = 'url(#arrowCyan)';

    svg += `    <path d="${path}" fill="none" stroke="${r.color}" stroke-width="2" stroke-dasharray="${r.type === 'M:N' ? '5,5' : 'none'}" marker-end="${marker}"/>\n`;
  });

  svg += `  </g>\n\n  <!-- Entity Cards -->\n  <g id="entities">\n`;

  // Draw table cards
  tables.forEach(t => {
    const cluster = clusters[t.cluster] || clusters.users;
    svg += `
    <!-- Card: ${t.name} -->
    <g id="card_${t.id}" class="entity-card" transform="translate(${t.x}, ${t.y})">
      <rect width="${t.w}" height="${t.h}" rx="8" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.2" filter="url(#cardShadow)"/>
      
      <!-- Card Header -->
      <rect width="${t.w}" height="36" rx="8" fill="${cluster.color}"/>
      <rect y="28" width="${t.w}" height="8" fill="${cluster.color}"/>
      <text x="14" y="23" fill="#FFFFFF" font-size="13" font-weight="700" letter-spacing="0.2">${t.name}</text>
      
      <!-- Columns -->
      <g transform="translate(0, 42)">
`;

    t.columns.forEach((col, idx) => {
      const y = idx * 20 + 12;
      const rowBg = idx % 2 === 0 ? '#FFFFFF' : '#F8FAFC';
      svg += `        <rect y="${idx * 20}" width="${t.w}" height="20" fill="${rowBg}"/>\n`;

      if (col.pk) {
        svg += `        <rect x="8" y="${idx * 20 + 3}" width="24" height="14" rx="3" fill="#FEF08A" stroke="#EAB308" stroke-width="0.7"/>\n`;
        svg += `        <text x="20" y="${y}" text-anchor="middle" font-size="8.5" font-weight="800" fill="#854D0E">PK</text>\n`;
      } else if (col.fk) {
        svg += `        <rect x="8" y="${idx * 20 + 3}" width="24" height="14" rx="3" fill="#BAE6FD" stroke="#0284C7" stroke-width="0.7"/>\n`;
        svg += `        <text x="20" y="${y}" text-anchor="middle" font-size="8.5" font-weight="800" fill="#0369A1">FK</text>\n`;
      } else if (col.uk) {
        svg += `        <rect x="8" y="${idx * 20 + 3}" width="24" height="14" rx="3" fill="#E9D5FF" stroke="#9333EA" stroke-width="0.7"/>\n`;
        svg += `        <text x="20" y="${y}" text-anchor="middle" font-size="8.5" font-weight="800" fill="#6B21A8">UK</text>\n`;
      }

      svg += `        <text x="38" y="${y}" fill="#1E293B" font-size="11" font-weight="${col.pk ? '700' : '500'}">${col.name}</text>\n`;
      svg += `        <text x="${t.w - 10}" y="${y}" text-anchor="end" fill="#64748B" font-size="10" font-family="'JetBrains Mono', Consolas, monospace">${col.type}</text>\n`;
    });

    svg += `      </g>
    </g>\n`;
  });

  svg += `  </g>
</svg>`;

  fs.writeFileSync(path.join(__dirname, 'ERD_JAGAPADI.svg'), svg, 'utf8');
  console.log('✅ Generated ERD_JAGAPADI.svg');
}

// 3. Generate Interactive HTML Viewer
function generateHTML() {
  const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Viewer ERD Sistem JAGAPADI — Kabupaten Jember</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0F172A;
      --accent: #2563EB;
      --bg: #F8FAFC;
      --surface: #FFFFFF;
      --border: #CBD5E1;
      --text: #0F172A;
      --muted: #64748B;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow: hidden;
    }
    header {
      background: var(--primary);
      color: #fff;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 10px rgba(0,0,0,0.15);
      z-index: 10;
    }
    .header-title h1 {
      font-size: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .header-title p {
      font-size: 12px;
      color: #94A3B8;
      margin-top: 2px;
    }
    .controls {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .search-box {
      position: relative;
    }
    .search-box input {
      background: #1E293B;
      border: 1px solid #334155;
      color: #fff;
      padding: 6px 12px 6px 30px;
      border-radius: 6px;
      font-size: 13px;
      width: 220px;
      outline: none;
      transition: border 0.2s;
    }
    .search-box input:focus {
      border-color: var(--accent);
    }
    .search-box svg {
      position: absolute;
      left: 8px;
      top: 7px;
      width: 15px;
      height: 15px;
      fill: #94A3B8;
    }
    .btn {
      background: #1E293B;
      border: 1px solid #334155;
      color: #F8FAFC;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 13px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }
    .btn:hover {
      background: #334155;
    }
    .btn-primary {
      background: var(--accent);
      border-color: var(--accent);
    }
    .btn-primary:hover {
      background: #1D4ED8;
    }
    .cluster-filter {
      background: #fff;
      border-bottom: 1px solid var(--border);
      padding: 8px 20px;
      display: flex;
      gap: 8px;
      overflow-x: auto;
      z-index: 5;
    }
    .chip {
      padding: 4px 12px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 500;
      border: 1px solid var(--border);
      background: #F1F5F9;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s;
    }
    .chip.active {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }
    .viewer-container {
      flex: 1;
      position: relative;
      overflow: hidden;
      cursor: grab;
      background: #F8FAFC;
    }
    .viewer-container:active {
      cursor: grabbing;
    }
    #svgWrapper {
      position: absolute;
      transform-origin: 0 0;
      will-change: transform;
    }
    .zoom-toolbar {
      position: absolute;
      bottom: 20px;
      right: 20px;
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      z-index: 10;
    }
    .zoom-toolbar button {
      background: none;
      border: none;
      padding: 10px 12px;
      font-size: 16px;
      cursor: pointer;
      color: #334155;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .zoom-toolbar button:hover {
      background: #F1F5F9;
    }
    .zoom-toolbar .divider {
      height: 1px;
      background: var(--border);
    }
    /* Modal Kamus Data */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 100;
      align-items: center;
      justify-content: center;
    }
    .modal.show { display: flex; }
    .modal-content {
      background: #fff;
      width: 90%;
      max-width: 680px;
      max-height: 80vh;
      border-radius: 10px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .modal-header {
      padding: 16px 20px;
      background: var(--primary);
      color: #fff;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .modal-body {
      padding: 20px;
      overflow-y: auto;
    }
    .table-spec {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px;
      font-size: 13px;
    }
    .table-spec th, .table-spec td {
      padding: 8px 12px;
      border: 1px solid #E2E8F0;
      text-align: left;
    }
    .table-spec th {
      background: #F1F5F9;
      font-weight: 600;
    }
    .highlighted {
      filter: drop-shadow(0 0 12px #3B82F6) !important;
    }
    .dimmed {
      opacity: 0.15 !important;
      transition: opacity 0.3s;
    }
  </style>
</head>
<body>

  <header>
    <div class="header-title">
      <h1>🌾 ERD Sistem JAGAPADI Kabupaten Jember</h1>
      <p>38 Entitas Relasional Terorganisir | Responsive Viewport Zoom & Pan</p>
    </div>
    <div class="controls">
      <div class="search-box">
        <svg viewBox="0 0 24 24"><path d="M10 18a7.952 7.952 0 0 0 4.897-1.688l4.396 4.396 1.414-1.414-4.396-4.396A7.952 7.952 0 0 0 18 10c0-4.411-3.589-8-8-8s-8 3.589-8 8 3.589 8 8 8zm0-14c3.309 0 6 2.691 6 6s-2.691 6-6 6-6-2.691-6-6 2.691-6 6-6z"/></svg>
        <input type="text" id="searchInput" placeholder="Cari entitas / tabel...">
      </div>
      <a href="ERD_JAGAPADI.png" download="ERD_JAGAPADI.png" class="btn btn-primary">⬇ Unduh PNG</a>
      <a href="ERD_JAGAPADI.svg" download="ERD_JAGAPADI.svg" class="btn">⬇ Unduh SVG</a>
      <button onclick="window.print()" class="btn">🖨 Cetak PDF</button>
    </div>
  </header>

  <div class="cluster-filter">
    <div class="chip active" onclick="filterCluster('all', this)">Semua Klaster (38)</div>
    <div class="chip" onclick="filterCluster('users', this)">Pengguna & Keamanan</div>
    <div class="chip" onclick="filterCluster('wilayah', this)">Lahan & Wilayah</div>
    <div class="chip" onclick="filterCluster('tanaman', this)">Tanaman & OPT</div>
    <div class="chip" onclick="filterCluster('laporan', this)">Pelaporan Lapangan</div>
    <div class="chip" onclick="filterCluster('panen', this)">Panen & BPS</div>
    <div class="chip" onclick="filterCluster('keuangan', this)">Keuangan & Pasar</div>
    <div class="chip" onclick="filterCluster('inventaris', this)">Inventaris & IoT</div>
    <div class="chip" onclick="filterCluster('komunikasi', this)">Notifikasi & Feedback</div>
  </div>

  <div class="viewer-container" id="viewport">
    <div id="svgWrapper">
      <!-- SVG Inlined for instant manipulation -->
      ${fs.readFileSync(path.join(__dirname, 'ERD_JAGAPADI.svg'), 'utf8').replace(/<\?xml.*?\?>/, '')}
    </div>

    <div class="zoom-toolbar">
      <button onclick="zoom(1.2)" title="Zoom In">+</button>
      <div class="divider"></div>
      <button onclick="zoom(0.8)" title="Zoom Out">−</button>
      <div class="divider"></div>
      <button onclick="resetZoom()" title="Reset Zoom">⟲</button>
    </div>
  </div>

  <!-- Modal Kamus Data Detail -->
  <div class="modal" id="dataModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Detail Entitas</h3>
        <button onclick="closeModal()" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;">✕</button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>

  <script>
    const wrapper = document.getElementById('svgWrapper');
    const viewport = document.getElementById('viewport');
    let scale = 0.45;
    let pointX = 20;
    let pointY = 20;
    let isPanning = false;
    let startX = 0;
    let startY = 0;

    function setTransform() {
      wrapper.style.transform = \`translate(\${pointX}px, \${pointY}px) scale(\${scale})\`;
    }

    setTransform();

    viewport.onmousedown = function (e) {
      if (e.target.closest('.zoom-toolbar')) return;
      isPanning = true;
      startX = e.clientX - pointX;
      startY = e.clientY - pointY;
    };

    window.onmousemove = function (e) {
      if (!isPanning) return;
      pointX = e.clientX - startX;
      pointY = e.clientY - startY;
      setTransform();
    };

    window.onmouseup = function () {
      isPanning = false;
    };

    viewport.onwheel = function (e) {
      e.preventDefault();
      const xs = (e.clientX - pointX) / scale;
      const ys = (e.clientY - pointY) / scale;
      const delta = -e.deltaY;
      if (delta > 0) scale *= 1.15;
      else scale /= 1.15;
      scale = Math.min(Math.max(0.1, scale), 3);
      pointX = e.clientX - xs * scale;
      pointY = e.clientY - ys * scale;
      setTransform();
    };

    function zoom(factor) {
      scale *= factor;
      scale = Math.min(Math.max(0.1, scale), 3);
      setTransform();
    }

    function resetZoom() {
      scale = 0.45;
      pointX = 20;
      pointY = 20;
      setTransform();
    }

    // Filter by Cluster
    const tableData = ${JSON.stringify(tables)};
    function filterCluster(cluster, el) {
      document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
      el.classList.add('active');

      tableData.forEach(t => {
        const card = document.getElementById('card_' + t.id);
        if (!card) return;
        if (cluster === 'all' || t.cluster === cluster) {
          card.classList.remove('dimmed');
        } else {
          card.classList.add('dimmed');
        }
      });
    }

    // Search Box
    document.getElementById('searchInput').addEventListener('input', function(e) {
      const q = e.target.value.toLowerCase().trim();
      tableData.forEach(t => {
        const card = document.getElementById('card_' + t.id);
        if (!card) return;
        const match = t.name.toLowerCase().includes(q) || t.desc.toLowerCase().includes(q) || t.columns.some(c => c.name.toLowerCase().includes(q));
        if (!q) {
          card.classList.remove('dimmed', 'highlighted');
        } else if (match) {
          card.classList.remove('dimmed');
          card.classList.add('highlighted');
        } else {
          card.classList.add('dimmed');
          card.classList.remove('highlighted');
        }
      });
    });

    // Card Click for Data Dictionary Modal
    tableData.forEach(t => {
      const card = document.getElementById('card_' + t.id);
      if (!card) return;
      card.style.cursor = 'pointer';
      card.onclick = function() {
        showModal(t);
      };
    });

    function showModal(table) {
      document.getElementById('modalTitle').innerText = 'Kamus Data: ' + table.name;
      let html = \`<p style="font-size:14px;color:#475569;margin-bottom:15px;">\${table.desc}</p>
        <table class="table-spec">
          <thead>
            <tr>
              <th>Kolom</th>
              <th>Tipe Data</th>
              <th>Kunci / Relasi</th>
            </tr>
          </thead>
          <tbody>\`;
      
      table.columns.forEach(c => {
        let tag = '-';
        if (c.pk) tag = '<span style="background:#FEF08A;color:#854D0E;padding:2px 6px;border-radius:4px;font-weight:700;">PRIMARY KEY</span>';
        else if (c.fk) tag = \`<span style="background:#BAE6FD;color:#0369A1;padding:2px 6px;border-radius:4px;font-weight:700;">FK \${c.fk}</span>\`;
        else if (c.uk) tag = '<span style="background:#E9D5FF;color:#6B21A8;padding:2px 6px;border-radius:4px;font-weight:700;">UNIQUE</span>';
        html += \`<tr>
          <td style="font-weight:600;">\${c.name}</td>
          <td style="font-family:monospace;color:#0F766E;">\${c.type}</td>
          <td>\${tag}</td>
        </tr>\`;
      });
      html += \`</tbody></table>\`;
      document.getElementById('modalBody').innerHTML = html;
      document.getElementById('dataModal').classList.add('show');
    }

    function closeModal() {
      document.getElementById('dataModal').classList.remove('show');
    }
  </script>
</body>
</html>`;

  fs.writeFileSync(path.join(__dirname, 'index.html'), html, 'utf8');
  console.log('✅ Generated index.html interactive viewer');
}

generateSVG();
generateHTML();
