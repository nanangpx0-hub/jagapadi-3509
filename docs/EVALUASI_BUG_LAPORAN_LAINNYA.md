# Laporan Evaluasi Bug — Modul Laporan Lainnya

**Tanggal Evaluasi**: 2026-08-04
**Modul**: Laporan Lainnya (Web Controller, API Controller, Model, Views)
**Evaluator**: Sistem Evaluasi Otomatis

---

## Ringkasan

| Severity | Jumlah | Deskripsi |
|----------|--------|-----------|
| Kritis | 4 | Bug yang menyebabkan data salah, workflow rusak, atau SQL error |
| Tinggi | 6 | Bug yang memengaruhi keamanan, UX, atau fungsionalitas utama |
| Sedang | 2 | Bug yang memengaruhi konsistensi atau akurasi data |
| Rendah | 1 | Bug minor / improvemen |
| **Total** | **13** | |

---

## Bug Kritis

### BUG-001: Status Laporan Salah saat Create (Web)
- **File**: `app/controllers/LaporanLainnyaController.php:355`
- **Severity**: Kritis
- **Deskripsi**: Method `store()` mengatur `status` ke `'submitted'` alih-alih `'draft'`. Saat pengguna membuat laporan baru melalui web form, laporan langsung masuk status "Submitted" tanpa melalui tahap "Draf". Ini melanggar workflow `Draft → Submitted → Diverifikasi`.
- **Langkah Reproduksi**:
  1. Login sebagai petugas
  2. Buat laporan baru via `/laporan-lainnya/create`
  3. Isi semua field wajib dan simpan
  4. Cek database — status laporan adalah `submitted`, bukan `draft`
  5. Pengguna tidak dapat mengedit laporan karena `canEdit()` mensyaratkan status `draft`
- **Dampak**: Pengguna tidak dapat mengedit laporan setelah disimpan. Workflow draft sepenuhnya rusak.
- **Perbaikan**: Ubah `'submitted'` menjadi `'draft'` pada line 355.

### BUG-002: submitReport() Melewati Status 'Submitted'
- **File**: `app/models/LaporanLainnya.php:167-174`
- **Severity**: Kritis
- **Deskripsi**: Method `submitReport()` mengatur status langsung ke `'verified'` alih-alih `'submitted'`. Workflow seharusnya `Draft → Submitted → Diverifikasi`, tetapi model melompat dari `Draft` langsung ke `Verified`.
- **Langkah Reproduksi**:
  1. Buat laporan dengan status `draft`
  2. Panggil `submitReport()`
  3. Status berubah langsung ke `verified`, bukan `submitted`
- **Dampak**: Status `submitted` tidak pernah tercapai. Laporan yang seharusnya menunggu verifikasi admin langsung terverifikasi otomatis.
- **Perbaikan**: Ubah `'verified'` menjadi `'submitted'` pada method `submitReport()`.

### BUG-003: getCountWithFilters() — Search Query Tanpa JOIN
- **File**: `app/models/LaporanLainnya.php:111-115`
- **Severity**: Kritis
- **Deskripsi**: Method `getCountWithFilters()` memiliki filter `search` yang merujuk ke `mjl.nama` dan `u.nama_lengkap`, tetapi method ini tidak memiliki `leftJoin` untuk tabel `master_jenis_laporan` maupun `users`. Ini menyebabkan SQL error saat fitur pencarian digunakan.
- **Langkah Reproduksi**:
  1. Buka halaman `/laporan-lainnya`
  2. Masukkan keyword pada kolom pencarian
  3. Submit form
  4. SQL error terjadi karena tabel `mjl` dan `u` tidak dikenal dalam query `getCountWithFilters()`
- **Dampak**: Fitur pencarian pada halaman daftar laporan tidak berfungsi dan menyebabkan error 500.
- **Perbaikan**: Tambahkan `leftJoin` yang diperlukan ke method `getCountWithFilters()`.

### BUG-004: getStatsByJenis() — OR Condition pada NULL Dates
- **File**: `app/models/LaporanLainnya.php:233`
- **Severity**: Kritis
- **Deskripsi**: SQL query di `getStatsByJenis()` menggunakan `OR ll.tanggal_kejadian IS NULL` yang menyebabkan baris dengan tanggal NULL masuk ke hitungan SEMUA tahun, bukan hanya tahun yang diminta. Ini mengakibatkan statistik per tahun tidak akurat.
- **Langkah Reproduksi**:
  1. Buat beberapa laporan dengan `tanggal_kejadian` NULL
  2. Panggil `getStatsByJenis(2026)`
  3. Perhatikan bahwa laporan dengan tanggal NULL juga terhitung di tahun 2026
- **Dampak**: Statistik per jenis laporan per tahun tidak akurat — jumlah laporan terinflasi.
- **Perbaikan**: Hapus `OR ll.tanggal_kejadian IS NULL` dari kondisi WHERE.

---

## Bug Tinggi

### BUG-005: getById() — Missing Verifikator Join
- **File**: `app/models/LaporanLainnya.php:125-148`
- **Severity**: Tinggi
- **Deskripsi**: Method `getById()` tidak memiliki `leftJoin('users v', 'll.verified_by = v.id')` dan seleksi `v.nama_lengkap as verifikator_nama` yang ada di `getAllWithFilters()`. Ini berarti saat melihat detail laporan, informasi verifikator tidak tersedia.
- **Langkah Reproduksi**:
  1. Buat laporan dan verifikasi sebagai admin
  2. Buka halaman detail laporan `/laporan-lainnya/show/{id}`
  3. Informasi "Diverifikasi Oleh" tidak ditampilkan
- **Dampak**: Detail laporan tidak menampilkan nama verifikator.
- **Perbaikan**: Tambahkan leftJoin dan seleksi yang sama seperti di `getAllWithFilters()`.

### BUG-006: Filename Generation Menggunakan uniqid() (Tidak Aman)
- **File**: `app/controllers/LaporanLainnyaController.php:306`
- **Severity**: Tinggi
- **Deskripsi**: Nama file foto di-generate menggunakan `hash('sha256', time() . $file['name'] . uniqid())`. Fungsi `uniqid()` berbasis microtime dan bersifat prediktif, sehingga nama file bisa ditebak oleh attacker.
- **Langkah Reproduksi**:
  1. Upload foto laporan
  2. Perhatikan nama file yang dihasilkan
  3. Nama file berbasis `uniqid()` yang bisa ditebak secara probabilistik
- **Dampak**: Risiko prediksi nama file upload, potensi akses tidak sah ke file.
- **Perbaikan**: Ganti dengan `bin2hex(random_bytes(16))` untuk nama file yang kriptografis aman.

### BUG-007: Directory Permissions 0777 (Security Risk)
- **File**: `app/controllers/LaporanLainnyaController.php:280`
- **Severity**: Tinggi
- **Deskripsi**: Direktori upload dibuat dengan izin `0777` yang memberikan akses baca/tulis/execute ke semua pengguna sistem. Ini merupakan risiko keamanan.
- **Langkah Reproduksi**:
  1. Periksa izin direktori `public/uploads/laporan-lainnya/` setelah laporan pertama diupload
  2. Direktori memiliki izin `rwxrwxrwx` (0777)
- **Dampak**: Pengguna lain di server dapat memodifikasi atau menghapus file upload.
- **Perbaikan**: Ubah `0777` menjadi `0755`.

### BUG-008: update() Tidak Mendukung Upload Foto
- **File**: `app/controllers/LaporanLainnyaController.php:427-547`
- **Severity**: Tinggi
- **Deskripsi**: Method `update()` tidak memiliki logika upload foto, padahal method `store()` memiliki. Pengguna tidak dapat mengganti foto laporan saat melakukan edit.
- **Langkah Reproduksi**:
  1. Buat laporan dengan foto
  2. Edit laporan tersebut
  3. Tidak ada opsi untuk mengganti atau menghapus foto
- **Dampak**: Pengguna tidak dapat memperbarui foto laporan setelah penyuntingan.
- **Perbaikan**: Tambahkan logika upload foto ke method `update()`, mirip dengan `store()`.

### BUG-009: Pesan Sukses Submit Menyesatkan
- **File**: `app/controllers/LaporanLainnyaController.php:596`
- **Severity**: Tinggi
- **Deskripsi**: Pesan sukses saat submit laporan berbunyi "Laporan berhasil disubmit dan diverifikasi" padahal status laporan adalah `verified` (langsung diverifikasi), bukan `submitted` (menunggu verifikasi). Pesan ini menyesatkan pengguna.
- **Langkah Reproduksi**:
  1. Buat laporan draft
  2. Klik Submit
  3. Muncul pesan "Laporan berhasil disubmit dan diverifikasi"
  4. Padahal status laporan adalah `verified`, bukan `submitted`
- **Dampak**: Pengguna bingung dengan status sebenarnya dari laporan mereka.
- **Perbaikan**: Ubah pesan menjadi "Laporan berhasil disubmit dan masuk verifikasi" atau sesuaikan dengan workflow yang benar.

### BUG-010: getMapData() — Koordinat Hardcoded
- **File**: `app/controllers/CurahHujanController.php:1086-1087`
- **Severity**: Tinggi
- **Deskripsi**: Semua marker peta curah hujan menggunakan koordinat hardcoded `(-8.1706, 113.7003)` untuk pusat Jember, terlepas dari lokasi aktual data curah hujan. Ini menyebabkan semua data curah hujan muncul di titik yang sama pada peta.
- **Langkah Reproduksi**:
  1. Buka halaman peta curah hujan
  2. Perhatikan semua marker berada di satu titik yang sama
  3. Data curah hujan dari kecamatan berbeda ditampilkan di lokasi yang sama
- **Dampak**: Peta curah hujan tidak berguna untuk analisis spasial karena semua data terpusat di satu lokasi.
- **Perbaikan**: Gunakan koordinat kecamatan/desa yang sebenarnya dari tabel wilayah, atau simpan koordinat per lokasi di tabel curah_hujan.

---

## Bug Sedang

### BUG-011: Hidden Status Field di View Create
- **File**: `app/views/laporan-lainnya/create.php:459`
- **Severity**: Sedang
- **Deskripsi**: Terdapat hidden input dengan nilai `submitted` yang mengatur status default ke `submitted`. Ini bertentangan dengan seharusnya status default `draft` saat membuat laporan baru.
- **Langkah Reproduksi**:
  1. Buka halaman buat laporan
  2. Periksa HTML source — hidden field `status` bernilai `submitted`
  3. Jika JavaScript dinonaktifkan, form akan mengirim status `submitted` langsung
- **Dampak**: Jika JavaScript gagal, laporan langsung dibuat dengan status `submitted` alih-alih `draft`.
- **Perbaikan**: Ubah nilai hidden field menjadi `'draft'`.

### BUG-012: Tidak Ada Validasi File di Backend untuk update()
- **File**: `app/controllers/LaporanLainnyaController.php:427-547`
- **Severity**: Sedang
- **Deskripsi**: Method `update()` tidak memiliki validasi maupun penanganan untuk file foto yang diupload. Berbeda dengan `store()` yang memiliki validasi MIME, ekstensi, dan ukuran file.
- **Langkah Reproduksi**:
  1. Edit laporan yang sudah ada
  2. Coba upload file non-gambar
  3. Tidak ada validasi di sisi server untuk update
- **Dampak**: File non-gambar bisa tersimpan jika update mendukung upload foto (setelah fix BUG-008).
- **Perbaikan**: Tambahkan validasi file yang sama seperti di `store()` ke method `update()`.

---

## Bug Rendah

### BUG-013: Duplikasi Logika Filter di Model
- **File**: `app/models/LaporanLainnya.php:27-123`
- **Severity**: Rendah
- **Deskripsi**: Logika filter pada `getAllWithFilters()` dan `getCountWithFilters()` hampir identik dan diduplikasi. Ini meningkatkan risiko inkonsistensi saat ada perubahan filter di masa depan.
- **Dampak**: Maintainability — perubahan filter pada satu method bisa tidak diterapkan ke method lain.
- **Perbaikan**: Refactor menjadi method helper bersama, atau gunakan trait.

---

## Catatan Tambahan

### File yang Tidak Di-evaluasi Mendalam
- `app/controllers/Api/LaporanLainnyaController.php` — API controller sudah menggunakan status `'draft'` dengan benar
- `app/views/laporan-lainnya/edit.php`, `show.php` — View tidak di-read secara mendalam
- `app/helpers/ImageCompressor.php` — Tidak di-evaluasi untuk bug spesifik
- `app/core/QueryBuilder.php` — Tidak di-evaluasi untuk bug spesifik

### Rekomendasi Umum
1. Tambahkan test unit untuk method model `LaporanLainnya`
2. Tambahkan test integrasi untuk workflow laporan lengkap (create → edit → submit → verify)
3. Terapkan konsistensi status antara web controller dan API controller
4. Tambahkan logging yang lebih detail untuk debugging
5. Pertimbangkan penggunaan enum untuk status laporan