# BLUEPRINT JAGAPADI — Ringkasan Arsitektur v1

> Dokumen ini adalah ringkasan blueprint. Dokumentasi lengkap: `docs/Dokumentasi-aplikasi-jagapadi-3509.md`

---

## 1. Ringkasan Proyek

| Item | Detail |
|------|--------|
| **Nama** | JAGAPADI (Jember Agrikultur Gapai Prestasi Digital) |
| **Tujuan** | Sistem pelaporan pertanian Hama/OPT & Kondisi Irigasi untuk Kab. Jember |
| **Aktor** | Petugas Lapangan (mobile), Admin/Verifikator (web) |
| **Hosting** | cPanel, document root → `backend/public` |

---

## 2. Stack Teknologi

| Layer | Teknologi |
|-------|-----------|
| Backend | PHP 8.2 native, MVC ringan, PDO, MariaDB/MySQL, REST JSON API |
| Web Admin | PHP server-rendered, Session + CSRF |
| Mobile | Flutter (Android), JWT, offline-first draft |
| Database | MariaDB/MySQL `utf8mb4` |
| Auth Web | Session + CSRF token |
| Auth Mobile | JWT (access + refresh) |

---

## 3. Modul v1 (MVP)

| Modul | Deskripsi |
|-------|-----------|
| **Auth Web** | Login/admin, session, CSRF, role: admin & petugas |
| **Auth Mobile** | Login petugas, JWT access/refresh token |
| **Laporan Hama/OPT** | CRUD, draft, submit, validasi field minimum analisis |
| **Laporan Irigasi** | CRUD, draft, submit, validasi field minimum analisis |
| **Verifikasi Admin** | Review Submitted → Verified / Rejected / Archived |
| **Dashboard & Statistik** | Filter `include_draft=true|false` (default `false`) |
| **Peta** | Titik laporan, filter status, cluster |
| **Analisis** | Hanya laporan dengan field minimum analisis |
| **Ekspor** | CSV/Excel/PDF, respect `include_draft` |

---

## 4. Status Laporan (Workflow)

```
DRAFT → SUBMITTED → DVERIFIKASI / DITOLAK → DIARSIPKAN
          ↑
          └─ (Admin verifikasi)
```

| Status | Deskripsi | Bisa Diverifikasi? | Masuk Statistik Default? |
|--------|-----------|-------------------|-------------------------|
| `Draf` | Disimpan server, bisa diedit, offline-first mobile | **TIDAK** | **TIDAK** |
| `Submitted` | Dikirim petugas, menunggu verifikasi | Ya | Ya |
| `Diverifikasi` | Disetujui admin | N/A | Ya |
| `Ditolak` | Ditolak admin, kembali ke petugas | N/A | Tidak |
| `Diarsipkan` | Arsip, read-only | N/A | Tidak |

---

## 5. Kebijakan Draf (Krussial)

| Aturan | Detail |
|--------|--------|
| **Penyimpanan** | Draf wajib tersimpan di database server saat online |
| **Analisis Draf** | Bisa dianalisis **jika field minimum analisis terisi** |
| **Statistik Default** | **Tidak** memasukkan Draf (`include_draft=false` default) |
| **Filter** | Semua endpoint dashboard/peta/analisis/ekspor wajib support `include_draft=true|false` |
| **Nomor Laporan** | **Hanya dibuat saat Submit**, bukan saat Draf |
| **Akses Petugas** | Hanya laporan milik sendiri (draf & submitted) |
| **Verifikasi** | Hanya admin yang memverifikasi `Submitted` → `Diverifikasi`/`Ditolak` |

---

## 5. Role & Otorisasi

| Role | Akses |
|------|-------|
| **Admin** | Kelola user, verifikasi laporan, dashboard full, peta full, ekspor full |
| **Petugas** | CRUD laporan sendiri (draf/submit), lihat peta sendiri, tidak bisa verifikasi |

---

## 6. Konvensi API (Rencana)

| Konvensi | Detail |
|----------|--------|
| Base path | `/api/v1` |
| Format | JSON |
| Auth mobile | `Authorization: Bearer <JWT>` |
| Auth web | Session cookie + `X-CSRF-TOKEN` header |
| Filter draft | `?include_draft=true` / `?include_draft=false` (default false) |
| Pagination | `page`, `per_page` (default 15, max 100) |
| Error format | `{ "error": { "code": "...", "message": "..." } }` |

---

## 7. Database Target

- **Engine**: MariaDB 10.6+ / MySQL 8.0+
- **Charset**: `utf8mb4`
- **Collation**: `utf8mb4_unicode_ci`
- **PK**: `BIGINT UNSIGNED AUTO_INCREMENT`
- **FK**: Explicit, `ON DELETE RESTRICT` default
- **Timestamps**: `created_at`, `updated_at` (DATETIME)
- **Soft delete**: `deleted_at` (nullable) untuk audit trail

---

## 8. Keamanan (Prinsip)

- **No secrets in repo** — `.env` hanya di server
- **PDO prepared statements** — wajib di semua query
- **No raw SQL from input** — validasi & sanitasi ketat
- **HTML escape** — `htmlspecialchars()` semua output HTML
- **CSRF** — semua mutasi web wajib CSRF token
- **AuthZ** — semua endpoint API cek role & ownership
- **File upload** — validasi magic bytes, MIME, ekstensi, ukuran, nama acak

---

## 9. Referensi Lengkap

- `docs/Dokumentasi-aplikasi-jagapadi-3509.md` — Blueprint detail (45+ halaman)
- `docs/TUTORIAL_BUILD.md` — 15 tahap pembangunan
- `docs/API.md` — Kontrak API (placeholder)
- `docs/DATABASE.md` — Skema DB (placeholder)
- `AGENTS.md` — Instruksi agent
