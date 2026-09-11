# ADR-011: Canonical Runtime Backend v1 & Strangler Migration

## Status
Accepted — 2026-08-30

## Context
Repository JAGAPADI memiliki dua runtime aktif (AGENTS.md §1, REFERENSI_TEKNIS_BACKEND_AI.md §2):

| Runtime | Front controller | Route | Auth | Docroot prod |
|---------|------------------|-------|------|--------------|
| Root/integrated | `index.php` | `config/web_routes.php` + `app/core/Router.php` | Session+CSRF, API key/token | `ROOT_PATH` |
| Backend v1 | `backend/public/index.php` | `backend/config/routes.php` | Session+CSRF (web), JWT Bearer `/api/v1` (mobile) | `backend/public` |

Discovery 2026-08-30 (git status, route audit 234 routes, middleware 7, controller 42, model 18, migration 28 + 12 backend) menunjukkan:
- Duplikasi logika (Laporan Hama/Irigasi, wilayah, OPT, upload, dashboard) dengan kontrak drift.
- Migration tersebar 3 lokasi (`migrations/`, `database/migrations/`, `backend/database/migrations/`) → risiko schema drift vs `schema_migrations`.
- Ownership/role tersebar tanpa policy matrix terpusat → IDOR risk Petugas A↔B.
- SEO & ekspetasi prod: docroot harus `backend/public` (DEPLOY.md, BLUEPRINT.md §2) agar hanya `backend/public` terekspos.

Big-bang rewrite akan mematikan fitur produksi (scraper, storytelling, usulan-opt, recycle-bin). Diperlukan keputusan canonical yang preservasi kompatibilitas.

## Decision
1. **Canonical runtime = Backend v1** (`backend/public/index.php`, `backend/config/routes.php`, `/api/v1`). Semua fitur baru dan kontrak API/mobile wajib di Backend v1. `APP_BASE_URL` tervalidasi menjadi sumber kebenaran untuk redirect & CORS.
2. **Root/integrated = compatibility layer (frozen)**. Tidak ada penambahan fitur baru di root sejak 2026-08-30. Hanya bugfix keamanan kritis dengan ADR & test. Route fallback `app/core/Router.php` tetap melayani URL lama agar Flutter/web lama tidak pecah.
3. **Strangler migration bertahap** (bukan big-bang):
   - Tahap 1 (P0): Stabilisasi — PDO placeholder konsisten, session lifecycle, JWT blacklist, upload allowlist, rate-limit & cache abstraction.
   - Tahap 2: Migrasi fitur by domain — `laporan-pupuk/panen/cuaca/alat-sarana` sudah di Backend v1; selanjutnya `laporan-lainnya`, `usulan-opt`, `feedback`, `storytelling` (portal read-only dulu), lalu `scraper` (queue).
   - Tahap 3: Data — canonical migration runner = `backend/scripts/migrate.php` + tabel `backend.schema_migrations`. Legacy `migrations/` & `database/migrations/` didokumentasikan sebagai `legacy` di `docs/DATABASE.md` & `docs/ADR/ADR-011` appdx, baseline diverifikasi tanpa `ALTER` migration yang sudah tercatat.
   - Tahap 4: Decommission — root route dialihkan 301 ke Backend v1 equivalent setelah 2 rilis stabil + contract test hijau.
4. **Batas kompatibilitas root** (frozen scope):
   - Tetap melayani: `/laporan*`, `/irigasi`, `/curahHujan`, `/kecepatanAngin`, `/hargaKomoditas`, `/bpsScraper`, `/storytelling`, `/feedback`, `/usulan-opt`, `/optsaya`, `/export/*`, `/bps/*`, `/adminWilayah/*`, `/recycle-bin`.
   - Wajib lulus `tests/Compatibility/RootCompatibilityTest.php` (route existence, status code, role guard) — fail-closed bila behaviour drift.
5. **Inventaris belum termigrasi (vs Backend v1 342 routes)**:
   | Fitur root belum di Backend v1 | Status | Rencana |
   |-------------------------------|------|---------|
   | `KecepatanAnginScraper` NASA POWER parallel + fallback simulasi | Parkir — scraper queue Fase 3 | Ekstrak ke `backend/app/Services/Scraper/*` + job |
   | `CurahHujan`, `HargaKomoditas`, `BpsScraper` (`/bpsScraper/*`) | Parkir | Sama |
   | `Storytelling` `/api/storytelling/*` (admin/statistisi) | Parkir | Port sebagai read-only API v1 `/api/v1/storytelling` |
   | `Usulan Opt` full workflow (`usulan-opt/*`, `optsaya/*`) | Parkir | Migrasi ke `backend/app/Models/UsulanOpt` + policy Petugas ownership |
   | `Feedback` vote/history (`feedback/*`, `/api/feedback`) | Parkir | Port ke `backend/api/v1/feedback` |
   | `Laporan Lainnya` (`laporan-lainnya/*`) + kategori lingkungan 2026-08-24 | Parkir | Migrasi ke `backend/app/Services/LaporanLainnyaService` |
   | `Recycle Bin` soft-delete `deleted_at` 2026-08-24 | Parkir | Port ke Backend v1 dengan policy Admin-only |
   | `IrigasiScraper` | Parkir | Queue |
   | Upload `feedback/attachment` & video `laporan-hama/video` | Sebagian di Backend v1 | Konsolidasi ke `UploadService` tunggal |
6. **Deprecation policy**:
   - Mark deprecated dengan header `Deprecation: true` + `Sunset: <date>` pada root route yang punya ekuivalen v1.
   - Announce di `CHANGELOG.md` & `docs/API.md` 1 minor sebelum sunset.
   - Hapus hanya bila: contract test hijau, OpenAPI sync, & `smoke_test` staging passes untuk semua role (Admin, Petugas A/B, operator, statistisi, viewer).
7. **Acceptance criteria (DoD)**:
   - Suite PHP (backend+root) 0 error/warning/risky, lint & PHPStan level 5 naik bertahap.
   - Contract test `tests/Contract/RouterOpenApiTest.php` hijau: no undocumented route, no OpenAPI orphan, no duplicate route/handler.
   - Ownership matrix hijau untuk laporan/foto/feedback/notifikasi/device-token/dashboard/grafik/peta/ekspor (Petugas A ⊄ B, Admin global hanya route Admin eksplisit).
   - `EXPLAIN` untuk list/dashboard/peta/ekspor tanpa N+1, pagination max 100, export chunked.
   - Flutter: `flutter analyze` 0 issue, release build fail bila keystore missing, sqlite3.dll portable.

## Consequences
- **Positif**: Satu sumber kebenaran untuk deploy (`backend/public`), cache/rate-limit terpusat, keamanan terukur, migrasi aman.
- **Negatif**: Maintaining dual runtime sementara → cost testing double. Mitigasi: compatibility test fail-closed + feature freeze.
- **Trade-off**: Beberapa endpoint root akan menampakkan latency cache file vs Redis shared; direncanakan abstraction `CacheManager` → `SharedCacheInterface` bertahap.
- **Risiko**: Schema drift bila tim menjalankan runner legacy. Mitigasi: dokumentasi legacy + CI `migration --dry-run` + check `schema_migrations` vs filesystem.

## Alternatives Considered
- Big-bang rewrite → ditolak: risiko downtime, loss of audit trail, butuh re-seed prod (dilarang AGENTS.md §5).
- Root sebagai canonical → ditolak: tidak memenuhi target deploy cPanel `backend/public` & kontrak `/api/v1` JWT.

## Implementation Checklist
- [x] ADR ini + bekukan fitur root (branch `codex/...` dengan commit Conventional)
- [x] Tambah `tests/Compatibility/RootCompatibilityTest.php` (P0 P0.1)
- [ ] Tambah `tests/Contract/RouterOpenApiTest.php` & CI contract gate (P1)
- [ ] Buat `docs/API.md` & `docs/openapi.yaml` sync job
- [ ] Inventaris route matrix `docs/ROUTE_MATRIX.json` (machine-readable)
- [x] Perbaikan P0: `DashboardService` placeholder, `order_dir`, `KecepatanAnginScraper` signature

## References
- `AGENTS.md` §1-3, `docs/BLUEPRINT.md` §2, `docs/REFERENSI_TEKNIS_BACKEND_AI.md` §2-3, `docs/DATABASE.md`, `backend/config/routes.php`, `config/web_routes.php`
- Discovery evidence: `git status 2026-08-30`, `backend/tests Unit 217 OK`, `root tests Integration KecepatanAnginFallback 10 OK`

## Appendix — Legacy Migration Map
- `migrations/` (historis root) → legacy, jangan jalankan di prod baru
- `database/migrations/2026_08_*.sql` → legacy root, catat di `schema_migrations` root DB
- `backend/database/migrations/*.sql` → canonical, runner `backend/scripts/migrate.php`; baseline terverifikasi idempoten guard `INFORMATION_SCHEMA`
