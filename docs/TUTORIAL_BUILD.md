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
| 8 | **Tahap 8** | Verifikasi Admin (Hama & Irigasi) | ⏳ Pending | Submitted → Verified/Rejected/Archived, resubmit from Ditolak
| 9 | **Tahap 9** | Upload Foto Aman | ⏳ Pending | OPT + Laporan Hama + Laporan Irigasi, magic bytes, random name
| 10 | **Tahap 10** | Mobile App Flutter | ⏳ Pending | Auth, offline draft, sync, JWT |
| 11 | **Tahap 11** | Notifikasi & Real-time | ⏳ Pending | Push notif, websocket/polling |
| 12 | **Tahap 12** | Testing & QA | ⏳ Pending | Unit, feature, E2E, security audit |
| 13 | **Tahap 13** | Deployment & CI/CD | ⏳ Pending | cPanel deploy, GitHub Actions |
| 14 | **Tahap 14** | Dokumentasi Akhir & Handover | ⏳ Pending | API docs final, runbook, knowledge transfer |

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

### Tahap 5 — Modul Laporan
- CRUD Draf (create, read own, update, delete own)
- Submit Draf → Submitted (generate `report_number`: `JPD-YYYY-NNNNNN`)
- Validasi field minimum analisis sebelum submit
- Attachments: foto/bukti (validasi magic bytes, MIME, size, random name)

### Tahap 6 — Verifikasi Admin
- List `Submitted` dengan filter
- Action: Verify → `Diverifikasi`, Reject → `Ditolak` (with reason), Archive → `Diarsipkan`
- Audit trail: `verifications` table (verifier_id, report_id, action, note, timestamp)

### Tahap 7 — Dashboard & Statistik
- Cards: total laporan, per status, per jenis, per bulan
- **Semua query default `WHERE status != "Draf"`**
- Query param `include_draft=true` untuk include draft
- Chart: tren bulanan, per kecamatan, per jenis hama

### Tahap 8 — Peta
- Leaflet/MapLibre di web, Google Maps/OSM di Flutter
- Marker clustering
- Filter: status, jenis, tanggal, `include_draft`
- Popup detail laporan

### Tahap 9 — Analisis & Ekspor
- Analisis hanya laporan dengan field minimum terisi
- Ekspor: CSV, Excel (PhpSpreadsheet), PDF (Dompdf)
- Respect `include_draft` filter

### Tahap 10 — Flutter Mobile App
- `flutter create` di `mobile/`
- Riverpod/BLoC state management
- Offline-first: SQLite (drift/sqflite) untuk draft
- Sync queue saat online
- JWT auth with secure storage (flutter_secure_storage)
- Camera & gallery pick, compress, upload

### Tahap 11 — Notifikasi
- Firebase Cloud Messaging (FCM) untuk mobile
- Email notifikasi untuk admin (verifikasi baru, laporan ditolak)
- In-app notification center

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

### Tahap 14 — Dokumentasi Akhir
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