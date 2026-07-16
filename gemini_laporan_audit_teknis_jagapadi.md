# Laporan Audit Teknis Komprehensif: Sistem JAGAPADI

> [!NOTE]
> **Tujuan:** Laporan ini menyajikan hasil audit teknis mendalam terhadap aplikasi JAGAPADI (Frontend & Backend), mengidentifikasi masalah kritis, dampaknya terhadap bisnis, serta rekomendasi perbaikan lengkap dengan *roadmap*, contoh *refactoring*, dan *test plan*.

## 1. Ringkasan Eksekutif

Aplikasi JAGAPADI menggunakan arsitektur *custom MVC framework* berbasisi PHP murni dengan interaksi *database* MariaDB/MySQL. Secara umum, aplikasi telah memiliki fundamental yang cukup baik dengan implementasi pengamanan berlapis seperti CSRF token, `QueryBuilder` anti SQL-Injection, *Rate Limiting*, dan kapabilitas PWA (*Service Worker*, *Offline Sync*). 

Namun, seiring dengan kompleksitas fitur, audit ini menemukan **isu kritikal terkait skalabilitas dan maintainability**, khususnya *God Objects* (kelas dengan beban kerja terlalu besar) pada area *Controller* dan kelemahan implementasi state/cache berbasis *file/session* yang rentan jika aplikasi didistribusikan (*Load Balancing*).

---

## 2. Daftar Temuan Berprioritas (Issue List) & Dampak Bisnis

### P1 - Kritis (Prioritas Utama: Keamanan & Stabilitas)

> [!CAUTION]
> Isu-isu ini dapat menyebabkan eksploitasi sistem atau *downtime* jika aplikasi diakses oleh ribuan pengguna secara bersamaan.

1. **State-bound Rate Limiting & Brute Force Protection (Backend - Keamanan)**
   * **Masalah:** Fungsi `Security::checkBruteForce` dan pemrosesan utama bergantung pada PHP `$_SESSION` dan sistem `.txt`/file-based `Cache`. Di lingkungan *load-balanced* atau dengan *traffic* tinggi, sistem ini gagal membagi *state* (terkecuali ada mekanisme *sticky sessions* yang menghambat performa). File caching dapat menyebabkan *race conditions* & *I/O bottleneck*.
   * **Dampak Bisnis:** Kegagalan menghentikan serangan bot (DDoS/Brute Force) pada skala besar dan berpotensi menghabiskan *disk I/O* server yang mengakibatkan aplikasi *down*.
   * **Effort:** Menengah (3-5 hari).

2. **God Objects pada Controller (Backend - Maintainability)**
   * **Masalah:** File `LaporanController.php` dan `AdminWilayahController.php` sangat panjang (>800 baris kode). Logika bisnis, validasi, manipulasi file (kompresi gambar), dan format respons disatukan dalam metode *controller*.
   * **Dampak Bisnis:** Menghambat kecepatan pengembangan (*time-to-market*). Sulit bagi tim developer baru untuk membaca, menambah fitur, atau melacak *bug* (rawan terjadi *regression bug*).
   * **Effort:** Tinggi (1-2 minggu).

### P2 - Mayor (Prioritas Menengah: Performa & Akurasi Data)

> [!WARNING]
> Isu-isu ini berdampak langsung pada pengalaman pengguna dan efisiensi *resource* sistem.

3. **Validasi Input Tipe Data yang Lemah (Backend - Keamanan & Stabilitas)**
   * **Masalah:** Metode penarikan elemen *pagination* dan *sorting* via `$_GET` di area *dashboard* dan *admin wilayah* kurang mengimplementasikan validasi tipe (strict type-casting).
   * **Dampak Bisnis:** Error yang tidak ditangani dengan baik (500 Internal Server error) jika tipe data yang dikirimkan tidak sesuai ekspektasi skema UI.
   * **Effort:** Ringan (1-2 hari).

4. **Ketergantungan Hardcode (*Hardcoded Configuration*)**
   * **Masalah:** Variabel lingkungan berantai (contoh: batasan lat/long geografis Jember) di-*hardcode* di `config.php`.
   * **Dampak Bisnis:** Aplikasi tidak fleksibel jika akan di-deploy untuk daerah/provinsi lain tanpa modifikasi sentuhan *codebase*.
   * **Effort:** Ringan (1 hari).

### P3 - Minor (Low Priority: UX & Best Practices)

> [!TIP]
> Rekomendasi tambahan untuk meningkatkan kualitas produk.

5. **Kurangnya Unit / Integration Testing**
   * **Masalah:** Framework inti seperti `QueryBuilder` (dengan 400+ baris logika dinamis) tidak dilengkapi *automated test*. 
   * **Dampak Bisnis:** Pembaruan logika core rentan merusak seluruh kueri database yang ada secara tidak disengaja.

6. **Optimasi Frontend Rendering & Bundle**
   * **Masalah:** File CSS / JS (seperti `mobile-enhancements.js`) masih menggunakan pola vanilla panjang tanpa proses *minification/bundling* moderen sehingga berdampak ringan pada ukuran *payload*.

---

## 3. Roadmap Implementasi Perbaikan

### Fase 1: Core Stabilisasi & Keamanan (Bulan 1 - Minggu 1 & 2)
1. **Migrasi Cache & Session:** Ganti file-based cache di `Cache.php` dan session-based security untuk menggunakan **Redis**.
2. **Validasi Strict Input:** Audit seluruh entrypoint `$_GET` & `$_POST` untuk menambahkan validasi tipe data yang solid (misal: is_numeric, filter_var).

### Fase 2: Dekomposisi Service (Bulan 1 - Minggu 3 & 4)
1. **Arsitektur Service Layer:** Kenalkan pola *Service/Repository Pattern*.
2. **Refactor LaporanController:** Pindahkan logika bisnis (termasuk upload file) ke `LaporanService`.
3. **Refactor AdminWilayahController:** Pisahkan logika audit dan cek konsistensi desa/kecamatan.

### Fase 3: Testing & Optimasi Skalabilitas (Bulan 2 - Minggu 1)
1. **Setup PHPUnit:** Implementasikan unit tests memfokuskan ke class `QueryBuilder` dan `Security`.
2. **Frontend Optimisation:** Terapkan build-step ringan (misal menggunakan vite/npm scripts) untuk minify `validation.js` dan `mobile-enhancements.js`.

---

## 4. Contoh Kode Refaktor (Dekomposisi Controller)

**Masalah:** `LaporanController` yang gemuk memuat manipulasi logika upload dan optimasi gambar secara mandiri.
**Solusi:** Pengenalan `LaporanService`.

#### Sebelum (Arsitektur Lama):
```php
class LaporanController extends Controller {
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validasi Rate Limit, CSRF, dll
            // Validasi $_POST manual 1 per 1
            // Kompresi image menggunakan GD library > 150 line code di dalam Controller
            // Kueri langsung dari $_POST array ke Model
        }
    }
}
```

#### Sesudah (Refactoring ke Service Pattern):

1. **Buat `app/services/LaporanService.php`**
```php
<?php
namespace App\Services;

class LaporanService {
    private $model;
    private $imageService;

    public function __construct($laporanModel, ImageOptimizationService $imgService) {
        $this->model = $laporanModel;
        $this->imageService = $imgService;
    }

    public function processSubmission(array $data, array $files, int $userId): array {
        // Validasi Bisnis yang kompleks
        $geoData = $this->extractGeoData($data);
        
        $imagePath = null;
        if (isset($files['bukti_foto']) && $files['bukti_foto']['error'] === UPLOAD_ERR_OK) {
            // Mendelegasikan logika kompresi gambar (SRP - Single Responsibility Principle)
            $imagePath = $this->imageService->compressAndSave($files['bukti_foto']);
        }

        $payload = array_merge($geoData, [
            'deskripsi' => htmlspecialchars($data['deskripsi']),
            'foto_path' => $imagePath,
            'user_id' => $userId
        ]);

        return $this->model->create($payload);
    }
}
```

2. **Clean Controller `app/controllers/LaporanController.php`**
```php
class LaporanController extends Controller {
    private $laporanService;

    public function __construct() {
        // DI / Inisiasi Service
        $this->laporanService = new \App\Services\LaporanService(
            $this->model('LaporanHama'),
            new \App\Services\ImageOptimizationService()
        );
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $result = $this->laporanService->processSubmission($_POST, $_FILES, $_SESSION['user_id']);
                
                if ($result) {
                    $this->jsonResponse(['success' => true, 'message' => 'Laporan berhasil disubmit!']);
                }
            } catch (\Exception $e) {
                // Sentralisasi error logger
                \ErrorLogger::log($e);
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
            }
        }
        $this->view('laporan/create');
    }
}
```

---

## 5. Estimasi Metrik Performa (Sebelum vs Sesudah Redis & Refactor)

Penerapan perbaikan Arsitektur Cache dan Controller diproyeksikan memberi dampak sebagai berikut (Stress Test 500 CCU - Concurrent Users):

| Metrik | Sebelum (File Cache) | Sesudah (Redis Cache) | Insight / Penjelasan |
| :--- | :--- | :--- | :--- |
| **Response Time (Dashboard)** | 450ms - 800ms | 80ms - 120ms | Redis beroperasi pada RAM/In-Memory (O(1)), mengeliminasi antrian IO pada disk membaca `Cache.php`. |
| **Memory Footprint / Req** | ~12 MB | ~4 MB | Dekomposisi class controller (tanpa me-load fungsi kompresi file saat tidak upload) mereduksi penggunaan memori per thread aplikasi. |
| **PWA Sync Performance** | Rentan tabrakan ID | Atomic Syncing | Refactor memastikan endpoint API bisa menampung 100 antrian background sync sw.js secara pararel dan asinkron. |

---

## 6. Rencana Pengujian (Test Plan)

Untuk mengamankan hasil Refactoring, kami merencanakan 3 lapis skema pengujian automatis:

### A. Unit Testing Plan (Fokus: Core & Security)
Kita akan mengimplementasikan `PHPUnit` untuk bagian yang tidak bersentuhan dengan database langsung.
*   **Target:** `QueryBuilder`, `Security::validateCsrfToken`, `ImageOptimizationService`.
*   **Contoh Skenario:** Menguji generator Query apakah menghasilkan string parameter PDO eksak yang sesuai ketika disuntik string *SQL injection* `OR 1=1`.

### B. Integration Testing Plan (Fokus: Service Layer & Flow)
*   **Target:** `LaporanService`.
*   **Skenario:** Mengirim data *mock* dari Controller, dan memvalidasi apakah database benar-benar menyimpan satu record laporan dengan *path file upload dummy* yang tepat.

### C. Frontend / E2E Testing Plan
Kita akan menggunakan Puppeteer atau Playwright (Opsional karena E2E lebih kompleks).
*   **Fokus E2E:** 
    1. Mensimulasikan jaringan *offline*.
    2. Input laporan hama baru.
    3. Menonaktifkan wifi/Koneksi.
    4. Cek *IndexedDB* apakah *Payload* masuk.
    5. Menghidupkan jaringan.
    6. Verifikasi Service Worker `background sync` berhasil trigger API `/laporan/create`.
