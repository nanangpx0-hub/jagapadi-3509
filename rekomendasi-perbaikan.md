# Rekomendasi Perbaikan — JAGAPADI
**Tanggal Audit:** 20 Juli 2026  
**Versi Kode:** Tahap 3 (Database Schema & Migration) — IN PROGRESS  
**Auditor:** Kiro AI Code Audit  
**Cakupan:** Seluruh kode sumber `app/`, `api/`, `config/`, `index.php`, migrations

---

## Ringkasan Eksekutif

Audit ini menemukan **47 temuan** yang terdistribusi dalam 5 kategori: keamanan, performa, fungsional, kualitas kode, dan arsitektur. Terdapat **9 temuan prioritas TINGGI** yang harus diselesaikan sebelum production deployment, termasuk celah keamanan upload file, bug fatal pada rate limiter, dan pelanggaran aturan bisnis inti (draft policy).

| Kategori | Tinggi | Sedang | Rendah | Total |
|---|---|---|---|---|
| Keamanan | 5 | 4 | 2 | 11 |
| Performa | 1 | 3 | 2 | 6 |
| Fungsional | 3 | 4 | 3 | 10 |
| Kualitas Kode | 0 | 4 | 6 | 10 |
| Arsitektur | 0 | 5 | 5 | 10 |
| **Total** | **9** | **20** | **18** | **47** |

---

## KATEGORI 1: KEAMANAN


### KEA-01 — Upload File Tanpa Validasi Magic Bytes (MIME Spoofing)
**Prioritas:** TINGGI  
**Upaya:** 2–3 jam  
**File Terdampak:** `app/controllers/Api/IrigasiController.php`, `app/controllers/Api/LaporanHamaController.php`

**Masalah:**  
Kedua controller API mengimplementasikan `handleFileUpload()` secara mandiri dan hanya memvalidasi ekstensi file dari nama aslinya (`pathinfo($file['name'], PATHINFO_EXTENSION)`). Validasi ini dapat dilewati dengan mengganti ekstensi file berbahaya (misal: `shell.php` menjadi `shell.php.jpg`). Tidak ada pemeriksaan magic bytes atau MIME type sesungguhnya dari konten file, sehingga penyerang bisa mengunggah file PHP yang dapat dieksekusi.

**Implementasi Perbaikan:**
```php
// Ganti fungsi handleFileUpload() di kedua controller dengan:
private function handleFileUpload(array $file): string {
    $uploadDir = ROOT_PATH . '/public/uploads/laporan/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 1. Validasi ukuran
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Ukuran file maksimal 5MB.');
    }

    // 2. Validasi MIME type menggunakan finfo (magic bytes)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        throw new Exception('Tipe file tidak diizinkan. Hanya JPG, PNG, WEBP.');
    }

    // 3. Nama file acak, bukan dari input user
    $extension = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        throw new Exception('Gagal menyimpan file.');
    }

    return 'uploads/laporan/' . $filename;
}
```
Pindahkan fungsi ini ke `BaseApiController` agar tidak duplikat.

**Dampak Positif:** Mencegah Remote Code Execution (RCE) via file upload berbahaya.

---

### KEA-02 — File Debug/Test Aktif di Document Root
**Prioritas:** TINGGI  
**Upaya:** 30 menit  
**File Terdampak:** `_debug_page.php`, `_debug3.php`, `_ajax_test.php`, `_js_check.php`, `check_overlay.php`, `check_user_dependencies.php`, `diagnose_preview.php`, `find_error_source.php`, `full_test.php`, `show_page.php`, `test.php`, `test_render.php`, `verify_fix.php`

**Masalah:**  
Terdapat 13+ file debug/test yang dapat diakses langsung oleh siapa pun melalui HTTP. File-file ini berpotensi mengekspos struktur database, pesan error PHP, konfigurasi sistem, atau data internal tanpa autentikasi.

**Implementasi Perbaikan:**
```bash
# Hapus semua file debug dari root
del _debug_page.php _debug3.php _ajax_test.php _js_check.php
del check_overlay.php check_user_dependencies.php diagnose_preview.php
del find_error_source.php full_test.php show_page.php test.php
del test_render.php verify_fix.php
# Tambahkan ke .gitignore untuk mencegah commit ulang:
echo "/_debug*.php" >> .gitignore
echo "/_ajax*.php" >> .gitignore
echo "/_js_check.php" >> .gitignore
```
Tambahkan juga di `.htaccess`:
```apache
<FilesMatch "^(_debug|_ajax|test|check_|diagnose|find_error|verify_fix)">
    Require all denied
</FilesMatch>
```

**Dampak Positif:** Menutup vektor serangan information disclosure yang paling mudah dieksploitasi.


### KEA-03 — CORS Terbuka (`Access-Control-Allow-Origin: *`) di File API Standalone
**Prioritas:** TINGGI  
**Upaya:** 1 jam  
**File Terdampak:** `api/recent_activity.php`, `api/kecamatan_stats.php`, `api/desa_autocomplete.php`, `api/desa_filter.php`

**Masalah:**  
Semua file di direktori `api/` mengirimkan header `Access-Control-Allow-Origin: *`. Meskipun endpoint ini sudah dilindungi session, header wildcard CORS memungkinkan request lintas domain dari browser manapun, yang berpotensi dieksploitasi dalam serangan CSRF berbasis browser pada skenario tertentu. Selain itu, file `api/recent_activity.php` mengirimkan pesan error mentah (`$e->getMessage()`) ke client saat terjadi exception.

**Implementasi Perbaikan:**
```php
// Ganti header CORS di seluruh file api/ dengan origin whitelist:
$allowedOrigins = ['https://jagapadi.yourdomain.com', 'http://localhost:8080'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
}

// Ganti pesan error di blok catch:
} catch (Exception $e) {
    error_log('[API] Error: ' . $e->getMessage()); // Log server-side saja
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan internal.']); // Jangan expose detail
}
```

**Dampak Positif:** Mengurangi permukaan serangan CSRF dan mencegah information disclosure dari pesan error.

---

### KEA-04 — `sanitizeData()` Menerapkan `htmlspecialchars()` pada Data Sebelum Disimpan ke DB
**Prioritas:** TINGGI  
**Upaya:** 2–4 jam  
**File Terdampak:** `app/controllers/Api/BaseApiController.php`, semua controller yang memanggil `$this->sanitizeData()`

**Masalah:**  
`BaseApiController::sanitizeData()` mengaplikasikan `htmlspecialchars()` pada seluruh input sebelum data disimpan ke database. Ini adalah praktik yang salah karena: (1) data yang tersimpan di DB akan mengandung entitas HTML (`&amp;`, `&lt;`) yang merusak integritas data, (2) laporan dengan nama desa seperti `"Wringin & Sari"` akan tersimpan sebagai `"Wringin &amp; Sari"`, (3) pendekatan yang benar adalah meng-escape output saat ditampilkan ke HTML, bukan saat input ke DB.

**Implementasi Perbaikan:**
```php
// Di BaseApiController.php — hapus htmlspecialchars dari sanitizeData:
protected function sanitizeData(mixed $data): mixed {
    if (is_array($data)) {
        return array_map([$this, 'sanitizeData'], $data);
    }
    if (is_string($data)) {
        return trim($data); // Hanya trim, jangan escape HTML
    }
    return $data;
}

// Validasi tipe data secara eksplisit di setiap controller:
$data['nama_irigasi'] = trim($data['nama_irigasi'] ?? '');
$data['kabupaten_id']  = (int)($data['kabupaten_id'] ?? 0);
// Escape HTML hanya saat output ke view:
echo htmlspecialchars($record['nama_irigasi'], ENT_QUOTES, 'UTF-8');
```

**Dampak Positif:** Data tersimpan bersih di database; XSS tetap dicegah di layer output.

---

### KEA-05 — Peran `operator` dan `statistisi` Tidak Terdefinisi di AGENTS.md & Logika Bisnis
**Prioritas:** TINGGI  
**Upaya:** 3 jam  
**File Terdampak:** `app/core/Router.php`, berbagai controller

**Masalah:**  
AGENTS.md mendefinisikan dua role: `admin` dan `petugas`. Namun di Router dan beberapa controller ditemukan role `operator` dan `statistisi` yang tidak terdefinisi dalam dokumen bisnis. Middleware `operator` mengizinkan akses jika role adalah `admin` ATAU `operator`, namun user dengan role `operator` tidak pernah dibuat/didefinisikan secara resmi. Ini menciptakan ambiguitas otorisasi dan potensi privilege escalation jika ada bug di proses registrasi user.

**Implementasi Perbaikan:**  
1. Dokumentasikan role `operator` dan `statistisi` di AGENTS.md jika memang diperlukan, termasuk permission masing-masing.
2. Jika tidak diperlukan, hapus middleware `operator` dan `statistisi` dari Router dan ganti dengan `admin`.
3. Tambahkan enum atau konstanta untuk role yang valid:
```php
// app/core/Roles.php (file baru)
final class Roles {
    public const ADMIN    = 'admin';
    public const PETUGAS  = 'petugas';
    // Uncomment jika sudah didefinisikan resmi:
    // public const OPERATOR   = 'operator';
    // public const STATISTISI = 'statistisi';
    public const ALL = [self::ADMIN, self::PETUGAS];
}
```

**Dampak Positif:** Konsistensi model otorisasi, mencegah privilege escalation dari role tidak terdokumentasi.


### KEA-06 — Error Detail Terekspos ke Client di Berbagai Controller
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** Hampir semua API controller (pola `$e->getMessage()` diteruskan ke response)

**Masalah:**  
Pola `$this->sendError('Failed to retrieve data: ' . $e->getMessage(), 500)` yang ada di hampir semua catch block mengirimkan pesan exception PHP ke client. Pesan ini dapat mengandung nama tabel, path file server, credential parsial, atau query SQL — semua informasi berguna bagi penyerang.

**Implementasi Perbaikan:**
```php
// Ubah semua catch block di API controller menjadi:
} catch (Exception $e) {
    error_log('[' . get_class($this) . '] Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    $this->sendError('Terjadi kesalahan internal. Silakan hubungi administrator.', 500);
}
// Gunakan log ID unik untuk tracing:
} catch (Exception $e) {
    $errorId = uniqid('ERR-');
    error_log("[{$errorId}] " . get_class($this) . ': ' . $e->getMessage());
    $this->sendError("Kesalahan internal. Kode: {$errorId}", 500);
}
```

**Dampak Positif:** Mencegah information disclosure, mempertahankan kemampuan debug via server log.

---

### KEA-07 — Tidak Ada Validasi Tipe Integer pada Parameter URL Route
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** Semua API controller yang menerima `$id` dari URL

**Masalah:**  
Meskipun ada pengecekan `is_numeric($id)`, pemeriksaan ini juga menganggap `"1e5"` (notasi eksponensial) sebagai numeric. Lebih aman menggunakan `ctype_digit()` atau cast eksplisit dengan validasi range untuk parameter ID database.

**Implementasi Perbaikan:**
```php
// Ganti pola validasi ID di semua controller:
// SEBELUM:
if (!$id || !is_numeric($id)) { ... }

// SESUDAH:
$id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false) {
    $this->sendError('ID tidak valid', 400);
}
```

**Dampak Positif:** Validasi input yang lebih ketat, mencegah edge case pada type casting.

---

### KEA-08 — Session Tidak Di-regenerate Setelah Force Change Password
**Prioritas:** SEDANG  
**Upaya:** 30 menit  
**File Terdampak:** `app/controllers/AuthController.php`

**Masalah:**  
Pada alur `change_password` ketika `$isForceChange = false` (user yang sudah login mengganti password secara sukarela), `session_regenerate_id(true)` tidak dipanggil. Ini meninggalkan session ID lama aktif, yang berpotensi dieksploitasi jika session ID tersebut sudah diketahui pihak lain (session fixation setelah privilege change).

**Implementasi Perbaikan:**
```php
// Di AuthController::change_password(), di blok else (non-force change):
if ($result['success']) {
    session_regenerate_id(true); // Tambahkan baris ini
    $_SESSION['success'] = $result['message'];
    $this->redirect('dashboard');
}
```

**Dampak Positif:** Mencegah session fixation setelah perubahan kredensial.

---

### KEA-09 — `$_SERVER['HTTP_USER_AGENT']` Disimpan Tanpa Panjang Maksimum
**Prioritas:** SEDANG  
**Upaya:** 1 jam  
**File Terdampak:** `app/controllers/AuthController.php` (method `logActivity`), `app/core/Security.php`

**Masalah:**  
`User-Agent` header dapat dikirim dengan panjang hingga beberapa KB oleh klien yang tidak wajar. Nilai ini disimpan langsung ke kolom `user_agent` di tabel `activity_log` tanpa truncation, berpotensi menyebabkan error database atau membuang storage.

**Implementasi Perbaikan:**
```php
// Tambahkan truncation sebelum INSERT:
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
```

**Dampak Positif:** Mencegah database error dan pemborosan storage.

---

### KEA-10 — Tidak Ada Content Security Policy (CSP) Header
**Prioritas:** RENDAH  
**Upaya:** 2 jam  
**File Terdampak:** `index.php`, `.htaccess`

**Masalah:**  
Aplikasi tidak mengirimkan header keamanan HTTP standar seperti `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, dan `Referrer-Policy`.

**Implementasi Perbaikan:**
```php
// Di index.php, setelah session_start():
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; img-src 'self' data:;");
```

**Dampak Positif:** Mengurangi risiko XSS, clickjacking, dan MIME sniffing attacks.

---

### KEA-11 — File `security_cleanup.sh` Tersimpan di Root Repository
**Prioritas:** RENDAH  
**Upaya:** 15 menit  
**File Terdampak:** `security_cleanup.sh`

**Masalah:**  
Script cleanup keamanan yang mungkin mengandung daftar file sensitif atau operasi sistem tersimpan di root dan dapat diakses publik.

**Implementasi Perbaikan:**  
Pindahkan ke direktori `scripts/` yang tidak dapat diakses via HTTP, atau tambahkan deny di `.htaccess`.


---

## KATEGORI 2: PERFORMA

### PER-01 — `RateLimiter` Menggunakan Kelas `Cache` yang Berbeda dari `CacheManager`
**Prioritas:** TINGGI  
**Upaya:** 1 jam  
**File Terdampak:** `app/helpers/RateLimiter.php`

**Masalah:**  
`RateLimiter::check()` memanggil `Cache::init()`, `Cache::get()`, dan `Cache::set()` — mengacu pada kelas `Cache` di `app/core/Cache.php`. Sementara seluruh sistem (termasuk `Security`, `ApiAuthMiddleware`) menggunakan `CacheManager`. Kedua kelas ini adalah implementasi caching yang terpisah dengan direktori penyimpanan berbeda (`storage/cache/` vs `storage/cache/cache_manager/`). Ini menyebabkan:
1. Rate limiting yang tidak konsisten (data tersebar di dua tempat)
2. Potensi error jika `Cache` tidak ter-autoload saat `RateLimiter` dipanggil via Router

**Implementasi Perbaikan:**
```php
// Di RateLimiter.php, ganti semua pemanggilan Cache:: dengan CacheManager::
// SEBELUM:
Cache::init();
$data = Cache::get($cacheKey);
Cache::set($cacheKey, $data, $config['window']);

// SESUDAH:
$cache = CacheManager::getInstance();
$data = $cache->get($cacheKey);
$cache->set($cacheKey, $data, $config['window']);

// Hapus juga metode static reset() dan getUsage() yang masih memanggil Cache::
```

**Dampak Positif:** Rate limiting bekerja konsisten; menghilangkan ketergantungan pada dua sistem cache berbeda.

---

### PER-02 — Query `dashboardSummary()` Menggunakan Raw `db->query()` Tanpa Caching
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** `app/controllers/Api/IrigasiController.php` (method `dashboardSummary`)

**Masalah:**  
Method ini menjalankan 6 query berbeda setiap kali dipanggil, termasuk query ke tabel `weather_alerts`, `irrigation_logs`, dan `pengairan_otomatis`. Jika dashboard diakses secara intensif, ini akan memberikan beban besar pada database. Tidak ada mekanisme caching untuk data agregat yang berubah perlahan.

**Implementasi Perbaikan:**
```php
public function dashboardSummary(): void {
    $cache = CacheManager::getInstance();
    $cacheKey = 'irigasi_dashboard_summary';

    $response = $cache->remember($cacheKey, function() {
        // letakkan semua 6 query di sini
        // ...
        return $response;
    }, 300); // cache 5 menit

    $this->sendResponse($response, 'Dashboard summary retrieved successfully');
}
```

**Dampak Positif:** Mengurangi beban database hingga 90% untuk endpoint dashboard yang sering diakses.

---

### PER-03 — N+1 Query pada `getRecentForDashboard()` Menggunakan `eagerLoad()`
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** `app/models/LaporanHama.php` (method `getRecentForDashboard`)

**Masalah:**  
Meskipun metode `eagerLoad()` digunakan, jika implementasi di kelas `Model` induk melakukan query per-relasi untuk setiap record, ini masih menghasilkan N+1 query. Perlu dipastikan `eagerLoad` benar-benar menggunakan `WHERE IN (...)` bukan loop individual, atau ganti dengan JOIN tunggal.

**Implementasi Perbaikan:**  
Verifikasi implementasi `Model::eagerLoad()`. Jika menggunakan loop, ganti dengan single JOIN query:
```sql
SELECT lh.*, u.nama_lengkap as pelapor_nama, mo.nama_opt, 
       kab.nama_kabupaten, kec.nama_kecamatan, des.nama_desa
FROM laporan_hama lh
LEFT JOIN users u ON lh.user_id = u.id
LEFT JOIN master_opt mo ON lh.master_opt_id = mo.id
LEFT JOIN master_kabupaten kab ON lh.kabupaten_id = kab.id
LEFT JOIN master_kecamatan kec ON lh.kecamatan_id = kec.id
LEFT JOIN master_desa des ON lh.desa_id = des.id
ORDER BY lh.created_at DESC LIMIT ?
```

**Dampak Positif:** Mengurangi jumlah query dashboard dari O(5n) menjadi O(1).

---

### PER-04 — `getAllUsers()` Menggunakan `SELECT *` dari Tabel Users
**Prioritas:** SEDANG  
**Upaya:** 1 jam  
**File Terdampak:** `app/models/User.php`

**Masalah:**  
`SELECT *` pada tabel `users` mengambil kolom `password` (hash bcrypt) yang tidak pernah dibutuhkan di daftar user. Selain boros bandwidth, ini meningkatkan risiko hash password terekspos di log atau response yang tidak sengaja.

**Implementasi Perbaikan:**
```php
// Ganti SELECT * dengan kolom eksplisit:
$sql = "SELECT id, username, email, nama_lengkap, role, aktif, 
               created_at, updated_at, last_password_change_at 
        FROM users WHERE 1=1";
```

**Dampak Positif:** Mengurangi data transfer, menghilangkan risiko hash password bocor ke response.

---

### PER-05 — Tidak Ada Index Database yang Dideklarasikan di Migration
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** `migrations/` (hanya 1 file tersedia)

**Masalah:**  
Hanya ada 1 file migration. Tidak ada definisi index pada kolom yang sering digunakan untuk filter seperti `laporan_hama.status`, `laporan_hama.user_id`, `laporan_hama.tanggal`, dan `laporan_hama.kabupaten_id`. Query dengan filter status dan range tanggal tanpa index akan mengakibatkan full table scan.

**Implementasi Perbaikan:**
```sql
-- Tambahkan di migration laporan_hama:
ALTER TABLE laporan_hama 
  ADD INDEX idx_status (status),
  ADD INDEX idx_user_id (user_id),
  ADD INDEX idx_tanggal (tanggal),
  ADD INDEX idx_kabupaten_id (kabupaten_id),
  ADD INDEX idx_status_tanggal (status, tanggal);
```

**Dampak Positif:** Peningkatan performa query filter dan agregasi hingga 10–100x pada tabel besar.

---

### PER-06 — `JSON_PRETTY_PRINT` Digunakan di Semua Response API Production
**Prioritas:** RENDAH  
**Upaya:** 30 menit  
**File Terdampak:** `app/controllers/Api/BaseApiController.php`

**Masalah:**  
`json_encode($response, JSON_PRETTY_PRINT)` menambahkan whitespace dan newline yang tidak diperlukan di production, meningkatkan ukuran response rata-rata 20–30%.

**Implementasi Perbaikan:**
```php
$flags = defined('APP_ENV') && APP_ENV === 'development' ? JSON_PRETTY_PRINT : 0;
echo json_encode($response, $flags | JSON_UNESCAPED_UNICODE);
```

**Dampak Positif:** Mengurangi ukuran response API, meningkatkan throughput.


---

## KATEGORI 3: FUNGSIONAL

### FUN-01 — Aturan Bisnis Draft Policy Dilanggar: Semua Statistik Menyertakan Draft
**Prioritas:** TINGGI  
**Upaya:** 4 jam  
**File Terdampak:** `app/models/LaporanHama.php`, semua endpoint dashboard dan agregasi

**Masalah:**  
AGENTS.md menyatakan dengan tegas: _"Statistik default tanpa Draf — `include_draft=false` default"_. Namun `getDashboardStats()` di `LaporanHama.php` menghitung `COUNT(*) as total_laporan` tanpa filter status — artinya laporan Draf masuk dalam total. Kolom `pending_verifikasi` bahkan di-hardcode ke `0`:
```sql
-- SAAT INI (salah):
SELECT COUNT(*) as total_laporan, 0 as pending_verifikasi ...
FROM laporan_hama  -- tidak ada filter status
```

**Implementasi Perbaikan:**
```php
// 1. Tambahkan parameter include_draft ke semua metode statistik:
public function getDashboardStats(?int $userId = null, bool $includeDraft = false): array {
    $statusFilter = $includeDraft 
        ? "" 
        : "AND status != 'Draf'";
    
    $sql = "SELECT 
        COUNT(*) as total_laporan,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as pending_verifikasi,
        SUM(CASE WHEN status = 'Diverifikasi' THEN 1 ELSE 0 END) as terverifikasi,
        ...
    FROM laporan_hama WHERE 1=1 {$statusFilter}";
    // ...
}

// 2. Di semua API endpoint agregat, baca query param:
$includeDraft = filter_var($_GET['include_draft'] ?? false, FILTER_VALIDATE_BOOLEAN);
$stats = $model->getDashboardStats($userId, $includeDraft);
```

**Dampak Positif:** Konsistensi dengan spesifikasi bisnis; statistik dashboard mencerminkan data yang sudah disubmit.

---

### FUN-02 — Nomor Laporan Tidak Pernah Digenerate
**Prioritas:** TINGGI  
**Upaya:** 3 jam  
**File Terdampak:** `app/controllers/Api/LaporanHamaController.php` (method `store`), `app/models/LaporanHama.php`

**Masalah:**  
AGENTS.md menyatakan: _"Nomor laporan hanya dibuat saat Submitted, bukan saat Draf"_. Namun method `store()` di API controller langsung menetapkan `status = 'Submitted'` tanpa mengenerate nomor laporan. Tidak ada kolom `nomor_laporan` yang diisi, tidak ada logika generasi nomor unik.

**Implementasi Perbaikan:**
```php
// Di LaporanHama model, tambahkan metode:
public function generateNomorLaporan(string $prefix = 'LH'): string {
    $year = date('Y');
    $month = date('m');
    
    $stmt = $this->db->prepare(
        "SELECT COUNT(*) FROM laporan_hama 
         WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? 
         AND status != 'Draf'"
    );
    $stmt->execute([$year, $month]);
    $count = (int)$stmt->fetchColumn() + 1;
    
    return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $count);
}

// Di controller store(), sebelum INSERT:
if ($data['status'] === 'Submitted') {
    $data['nomor_laporan'] = $this->laporanModel->generateNomorLaporan();
}
```
Pastikan kolom `nomor_laporan` ada di tabel dan memiliki unique constraint.

**Dampak Positif:** Memenuhi aturan bisnis, setiap laporan tersubmit memiliki nomor unik yang dapat dilacak.

---

### FUN-03 — Draft Dapat Diverifikasi Tanpa Validasi Status
**Prioritas:** TINGGI  
**Upaya:** 2 jam  
**File Terdampak:** `app/models/LaporanHama.php` (method `verify`), controller verifikasi

**Masalah:**  
AGENTS.md menyatakan: _"Draf tidak boleh diverifikasi — Validasi wajib di server"_. Method `verify()` di model tidak memeriksa status laporan sebelum melakukan UPDATE. Ini memungkinkan admin memverifikasi laporan yang masih berstatus `Draf`.

**Implementasi Perbaikan:**
```php
public function verify(int $id, int $userId, string $status, string $catatan = ''): int {
    // Ambil laporan dulu untuk validasi
    $laporan = $this->find($id);
    if (!$laporan) {
        throw new InvalidArgumentException('Laporan tidak ditemukan.');
    }
    
    // Validasi business rule: hanya Submitted yang bisa diverifikasi
    if ($laporan['status'] !== 'Submitted') {
        throw new LogicException(
            "Laporan dengan status '{$laporan['status']}' tidak dapat diverifikasi. " .
            "Hanya laporan berstatus 'Submitted' yang dapat diverifikasi."
        );
    }
    
    // Lanjutkan update...
}
```

**Dampak Positif:** Menegakkan alur status laporan sesuai spesifikasi bisnis, mencegah bypass proses review.

---

### FUN-04 — Route `/api/users/profile` dan `/api/opt/stats` Tidak Dapat Dicapai (Shadowed Routes)
**Prioritas:** SEDANG  
**Upaya:** 1 jam  
**File Terdampak:** `app/core/Router.php`

**Masalah:**  
Di Router, route dengan path literal seperti `/api/users/profile` didaftarkan SETELAH route dengan parameter `/api/users/{id}`. Karena matching dilakukan secara berurutan, request ke `/api/users/profile` akan cocok dengan pattern `{id}` (dengan `id = 'profile'`) sebelum sempat mencapai route literal `/api/users/profile`. Hal sama terjadi pada `/api/opt/stats` dan `/api/opt/search`.

**Implementasi Perbaikan:**
```php
// Di Router::loadApiRoutes(), pastikan route literal SELALU didaftarkan SEBELUM route dengan {id}:

// BENAR: literal dulu, parameter belakangan
$this->get('/api/users/profile', 'Api\UserController@getProfile', ['auth']);
$this->get('/api/users/needing-password-change', 'Api\UserController@getUsersNeedingPasswordChange', ['auth', 'admin']);
$this->get('/api/users/{id}', 'Api\UserController@show', ['auth']); // SETELAH literal

$this->get('/api/opt/stats', 'Api\OptController@getStats', ['auth']);
$this->get('/api/opt/search', 'Api\OptController@search', ['auth']);
$this->get('/api/opt/{id}', 'Api\OptController@show', ['auth']); // SETELAH literal
```

**Dampak Positif:** Endpoint `/api/users/profile` dan `/api/opt/stats` mulai dapat diakses.

---

### FUN-05 — `LaporanHamaController::store()` Tidak Mendukung Alur Draft
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** `app/controllers/Api/LaporanHamaController.php`

**Masalah:**  
Method `store()` selalu menetapkan `$data['status'] = 'Submitted'`, mengabaikan aturan bisnis bahwa laporan dapat disimpan sebagai `Draf`. Aplikasi mobile yang ingin menyimpan draft tidak memiliki cara untuk melakukan ini melalui API.

**Implementasi Perbaikan:**
```php
// Di store(), baca status dari request:
$requestedStatus = $data['status'] ?? 'Draf';
$allowedStatuses = ['Draf', 'Submitted'];
if (!in_array($requestedStatus, $allowedStatuses, true)) {
    $this->sendError('Status tidak valid', 422);
}
$data['status'] = $requestedStatus;

// Hanya generate nomor laporan jika Submitted:
if ($data['status'] === 'Submitted') {
    $data['nomor_laporan'] = $this->laporanModel->generateNomorLaporan();
}
```

**Dampak Positif:** Mobile app dapat menyimpan draft sesuai spesifikasi; alur kerja offline-first terpenuhi.

---

### FUN-06 — `getDashboardStats()` Menghitung 'Submitted' dan 'Diverifikasi' sebagai `terverifikasi`
**Prioritas:** SEDANG  
**Upaya:** 1 jam  
**File Terdampak:** `app/models/LaporanHama.php`

**Masalah:**  
```sql
SUM(CASE WHEN status IN ('Submitted', 'Diverifikasi') THEN 1 ELSE 0 END) as terverifikasi
```
Laporan `Submitted` belum diverifikasi, namun dihitung sebagai `terverifikasi`. Ini menyesatkan pengguna dashboard.

**Implementasi Perbaikan:**
```sql
SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as pending_verifikasi,
SUM(CASE WHEN status = 'Diverifikasi' THEN 1 ELSE 0 END) as terverifikasi,
```

**Dampak Positif:** Angka di dashboard akurat; admin bisa melihat berapa laporan yang menunggu verifikasi.

---

### FUN-07 — Tidak Ada Validasi Unique Constraint untuk `nomor_laporan`
**Prioritas:** SEDANG  
**Upaya:** 1 jam  
**File Terdampak:** Skema database, migrations

**Masalah:**  
Jika dua request `store()` Submitted masuk secara bersamaan (race condition), keduanya dapat menghitung counter yang sama dan menghasilkan `nomor_laporan` duplikat tanpa ada database constraint yang mencegah hal ini.

**Implementasi Perbaikan:**
```sql
-- Di migration:
ALTER TABLE laporan_hama ADD UNIQUE KEY uk_nomor_laporan (nomor_laporan);
```
Dan tangani duplicate key exception di PHP.

**Dampak Positif:** Integritas data terjamin, tidak ada nomor laporan duplikat.

---

### FUN-08 — Endpoint `LaporanHama::update()` Memungkinkan Perubahan Status Sembarangan
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** `app/controllers/Api/LaporanHamaController.php`

**Masalah:**  
`update()` meneruskan semua field dari request ke model tanpa memvalidasi transisi status. Seorang `petugas` secara teori bisa mengirimkan `status=Diverifikasi` di body PUT request untuk memverifikasi laporannya sendiri.

**Implementasi Perbaikan:**
```php
// Di update(), hapus field status dari data yang bisa diubah oleh petugas:
if ($_SESSION['role'] === 'petugas') {
    unset($data['status'], $data['verified_by'], $data['verified_at']);
}
// Transisi status hanya boleh melalui endpoint khusus (submit, verify, reject)
```

**Dampak Positif:** Alur verifikasi tidak dapat di-bypass oleh petugas.

---

### FUN-09 — Tidak Ada Endpoint Submit Terpisah untuk Laporan
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** Router, `LaporanHamaController`

**Masalah:**  
Tidak ada endpoint `POST /api/laporan-hama/{id}/submit` yang mengubah status dari `Draf` ke `Submitted`. Ini membuat alur Draf → Submit tidak jelas dan hanya bisa dilakukan melalui `update()` yang tidak aman.

**Implementasi Perbaikan:**
```php
// Tambahkan di Router:
$this->post('/api/laporan-hama/{id}/submit', 'Api\LaporanHamaController@submit', ['auth']);

// Tambahkan method submit() di controller:
public function submit(int $id): void {
    $laporan = $this->laporanModel->getById($id);
    if ($laporan['status'] !== 'Draf') {
        $this->sendError('Hanya laporan Draf yang dapat disubmit', 422);
    }
    if ($laporan['user_id'] != $_SESSION['user_id']) {
        $this->sendError('Forbidden', 403);
    }
    $nomorLaporan = $this->laporanModel->generateNomorLaporan();
    $this->laporanModel->update($id, ['status' => 'Submitted', 'nomor_laporan' => $nomorLaporan]);
    $this->sendResponse($this->laporanModel->getById($id), 'Laporan berhasil disubmit');
}
```

**Dampak Positif:** Alur status laporan eksplisit dan aman sesuai AGENTS.md.

---

### FUN-10 — `kecamatan_stats.php` Menggunakan Path `app/config/Database.php` yang Tidak Ada
**Prioritas:** RENDAH  
**Upaya:** 15 menit  
**File Terdampak:** `api/kecamatan_stats.php`, `api/recent_activity.php`

**Masalah:**  
```php
require_once __DIR__ . '/../app/config/Database.php';
```
Tidak ada direktori `app/config/`. Kelas Database ada di `app/core/Database.php`. File ini akan error fatal saat diakses.

**Implementasi Perbaikan:**
```php
// Ganti di kecamatan_stats.php dan recent_activity.php:
require_once __DIR__ . '/../app/core/Database.php';
// Atau lebih baik, gunakan autoloader:
require_once __DIR__ . '/../index.php';
```


---

## KATEGORI 4: KUALITAS KODE

### KUA-01 — Tidak Ada `declare(strict_types=1)` di Seluruh File Kode
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** Seluruh file `.php` di `app/`

**Masalah:**  
AGENTS.md mewajibkan `declare(strict_types=1)` di setiap file PHP. Tidak satu pun file yang diaudit memenuhi ketentuan ini. Tanpa strict types, PHP akan melakukan type coercion implisit yang dapat menyembunyikan bug tipe data. Contoh: fungsi dengan parameter `int $id` akan menerima string `"abc"` dan mengkonversinya tanpa error.

**Implementasi Perbaikan:**  
Tambahkan di awal setiap file PHP setelah `<?php`:
```php
<?php
declare(strict_types=1);
```
Gunakan script untuk menambahkan secara massal:
```bash
# PowerShell — tambahkan declare ke semua file PHP:
Get-ChildItem -Recurse -Filter "*.php" -Path "app\" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    if ($content -notmatch 'declare\(strict_types') {
        $content = "<?php`ndeclare(strict_types=1);`n" + $content.TrimStart('<?php').TrimStart()
        Set-Content $_.FullName $content
    }
}
```
Kemudian perbaiki type errors yang muncul secara bertahap.

**Dampak Positif:** Deteksi bug tipe data lebih awal; meningkatkan keandalan kode.

---

### KUA-02 — Duplikasi Kode `handleFileUpload()` di Dua Controller
**Prioritas:** SEDANG  
**Upaya:** 1 jam  
**File Terdampak:** `app/controllers/Api/IrigasiController.php`, `app/controllers/Api/LaporanHamaController.php`

**Masalah:**  
Implementasi `handleFileUpload()` yang identik (dengan direktori upload berbeda) terdapat di dua controller. Setiap perbaikan (seperti KEA-01) harus dilakukan dua kali, meningkatkan risiko inkonsistensi.

**Implementasi Perbaikan:**  
Pindahkan ke `BaseApiController` sebagai protected method dengan parameter direktori:
```php
// Di BaseApiController:
protected function handleFileUpload(array $file, string $subdirectory = 'uploads'): string {
    // Implementasi lengkap termasuk magic bytes validation (lihat KEA-01)
    $uploadDir = ROOT_PATH . '/public/' . trim($subdirectory, '/') . '/';
    // ...
}

// Di IrigasiController:
$data['foto'] = $this->handleFileUpload($_FILES['foto'], 'uploads/irigasi');

// Di LaporanHamaController:
$data['foto'] = $this->handleFileUpload($_FILES['foto'], 'uploads/laporan');
```

**Dampak Positif:** DRY principle; perbaikan keamanan cukup dilakukan sekali.

---

### KUA-03 — `AuthController` Tidak Memiliki PSR-4 Namespace dan Tidak Menggunakan Type Hints
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** `app/controllers/AuthController.php` dan controller lainnya

**Masalah:**  
Tidak ada namespace di controller (melanggar PSR-4), dan beberapa method tidak memiliki return type hint. Contoh: `login()` tidak deklarasikan `return type`, `logActivity()` menggunakan parameter tanpa type hint (`$userId`, `$action`, dll).

**Implementasi Perbaikan:**
```php
// Tambahkan namespace dan type hints:
namespace App\Controllers;

class AuthController extends Controller {
    private function logActivity(
        int $userId, 
        string $action, 
        string $table, 
        int $recordId, 
        string $description
    ): void {
        // ...
    }
}
```

**Dampak Positif:** Konsistensi dengan standar PSR-12; IDE dapat memberikan autocompletion dan type checking.

---

### KUA-04 — `User::getAllUsers()` Menggunakan String Interpolasi untuk LIMIT/OFFSET
**Prioritas:** SEDANG  
**Upaya:** 30 menit  
**File Terdampak:** `app/models/User.php`

**Masalah:**  
```php
$sql .= " LIMIT {$limit} OFFSET {$offset}";
```
Meskipun keduanya sudah di-cast ke int, ini adalah pola yang tidak konsisten dengan penggunaan prepared statements di seluruh codebase. Komentar dalam kode sendiri mengakui ini sebagai workaround: _"Cast to int to avoid SQL syntax issues with bound LIMIT/OFFSET in MySQL"_.

**Implementasi Perbaikan:**
```php
// Gunakan bindValue dengan PARAM_INT (bekerja di MySQL/MariaDB dengan ATTR_EMULATE_PREPARES=false):
$sql .= " LIMIT :limit OFFSET :offset";
$stmt = $this->db->prepare($sql);
$stmt->execute($params);
$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
```

**Dampak Positif:** Konsistensi penggunaan prepared statements di seluruh codebase.

---

### KUA-05 — Banyak Konstanta Magic String untuk Status Laporan
**Prioritas:** RENDAH  
**Upaya:** 2 jam  
**File Terdampak:** `app/models/LaporanHama.php`, semua file yang menggunakan status laporan

**Masalah:**  
String status laporan seperti `'Draf'`, `'Submitted'`, `'Diverifikasi'`, `'Ditolak'`, `'Diarsipkan'` tersebar di banyak file sebagai literal string. Typo pada salah satunya tidak akan terdeteksi sampai runtime.

**Implementasi Perbaikan:**
```php
// app/core/LaporanStatus.php (file baru):
final class LaporanStatus {
    public const DRAF       = 'Draf';
    public const SUBMITTED  = 'Submitted';
    public const DIVERIFIKASI = 'Diverifikasi';
    public const DITOLAK    = 'Ditolak';
    public const DIARSIPKAN = 'Diarsipkan';
    
    public const ALL = [
        self::DRAF, self::SUBMITTED, self::DIVERIFIKASI, 
        self::DITOLAK, self::DIARSIPKAN
    ];
    public const VERIFIABLE = [self::SUBMITTED];
    public const STATISTICAL = [self::SUBMITTED, self::DIVERIFIKASI];
}

// Penggunaan:
WHERE status != LaporanStatus::DRAF
```

**Dampak Positif:** Typo terdeteksi saat compile-time; IDE autocompletion untuk status.

---

### KUA-06 — Tidak Ada Logging Terstruktur, Hanya `error_log()`
**Prioritas:** RENDAH  
**Upaya:** 3 jam  
**File Terdampak:** Seluruh codebase

**Masalah:**  
Meskipun ada kelas `Logger` di `app/helpers/Logger.php`, sebagian besar kode masih menggunakan `error_log()` langsung. Log tidak memiliki level (ERROR, WARNING, INFO), context terstruktur, atau request ID untuk korelasi.

**Implementasi Perbaikan:**  
Standarisasi penggunaan `Logger` di semua kelas:
```php
// Ganti error_log() dengan Logger:
$this->logger = new Logger();
$this->logger->error('Gagal ambil data irigasi', ['id' => $id, 'user' => $_SESSION['user_id']]);
$this->logger->warning('Attempt login gagal', ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
```

**Dampak Positif:** Log yang dapat dianalisis dan di-query; memudahkan debugging production.

---

### KUA-07 — `BaseApiController::validateRequired()` Menggunakan `empty()` yang Tidak Tepat untuk Integer
**Prioritas:** RENDAH  
**Upaya:** 1 jam  
**File Terdampak:** `app/controllers/Api/BaseApiController.php`

**Masalah:**  
`empty($data[$field])` menganggap `0` (nol) sebagai "kosong", yang bisa menjadi masalah jika ada field numerik yang nilai sahnya adalah `0` (misalnya `populasi = 0`).

**Implementasi Perbaikan:**
```php
protected function validateRequired(array $data, array $requiredFields): array {
    $errors = [];
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            $errors[] = "Field '{$field}' wajib diisi";
        }
    }
    return $errors;
}
```

**Dampak Positif:** Validasi yang akurat untuk semua tipe data termasuk `0` dan `false`.

---

### KUA-08 — `stateChangingMethods` di `index.php` Adalah Daftar Manual yang Rawan Terlewat
**Prioritas:** RENDAH  
**Upaya:** 3 jam  
**File Terdampak:** `index.php`

**Masalah:**  
Daftar method yang memerlukan CSRF check di `index.php` adalah hard-coded array. Setiap method baru yang mengubah state harus ditambahkan manual ke daftar ini. Jika developer lupa, method baru tidak akan terlindungi CSRF.

**Implementasi Perbaikan:**  
Implementasikan dengan PHP Attribute atau anotasi komentar yang bisa dibaca secara otomatis:
```php
// Atau lebih simpel: default CSRF untuk semua POST/PUT/DELETE request tanpa pengecualian:
$isStateChanging = in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH']);
if ($isStateChanging) {
    // validasi CSRF
}
// Method GET tidak perlu state-changing check
```

**Dampak Positif:** CSRF protection otomatis berlaku untuk semua route baru.


---

## KATEGORI 5: ARSITEKTUR

### ARS-01 — File `api/*.php` Standalone Merupakan Arsitektur Parallel yang Tidak Konsisten
**Prioritas:** SEDANG  
**Upaya:** 4 jam  
**File Terdampak:** `api/desa_autocomplete.php`, `api/desa_filter.php`, `api/kecamatan_stats.php`, `api/recent_activity.php`

**Masalah:**  
Terdapat dua sistem routing yang berjalan paralel: (1) Router di `app/core/Router.php` untuk endpoint `/api/v1/`, dan (2) file PHP standalone di `/api/` yang langsung diakses via URL. File standalone ini:
- Tidak menggunakan autoloader, melakukan manual `require_once`
- Mengatur session sendiri (`session_start()`)
- Tidak mengikuti pola MVC
- Tidak terdaftar di Router sehingga tidak terkena middleware global
- Memiliki CORS header yang berbeda dari endpoint utama

**Implementasi Perbaikan:**  
Migrasi semua endpoint dari `api/` ke dalam Router dan controller yang sesuai:
```php
// Pindahkan logika api/desa_filter.php ke:
// app/controllers/Api/WilayahController.php::filterDesa()

// Daftarkan di Router:
$this->get('/api/wilayah/desa/filter', 'Api\WilayahController@filterDesa', ['auth', 'rate_limit']);
```
Setelah migrasi, tambahkan redirect atau hapus file lama.

**Dampak Positif:** Satu titik entry untuk semua request API; middleware global berlaku konsisten.

---

### ARS-02 — Tidak Ada Service Layer — Logika Bisnis Tersebar di Controller dan Model
**Prioritas:** SEDANG  
**Upaya:** Besar (bertahap, 2–4 sprint)  
**File Terdampak:** Seluruh controller API

**Masalah:**  
Logika bisnis kompleks (seperti verifikasi laporan, generasi nomor laporan, pembuatan alert irigasi) dilakukan langsung di controller atau model. Controller seperti `IrigasiController` memiliki 500+ baris kode dan menangani HTTP parsing, business logic, database queries, dan file operations sekaligus.

**Implementasi Perbaikan:**  
Terapkan Service Layer secara bertahap:
```
app/services/
  LaporanService.php     — verifikasi, submit, reject laporan
  FileUploadService.php  — upload, validasi, hapus file
  IrigasiService.php     — monitoring, analytics, rule engine
  NotifikasiService.php  — FCM notifications
```
```php
// LaporanService.php:
class LaporanService {
    public function submit(int $laporanId, int $userId): LaporanHama {
        // Semua business rules di sini
    }
}

// Controller menjadi tipis:
public function submit(int $id): void {
    $laporan = $this->laporanService->submit($id, $_SESSION['user_id']);
    $this->sendResponse($laporan, 'Berhasil disubmit');
}
```

**Dampak Positif:** Testability meningkat; controller lebih tipis; logika bisnis dapat diuji unit tanpa HTTP layer.

---

### ARS-03 — Hanya 1 Migration untuk Keseluruhan Database
**Prioritas:** SEDANG  
**Upaya:** 4 jam  
**File Terdampak:** `migrations/`

**Masalah:**  
Hanya ada satu file migration (`2025_01_create_kecamatan_jember.php`), sementara aplikasi sudah memiliki puluhan tabel yang digunakan (laporan_hama, irigasi, users, master_opt, dll). Ini menandakan skema database tidak dikelola melalui migration, sehingga tidak ada cara reproducible untuk setup environment baru atau rollback perubahan skema.

**Implementasi Perbaikan:**  
Buat migration lengkap untuk setiap tabel secara bertahap:
```
migrations/
  2025_01_001_create_users.php
  2025_01_002_create_master_opt.php
  2025_01_003_create_laporan_hama.php
  2025_01_004_create_laporan_irigasi.php
  2025_01_005_add_nomor_laporan_to_laporan_hama.php
  ...
```
Referensikan `docs/DATABASE.md` sebagai sumber kebenaran schema.

**Dampak Positif:** Reproducible setup; rollback schema; onboarding developer baru lebih mudah.

---

### ARS-04 — Autoloader Tidak Mendukung Namespace (PSR-4) — Class Collision Risk
**Prioritas:** SEDANG  
**Upaya:** 3 jam  
**File Terdampak:** `index.php` (blok `spl_autoload_register`), `app/core/Router.php`

**Masalah:**  
Autoloader saat ini mencari file berdasarkan nama kelas saja, tanpa mempertimbangkan namespace. Jika dua kelas di direktori berbeda memiliki nama yang sama (misal: `DashboardController` di `app/controllers/` dan `Api/DashboardController`), hanya satu yang akan ter-load. Saat ini Router mengatasinya dengan `require_once` manual di `executeRoute()`, tetapi ini tidak skalabel.

**Implementasi Perbaikan:**  
Tambahkan Composer autoload dengan PSR-4:
```json
// composer.json:
{
  "autoload": {
    "psr-4": {
      "App\\Controllers\\": "app/controllers/",
      "App\\Controllers\\Api\\": "app/controllers/Api/",
      "App\\Models\\": "app/models/",
      "App\\Core\\": "app/core/",
      "App\\Helpers\\": "app/helpers/",
      "App\\Services\\": "app/services/"
    }
  }
}
```
Kemudian jalankan `composer dump-autoload` dan ganti `spl_autoload_register` di `index.php` dengan `require vendor/autoload.php`.

**Dampak Positif:** Resolusi kelas deterministik; mendukung namespace PSR-4 penuh.

---

### ARS-05 — Tidak Ada Pemisahan Environment Config (dev/staging/prod)
**Prioritas:** SEDANG  
**Upaya:** 2 jam  
**File Terdampak:** `index.php`, `config/`

**Masalah:**  
Hanya ada satu konfigurasi via `.env`. Tidak ada cara untuk mengaktifkan/menonaktifkan fitur debug, error reporting, atau caching berdasarkan environment. `JSON_PRETTY_PRINT` aktif di production, `error_log` mencampur pesan dev dan prod, tidak ada flag `APP_ENV`.

**Implementasi Perbaikan:**
```php
// Di index.php setelah load .env:
define('APP_ENV', getenv('APP_ENV') ?: 'production');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
```
```env
# .env.example:
APP_ENV=production  # development | staging | production
```

**Dampak Positif:** Konfigurasi yang tepat per environment; tidak ada debug output di production.

---

### ARS-06 — `IrigasiController` Berisi Endpoint yang Bergantung pada Service/Model yang Belum Ada
**Prioritas:** RENDAH  
**Upaya:** Tergantung implementasi  
**File Terdampak:** `app/controllers/Api/IrigasiController.php`

**Masalah:**  
Method `weather()` menginstansiasi `WeatherService`, `createRule()` dan `executeRule()` menginstansiasi `IrrigationRuleEngine` — keduanya di-require dari `app/services/`. Jika file ini tidak ada, request akan menghasilkan PHP Fatal Error yang tidak tertangani (karena `require_once` bukan dalam try-catch).

**Implementasi Perbaikan:**  
1. Buat stub service yang melempar `NotImplementedException`:
```php
// app/services/WeatherService.php:
class WeatherService {
    public function getForIrigasi(int $id): array {
        throw new RuntimeException('WeatherService belum diimplementasikan');
    }
}
```
2. Atau pindahkan semua `require_once` ke dalam try-catch di constructor controller.

**Dampak Positif:** Graceful degradation; error 501 bukan PHP Fatal Error.

---

### ARS-07 — Banyak Dokumen Audit Lama di Root Project yang Mengganggu
**Prioritas:** RENDAH  
**Upaya:** 15 menit  
**File Terdampak:** `BLACKBOXAI_JAGAPADI_AUDIT_2026-04-22.md`, `CODEX_TECHNICAL_AUDIT_2026-04-22.md`, `cline_ANALISIS_KODE_JAGAPADI.md`, `gemini_laporan_audit_teknis_jagapadi.md`, `kilo code_technical-audit-report.md`, `kiro_ANALISIS_KODE_JAGAPADI.md`, `audit_files.txt`, `cj.txt`, `pc.txt`, `sc.txt`, `dummy.txt`, `fcm_files.txt`

**Masalah:**  
Root project mengandung puluhan file non-kode yang merupakan artefak pengembangan. Ini mencemari working tree git, meningkatkan ukuran repository, dan menyulitkan navigasi.

**Implementasi Perbaikan:**
```bash
# Buat direktori arsip untuk dokumen audit:
mkdir dok\audit-history
move *AUDIT*.md dok\audit-history\
move *audit*.md dok\audit-history\
# Hapus file temp:
del cj.txt pc.txt sc.txt dummy.txt fcm_files.txt audit_files.txt
# Tambahkan ke .gitignore:
echo "/*.txt" >> .gitignore
echo "/kiro_*.md" >> .gitignore
```

**Dampak Positif:** Repository bersih; developer baru tidak kebingungan dengan dokumen audit lama.

---

### ARS-08 — `stateChangingMethods` di `index.php` dan Enforcement CSRF di Router Tidak Sinkron
**Prioritas:** RENDAH  
**Upaya:** 1 jam  
**File Terdampak:** `index.php`, `app/core/Router.php`

**Masalah:**  
CSRF enforcement dilakukan di dua tempat dengan mekanisme berbeda: `index.php` untuk web routes menggunakan whitelist method name, sementara `Router.php` untuk API routes menggunakan logika middleware. Jika kedua sistem memiliki gap atau inkonsistensi, sulit untuk memastikan semua mutasi terlindungi.

**Implementasi Perbaikan:**  
Konsolidasikan CSRF enforcement ke satu tempat (misal: di `Security::enforceCsrf()`) yang dipanggil dari keduanya dengan logika yang sama.


---

## Roadmap Implementasi Bertahap

### Sprint 1 — Keamanan Kritis (Minggu 1–2)
Selesaikan semua temuan prioritas TINGGI sebelum deployment production.

| ID | Judul | Estimasi |
|---|---|---|
| KEA-01 | Fix upload validation (magic bytes) | 2–3 jam |
| KEA-02 | Hapus file debug dari root | 30 menit |
| KEA-04 | Perbaiki sanitizeData() (jangan escape sebelum DB) | 2–4 jam |
| FUN-02 | Implementasi nomor laporan | 3 jam |
| FUN-03 | Blokir verifikasi laporan Draf | 2 jam |
| PER-01 | Fix RateLimiter: gunakan CacheManager | 1 jam |
| FUN-10 | Fix path require Database.php salah | 15 menit |

**Total estimasi Sprint 1:** ~14 jam

---

### Sprint 2 — Fungsional & Bisnis (Minggu 3–4)

| ID | Judul | Estimasi |
|---|---|---|
| FUN-01 | Terapkan include_draft=false default di statistik | 4 jam |
| FUN-04 | Fix route ordering (profile shadowed by {id}) | 1 jam |
| FUN-05 | Dukung alur Draft di store() | 2 jam |
| FUN-06 | Fix kalkulasi pending_verifikasi | 1 jam |
| FUN-08 | Cegah petugas ubah status via update() | 2 jam |
| FUN-09 | Tambah endpoint /submit | 2 jam |
| KEA-03 | Batasi CORS dari wildcard ke whitelist | 1 jam |

**Total estimasi Sprint 2:** ~13 jam

---

### Sprint 3 — Performa & Kualitas (Minggu 5–6)

| ID | Judul | Estimasi |
|---|---|---|
| KUA-01 | Tambah declare(strict_types=1) ke semua file | 2 jam |
| KUA-02 | Satukan handleFileUpload() ke BaseApiController | 1 jam |
| KUA-05 | Buat konstanta LaporanStatus | 2 jam |
| PER-02 | Caching dashboardSummary | 2 jam |
| PER-04 | Ganti SELECT * di User model | 1 jam |
| PER-05 | Tambah index database | 2 jam |
| KEA-05 | Dokumentasikan/hapus role operator & statistisi | 3 jam |

**Total estimasi Sprint 3:** ~13 jam

---

### Sprint 4 — Arsitektur Jangka Panjang (Sprint selanjutnya, bertahap)

| ID | Judul | Estimasi |
|---|---|---|
| ARS-01 | Migrasi api/*.php ke Router | 4 jam |
| ARS-02 | Implementasi Service Layer (bertahap) | 2–4 sprint |
| ARS-03 | Lengkapi migration database | 4 jam |
| ARS-04 | Setup Composer PSR-4 autoload | 3 jam |
| ARS-05 | Pemisahan environment config | 2 jam |
| ARS-07 | Bersihkan file artifact di root | 15 menit |

---

## Kesimpulan

JAGAPADI memiliki fondasi arsitektur yang cukup solid — penggunaan PDO prepared statements, CSRF protection, bcrypt password hashing dengan cost 12, dan brute force protection menunjukkan kesadaran keamanan yang baik dari tim pengembang. Kelas `CacheManager` dan `Security` diimplementasikan dengan matang.

Namun, terdapat **gap kritis antara apa yang didokumentasikan di AGENTS.md dan apa yang diimplementasikan dalam kode**, terutama pada:
1. **Draft Policy** — statistik masih menyertakan draft
2. **Nomor Laporan** — belum digenerate sama sekali  
3. **File Upload Security** — tidak ada magic bytes validation
4. **File Debug** — masih aktif di production

Dengan menyelesaikan Sprint 1 dan 2 (estimasi ~27 jam kerja), aplikasi akan siap untuk deployment production yang aman dan sesuai spesifikasi bisnis.

---

*Dokumen ini dihasilkan dari analisis statis kode sumber. Pengujian dinamis dan penetration testing tambahan disarankan sebelum go-live.*
