# Laporan Audit Teknis JAGAPADI

Tanggal audit: 2026-04-22  
Ruang lingkup: backend PHP MVC custom, API, database MySQL/MariaDB, view PHP server-rendered, JavaScript/CSS statis, PWA/service worker, test yang tersedia.  
Catatan: laporan ini tidak menimpa file audit untracked yang sudah ada di `docs/` dan root repo.

## Ringkasan Eksekutif

JAGAPADI adalah aplikasi monolit PHP native dengan pola MVC custom. Sistem sudah memiliki beberapa kontrol dasar yang baik: prepared statement di banyak query, CSRF helper, password hashing, beberapa validasi upload, service layer untuk scraper/analitik, dan PWA offline support. Namun, audit menemukan masalah kritis pada stabilitas API, hygiene data sensitif, kontrol security produksi, dan maintainability frontend/backend.

Baseline statis yang ditemukan:

| Area | Baseline |
|---|---:|
| PHP file | 199 file / 79.032 baris |
| JS/CSS | 10 JS / 6 CSS / 7.198 baris |
| Route API di `Router` | 85 route |
| Route API yang menunjuk controller/method tidak ada | 15 route |
| Pemanggilan method model API yang tidak tersedia | 49 call site |
| Inline `<script>` di view | 104 blok |
| Inline `<style>` di view | 38 blok |
| Direct global input refs | `$_GET` 299, `$_POST` 355, `$_SESSION` 721 |
| Dump SQL ter-track | 1 file, 14.101 `INSERT INTO` |
| PHP lint | 0 syntax error |
| Test tersedia | Unit CurahHujan 36/36 pass; Integration CurahHujan 6/13 pass |

Skor risiko keseluruhan: tinggi untuk production readiness. Prioritas pertama sebaiknya bukan microservice, melainkan stabilisasi monolit: kontrak API, hardening security, migrasi state/cache, dan pemisahan service dari controller/view.

## Arsitektur

Arsitektur saat ini adalah monolit MVC custom, bukan microservice. Entry point ada di `index.php`, routing web dilakukan berdasarkan path ke controller/method, sedangkan route API didefinisikan manual di `app/core/Router.php`. View menggunakan PHP server-rendered dengan AdminLTE, jQuery, Bootstrap, Chart.js, Leaflet, dan JavaScript inline/statis.

Kelebihan:

| Area | Observasi |
|---|---|
| Deploy sederhana | Monolit cocok untuk tim kecil dan operasional Laragon/shared hosting. |
| Query dasar | Banyak query sudah memakai prepared statement. |
| Domain service | Ada service untuk BPS, BMKG, cuaca, scraper, analytics, import. |
| Password | `User` memakai `password_hash`/`password_verify` dengan bcrypt di jalur utama. |

Kelemahan arsitektural:

| Area | Masalah |
|---|---|
| Boundary | Controller memuat routing use case, validasi, upload, transaksi, logging, notifikasi, dan render. |
| API contract | Controller API tidak sinkron dengan model dan beberapa route menunjuk file yang tidak ada. |
| State | Rate limiting, brute force protection, cache, dan offline state banyak berbasis session/file. |
| Release | Tidak ada dependency manifest (`composer.json`/`package.json`) dan tidak ada pipeline test/build. |

## Issue Prioritas

### P0-01 API Banyak Gagal Runtime

Evidence:

- `app/core/Router.php:84-91` mendaftarkan `Api\IoTController`, tetapi `app/controllers/Api/IoTController.php` tidak ada.
- `app/core/Router.php:152-158` mendaftarkan `Api\StorytellingController`, tetapi controller API tersebut tidak ada.
- `app/controllers/Api/IrigasiController.php:4` me-require `app/models/Irigasi.php`, tetapi model tersebut tidak ada.
- API user/laporan/OPT/irigasi memanggil method model yang tidak tersedia, misalnya `getAllWithFilters`, `getCountWithFilters`, `getById`, `getByName`, `isUsedInReports`, `getStatistics` di `app/controllers/Api/*`.

Dampak bisnis: API mobile/integrasi eksternal tidak dapat diandalkan, dashboard realtime dapat gagal 500, dan tim support sulit membedakan bug data dari endpoint yang memang tidak valid.

Effort: 2-4 hari untuk stabilisasi minimum, 1-2 minggu untuk kontrak API lengkap.

Rekomendasi:

- Tambah smoke test route yang memvalidasi semua handler dan class/method sebelum deploy.
- Pilih satu kontrak model: `find/getById`, `paginate/getAllWithFilters`, `count/getCountWithFilters`.
- Hapus route yang belum siap atau buat controller stub eksplisit yang mengembalikan 501.
- Buat OpenAPI minimal untuk endpoint publik/internal.

Contoh refaktor smoke test route:

```php
public function testEveryApiRouteHandlerExists(): void
{
    $routerFile = ROOT_PATH . '/app/core/Router.php';
    $source = file_get_contents($routerFile);
    preg_match_all("/'([^']+)'\\s*,\\s*'([^']+@[^']+)'/", $source, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        [$controller, $method] = explode('@', $match[2]);
        $file = ROOT_PATH . '/app/controllers/' . str_replace('\\', '/', $controller) . '.php';

        $this->assertFileExists($file, "Missing controller for {$match[1]}");
        require_once $file;

        $class = basename(str_replace('\\', '/', $controller));
        $this->assertTrue(class_exists($class), "Missing class {$class}");
        $this->assertTrue(method_exists($class, $method), "Missing {$class}::{$method}");
    }
}
```

Contoh kompatibilitas model minimum:

```php
class User extends Model
{
    public function getById(int $id): ?array
    {
        return $this->getUserById($id) ?: null;
    }

    public function getAllWithFilters(array $filters, int $limit, int $offset): array
    {
        $page = intdiv($offset, max(1, $limit)) + 1;
        return $this->getAllUsers(
            $page,
            $limit,
            $filters['search'] ?? '',
            $filters['role'] ?? '',
            isset($filters['aktif']) ? (string) $filters['aktif'] : ''
        );
    }

    public function getCountWithFilters(array $filters): int
    {
        return (int) $this->getTotalUsers(
            $filters['search'] ?? '',
            $filters['role'] ?? '',
            isset($filters['aktif']) ? (string) $filters['aktif'] : ''
        );
    }
}
```

### P0-02 Data Sensitif dan Artefak Runtime Ter-track

Evidence:

- `bpsjembe_jagapadi.sql`, `cookies.txt`, dan `error_log` masuk `git ls-files`.
- Dump SQL berisi 14.101 `INSERT INTO`, termasuk data user, hash password, email, activity log, IP, dan user-agent.
- Script operasional berisi kredensial/default password hardcoded, misalnya `scripts/create_admin_user.php`, `scripts/create_multi_users.php`, dan script verifikasi password.

Dampak bisnis: kebocoran PII, hash password, token/cookie, dan log aktivitas; risiko kepatuhan dan reputasi; repo menjadi besar dan sulit direplikasi aman.

Effort: 1 hari untuk stop-the-bleed, 2-3 hari untuk pembersihan history dan rotasi.

Rekomendasi:

- Hapus artefak dari tracking: `git rm --cached bpsjembe_jagapadi.sql cookies.txt error_log`.
- Rotasi semua password/token yang pernah ada di dump/script.
- Pindahkan data seed ke seeder sanitised tanpa PII.
- Tambahkan secret scanning di pre-commit/CI.

Contoh `.gitignore` tambahan:

```gitignore
*.sql
*.dump
cookies.txt
error_log
logs/
storage/logs/
public/uploads/
config/api_config.php
```

Contoh config env-only:

```php
function envRequired(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException("Missing required environment variable: {$key}");
    }
    return $value;
}

define('SIMITRA_API_TOKEN', envRequired('SIMITRA_API_TOKEN'));
define('SMTP_PASS', envRequired('SMTP_PASS'));
```

### P0-03 SQL Identifier Injection dan Mass Assignment Risk

Evidence:

- `app/core/Model.php:79` membangun `"$key = ?"` dari nama field tanpa sanitasi.
- `app/core/Model.php:82` memakai `$this->table` langsung di SQL update.
- `app/core/Model.php:22` dan `app/core/Model.php:91` juga memakai `$this->table` langsung.
- API update mengirim `$data` hasil request ke model update, misalnya `app/controllers/Api/UserController.php:217` dan `app/controllers/Api/LaporanHamaController.php:163`.

Dampak bisnis: jika attacker dapat mengirim key field berbahaya melalui endpoint update, risiko query manipulation, update kolom yang tidak semestinya, atau error SQL yang leak ke response.

Effort: 1-2 hari.

Rekomendasi:

- Tambahkan identifier sanitizer/allowlist di base model.
- Terapkan `$fillable` per model untuk mencegah mass assignment.
- Jangan pernah membangun identifier SQL dari input request tanpa validasi.

Contoh refaktor:

```php
abstract class Model
{
    protected array $fillable = [];

    protected function identifier(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException('Invalid SQL identifier');
        }
        return "`{$value}`";
    }

    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            throw new LogicException(static::class . ' must define $fillable');
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function update($id, $data)
    {
        $data = $this->filterFillable($data);
        if ($data === []) {
            throw new InvalidArgumentException('No valid fields to update');
        }

        $set = [];
        foreach ($data as $column => $_) {
            $set[] = $this->identifier($column) . ' = ?';
        }

        $sql = 'UPDATE ' . $this->identifier($this->table)
            . ' SET ' . implode(', ', $set)
            . ' WHERE id = ?';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([...array_values($data), (int) $id]);
    }
}
```

### P0-04 CSRF dan Authorization Belum Konsisten

Evidence:

- `app/controllers/LaporanController.php:955` `delete($id)` tidak memanggil `validateCsrfToken()`, sehingga destructive action bisa dipanggil lewat GET route web.
- `app/core/Router.php:152-158` memakai middleware `statistisi`, tetapi `applyMiddleware()` hanya menangani `auth`, `admin`, dan `operator` (`app/core/Router.php:279-294`). Middleware yang tidak dikenal diabaikan.
- API state-changing memakai session auth, tetapi tidak ada CSRF gate terpusat di `Router`/`BaseApiController`.
- Logout di `AuthController` menghancurkan session tetapi tidak melakukan cookie invalidation eksplisit.

Dampak bisnis: perubahan data tidak sah melalui CSRF, endpoint role tertentu terbuka karena middleware salah ketik/tidak terdaftar, dan peningkatan risiko account abuse.

Effort: 2-4 hari.

Rekomendasi:

- Tolak middleware yang tidak dikenal dengan 500/403 saat boot.
- Tambah middleware role generic, misalnya `role:admin,operator`.
- Semua action create/update/delete web harus POST/PUT/DELETE + CSRF.
- Untuk API, pilih salah satu: session+CSRF untuk browser, atau token/Bearer stateless untuk external client.

Contoh role middleware:

```php
private function applyMiddleware(array $middlewares): bool
{
    foreach ($middlewares as $middleware) {
        if ($middleware === 'auth') {
            return $this->requireSession();
        }

        if (str_starts_with($middleware, 'role:')) {
            $roles = explode(',', substr($middleware, 5));
            return $this->requireRole($roles);
        }

        throw new RuntimeException("Unknown middleware: {$middleware}");
    }

    return true;
}
```

Contoh delete aman:

```php
public function delete($id)
{
    $this->checkRole(['admin', 'operator', 'petugas']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }

    $this->validateCsrfToken();
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if (!$id) {
        $_SESSION['error'] = 'ID laporan tidak valid';
        $this->redirect('laporan');
    }

    // ownership check tetap dipertahankan
}
```

### P1-05 Production Error Leakage

Evidence:

- `config/config.php:63` mengaktifkan `display_errors`.
- `config/database.php:19` menampilkan detail error koneksi database lewat `die(...)`.
- Banyak API response menggabungkan `$e->getMessage()`, misalnya `app/controllers/Api/UserController.php:53`, `app/controllers/Api/LaporanHamaController.php:54`, `app/controllers/Api/IrigasiController.php:54`.

Dampak bisnis: informasi schema, path filesystem, query, atau detail integrasi dapat bocor ke user/API client.

Effort: 1 hari.

Rekomendasi:

- Gunakan `APP_ENV`; `display_errors=0` di production.
- Log detail exception server-side, response client hanya error code/correlation id.
- Tambahkan error handler global.

Contoh:

```php
final class ApiErrors
{
    public static function internal(Throwable $e): void
    {
        $id = bin2hex(random_bytes(8));
        error_log("[{$id}] " . $e);

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error_id' => $id,
        ]);
        exit;
    }
}
```

### P1-06 Database Schema Belum Optimal untuk Query Utama

Evidence:

- `laporan_hama` memiliki index `user_id`, `master_opt_id`, `verified_by`, dan string wilayah, tetapi belum ada composite index untuk query paling sering: `status + created_at`, `user_id + status + created_at`, `tanggal + status`, dan ID wilayah (`kabupaten_id`, `kecamatan_id`, `desa_id`).
- `users.email` tidak unique, sedangkan controller mengecek uniqueness di aplikasi.
- `laporan_hama.master_opt_id` memakai `ON DELETE CASCADE`; menghapus master OPT akan menghapus laporan historis.
- Ada denormalisasi `kabupaten/kecamatan/desa` string sekaligus ID wilayah.

Dampak bisnis: dashboard/list laporan melambat saat data tumbuh, risiko duplikasi email, dan risiko hilangnya histori laporan saat master data dihapus.

Effort: 2-5 hari termasuk migration dan backfill.

Rekomendasi index:

```sql
ALTER TABLE laporan_hama
  ADD INDEX idx_lh_status_created (status, created_at),
  ADD INDEX idx_lh_user_status_created (user_id, status, created_at),
  ADD INDEX idx_lh_tanggal_status (tanggal, status),
  ADD INDEX idx_lh_wilayah_status (kabupaten_id, kecamatan_id, desa_id, status);

ALTER TABLE users
  ADD UNIQUE INDEX uq_users_email (email);

ALTER TABLE laporan_hama
  DROP FOREIGN KEY laporan_hama_ibfk_2,
  ADD CONSTRAINT laporan_hama_master_opt_fk
    FOREIGN KEY (master_opt_id) REFERENCES master_opt(id)
    ON DELETE SET NULL;
```

### P1-07 File/Session Based State Tidak Skalabel

Evidence:

- `Security::checkBruteForce()` menyimpan counter di `$_SESSION` (`app/core/Security.php:119-128`).
- `Security::checkRateLimit()` juga menyimpan rate counter di `$_SESSION` (`app/core/Security.php:147-162`).
- `Cache` memakai file serialize/unserialize (`app/core/Cache.php:44`, `75`, `103`, `122`).
- `OptPhotoUploader` membangun path rate limit menjadi `ROOT_PATH/storage/storage/...` (`app/helpers/OptPhotoUploader.php:626`, `642`, `672`).

Dampak bisnis: brute force dari session baru bisa lolos, rate limit tidak konsisten pada multi-server, file lock/I/O bottleneck, dan bug path membuat rate limit upload tidak bekerja sesuai niat.

Effort: 3-5 hari.

Rekomendasi:

- Pindahkan cache/rate limit/brute force ke Redis atau database table dengan TTL.
- Standarkan `RateLimiter::apply()` di route/API gateway.
- Perbaiki bug path upload rate limiter.

Contoh interface:

```php
interface RateLimitStore
{
    public function increment(string $key, int $ttlSeconds): int;
}

final class RedisRateLimitStore implements RateLimitStore
{
    public function __construct(private Redis $redis) {}

    public function increment(string $key, int $ttlSeconds): int
    {
        $count = $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, $ttlSeconds);
        }
        return $count;
    }
}
```

### P1-08 Upload API Tidak Memvalidasi MIME/Magic Byte

Evidence:

- API upload mengambil ekstensi dari nama file (`app/controllers/Api/LaporanHamaController.php:225`, `app/controllers/Api/IrigasiController.php:238`, `app/controllers/Api/OptController.php:358`).
- File langsung dipindah dengan `move_uploaded_file()` setelah cek ekstensi/size (`app/controllers/Api/LaporanHamaController.php:241`, `app/controllers/Api/IrigasiController.php:254`, `app/controllers/Api/OptController.php:374`).
- Web laporan punya validasi `finfo`, tetapi API tidak memakai helper yang sama.

Dampak bisnis: upload polyglot/malicious file lebih mudah, file besar/invalid bisa masuk storage publik, dan perilaku web/API berbeda.

Effort: 1-2 hari.

Rekomendasi:

- Pakai satu `UploadService` untuk web dan API.
- Validasi `finfo`, `getimagesize`, magic byte, size, extension dari MIME, dan re-encode gambar.
- Simpan di luar webroot bila akses harus authenticated.

Contoh:

```php
final class ImageUploadService
{
    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(array $file, string $dir): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload gagal');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::MIME_TO_EXT[$mime]) || getimagesize($file['tmp_name']) === false) {
            throw new InvalidArgumentException('File gambar tidak valid');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::MIME_TO_EXT[$mime];
        $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Gagal menyimpan upload');
        }

        return $name;
    }
}
```

## Frontend Audit

### Struktur Komponen

Masalah:

- View seperti `app/views/curah_hujan/index.php` sekitar 3.006 baris, `app/views/laporan/create.php` sekitar 1.890 baris, dan `app/views/laporan/index.php` sekitar 1.724 baris.
- Banyak CSS/JS inline: 104 `<script>` dan 38 `<style>` di view.
- Header layout memuat style dan modul navigasi wilayah yang seharusnya menjadi partial/component tersendiri.

Dampak bisnis: UI sulit diuji, perubahan kecil berisiko mematahkan halaman besar, onboarding developer lambat.

Rekomendasi:

- Ekstrak partial PHP untuk tabel, filter, modal, form section, dan alert.
- Ekstrak JS per fitur ke `public/js/modules/*`.
- Definisikan kontrak data per view lewat JSON `<script type="application/json">`.

Contoh:

```php
<script type="application/json" id="laporan-page-data">
<?= json_encode([
    'baseUrl' => BASE_URL,
    'csrfToken' => Security::generateCsrfToken(),
    'currentUser' => $currentUser,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
</script>
<script type="module" src="<?= BASE_URL ?>public/js/modules/laporan-create.js"></script>
```

### State Management

Masalah:

- State tersebar di global variable, `window.*`, `localStorage`, IndexedDB, dan service worker.
- `offline-laporan.js` menyimpan data di store `laporan_drafts`, sementara `public/sw.js` membaca store `submissions`; background sync tidak akan mengambil data yang sama.
- Draft laporan disimpan di `localStorage` 24 jam. Data laporan pertanian/lokasi dapat dianggap sensitif.

Dampak bisnis: offline submission dapat terlihat sukses di UI tetapi tidak tersinkron, data user tertinggal di browser bersama/shared device.

Rekomendasi:

- Samakan DB/store IndexedDB antara page script dan service worker.
- Tambahkan UI outbox untuk pending sync.
- Hindari `localStorage` untuk field sensitif; pakai IndexedDB dengan TTL dan clear eksplisit setelah submit.

### Rendering Performance

Masalah:

- Chart.js dimuat dua versi: v4 di header (`app/views/layouts/header.php:15`) dan v3 di footer (`app/views/layouts/footer.php:22`), serta halaman curah hujan memuat Chart.js lagi.
- Banyak CDN blocking tanpa bundling, SRI, atau self-host cache strategy.
- `mobile-enhancements.js` memasang banyak event listener global, `MutationObserver`, dan operasi `querySelectorAll` untuk semua halaman.
- Banyak penggunaan `innerHTML` untuk render data dinamis tanpa template sanitizer.
- Service worker cache static memasukkan halaman dinamis seperti `./dashboard` dan `./laporan` (`public/sw.js:10-12`) dan men-cache GET same-origin (`public/sw.js:101-105`).

Dampak bisnis: load awal lebih lambat, risiko stale HTML/session page, bug UI karena versi library konflik.

Rekomendasi:

- Muat Chart.js satu versi saja dan hanya di halaman yang membutuhkan.
- Split JS berdasarkan halaman.
- Gunakan delegated events secara selektif dan matikan debug `console.log` production.
- Service worker hanya cache asset immutable; data/API pakai network-first atau no-store.

Target metrik:

| Metrik | Sekarang | Target |
|---|---:|---:|
| Chart.js loads halaman dashboard | 2-3 versi/instance | 1 versi |
| Inline script block | 104 | < 25 setelah ekstraksi fase 1 |
| JS global mobile enhancer | 976 baris loaded semua halaman | loaded hanya mobile/layout pages yang butuh |
| Service worker dynamic page cache | Ya | Tidak cache authenticated HTML |

### Aksesibilitas

Masalah:

- Ada enhancement JS untuk skip link/ARIA, tetapi aksesibilitas dasar sebaiknya hadir di HTML awal, bukan setelah JS load.
- Banyak link `href="#"` dipakai sebagai control; seharusnya `button`.
- Icon-only control perlu `aria-label` server-side, bukan hanya heuristik JS.
- Toast/alert dinamis memakai `innerHTML`; perlu `role="status"`/`aria-live`.

Rekomendasi:

- Tambahkan `main id="main-content"` dan skip link di layout PHP.
- Ubah action non-navigasi menjadi `<button type="button">`.
- Tambahkan label eksplisit untuk tombol icon.

### Responsivitas UI

Masalah:

- Ada CSS khusus device `400x926` dan manipulasi style inline oleh JS. Ini rawan gagal di device lain.
- Banyak tabel besar bergantung pada `.table-responsive` dan script stacking, bukan desain data mobile yang konsisten.
- `preventZoom()` mencoba mencegah double-tap zoom; harus hati-hati karena dapat mengganggu aksesibilitas.

Rekomendasi:

- Gunakan breakpoint berbasis layout, bukan resolusi device spesifik.
- Untuk tabel padat, sediakan mode list/detail mobile yang server-rendered.
- Jangan mematikan zoom; optimalkan ukuran input/font.

## Backend Audit

### API Design

Masalah:

- API memakai session web sebagai auth utama. Ini cocok untuk AJAX internal, tetapi lemah untuk external/mobile API.
- Tidak ada versioning (`/api/v1`), OpenAPI, atau schema response stabil.
- `BaseApiController::getRequestData()` mengembalikan `$_REQUEST`, yang mencampur GET/POST/cookie.
- Response error pakai `JSON_PRETTY_PRINT`, membesar payload tanpa manfaat production.

Rekomendasi:

- Pisahkan Internal AJAX API dan External API.
- Gunakan `/api/v1`, Bearer token/API key hashed untuk external.
- Gunakan request validator per endpoint.
- Response error generik + correlation id.

### Authentication/Authorization

Masalah:

- Session cookie tidak dikonfigurasi dengan `secure`, `httponly`, `samesite`, `lifetime`, `strict_mode`.
- Middleware role tidak generic dan tidak fail-closed untuk middleware tidak dikenal.
- Login brute force session-based.
- Beberapa controller mendefinisikan `checkAuth()` sendiri, sehingga behavior security tidak seragam.

Rekomendasi:

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();
```

### Error Handling dan Logging

Masalah:

- `error_log()` tersebar tanpa struktur.
- `activity_log` menyimpan IP/user-agent tanpa retention policy.
- Log file runtime ter-track.

Rekomendasi:

- Buat `Logger` adapter minimal dengan level, context, request id.
- Audit log security dan activity log dipisah.
- Tambahkan retention dan redaction PII.

### Skalabilitas

Masalah:

- Scraper/import dijalankan dari request controller; rawan timeout.
- File cache dan session rate limiter tidak cocok untuk multi-worker/load-balanced.
- Tidak ada job queue.

Rekomendasi:

- Pindahkan scraper/import berat ke queue/cron worker.
- Redis untuk cache/rate-limit/session.
- Tambahkan health check yang memeriksa DB, cache, storage writable, dan route registry.

## Metrik Performa Sebelum/Sesudah

Karena audit dilakukan statis dan test HTTP tidak memiliki server baseline yang stabil, metrik "sesudah" di bawah adalah target implementasi yang harus divalidasi dengan load test (`ab`, `wrk`, k6, atau JMeter) setelah perbaikan.

| Area | Sebelum | Sesudah target | Cara validasi |
|---|---:|---:|---|
| API route health | 15/85 route invalid | 0 route invalid | smoke test route registry |
| API model contract | 49 call site berpotensi fatal | 0 missing method | static symbol test + API integration |
| Laporan list query | full fetch `getAllWithDetails()` tanpa pagination | paginated 20-50 row | DB query log, page TTFB |
| Query laporan hama | index belum composite untuk status/date/wilayah ID | composite index aktif | `EXPLAIN` query dashboard/list |
| Chart.js | multi-version load | single version | browser network panel |
| View JS inline | 104 block | < 25 block fase 1 | static count |
| State cache | file/session | Redis/shared | concurrency test 10-50 workers |
| Integration tests | 6/13 pass | 13/13 pass | test runner/CI |

Contoh target load test awal:

| Endpoint | Target P95 |
|---|---:|
| `GET /dashboard` authenticated | < 500 ms pada dataset dev |
| `GET /laporan?status=Submitted` | < 400 ms dengan pagination |
| `GET /api/wilayah/kecamatan/{id}` | < 150 ms setelah cache |
| `POST /laporan/create` tanpa upload | < 700 ms |
| `POST /laporan/create` dengan upload 2 MB | < 2.500 ms atau async processing |

## Test Plan

### Unit Test

| Modul | Test |
|---|---|
| `Model` | sanitasi identifier, fillable whitelist, update/delete tanpa WHERE invalid, invalid column ditolak |
| `QueryBuilder` | where/order/limit sanitization, join condition allowlist, empty update/delete exception |
| `Security` | CSRF valid/expired, session cookie config, unknown middleware fail-closed |
| `RateLimiter` | shared store increment, TTL reset, concurrent increment |
| `UploadService` | MIME mismatch, extension mismatch, oversize, corrupt image, success path |
| `User` | password validation, unique email, role validation, auth inactive user |

### Integration Test

| Area | Test |
|---|---|
| Route registry | semua handler API ada dan method callable |
| API user/laporan/OPT/irigasi | index/show/store/update/delete happy path + forbidden path |
| CSRF | POST/DELETE tanpa token ditolak; token invalid ditolak |
| Authorization | petugas tidak bisa akses data orang lain; operator/admin sesuai role |
| Database | migrations idempotent, foreign key behavior, `EXPLAIN` memakai index target |
| Upload | API dan web upload memakai validasi yang sama |
| PWA offline | draft disimpan, background sync membaca store yang sama, retry dan outbox terlihat |

### Frontend/A11y/Responsive Test

| Area | Test |
|---|---|
| Rendering | tidak ada duplicate library, chart render satu kali, no console error |
| A11y | axe-core/Playwright: label tombol, heading order, focus trap modal, skip link |
| Responsive | 360x740, 400x926, 768x1024, 1366x768, tabel tidak overlap |
| Offline | service worker tidak menyajikan HTML authenticated yang stale |
| State | draft hilang setelah submit, pending sync muncul di outbox |

## Roadmap Implementasi

### Minggu 1: Stabilitas dan Stop-the-Bleed

1. Hapus artefak sensitif dari tracking, rotate kredensial.
2. Matikan `display_errors` production dan implement error response generik.
3. Perbaiki route API missing controller atau disable route dengan 501.
4. Tambahkan route registry smoke test.
5. Terapkan CSRF untuk semua destructive web action.

### Minggu 2: Kontrak API dan Model

1. Tambahkan method model yang dipakai API atau sesuaikan controller ke method existing.
2. Terapkan `$fillable` dan identifier sanitizer di `Model`.
3. Pisahkan Internal AJAX API vs External API.
4. Tambahkan integration test untuk endpoint kritis.

### Minggu 3-4: Database dan Performance

1. Tambah composite index laporan hama.
2. Ubah list laporan/dashboard ke pagination dan query aggregate terukur.
3. Tambah `EXPLAIN` check untuk query utama.
4. Refactor service worker supaya tidak cache authenticated HTML.

### Bulan 2: Maintainability dan Skalabilitas

1. Ekstrak `LaporanService`, `UploadService`, `NotificationService`, `AuditLogger`.
2. Migrasi cache/rate limit ke Redis atau database TTL table.
3. Ekstrak JS/CSS inline ke module per halaman dan hapus duplicate CDN.
4. Setup CI: lint PHP, route smoke, unit tests, integration smoke, secret scan.

## Quick Wins

| Aksi | Effort | Dampak |
|---|---:|---|
| `git rm --cached` dump/cookie/log + rotate secrets | 0.5-1 hari | sangat tinggi |
| Disable invalid API route atau stub 501 | 0.5 hari | tinggi |
| `display_errors=0` production | 1 jam | tinggi |
| Fix `LaporanController::delete()` CSRF | 2 jam | tinggi |
| Hapus duplicate Chart.js | 1 jam | sedang |
| Perbaiki IndexedDB store mismatch offline sync | 0.5 hari | sedang |
| Tambah route registry test | 0.5 hari | tinggi |

## Kesimpulan

JAGAPADI belum perlu dipecah menjadi microservice. Masalah utama saat ini adalah kontrak monolit yang belum terkunci: route API tidak sinkron, model tidak punya interface konsisten, security control belum fail-closed, dan frontend terlalu banyak logika inline. Roadmap yang paling efektif adalah menstabilkan monolit, menambah test otomatis, membersihkan data sensitif, lalu baru memecah service internal berdasarkan domain jika traffic dan tim sudah membutuhkan.
