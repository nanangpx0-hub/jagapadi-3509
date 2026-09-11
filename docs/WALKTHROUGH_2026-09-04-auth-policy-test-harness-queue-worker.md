# Walkthrough — Konsolidasi Otorisasi, Test Harness & Queue Worker (Backend v1)

- Tanggal (UTC): 2026-09-04
- Runtime target: Backend v1 (`backend/public/index.php`, `backend/config/routes.php`, `/api/v1`)
- Referensi: AGENTS.md, ADR-011 (Canonical Runtime Backend v1 & Strangler Migration),
  `docs/BLUEPRINT.md`, `docs/REFERENSI_TEKNIS_BACKEND_AI.md`, `docs/QUEUE_DURABLE.md`
- Kompatibilitas root: tidak diubah; guard `tests/Compatibility/RootCompatibilityTest.php` hijau.

## 1. Ringkasan

1. **P0 — Policy terpusat**: `backend/app/Policies/ReportAuthorizationPolicy.php`
   (strict types, PSR-12, murni tanpa I/O DB) menjadi single source of truth
   role + filter status. 6 Service (`LaporanHama/Irigasi/Pupuk/Panen/Cuaca/
   AlatSaranaService`) dan 6 Model (`LaporanHama/Irigasi/Pupuk/Panen/Cuaca/
   AlatSarana`) kini mendelegasikan scope/detail ke policy.
   Aturan terkunci: Petugas owner-scoped IDOR-safe; Statistisi/Operator/Viewer
   global hanya `Submitted,Diverifikasi` dan `include_draft=true` diabaikan;
   Admin global (enforcement rute tetap di `AdminMiddleware` eksplisit).
2. **P1 — Test harness resilient**: suite `Unit` vs `Integration` dipisah di
   `backend/phpunit.xml` dan `phpunit.xml`; konstruktor rakus-DB dibuat lazy
   (`DashboardService`, `ExportService`, `NotificationService` backend;
   `UsulanOptService`, `OtherReportImportService` root) sehingga `Unit` hijau
   tanpa socket MySQL; test DB-gated skip cepat (`PDO::ATTR_TIMEOUT=5` backend).
3. **P1 — Worker antrean**: `backend/app/Services/ScraperJobService.php` +
   CLI `backend/scripts/queue-worker.php` memproses job `nasa_wind`/`bps_sync`
   via `FileQueue` dengan timeout, retry max 5 + DLQ, error logging tanpa
   secret/PII, dan fallback simulasi deterministik + `last_success` JSON.

Tidak ada perubahan migration (`schema_migrations` tidak disentuh) dan tidak
ada perubahan route/parameter/payload — `docs/API.md`/`DATABASE.md`/OpenAPI
tidak perlu sinkronisasi kontrak.

## 2. Berkas berubah (milik task ini)

Baru:

- `backend/app/Policies/ReportAuthorizationPolicy.php`
- `backend/app/Services/ScraperJobService.php`
- `backend/scripts/queue-worker.php`
- `backend/tests/Unit/ReportAuthorizationPolicyTest.php`
- `backend/tests/Unit/FileQueueTest.php`
- `backend/tests/Unit/ScraperJobServiceTest.php`
- `backend/tests/Integration/DatabaseConnectivityTest.php` (+ dir `backend/tests/Integration/`)
- `docs/WALKTHROUGH_2026-09-04-auth-policy-test-harness-queue-worker.md` (berkas ini)

Ubah:

- `backend/app/Models/Laporan{Hama,Irigasi,Pupuk,Panen,Cuaca,AlatSarana}.php`
  — `findAccessibleById()` delegasi ke `ReportAuthorizationPolicy::accessibleCondition()`
- `backend/app/Services/Laporan{Hama,Irigasi,Pupuk,Panen,Cuaca,AlatSarana}Service.php`
  — `listForCurrentUser()` via `resolveListScope()`, `getDetailForCurrentUser()`
  + `canViewRow()`, `updateDraft()` via `ReportAuthorizationPolicy::editDenial()`
- `backend/app/Services/{DashboardService,ExportService,NotificationService}.php`
  — koneksi DB lazy (konstruktor tanpa socket)
- `backend/app/Core/Database.php` — `PDO::ATTR_TIMEOUT=5` (fail-fast)
- `backend/phpunit.xml` — suite `Unit` (`tests/EnvTest.php` + `tests/Unit/`)
  dan `Integration` (`tests/Integration/`)
- `backend/tests/Unit/{StatistisiReportAccessTest,OwnershipIdorMatrixTest,LaporanHamaValidatorTest}.php`
  — kontrak delegasi policy; skip aman saat DB offline
- `app/services/{UsulanOptService,OtherReportImportService}.php` — DB/model lazy
- `phpunit.xml` — `FeedbackAttachmentUploadTest` pindah Unit → Integration
- `tests/Unit/FeedbackAttachmentUploadTest.php` → `tests/Integration/FeedbackAttachmentUploadTest.php` (git mv)
- `docs/QUEUE_DURABLE.md` — seksi runner `queue-worker.php`

Catatan: banyak file lain berstatus `M` di `git status` adalah perubahan
pengguna yang sudah ada sebelum task (AGENTS.md §8) dan tidak disentuh
di luar scope di atas.

## 3. Matriks hasil uji

| Perintah | Hasil |
|---|---|
| `php backend/vendor/bin/phpunit --configuration backend/phpunit.xml --testsuite Unit` (MySQL offline) | OK — 249 tests, 453 assertions, 0 errors/failures (70 skipped DB-gated; diukur sebelum 5 test `ScraperJobServiceTest` ditambahkan — ketiganya murni in-memory dan hijau terpisah 15/15) |
| `php backend/vendor/bin/phpunit --configuration backend/phpunit.xml --testsuite Unit` (MySQL online) | OK — 254 tests, 757 assertions, 0 errors/failures |
| `php vendor/bin/phpunit --testsuite Unit` (offline & online) | OK — 130 tests, 555 assertions, 0 errors/failures |
| `php vendor/bin/phpunit --testsuite Compatibility` | OK — 6 tests, 276 assertions |
| `php vendor/bin/phpunit --testsuite Contract` | OK — 4 tests, 615 assertions |
| `php backend/vendor/bin/phpunit --configuration backend/phpunit.xml --testsuite Integration` | OK — 1 test, 2 assertions (skip bila DB offline) |
| `php backend/scripts/lint.php` | 131 file backend sukses |
| `php -l` semua berkas baru/ubah backend + root service | tanpa syntax error |
| Worker E2E manual (`--enqueue-nasa-wind/--enqueue-bps` + drain) | 2 job sukses via fallback, `scraper_*_last_success.json` tertulis (`stale=true`) |

Catatan: PHPUnit melaporkan `Deprecations: 3` (backend) / `1` (root) —
metadata doc-comment warisan, bukan dari task ini.

## 4. Quality gates (DoD)

- [x] `declare(strict_types=1)`, prepared statement, allowlist order/filter tetap.
- [x] Tanpa `ALTER`/edit migration tercatat; diverifikasi via `migrate.php`
      (`[SKIP] already executed`, DB online).
- [x] Rute kompatibilitas root utuh (Compatibility hijau, 115 route).
- [x] `git status` terisolasi; pesan commit konvensional disarankan:
  - `refactor(backend): centralize report authorization policy`
  - `test(backend): split unit/integration suites, resilient offline harness`
  - `feat(backend): add filequeue scraper worker`
  - `docs(queue): document queue-worker runner`
  - `test(root): move feedback upload test to integration suite`
- [x] Petugas A/B + Admin tercakup via `OwnershipIdorMatrixTest`,
      `LaporanEditAuthorizationTest`, `StatistisiReportAccessTest`.

## 5. Risiko & pekerjaan lanjutan

- `DashboardService`/`ExportService`/`NotificationService` kini lazy-DB:
  error koneksi muncul saat query pertama, bukan konstruktor — panggil
  tertua yang mengharapkan throw di konstruktor perlu disesuaikan (tidak
  ditemukan di codebase saat ini).
- Suite `Integration` backend baru berisi 1 test konektivitas; migrasi
  bertahap test DB-berat dari `tests/Unit` ke `tests/Integration`
  disarankan sebagai follow-up.
- Worker memakai `FileQueue` (flock, single-node); Redis atomic
  (`SharedCacheInterface`) tetap roadmap ADR-011 untuk multi-instance.
- Endpoint BPS eksternal Backend v1 belum dikontrakkan — `handleBpsSync()`
  sengaja fallback simulasi + `stale=true` hingga kontrak ada.
