# JAGAPADI — Jember Agrikultur Gapai Prestasi Digital

Sistem pelaporan pertanian (Hama/OPT & Kondisi Irigasi) untuk Kabupaten Jember.

---

## Status Proyek

**v1.0.0 Production Ready** ✅

---



## Stack Teknologi (Direncanakan)

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP 8.2 native, MVC ringan, PDO, MariaDB/MySQL, REST API |
| Web Admin | PHP server-rendered, Session + CSRF |
| Mobile | Flutter (Android), JWT auth, offline-first draft |
| Database | MariaDB / MySQL `utf8mb4` |
| Hosting | cPanel (document root → `backend/public`) |

---

## Struktur Monorepo

```
jagapadi/
├── backend/          # PHP backend (PHP 8.2 MVC skeleton)
├── mobile/           # Flutter app (placeholder)
├── docs/             # Dokumentasi teknis
├── scripts/          # Utility scripts (placeholder)
├── .github/
│   ├── workflows/    # CI/CD workflows (placeholder)
│   └── ISSUE_TEMPLATE/
├── README.md
├── CHANGELOG.md
├── AGENTS.md         # Instruksi permanen untuk AI agent
├── .gitignore
├── .editorconfig
```

> **Catatan**: Backend telah memiliki MVC lengkap (auth, master data wilayah & OPT, CRUD laporan hama & irigasi, workflow verifikasi admin, upload foto aman) dan skema database lengkap (11 tabel). Mobile masih placeholder.

---

## Dokumentasi (Folder `docs/`)

| File | Deskripsi |
|------|-----------|
| `BLUEPRINT.md` | Ringkasan arsitektur, modul v1, status laporan, kebijakan Draf |
| `TUTORIAL_BUILD.md` | Tahapan pembangunan 0–14 |
| `API.md` | Kontrak API (`/api/v1`, JSON, JWT, `include_draft`) |
| `DATABASE.md` | Skema database aktual, migrasi & seed |
| `DEPLOY.md` | **Panduan deployment production (Nginx, TLS, backup, cron)** |
| `SMOKE_TEST.md` | Prosedur smoke test post-deploy |
| `GO_LIVE_CHECKLIST.md` | Checklist go-live |
| `QA_CHECKLIST.md` | Checklist regresi manual |
| `Dokumentasi-aplikasi-jagapadi-3509.md` | Dokumen referensi lengkap (blueprint detail) |

---

## Clone Repository

```bash
git clone <repository-url>
cd jagapadi-3509
```

> **Catatan**: Instruksi instalasi backend tersedia di `backend/README.md` (lokal) dan `docs/DEPLOY.md` (production). Instruksi Flutter tersedia di `mobile/README.md`.

---

## Mulai Cepat

### Akses web backend
- Panduan lokal & production: [docs/AKSES_WEB_BACKEND.md](docs/AKSES_WEB_BACKEND.md)
- Jalankan web lokal:
  1. `cd backend`
  2. `composer install`
  3. `php -S localhost:8080 -t public`

### Build APK
- Panduan build debug/release: [docs/BUILD_APK.md](docs/BUILD_APK.md)
- Build debug cepat:
  1. `cd mobile`
  2. `flutter pub get`
  3. `flutter build apk --debug`

---

## Aturan Kerja (Ringkas)

- **Branch per task/issue** → Pull Request → Review → Merge
- **Conventional Commits**: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`, `ci:`
- **AGENTS.md** = panduan wajib AI agent (baca sebelum coding)
- **Tidak commit secret** (`.env`, `.key`, `.pem`, token, password)
- **Tahap 1-2**: Setup repo & backend skeleton
- **Tahap 3**: Database migration & seed data lokal
- **Tahap 4**: Authentication web (Session+CSRF) & mobile (JWT)
- **Tahap 5**: Master data wilayah & OPT
- **Tahap 6**: Laporan Hama (CRUD, Draft, Submit)
- **Tahap 7**: Laporan Irigasi (CRUD, Draft, Submit)
- **Tahap 8**: Verifikasi Admin (hama & irigasi)
- **Tahap 9**: Upload Foto Aman (OPT + laporan)
- **Tahap 10**: Dashboard, Statistik & Cache (KPI, chart, peta, cache file TTL 5 menit)
- **Tidak commit secret** (`.env`, `.key`, `.pem`, token, password)

---

## Lisensi

Proyek internal Pemerintah Kabupaten Jember. Hak cipta dilindungi.
