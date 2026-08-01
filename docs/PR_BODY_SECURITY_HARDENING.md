# PR: Security hardening, draft scope fix, native XLSX

## Ringkasan Perubahan

Security hardening putaran penuh, perbaikan konsistensi `include_draft`, dan migrasi XLSX ke native ZipArchive.

### Keamanan
- **APP_DEBUG=false** — cegah stack trace terekspos (via `.env`; tidak ikut commit)
- **JWT_SECRET regenerated** — token lama invalid; semua client perlu login ulang
- **Open redirect fixed** — `NotificationController::markRead()` + `CsrfMiddleware` redirect sekarang divalidasi whitelist regex `#^/[a-z0-9/_-]*$#`
- **session.cookie_secure** — aktif unconditional saat HTTPS (tidak bergantung `APP_ENV=production`)
- **Session idle timeout** — 8 jam tanpa aktivitas → session di-reset

### Fungsional
- **include_draft default false** untuk semua role (sebelumnya default true, dan hanya petugas yang terpengaruh). Dashboard, export, dan listing API kini konsisten exclude draft kecuali `?include_draft=true`
- **Dashboard + Export** — sekarang support parameter `include_draft` end-to-end
- **State machine lengkap** — `Draf → Submitted` ditambahkan ke `LaporanStatus::TRANSITIONS`
- **XLSX** — migrasi dari PclZip (tidak terinstall/tidak di composer.json) ke `ZipArchive` native PHP

### Mobile Flutter
- `user.dart`: field `is_active` (int) → `aktif` (bool) cocok response backend
- `api_client.dart`: method `delete()` support `data` body (FCM unregister token sebelumnya compile error)

### Web Residual
- Konfirmasi: password hash **tidak** disimpan di `$_SESSION`
- `.env.example` ditambahi `JWT_SECRET` + `JWT_EXPIRY`

## File Berubah (16 files)

| File | Perubahan |
|------|-----------|
| `.env.example` | Added `JWT_SECRET`, `JWT_EXPIRY` |
| `CURRENT_TASK.md` | Full audit report |
| `Controllers/Api/DashboardController.php` | Pass `include_draft` to service |
| `Controllers/Api/ExportController.php` | Pass `include_draft` to service |
| `Controllers/Web/ExportController.php` | Pass `include_draft`; removed dead code |
| `Controllers/Web/NotificationController.php` | Redirect whitelist validation |
| `Core/Security.php` | Session idle timeout; cookie_secure unconditional |
| `Helpers/LaporanStatus.php` | Added `Draf → Submitted` transition |
| `Helpers/XlsxWriter.php` | PclZip → ZipArchive |
| `Middleware/CsrfMiddleware.php` | Referer redirect whitelist |
| `Services/DashboardService.php` | `includeDraft` param + propagate ke semua query |
| `Services/ExportService.php` | `includeDraft` param + status filter default |
| `Services/LaporanHamaService.php` | `include_draft` default `false`; berlaku semua role |
| `Services/LaporanIrigasiService.php` | `include_draft` default `false`; berlaku semua role |
| `mobile/lib/core/api_client.dart` | `delete()` support `data` param |
| `mobile/lib/features/auth/models/user.dart` | `aktif` (bool) instead of `is_active` (int) |

## Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| JWT_SECRET baru | Semua token JWT lama invalid → client perlu login ulang | Komunikasikan ke pengguna mobile saat deploy |
| include_draft=false default | Draf tidak muncul tanpa parameter | Sesuai kontrak API.md |
| Session idle timeout | Session web hangus setelah 8 jam idle | Naikkan TTL jika tidak sesuai |
| ZipArchive | Butuh ekstensi PHP `zip` | Cek `php -m \| grep zip` saat deployment |

## Test

- PHPUnit: **125/125 PASS**, 231 assertions, 0 failures
- Export tests: 13/13 PASS
- Dashboard tests: 8/8 PASS
- Status/LaporanStatus tests: 24/24 PASS
- API smoke: health ✅, login ✅

Branch: `fix/security-hardening-draft-xlsx` (dibuat dari `feature/fcm`)
