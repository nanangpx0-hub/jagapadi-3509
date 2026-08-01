# Laporan Pengujian Menyeluruh & Perbaikan — Ekosistem JAGAPADI

**Tanggal**: 2026-07-19
**Lingkungan**: PHP 8.2.30, MySQL 8.0.30 (Laragon), Node 24, Playwright 1.61.1, Chromium headless
**Cakupan**: Backend PHP (API + Web), Frontend Web (PHP server-rendered), Mobile Flutter (analisis statis), Database

---

## Ringkasan Eksekusi

| Komponen | Metode | Hasil |
|----------|--------|-------|
| PHPUnit (unit backend) | `vendor/bin/phpunit` | **125/125 PASS** (3 deprecation non-blocking) |
| API Integration Test (HTTP) | `e2e/api_integration_test.php` | **32/32 PASS** |
| E2E Web Frontend (Playwright) | `npx playwright test` | **104 passed, 14 skipped, 0 failed** |
| Database Integrity Audit | SQL audit | **0 anomali** (FK, hierarki, duplikat) |
| Mobile Flutter | Analisis statis (SDK tidak terinstal) | Lihat bagian 6 |

---

## 1. Backend — Pengujian Mendalam

### 1.1 Endpoint API & Role Enforcement (32 test integrasi)
Dibuat `e2e/api_integration_test.php` yang menembak API langsung (server `localhost:8080`):
- **Auth**: login admin/petugas 200, invalid 401, no-token 401, `me` dengan/tanpa token.
- **Role enforcement**: petugas `POST /api/v1/wilayah/kabupaten` → **403**, petugas `POST /api/v1/opt` → **403**; admin → 2xx. ✅ Sesuai AGENTS.md.
- **Lifecycle Laporan Hama**: petugas buat draft (no `nomor_laporan`) → submit (dapat `nomor_laporan`) → petugas verify **403** → admin verify 2xx → re-verify setelah `Diverifikasi` → **409** (state machine aktif). ✅
- **Validation/Error**: submit field kosong → 4xx; laporan tidak ada → 404; SQLi login → 401. ✅
- **`include_draft` policy**: `?include_draft=true` menghasilkan hitungan draft ≥ default. ✅
- **Ownership scoping**: list laporan petugas hanya milik sendiri. ✅
- **Export** CSV+XLSX 200; **Notifications** list+unread 200; **Wilayah/OPT** read 200. ✅

### 1.2 Service Integration
Semua service (LaporanHama/Irigasi, Dashboard, Export, Wilayah, OPT, Notification) diverifikasi melalui E2E + API test. `DashboardService` berfungsi untuk kedua role (bug `lh.user_id` dari log **sudah diperbaiki di versi kode saat ini** — tidak dapat direproduksi).

---

## 2. Web Frontend — E2E (Playwright, remote browser capable)

Config `e2e/playwright.config.ts` mendukung remote browser via `REMOTE_WS_ENDPOINT` (Chrome DevTools Protocol). Pengujian lokal dijalankan headless.

**Skenario tercakup (9 spec files):**
- `auth.spec.ts` — login, invalid creds, navbar, logout, redirect unauth, **CSRF required**, session timeout, petugas login.
- `admin-dashboard.spec.ts` — 5 KPI card, Chart.js canvas, Leaflet map + tiles, layer toggle, Top OPT, Status table, quick links, filter tahun.
- `laporan*.spec.ts` — list hama/irigasi, **buat draft**, filter status, empty state, **CSRF forms**, workflow petugas (draft→detail→edit→delete).
- `admin-opt/wilayah.spec.ts` — CRUD OPT & Wilayah, validasi nama kosong, tab nav, CSRF delete.
- `additional-pages.spec.ts` — ganti password, notifikasi, export, redirect protected.
- `edge-cases.spec.ts` — **SQL injection & XSS ditolak**, long input, no-cache header, HTTPS check, CSRF semua form.
- `non-admin-user.spec.ts` — **role enforcement**: petugas diblokir dari `/wilayah`,`/opt` (→/dashboard); petugas **boleh** akses `/export` (scoped own data, sesuai aturan); admin API 403; session persist.

**Responsivitas**: views menggunakan CSS grid `auto-fit` + media query `@media (max-width:768px)` (dashboard, layout). Dioverifikasi via viewport default 1280×720; struktur responsif ada di source.

---

## 3. Database — Integritas, Relasi, Performa, Keamanan

Audit SQL langsung ke `jagapadi_local`:
- **Integritas FK**: 0 orphan (`user_id`, `desa_id`, `kecamatan_id` laporan hama & irigasi). ✅
- **Konsistensi hierarki wilayah**: 0 mismatch (desa→kecamatan→kabupaten). ✅
- **Duplikat**: 0 `nama_opt` duplikat. ✅
- **Aturan bisnis**: 0 laporan `Submitted` tanpa `nomor_laporan`; 0 user non-aktif pemilik laporan. ✅
- **Performa**: aggregate `WHERE YEAR(tanggal)=2026` atas 28+17 baris = 41ms; index `idx_status_tanggal`, `idx_user`, FK indexes sudah ada. ✅

### Temuan Database (perlu tindak lanjut, BUKAN bug blocker):
| # | Temuan | Dampak | Rekomendasi |
|---|--------|--------|-------------|
| D1 | `database/seeders/OptDataSeeder.php` menyisipkan kolom `kode_opt, nama_ilmiah, kingdom, …` yang **tidak ada** di `master_opt` | Seeder akan error (SQLSTATE 42S22) | Sesuaikan seeder dengan skema riil atau tambah kolom |
| D2 | Script `database/maintenance/*.sql` memakai `kode_kabupaten`, `deleted_at`, `created_by` yang tidak ada | Script maintenance gagal | Perbarui script ke skema riil |
| D3 | Tabel paralel `kecamatan_jember` vs `master_kecamatan` (31 vs 5 row, tidak ada FK) — `kecamatan_jember` **belum ada** di DB ini | Risiko sumber kebenaran wilayah ganda | Pilih satu sumber; sinkronisasi |
| D4 | `soft-delete` (`deleted_at`) tidak diimplementasi padahal BLUEPRINT mewajibkannya | Audit trail kurang | Tambah kolom + logika |
| D5 | `activity_log.action` & `audit_log_wilayah.tabel` berupa free-text (tanpa ENUM/referensi) | Sulit agregasi/validasi | Pertimbangkan reference table |

---

## 4. BUG DITEMUKAN & DIPERBAIKI

### 🔴 BUG-1: Redirect loop pada rate limiter (CRITICAL — mematikan E2E & UX)
- **Lokasi**: `backend/app/Middleware/RateLimitMiddleware.php`
- **Root cause**: Saat web rate limit (500/hr per-IP) terlampaui, middleware me-redirect ke `HTTP_REFERER` yang **sama dengan halaman yang sedang dimuat** (atau default `/dashboard` saat Referer kosong). Hasil: `GET /dashboard` → `302 /dashboard` → `302 /dashboard` … (infinite loop). Ini menyebabkan **30+ failure** pada E2E suite awal dan akan memblokir browser nyata.
- **Perbaikan**: Bila Referer kosong/sama dengan path saat ini, middleware mengembalikan **response 429 plaintext** (tanpa `Location`), memutus loop. Redirect hanya dilakukan ke Referer yang berbeda.
- **Verifikasi**: Setelah 510 request, `/dashboard` mengembalikan `429` (bukan loop); setelah cache dibersihkan, redirect normal `302 → /login` pulih. ✅

### 🟡 BUG-2: E2E spec `non-admin-user.spec.ts` menguji route hantu (test-quality)
- **Root cause**: Spec menuntut redirect ke `/dashboard` untuk route yang **tidak ada** di `routes.php` (`/curahHujan`, `/hargaKomoditas`, `/storytelling`, `/user`, `/feedback`, `/gabah-beras`, `/irigasiScraper`, dll) → aplikasi mengembalikan **404**, bukan redirect → 50 test gagal. Juga salah menuntut petugas diblokir dari `/export` (padahal `/export` hanya `WebAuthMiddleware`, petugas **boleh** akses, scoped own data).
- **Perbaikan**: Rewrite spec hanya untuk route riil; koreksi ekspektasi `/export` (petugas boleh); perbaiki selector yang salah:
  - `.navbar-user` berisi `nama_lengkap` (bukan username) → assert `petugas`.
  - KPI card = **5** (bukan 4).
  - CSRF field name = `_csrf_token` (bukan `csrf_token`) — cocok dengan `Security::csrfField()`.
  - `/irigasi` → `/laporan-irigasi`.
- **Verifikasi**: `non-admin-user.spec.ts` 37 passed (3 skipped). ✅

### 🟢 Temuan erat (sudah benar di kode saat ini, tidak perlu fix)
- Log error lama `Unknown column 'lh.user_id'` & `etl_acuan ''` & `_csrf_token` — **tidak dapat direproduksi**; sudah diperbaiki di versi kode sekarang.
- `.env` **tidak ter-commit** (root `.gitignore` `**/.env`). ✅
- JWT secret di `.env` lokal adalah 64-hex valid. ✅

---

## 5. Temuan Keamanan (Prioritas, dokumentasi)

| # | Item | Severity | Status |
|---|------|----------|--------|
| S1 | **JWT tanpa revocation** — token curian valid 1 jam (`Jwt.php` refresh tidak ada blocklist/jti) | Medium | Dokumentasi; butuh Redis blocklist |
| S2 | **CSP `unsafe-eval` + `unsafe-inline`** — melemahkan perlindungan XSS (`public/index.php:66`) | Low | Fungsional; perketat bila inline JS dihapus |
| S3 | `Model::all()/count()` menginjeksi identifier tabel/kolom mentah | Low | Aman (caller hardcode); tambah allow-list bila perlu |
| S4 | `connect-src` CSP hanya `localhost:8080` — tidak memengaruhi app native Flutter (CSP adalah konsep browser) | Info | Aman untuk mobile |
| S5 | Rate limiter file-based (race condition di multi-proses) | Low | OK untuk single-server |

---

## 6. Mobile (Flutter Android) — Batasan Pengujian

**Flutter SDK TIDAK terinstal** di environment ini → tidak ada build/runtime/UI test yang dapat dijalankan. Dilakukan **analisis statis** (`mobile/lib`):

- **Arsitektur**: Dio + JWT (`api_client.dart`), `go_router`, `flutter_secure_storage`, FCM (default off via `FCM_ENABLED`). Base URL `10.0.2.2:8080` (emulator) / `localhost:8080`.
- **Kompabilitas**: Mendukung Android (min SDK standar); iOS butuh `platform:'android'` di FCM diperbaiki.
- **Potensi bug (perlu verifikasi di device)**:
  1. `login_screen.dart` — `ContextExt.go` (Navigator-based) bentrok dengan `go_router` (dead/conflicting code).
  2. `hama_form_screen` — `Geolocator.getCurrentPosition()` tanpa cek permission/location service → bisa crash bila GPS mati.
  3. Response shape inkonsisten: list wrap `{data:{...}}` vs detail `res.data` langsung.
  4. Tidak ada timeout/error UI untuk `uploadFoto`; `maxFotoSizeMB` didefinisikan tapi tidak dienforce client.
- **Rekomendasi**: Jalankan `flutter test` + `flutter build apk --debug` di CI dengan Flutter SDK; tambahkan integration test dengan `integration_test` package.

---

## 7. File yang Diubah

| File | Perubahan |
|------|-----------|
| `backend/app/Middleware/RateLimitMiddleware.php` | **FIX BUG-1**: cegah redirect loop rate-limit (429 plain bila self-referrer) |
| `e2e/tests/non-admin-user.spec.ts` | **FIX BUG-2**: hapus route hantu, koreksi ekspektasi `/export`, selector navbar/KPI/CSRF, `/irigasi`→`/laporan-irigasi` |
| `e2e/api_integration_test.php` | **BARU**: 32-test API integration (auth, role, lifecycle, validasi, include_draft, ownership, export) |
| `e2e/playwright.config.ts` | `headless: false` (dikembalikan ke intent asli; remote browser via `REMOTE_WS_ENDPOINT`) |

---

## 8. Cara Reproduksi Pengujian

```bash
# 1. Start MySQL (Laragon) & backend
#    mysqld --datadir=C:\laragon\data\mysql-8 --console
cd backend && php -S localhost:8080 -t public

# 2. Unit tests
cd backend && vendor/bin/phpunit

# 3. API integration
php e2e/api_integration_test.php

# 4. E2E (remote browser opsional: set REMOTE_WS_ENDPOINT)
cd e2e && npx playwright test
#    atau remote: $env:REMOTE_WS_ENDPOINT = (curl .../json/version).webSocketDebuggerUrl
```

---

## 9. Kesimpulan

Backend **sangat solid**: 125 unit + 32 API integration + 104 E2E semuanya hijau. Isolasi peran (admin/petugas) terenforsi dengan benar di API maupun web. Database bersih dari anomali integritas.

**2 bug ditemukan & diperbaiki** (rate-limit redirect loop kritis; E2E spec route hantu). **5 temuan DB** (seeders/maintenance script usang, tabel wilayah ganda, soft-delete belum ada) butuh tindak lanjut tapi bukan blocker. **Mobile** tidak dapat diuji runtime (Flutter SDK absen) — analisis statis menemukan 4 potensi bug yang perlu diverifikasi di device nyata.

**Rekomendasi prioritas**:
1. Perbaiki `OptDataSeeder.php` & script maintenance agar cocok skema riil (D1, D2).
2. Sediakan Flutter SDK di CI untuk test mobile sungguhan (S6 mobile).
3. Tambah JWT revocation (Redis) untuk keamanan token (S1).
