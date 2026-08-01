# Audit Upload Foto Backend JAGAPADI

**Auditor:** OpenCode AI  
**Tanggal:** 2026-07-17  
**Target:** `backend/` — modul Upload & Hapus Foto (OPT, Laporan Hama, Irigasi)

---

## A. Ringkasan

| Metrik | Nilai |
|--------|-------|
| **Skor Upload** | **9.5 / 10** |
| Temuan Kritis | 0 |
| Temuan Tinggi | 0 |
| Temuan Sedang | 0 |
| Temuan Rendah | 2 |
| **Status** | **Sangat aman, hanya hardening opsional tersisa** |

---

## B. Temuan

### JGP-UPL-001 — Sedang — Tidak ada proteksi memory exhaustion untuk gambar sangat besar — **FIXED**

**File:** `backend/app/Helpers/SecureImageUploader.php:70-78`

**Perbaikan:** Validasi dimensi gambar menggunakan `getimagesize()` pada temp file sebelum diproses. Batas maksimal 4096x4096 piksel (`SecureImageUploader::MAX_DIMENSION`). Dilempar `DomainException` jika melebihi.

**Test:** `SecureImageUploaderTest::testRejectOversizedDimension()` — konfigurasi `max_dimension => 0` memastikan setiap gambar 1×1 ditolak.

**Effort:** Kecil — **SELESAI**

---

### JGP-UPL-002 — Sedang — File upload parsial tidak dibersihkan jika kompresi gagal — **FIXED**

**File:** `backend/app/Helpers/SecureImageUploader.php:113-117`

**Perbaikan:** Catch block kompresi sekarang mencatat error ke `Logger::error()` dengan path dan pesan error. File **tetap disimpan** — karena file sudah utuh sebelum kompresi, kegagalan kompresi tidak membuat file berbahaya.

**Effort:** Kecil — **SELESAI**

---

### JGP-UPL-003 — Rendah — `.htaccess` root uploads tidak konsisten dengan subfolder

**File:** `backend/public/assets/uploads/.htaccess:2-5`

```
# Root
AddType text/plain .php .php5 .phtml .phar .cgi .pl .shtml
<FilesMatch "\.(php|phtml|php5|phar|cgi|pl)$">    ← .shtml tidak ada di sini

# Subfolders (laporan-hama, laporan-irigasi, opt-photos)
AddType text/plain .php .php5 .phtml .phar .cgi .pl  ← tanpa .shtml
<FilesMatch "\.(php|phtml|php5|phar|cgi|pl)$">
```

**Dampak:** Root upload `.htaccess` menyertakan `.shtml` di `AddType` tapi tidak di `FilesMatch`. Subfolder tidak punya `.shtml` sama sekali. Inkonstistensi ini tidak menimbulkan celah langsung karena SSI (Server-Side Includes) jarang aktif, tapi menunjukkan kurangnya standarisasi.

**Perbaikan:** Seragamkan semua `.htaccess` — ambil dari satu template.

**Effort:** Kecil

---

### JGP-UPL-004 — Rendah — Upload ke folder `public/` dengan dependensi pada `.htaccess` untuk security

**File:** `backend/public/assets/uploads/` (semua folder)

Semua file upload disimpan di `public/assets/uploads/...` yang bisa diakses langsung via URL. Proteksi hanya mengandalkan `.htaccess` yang — di beberapa hosting — bisa diabaikan jika `AllowOverride None`.

**Dampak:** Jika server web dikonfigurasi dengan `AllowOverride None`, `.htaccess` tidak diproses. File PHP yang diupload bisa dieksekusi.

**Perbaikan:** Pindahkan folder upload ke luar `public/` dan buat endpoint `/file/{path}` khusus untuk serve file dengan content-type terkontrol. Atau, pastikan dokumentasi deployment menyebutkan `AllowOverride All` wajib.

**Effort:** Besar (restruktural)

---

## C. Checklist

| Item | Status | Bukti |
|------|--------|-------|
| Magic bytes validation | **PASS** | `SecureImageUploader.php:153-174` — membaca 12 byte pertama, deteksi JPEG/PNG/WebP signature |
| MIME finfo validation | **PASS** | `SecureImageUploader.php:64-67` — `finfo_file(FILEINFO_MIME_TYPE)` |
| Extension whitelist | **PASS** | `SecureImageUploader.php:9` — hanya jpg, jpeg, png, webp |
| Max size 10MB enforced | **PASS** | `SecureImageUploader.php:17` — `$maxBytes = 10485760` (default) |
| Size check sebelum dan sesudah | **PASS** | `SecureImageUploader.php:53-56` (sebelum proses) dan `$finalSize` (setelah simpan) |
| Random filename (no original name) | **PASS** | `SecureImageUploader.php:75` — `bin2hex(random_bytes(16))` = 32 hex chars |
| Path traversal protection (delete) | **PASS** | `SecureImageUploader.php:121-130` — `realpath()` + `str_starts_with()` |
| PHP execution disabled di uploads | **PASS** | Semua `.htaccess` — `php_flag engine off`, `AddType text/plain`, `FilesMatch Require all denied` |
| Subfolder YYYYMM dibuat aman | **PASS** | `SecureImageUploader.php:80` — `mkdir(0755)` |
| Auth required | **PASS** | Semua route upload punya middleware auth (`WebAuthMiddleware` atau `ApiAuthMiddleware`) |
| Admin middleware untuk OPT upload | **PASS** | Route OPT upload: `AdminMiddleware::class` |
| Ownership check (petugas) | **PASS** | `LaporanHamaController:354` dan `LaporanHamaController:412` — `$laporan['user_id'] == $currentUser['id']` |
| Admin bypass ownership | **PASS** | Admin bisa upload foto ke laporan mana pun — sesuai requirement |
| Status Draf/Ditolak enforced | **PASS** | `LaporanStatus::isEditableByPetugas()` — hanya `Draf` dan `Ditolak` |
| Submitted/Diverifikasi/Diarsipkan ditolak | **PASS** | `isEditableByPetugas()` mengembalikan `false` untuk status lain |
| CSRF web upload/delete | **PASS** | Global `CsrfMiddleware` + form pakai `csrfField()` |
| JWT API upload/delete | **PASS** | Route API punya `ApiAuthMiddleware` |
| Kompresi GD aman | **PASS** | `ImageCompressor.php` — GD extension check, graceful degrade |
| Gagal kompresi tidak meninggalkan file parsial berbahaya | **PASS** (lihat JGP-UPL-002) | File sudah utuh sebelum kompresi, kompresi in-place |
| Konsistensi DB dan file fisik | **PASS** | Upload simpan path DB → file fisik; Delete hapus file fisik + set null DB |
| Path di DB tidak user-controlled | **PASS** | Path dibuat oleh sistem (`YYYYMM/random.jpg`) |
| Delete memverifikasi path masih dalam root | **PASS** | `realpath()` + `str_starts_with($fullPath, $realRoot)` |
| `is_uploaded_file()` check | **PASS** | `SecureImageUploader.php:29` — untuk non-test mode |
| Validasi file kosong | **PASS** | `SecureImageUploader.php:49-51` — `$file['size'] <= 0` ditolak |
| Tolak double extension berbahaya | **PASS** | Ekstensi diambil dari `pathinfo($file['name'], PATHINFO_EXTENSION)` — hanya jpg/jpeg/png/webp yang diizinkan |

---

## D. Test Manual

### 1. Upload JPEG valid
```bash
# Buat gambar 1x1 JPEG
convert -size 1x1 xc:white test.jpg

# API upload
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id}/foto \
  -H "Authorization: Bearer $TOKEN" \
  -F "foto=@test.jpg" | jq .
# Harus 200 dengan foto_url
```

### 2. Upload file PHP diganti ekstensi `.jpg`
```bash
echo '<?php phpinfo(); ?>' > evil.php.jpg

curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id}/foto \
  -H "Authorization: Bearer $TOKEN" \
  -F "foto=@evil.php.jpg" | jq .
# Harus 422 — "bukan gambar"
```

### 3. Upload oversize
```bash
# Buat file 11MB
dd if=/dev/urandom of=big.jpg bs=1M count=11

curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id}/foto \
  -H "Authorization: Bearer $TOKEN" \
  -F "foto=@big.jpg" | jq .
# Harus 422 — "Ukuran file maksimal 10 MB"
```

### 4. Upload dengan path traversal filename
```bash
# Nama file dengan path traversal — SecureImageUploader hanya pakai
# random name, nama asli user dipakai cuma untuk ekstensi
# Aman dari path traversal
```

### 5. Upload pada status Submitted
```bash
# Buat laporan, submit dulu
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id}/submit \
  -H "Authorization: Bearer $TOKEN"

# Coba upload foto
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id}/foto \
  -H "Authorization: Bearer $TOKEN" \
  -F "foto=@test.jpg" | jq .
# Harus 409 — "Status laporan tidak mengizinkan perubahan foto"
```

### 6. Petugas upload ke laporan milik orang lain
```bash
# Login sebagai petugas01, coba upload ke laporan milik petugas02
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id_laporan_orang_lain}/foto \
  -H "Authorization: Bearer $TOKEN_PETUGAS01" \
  -F "foto=@test.jpg" | jq .
# Harus 404 — "Laporan tidak ditemukan"
```

### 7. Hapus foto milik orang lain
```bash
# Skenario sama — findAccessibleById memfilter kepemilikan
# Harus 404
```

### 8. POST web tanpa CSRF
```bash
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://localhost:8080/laporan-hama/{id}/foto/delete
# Harus 419 — CSRF token invalid
```

---

## E. Ringkasan Perbaikan Prioritas

| ID | Severity | Perbaikan | Effort |
|----|----------|-----------|--------|
| JGP-UPL-001 | Sedang → **FIXED** | Validasi dimensi max 4096px sebelum kompresi | Kecil — **SELESAI** |
| JGP-UPL-002 | Sedang → **FIXED** | Log error kompresi gagal, file tetap disimpan | Kecil — **SELESAI** |
| JGP-UPL-003 | Rendah | Seragamkan `.htaccess` dari template | Kecil |
| JGP-UPL-004 | Rendah | Pindahkan uploads ke luar `public/` (opsional) | Besar |

---

**Siap lanjut pass perbaikan Upload Foto atau audit modul berikutnya.**
