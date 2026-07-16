# JAGAPADI — Jember Agrikultur Gapai Prestasi Digital

Sistem pelaporan pertanian (Hama/OPT & Kondisi Irigasi) untuk Kabupaten Jember.

---

## Status Proyek

**Tahap 3 — Database Schema & Migration** (In Progress)

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

> **Catatan**: Backend telah memiliki skeleton MVC (entry point, router, PDO, env loader, logger, error handler, health endpoint) dan skema database lengkap (11 tabel). Mobile masih placeholder.

---

## Dokumentasi (Folder `docs/`)

| File | Deskripsi |
|------|-----------|
| `BLUEPRINT.md` | Ringkasan arsitektur, modul v1, status laporan, kebijakan Draf |
| `TUTORIAL_BUILD.md` | Tahapan pembangunan 0–14 (Tahap 1 = current) |
| `API.md` | Kontrak API placeholder (`/api/v1`, JSON, JWT, `include_draft`) |
| `DATABASE.md` | Skema database aktual (11 tabel), migrasi & seed |
| `Dokumentasi-aplikasi-jagapadi-3509.md` | Dokumen referensi lengkap (blueprint detail) |

---

## Clone Repository

```bash
git clone <repository-url>
cd jagapadi-3509
```

> **Catatan**: Instruksi instalasi backend sudah tersedia di `backend/README.md`. Instruksi Flutter akan ditambahkan pada Tahap 10.

---

## Aturan Kerja (Ringkas)

- **Branch per task/issue** → Pull Request → Review → Merge
- **Conventional Commits**: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`, `ci:`
- **AGENTS.md** = panduan wajib AI agent (baca sebelum coding)
- **Tidak commit secret** (`.env`, `.key`, `.pem`, token, password)
- **Tahap 1-2**: Setup repo & backend skeleton
- **Tahap 3**: Database migration & seed data lokal
- **Tidak commit secret** (`.env`, `.key`, `.pem`, token, password)

---

## Lisensi

Proyek internal Pemerintah Kabupaten Jember. Hak cipta dilindungi.
