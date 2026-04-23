# LAPORAN ANALISIS TEKNIS KOMPREHENSIF APLIKASI JAGAPADI
**Tanggal Analisis**: 22 April 2026  
**Versi Aplikasi**: Custom PHP MVC Monolit (dari config/version.php)  
**Analis**: BLACKBOXAI Automated Code Auditor  
**Skor Keseluruhan**: **6.2/10** (MVP stabil, namun perlu perbaikan segera untuk produksi dan skalabilitas)

## 1. Ringkasan Eksekutif
Aplikasi JAGAPADI saat ini adalah monolit PHP native dengan arsitektur custom MVC, server-rendered UI, dan API internal berbasis session. Audit menemukan beberapa kekuatan utama seperti: model layanan/scraper terpisah, penggunaan `password_hash` untuk password, dan dukungan PWA/Service Worker. Namun, ada isu kritis yang harus ditangani segera: otentikasi API tidak konsisten, CSRF tidak diterapkan pada semua aksi destruktif, konfigurasi sensitif disimpan dalam kode, serta frontend state dan render kurang terstruktur.

### Skor kategori
- Keamanan: 5.8/10
- Performansi: 6.3/10
- Maintainability: 6.0/10
- Arsitektur & API: 6.5/10
- Frontend & aksesibilitas: 6.7/10

## 2. Lingkup Analisis
- Backend: `index.php`, `config/`, `app/core/`, `app/controllers/`, `app/middleware/`, `app/helpers/`, `app/models/`, `app/services/`, `config/database.php`
- Frontend: server-rendered `app/views/`, inline JS, `public/js/`, `public/css/`, `public/sw.js`, `public/manifest.json`
- Data & arsitektur: database config, routing custom, cache file-based, API endpoint mix
- Tes: `tests/Unit/CurahHujanValidatorTest.php`, `tests/Integration/CurahHujanApiTest.php`

## 3. Temuan Utama
### 3.1 Backend & Arsitektur
1. Monolit custom terpusat dengan router regex manual (`app/core/Router.php`), tidak ada dependency injection dan tidak ada service container.
2. API design tidak konsisten: beberapa endpoint menggunakan session auth, beberapa menggunakan `X-API-KEY`, dan `ApiAuthMiddleware` tidak terintegrasi dengan jelas di seluruh API.
3. `config/database.php` menyimpan credential secara hardcoded, tanpa dukungan `.env` atau konfigurasi rahasia yang terpisah.
4. `app/core/QueryBuilder.php` memiliki sanitasi identifier yang lemah dan tidak mendukung alias/query kompleks dengan benar.
5. Error handling tersebar dengan `error_log()` di banyak controller, tanpa struktur logging, trace id, atau pengiriman ke sistem observabilitas.
6. `Cache` berbasis file di `storage/cache` sangat rentan pada deployment multi-instance dan tidak cocok untuk scaling horizontal.

### 3.2 Keamanan
1. CSRF tidak konsisten: `LaporanController::delete()` tidak memvalidasi CSRF, sementara `bulkDelete()` baru menggunakan CSRF untuk AJAX.
2. API publik `ApiController::submitReport()` mengembalikan `201` meski `checkApiAuth()` stub tidak melakukan validasi; autentikasi API hanya memeriksa header kosong.
3. Middleware role di `Router::applyMiddleware()` bersifat permissive terhadap middleware tak dikenal; identifier unknown tidak ditolak secara eksplisit.
4. Konfigurasi sensitif di `config/config.php` berisi token API default, SMTP user, dan password placeholder yang mudah bocor jika diterbitkan.
5. `Security::checkBruteForce()` menggunakan session untuk pelacakan, tidak efektif untuk serangan terdistribusi dan mudah di-bypass.
6. `Service Worker` meng-cache CDN asset menggunakan `credentials: 'same-origin'`, yang dapat menyebabkan kegagalan cache karena cross-origin dan memperbesar offline cache footprint.
7. Banyak output HTML server-rendered tidak menunjukkan sanitasi output XSS/HTML escaping secara eksplisit.

### 3.3 Performansi
1. Banyak query model `User` dan `ApiController` menggunakan `SELECT *`, tanpa pemilihan kolom yang tepat.
2. `QueryBuilder` tidak memfasilitasi `LIMIT/OFFSET` dengan parameter bind dan tidak menyokong query builder untuk caches.
3. Kode frontend melakukan destroy/recreate Chart.js setiap kali data di-refresh, memicu overhead rendering yang tidak perlu.
4. `public/sw.js` cache onboarding remote CDN yang besar, memperlambat install dan tidak meminimalkan konten untuk jaringan terbatas.
5. View PHP memuat gaya inline dan skrip langsung di halaman, membuat optimasi critical rendering path sulit.

### 3.4 Maintainability & Best Practice
1. Banyak file view menggunakan inline style dan inline script, menyebabkan duplikasi dan sulit diuji.
2. Dependency loading kelas memakai `spl_autoload_register` dengan path manual, tidak menggunakan PSR-4 dan composer.
3. Data flow di frontend melalui DOM manual (`document.getElementById`, `innerText`, `innerHTML`), bukan state management terpisah.
4. Dokumentasi test terbatas: hanya dua tes, tidak ada coverage untuk autentikasi, API, atau service worker.
5. Modul `ApiController` dan `ApiAuthMiddleware` tumpang tindih; ini menambah technical debt karena logika auth tersebar.
6. Banyak controller menggunakan `require_once` setiap kali instansiasi service; ini menghambat reuse dan unit test.

### 3.5 Frontend & Aksesibilitas
1. UI menggunakan layout bootstrap/AdminLTE, tetapi tabel dan form tidak konsisten dengan label/aria attributes.
2. Elemen tombol dan input tidak selalu memiliki `aria-label` atau `role`, khususnya pada card dashboard dan action button.
3. Responsif umumnya baik, tetapi `#wilayahDropdown` dan beberapa komponen tabel menggunakan min-width tetap dan dapat menimbulkan overflow di layar kecil.
4. Tidak ada deskripsi alternatif (`alt`) eksplisit di banyak ikon atau gambar inline yang di-generate.
5. Akses keyboard/ fokus tidak terdokumentasi; beberapa elemen clickable memakai `div` non-button tanpa keyboard event.

## 4. Daftar Issue Prioritas
| Prioritas | Issue | Dampak Bisnis | Effort | Rekomendasi Utama |
|---|---|---|---|---|
| P0 | API auth / CSRF inconsistency | Kebocoran data, endpoint destruktif disalahgunakan, reputasi | 2-3 hari | Konsolidasikan auth menjadi token Bearer/HTTPS + CSRF pusat untuk form web |
| P0 | Konfigurasi sensitif tersimpan di kode | Kebocoran produksi, audit gagal | 1 hari | Pindahkan ke `.env`/secret manager + periksa git history |
| P1 | `submitReport()` tanpa validasi API key | Integrasi eksternal tidak aman | 1-2 hari | Terapkan validasi API key/role di DB dan middleware tunggal |
| P1 | File-based cache dan session sharing | Skalabilitas menghentikan deployment horizontal | 3-5 hari | Ganti dengan Redis/Memcached atau DB-backed cache untuk shared state |
| P1 | Inline JS/CSS dan non-PSR autoload | Kecepatan pengembangan melambat, bug bertambah | 3-4 hari | Pisahkan resource, pindah ke asset bundler, gunakan PSR-4 composer |
| P2 | Error logging tidak terstruktur | Investigasi insiden sulit, penelusuran tidak konsisten | 2 hari | Terapkan logger terpusat (Monolog atau file JSON dengan trace_id) |
| P2 | Chart rerender setiap update | UX tersendat di data refresh | 1-2 hari | Reuse chart instance dan update dataset saja |
| P2 | Frontend a11y / ARIA kurang | Pengguna disabilitas terpinggirkan | 2 hari | Tambah `aria-label`, semantic HTML, focus states |

## 5. Rekomendasi Solusi Konkret
### 5.1 Backend Security & Auth
- Buat `BaseApiController` / middleware tunggal: semua request API harus melewati pengecekan token, verifikasi method, dan rate-limit.
- Gunakan `Authorization: Bearer <token>` untuk API, jangan pakai query string.
- Simpan API key di database/secret manager, bukan file. Rotasi setiap 90 hari.
- Konsolidasikan CSRF web pada `Controller::validateCsrfToken()` dan panggil sebelum aksi `delete`, `store`, `update`, `bulkDelete`.
- Tingkatkan brute-force dengan store berbasis Redis/DB dan pembatasan IP/username.

### 5.2 Config & Deployment
- Terapkan `vlucas/phpdotenv` atau `symfony/dotenv`.
- `config/config.php` dan `config/database.php` harus membaca `$_ENV`/`.env`.
- Gunakan mode `display_errors=0` di production, log ke file/observability.
- Periksa file `.gitignore` untuk memastikan `.env` tidak masuk.

### 5.3 Database & Query
- Tambahkan indeks pada kolom pencarian utama: `users(username,email)`, `laporan_hama(user_id)`, `master_opt`, `wilayah`.
- Ubah `QueryBuilder` untuk membuat alias kolom aman dan gunakan parameter binding penuh.
- Hindari `SELECT *`; pilih kolom yang dibutuhkan.
- Tambahkan analytics query dengan cache TTL 5-30 menit untuk dashboard.

### 5.4 Frontend Architecture
- Pisahkan asset: `public/css/*.css`, `public/js/*.js`; hindari CSS inline di view.
- Refaktor UI heavy page seperti `storytelling/index.php` menjadi modul JS dengan state object.
- Ubah `updateChart()` menjadi dataset update, bukan `destroy()/new Chart()`.
- Pertimbangkan transisi ke SPA/komponen terstruktur (Vue/Alpine/React) setelah stabilisasi backend.

### 5.5 Observability & Testing
- Terapkan logger terpusat: `ErrorLogger::log($context)` ke file JSON, atau `Monolog`.
- Tambahkan trace id pada setiap request web dan API.
- Bangun test suite: unit test `Security`, `QueryBuilder`, `User`, controller auth; integration test endpoint API, CSRF, role.
- Lakukan load test/benchmark API endpoint dashboard sebelum dan setelah caching.

## 6. Roadmap Implementasi
### Fase 1: Stabilitas & Keamanan (1-2 minggu)
1. Pindahkan secret ke `.env` + perbaiki `config/database.php`.
2. Konsolidasikan API auth di middleware, hapus stub `checkApiAuth()`.
3. Terapkan CSRF global dan perbaiki semua action destruktif (`delete`, `bulkDelete`, `store`).
4. Tambah logging terstruktur dan disable error display di production.
5. Ubah `public/sw.js` agar hanya cache asset statis yang bermilik sama origin dan batasi cache.

### Fase 2: Performa & Skalabilitas (2-3 minggu)
1. Migrasi caching shared ke Redis atau DB-backed cache.
2. Tambah indeks DB kritis dan optimalkan query model.
3. Refaktor chart rendering, query API analytics, dan fallback data.
4. Pisahkan resource CSS/JS dan aktifkan minification.

### Fase 3: Maintainability & UX (3-4 minggu)
1. Terapkan autoload PSR-4 + composer, hapus `require_once` berulang.
2. Refaktor modul frontend menjadi library komponen terstruktur.
3. Lengkapi test coverage dan automated CI pipeline (PHPUnit + E2E minimal).
4. Tambah a11y audit dan validasi desain responsif dengan mobile-first.

## 7. Contoh Refaktor Kritis
### 7.1 Middleware API Auth Terpusat
```php
class ApiMiddleware {
    public static function handle() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(?<token>.+)$/i', $authorization, $matches)) {
            self::unauthorized('Missing Bearer token');
        }

        $token = trim($matches['token']);
        if (!ApiKey::validate($token)) {
            self::unauthorized('Invalid API token');
        }

        RateLimiter::apply(self::getEndpoint(), self::getClientIdentifier());
        return true;
    }

    private static function unauthorized(string $message) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
```

### 7.2 Central CSRF untuk Web Actions
```php
protected function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Security::validateCsrfToken($token)) {
        Security::logSecurityEvent('CSRF_VIOLATION', 'Invalid CSRF token detected', $_SESSION['user_id'] ?? null);
        http_response_code(403);
        $this->json(['error' => 'CSRF token validation failed'], 403);
    }
}
```

### 7.3 Chart.js Update Tanpa Destroy
```js
function updateChart(chartData) {
    if (!chartInstance) {
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [/* ... */]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
        return;
    }

    chartInstance.data.labels = chartData.labels;
    chartInstance.data.datasets[0].data = chartData.produksi;
    chartInstance.data.datasets[1].data = chartData.rainfall;
    chartInstance.update();
}
```

### 7.4 Query Builder Safe Alias Support
```php
public function table(string $table): self {
    $table = trim($table);
    if (strpos($table, ' ') !== false) {
        [$name, $alias] = preg_split('/\s+AS\s+/i', $table) + [null, null];
        $name = $this->sanitizeIdentifier($name);
        $alias = $alias ? $this->sanitizeIdentifier($alias) : null;
        $this->table = $alias ? "{$name} AS {$alias}" : $name;
    } else {
        $this->table = $this->sanitizeIdentifier($table);
    }
    return $this;
}
```

## 8. Metrik Performa Sebelum / Sesudah (Estimasi)
| Area | Sebelum | Target Sesudah |
|---|---|---|
| Latensi respons dashboard | 800-1200 ms | 300-500 ms |
| Waktu render Storytelling chart | 1.2-1.8 detik | 400-700 ms |
| Rate limit overhead | tinggi saat cache file invalid | rendah konsisten dengan Redis |
| Waktu build offline cache | 5-8 detik | 2-3 detik |
| Coverage test | <10% area API | 60-70% area kritis |

> Catatan: metrik di atas adalah estimasi awal berdasarkan struktur kode saat ini; validasi menggunakan benchmark tools diperlukan setelah refaktor.

## 9. Rencana Validasi & Test Plan
### 9.1 Unit Test
- `Security::validateCsrfToken()` valid/invalid/expired
- `Security::sanitizeInput()` XSS payloads
- `QueryBuilder` select/insert/update/delete SQL generation
- `User::authenticate()` password hash/disabled akun
- `ApiKey::validate()` token rotation

### 9.2 Integration Test
- Auth workflow: login, change_password, logout
- API auth: protected endpoint must 401 tanpa Bearer token
- CSRF protection: POST destroy endpoint ditolak tanpa token
- `storytelling/generateAnalysis` dan `storytelling/store`
- `submitReport()` dan API error handling

### 9.3 Frontend / E2E
- Form fill + submit dengan CSRF token
- Mobile viewport render `storytelling/index.php`
- Keyboard navigation dan `aria` keyboard focus
- Service worker offline load `offline.html`

### 9.4 Performance Verification
- Jalankan benchmark request API dengan `wrk`/`ApacheBench`
- Bandingkan `time to first byte` sebelum/sesudah caching
- Profil query berat dengan `EXPLAIN` dan `slow query log`

## 10. Rekomendasi Langsung
1. Segera perbaiki CSRF pada semua aksi write/delete.
2. Konsolidasikan API auth menjadi Bearer token, hapus stub validasi tidak aman.
3. Pindahkan credential ke `.env` dan hentikan penyimpanan password default di repo.
4. Tambah logging terstruktur + trace id untuk debugging insiden.
5. Refaktor chart rendering dan cache material untuk menurunkan beban UI.

---

Dokumen ini adalah laporan analisis teknis berdasar inspeksi kode sumber. Untuk eksekusi perbaikan, saya rekomendasikan menangani issue P0/P1 secara berurutan sebelum menambahkan pengujian end-to-end.
