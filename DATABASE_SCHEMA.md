# Database Schema Ringkas

Sumber ringkasan: `jagapadi.sql`, migration di `database/migrations` dan `migrations`, serta model di `app/models`.

Database lokal default: `bpsjembe_jagapadi`.

Catatan:
- Schema bisa drift antar environment. Verifikasi dengan `DESCRIBE` atau `information_schema` sebelum maintenance data.
- Jangan menjalankan perubahan destructive tanpa backup dan review.
- Untuk soft-delete, cek kolom `deleted_at` jika tersedia.

## Tabel Wilayah

### `master_kabupaten`
- Master kabupaten/kota.
- Kolom penting: `id`, `kode_kabupaten`, `nama_kabupaten`, `provinsi`, `tanggal_dibuat`, `deleted_at`, audit user jika sudah dimigrasi.
- Kode BPS kabupaten/kota Jawa Timur memakai 4 digit, contoh Jember `3509`.

### `master_kecamatan`
- Master kecamatan.
- Kolom penting: `id`, `kabupaten_id`, `kode_kecamatan`, `nama_kecamatan`, `deleted_at`.
- `kode_kecamatan` MFD/BPS memakai 7 digit.
- Parent valid jika `LEFT(kode_kecamatan, 4) = master_kabupaten.kode_kabupaten`.

### `master_desa`
- Master desa/kelurahan.
- Kolom penting: `id`, `kecamatan_id`, `kode_desa`, `nama_desa`, `kode_pos`, `deleted_at`.
- `kode_desa` MFD/BPS memakai 10 digit.
- Parent valid jika `LEFT(kode_desa, 7) = master_kecamatan.kode_kecamatan`.

### `kabupaten` dan `kecamatan_jember`
- Tabel legacy/pendukung untuk daftar wilayah tertentu.
- Jangan diasumsikan sebagai sumber utama master wilayah tanpa cek controller/model terkait.

## Tabel User dan Audit

### `users`
- User aplikasi dan role.
- Dipakai oleh auth, API, laporan, feedback, dan audit.

### `password_resets`
- Token reset password jika migration terkait sudah dijalankan.

### `audit_log_wilayah`
- Audit perubahan master wilayah.
- Kolom penting: `table_name`, `record_id`, `action`, `old_values`, `new_values`, `user_id`.

### `activity_log`
- Log aktivitas umum.

## Tabel Laporan Hama dan OPT

### `laporan_hama`
- Laporan serangan hama/OPT.
- Kolom penting: `user_id`, `master_opt_id`, `tanggal`, `lokasi`, `latitude`, `longitude`, `tingkat_keparahan`, `populasi`, `luas_serangan`, `foto_url`, `status`, `kabupaten_id`, `kecamatan_id`, `desa_id`.
- Status historis yang terlihat: `Draf`, `Submitted`, `Diverifikasi`, `Ditolak`; migration terbaru menambah status arsip jika sudah diterapkan.

### `master_opt`
- Master organisme pengganggu tanaman.
- Dipakai untuk referensi laporan hama/OPT.

### `honor_pelaporan`
- Data honor pelaporan untuk integrasi/API eksternal.

## Tabel Irigasi dan IoT

### `data_irigasi`
- Data observasi irigasi.
- Kolom penting: `tanggal`, `daerah_irigasi`, `kecamatan`, `luas_sawah`, `debit_air`, `status_pintu`, `keterangan`.

### `laporan_irigasi`
- Laporan irigasi user.

### `irrigation_rules`
- Rule engine irigasi.

### `irrigation_rule_logs`, `irrigation_adaptive_thresholds`
- Log dan threshold rule irigasi.

### `pembacaan_sensor`
- Model tersedia untuk pembacaan sensor; cek schema environment karena tidak selalu muncul di dump utama.

## Tabel Cuaca, Harga, Produksi, dan Analitik

### `curah_hujan`
- Data curah hujan.

### `kecepatan_angin`
- Data kecepatan angin.

### `harga_komoditas`
- Data harga komoditas.

### `data_pertanian_bps`
- Data pertanian dari BPS.

### `produksi_gabah`
- Data produksi gabah untuk dashboard padi/storytelling.

### `evaluasi_akurasi_panen`
- Evaluasi akurasi panen.

### `analisis_produksi_bulanan`
- Hasil analisis/storytelling produksi bulanan.

## Tabel Feedback

### `feedback`
- Masukan user: bug, fitur baru, atau peningkatan.
- Kolom penting: `jenis_feedback`, `judul`, `deskripsi`, `prioritas`, `status`, `attachment_url`, `admin_notes`.

### `feedback_status_history`
- Riwayat perubahan status feedback.

### `feedback_votes`
- Vote/dukungan user pada feedback.

## Log dan Scraping

Tabel log yang sering muncul:

- `bps_scraping_logs`
- `curah_hujan_logs`
- `harga_komoditas_logs`
- `irigasi_scraping_logs`
- `kecepatan_angin_logs`
- `gabah_beras_logs`
- `evaluasi_akurasi_logs`
- `system_alerts`

Gunakan tabel log untuk diagnosis, tapi jangan commit output log lokal.
