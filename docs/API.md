# API Contract JAGAPADI

> **Status**: Tahap 12 — Notifikasi in-app telah diimplementasikan. NotificationService, Migration 009, Web bell badge + polling, API endpoints, NullPushNotifier default.
> **Base URL**: `https://domain.tld/api/v1`
> **Format**: JSON
> **Auth Mobile**: JWT (`Authorization: Bearer <access_token>`)
> **Auth Web**: Session cookie + CSRF token (field `_csrf_token` atau header `X-CSRF-TOKEN`)

---

## Konvensi Umum

| Aspek | Aturan |
|-------|--------|
| Base path | `/api/v1` |
| Response envelope | `{ "data": ..., "meta": ... }` atau `{ "error": { "code": "...", "message": "..." } }` |
| Pagination | `?page=1&per_page=15` (default 15, max 100) |
| Sorting | `?sort=created_at:desc` |
| Filter draft | `?include_draft=true\|false` (default `false`) |
| Date format | ISO 8601 (`YYYY-MM-DD`, `YYYY-MM-DDTHH:MM:SSZ`) |
| Error codes | `ValidationError`, `Unauthorized`, `Unauthenticated`, `Forbidden`, `NotFound`, `Conflict`, `ServerError`, `TooManyRequests`, `TokenInvalid` |
| Rate limit | 60 req/min (auth), 20 req/min (guest); brute-force: 5 failed login / IP / 15 menit |

---

## Health (Implemented — Tahap 2)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/health` | Public | Pemeriksaan kesehatan aplikasi & database |

**Response 200** (database tersambung):
```json
{
  "success": true,
  "message": "JAGAPADI is healthy",
  "data": {
    "app": "JAGAPADI",
    "environment": "local",
    "time": "2026-07-16T13:00:00+07:00",
    "database": "connected"
  }
}
```

**Response 503** (database tidak tersedia):
```json
{
  "success": false,
  "error": "DatabaseUnavailable",
  "message": "Layanan database tidak tersedia."
}
```

---

## Auth Endpoints (Implemented — Tahap 4)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/auth/login` | Public | Login mobile (JWT) |
| POST | `/api/v1/auth/refresh` | JWT | Refresh access token (perpanjang masa berlaku) |
| POST | `/api/v1/auth/logout` | JWT | Logout (catat activity log) |
| POST | `/api/v1/auth/change-password` | JWT | Ubah password (validasi password lama + policy) |
| GET | `/api/v1/me` | JWT | Current user profile (public fields) |

### POST /api/v1/auth/login

**Request:**
```json
{
  "username": "petugas01",
  "password": "ChangeMePetugas!123"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 2,
      "username": "petugas01",
      "nama_lengkap": "Petugas Satu",
      "role": "petugas",
      "is_active": 1,
      "must_change_password": true
    }
  }
}
```

**Response 401 (salah password):**
```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "Username atau password salah."
}
```

**Response 422 (validasi):**
```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Username dan password harus diisi.",
  "errors": {
    "username": "Username wajib diisi.",
    "password": "Password wajib diisi."
  }
}
```

**Response 429 (rate limit):**
```json
{
  "success": false,
  "error": "TooManyRequests",
  "message": "Terlalu banyak percobaan login. Coba lagi nanti."
}
```

### POST /api/v1/auth/refresh

Memperbarui token JWT sebelum kedaluwarsa. Mengembalikan token baru dengan `exp` yang diperbarui.

**Request:** (Bearer token di header)

**Response 200:**
```json
{
  "success": true,
  "message": "Token berhasil diperbarui",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

**Response 401:**
```json
{
  "success": false,
  "error": "TokenInvalid",
  "message": "Token tidak valid atau sudah kedaluwarsa."
}
```

### POST /api/v1/auth/logout

Mencatat aktivitas logout. Tidak ada revoke token (stateless JWT).

### POST /api/v1/auth/change-password

**Request:**
```json
{
  "current_password": "ChangeMePetugas!123",
  "new_password": "NewPass!234",
  "new_password_confirmation": "NewPass!234"
}
```

**Password policy:**
- Minimal 8 karakter
- Minimal 1 huruf besar
- Minimal 1 huruf kecil
- Minimal 1 angka
- Minimal 1 karakter khusus

### GET /api/v1/me

Mengembalikan data user yang sedang login (tanpa field `password`).

**Response 200:**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 2,
    "username": "petugas01",
    "nama_lengkap": "Petugas Satu",
    "role": "petugas",
    "is_active": 1,
    "must_change_password": false
  }
}
```

---

## Master Data (Implemented — Tahap 5)

### Wilayah

Master wilayah berjenjang: Kabupaten → Kecamatan → Desa.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/wilayah/kabupaten` | JWT/Web | List kabupaten |
| GET | `/api/v1/wilayah/kabupaten/{id}` | JWT/Web | Detail kabupaten |
| GET | `/api/v1/wilayah/kecamatan?kabupaten_id=` | JWT/Web | List kecamatan (wajib filter) |
| GET | `/api/v1/wilayah/kecamatan/{id}` | JWT/Web | Detail kecamatan |
| GET | `/api/v1/wilayah/desa?kecamatan_id=` | JWT/Web | List desa (wajib filter) |
| GET | `/api/v1/wilayah/desa/{id}` | JWT/Web | Detail desa |
| POST | `/api/v1/wilayah/kabupaten` | Admin | Tambah kabupaten |
| PUT | `/api/v1/wilayah/kabupaten/{id}` | Admin | Update kabupaten |
| DELETE | `/api/v1/wilayah/kabupaten/{id}` | Admin | Hapus kabupaten |
| POST | `/api/v1/wilayah/kecamatan` | Admin | Tambah kecamatan |
| PUT | `/api/v1/wilayah/kecamatan/{id}` | Admin | Update kecamatan |
| DELETE | `/api/v1/wilayah/kecamatan/{id}` | Admin | Hapus kecamatan |
| POST | `/api/v1/wilayah/desa` | Admin | Tambah desa |
| PUT | `/api/v1/wilayah/desa/{id}` | Admin | Update desa |
| DELETE | `/api/v1/wilayah/desa/{id}` | Admin | Hapus desa |

**Aturan:**
- Hapus wilayah diblokir (409) jika masih memiliki child atau direferensikan laporan (FK RESTRICT)
- Setiap mutasi tercatat di `audit_log_wilayah` (admin_id, tabel, record_id, aksi, data_lama, data_baru)
- Kecamatan wajib filter `kabupaten_id` (422 jika kosong)
- Desa wajib filter `kecamatan_id` (422 jika kosong)

### OPT (Organisme Pengganggu Tanaman)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/opt` | JWT/Web | List OPT (filter: `jenis`, `q`, `aktif`) |
| GET | `/api/v1/opt/{id}` | JWT/Web | Detail OPT |
| POST | `/api/v1/opt` | Admin | Tambah OPT |
| PUT | `/api/v1/opt/{id}` | Admin | Update OPT |
| DELETE | `/api/v1/opt/{id}` | Admin | Hapus/nonaktifkan OPT |

**Read filters:**
- `jenis`: `hama` | `penyakit` | `gulma`
- `q`: search by nama_opt
- `aktif`: `1` | `0` (admin only; non-admin hanya melihat aktif)

**Aturan:**
- Petugas/mobile hanya bisa read OPT aktif
- Admin full CRUD
- DELETE: hard delete jika tidak ada referensi laporan; soft deactivate (`aktif=0`) jika masih dirujuk
- Validasi: nama_opt unique, jenis enum wajib, etl_acuan >= 0

## Laporan Hama — API (Implemented — Tahap 6)

### Aturan
- **Draf**: nomor_laporan NULL, semua field nullable, hanya bisa diedit/dihapus oleh pemilik.
- **Submitted**: nomor_laporan diisi (LH-YYYYMMDD-XXXX), read-only, tidak bisa diedit/dihapus.
- **Petugas** hanya melihat dan mengelola laporan sendiri.
- **Admin** dapat melihat semua laporan.
- Nomor laporan hanya dibuat saat Submit, atomic via `nomor_laporan_counter`.
- Foto bersifat opsional selama status masih Draf, tetapi wajib tersedia sebelum laporan dapat dikirim atau dikirim ulang.
- Alur API yang direkomendasikan: buat Draf, upload foto melalui endpoint `/foto`, lalu panggil endpoint `/submit`.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/laporan-hama` | JWT | List (filter: status, tanggal, wilayah, OPT, q, page, limit, include_draft) |
| POST | `/api/v1/laporan-hama` | JWT | Create (action=draft|submit, default draft) |
| GET | `/api/v1/laporan-hama/{id}` | JWT | Detail (owner/admin only) |
| PUT | `/api/v1/laporan-hama/{id}` | JWT | Update Draf (owner only) |
| DELETE | `/api/v1/laporan-hama/{id}` | JWT | Delete Draf (owner only) |
| POST | `/api/v1/laporan-hama/{id}/submit` | JWT | Submit Draf → Submitted |

**Request POST /api/v1/laporan-hama (buat Draf):**
```json
{
  "action": "draft",
  "tanggal": "2026-07-16",
  "master_opt_id": 1,
  "kabupaten_id": 1,
  "kecamatan_id": 1,
  "desa_id": 1,
  "tingkat_keparahan": "Sedang",
  "luas_serangan": 1.25,
  "populasi": 10,
  "lokasi": "Blok sawah utara",
  "latitude": -8.1734,
  "longitude": 113.7012,
  "catatan": "Populasi meningkat"
}
```

**Response 201 Create Draf:**
```json
{
  "success": true,
  "message": "Draf laporan hama berhasil dibuat",
  "data": {
    "id": 1,
    "nomor_laporan": null,
    "status": "Draf",
    ...
  }
}
```

**Response 201 Create + Submit:**
```json
{
  "success": true,
  "message": "Laporan hama berhasil dikirim",
  "data": {
    "id": 1,
    "nomor_laporan": "LH-20260716-0001",
    "status": "Submitted",
    ...
  }
}
```

**Response 422 Validation Error:**
```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Data laporan tidak valid",
  "errors": {
    "tanggal": "Tanggal wajib diisi",
    "luas_serangan": "Luas serangan harus antara 0 dan 9999.99",
    "foto": "Foto laporan wajib disertakan sebelum laporan dapat dikirim."
  }
}
```

**Response 409 Conflict:**
```json
{
  "success": false,
  "error": "Conflict",
  "message": "Hanya laporan dengan status Draf yang dapat diubah."
}
```

## Laporan Irigasi — API (Implemented — Tahap 7)

### Aturan
- Sama dengan Laporan Hama, menggunakan prefix nomor `LI`.
- Field wajib submit: tanggal, kabupaten_id, kecamatan_id, desa_id, nama_saluran, kondisi_fisik, debit_air.
- Field opsional: daerah_irigasi, latitude, longitude, foto_url, catatan.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/laporan-irigasi` | JWT | List (filter: status, tanggal, wilayah, kondisi_fisik, debit_air, q, page, limit, include_draft) |
| POST | `/api/v1/laporan-irigasi` | JWT | Create (action=draft|submit, default draft) |
| GET | `/api/v1/laporan-irigasi/{id}` | JWT | Detail (owner/admin only) |
| PUT | `/api/v1/laporan-irigasi/{id}` | JWT | Update Draf (owner only) |
| DELETE | `/api/v1/laporan-irigasi/{id}` | JWT | Delete Draf (owner only) |
| POST | `/api/v1/laporan-irigasi/{id}/submit` | JWT | Submit Draf → Submitted |

**Request POST /api/v1/laporan-irigasi:**
```json
{
  "action": "submit",
  "tanggal": "2026-07-16",
  "kabupaten_id": 1,
  "kecamatan_id": 1,
  "desa_id": 1,
  "nama_saluran": "Saluran Sekunder Bedadung 1",
  "daerah_irigasi": "Dam Bedadung",
  "kondisi_fisik": "Sedang",
  "debit_air": "Kurang",
  "latitude": -8.2011,
  "longitude": 113.6890,
  "catatan": "Kebocoran kecil di km 2"
}
```

**Response 201 Create + Submit:**
```json
{
  "success": true,
  "message": "Laporan irigasi berhasil dikirim",
  "data": {
    "id": 15,
    "nomor_laporan": "LI-20260716-0001",
    "status": "Submitted"
  }
}
```

---

## Laporan Verifikasi — API (Implemented — Tahap 8)

### Aturan Umum

Status laporan (hama & irigasi) mengikuti state machine berikut:

```
Draf → Submitted → Diverifikasi → Diarsipkan
                ↘ Ditolak → Submitted (resubmit)
```

| Transisi | Pelaku | Kode |
|----------|--------|------|
| Submitted → Diverifikasi | Admin | 200 |
| Submitted → Ditolak | Admin | 200 |
| Diverifikasi → Diarsipkan | Admin | 200 |
| Ditolak → Submitted (resubmit) | Petugas (owner) | 200 |
| Ditolak → Draf (revisi) | Petugas (owner) | 200 |

**Aturan:**
- Alasan tolak wajib minimal 10 karakter, maksimal 2000 karakter
- Catatan verifikasi opsional untuk verify/archive
- Resubmit TIDAK mengubah nomor laporan (tetap pakai nomor yang sudah ada)
- Resubmit mereset `verified_by`, `verified_at`, `catatan_verifikasi` ke NULL
- Transisi ilegal → 409 Conflict
- Petugas melakukan aksi admin → 403 Forbidden

### Laporan Hama — Verifikasi

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/laporan-hama/{id}/verifikasi` | JWT (admin) | Verifikasi laporan Submitted |
| POST | `/api/v1/laporan-hama/{id}/tolak` | JWT (admin) | Tolak laporan Submitted |
| POST | `/api/v1/laporan-hama/{id}/archive` | JWT (admin) | Arsipkan laporan Diverifikasi |
| POST | `/api/v1/laporan-hama/{id}/resubmit` | JWT (petugas) | Kirim ulang laporan Ditolak |

### Laporan Irigasi — Verifikasi

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/laporan-irigasi/{id}/verifikasi` | JWT (admin) | Verifikasi laporan Submitted |
| POST | `/api/v1/laporan-irigasi/{id}/tolak` | JWT (admin) | Tolak laporan Submitted |
| POST | `/api/v1/laporan-irigasi/{id}/archive` | JWT (admin) | Arsipkan laporan Diverifikasi |
| POST | `/api/v1/laporan-irigasi/{id}/resubmit` | JWT (petugas) | Kirim ulang laporan Ditolak |

### Contoh Request & Response

**POST /api/v1/laporan-hama/{id}/verifikasi**

Request:
```json
{
  "catatan": "Data lengkap dan valid"
}
```

Response 200:
```json
{
  "success": true,
  "message": "Laporan hama berhasil diverifikasi",
  "data": {
    "id": 42,
    "status": "Diverifikasi",
    "verified_by": 1,
    "verified_at": "2026-07-16 14:00:00",
    "catatan_verifikasi": "Data lengkap dan valid",
    "nomor_laporan": "LH-20260716-0001",
    "verifikator_nama": "Admin Satu"
  }
}
```

**POST /api/v1/laporan-hama/{id}/tolak**

Request:
```json
{
  "alasan": "Lokasi desa tidak sesuai dengan koordinat yang dilaporkan"
}
```

Response 200:
```json
{
  "success": true,
  "message": "Laporan hama berhasil ditolak",
  "data": {
    "id": 42,
    "status": "Ditolak",
    "verified_by": 1,
    "verified_at": "2026-07-16 14:05:00",
    "catatan_verifikasi": "Lokasi desa tidak sesuai dengan koordinat yang dilaporkan",
    "nomor_laporan": "LH-20260716-0002"
  }
}
```

**POST /api/v1/laporan-irigasi/{id}/resubmit**

Request:
```json
{
  "desa_id": 5,
  "catatan": "Sudah diperbaiki sesuai arahan"
}
```

Response 200:
```json
{
  "success": true,
  "message": "Laporan irigasi berhasil dikirim ulang",
  "data": {
    "id": 15,
    "status": "Submitted",
    "verified_by": null,
    "verified_at": null,
    "catatan_verifikasi": null,
    "nomor_laporan": "LI-20260716-0001"
  }
}
```

**Error 409 (transisi ilegal):**
```json
{
  "success": false,
  "error": "Conflict",
  "message": "Transisi status tidak diizinkan: 'Draf' -> 'Diverifikasi'"
}
```

**Error 422 (alasan tolak pendek):**
```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Alasan penolakan minimal 10 karakter."
}
```

**Error 403 (petugas coba verifikasi):**
```json
{
  "success": false,
  "error": "Forbidden",
  "message": "Aksi ini hanya untuk admin."
}
```

---

## Upload Foto — API (Implemented — Tahap 9)

### Aturan Umum

- Upload menggunakan `multipart/form-data` dengan field name `foto`.
- Format yang diizinkan: JPEG, PNG, WebP (validasi magic bytes + MIME + ekstensi).
- Ukuran maksimal: 10 MB (dikompresi otomatis jika > 2 MB via GD library).
- File disimpan di `assets/uploads/{context}/YYYYMM/` dengan nama random (bin2hex(16)).
- Foto lama otomatis dihapus saat upload baru (di direktori yang sama).
- Path traversal dicegah: validasi realpath + prefix check.
- Aktivitas upload/hapus foto dicatat di `activity_log`.

### Aturan Per-Entitas

| Entitas | Upload | Hapus | Batasan Status |
|---------|--------|-------|----------------|
| OPT (master_opt) | Admin | Admin | Tidak ada batasan status |
| Laporan Hama | Owner/Admin | Owner/Admin | Hanya `Draf` atau `Ditolak` |
| Laporan Irigasi | Owner/Admin | Owner/Admin | Hanya `Draf` atau `Ditolak` |

### Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/opt/{id}/foto` | JWT (admin) | Upload foto OPT |
| POST | `/api/v1/opt/{id}/foto/delete` | JWT (admin) | Hapus foto OPT |
| POST | `/api/v1/laporan-hama/{id}/foto` | JWT | Upload foto laporan hama (Draf/Ditolak only) |
| POST | `/api/v1/laporan-hama/{id}/foto/delete` | JWT | Hapus foto laporan hama (Draf/Ditolak only) |
| POST | `/api/v1/laporan-irigasi/{id}/foto` | JWT | Upload foto laporan irigasi (Draf/Ditolak only) |
| POST | `/api/v1/laporan-irigasi/{id}/foto/delete` | JWT | Hapus foto laporan irigasi (Draf/Ditolak only) |

### POST /api/v1/laporan-hama/{id}/foto

**Request:** `multipart/form-data`
```
foto: <binary file>
```

**Response 200:**
```json
{
  "success": true,
  "message": "Foto berhasil diunggah.",
  "data": {
    "id": 42,
    "foto_url": "assets/uploads/laporan-hama/202607/ab12cd34ef56ab78cd90ef12.jpg"
  }
}
```

**Response 422 (validasi gagal):**
```json
{
  "success": false,
  "error": "ValidationError",
  "message": "File bukan gambar yang diizinkan (JPEG/PNG/WebP)."
}
```

**Response 409 (status tidak sesuai):**
```json
{
  "success": false,
  "error": "Conflict",
  "message": "Status laporan tidak mengizinkan perubahan foto."
}
```

### POST /api/v1/laporan-hama/{id}/foto/delete

**Response 200:**
```json
{
  "success": true,
  "message": "Foto berhasil dihapus.",
  "data": {
    "id": 42,
    "foto_url": null
  }
}
```

**Response 404 (tidak ada foto):**
```json
{
  "success": false,
  "error": "NotFound",
  "message": "Laporan tidak memiliki foto."
}
```

### Catatan Implementasi

- Upload foto OPT hanya untuk **admin** (master data).
- Upload foto laporan hanya untuk **pemilik laporan** atau **admin**.
- Foto hanya bisa diupload/dihapus saat laporan berstatus `Draf` atau `Ditolak`.
- Gunakan `SecureImageUploader` helper untuk validasi keamanan berlapis:
  1. Magic bytes detection (JPEG `FF D8 FF`, PNG `89 50 4E 47...`, WebP `RIFF...WEBP`)
  2. `finfo` MIME type check
  3. Ekstensi file check
  4. Ukuran file check
  5. Nama file random (bin2hex(random_bytes(16)))
  6. Sub-direktori per bulan (`YYYYMM`)
- Gunakan `ImageCompressor` helper (GD library) untuk kompresi otomatis jika file > 2 MB (quality 75).

---




## Export — API (Implemented — Tahap 11)

### Aturan Umum

- Semua endpoint export: **authenticated** (JWT atau Session).
- **Admin**: global (semua data). **Petugas**: hanya data miliknya (`user_id = current`).
- Format: `csv` atau `xlsx`. Default: `csv`.
- Maksimal 10.000 baris per export. Jika lebih → 422 "Perketat filter."
- Maksimal rentang tanggal: 366 hari.
- File di-stream langsung ke browser (tidak disimpan permanen).
- Temp file XLSX dihapus setelah di-download (`storage/tmp/`).
- Aktivitas export dicatat di `activity_log` dengan action `export_hama` / `export_irigasi`.

### Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/export/hama` | JWT | Export laporan hama (format csv/xlsx) |
| GET | `/api/v1/export/irigasi` | JWT | Export laporan irigasi (format csv/xlsx) |

### Web Endpoints (Session)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/export` | Form filter UI |
| POST | `/export/hama` | Unduh file hama (CSRF protected) |
| POST | `/export/irigasi` | Unduh file irigasi (CSRF protected) |

### Query Parameters

| Parameter | Tipe | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `format` | string | No | `csv` | Format file: `csv` atau `xlsx` |
| `status` | string | No | (all) | Filter status, comma-separated: `Draf,Submitted,Diverifikasi,Ditolak,Diarsipkan` |
| `kabupaten_id` | int | No | (all) | Filter kabupaten |
| `kecamatan_id` | int | No | (all) | Filter kecamatan |
| `desa_id` | int | No | (all) | Filter desa |
| `tanggal_dari` | date | No | (all) | Filter tanggal awal (YYYY-MM-DD) |
| `tanggal_sampai` | date | No | (all) | Filter tanggal akhir (YYYY-MM-DD) |
| `include_draft` | bool | No | `false` | Sertakan laporan `Draf` (hanya berlaku bila `status` tidak diisi; bila `status` diisi, gunakan nilai tersebuat) |

### GET /api/v1/export/hama?format=csv&status=Submitted,Diverifikasi

**Response 200:** Binary file download

Headers:
```
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="laporan-hama-20260716-153000.csv"
Cache-Control: no-store
```

**Response 200 (XLSX):**
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="laporan-hama-20260716-153000.xlsx"
Cache-Control: no-store
```

**Response 422 (too many rows):**
```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Data terlalu banyak (15000 baris). Maksimal 10000 baris. Perketat filter."
}
```

**Response 422 (validasi filter):**
```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Validasi gagal.",
  "errors": {
    "tanggal_sampai": "Rentang tanggal maksimal 366 hari.",
    "format": "Format harus csv atau xlsx."
  }
}
```

### Kolom Export

#### Laporan Hama (22 kolom)

| No | Label | Sumber |
|----|-------|--------|
| 1 | Nomor Laporan | `lh.nomor_laporan` |
| 2 | Tanggal | `lh.tanggal` |
| 3 | Status | `lh.status` |
| 4 | Nama Petugas | `users.nama_lengkap` (pelapor) |
| 5 | Nama OPT | `master_opt.nama_opt` |
| 6 | Jenis OPT | `master_opt.jenis` |
| 7 | Tingkat Keparahan | `lh.tingkat_keparahan` |
| 8 | Luas Serangan | `lh.luas_serangan` |
| 9 | Populasi | `lh.populasi` |
| 10 | Kabupaten | `master_kabupaten.nama_kabupaten` |
| 11 | Kecamatan | `master_kecamatan.nama_kecamatan` |
| 12 | Desa | `master_desa.nama_desa` |
| 13 | Lokasi | `lh.lokasi` |
| 14 | Alamat Lengkap | `lh.alamat_lengkap` |
| 15 | Latitude | `lh.latitude` |
| 16 | Longitude | `lh.longitude` |
| 17 | Catatan | `lh.catatan` |
| 18 | Diverifikasi Oleh | `verifikator.nama_lengkap` |
| 19 | Tanggal Verifikasi | `lh.verified_at` |
| 20 | Catatan Verifikasi | `lh.catatan_verifikasi` |
| 21 | Dibuat Pada | `lh.created_at` |
| 22 | Diperbarui Pada | `lh.updated_at` |

#### Laporan Irigasi (19 kolom)

| No | Label | Sumber |
|----|-------|--------|
| 1 | Nomor Laporan | `li.nomor_laporan` |
| 2 | Tanggal | `li.tanggal` |
| 3 | Status | `li.status` |
| 4 | Nama Petugas | `users.nama_lengkap` (pelapor) |
| 5 | Nama Saluran | `li.nama_saluran` |
| 6 | Daerah Irigasi | `li.daerah_irigasi` |
| 7 | Kondisi Fisik | `li.kondisi_fisik` |
| 8 | Debit Air | `li.debit_air` |
| 9 | Kabupaten | `master_kabupaten.nama_kabupaten` |
| 10 | Kecamatan | `master_kecamatan.nama_kecamatan` |
| 11 | Desa | `master_desa.nama_desa` |
| 12 | Latitude | `li.latitude` |
| 13 | Longitude | `li.longitude` |
| 14 | Catatan | `li.catatan` |
| 15 | Diverifikasi Oleh | `verifikator.nama_lengkap` |
| 16 | Tanggal Verifikasi | `li.verified_at` |
| 17 | Catatan Verifikasi | `li.catatan_verifikasi` |
| 18 | Dibuat Pada | `li.created_at` |
| 19 | Diperbarui Pada | `li.updated_at` |

### Contoh Curl

```bash
# Export CSV laporan hama (JWT)
curl -H "Authorization: Bearer $TOKEN" \
  -o laporan-hama.csv \
  "http://localhost:8080/api/v1/export/hama?format=csv&status=Submitted,Diverifikasi&tanggal_dari=2026-01-01&tanggal_sampai=2026-06-30"

# Export XLSX laporan irigasi petugas (JWT)
curl -H "Authorization: Bearer $TOKEN" \
  -o laporan-irigasi.xlsx \
  "http://localhost:8080/api/v1/export/irigasi?format=xlsx&kabupaten_id=1"

# Export via web (session)
curl -c cookies.txt -b cookies.txt \
  -X POST \
  -d "format=csv&_csrf_token=..." \
  -o laporan.csv \
  "http://localhost:8080/export/hama"
```

### Batasan

| Aturan | Nilai |
|--------|-------|
| Maks baris | 10.000 |
| Maks rentang tanggal | 366 hari |
| Format valid | `csv`, `xlsx` |
| Status valid | `Draf`, `Submitted`, `Diverifikasi`, `Ditolak`, `Diarsipkan` |
| Role admin | Export semua data |
| Role petugas | Export data sendiri (`user_id` scope) |
| Temp file | XLSX: `storage/tmp/export_{random}.xlsx`, dihapus setelah download |
| Aktivitas dicatat | action: `export_hama` / `export_irigasi`, format, filename, row count |

---

## Dashboard & Statistik — API (Implemented — Tahap 10)

- (existing content unchanged after this point)

---

## User Management (Admin Only, Planned)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/users` | Admin | List users |
| POST | `/users` | Admin | Create user |
| GET | `/users/{id}` | Admin | Detail user |
| PUT | `/users/{id}` | Admin | Update user |
| DELETE | `/users/{id}` | Admin | Deactivate user |

---

## Error Response Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": [
      { "field": "latitude", "message": "Latitude is required" }
    ]
  }
}
```

---

## Success Response Format

```json
{
  "data": { ... },
  "meta": {
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7
    }
  }
}
```

---

## `include_draft` Behavior (Critical)

| Endpoint Category | Default | `include_draft=true` | `include_draft=false` |
|-------------------|---------|---------------------|----------------------|
| Dashboard stats | Exclude draft | Include draft | Exclude draft (default) |
| Map points | Exclude draft | Include draft | Exclude draft (default) |
| Analysis | Exclude draft | Include draft (if `analysis_ready`) | Exclude draft (default) |
| Export | Exclude draft | Include draft | Exclude draft (default) |
| List reports (petugas) | Include own draft | Include own draft | Exclude own draft |

> **Rule**: Semua endpoint yang menampilkan data agregat/statistik/visualisasi **wajib** support parameter `include_draft`.

---

## JWT Token Structure

**Access Token** (configurable, default 3600 detik / 1 jam):
```json
{
  "sub": 2,
  "role": "petugas",
  "username": "petugas01",
  "iat": 1742160000,
  "exp": 1742163600
}
```

**Key notes:**
- Algoritma: HMAC SHA-256 (HS256)
- Secret: env `JWT_SECRET` (minimal 64 karakter)
- Tidak ada refresh token terpisah — gunakan endpoint `/auth/refresh` untuk memperpanjang token yang masih valid
- Verifikasi dilakukan di `ApiAuthMiddleware` setiap request

---

## Dashboard & Statistik — API (Implemented — Tahap 10)

### Aturan Umum

- Semua endpoint dashboard: **authenticated** (JWT atau Session).
- **Admin**: agregat global. **Petugas**: hanya data miliknya (`user_id = current`).
- Statistik aktif = **Submitted + Diverifikasi** (Draf, Ditolak, Diarsipkan tidak masuk).
- Cache file TTL 5 menit di `storage/cache/`. Cache diinvalidate otomatis saat laporan dibuat/diverifikasi/ditolak/diarsipkan.
- Filter `tahun` (YYYY, default tahun berjalan). Range: 2020..(current+1).

### Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/dashboard/stats` | JWT | Ringkasan statistik hama + irigasi |
| GET | `/api/v1/dashboard/charts/hama` | JWT | Data chart bulanan hama (12 bucket) |
| GET | `/api/v1/dashboard/charts/irigasi` | JWT | Data chart bulanan irigasi (12 bucket) |
| GET | `/api/v1/dashboard/map/hama` | JWT | GeoJSON titik laporan hama |
| GET | `/api/v1/dashboard/map/irigasi` | JWT | GeoJSON titik laporan irigasi |

### Web JSON Endpoints (Session)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard/stats.json` | Stats (sama dengan API) |
| GET | `/dashboard/charts/hama.json` | Chart hama bulanan |
| GET | `/dashboard/charts/irigasi.json` | Chart irigasi bulanan |
| GET | `/dashboard/map/hama` | GeoJSON hama |
| GET | `/dashboard/map/irigasi` | GeoJSON irigasi |

### GET /api/v1/dashboard/stats

**Query params:** `tahun=2026`

**Response:**
```json
{
  "success": true,
  "data": {
    "tahun": 2026,
    "hama": {
      "total_submitted": 12,
      "total_diverifikasi": 40,
      "total_aktif": 52,
      "total_ditolak": 3,
      "total_draf": 2,
      "total_diarsipkan": 5,
      "luas_serangan_total": 123.45,
      "by_keparahan": {
        "Ringan": 20,
        "Sedang": 22,
        "Berat": 10
      },
      "top_opt": [
        {"master_opt_id": 3, "nama_opt": "Wereng Batang Coklat", "jumlah": 15}
      ]
    },
    "irigasi": {
      "total_submitted": 8,
      "total_diverifikasi": 25,
      "total_aktif": 33,
      "total_ditolak": 1,
      "total_draf": 0,
      "total_diarsipkan": 2,
      "by_kondisi_fisik": {"Bagus": 10, "Sedang": 12, "Tidak Bagus": 7, "Rusak": 4},
      "by_debit_air": {"Cukup": 15, "Kurang": 12, "Kering": 6}
    },
    "meta": {"cached": true, "generated_at": "2026-07-16 15:00:00"}
  }
}
```

### GET /api/v1/dashboard/charts/hama

**Response:**
```json
{
  "success": true,
  "data": {
    "tahun": 2026,
    "labels": ["Jan","Feb","Mar","Apr","Mei","Jun","Jul","Agu","Sep","Okt","Nov","Des"],
    "series": {
      "submitted": [1,2,0,4,0,0,0,0,0,0,0,0],
      "diverifikasi": [3,5,2,6,0,0,0,0,0,0,0,0],
      "aktif": [4,7,2,10,0,0,0,0,0,0,0,0]
    },
    "by_keparahan_bulanan": {
      "Ringan": [1,0,0,0,0,0,0,0,0,0,0,0],
      "Sedang": [0,0,0,0,0,0,0,0,0,0,0,0],
      "Berat": [0,0,0,0,0,0,0,0,0,0,0,0]
    }
  }
}
```

### GET /api/v1/dashboard/map/hama

**Query params:** `tahun=2026&status=aktif&limit=500`

**Response (GeoJSON FeatureCollection):**
```json
{
  "success": true,
  "data": {
    "type": "FeatureCollection",
    "features": [
      {
        "type": "Feature",
        "geometry": {"type": "Point", "coordinates": [113.7012, -8.1734]},
        "properties": {
          "id": 42,
          "nomor_laporan": "LH-20260716-0001",
          "status": "Diverifikasi",
          "tanggal": "2026-07-16",
          "desa": "Sumbersari",
          "kecamatan": "Sumbersari",
          "opt": "Wereng Batang Coklat",
          "tingkat_keparahan": "Sedang",
          "popup": "LH-20260716-0001 · Wereng · Sedang"
        }
      }
    ],
    "meta": {"count": 1, "limit": 500, "tahun": 2026}
  }
}
```

### Cache Design

| Aspek | Detail |
|-------|--------|
| Driver | File-based (`storage/cache/`) |
| TTL default | 300 detik (5 menit) |
| Key pattern | `dashboard:{type}:{role}:{userId}:{tahun}[:hash]` |
| Invalidation | Otomatis via `DashboardService::invalidateCache()` pada create/submit/verify/reject/archive/resubmit |
| Atomic write | Temp file + rename |
| Fallback | Jika cache tidak writable, query tetap jalan (no-cache) |

---

## Notifikasi — API (Implemented — Tahap 12)

### Aturan Umum

- Semua endpoint notifikasi: **authenticated** (JWT atau Session).
- Setiap user hanya melihat notifikasi miliknya sendiri (enforced di query).
- Notifikasi dibuat otomatis oleh hook di `LaporanHamaService` & `LaporanIrigasiService` pada event:
  - `laporan_submitted` → admin mendapat notifikasi laporan baru
  - `laporan_resubmitted` → admin mendapat notifikasi laporan dikirim ulang
  - `laporan_verified` → pemilik laporan mendapat notifikasi verifikasi
  - `laporan_rejected` → pemilik laporan mendapat notifikasi penolakan (+ cuplikan alasan)
  - `laporan_archived` → pemilik laporan mendapat notifikasi pengarsipan
- Body notifikasi reject: alasan di-truncate ~120 karakter.
- Kegagalan push (FCM) tidak mengganggu alur utama (catch + Logger::warning).

### Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/notifications` | JWT | List notifikasi (pagination + filter unread) |
| GET | `/api/v1/notifications/unread-count` | JWT | Jumlah notifikasi belum dibaca |
| POST | `/api/v1/notifications/{id}/read` | JWT | Tandai satu notifikasi telah dibaca |
| POST | `/api/v1/notifications/read-all` | JWT | Tandai semua notifikasi telah dibaca |
| DELETE | `/api/v1/notifications/{id}` | JWT | Hapus notifikasi |

### Web Endpoints (Session)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/notifications` | Halaman list notifikasi |
| GET | `/notifications/unread-count.json` | JSON unread count (untuk badge) |
| GET | `/notifications/recent.json` | JSON 5 notifikasi terbaru |
| POST | `/notifications/{id}/read` | Tandai baca + redirect (CSRF) |
| POST | `/notifications/read-all` | Tandai semua baca (CSRF) |
| POST | `/notifications/{id}/delete` | Hapus notifikasi (CSRF) |

### GET /api/v1/notifications

**Query params:** `page=1&limit=20&unread=1`

**Response 200:**
```json
{
  "success": true,
  "message": "Daftar notifikasi",
  "data": [
    {
      "id": 1,
      "user_id": 2,
      "type": "laporan_verified",
      "title": "Laporan diverifikasi",
      "body": "LH-20260716-0001 telah diverifikasi oleh admin.",
      "data": {
        "entity": "hama",
        "laporan_id": 1,
        "nomor_laporan": "LH-20260716-0001",
        "status": "Diverifikasi",
        "web_path": "/laporan-hama/1",
        "api_path": "/api/v1/laporan-hama/1"
      },
      "read_at": null,
      "created_at": "2026-07-16 14:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 1,
    "unread": 1
  }
}
```

### GET /api/v1/notifications/unread-count

**Response 200:**
```json
{
  "success": true,
  "message": "Unread count",
  "data": {
    "count": 3
  }
}
```

### POST /api/v1/notifications/{id}/read

**Response 200:**
```json
{
  "success": true,
  "message": "Notifikasi ditandai telah dibaca.",
  "data": {
    "id": 1
  }
}
```

**Response 404 (bukan milik user atau tidak ditemukan):**
```json
{
  "success": false,
  "error": "NotFound",
  "message": "Notifikasi tidak ditemukan."
}
```

### POST /api/v1/notifications/read-all

**Response 200:**
```json
{
  "success": true,
  "message": "Semua notifikasi ditandai telah dibaca.",
  "data": {
    "count": 3
  }
}
```

### DELETE /api/v1/notifications/{id}

**Response 200:**
```json
{
  "success": true,
  "message": "Notifikasi berhasil dihapus.",
  "data": {
    "id": 1
  }
}
```

### Event Matrix Notifikasi

| Event | Trigger | Penerima | Type |
|-------|---------|----------|------|
| Petugas submit laporan baru | `createAndSubmit()` / `submitDraft()` | Semua admin (kecuali aktor) | `laporan_submitted` |
| Petugas resubmit laporan | `resubmit()` | Semua admin (kecuali aktor) | `laporan_resubmitted` |
| Admin verifikasi laporan | `verify()` | Pemilik laporan | `laporan_verified` |
| Admin tolak laporan | `reject()` | Pemilik laporan | `laporan_rejected` |
| Admin arsip laporan | `archive()` | Pemilik laporan | `laporan_archived` |

### Notifikasi Push (Stub)

| Komponen | Detail |
|----------|--------|
| Interface | `PushNotifierInterface::send(userId, title, body, data)` |
| Default | `NullPushNotifier` (no-op) |
| FCM | `FcmPushNotifier` stub — aktif jika `FCM_ENABLED=true` dan `FCM_SERVER_KEY` terisi |
| Env | `FCM_ENABLED=false`, `FCM_SERVER_KEY=` |

---

## Device Tokens — API (Implemented — FCM)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/device-tokens` | JWT | Register/upsert FCM token |
| DELETE | `/api/v1/device-tokens` | JWT | Hapus token milik current user |
| DELETE | `/api/v1/device-tokens/all` | JWT | Hapus semua token user |

### POST /api/v1/device-tokens

**Request:**
```json
{
  "token": "fcm_token_here",
  "platform": "android"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Token berhasil didaftarkan.",
  "data": { "id": 1 }
}
```

**Perilaku:**
- Upsert: jika token sudah ada (milik user lain) → pindahkan ke user sekarang
- Update `last_seen_at` setiap kali register
- `platform`: `android`, `ios`, `web` (default `android`)

### DELETE /api/v1/device-tokens

**Request:**
```json
{
  "token": "fcm_token_here"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Token berhasil dihapus."
}
```

Hanya menghapus token milik current user.

### Push Payload (FCM Data)

```json
{
  "type": "laporan_rejected",
  "entity": "hama",
  "laporan_id": "42",
  "notification_id": "10",
  "nomor_laporan": "LH-20260716-0001",
  "status": "Ditolak"
}
```

Semua value FCM data adalah **string**.

---

## Storytelling Internal Web API (Implemented — Algorithm 2.0.0)

Endpoint ini berada pada router internal `/api`, memakai session cookie web,
role `admin`/`statistisi`, dan CSRF untuk request mutasi. Endpoint ini bukan bagian
dari kontrak JWT mobile `/api/v1`.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/storytelling/analyses` | Daftar analisis; filter `tahun`, `wilayah_id`, `status`, serta pagination |
| GET | `/api/storytelling/analyses/{id}` | Detail analisis dan snapshot sumber |
| POST | `/api/storytelling/generate` | Membuat indikasi hubungan dari `bulan`, `tahun`, `wilayah_id` |
| POST | `/api/storytelling/save` | Rekalkulasi server-side lalu create/update draft |
| POST | `/api/storytelling/publish/{id}` | Publikasi analisis yang memiliki narasi final |
| GET | `/api/storytelling/chart-data` | Tiga seri set-based untuk 1-24 bulan |
| GET | `/api/storytelling/stats` | Statistik draft/published/archived per tahun |

Input minimum generate:

```json
{
  "bulan": 8,
  "tahun": 2026,
  "wilayah_id": 1
}
```

Save hanya menerima periode, narasi final, dan override faktor. Nilai produksi,
hujan, OPT, serta seluruh skor dari client diabaikan dan dihitung ulang di server.
Jika produksi bulanan terverifikasi atau indikator minimum tidak tersedia, generate
mengembalikan HTTP `422` dengan `errors.error_code=InsufficientData` dan rincian
kualitas data. Output adalah indikasi hubungan berbasis aturan, bukan bukti kausalitas.

---

## Webhook / Callback (Future)

## Feedback Internal API (Implemented)

Endpoint berikut menggunakan session web dan hanya dapat diakses role `admin`.
Role `petugas` menerima HTTP 403 dan pengguna tanpa session menerima HTTP 401.
Respons selalu JSON `{success, message, data, timestamp}`; filter tidak valid
mengembalikan HTTP 422.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/feedback/summary` | Session Admin | Total status/jenis dan rekap per petugas |
| GET | `/api/feedback` | Session Admin | Daftar aduan paginated (`page`, `per_page`) |

Filter yang didukung (validasi server-side):

| Param | Nilai valid |
|-------|-------------|
| `year` | 2020 – (tahun berjalan + 1) |
| `month` | 0 (semua) – 12 |
| `jenis` | `bug`, `fitur_baru`, `peningkatan` |
| `status` | `diterima`, `dalam_proses`, `selesai`, `ditolak` |
| `search` | Teks bebas (hanya endpoint daftar), dicari di judul & deskripsi |

Pagination endpoint daftar: `page` (default 1) dan `per_page` (1–100, default
20). Response dihasilkan langsung dari database tanpa cache, sehingga
mencerminkan data terbaru pada request berikutnya.

Contoh `GET /api/feedback/summary?year=2026&month=8`:

```json
{
  "success": true,
  "message": "Rekap masukan berhasil diambil",
  "data": {
    "totals": { "total": 5, "pending": 3, "in_progress": 1, "completed": 1, "rejected": 0, "bugs": 2, "features": 2, "improvements": 1 },
    "by_petugas": [ { "user_id": 2, "nama_lengkap": "Petugas Lapangan 01", "username": "petugas01", "total": 5, "pending": 3, "in_progress": 1, "completed": 1, "rejected": 0 } ],
    "generated_at": "2026-08-20T04:50:00+07:00"
  },
  "timestamp": "2026-08-20 04:50:00"
}
```

### Web Feedback (server-rendered)

| Method | Endpoint | Akses | Description |
|--------|----------|-------|-------------|
| GET | `/feedback` | Petugas | Daftar masukan MILIK SENDIRI (ownership di-enforce di query) |
| GET/POST | `/feedback/create` | Petugas | Form + submit masukan (CSRF wajib) |
| GET | `/feedback/detail/{id}` | Petugas (milik sendiri) / Admin | Detail + riwayat status |
| POST | `/feedback/vote/{id}` | Petugas | Toggle vote; vote milik sendiri = 400, milik petugas lain = 403 (IDOR) |
| GET | `/feedback/admin-summary` | Admin | Panel rekap: total, per status, rekap per petugas, daftar aduan (filter year/month/jenis/status/search + pagination) |
| GET | `/feedback/report` | Admin | Laporan bulanan & statistik |
| POST | `/feedback/updateStatus/{id}` | Admin | Ubah status + catatan (dicatat ke `feedback_status_history`, transaksi) |
| POST | `/feedback/delete/{id}` | Admin | Hapus masukan + lampiran |

Validasi input (server-side): `jenis_feedback` whitelist; judul 5–255 karakter
dan deskripsi 20–5000 karakter dengan `mb_strlen` (multibyte-safe); prioritas
whitelist; catatan admin disimpan mentah (hanya trim) dan di-escape saat output
(mencegah double-escape/XSS); error upload dieksplisitkan (ukuran, parsial,
MIME via magic bytes `finfo`, ekstensi dari MIME aktual, nama file acak,
`is_uploaded_file`, direktori dilindungi `.htaccess`). Semua aksi mutasi web
wajib CSRF token.

- Webhook verifikasi ke sistem eksternal

---

## Next Steps

- Tahap 13: Mobile App Flutter (auth, offline draft, sync, JWT)
- Push FCM production (device token registration + FcmPushNotifier)
- OpenAPI/Swagger docs final
- Generate SDK/client untuk Flutter
