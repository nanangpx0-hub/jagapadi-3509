# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.1] - 2026-08-09

### Fixed
- **OpenMeteoService.php** — `loadLocations()` kini mengambil kolom `latitude` dan `longitude` dari `master_kecamatan` (sebelumnya hanya `id, nama_kecamatan, kode` sehingga seluruh 31 kecamatan jatuh ke koordinat default Jember). Query juga difilter `WHERE latitude IS NOT NULL AND longitude IS NOT NULL`.
- **WeatherService.php** — `getForKecamatan()` kini mengambil `latitude`/`longitude` dari `master_kecamatan`; memanggil `getForecast()` dengan koordinat sebenarnya, dan menambahkan fallback ke koordinat default Jember + `error_log` warning bila koordinat hilang.
- **CurahHujanScraper.php** — Aktifkan SSL certificate verification (default) pada `httpRequest()` dan `fetch_nasa_curah_hujan()`: `CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2` dengan fallback via env `CURL_SSL_VERIFY=false` untuk development.
- **HargaKomoditasScraper.php** — Aktifkan SSL certificate verification pada `fetchSiskaperbapoData()` (sebelumnya `VERIFYPEER => false, VERIFYHOST => false`).
- **KecepatanAnginScraper.php** — Aktifkan SSL certificate verification pada `fetch_nasa_kecepatan_angin()` (sebelumnya `VERIFYPEER => false`).
- **footer.php** — Hapus duplikasi pemuatan Chart.js v3.9.1; hanya Chart.js v4.4.0 di header.php yang dipertahankan.
- **irigasi_scraper/index.php** — Konversi sintaks Chart.js v3 ke v4: `xAxes/yAxes` → `x/y`, `gridLines` → `grid`, `legend`/`tooltips` → `plugins.legend`/`plugins.tooltip`, `titleFontSize` → `titleFont.size`.

### Changed
- `.env.local` — Tambah komentar dokumentasi cara mendapatkan BPS API Key (https://webapi.bps.go.id).

### Notes
- **BpsApiClient.php**, **BpsDataService.php**, **BpsSimulationService.php**, dan standardisasi GKG→Beras (0.577) sudah diperbaiki pada rilis sebelumnya; tercatat di bagian [Unreleased] di bawah.

---

## [Unreleased]

### Added
- BPS Scraper — Live Test (V2) Fix Release
  - `scripts/standardize_bps_data.php` — Database standardization script (fixes GKG→Beras conversion ratio 0.5744 → 0.577 for 266 manual records)
  - `data/ksa/export_documentation.md` — Documentation for export process

### Fixed
- **BpsApiClient.php** — Initialized `$this->logFile` in constructor (was NULL, causing silent logging failures)
- **BpsDataService.php** — Fixed multi-year summary update: `updateYearlySummary()` now loops through all unique years instead of only `$records[0]['tahun']`
- **BpsSimulationService.php** — Fixed false-positive productivity anomalies: `produktivitas` now calculated from actual `produksi_gabah / luas_panen` ratio instead of independent random factor
- **Database** — Standardized 266 manual records: `produksi_beras = ROUND(produksi_gabah * 0.577, 2)` where deviation > 1 ton
- BPS Scraper — Comprehensive security and performance improvements
  - `.env.local` / `.env` — Added `BPS_API_KEY`, `BPS_API_BASE_URL`, `BPS_API_TIMEOUT` env variables
  - `config/config.php` — Define `BPS_API_KEY`, `BPS_API_BASE_URL`, `BPS_API_TIMEOUT` constants from env
  - `app/services/BpsApiClient.php` — Read API base URL from `BPS_API_BASE_URL` constant; configurable timeout via `BPS_API_TIMEOUT`
  - `app/controllers/BpsScraperController.php` — CSV formula injection sanitization in `export()` via `sanitizeCsvValue()` helper
  - `app/controllers/BpsScraperController.php` — Orphan temp file cleanup in `previewImport()` and `importExcel()` (session-based)
  - `app/controllers/AuthController.php` — Temp file cleanup on logout
  - `app/controllers/BpsScraperController.php` — Access control: `getRecord()` now requires admin (was auth-only)
  - `app/controllers/BpsScraperController.php` — Server-side caching via `CacheManager` for `getStatistics()` (5min TTL) and `getChartData()` (10min TTL)
  - `app/controllers/BpsScraperController.php` — Cache invalidation (`clearCache()`) on all write operations (runScraper, store, update, delete, deleteByYear, importExcel, importKsa, syncKsaToAnnual)
  - `app/controllers/BpsScraperController.php` — `_getDefaultYear()` helper: uses most recent year with data instead of `date('Y')`
  - `app/controllers/BpsScraperController.php` — `sanitizeNullStats()` helper: converts NULL statistics to 0 for consistent frontend handling
  - `app/controllers/BpsScraperController.php` — Year fallback in `getData()`: if requested year has no data, falls back to most recent available year
  - `app/controllers/BpsScraperController.php` — Detailed per-record error tracking in `runScraper()` response (`errors` array)
  - `app/controllers/BpsScraperController.php` — Database activity logging for scraper execution
  - `app/controllers/BpsScraperController.php` — Fixed `auto` source handling in `BpsScraper::run()` (previously fell through to simulation)
  - `app/views/bps_scraper/index.php` — Dropdown year: years without data marked `disabled` with "(belum ada data)" label
  - `app/views/bps_scraper/index.php` — Empty state handling for statistics and charts
  - `app/views/bps_scraper/index.php` — Enhanced scraping progress UI with progress bar, ETA, cancel button, source-aware messages
  - `app/views/bps_scraper/index.php` — `showToast()` now supports 'warning' type with longer display
  - `app/models/DataPertanianBps.php` — DRY refactor: extracted `buildFilterClause()` shared by `getAll()` and `countAll()`
  - `app/models/DataPertanianBps.php` — Static `$tablesChecked` flag to prevent redundant table existence checks per request
  - `app/services/BpsDataService.php` — Required field validation before processing (`tahun`, `kabupaten_kota`, `luas_panen`)
  - `app/services/BpsDataService.php` — Detailed progress logging (start, complete with counts)
  - `app/services/BpsApiClient.php` — File-based logging to `logs/bps_api_client.log`
  - `scripts/export_bps_data.php` — BPS data export script with validation
  - `data/ksa/export_documentation.md` — Documentation for export process
  - `database/migrations/2026-08-08_create_bps_scraping_queue.sql` — Queue & logs table migration (bps_scraping_queue: id, tahun, kabupaten, source, skenario, status, progress, result JSON, error, created_at, started_at, completed_at)
  - `scripts/bps_scraper_worker.php` — CLI background worker (poll mode + --once mode for cron); claims/purges jobs, invalidates cache, logs activity
  - `app/controllers/BpsScraperController.php` — `runScraper()` now supports `background=true` to queue jobs instead of synchronous execution
  - `app/controllers/BpsScraperController.php` — New `runScraperBackground()` endpoint for explicit background queueing
  - `app/controllers/BpsScraperController.php` — New `getScraperStatus($jobId)` endpoint for polling background job progress
  - `app/controllers/ApiBpsController.php` — New REST API controller with endpoints: `/api/v1/bps/{data,statistics,trend,provinsi,kabupaten-list,status}` + `POST /api/v1/bps/scrape`
  - `app/controllers/ApiBpsController.php` — Auth via existing `external_auth` middleware (X-API-Key), rate-limited at 100 req/min
  - `app/services/BpsApiClient.php` — `fetchAgriculturalData()` and `fetchVariable()` now accept `$provCode` parameter (defaults to '35'); added `getProvinsiList()` and `getKabupatenForProvinsi()` static methods
  - `config/api_config.php` — Added `bps_api` section with `api_key`, `api_key_hash`, `allowed_ips`, rate limit (100/min), brute force protection
  - `scripts/bps_auto_scrape.php` — CLI auto-scrape script with anomaly detection, cron-ready
  - Cron job recommendation: `0 2 1 * * php scripts/bps_auto_scrape.php --tahun=$(date +%Y) --source=auto`
  - `app/core/Router.php` — Added 7 new `/api/v1/bps/*` routes (data, statistics, trend, provinsi, kabupaten-list, scrape, status)
  - `app/views/bps_scraper/index.php` — Background scraping with polling (auto source → background mode, 5s polling via `getScraperStatus()`)
  - `index.php` — Added `runScraperBackground` to state-changing methods (CSRF protection)
  - `docs/DEPLOY.md` — Full deployment guide (Ubuntu + Nginx + PHP-FPM + MySQL)
  - `docs/SMOKE_TEST.md` — Post-deploy smoke test procedure (curl + browser)
  - `docs/GO_LIVE_CHECKLIST.md` — Pre-flight checklist with sign-off
  - `scripts/backup-db.sh.example` — Daily DB backup script example
  - `scripts/backup-uploads.sh.example` — Weekly uploads backup script example
  - `scripts/prune-notifications.php` — CLI script to prune old notifications (cron-ready)
  - `backend/public/index.php` — CORS now reads `CORS_ALLOWED_ORIGINS` from env
  - `backend/.env.example` — Expanded with production comments, CORS_ALLOWED_ORIGINS
  - `.gitignore` — Added `google-services.json`, `*.service_account.json`, `*.jks`, `key.properties`, `backups/`
  - `CHANGELOG.md` — v1.0.0 preparation
  - `README.md` — Status updated, links to DEPLOY.md, SMOKE_TEST.md, GO_LIVE_CHECKLIST.md
  - `AGENTS.md` — Tahap 15 marked as Done
  - Mobile release build notes added to DEPLOY.md
  - Session cookie security already implemented (cookie_secure, httponly, samesite)
  - ErrorHandler already safe (no stack trace when APP_DEBUG=false)


- Notifikasi In-App (Tahap 12)
  - `database/migrations/009_create_notifications_table.sql` — Tabel notifications (id, user_id, type, title, body, data_json, read_at, created_at)
  - `app/Models/Notification.php` — Full CRUD model (list, unread count, mark read, mark all, delete, prune, find)
  - `app/Services/NotificationService.php` — notifyUser, notifyAdmins (query admin ids), listForUser, unreadCount, markRead, markAllRead, deleteForUser, getRecentForUser, pruneOlderThan, truncateBody
  - `app/Services/Push/PushNotifierInterface.php` — Interface push notification
  - `app/Services/Push/NullPushNotifier.php` — Default no-op push
  - `app/Services/Push/FcmPushNotifier.php` — FCM stub (no-op until device tokens implemented)
  - `app/Controllers/Web/NotificationController.php` — index, unreadCountJson, recentJson, markRead, markAllRead, delete
  - `app/Controllers/Api/NotificationController.php` — index, unreadCount, markRead, markAllRead, delete
  - `app/Views/notifications/index.php` — Halaman list notifikasi dengan filter all/unread, pagination, mark read, delete, inline JS mark+redirect
  - `app/Views/layouts/main.php` — Bell icon + badge unread, polling JS setiap 60 detik
  - `config/routes.php` — 5 web routes + 5 API routes untuk notifikasi
  - `.env.example` — FCM_ENABLED, FCM_SERVER_KEY
  - Notification hooks in `LaporanHamaService` & `LaporanIrigasiService` (submit, resubmit → admins; verify, reject, archive → owner)
  - Hooks wrapped in try-catch + Logger::warning() — gagal push tidak ganggu alur utama
  - `tests/Unit/NotificationServiceTest.php` — 12 test cases (truncateBody, list structure, unread count, mark read, mark all, delete, prune, notifyUser no-throw)
  - Updated docs: API.md (notifikasi section + event matrix), DATABASE.md (notifications table), TUTORIAL_BUILD.md (Tahap 12 Done), CHANGELOG.md
- Export XLSX/CSV (Tahap 11)
  - `app/Services/ExportService.php` — Validasi filter, COUNT query, JOIN fetch, stream CSV/XLSX; scope petugas vs admin
  - `app/Helpers/CsvWriter.php` — Simple CSV writer with UTF-8 BOM
  - `app/Helpers/XlsxWriter.php` — Pure PHP XLSX generator using PclZip (XML sheets + ZIP)
  - `app/Controllers/Web/ExportController.php` — Form UI + POST download hama/irigasi
  - `app/Controllers/Api/ExportController.php` — GET `api/v1/export/hama` & `api/v1/export/irigasi` with JWT
  - `app/Views/export/index.php` — Form filter (jenis, format, status, kabupaten/kecamatan/desa cascading, tanggal)
  - `config/routes.php` — 5 new routes (web GET + POST ×2, API ×2)
  - `app/Views/layouts/main.php` — "Ekspor" nav link added
  - `composer.json` — Added `pclzip/pclzip` (pure PHP Zip, lightweight)
  - `tests/Unit/ExportServiceTest.php` — 13 test cases (format, tanggal, status, wilayah, headings)
  - Updated docs: API.md, TUTORIAL_BUILD.md, backend/README.md, README.md
  - Export constraints: max 10.000 rows, max 366 days date range, temp file cleaned up
  - Activity log: export_hama / export_irigasi logged with format, filename, row count
  - Columns: 22 for hama (nomor, tanggal, status, petugas, OPT, keparahan, wilayah, koordinat, verifikasi, dll), 19 for irigasi
- Dashboard, Statistik & Cache (Tahap 10)
  - `app/Core/CacheManager.php` — File-based cache (TTL 300s, atomic write, prefix delete, fallback no-cache)
  - `app/Services/DashboardService.php` — Agregasi stats/charts/map dengan cache orchestration; scope admin vs petugas
  - `app/Controllers/Web/DashboardController.php` — index + 5 JSON endpoints (stats, charts hama/irigasi, map hama/irigasi)
  - `app/Controllers/Api/DashboardController.php` — 5 API endpoints (stats, charts hama/irigasi, map hama/irigasi)
  - `app/Views/dashboard/index.php` — KPI cards, Chart.js bar charts x2, Leaflet map, top OPT table, status breakdown
  - `public/assets/js/dashboard.js` — Chart.js init + Leaflet with toggle hama/irigasi layer
  - `config/routes.php` — 5 web JSON routes + 5 API routes for dashboard
  - Cache invalidation on write: semua service mutation panggil `invalidateCache()`
  - `tests/Unit/DashboardServiceTest.php` — 8 test cases
  - Updated `docs/API.md` — dashboard endpoints documented with examples
  - Updated `docs/TUTORIAL_BUILD.md` — Tahap 10 marked Done, renumbered subsequent tahaps
  - Updated `backend/README.md` — new routes added
- Upload Foto Aman (Tahap 9)
  - `app/Helpers/SecureImageUploader.php` — Validasi keamanan berlapis: magic bytes (JPEG/PNG/WebP), finfo MIME type, ekstensi file, ukuran (max 10MB), random name (bin2hex 16 bytes), sub-direktori YYYYMM, auto-kompresi >2MB via ImageCompressor, path traversal protection pada delete
  - `app/Helpers/ImageCompressor.php` — Kompresi gambar via GD library (JPEG quality 75, PNG compression 7, WebP quality 75)
  - `app/Controllers/Api/OptController.php` — added uploadFoto(), deleteFoto() endpoints
  - `app/Controllers/Api/LaporanHamaController.php` — added uploadFoto(), deleteFoto() endpoints
  - `app/Controllers/Api/LaporanIrigasiController.php` — added uploadFoto(), deleteFoto() endpoints
  - `app/Controllers/Web/OptController.php` — added uploadFoto(), deleteFoto() actions
  - `app/Controllers/Web/LaporanHamaController.php` — added uploadFoto(), deleteFoto() actions
  - `app/Controllers/Web/LaporanIrigasiController.php` — added uploadFoto(), deleteFoto() actions
  - `app/Views/opt/form.php` — foto upload form + existing foto display + delete button
  - `app/Views/laporan-hama/edit.php` — foto upload form + existing foto display + delete button
  - `app/Views/laporan-hama/show.php` — existing foto display (inline image)
  - `app/Views/laporan-irigasi/edit.php` — foto upload form + existing foto display + delete button
  - `app/Views/laporan-irigasi/show.php` — existing foto display (inline image)
  - `config/routes.php` — 12 new routes (6 web + 6 API) for foto upload/delete
  - `tests/Unit/SecureImageUploaderTest.php` — 12 test cases
  - `tests/Unit/ImageCompressorTest.php` — 4 test cases
  - Updated `docs/API.md` — foto upload endpoints documented with examples
  - Updated `docs/TUTORIAL_BUILD.md` — Tahap 9 marked Done
  - Updated `backend/README.md` — new web/API routes added
- Verifikasi Admin Laporan Hama & Irigasi (Tahap 8)
  - `app/Helpers/LaporanStatus.php` — status constants + transition matrix (canTransition, assertCanTransition, isEditableByPetugas, dll)
  - `app/Models/LaporanHama.php` — added updateStatusAndVerification(), resetVerification(), verifikator_nama JOIN
  - `app/Models/LaporanIrigasi.php` — added updateStatusAndVerification(), resetVerification(), verifikator_nama JOIN
  - `app/Services/LaporanHamaService.php` — added verify(), reject(), archive(), resubmit(); updateDraft now allows Ditolak
  - `app/Services/LaporanIrigasiService.php` — added verify(), reject(), archive(), resubmit(); updateDraft now allows Ditolak
  - `app/Controllers/Api/LaporanHamaController.php` — added verify/reject/archive/resubmit endpoints
  - `app/Controllers/Api/LaporanIrigasiController.php` — added verify/reject/archive/resubmit endpoints
  - `app/Controllers/Web/LaporanHamaController.php` — added verify/reject/archive/resubmit actions; edit now allows Ditolak
  - `app/Controllers/Web/LaporanIrigasiController.php` — added verify/reject/archive/resubmit actions; edit now allows Ditolak
  - `app/Views/laporan-hama/show.php` — verification info panel + action buttons by role & status
  - `app/Views/laporan-irigasi/show.php` — verification info panel + action buttons by role & status
  - `app/Views/laporan-hama/index.php` — expanded status filter (Diverifikasi, Ditolak, Diarsipkan)
  - `app/Views/laporan-irigasi/index.php` — expanded status filter (Diverifikasi, Ditolak, Diarsipkan)
  - `config/routes.php` — 16 new routes (8 web + 8 API) for verifikasi workflow
  - `database/migrations/008_create_verifikasi_indexes.sql` — indexes on verified_by, verified_at
  - `tests/Unit/LaporanStatusTest.php` — 22 test cases for transition matrix + helper methods
  - Updated `docs/API.md` — verifikasi endpoints documented with examples
  - Updated `docs/TUTORIAL_BUILD.md` — Tahap 8 marked Done
  - Updated `backend/README.md` — new web routes table entries
- Laporan Irigasi (Tahap 7)
  - `app/Helpers/NomorLaporanGenerator.php` — generalized to support LH and LI prefixes
  - `app/Helpers/LaporanIrigasiValidator.php` — validasi Draf (parsial) dan Submit (lengkap) irigasi
  - `app/Models/LaporanIrigasi.php` — model with findAccessibleById, listForPetugas, listForAdmin, deleteDraft
  - `app/Services/LaporanIrigasiService.php` — CRUD draft, submit, generate nomor LI, activity log
  - `app/Controllers/Api/LaporanIrigasiController.php` — 6 API endpoints (index, store, show, update, destroy, submit)
  - `app/Controllers/Web/LaporanIrigasiController.php` — 8 web endpoints (index, create, store, show, edit, update, submit, delete)
  - `app/Views/laporan-irigasi/` — index (filter), create, edit, show views
  - `config/routes.php` — web + API routes for irigasi
  - `tests/Unit/LaporanIrigasiValidatorTest.php` — 11 test cases
  - `tests/Unit/NomorLaporanGeneratorTest.php` — updated with LI prefix + invalid prefix tests
  - Updated `docs/API.md` — laporan irigasi endpoints documented
  - Updated `docs/TUTORIAL_BUILD.md` — Tahap 7 marked Done
- Laporan Hama (Tahap 6)
  - `app/Helpers/NomorLaporanGenerator.php` — atomic nomor LH: prefix `LH`, date, counter via `nomor_laporan_counter`
  - `app/Helpers/LaporanHamaValidator.php` — validasi Draf (parsial) dan Submit (lengkap)
  - `app/Models/LaporanHama.php` — model with findAccessibleById, listForPetugas, listForAdmin, deleteDraft
  - `app/Services/LaporanHamaService.php` — CRUD draft, submit, generate nomor, activity log
  - `app/Controllers/Api/LaporanHamaController.php` — 6 API endpoints (index, store, show, update, destroy, submit)
  - `app/Controllers/Web/LaporanHamaController.php` — 8 web endpoints (index, create, store, show, edit, update, submit, delete)
  - `app/Views/laporan-hama/index.php` — list dengan filter status/tanggal/wilayah/OPT/q + pagination
  - `app/Views/laporan-hama/create.php` — form dengan cascading dropdown kab/kec/desa
  - `app/Views/laporan-hama/edit.php` — edit form + submit button
  - `app/Views/laporan-hama/show.php` — detail dengan action edit/submit/delete (Draf only)
  - `config/routes.php` — web + API routes with WebAuthMiddleware/ApiAuthMiddleware
  - `Updated docs/API.md` — laporan hama endpoints documented
  - `Updated docs/TUTORIAL_BUILD.md` — Tahap 6 marked Done
- Master Data Wilayah & OPT (Tahap 5)
  - `app/Models/MasterKabupaten.php` — model kabupaten
  - `app/Models/MasterKecamatan.php` — model kecamatan (findByKabupaten)
  - `app/Models/MasterDesa.php` — model desa (findByKecamatan)
  - `app/Models/AuditLogWilayah.php` — audit log wilayah (log INSERT/UPDATE/DELETE)
  - `app/Models/MasterOpt.php` — model OPT (allActive, allWithFilters)
  - `app/Services/WilayahService.php` — CRUD wilayah + audit log + FK guard
  - `app/Services/MasterOptService.php` — CRUD OPT + soft deactivate + validasi
  - `app/Controllers/Api/WilayahController.php` — API read: auth user, write: admin
  - `app/Controllers/Api/OptController.php` — API read/write dengan role guard
  - `app/Controllers/Web/WilayahController.php` — web admin CRUD wilayah
  - `app/Controllers/Web/OptController.php` — web admin CRUD OPT
  - `app/Views/wilayah/` — index (cascading tabs), kabupaten_form, kecamatan_form, desa_form
  - `app/Views/opt/` — index (filter), form (create/edit)
  - `config/routes.php` — all web + API routes for wilayah & OPT
  - Updated `docs/API.md` — master data endpoints documented
  - Updated `docs/TUTORIAL_BUILD.md` — Tahap 5 marked Done, table realigned

### Added
- Authentication Web & Mobile (Tahap 4)
  - `app/Core/Request.php` — input parsing (JSON/form), bearer token, IP, user agent, isApi, isSecure
  - `app/Core/Security.php` — session management aman (httponly, samesite Lax, regenerate), CSRF token (auto-regenerasi tiap 1 jam)
  - `app/Core/Jwt.php` — JWT HS256 encode/decode/refresh, base64url, exp check
  - `app/Core/Model.php` — base PDO model (find, findBy, all, count, insert, update, delete)
  - `app/Models/User.php` — findByUsername, verifyPassword, hashPassword (bcrypt cost 12), toPublicArray (tanpa hash)
  - `app/Models/ActivityLog.php` — log auth events (login_success, login_failed, logout, password_changed)
  - `app/Helpers/RateLimiter.php` — file-based brute-force protection per IP (5 gagal / 15 menit)
  - `app/Helpers/PasswordValidator.php` — password policy (min 8, upper, lower, digit, special)
  - `app/Middleware/CsrfMiddleware.php` — CSRF untuk mutasi web, skip `/api/*`
  - `app/Middleware/WebAuthMiddleware.php` — session check + must_change_password redirect
  - `app/Middleware/ApiAuthMiddleware.php` — JWT Bearer validation, set $GLOBALS['auth_user']
  - `app/Middleware/AdminMiddleware.php` — role admin check (403 JSON/redirect)
  - `app/Controllers/Web/AuthController.php` — web login/logout dengan rate limiter + activity log
  - `app/Controllers/Web/PasswordController.php` — web change password
  - `app/Controllers/Web/DashboardController.php` — web dashboard landing
  - `app/Controllers/Api/AuthController.php` — API login/refresh/logout/change-password
  - `app/Controllers/Api/MeController.php` — API current user profile
  - `app/Views/layouts/auth.php` — layout halaman auth (login)
  - `app/Views/layouts/main.php` — layout dashboard utama
  - `app/Views/auth/login.php` — form login
  - `app/Views/auth/change_password.php` — form change password
  - `app/Views/dashboard/index.php` — dashboard landing page
  - `config/routes.php` — semua route web + API dengan middleware chain
  - `public/index.php` — session initialization (Security::initSession)
  - `.env.example` — added SESSION_NAME, LOGIN_MAX_ATTEMPTS, LOGIN_DECAY_SECONDS
  - Updated `docs/API.md` — auth endpoints documented, JWT structure updated
  - Updated `docs/TUTORIAL_BUILD.md` — Tahap 4 marked Done
- Database schema & migration (Tahap 3)
  - 7 migration files (001-007): schema_migrations, wilayah, users, master_opt, laporan_hama, laporan_irigasi, activity_log & counter
  - `backend/scripts/migrate.php` — PHP migration runner (batch tracking, idempotent)
  - `backend/scripts/seed.php` — PHP seed runner (bcrypt password_hash, idempotent)
  - `backend/database/schema.sql` — complete schema reference
  - Seed files: wilayah Jember (5 kecamatan, 10 desa), 8 master OPT, 2 users
  - Updated `docs/DATABASE.md` — full schema documentation with table specs, FK relations, status workflow
  - Updated `backend/.env.example` — added `DB_DRIVER=mysql`

### Added
- Backend skeleton (Tahap 2)
  - `backend/composer.json` — PSR-4 autoload, PHP 8.2+, PHPUnit 11
  - `backend/.env.example` — environment template (safe placeholders)
  - `backend/app/Core/Env.php` — `.env` file loader (KEY=VALUE parser, no override)
  - `backend/app/Core/Database.php` — PDO connection factory (lazy, MariaDB/MySQL)
  - `backend/app/Core/Router.php` — HTTP router (GET, extensible for POST/PUT/DELETE)
  - `backend/app/Core/Controller.php` — base controller
  - `backend/app/Core/BaseApiController.php` — JSON envelope (success/error format)
  - `backend/app/Core/Logger.php` — file logger (storage/logs/app.log, sensitive data redaction)
  - `backend/app/Core/ErrorHandler.php` — error/exception/shutdown handler (safe in production)
  - `backend/app/Controllers/Api/HealthController.php` — GET /api/v1/health
  - `backend/config/routes.php` — route definitions
  - `backend/public/index.php` — single entry point (security headers, bootstrap)
  - `backend/public/.htaccess` — Apache mod_rewrite
  - `backend/phpunit.xml` — PHPUnit config
  - `backend/tests/EnvTest.php` — env loader unit tests (no DB required)
  - `backend/README.md` — local setup guide

### Added
- Repository foundation and development standards (Tahap 1)
  - Monorepo structure (`backend/`, `mobile/`, `docs/`, `scripts/`, `.github/`)
  - Comprehensive `.gitignore` (secrets, build artifacts, IDE, OS files)
  - `AGENTS.md` — permanent instructions for AI coding agents
  - `README.md` — project overview, monorepo structure, tech stack
  - `CHANGELOG.md` — Keep a Changelog format with [Unreleased] section
  - `docs/BLUEPRINT.md` — architecture summary, v1 modules, report statuses, draft policy
  - `docs/TUTORIAL_BUILD.md` — 15 build phases (0–14), Phase 1 marked current
  - `docs/API.md` — API placeholder (`/api/v1`, JSON, JWT, `include_draft`)
  - `docs/DATABASE.md` — DB placeholder (MariaDB/MySQL utf8mb4)
  - `docs/ADR/README.md` — Architecture Decision Records index
  - `.editorconfig` — UTF-8, LF, 4-space PHP, 2-space YAML/JSON/MD
  - `.github/PULL_REQUEST_TEMPLATE.md` — PR checklist (scope, secrets, tests, docs, migration, draft policy)
  - `.github/ISSUE_TEMPLATE/bug_report.md` — bug report template
  - `.github/ISSUE_TEMPLATE/feature_request.md` — feature request template
  - `.github/CODEOWNERS` — code ownership rules
  - `.github/dependabot.yml` — Dependabot configuration placeholder
  - `scripts/health-check.sh` — repository health check script (placeholder)
  - Placeholder `.gitkeep` files in `backend/`, `mobile/`, `scripts/`

---

> **Note**: Version `v1.0.0` has **not** been released. This project is in **Phase 3 — Database Schema & Migration**.
