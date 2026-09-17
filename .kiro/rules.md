# .kiro/rules.md — Aturan Khusus Kiro Agent untuk JAGAPADI

1. **Dual Runtime**:
   - `index.php` (Root/Integrated): Web UI & admin. `config/web_routes.php` beku di 136 rute. Gunakan auto-dispatch di `index.php`.
   - `backend/public/index.php` (Backend v1): REST API canonical `/api/v1` dengan JWT. Rute ada di `backend/config/routes.php`.
2. **Business Workflow**:
   - Status laporan: `Draf` -> `Submitted` -> `Diverifikasi` (status `Diarsipkan` telah dihapus).
   - Status penolakan: `Submitted` -> `Ditolak` -> `Draf` / `Submitted`.
   - Agregasi/statistik/peta abaikan `Draf`.
3. **Security & Quality**:
   - `declare(strict_types=1);` pada seluruh file PHP.
   - PDO prepared statement untuk setiap query.
   - XSS sanitization dengan `htmlspecialchars()` / `e()`.
   - CSRF protection pada mutasi web.
4. **Verifikasi**:
   - Selalu jalankan `php vendor/bin/phpunit` sebelum menyelesaikan pekerjaan.
