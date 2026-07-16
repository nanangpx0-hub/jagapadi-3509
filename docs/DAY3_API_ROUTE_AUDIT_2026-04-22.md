# Day 3 - API Route Audit (2026-04-22)

## Ringkasan hasil

- Semua route API pada `app/core/Router.php` sekarang menunjuk ke file controller dan method yang tersedia.
- Endpoint yang belum siap implementasi tidak lagi berisiko memunculkan `500` karena class/method hilang.
- Endpoint tersebut sekarang merespons `501 Not Implemented` secara eksplisit.

## Endpoint utama yang valid

- `GET/POST/PUT/DELETE /api/laporan-hama` dan detail by id.
- `GET/POST/PUT/DELETE /api/irigasi` + endpoint monitoring/rules/analytics.
- `GET /api/wilayah/*` (kabupaten/kecamatan/desa/hierarchy/search/stats/by-coordinates).
- `GET /api/dashboard/*` (stats/charts/activities/alerts).
- `GET /api/dashboard/map/*` dan `GET /api/dashboard/charts/*`.
- `GET/POST/PUT/DELETE /api/users/*` (profile/password/toggle/force-password-change).
- `GET/POST/PUT/DELETE /api/opt/*` + search/filter/stats.
- `POST/GET /api/external/*` untuk integrasi eksternal.

## Endpoint rusak yang ditutup/perbaiki

- Ditutup sementara dengan `501` (controller stub):
  - `/api/pengairan/*` (IoT endpoints)
  - `/api/storytelling/*`
- Perbaikan kompatibilitas API:
  - Tambah `app/models/Wilayah.php` agar endpoint wilayah tidak gagal class/model not found.
  - Tambah method minimum yang dipanggil API di model `User`, `MasterOpt`, `Irigasi`, dan `LaporanHama`.
  - Tambah alias `jsonResponse()`/`errorResponse()` di `BaseApiController` untuk controller API peta/charts.
  - Router sekarang mengembalikan `501` saat controller/class/method handler belum tersedia.
