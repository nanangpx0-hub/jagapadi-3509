# AGENTS.md — Permanent Instructions for AI Coding Agent (JAGAPADI)

> **Wajib dibaca** sebelum menulis kode apapun.

---

## 1. Ringkasan Proyek & Stack

| Aspek | Detail |
|-------|--------|
| Nama | JAGAPADI — Jember Agrikultur Gapai Prestasi Digital |
| Deskripsi | Sistem pelaporan pertanian (Hama/OPT & Kondisi Irigasi) untuk Kab. Jember |
| Backend | PHP 8.2 native, MVC ringan, PDO, MariaDB/MySQL, REST API |
| Web Admin | PHP server-rendered (Session + CSRF) |
| Mobile | Flutter Android (JWT auth) |
| Hosting | cPanel; document root production = `backend/public` |

---

## 2. Dokumen Wajib Dibaca Sebelum Coding

1. `docs/BLUEPRINT.md` — Arsitektur, modul v1, status laporan, kebijakan Draf
2. `docs/TUTORIAL_BUILD.md` — Tahapan pembangunan 0–14
3. `docs/API.md` — Kontrak API (`/api/v1`, JSON, JWT, `include_draft`)
4. `docs/DATABASE.md` — Target DB MariaDB/MySQL `utf8mb4`
5. `README.md` — Overview proyek & struktur monorepo

---

## 3. Aturan Bisnis Inti

| Aturan | Detail |
|--------|--------|
| Draf disimpan di server | Saat koneksi tersedia, draf wajib tersimpan ke DB server |
| Draf bisa dianalisis | Bila field minimum analisis terpenuhi |
| Statistik default tanpa Draf | `include_draft=false` default (dashboard, peta, analisis, ekspor) |
| Filter include_draft | Semua endpoint agregat wajib support `?include_draft=true\|false` |
| Status laporan | `Draf`, `Submitted`, `Diverifikasi`, `Ditolak`, `Diarsipkan` |
| Role awal | `admin`, `petugas` |
| Nomor laporan | Hanya dibuat saat `Submitted`, bukan saat `Draf` |
| Petugas hanya laporan sendiri | Enforced di level query & policy |
| Admin verifikasi Submitted | Hanya admin yang boleh verifikasi |
| Draf tidak boleh diverifikasi | Validasi wajib di server |

---

## 4. Aturan Keamanan

- **No secret commit**: `.env`, token, password, private key, `.pem`, `.key`
- **PDO prepared statements**: Semua query DB wajib prepared statement
- **No raw SQL from input**: Tidak ada query dari input mentah
- **HTML escape**: Semua output HTML wajib `htmlspecialchars()` / `e()`
- **CSRF**: Semua aksi mutasi web wajib CSRF token
- **AuthZ di API**: Semua endpoint wajib autentikasi + otorisasi
- **Upload validation**: Validasi magic bytes, MIME, ekstensi, ukuran, nama random

---

## 5. Cara Kerja Agent

1. **Baca dulu**: Baca dokumen & kode relevan sebelum coding
2. **Satu task kecil**: Selesaikan satu task, verifikasi, baru lanjut
3. **No schema change tanpa migration**: Jangan ubah skema DB tanpa migration baru
4. **No refactor di luar scope**: Jangan refactor di luar cakupan task
5. **Jalankan test/lint**: Setelah implementasi, jalankan test/lint relevan
6. **Laporan akhir**: Daftar file berubah, hasil test, risiko/pekerjaan lanjutan

---

## 6. Konvensi Kode

| Area | Aturan |
|------|--------|
| PHP | PSR-12, `declare(strict_types=1)`, type hint ketat |
| PHP indent | 4 spasi |
| YAML/JSON/MD | 2 spasi |
| Line ending | LF |
| Charset | UTF-8 |
| Naming DB | `snake_case` jamak, PK `id` (BIGINT UNSIGNED) |
| API | `/api/v1`, JSON, JWT, `include_draft` query param |
| Git | Conventional Commits, branch per task/issue |

---

## 7. Tahapan Pembangunan

| Tahap | Nama | Status |
|-------|------|--------|
| 1 | Repository & Standar Kerja | **Done** |
| 2 | Backend Skeleton | **Done** |
| 3 | Database Schema, Migration & Seed | **Done** |
| 4 | Auth Web & Mobile | Pending |
| 5 | Modul Laporan (CRUD, Draft, Submit) | Pending |
| 6 | Modul Verifikasi (Admin) | Pending |
| 7 | Dashboard & Statistik | Pending |
| 8 | Peta & Geospasial | Pending |
| 9 | Analisis & Ekspor | Pending |
| 10 | Mobile App Flutter | **DONE** |
| 11 | Notifikasi & Real-time (FCM) | **DONE** |
| 12 | Testing & QA | Pending |
| 13 | Deployment & CI/CD | **DONE** |
| 14 | Dokumentasi Akhir & Handover | Pending |

---

## 8. Aturan PR & Commit

- **Conventional Commits**: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`, `ci:`
- **Branch**: `feat/<slug>`, `fix/<slug>`, `chore/<slug>`
- **PR checklist**: scope sesuai, no secret, test/lint, docs updated, migration if needed, draft policy intact

---

> Agent yang tidak mengikuti AGENTS.md ini tidak diizinkan menulis kode produksi.