# Testing Guide - Official Regression Checklist

Dokumen ini adalah checklist resmi regresi teknis sebelum deploy.

## 1) Pipeline Lokal Wajib (Pre-Deploy Gate)

Jalankan dari root project:

```bash
php scripts/run_local_pipeline.php
```

Pipeline berurutan:
1. `php -l` untuk file inti API.
2. Unit test dasar: `tests/Unit/CurahHujanValidatorTest.php`.
3. Smoke test route API: `scripts/smoke_test_api_routes.php`.

Jika salah satu langkah gagal, **deploy dibatalkan**.

---

## 2) Checklist Regresi Teknis (Wajib)

Isi kolom status: `PASS` / `FAIL` / `N/A`.

| No | Area Uji | Langkah Uji Singkat | Ekspektasi | Status |
| --- | --- | --- | --- | --- |
| 1 | SQL Injection | Kirim payload `' OR 1=1 --` ke input API/form penting | Ditolak validasi / 4xx, tidak ada query rusak | ☐ |
| 2 | Auth Bypass | Akses endpoint admin tanpa sesi / role admin | `401` atau `403`, tidak bocor data | ☐ |
| 3 | Rate Limiting | Spam endpoint publik (`/api/wilayah/kabupaten`) > limit | Muncul `429` + header rate limit | ☐ |
| 4 | Session Destruction | Login -> logout -> reuse session lama | Session lama tidak valid, akses ditolak | ☐ |
| 5 | CORS | Request dari origin whitelist vs non-whitelist | Hanya origin whitelist yang lolos | ☐ |
| 6 | Logger | Tulis log info/warn/error/security/API | Log JSON masuk ke `storage/logs` dengan field lengkap | ☐ |
| 7 | API Utama | Hit endpoint inti (`users`, `laporan-hama`, `irigasi`, `dashboard`, `wilayah`) | Respon tidak `500` karena class/method hilang | ☐ |
| 8 | Smoke Route | Jalankan `php scripts/smoke_test_api_routes.php` | Semua route `/api/*` punya controller+method valid | ☐ |

---

## 3) Langkah Uji Detail yang Direkomendasikan

### A. SQL Injection

```bash
curl -X POST http://localhost/jagapadi/api/external/report \
  -H "Content-Type: application/json" \
  -d "{\"lokasi\":\"' OR 1=1 --\",\"master_opt_id\":1,\"tanggal\":\"2026-01-01\",\"tingkat_keparahan\":\"Ringan\"}"
```

Target hasil: `400/401/422`, bukan `500`.

### B. Auth Bypass

```bash
curl -i http://localhost/jagapadi/api/users
```

Target hasil: `401` atau `403`.

### C. Rate Limiting

```bash
for i in {1..310}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
  http://localhost/jagapadi/api/wilayah/kabupaten
done
```

Target hasil: setelah threshold, status `429`.

### D. Session Destruction

1. Login dari browser.
2. Logout.
3. Akses endpoint auth-only dengan cookie lama.

Target hasil: tidak bisa akses endpoint protected.

### E. CORS

```bash
curl -i -X OPTIONS http://localhost/jagapadi/api/wilayah/kabupaten \
  -H "Origin: http://localhost" \
  -H "Access-Control-Request-Method: GET"
```

Target hasil: header CORS hanya untuk origin yang diizinkan.

### F. Logger

Contoh uji cepat via script PHP:

```php
require_once 'app/helpers/Logger.php';
Logger::info('regression-info');
Logger::warning('regression-warning');
Logger::error('regression-error');
Logger::security('REGRESSION_TEST', 'security event');
```

Target hasil: entri JSON muncul di log file.

### G. Endpoint API Utama

Minimal cek endpoint:
- `/api/users`
- `/api/laporan-hama`
- `/api/irigasi`
- `/api/dashboard/stats`
- `/api/wilayah/kabupaten`

Target hasil: tidak ada fatal error class/method missing.

---

## 4) Smoke Test Route API

Jalankan:

```bash
php scripts/smoke_test_api_routes.php
```

Kriteria lulus:
- Semua definisi route `/api/*` di router valid.
- Controller file ada.
- Method handler ada.
- Exit code `0`.

---

## 5) Sign-Off Hari 6

- Testing Date: ____________________
- Tester: ____________________
- Environment: `local` / `staging` / `production`
- Pipeline lokal: ☐ PASS ☐ FAIL
- Checklist regresi: ☐ PASS ☐ FAIL
- Catatan risiko tersisa: ____________________
