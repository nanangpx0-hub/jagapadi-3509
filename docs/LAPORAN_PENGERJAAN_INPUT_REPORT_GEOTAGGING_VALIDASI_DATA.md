# LAPORAN PENGERJAAN BACKEND

## Membangun Fitur Input Report, Geotagging, dan Data Validation pada Aplikasi JAGAPADI

**Nama aplikasi:** JAGAPADI — Jember Agrikultur Gapai Prestasi Digital  
**Lingkup laporan:** Backend PHP, REST API, dan database  
**Tanggal laporan:** 15 Agustus 2026  
**Status:** Fitur inti backend telah diimplementasikan dan diuji

---

## 1. Pendahuluan

Backend JAGAPADI bertanggung jawab menerima laporan pertanian, memvalidasi data, menyimpan koordinat lokasi, mengendalikan status laporan, dan memastikan setiap operasi dilakukan oleh pengguna yang berhak. Backend menjadi sumber kebenaran utama sehingga data dari web maupun aplikasi mobile tidak langsung dipercaya tanpa pemeriksaan server.

Pengerjaan difokuskan pada tiga fitur backend yang saling terhubung:

1. **Input Report**, yaitu API dan service untuk membuat, memperbarui, menyimpan Draf, dan mengirim laporan.
2. **Geotagging**, yaitu penerimaan, validasi, dan penyimpanan latitude serta longitude bersama laporan.
3. **Data Validation**, yaitu validasi kelengkapan, tipe, rentang, relasi wilayah, status, kepemilikan, dan keamanan data sebelum masuk ke database.

## 2. Tujuan

Tujuan pembangunan backend adalah:

- menyediakan REST API laporan yang konsisten;
- mendukung penyimpanan laporan parsial sebagai Draf;
- menerapkan validasi lebih ketat ketika laporan dikirim;
- menyimpan koordinat lokasi bersama laporan;
- memastikan nomor laporan hanya dibuat ketika status menjadi `Submitted`;
- mencegah perubahan status secara langsung dari request client;
- membatasi petugas pada laporan miliknya;
- memastikan hanya admin yang dapat memverifikasi laporan;
- mengamankan upload foto dan input pengguna;
- menyediakan respons error per field;
- mencatat aktivitas untuk kebutuhan audit.

## 3. Ruang Lingkup Backend

Pekerjaan backend meliputi:

- route REST API `/api/v1`;
- autentikasi JWT;
- controller laporan;
- service layer dan aturan bisnis;
- validator Draf dan Submit;
- model dan query database;
- penyimpanan latitude dan longitude;
- validasi wilayah kabupaten, kecamatan, dan desa;
- upload foto aman;
- pembuatan nomor laporan secara atomik;
- transaksi database;
- workflow status laporan;
- activity log dan notifikasi;
- penanganan error HTTP;
- pengujian unit validator backend.

Modul laporan hama digunakan sebagai implementasi utama. Pola controller dan service yang sama diterapkan pada laporan irigasi, pupuk, panen, cuaca, serta alat dan sarana.

## 4. Teknologi Backend

| Komponen | Teknologi |
|---|---|
| Bahasa | PHP 8.2 dengan `strict_types` |
| Arsitektur | MVC ringan dengan Controller–Service–Model |
| API | REST JSON `/api/v1` |
| Autentikasi | JWT Bearer Token |
| Database | MariaDB/MySQL `utf8mb4` |
| Akses database | PDO dan prepared statement |
| Upload | Validasi magic bytes, MIME, ekstensi, dan ukuran |
| Pengujian | PHPUnit 11.5 |

## 5. Arsitektur Backend

```text
HTTP Request
     │
     ▼
Router dan Middleware
     ├── JWT Authentication
     ├── Role Authorization
     └── Rate Limiting
     │
     ▼
API Controller
     ├── Membaca input
     ├── Memproses upload
     └── Mengirim response JSON
     │
     ▼
Service Layer
     ├── Validasi Draf/Submit
     ├── Ownership dan workflow status
     ├── Transaksi dan nomor laporan
     ├── Activity log
     └── Notifikasi
     │
     ▼
Model / PDO Prepared Statement
     │
     ▼
MariaDB/MySQL
```

Pemisahan ini memastikan controller tetap menangani protokol HTTP, service menjalankan aturan bisnis, dan model berfokus pada akses database.

## 6. Implementasi Input Report

### 6.1 Endpoint utama

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/api/v1/laporan-hama` | Menampilkan daftar sesuai scope pengguna |
| `POST` | `/api/v1/laporan-hama` | Membuat Draf atau langsung Submit |
| `GET` | `/api/v1/laporan-hama/{id}` | Menampilkan detail laporan |
| `PUT/PATCH` | `/api/v1/laporan-hama/{id}` | Memperbarui Draf |
| `DELETE` | `/api/v1/laporan-hama/{id}` | Menghapus Draf |
| `POST` | `/api/v1/laporan-hama/{id}/submit` | Mengirim Draf |
| `POST` | `/api/v1/laporan-hama/{id}/foto` | Mengunggah foto laporan |
| `POST` | `/api/v1/laporan-hama/{id}/verify` | Verifikasi oleh admin |
| `POST` | `/api/v1/laporan-hama/{id}/reject` | Penolakan oleh admin |

### 6.2 Pembuatan Draf

Proses pembuatan Draf pada backend adalah:

1. Middleware memvalidasi JWT dan mengambil pengguna aktif.
2. Controller membaca input request dan file foto.
3. Service menjalankan `validateDraft()`.
4. Hanya field yang terdapat dalam whitelist yang diproses.
5. Backend menambahkan `user_id`, status `Draf`, dan IP pengirim.
6. Model menyimpan data ke database.
7. Activity log mencatat pembuatan Draf.
8. Cache dashboard dihapus agar data terbaru dapat dimuat.
9. API mengembalikan HTTP `201` dan data Draf.

Draf boleh tidak lengkap, tetapi field yang sudah dikirim tetap harus memenuhi tipe dan rentang yang ditentukan. Nomor laporan belum dibuat pada tahap ini.

### 6.3 Pembaruan Draf

Sebelum memperbarui laporan, backend memeriksa:

- laporan tersedia;
- laporan dapat diakses oleh pengguna aktif;
- petugas merupakan pemilik laporan;
- status laporan masih dapat diedit;
- client tidak mengubah status melalui endpoint update;
- setiap field yang dikirim lulus validasi Draf.

Jika tidak ada field yang berubah, API tetap memberikan respons sukses tanpa menjalankan pembaruan yang tidak diperlukan.

### 6.4 Pengiriman laporan

Pengiriman laporan menggunakan validasi Submit yang lebih ketat. Backend:

1. mengambil data Draf yang sudah tersimpan;
2. menggabungkannya dengan input terbaru;
3. memvalidasi seluruh field wajib;
4. membuka transaksi database;
5. membuat nomor laporan secara atomik;
6. mengubah status menjadi `Submitted`;
7. menyimpan perubahan;
8. mencatat activity log;
9. melakukan commit;
10. mengirim notifikasi kepada admin.

Jika terjadi kegagalan, transaksi dibatalkan sehingga nomor dan status laporan tidak tersimpan sebagian.

### 6.5 Whitelist input

Service hanya menerima field berikut untuk Draf laporan hama:

- `master_opt_id`;
- `tanggal`;
- `kabupaten_id`, `kecamatan_id`, dan `desa_id`;
- `lokasi` dan `alamat_lengkap`;
- `latitude` dan `longitude`;
- `tingkat_keparahan`;
- `luas_serangan`;
- `populasi`;
- `foto_url`;
- `catatan`.

Field seperti `user_id`, `status`, `nomor_laporan`, dan `verified_by` tidak diterima langsung dari client. Nilai tersebut ditentukan oleh backend sesuai aturan bisnis.

## 7. Implementasi Geotagging pada Backend

### 7.1 Penerimaan koordinat

Backend menerima dua field geospasial:

- `latitude`;
- `longitude`.

Keduanya disimpan pada tabel laporan sebagai nilai desimal. Koordinat dapat disimpan pada Draf dan digunakan kembali ketika Draf dikirim.

### 7.2 Validasi dasar koordinat

Validator laporan memeriksa:

- latitude harus numerik dan berada pada rentang -90 sampai 90;
- longitude harus numerik dan berada pada rentang -180 sampai 180;
- nilai di luar rentang menghasilkan error pada field terkait;
- nilai kosong masih diperbolehkan pada Draf.

Validasi dilakukan kembali di server walaupun client telah melakukan validasi sendiri.

### 7.3 Validator wilayah Kabupaten Jember

Backend proyek juga memiliki helper `GeoValidator` dengan batas berikut:

| Aturan | Nilai |
|---|---|
| Latitude minimum | -8,480000 |
| Latitude maksimum | -7,960000 |
| Longitude minimum | 113,280000 |
| Longitude maksimum | 113,980000 |
| Batas indikasi perairan selatan | latitude di bawah -8,390000 |

Helper tersebut menolak:

- titik di luar bounding box Kabupaten Jember;
- titik terlalu utara, barat, atau timur;
- titik yang terindikasi berada di perairan selatan.

### 7.4 Status integrasi geofence

`GeoValidator` sudah tersedia dan lulus pengujian unit. Namun, validator laporan utama masih menggunakan batas koordinat global. Artinya, request API dengan koordinat valid secara global tetapi berada di luar Kabupaten Jember masih berpotensi diterima.

Integrasi `GeoValidator::validateJemberCoordinates()` ke `validateSubmit()` merupakan pekerjaan penguatan yang direkomendasikan. Validasi ini wajib berada di backend agar tidak dapat dilewati melalui request langsung.

Untuk akurasi produksi yang lebih tinggi, bounding box sebaiknya diganti atau dilengkapi dengan metode point-in-polygon menggunakan GeoJSON batas administratif resmi.

## 8. Implementasi Data Validation

### 8.1 Validasi Draf

| Field | Aturan backend |
|---|---|
| `tanggal` | Format harus `YYYY-MM-DD` |
| `master_opt_id` | OPT harus tersedia dan aktif |
| `kabupaten_id` | Kabupaten harus valid dan dibatasi pada Jember |
| `kecamatan_id` | Harus valid dan sesuai kabupaten |
| `desa_id` | Harus valid dan sesuai kecamatan |
| `tingkat_keparahan` | `Ringan`, `Sedang`, atau `Berat` |
| `luas_serangan` | 0 sampai 9.999,99 |
| `populasi` | Tidak boleh negatif |
| `latitude` | -90 sampai 90 |
| `longitude` | -180 sampai 180 |
| `lokasi` | Maksimal 255 karakter |
| `alamat_lengkap` | Maksimal 300 karakter |
| `catatan` | Maksimal 5.000 karakter |

### 8.2 Validasi Submit

Ketika laporan dikirim, field berikut wajib tersedia:

- tanggal;
- jenis OPT;
- kabupaten;
- kecamatan;
- desa;
- tingkat keparahan;
- luas serangan;
- populasi;
- foto laporan.

Setelah pemeriksaan kelengkapan, backend menjalankan kembali seluruh validasi Draf dan validasi relasi referensial.

### 8.3 Validasi relasi wilayah

Backend tidak hanya memastikan ID tersedia, tetapi juga memeriksa hubungan data:

- kecamatan harus berada dalam kabupaten yang dipilih;
- desa harus berada dalam kecamatan yang dipilih;
- kabupaten laporan dibatasi pada Kabupaten Jember;
- OPT harus aktif ketika digunakan pada laporan.

Validasi ini mencegah request yang menggabungkan ID wilayah secara tidak konsisten.

### 8.4 Respons validasi

Jika validasi gagal, backend mengembalikan HTTP `422` dengan struktur yang memuat:

- kode error `ValidationError`;
- pesan umum bahwa data tidak valid;
- daftar kesalahan berdasarkan nama field.

Contoh konseptual:

```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Data laporan tidak valid",
  "errors": {
    "tanggal": "Tanggal wajib diisi.",
    "foto": "Foto laporan wajib disertakan sebelum laporan dapat dikirim."
  }
}
```

## 9. Keamanan Backend

### 9.1 Autentikasi dan otorisasi

- setiap endpoint laporan menggunakan JWT;
- pengguna nonaktif ditolak;
- petugas hanya dapat mengakses laporan miliknya;
- admin memiliki akses sesuai fungsi verifikasi;
- verifikasi hanya dapat dilakukan terhadap status `Submitted`;
- Draf tidak dapat diverifikasi;
- perubahan status tidak dapat dilakukan melalui endpoint update umum.

### 9.2 Keamanan database

- query menggunakan PDO prepared statement;
- input tidak disisipkan langsung ke SQL;
- operasi Submit menggunakan transaksi;
- foreign key menjaga konsistensi pengguna, OPT, dan wilayah;
- nomor laporan dibuat melalui counter atomik;
- status dan nomor laporan ditentukan server.

### 9.3 Keamanan upload foto

Backend menjalankan:

- validasi status upload;
- pemeriksaan magic bytes;
- pemeriksaan MIME menggunakan isi file;
- whitelist ekstensi gambar;
- batas ukuran maksimum 10 MB;
- nama file acak;
- perlindungan terhadap path traversal;
- penghapusan foto apabila penyimpanan laporan gagal.

Client tidak diperbolehkan menetapkan `foto_url` secara bebas. URL foto ditentukan dari hasil upload yang telah diverifikasi server.

### 9.4 Audit

Backend mencatat:

- pengguna pelaku;
- jenis aktivitas;
- tabel dan ID laporan;
- deskripsi aktivitas;
- alamat IP;
- user agent;
- waktu aktivitas.

Aktivitas yang dicatat mencakup pembuatan Draf, pembaruan, penghapusan, Submit, verifikasi, penolakan, dan pengarsipan.

## 10. Workflow Status Laporan

```text
Draf ──Submit──> Submitted ──Verifikasi──> Diverifikasi
                     │
                     └──Tolak──> Ditolak ──Perbaiki/Kirim Ulang──> Submitted

Diverifikasi ──Arsipkan──> Diarsipkan
```

Aturan penting:

- nomor laporan hanya dibuat saat Submit;
- Draf hanya dapat diubah atau dihapus oleh pemiliknya;
- status tidak dapat diubah langsung dari payload client;
- admin saja yang dapat memverifikasi atau menolak;
- setiap transisi diperiksa oleh service;
- perubahan status penting dicatat dalam riwayat dan activity log.

## 11. Penanganan Error HTTP

| Status | Kondisi |
|---|---|
| `200` | Operasi berhasil |
| `201` | Laporan/Draf berhasil dibuat |
| `400` | ID atau format request tidak valid |
| `401` | JWT tidak tersedia atau tidak valid |
| `403` | Pengguna tidak memiliki izin |
| `404` | Laporan tidak ditemukan atau tidak dapat diakses |
| `409` | Konflik status/workflow |
| `422` | Data gagal divalidasi |
| `500` | Kegagalan internal server |

## 12. Pengujian Backend

Pengujian terfokus dijalankan pada 15 Agustus 2026 menggunakan PHP 8.2.32 dan PHPUnit 11.5.56.

### 12.1 Pengujian `GeoValidator`

Kasus yang diuji:

- koordinat Alun-alun Jember diterima;
- koordinat Tanggul diterima;
- koordinat Ambulu daratan diterima;
- koordinat Surabaya ditolak;
- koordinat Jakarta ditolak;
- titik terlalu utara, barat, dan timur ditolak;
- titik di perairan selatan ditolak.

Hasil: **3 test, 18 assertion, seluruhnya lulus**.

### 12.2 Pengujian `LaporanHamaValidator`

Kasus yang diuji:

- Draf kosong diterima;
- Draf parsial yang valid diterima;
- enum tingkat keparahan tidak valid ditolak;
- latitude dan longitude tidak valid ditolak;
- luas serangan negatif ditolak;
- luas serangan melebihi batas ditolak;
- Submit kosong menghasilkan error seluruh field wajib;
- tanggal tidak valid ditolak;
- panjang lokasi dan alamat diperiksa;
- Submit tanpa foto ditolak.

Hasil: **14 test, 22 assertion, seluruhnya lulus**.

### 12.3 Ringkasan

| Test suite | Test | Assertion | Hasil |
|---|---:|---:|---|
| GeoValidator | 3 | 18 | Lulus |
| LaporanHamaValidator | 14 | 22 | Lulus |
| **Total** | **17** | **40** | **Seluruhnya lulus** |

## 13. Hasil Pengerjaan Backend

Hasil yang telah dicapai:

- REST API laporan tersedia;
- Draf dapat dibuat, diperbarui, dan dihapus;
- laporan lengkap dapat dikirim;
- koordinat dapat diterima dan disimpan bersama laporan;
- validasi Draf dan Submit dipisahkan sesuai kebutuhan bisnis;
- relasi kabupaten–kecamatan–desa diperiksa server;
- nomor laporan dibuat atomik ketika Submit;
- manipulasi status dan ownership dicegah;
- upload foto dilindungi validasi keamanan;
- aktivitas penting tercatat;
- error API diberikan menggunakan status HTTP yang sesuai;
- pengujian terfokus backend lulus seluruhnya.

## 14. Risiko dan Rekomendasi

1. Integrasikan `GeoValidator` ke validator Submit seluruh jenis laporan.
2. Gunakan polygon administratif resmi Kabupaten Jember untuk validasi lokasi yang lebih akurat.
3. Tambahkan kolom metadata `location_accuracy`, `location_captured_at`, dan `location_provider`.
4. Pertimbangkan deteksi koordinat palsu sebagai indikator audit, bukan satu-satunya alasan penolakan.
5. Tambahkan feature test API untuk JWT, ownership, Draf, Submit, upload, dan workflow status.
6. Tambahkan pengujian transaksi untuk memastikan rollback nomor laporan saat terjadi kegagalan.
7. Terapkan validasi geospasial yang sama pada laporan irigasi, pupuk, panen, cuaca, dan alat/sarana.
8. Dokumentasikan schema respons error seluruh endpoint dalam OpenAPI.

## 15. Kesimpulan

Backend fitur Input Report, Geotagging, dan Data Validation JAGAPADI telah dibangun menggunakan pemisahan controller, service, validator, dan model. Backend mendukung Draf dan Submit, menyimpan koordinat, mengendalikan workflow status, memvalidasi data dan relasi wilayah, mengamankan upload foto, serta menerapkan autentikasi dan otorisasi.

Pengujian terfokus menghasilkan 17 test dan 40 assertion yang seluruhnya lulus. Fitur inti backend telah memenuhi kebutuhan dasar. Penguatan utama yang masih direkomendasikan adalah integrasi geofence Kabupaten Jember pada validasi Submit server dan peningkatan validasi lokasi dari bounding box menjadi polygon administratif resmi.

## 16. Referensi File Backend

- `backend/app/Controllers/Api/LaporanHamaController.php`
- `backend/app/Services/LaporanHamaService.php`
- `backend/app/Helpers/LaporanHamaValidator.php`
- `backend/app/Helpers/LaporanStatus.php`
- `backend/app/Helpers/SecureImageUploader.php`
- `backend/app/Models/LaporanHama.php`
- `backend/app/Middleware/ApiAuthMiddleware.php`
- `backend/database/migrations/005_create_laporan_hama_table.sql`
- `backend/database/migrations/020_create_laporan_status_history.sql`
- `app/helpers/GeoValidator.php`
- `tests/Unit/GeoValidatorTest.php`
- `backend/tests/Unit/LaporanHamaValidatorTest.php`

