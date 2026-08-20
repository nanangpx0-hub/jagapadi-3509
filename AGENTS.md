# AGENTS.md — Instruksi Permanen AI Coding Agent JAGAPADI

> Wajib dibaca sebelum menganalisis atau mengubah repository.

## 1. Proyek dan Runtime

JAGAPADI adalah sistem pelaporan pertanian Kabupaten Jember. Stack utama:
PHP 8.2 native, MVC ringan, PDO, MariaDB/MySQL, web server-rendered dengan
session dan CSRF, serta Flutter Android dengan JWT.

Repository memiliki dua runtime yang tidak boleh dicampur tanpa audit:

| Runtime | Front controller | Route utama | Auth umum |
|---|---|---|---|
| Root/integrated | `index.php` | `config/web_routes.php` dan route internal `app/core/Router.php` | Session + CSRF; sebagian API internal/external memiliki middleware sendiri |
| Backend v1 | `backend/public/index.php` | `backend/config/routes.php`; API canonical `/api/v1` | Session + CSRF untuk web; JWT Bearer untuk API |

Target produksi Backend v1 menggunakan document root `backend/public`. Namun,
agent wajib menentukan runtime yang sedang ditangani dari base URL, front
controller, route, middleware, controller, migration, dan database target yang
benar. Jangan menganggap kedua runtime mempunyai kontrak atau schema migration
yang identik.

## 2. Sumber Kebenaran dan Dokumen

Urutan resolusi konflik:

1. Route, middleware, controller/service/model, migration, dan database runtime
   target yang benar-benar berjalan.
2. Test otomatis yang relevan dengan runtime tersebut.
3. `docs/API.md`, OpenAPI terkait, dan `docs/DATABASE.md`.
4. Dokumen arsitektur dan panduan pengembangan.

Dokumen minimum untuk semua task:

1. `AGENTS.md` — instruksi permanen.
2. `README.md` — gambaran repository.
3. `docs/BLUEPRINT.md` — ringkasan arsitektur dan bisnis.
4. `docs/REFERENSI_TEKNIS_BACKEND_AI.md` — detail dua runtime dan peta kode.

Dokumen tambahan dibaca sesuai scope:

| Scope | Dokumen |
|---|---|
| Tahapan pembangunan | `docs/TUTORIAL_BUILD.md` |
| API | `docs/API.md`; `docs/openapi.yaml` jika tersedia |
| Database | `docs/DATABASE.md`; migration dan `schema_migrations` runtime target |
| Role Petugas | `docs/PETUGAS_BACKEND_AI_GUIDE.md` dan `docs/openapi-petugas.yaml` jika tersedia |
| Implementasi dashboard/laporan/feedback Petugas | `docs/IMPLEMENTASI_PETUGAS_LAPORAN_FEEDBACK_DASHBOARD.md` jika tersedia |
| Deployment | `docs/DEPLOY.md`, `docs/SMOKE_TEST.md`, `docs/GO_LIVE_CHECKLIST.md` jika tersedia |
| QA/testing | `docs/QA_CHECKLIST.md` dan `TESTING_GUIDE.md` jika tersedia |

Dokumen historis atau handover, termasuk `docs/DOKUMENTASI_PROYEK.md` dan
`dok/Dokumentasi-aplikasi-jagapadi-3509.md`, digunakan jika tersedia dan
relevan, bukan sebagai pengganti audit implementasi aktual.

## 3. Aturan Bisnis Laporan

| Aturan | Ketentuan |
|---|---|
| Status resmi | `Draf`, `Submitted`, `Diverifikasi`, `Ditolak`, `Diarsipkan` |
| Label UI | `Dikirim` boleh menjadi label tampilan untuk `Submitted`; nilai DB/API/query/test tetap `Submitted` |
| Draf | Disimpan di server saat koneksi tersedia; dapat tampil sebagai pekerjaan Petugas |
| Agregat resmi | Statistik, grafik, peta, analisis, dan ekspor default tidak memasukkan Draf |
| Filter Draf | Endpoint agregat yang relevan mendukung `include_draft=true\|false`, default `false` |
| Nomor laporan | Dibuat atomik saat pertama kali menjadi `Submitted`, bukan saat `Draf` |
| Resubmit | Laporan `Ditolak` kembali ke `Submitted` tanpa mengganti nomor yang sudah ada |
| Verifikasi | Hanya Admin; hanya laporan `Submitted` dapat diverifikasi atau ditolak |
| Arsip Backend v1 | Hanya Admin; transisi resmi `Diverifikasi` → `Diarsipkan` |
| Draf | Tidak boleh diverifikasi |

Workflow resmi Backend v1:

```text
Draf → Submitted → Diverifikasi → Diarsipkan
              └→ Ditolak → Draf
                         └→ Submitted (resubmit pemilik)
```

Runtime root/integrated memiliki jalur kompatibilitas lama pada sebagian modul.
Jika perilakunya berbeda, dokumentasikan perbedaan itu dan jangan menurunkan
jaminan workflow Backend v1.

## 4. Role dan Ownership

Role database aktual dapat mencakup `admin`, `petugas`, `operator`,
`statistisi`, dan `viewer`. Izin efektif tetap ditentukan middleware dan policy
route target; jangan menyimpulkan izin hanya dari keberadaan nilai role.

- Petugas hanya dapat melihat dan mengelola resource miliknya.
- Identitas pemilik wajib berasal dari session/JWT authenticated user.
- Jangan percaya `user_id`, `role`, `nomor_laporan`, `verified_by`,
  `verified_at`, `catatan_verifikasi`, atau status administratif dari client
  sebagai sumber otorisasi.
- Terapkan ownership pada query dan periksa ulang pada controller/service/policy.
- Aturan ini berlaku pada laporan, foto, feedback, notifikasi, device token,
  dashboard, grafik, peta, ekspor, dan detail resource.
- Admin mempunyai akses global hanya pada route yang secara eksplisit
  dilindungi policy Admin.

## 5. Database dan Migration

- Target: MariaDB/MySQL, InnoDB, `utf8mb4`; ikuti collation migration aktual.
- Tipe PK mengikuti schema: master/user dapat memakai `INT UNSIGNED`, sedangkan
  laporan/log/notifikasi dapat memakai `BIGINT UNSIGNED`; jangan dipukul rata.
- Timestamp mengikuti migration aktual (`TIMESTAMP` atau `DATETIME`).
- Soft delete bukan aturan global; gunakan hanya bila schema/modul menerapkannya.
- Semua perubahan schema harus memakai migration baru yang append-only.
- Jangan mengubah migration yang sudah tercatat di `schema_migrations`.
- Bandingkan file migration dengan `schema_migrations` database target sebelum
  menyatakan migration sudah dijalankan.
- Gunakan FK, constraint, transaction, lock, dan indeks sesuai integritas serta
  pola query aktual.

## 6. Keamanan

- Jangan commit `.env`, token, password, private key, `.pem`, atau `.key`.
- Semua SQL menggunakan prepared statement; input tidak boleh membentuk SQL
  mentah, termasuk nama kolom/order tanpa allowlist.
- Escape output HTML dengan `htmlspecialchars()` atau helper `e()`.
- Semua mutasi web wajib CSRF dan method validation.
- Semua endpoint wajib autentikasi dan otorisasi sesuai kontrak.
- Upload wajib memeriksa error, ukuran, magic bytes, MIME, ekstensi, nama acak,
  traversal, dan non-executable storage.
- Log tidak boleh memuat password, JWT, API key, atau data pribadi berlebihan.

## 7. Kontrak dan Sinkronisasi Dokumentasi

Jika route, parameter, payload, response, status code, autentikasi, role,
ownership, cache, atau schema berubah, sinkronkan dalam task yang sama:

- route dan implementasi runtime target;
- `docs/API.md`;
- OpenAPI yang relevan;
- `docs/DATABASE.md` bila schema berubah;
- test kontrak/otorisasi;
- panduan fitur terkait.

## 8. Cara Kerja dan Testing

1. Jalankan `git status`; perubahan yang sudah ada dianggap milik pengguna.
2. Baca dokumen serta kode relevan sebelum mengedit.
3. Jangan refactor di luar scope.
4. Selesaikan perubahan kecil, lalu lint/test secara proporsional.
5. Untuk perubahan data Petugas, uji minimal Petugas A, Petugas B, dan Admin.
6. Sesuai scope, cakup IDOR, role bypass, CSRF, SQL injection, XSS, upload
   spoofing, workflow status, idempotensi, dan contract API.
7. Laporan akhir berisi file berubah, hasil test, risiko, dan pekerjaan lanjutan.

Konvensi: PSR-12, `declare(strict_types=1)`, type hint ketat, indent PHP 4 spasi,
YAML/JSON/Markdown 2 spasi, LF, UTF-8, DB `snake_case`, API v1 JSON/JWT, serta
Conventional Commits pada branch per task.

> Agent yang tidak mengikuti AGENTS.md ini tidak diizinkan mengubah kode produksi.
