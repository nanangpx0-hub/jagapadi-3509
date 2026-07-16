# API Contract JAGAPADI

> **Status**: Tahap 5 — Master Data Wilayah dan Master OPT telah diimplementasikan.
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

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/laporan-hama` | JWT | List (filter: status, tanggal, wilayah, OPT, q, page, limit, include_draft) |
| POST | `/api/v1/laporan-hama` | JWT | Create (action=draft|submit, default draft) |
| GET | `/api/v1/laporan-hama/{id}` | JWT | Detail (owner/admin only) |
| PUT | `/api/v1/laporan-hama/{id}` | JWT | Update Draf (owner only) |
| DELETE | `/api/v1/laporan-hama/{id}` | JWT | Delete Draf (owner only) |
| POST | `/api/v1/laporan-hama/{id}/submit` | JWT | Submit Draf → Submitted |

**Request POST /api/v1/laporan-hama:**
```json
{
  "action": "submit",
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
    "luas_serangan": "Luas serangan harus antara 0 dan 9999.99"
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

### Laporan Irigasi (Planned)
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/laporan-irigasi` | JWT | List (filter: status, date, location, `include_draft`) |
| POST | `/api/v1/laporan-irigasi` | JWT | Create draft |
| GET | `/api/v1/laporan-irigasi/{id}` | JWT | Detail (owner/admin) |
| PUT | `/api/v1/laporan-irigasi/{id}` | JWT | Update draft (owner) |
| DELETE | `/api/v1/laporan-irigasi/{id}` | JWT | Delete draft (owner) |
| POST | `/api/v1/laporan-irigasi/{id}/submit` | JWT | Submit draft → Submitted |

---

## Dashboard & Statistics (Planned)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/dashboard/stats` | Auth | Summary counts (filter `include_draft`) |
| GET | `/dashboard/trends` | Auth | Time-series (filter `include_draft`) |
| GET | `/dashboard/by-location` | Auth | Aggregated by kecamatan/desa (filter `include_draft`) |
| GET | `/dashboard/by-pest` | Auth | Hama breakdown (filter `include_draft`) |
| GET | `/dashboard/by-channel` | Auth | Irigasi channel breakdown (filter `include_draft`) |

---

## Map / Geospatial (Planned)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/map/reports` | Auth | GeoJSON points (filter: status, type, date, `include_draft`) |
| GET | `/map/clusters` | Auth | Clustered points for zoom levels |

---

## Analysis (Planned)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/analysis/hama` | Auth | Hama analysis (only `analysis_ready=true`, filter `include_draft`) |
| GET | `/analysis/irigasi` | Auth | Irigasi analysis (only `analysis_ready=true`, filter `include_draft`) |
| GET | `/analysis/comparison` | Auth | Period comparison |

---

## Export (Planned)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/export/hama` | Admin | CSV/Excel/PDF (filter `include_draft`) |
| GET | `/export/irigasi` | Admin | CSV/Excel/PDF (filter `include_draft`) |
| GET | `/export/dashboard` | Admin | Dashboard summary export |

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

## Webhook / Callback (Future)

- Notifikasi push ke mobile (FCM)
- Webhook verifikasi ke sistem eksternal

---

## Next Steps

- Tahap 5: Implement report CRUD endpoints (Hama/OPT & Irigasi)
- Update `docs/API.md` dengan kontrak final (OpenAPI/Swagger)
- Generate SDK/client untuk Flutter