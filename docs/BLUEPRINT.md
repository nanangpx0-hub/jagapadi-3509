# BLUEPRINT JAGAPADI — Ringkasan Arsitektur dan Bisnis

> Blueprint ini sengaja ringkas. Detail runtime dan peta kode tersedia di
> [`REFERENSI_TEKNIS_BACKEND_AI.md`](REFERENSI_TEKNIS_BACKEND_AI.md).

## 1. Ringkasan Proyek

JAGAPADI (Jember Agrikultur Gapai Prestasi Digital) mendukung pelaporan dan
pemantauan pertanian Kabupaten Jember. Petugas mengelola laporan lapangan;
Admin mengelola data global dan workflow verifikasi.

Stack utama: PHP 8.2 native, MVC ringan, PDO, MariaDB/MySQL, REST JSON API,
web server-rendered dengan session/CSRF, serta Flutter Android dengan JWT.

## 2. Topologi Runtime

Repository memiliki dua runtime:

| Runtime | Entry point | Route | Catatan |
|---|---|---|---|
| Root/integrated | `../index.php` | `../config/web_routes.php` dan `../app/core/Router.php` | Web terintegrasi dan API internal/kompatibilitas |
| Backend v1 | `../backend/public/index.php` | `../backend/config/routes.php` | Target utama deployment Backend v1; API canonical `/api/v1` |

Document root produksi Backend v1 diarahkan ke `backend/public`. Runtime yang
berlaku tetap harus ditentukan dari base URL, front controller, route,
middleware, controller, migration, dan database deployment aktual.

## 3. Sumber Kebenaran

Jika dokumentasi berbeda, gunakan urutan berikut:

1. route, middleware, controller/service/model, migration, dan database runtime
   target yang berjalan;
2. test relevan;
3. [`API.md`](API.md), OpenAPI terkait, dan [`DATABASE.md`](DATABASE.md);
4. blueprint serta panduan pengembangan.

Migration yang ada di filesystem belum tentu sudah dijalankan. Verifikasi tabel
`schema_migrations` pada database target sebelum mengambil kesimpulan.

## 4. Autentikasi, Role, dan Ownership

- Web: session dan CSRF untuk mutasi.
- Mobile/API v1: JWT Bearer access token; diperbarui melalui endpoint refresh
  sesuai kontrak API aktual. Tidak ada klaim refresh token terpisah.
- Role database dapat mencakup `admin`, `petugas`, `operator`, `statistisi`, dan
  `viewer`; izin efektif mengikuti middleware/policy route.
- Petugas hanya dapat mengakses resource miliknya; Admin hanya memperoleh akses
  global pada route yang dilindungi policy Admin.
- Ownership berasal dari session/JWT, bukan `user_id` atau role kiriman client,
  dan harus diterapkan pada query serta diperiksa ulang pada policy/controller.

Ownership mencakup laporan, foto, feedback, notifikasi, device token,
dashboard, grafik, peta, ekspor, dan detail resource.

## 5. Modul Aktif

| Modul | Cakupan |
|---|---|
| Auth/RBAC | Login web/API, session, CSRF, JWT, pergantian password, revokasi |
| Hama/OPT | Draf, CRUD, submit/resubmit, foto, verifikasi, arsip |
| Irigasi | Draf, CRUD, submit/resubmit, foto, verifikasi, arsip |
| Laporan Lainnya | Runtime root dan/atau modul tambahan Backend v1 sesuai route/migration target |
| Master Data | Wilayah, OPT, user sesuai role |
| Dashboard | Statistik, grafik, ringkasan dan cache dengan scope pengguna |
| Peta | Endpoint Hama/Irigasi tersedia; pemakaian UI bergantung role/runtime |
| Feedback | Form/riwayat pribadi Petugas; rekap dan status global Admin |
| Notifikasi | Notifikasi database, unread/read, event workflow |
| Device Token | Registrasi dan penghapusan token perangkat milik pengguna |
| Ekspor | Hama/Irigasi dan ekspor lain sesuai runtime serta scope |
| Audit/Health/Operasi | Activity/status history, health check, logging, migration, deployment |

## 6. Workflow Laporan

```text
Draf → Submitted → Diverifikasi → Diarsipkan
              └→ Ditolak → Draf
                         └→ Submitted (resubmit oleh pemilik)
```

Nilai internal resmi adalah `Draf`, `Submitted`, `Diverifikasi`, `Ditolak`, dan
`Diarsipkan`. UI boleh menampilkan label `Dikirim` untuk `Submitted`, tetapi DB,
API, query, filter, controller, dan test tetap memakai `Submitted`.

- Nomor laporan dibuat atomik ketika laporan pertama kali menjadi `Submitted`.
- Resubmit laporan `Ditolak` mempertahankan nomor laporan yang sudah ada.
- Petugas hanya mengedit miliknya yang `Draf` atau `Ditolak` sesuai policy modul.
- Hanya Admin dapat memverifikasi atau menolak laporan `Submitted`.
- Draf tidak dapat diverifikasi.
- Pada workflow resmi Backend v1, hanya laporan `Diverifikasi` dapat diarsipkan.
  Jalur kompatibilitas root yang berbeda harus didokumentasikan terpisah.

## 7. Kebijakan Draf dan Agregat

- Draf disimpan ke database server saat koneksi tersedia dan dapat tampil pada
  daftar/kartu pekerjaan Petugas.
- Statistik, grafik, peta, analisis, dan ekspor resmi mengecualikan Draf secara
  default.
- Endpoint agregat yang relevan mendukung `include_draft=true|false`, dengan
  default `false`, sesuai kontrak endpoint.
- Draf hanya dapat dianalisis bila field minimum analisis tersedia dan endpoint
  tersebut memang mengizinkannya.
- Perubahan laporan/status harus menginvalidasi cache agregat terkait.

## 8. Database

- MariaDB/MySQL, InnoDB, `utf8mb4`; collation mengikuti migration aktual.
- PK tidak seragam: master/user dapat menggunakan `INT UNSIGNED`, sedangkan
  laporan/log/notifikasi dapat menggunakan `BIGINT UNSIGNED`.
- Timestamp dapat berupa `TIMESTAMP` atau `DATETIME` sesuai migration.
- Soft delete bukan kebijakan global.
- Integritas menggunakan FK, constraint, transaction/lock, dan indeks sesuai
  kebutuhan query.
- Migration bersifat append-only dan status eksekusinya diverifikasi melalui
  `schema_migrations` database target.
- Audit trail dapat menggunakan activity log, audit wilayah, notifikasi, dan
  status history bila tabel/migration tersebut tersedia pada runtime target.

## 9. Prinsip Keamanan dan Pengujian

- Prepared statement; tidak ada SQL mentah dari input.
- Escape output HTML; CSRF untuk seluruh mutasi web.
- Autentikasi, role, dan ownership wajib pada setiap endpoint/resource.
- Upload memvalidasi magic bytes, MIME, ekstensi, ukuran, nama acak, dan lokasi
  non-executable.
- Jangan menyimpan secret dalam repository atau log.
- Perubahan data Petugas diuji dengan Petugas A, Petugas B, dan Admin, termasuk
  IDOR, role bypass, workflow status, CSRF, SQL injection, XSS, upload spoofing,
  serta contract API sesuai scope.

## 10. Referensi

- [`API.md`](API.md) — kontrak API aktif.
- [`DATABASE.md`](DATABASE.md) — referensi schema dan kebijakan database.
- [`openapi-petugas.yaml`](openapi-petugas.yaml) — kontrak machine-readable
  Petugas, jika tersedia dan relevan.
- [`TUTORIAL_BUILD.md`](TUTORIAL_BUILD.md) — tahapan pembangunan.
- [`REFERENSI_TEKNIS_BACKEND_AI.md`](REFERENSI_TEKNIS_BACKEND_AI.md) — detail
  runtime, arsitektur, dan peta kode.
- [`PETUGAS_BACKEND_AI_GUIDE.md`](PETUGAS_BACKEND_AI_GUIDE.md) — panduan Petugas,
  jika tersedia dan relevan.
- [`IMPLEMENTASI_PETUGAS_LAPORAN_FEEDBACK_DASHBOARD.md`](IMPLEMENTASI_PETUGAS_LAPORAN_FEEDBACK_DASHBOARD.md)
  — implementasi UI Petugas, jika tersedia dan relevan.
- `../dok/Dokumentasi-aplikasi-jagapadi-3509.md` — dokumen historis/handover,
  jika tersedia; validasi kembali terhadap implementasi aktual.
