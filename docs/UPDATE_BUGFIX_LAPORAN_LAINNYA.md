# Dokumen Pembaruan — Perbaikan Bug Modul Laporan Lainnya

**Tanggal**: 2026-08-04
**Versi**: 1.0.0
**Cakupan**: Modul Laporan Lainnya (Web Controller, API Controller, Model, Views)

---

## Ringkasan Perubahan

Total **11 bug** diperbaiki dari 4 file:
- `app/controllers/LaporanLainnyaController.php`
- `app/models/LaporanLainnya.php`
- `app/views/laporan-lainnya/create.php`
- `app/controllers/CurahHujanController.php`

| Kategori | Jumlah | Detail |
|----------|--------|--------|
| Bug Kritis | 4 | Workflow rusak, SQL error, data salah |
| Bug Tinggi | 6 | Keamanan, UX, fungsionalitas |
| Bug Sedang | 2 | Konsistensi data |
| Bug Rendah | 1 | Maintainability |

---

## Detail Perbaikan

### BUG-001: Status Laporan Salah saat Create (Kritis)
- **File**: `app/controllers/LaporanLainnyaController.php:355`
- **Sebelum**: `'status' => 'submitted'`
- **Sesudah**: `'status' => 'draft'`
- **Penjelasan**: Saat pengguna membuat laporan baru via web form, status harus `draft` agar pengguna dapat mengedit laporan sebelum submit. Status `submitted` seharusnya hanya diberikan saat pengguna menekan tombol Submit.

### BUG-002: submitReport() Melewati Status 'Submitted' (Kritis)
- **File**: `app/models/LaporanLainnya.php:167-174`
- **Sebelum**: Status diatur ke `'verified'` dengan `verified_at` diisi otomatis
- **Sesudah**: Status diatur ke `'submitted'` dengan `verified_at` bernilai `null`
- **Penjelasan**: Workflow seharusnya `Draft → Submitted → Diverifikasi`. Method `submitReport()` sekarang benar mengatur status ke `submitted` sehingga laporan masuk antrian verifikasi admin.

### BUG-003: getCountWithFilters() — Search Query Tanpa JOIN (Kritis)
- **File**: `app/models/LaporanLainnya.php:89-123`
- **Sebelum**: Method `getCountWithFilters()` tidak memiliki `leftJoin` untuk tabel `users` dan `master_jenis_laporan`
- **Sesudah**: Ditambahkan `leftJoin('users u', 'll.user_id = u.id')` dan `leftJoin('master_jenis_laporan mjl', 'll.jenis_id = mjl.id')`
- **Penjelasan**: Filter pencarian merujuk ke `mjl.nama` dan `u.nama_lengkap` yang memerlukan JOIN. Tanpa JOIN, query akan gagal dengan SQL error saat fitur pencarian digunakan.

### BUG-004: getStatsByJenis() — OR Condition pada NULL Dates (Kritis)
- **File**: `app/models/LaporanLainnya.php:233`
- **Sebelum**: `WHERE YEAR(ll.tanggal_kejadian) = :tahun OR ll.tanggal_kejadian IS NULL`
- **Sesudah**: `WHERE YEAR(ll.tanggal_kejadian) = :tahun`
- **Penjelasan**: Kondisi `OR ll.tanggal_kejadian IS NULL` menyebabkan baris dengan tanggal NULL masuk ke hitungan SEMUA tahun, mengakibatkan statistik per tahun tidak akurat.

### BUG-005: getById() — Missing Verifikator Join (Tinggi)
- **File**: `app/models/LaporanLainnya.php:125-148`
- **Sebelum**: Tidak ada `leftJoin('users v', 'll.verified_by = v.id')` dan seleksi `v.nama_lengkap as verifikator_nama`
- **Sesudah**: Ditambahkan leftJoin dan seleksi yang sama seperti di `getAllWithFilters()`
- **Penjelasan**: Saat melihat detail laporan yang telah diverifikasi, informasi nama verifikator tidak tersedia karena JOIN yang diperlukan tidak ada.

### BUG-006: Filename Generation Menggunakan uniqid() (Tinggi)
- **File**: `app/controllers/LaporanLainnyaController.php:306`
- **Sebelum**: `hash('sha256', time() . $file['name'] . uniqid())`
- **Sesudah**: `bin2hex(random_bytes(16))`
- **Penjelasan**: `uniqid()` berbasis microtime dan bersifat prediktif. `random_bytes(16)` menghasilkan 32 karakter heksadesimal yang kriptografis aman dan tidak dapat ditebak.

### BUG-007: Directory Permissions 0777 (Tinggi)
- **File**: `app/controllers/LaporanLainnyaController.php:280`
- **Sebelum**: `mkdir($uploadDir, 0777, true)`
- **Sesudah**: `mkdir($uploadDir, 0755, true)`
- **Penjelasan**: Izin `0777` memberikan akses baca/tulis/execute ke semua pengguna sistem. `0755` hanya memberikan akses penuh ke pemilik dan akses baca/execute ke grup dan pengguna lain.

### BUG-008: update() Tidak Mendukung Upload Foto (Tinggi)
- **File**: `app/controllers/LaporanLainnyaController.php:427-547`
- **Sebelum**: Method `update()` tidak memiliki logika upload foto
- **Sesudah**: Ditambahkan logika upload foto lengkap dengan validasi MIME, ekstensi, ukuran, kompresi otomatis, dan penghapusan foto lama
- **Penjelasan**: Pengguna tidak dapat mengganti foto laporan saat melakukan edit. Sekarang method `update()` mendukung upload foto dengan validasi yang sama seperti method `store()`.

### BUG-009: Pesan Sukses Submit Menyesatkan (Tinggi)
- **File**: `app/controllers/LaporanLainnyaController.php:596`
- **Sebelum**: `'Laporan berhasil disubmit dan diverifikasi'`
- **Sesudah**: `'Laporan berhasil disubmit dan masuk antrian verifikasi'`
- **Penjelasan**: Pesan sebelumnya menyiratkan bahwa laporan langsung diverifikasi, padahal statusnya adalah `submitted` (menunggu verifikasi admin). Pesan baru lebih akurat menggambarkan proses yang terjadi.

### BUG-010: getMapData() — Koordinat Hardcoded (Tinggi)
- **File**: `app/controllers/CurahHujanController.php:1086-1087`
- **Sebelum**: Semua marker menggunakan koordinat hardcoded `(-8.1706, 113.7003)`
- **Sesudah**: Koordinat diatur ke `null` agar frontend dapat menangani data tanpa koordinat secara graceful
- **Penjelasan**: Semua data curah hujan ditampilkan di satu titik yang sama pada peta, membuat peta tidak berguna untuk analisis spasial. Perbaikan sementara menghapus koordinat hardcoded. Perbaikan permanen memerlukan penyimpanan koordinat per lokasi di tabel `curah_hujan` atau join dengan tabel wilayah.

### BUG-011: Hidden Status Field di View Create (Sedang)
- **File**: `app/views/laporan-lainnya/create.php:459`
- **Sebelum**: `<input type="hidden" name="status" id="statusSelect" value="submitted">`
- **Sesudah**: `<input type="hidden" name="status" id="statusSelect" value="draft">`
- **Penjelasan**: Jika JavaScript dinonaktifkan di browser, form akan mengirim status `submitted` langsung alih-alih `draft`. Hidden field sekarang sesuai dengan status default yang benar.

### BUG-012: Tidak Ada Validasi File di Backend untuk update() (Sedang)
- **File**: `app/controllers/LaporanLainnyaController.php:427-547`
- **Sebelum**: Tidak ada validasi file di method `update()`
- **Sesudah**: Ditambahkan validasi MIME, ekstensi, dan ukuran file yang sama seperti di `store()`
- **Penjelasan**: Berbeda dengan `store()` yang memiliki validasi file lengkap, `update()` tidak memiliki validasi sama sekali. Sekarang `update()` memiliki validasi yang sama.

### BUG-013: Duplikasi Logika Filter di Model (Rendah)
- **File**: `app/models/LaporanLainnya.php:27-123`
- **Status**: Tidak diperbaiki secara langsung (refactoring)
- **Penjelasan**: Logika filter pada `getAllWithFilters()` dan `getCountWithFilters()` hampir identik. Ini meningkatkan risiko inkonsistensi saat ada perubahan filter di masa depan. Direkomendasikan untuk direfactor menjadi method helper bersama di versi berikutnya.

---

## Hasil Pengujian

### Pengujian Otomatis (PHPUnit)
```
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.2.32
Configuration: phpunit.xml

.......................                                           23 / 23 (100%)

Time: 00:01.848, Memory: 8.00 MB

OK (23 tests, 75 assertions)
```

Semua 23 tes yang ada lolos dengan 75 asersi. Tidak ada tes yang gagal setelah perbaikan bug.

### Pengujian Manual yang Direkomendasikan
1. **Workflow Draft**: Buat laporan baru → verifikasi status `draft` → edit laporan → submit → verifikasi status `submitted`
2. **Pencarian**: Gunakan fitur pencarian di halaman `/laporan-lainnya` → pastikan tidak ada SQL error
3. **Statistik**: Periksa statistik per tahun → pastikan laporan dengan tanggal NULL tidak terhitung di semua tahun
4. **Detail Laporan**: Buka laporan yang telah diverifikasi → pastikan nama verifikator ditampilkan
5. **Upload Foto**: Edit laporan yang sudah ada → upload foto baru → verifikasi foto lama dihapus dan foto baru tersimpan
6. **Keamanan File**: Coba upload file non-gambar → pastikan validasi menolak file tersebut
7. **Peta Curah Hujan**: Buka halaman peta → pastikan tidak ada marker yang terpusat di satu titik yang sama

---

## File yang Diubah

| File | Perubahan |
|------|-----------|
| `app/controllers/LaporanLainnyaController.php` | Fix status draft, tambah upload foto di update(), fix filename generation, fix directory permissions, fix success message |
| `app/models/LaporanLainnya.php` | Fix submitReport(), fix getCountWithFilters() JOIN, fix getById() JOIN, fix getStatsByJenis() SQL |
| `app/views/laporan-lainnya/create.php` | Fix hidden status field value |
| `app/controllers/CurahHujanController.php` | Fix hardcoded map coordinates |

---

## Risiko dan Pekerjaan Lanjutan

1. **BUG-010 (Koordinat Peta)**: Perbaikan sementara menghapus koordinat hardcoded. Perbaikan permanen memerlukan:
   - Menambahkan kolom `latitude` dan `longitude` ke tabel `curah_hujan`, ATAU
   - Menyimpan koordinat per lokasi di tabel referensi wilayah, ATAU
   - Menggunakan API geocoding untuk mengkonversi nama lokasi ke koordinat

2. **BUG-013 (Duplikasi Logika)**: Refactoring `getAllWithFilters()` dan `getCountWithFilters()` menjadi method helper bersama direkomendasikan untuk versi berikutnya.

3. **Test Coverage**: Test unit untuk method `LaporanLainnya` masih terbatas. Direkomendasikan untuk menambahkan test integration untuk workflow laporan lengkap.

4. **API Documentation**: Endpoint API `LaporanLainnya` perlu diperbarui untuk mencerminkan perubahan status workflow (`submitted` bukan `verified`).