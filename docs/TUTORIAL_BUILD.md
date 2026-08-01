# TUTORIAL BUILD JAGAPADI — 15 Tahap Pembangunan

> Referensi: `docs/Dokumentasi-aplikasi-jagapadi-3509.md` (blueprint lengkap)

---

## Daftar Tahap

| No | Tahap | Nama | Status | Catatan |
|----|-------|------|--------|---------|
| 0 | **Tahap 0** | Persiapan & Riset | ✅ Done | Dokumen blueprint & riset teknis selesai |
| **1** | **Tahap 1** | **Repository & Standar Kerja** | ✅ **Done** | Setup monorepo, `.gitignore`, `AGENTS.md`, docs, templates |
| **2** | **Tahap 2** | **Backend Skeleton** | ✅ **Done** | PHP 8.2 MVC, Router, PDO, Config, `.env.example` |
| **3** | **Tahap 3** | **Database Schema & Migration** | ✅ **Done** | MariaDB/MySQL utf8mb4, 11 tabel, migration runner, seeders |
| **4** | **Tahap 4** | Auth Web (Session+CSRF) & Auth Mobile (JWT) | ✅ **Done** | Web: Session aman + CSRF, middleware chain; API: JWT HS256; Role admin/petugas; Rate limiter; Activity log; Password policy; Must change password
| **5** | **Tahap 5** | Master Data Wilayah & OPT | ✅ **Done** | Master wilayah (kab/kec/desa) + audit log; Master OPT CRUD + soft deactivate; API read auth, write admin; Web admin cascading dropdown
| 6 | **Tahap 6** | Laporan Hama (Draf, Submit, List, Detail) | ✅ **Done** | CRUD draft, submit, nomor LH-..., validasi field wajib, petugas scoped
| 7 | **Tahap 7** | Laporan Irigasi (Draf, Submit, List, Detail) | ✅ **Done** | CRUD draft, submit, nomor LI-..., validasi field wajib, petugas scoped
| 8 | **Tahap 8** | Verifikasi Admin (Hama & Irigasi) | ✅ **Done** | Submitted → Diverifikasi/Ditolak, Diverifikasi → Diarsipkan, Ditolak → Submitted (resubmit)
| 9 | **Tahap 9** | Upload Foto Aman | ✅ **Done** | SecureImageUploader (magic bytes, MIME, random name, path traversal), ImageCompressor (GD auto-compress >2MB), API + Web endpoints, unit tests
| 10 | **Tahap 10** | Dashboard, Statistik & Cache | ✅ **Done** | DashboardService + CacheManager + API stats/charts/map + web Chart.js/Leaflet |
| 11 | **Tahap 11** | Export XLSX/CSV | ✅ **Done** | ExportService (filter + stream), CsvWriter, XlsxWriter, Web + API controllers |
| 12 | **Tahap 12** | Notifikasi & Real-time | ✅ **Done** | In-app notification, DB migration 009, NotificationService, Web bell + polling API, NullPushNotifier |
| 13 | **Tahap 13** | Mobile App Flutter | ⏳ Pending | Auth, offline draft, sync, JWT |
| 14 | **Tahap 14** | Testing & QA | ⏳ Pending | Unit, feature, E2E, security audit |
| 15 | **Tahap 15** | Deployment & CI/CD | ✅ **Done** | docs/DEPLOY.md, CORS env, backup scripts, prune cron, SMOKE_TEST, GO_LIVE_CHECKLIST |
| 16 | **Tahap 16** | Dokumentasi Akhir & Handover | ⏳ Pending | API docs final, runbook, knowledge transfer |

---

## Catatan Penting per Tahap

### Tahap 1 (Current) — Repository & Standar Kerja
- **Scope**: Hanya setup repo, docs, templates, AGENTS.md
- **TIDAK**: Backend code, DB, Flutter, Composer, CI workflow aktif
- **Output**: Monorepo bersih, `.gitignore`, `AGENTS.md`, `README.md`, `CHANGELOG.md`, docs placeholder, `.editorconfig`, GitHub templates

### Tahap 2 — Backend Skeleton
- `backend/public/index.php` entry point
- Router sederhana (FastRoute atau custom)
- PDO connection factory
- Config loader (`.env` → `config/*.php`)
- MVC structure: `app/{Controllers,Models,Views,Middleware,Services}`
- `composer.json` (PHP 8.2, psr-4 autoload)
- `.env.example` (no secrets)

### Tahap 3 — Database Schema & Migration
- Migration runner (Phinx atau custom)
- Schema: users, roles, reports_hama, reports_irigasi, attachments, verifications, subdistricts, villages, report_numbers
- Seeders: roles, admin user
- `DATABASE.md` updated with DDL

### Tahap 4 (Current) — Authentication
- Web: Session + CSRF (middleware), login/logout, role guard (WebAuthMiddleware, AdminMiddleware)
- Mobile: JWT (HS256, configurable expiry), refresh endpoint, change password
- Password: `password_hash(bcrypt, cost 12)` + `password_verify()`, validator (min 8 chars, upper, lower, digit, special)
- Rate limiter: file-based brute force protection (5 gagal / IP / 15 menit)
- Activity log: catat login_success, login_failed, logout, password_changed

### Tahap 5 — Master Data Wilayah & OPT
- `app/Models/MasterKabupaten.php`, `MasterKecamatan.php`, `MasterDesa.php` — model wilayah
- `app/Models/MasterOpt.php` — model OPT dengan soft deactivate (`aktif` field)
- `app/Models/AuditLogWilayah.php` — audit trail perubahan wilayah
- `app/Services/WilayahService.php` — cascading dropdown kab → kec → desa
- `app/Services/MasterOptService.php` — CRUD OPT, daftar aktif saja, soft deactivate
- `app/Controllers/Api/WilayahController.php` — read-only wilayah endpoints (public index per level)
- `app/Controllers/Api/OptController.php` — CRUD OPT API (read all auth, write admin)
- `app/Controllers/Web/OptController.php` — CRUD OPT web admin
- `app/Views/opt/` — index, form views
- `tests/Unit/MasterOptServiceTest.php` — unit test create, update, deactivate, validasi duplikat

### Tahap 6 — Laporan Hama
- `app/Helpers/NomorLaporanGenerator.php` — atomic nomor LH: prefix `LH`, date, counter via `nomor_laporan_counter`
- `app/Helpers/LaporanHamaValidator.php` — validasi Draf (parsial) dan Submit (lengkap)
- `app/Models/LaporanHama.php` — model with findAccessibleById, listForPetugas, listForAdmin, deleteDraft
- `app/Services/LaporanHamaService.php` — CRUD draft, submit, generate nomor, activity log
- `app/Controllers/Api/LaporanHamaController.php` — 6 API endpoints
- `app/Controllers/Web/LaporanHamaController.php` — 8 web endpoints
- `app/Views/laporan-hama/` — index, create, edit, show views
- `tests/Unit/LaporanHamaValidatorTest.php` — 12 test cases

### Tahap 7 — Laporan Irigasi
- `app/Helpers/LaporanIrigasiValidator.php` — validasi Draf (parsial) dan Submit (lengkap) irigasi
- `app/Models/LaporanIrigasi.php` — model with findAccessibleById, listForPetugas, listForAdmin
- `app/Services/LaporanIrigasiService.php` — CRUD draft, submit, generate nomor LI, activity log
- `app/Controllers/Api/LaporanIrigasiController.php` — 6 API endpoints
- `app/Controllers/Web/LaporanIrigasiController.php` — 8 web endpoints
- `app/Views/laporan-irigasi/` — index, create, edit, show views
- `tests/Unit/LaporanIrigasiValidatorTest.php` — 11 test cases

### Tahap 8 — Verifikasi Admin (Hama & Irigasi)
- `app/Helpers/LaporanStatus.php` — status constants + transition matrix
- `app/Services/LaporanHamaService.php` / `LaporanIrigasiService.php` — verify, reject, archive, resubmit
- List `Submitted` untuk admin dengan action buttons
- Action: Verify → `Diverifikasi`, Reject → `Ditolak` (dengan alasan), Archive → `Diarsipkan`
- Resubmit: `Ditolak` → `Submitted` atau `Draf` (oleh petugas)
- Audit trail: `verified_by`, `verified_at`, `catatan_verifikasi` di laporan
- API + Web controllers dengan 4 action endpoints each
- `tests/Unit/LaporanStatusTest.php` — 22 test cases

### Tahap 9 — Upload Foto Aman (OPT + Laporan)
- `app/Helpers/SecureImageUploader.php` — validasi magic bytes (JPEG/PNG/WebP), finfo MIME, ekstensi, ukuran (max 10MB), random name (bin2hex 16 bytes), path traversal protection, auto-kompresi >2MB via GD library
- `app/Helpers/ImageCompressor.php` — GD-based compression (JPEG quality 75, PNG compression 7, WebP quality 75)
- 6 API endpoints: POST `{entity}/{id}/foto` dan `{entity}/{id}/foto/delete` (OPT, laporan-hama, laporan-irigasi)
- 6 Web routes: POST untuk upload + delete foto
- Controllers: uploadFoto() + deleteFoto() di Api/Web controllers untuk OPT, LaporanHama, LaporanIrigasi
- Views: edit forms + show details dengan display foto dan upload/delete UI
- Storage: `public/assets/uploads/{opt-photos,laporan-hama,laporan-irigasi}/YYYYMM/`
- Folder protection via `.htaccess` (php_flag engine off, deny PHP execution)
- Foto hanya bisa diupload/dihapus saat laporan berstatus `Draf` atau `Ditolak`
- `tests/Unit/SecureImageUploaderTest.php` — 12 test cases
- `tests/Unit/ImageCompressorTest.php` — 4 test cases

### Tahap 10 — Dashboard, Statistik & Cache
- `app/Core/CacheManager.php` — File-based cache (TTL 300s, atomic write, prefix delete, fallback no-cache)
- `app/Services/DashboardService.php` — Agregasi statistik + chart + map dengan cache orchestration; scope admin (global) vs petugas (user_id)
- `app/Controllers/Web/DashboardController.php` — index + 5 JSON endpoints (stats/charts hama/irigasi, map hama/irigasi)
- `app/Controllers/Api/DashboardController.php` — 5 API endpoints (stats, charts hama/irigasi, map hama/irigasi)
- `app/Views/dashboard/index.php` — KPI cards (aktif, menunggu verifikasi, ditolak, luas serangan), Chart.js bar chart, Leaflet map, top OPT table, status breakdown, quick links
- `public/assets/js/dashboard.js` — Chart.js init x2, Leaflet map dengan toggle hama/irigasi, GeoJSON loading
- Cache invalidation on write: semua service mutation (create/submit/verify/reject/archive/resubmit) panggil `DashboardService::invalidateCache()`
- Hanya Submitted + Diverifikasi masuk statistik aktif. Draf/Ditolak/Diarsipkan eksklusif dari KPI utama.
- Koordinat GeoJSON [lng, lat]. Map limit 500 default, max 1000.
- `tests/Unit/DashboardServiceTest.php` — 8 test cases (validasi tahun, status filter, chart 12 bulan, cache invalidation, GeoJSON format, map cap)

### Tahap 11 — Export XLSX/CSV (Done)
- `app/Services/ExportService.php` — Validasi filter (format, status, tanggal, wilayah), COUNT query dengan WHERE dinamis JOIN fetch semua field, stream CSV/XLSX ke browser dengan header yang benar
- `app/Helpers/CsvWriter.php` — Simple CSV writer, UTF-8 BOM (EF BB BF), fputcsv
- `app/Helpers/XlsxWriter.php` — Pure PHP XLSX generator: XML sheets + shared strings + inline styles, ZIP via PclZip, temp file di `storage/tmp/` dihapus setelah download
- `app/Controllers/Web/ExportController.php` — GET `/export` render form, POST `/export/hama` dan `/export/irigasi` download
- `app/Controllers/Api/ExportController.php` — GET `/api/v1/export/hama` dan `/api/v1/export/irigasi`, JWT auth
- `app/Views/export/index.php` — Form dengan radio jenis/format, checkbox status, cascading kab → kec → desa (via API wilayah), input tanggal, CSRF token
- ExportService::validateFiltersStatic() — static validation (format, status whitelist, tanggal format/range/wilayah) tanpa DB
- Scope: admin global, petugas scoped ke `user_id`. Maks 10.000 baris. Range max 366 hari. Status multi select comma-separated.
- Activity Log: `export_hama` / `export_irigasi` dicatat dengan format, filename, jumlah baris
- `composer.json` — Added `pclzip/pclzip` (pure PHP Zip library untuk XLSX)

### Tahap 12 — Flutter Mobile App
- `flutter create` di `mobile/`
- Riverpod/BLoC state management
- Offline-first: SQLite (drift/sqflite) untuk draft
- Sync queue saat online
- JWT auth with secure storage (flutter_secure_storage)
- Camera & gallery pick, compress, upload

### Tahap 11 — Notifikasi & Real-time
- Firebase Cloud Messaging (FCM) untuk mobile
- Email notifikasi untuk admin (verifikasi baru, laporan ditolak)
- In-app notification center
- WebSocket/polling untuk update real-time

### Tahap 12 — Testing & QA
- PHPUnit (backend), widget test (Flutter)
- PHPStan Level 5+, Psalm
- CS Fixer (PSR-12)
- Security audit: SQLi, XSS, CSRF, auth bypass
- Load test API endpoints

### Tahap 13 — Deployment & CI/CD
- GitHub Actions: lint, test, build
- Deploy script: rsync/ssh ke cPanel
- `backend/public` sebagai document root
- DB migration runner di deploy
- Health check endpoint

### Tahap 14 — Dokumentasi Akhir & Handover
- API docs (OpenAPI/Swagger)
- Runbook: deploy, rollback, backup, restore
- Architecture decision records (ADR) final
- Handover session

---

## Prinsip Kerja

1. **Satu tahap = satu PR/issue utama** (bisa dipecah sub-task)
2. **Tidak lompat tahap** — selesaikan tahap N sebelum N+1
3. **Test & lint wajib** sebelum merge
4. **Update docs** (`docs/`, `CHANGELOG.md`, `README.md`) di setiap tahap
5. **Migration wajib** jika skema DB berubah
6. **Tidak commit secret** — ever

---

## Referensi Cepat

- `AGENTS.md` — Aturan agent coding
- `docs/BLUEPRINT.md` — Arsitektur & kebijakan bisnis
- `docs/API.md` — Kontrak API (evolving)
- `docs/DATABASE.md` — Skema DB (evolving)
- `docs/ADR/README.md` — Catatan keputusan arsitektur