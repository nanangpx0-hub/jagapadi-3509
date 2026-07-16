# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
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
