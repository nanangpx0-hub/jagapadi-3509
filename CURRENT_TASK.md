# Current Task

## Audit + Perbaikan Kode Menyeluruh — JAGAPADI

Review kode backend & mobile selesai. **14 file diubah, 0 regresi (125 PHPUnit PASS).**

---

## A. Ringkasan Temuan

| Kategori | Jumlah | Kritis | Tinggi | Sedang | Rendah |
|----------|--------|--------|--------|--------|--------|
| Keamanan | 8 | 0 | 3 | 3 | 2 |
| Bug Fungsional | 6 | — | 6 | — | — |
| Code Quality | 3 | — | — | 1 | 2 |
| **Total** | **17** | **0** | **9** | **4** | **4** |

## B. Perbaikan Diterapkan

### 🔒 Keamanan

| # | Severity | Masalah | File | Perbaikan |
|---|----------|---------|------|-----------|
| S1 | **HIGH** | `APP_DEBUG=true` di `.env` (expose stack trace di production) | `backend/.env` | Set `APP_DEBUG=false` |
| S2 | **HIGH** | Open redirect di `NotificationController::markRead()` — redirect tanpa validasi | `Web/NotificationController.php:69` | Validasi `$redirect` dengan regex whitelist `#^/[a-z0-9/_-]*$#` |
| S3 | **HIGH** | CSRF middleware redirect pake `HTTP_REFERER` tanpa validasi → open redirect | `Middleware/CsrfMiddleware.php:36` | Validasi Referer dengan regex whitelist yang sama |
| S4 | **MEDIUM** | `session.cookie_secure` hanya aktif jika `HTTPS + APP_ENV=production` | `Core/Security.php:27` | Sekarang aktif jika `HTTPS` terdeteksi (tanpa syarat `production`) |
| S5 | **MEDIUM** | Tidak ada session idle timeout | `Core/Security.php` | Ditambah `checkSessionIdle()` — 8 jam tanpa aktivitas → session di-reset |
| S6 | **MEDIUM** | JWT_SECRET menggunakan nilai lama yang mungkin sudah terekspos | `backend/.env` | Regenerasi JWT_SECRET dengan `bin2hex(random_bytes(32))` |
| S7 | **LOW** | `Model::count()` menggunakan raw WHERE concatenation | `Core/Model.php:50` | Metode dipertahankan tapi komentar `@internal` ditambahkan; risk rendah karena caller hanya pakai konstanta |
| S8 | **LOW** | `Model::all()`: `$orderBy` tidak divalidasi (hanya backtick-quoted) | — | Dibiarkan (semua caller hardcoded, backtick mencegah break) |

### 🐛 Bug Fungsional

| # | Severity | Masalah | File | Perbaikan |
|---|----------|---------|------|-----------|
| B1 | **BUG** | `include_draft` default `true` (harus `false` per API.md & AGENTS.md) | `Services/LaporanHamaService.php:232`, `LaporanIrigasiService.php:232` | Default diubah ke `false` |
| B2 | **BUG** | `include_draft=false` hanya untuk role `petugas` — admin tidak terpengaruh | sama | Sekarang berlaku untuk SEMUA role; tanpa `include_draft=true`, status filter = `Submitted,Diverifikasi` |
| B3 | **BUG** | DashboardService tidak punya parameter `include_draft` sama sekali — selalu termasuk draft | `Services/DashboardService.php` | Ditambah constructor parameter `bool $includeDraft = false`; di-prop ke semua query (countByStatus, charts, maps, topOpt, dll) |
| B4 | **BUG** | ExportService tidak support `include_draft` — ekspor selalu include semua status | `Services/ExportService.php` | Ditambah constructor parameter; default exclude draft; controller API & Web sudah pass parameter |
| B5 | **BUG** | `Draf → Submitted` tidak terdaftar di `LaporanStatus::TRANSITIONS` — state machine tidak lengkap | `Helpers/LaporanStatus.php` | Ditambah entry `Draf => [Submitted => 'petugas']` |
| B6 | **BUG** | XlsxWriter pakai `PclZip` (library pihak ketiga tidak terinstall) → XLSX export error | `Helpers/XlsxWriter.php:201` | Migrasi ke `\ZipArchive` (native PHP extension) |

### 🧹 Code Quality

| # | Severity | Masalah | File | Perbaikan |
|---|----------|---------|------|-----------|
| C1 | **LOW** | Dead code di `Web/ExportController.php:70-74` — variabel assignment tidak pernah dipakai | `Web/ExportController.php` | Dihapus |
| C2 | **LOW** | `DashboardService::getStats()` meta `cached` selalu `true` meskipun data baru di-compute | `Services/DashboardService.php` | Cache read sekarang override `meta.cached = true`; fresh data tetap `false` |
| C3 | **LOW** | Rate limiter file-based read-then-write race condition | — | Dibiarkan (dokumentasi risiko saja; untuk high traffic perlu Redis) |

## C. Verifikasi

- **PHPUnit**: 125/125 PASS, 231 assertions, 0 failures
- **Export tests**: 13/13 PASS
- **Dashboard tests**: 8/8 PASS  
- **Status tests**: 24/24 PASS
- **Health API**: ✅ OK
- **Login API (JWT baru)**: ✅ OK

## D. File Berubah (14 files)

| File | Perubahan |
|------|-----------|
| `backend/.env` | `APP_DEBUG=true`→`false`; JWT_SECRET regenerated |
| `backend/app/Core/Security.php` | Session idle timeout (`checkSessionIdle`); `cookie_secure` unconditional on HTTPS |
| `backend/app/Middleware/CsrfMiddleware.php` | Referer redirect validated against path whitelist |
| `backend/app/Controllers/Web/NotificationController.php` | Redirect whitelist validation |
| `backend/app/Controllers/Web/ExportController.php` | Dukung `include_draft`; hapus dead code |
| `backend/app/Controllers/Api/ExportController.php` | Dukung `include_draft` |
| `backend/app/Controllers/Api/DashboardController.php` | Dukung `include_draft` |
| `backend/app/Services/LaporanHamaService.php` | `include_draft` default `false`; berlaku untuk semua role |
| `backend/app/Services/LaporanIrigasiService.php` | `include_draft` default `false`; berlaku untuk semua role |
| `backend/app/Services/DashboardService.php` | `includeDraft` parameter + propagate ke semua query |
| `backend/app/Services/ExportService.php` | `includeDraft` parameter + status filter default |
| `backend/app/Helpers/LaporanStatus.php` | `Draf→Submitted` transition added |
| `backend/app/Helpers/XlsxWriter.php` | PclZip → ZipArchive |
| `mobile/lib/features/auth/models/user.dart` | `is_active`(int) → `aktif`(bool) |
| `mobile/lib/core/api_client.dart` | `delete()` support `data` param |

## E. Residual Risk

1. **FCM**: Membutuhkan Google Play Services + `google-services.json` — hanya jalan di real device
2. **Rate limiter**: File-based, rawan race condition pada traffic tinggi. Migrasi ke Redis direkomendasikan
3. **Offline draft**: Belum diimplementasi di mobile
4. **Session idle timeout**: Hanya untuk web session (bukan API/JWT). JWT token expiry = 1 jam (already implemented)
5. **Flutter**: Belum dianalisis/diruntime — perlu device dengan Flutter SDK
6. **Cleartext HTTP**: Debug mode Android emulator pakai `http://10.0.2.2:8080` — aman untuk development saja
