# QA Checklist — JAGAPADI

> Panduan regresi manual sebelum deploy.

---

## Auth

- [ ] Login web (`/login`) sukses dengan admin
- [ ] Login web sukses dengan petugas
- [ ] Login gagal → error flash message
- [ ] Rate limit: 5x gagal login → 429 / flash error
- [ ] Logout → session cleared → redirect ke `/login`
- [ ] Login API (`POST /api/v1/auth/login`) → JWT returned
- [ ] API 401 tanpa token / token invalid
- [ ] API change-password works
- [ ] CSRF: submit form tanpa `_csrf_token` → 419

## Wilayah (Admin)

- [ ] List kabupaten/kecamatan/desa
- [ ] Create/edit/delete kabupaten
- [ ] Hapus kabupaten dengan child → 409
- [ ] Create/edit/delete kecamatan
- [ ] Create/edit/delete desa

## OPT (Admin)

- [ ] List OPT (filter jenis, q, aktif)
- [ ] Create OPT
- [ ] Edit OPT
- [ ] Hapus OPT (hard delete if no references, soft deactivate if referenced)
- [ ] Upload/delete foto OPT
- [ ] Non-admin hanya melihat OPT aktif

## Laporan Hama

- [ ] Buat draft → status `Draf`, nomor_laporan NULL
- [ ] Edit draft
- [ ] Delete draft
- [ ] Submit draft → status `Submitted`, nomor_laporan `LH-...`
- [ ] Create + submit langsung (action=submit)
- [ ] Detail laporan (owner & admin)
- [ ] Petugas hanya melihat laporan sendiri
- [ ] List filter: status, tanggal, wilayah, OPT, q, pagination
- [ ] Upload foto draft/ditolak
- [ ] Hapus foto draft/ditolak
- [ ] Upload foto status selain draft/ditolak → 409

## Laporan Irigasi

- [ ] Buat draft → status `Draf`, nomor_laporan NULL
- [ ] Edit draft
- [ ] Delete draft
- [ ] Submit draft → status `Submitted`, nomor_laporan `LI-...`
- [ ] Create + submit langsung
- [ ] Detail laporan (owner & admin)
- [ ] Petugas hanya melihat laporan sendiri
- [ ] List filter: status, tanggal, wilayah, kondisi_fisik, debit_air, pagination
- [ ] Upload foto draft/ditolak
- [ ] Hapus foto draft/ditolak

## Verifikasi (Admin)

- [ ] Lihat list submitted (hama & irigasi)
- [ ] Verifikasi submitted → status `Diverifikasi`, verified_at terisi
- [ ] Tolak submitted → status `Ditolak`, alasan di catatan_verifikasi
- [ ] Alasan tolak < 10 karakter → 422
- [ ] Arsip diverifikasi → status `Diarsipkan`
- [ ] Resubmit ditolak → status `Submitted`, verified_at NULL
- [ ] Transisi ilegal → 409
- [ ] Petugas tidak bisa verifikasi

## Dashboard & Cache

- [ ] Halaman dashboard render KPI, chart, map
- [ ] Filter tahun berfungsi
- [ ] Stats JSON match halaman
- [ ] Chart data bulanan akurat
- [ ] Map menampilkan titik laporan
- [ ] Invalidate cache: submit/verify/reject → stats berubah

## Export

- [ ] Form filter export
- [ ] Export CSV (hama & irigasi)
- [ ] Export XLSX (hama & irigasi)
- [ ] Filter status/tanggal/wilayah dihormati
- [ ] Max 10.000 rows → 422 jika lebih
- [ ] Petugas hanya export data sendiri
- [ ] Temp file dihapus setelah download

## Notifikasi

- [ ] Petugas submit → admin dapat `laporan_submitted`
- [ ] Petugas resubmit → admin dapat `laporan_resubmitted`
- [ ] Admin verifikasi → owner dapat `laporan_verified`
- [ ] Admin tolak → owner dapat `laporan_rejected` (cuplikan alasan)
- [ ] Admin arsip → owner dapat `laporan_archived`
- [ ] Bell badge unread count akurat
- [ ] Mark read → badge berkurang
- [ ] Mark all read → badge 0
- [ ] Klik notif → redirect ke detail laporan
- [ ] Petugas A tidak lihat notif Petugas B
- [ ] Polling 60 detik tidak overload

## Upload Foto

- [ ] File valid (JPEG/PNG/WebP) → sukses
- [ ] File jahat (.php, .exe) → 422
- [ ] File >10MB → 422
- [ ] Magic bytes valid → reject file dengan ekstensi .jpg tapi konten text
- [ ] Delete foto → foto_url NULL
- [ ] Path traversal dicegah

## Security

- [ ] APP_DEBUG=false → no stack trace
- [ ] Session cookie httponly, samesite=Lax
- [ ] CSRF token required for all POST/PUT/DELETE web
- [ ] X-Content-Type-Options: nosniff
- [ ] X-Frame-Options: DENY
- [ ] CSP header aktif
- [ ] Upload .htaccess protects from PHP execution
- [ ] Rate limit: export 20/jam, notif poll 120/jam, API 1000/jam

## Role Scope

- [ ] Admin: melihat semua laporan, verifikasi, kelola wilayah + OPT
- [ ] Petugas: hanya laporan sendiri, tidak bisa verifikasi
- [ ] API JWT: role enforcement
- [ ] Web session: role enforcement via middleware

---

## Environment

| Env | Expected |
|-----|----------|
| `.env` | Tidak di-commit ke repo |
| `JWT_SECRET` | Minimal 64 karakter, unik per environment |
| `APP_DEBUG` | `false` di production |
| `storage/cache/` | Writable |
| `storage/logs/` | Writable |
| `storage/tmp/` | Writable |
| `public/assets/uploads/` | Writable |
