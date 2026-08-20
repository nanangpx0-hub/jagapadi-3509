# Design Document — Monitoring Pelaporan JAGAPADI

## Overview

Modul **Monitoring Pelaporan** diimplementasikan sebagai modul MVC baru yang sepenuhnya independen di dalam aplikasi JAGAPADI yang sudah ada. Modul ini tidak mengubah tabel atau controller yang ada, hanya membaca dari tabel laporan yang sudah ada dan menambahkan satu tabel baru (`evaluasi_petugas`). Semua pola kode mengikuti konvensi yang berlaku: PHP 8.2 native, PSR-12, `declare(strict_types=1)`, QueryBuilder, CacheManager, dan AdminLTE Bootstrap 4.

---

## Architecture

### Komponen Baru yang Ditambahkan

```
app/
  controllers/
    MonitoringController.php       ← Web controller (index, petugas, detail, evaluasi, export)
    Api/
      MonitoringApiController.php  ← AJAX endpoints (stats, charts, petugas list)
  models/
    MonitoringReport.php           ← Query agregat lintas tabel (hama + irigasi + lainnya)
    EvaluasiPetugas.php            ← CRUD catatan evaluasi & kalkulasi skor
  views/
    monitoring/
      index.php                    ← Dashboard utama (statistik + grafik)
      petugas.php                  ← Tabel peringkat petugas
      detail_petugas.php           ← Riwayat laporan satu petugas
      evaluasi.php                 ← Rekapitulasi & unduh skor evaluasi
      _filter_bar.php              ← Partial: komponen filter periode/kategori/wilayah
      _stat_cards.php              ← Partial: 4 kartu statistik
      print_monitoring.php         ← View khusus cetak PDF
      print_evaluasi.php           ← View khusus cetak evaluasi PDF
database/
  migrations/
    2026_08_07_create_evaluasi_petugas.php
```

### Routes yang Ditambahkan (di Router.php)

```
GET  /monitoring                          → MonitoringController@index         ['auth', 'admin_or_operator']
GET  /monitoring/petugas                  → MonitoringController@petugas        ['auth', 'admin_or_operator']
GET  /monitoring/petugas/{id}             → MonitoringController@detailPetugas  ['auth', 'admin_or_operator']
GET  /monitoring/evaluasi                 → MonitoringController@evaluasi       ['auth', 'admin_or_operator']
GET  /monitoring/export                   → MonitoringController@exportExcel    ['auth', 'admin_or_operator']
GET  /monitoring/print                    → MonitoringController@printPdf       ['auth', 'admin_or_operator']
GET  /monitoring/evaluasi/export          → MonitoringController@exportEvaluasi ['auth', 'admin']
GET  /monitoring/evaluasi/print           → MonitoringController@printEvaluasi  ['auth', 'admin_or_operator']
POST /monitoring/evaluasi/catatan         → MonitoringController@saveCatatan    ['auth', 'admin']
POST /monitoring/evaluasi/target          → MonitoringController@saveTarget     ['auth', 'admin']

GET  /api/monitoring/stats                → Api\MonitoringApiController@stats   ['auth', 'admin_or_operator']
GET  /api/monitoring/charts               → Api\MonitoringApiController@charts  ['auth', 'admin_or_operator']
GET  /api/monitoring/petugas              → Api\MonitoringApiController@petugas ['auth', 'admin_or_operator']
```

**Catatan:** Middleware `admin_or_operator` adalah middleware baru yang memeriksa `$_SESSION['role'] IN ('admin', 'operator')`. Ditambahkan di `Router::applyMiddleware()`.


---

## Database Design

### Tabel Baru: `evaluasi_petugas`

```sql
CREATE TABLE `evaluasi_petugas` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `periode_bulan` TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  `periode_tahun` SMALLINT UNSIGNED NOT NULL COMMENT 'YYYY',
  `catatan`       TEXT NULL DEFAULT NULL COMMENT 'Catatan manual admin, max 1000 char',
  `created_by`    INT UNSIGNED NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_periode` (`user_id`, `periode_bulan`, `periode_tahun`),
  KEY `idx_periode` (`periode_tahun`, `periode_bulan`),
  CONSTRAINT `fk_ep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ep_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabel Konfigurasi (menggunakan tabel config yang ada atau buat baru)

Nilai `target_laporan_bulanan` disimpan di tabel konfigurasi sistem. Jika tidak ada, dibuat tabel:

```sql
CREATE TABLE IF NOT EXISTS `monitoring_config` (
  `key`        VARCHAR(100) NOT NULL,
  `value`      VARCHAR(255) NOT NULL,
  `updated_by` INT UNSIGNED NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `monitoring_config` (`key`, `value`) VALUES ('target_laporan_bulanan', '10')
  ON DUPLICATE KEY UPDATE `value` = `value`;
```

### Tabel yang Dibaca (tidak dimodifikasi)

| Tabel | Kolom relevan untuk monitoring |
|-------|-------------------------------|
| `laporan_hama` | `id`, `user_id`, `tanggal`, `status`, `verified_at`, `foto_url`, `latitude`, `longitude`, `kecamatan_id`, `created_at` |
| `laporan_irigasi` | `id`, `user_id`, `tanggal`, `status`, `verified_at`, `foto_url`, `latitude`, `longitude`, `kecamatan_id`, `created_at` |
| `laporan_lainnya` | `id`, `user_id`, `tanggal_kejadian`, `status`, `verified_at`, `foto_url`, `latitude`, `longitude`, `kecamatan_id`, `created_at` |
| `users` | `id`, `nama_lengkap`, `role`, `aktif` |
| `master_kecamatan` | `id`, `nama_kecamatan` |

**Catatan penting:** Ketiga tabel laporan menggunakan nama kolom yang sedikit berbeda:
- `laporan_hama` dan `laporan_irigasi`: `tanggal` (DATE)
- `laporan_lainnya`: `tanggal_kejadian` (DATE)
- Kolom `nomor_laporan` di `laporan_hama` = `kode_laporan` di `laporan_lainnya`


---

## Model Design

### `MonitoringReport` — Query Agregat Lintas Tabel

Model ini tidak extend `Model` base class karena hanya membaca data secara agregat dari banyak tabel. Menggunakan raw PDO prepared statements.

#### Method utama:

```php
class MonitoringReport {
    private PDO $db;
    private CacheManager $cache;

    // Mengembalikan total dan per-kategori untuk periode tertentu
    // Return: ['total' => int, 'hama' => int, 'irigasi' => int, 'lainnya' => int]
    public function getStatsSummary(string $dateFrom, string $dateTo): array {}

    // Mengembalikan jumlah laporan per hari per kategori untuk grafik garis
    // Return: [['date' => 'Y-m-d', 'hama' => int, 'irigasi' => int, 'lainnya' => int], ...]
    public function getDailyTrend(string $dateFrom, string $dateTo): array {}

    // Mengembalikan daftar petugas dengan statistik, sudah include filter
    // $filters: ['kecamatan_id' => int|null, 'kategori' => string|null]
    // Return: [['user_id' => int, 'nama' => string, 'total' => int, 'kategori_dominan' => string,
    //           'avg_verif_days' => float|null], ...]
    public function getPetugasRanking(string $dateFrom, string $dateTo, array $filters = []): array {}

    // Riwayat laporan satu petugas (semua kategori, gabungan UNION)
    // Return: [['kode' => string, 'tanggal_kejadian' => string, 'kategori' => string,
    //           'kecamatan' => string, 'desa' => string, 'status' => string,
    //           'tanggal_verifikasi' => string|null], ...]
    public function getLaporanByPetugas(int $userId, string $dateFrom, string $dateTo): array {}

    // Statistik ringkasan untuk satu petugas
    // Return: ['total' => int, 'verified' => int, 'rejected' => int, 'avg_verif_days' => float|null]
    public function getStatsPetugas(int $userId, string $dateFrom, string $dateTo): array {}

    // Ekspor lengkap untuk Excel - tanpa LIMIT, dikelompokkan per hari
    public function getExportData(string $dateFrom, string $dateTo, array $filters = []): array {}

    private function buildDateRange(string $preset): array {}  // preset: 'today','week','month','year'
    private function cacheKey(string $method, ...$params): string {}
}
```

#### Implementasi `getStatsSummary` (contoh query):

```sql
-- Query menggunakan UNION ALL untuk agregat 3 tabel sekaligus
SELECT kategori, COUNT(*) as jumlah
FROM (
    SELECT 'hama' AS kategori FROM laporan_hama
    WHERE status != 'draft' AND DATE(tanggal) BETWEEN ? AND ?
    UNION ALL
    SELECT 'irigasi' AS kategori FROM laporan_irigasi
    WHERE status NOT IN ('Draf','draft') AND DATE(tanggal) BETWEEN ? AND ?
    UNION ALL
    SELECT 'lainnya' AS kategori FROM laporan_lainnya
    WHERE status != 'draft' AND DATE(tanggal_kejadian) BETWEEN ? AND ?
) t
GROUP BY kategori
```

**Penting:** `laporan_irigasi` menggunakan status berbeda (`Draf` dengan huruf kapital). Query harus handle kedua format.


### `EvaluasiPetugas` — Skor Evaluasi dan Catatan

```php
class EvaluasiPetugas extends Model {
    protected $table = 'evaluasi_petugas';

    // Hitung skor evaluasi bulanan untuk satu petugas
    // Return: [
    //   'skor_frekuensi' => float,        // 0-100
    //   'skor_ketepatan' => float,         // 0-100
    //   'skor_kelengkapan' => float,       // 0-100
    //   'skor_akurasi' => float|null,      // null = tidak dapat dihitung
    //   'skor_total' => float,             // 0-100
    //   'total_laporan' => int,
    //   'target_laporan' => int,
    // ]
    public function hitungSkor(int $userId, int $bulan, int $tahun): array {}

    // Ambil semua petugas aktif + skor untuk periode tertentu
    // Return: array of ['user_id', 'nama_lengkap', 'wilayah_utama', skor..., 'catatan']
    public function getRekapBulanan(int $bulan, int $tahun): array {}

    // Simpan atau hapus catatan evaluasi (sesuai Req 9)
    public function saveCatatan(int $userId, int $bulan, int $tahun, string $catatan, int $createdBy): bool {}

    // Ambil catatan yang tersimpan
    public function getCatatan(int $userId, int $bulan, int $tahun): ?array {}

    // Ambil target laporan bulanan dari konfigurasi
    public function getTargetBulanan(): int {}

    // Simpan target laporan bulanan baru
    public function saveTargetBulanan(int $target, int $updatedBy): bool {}
}
```

#### Implementasi `hitungSkor` — formula dari Requirement 7:

```php
private function hitungFrekuensi(int $totalLaporan, int $target): float {
    return min(($totalLaporan / $target) * 100, 100.0);
}

private function hitungKetepatan(int $userId, int $bulan, int $tahun): float {
    // Hitung laporan yang disubmit dalam 3 hari sejak tanggal kejadian
    // DATEDIFF(created_at, tanggal_kejadian) <= 3
    // Query UNION ALL ketiga tabel
}

private function hitungKelengkapan(int $userId, int $bulan, int $tahun): float {
    // Hitung laporan submitted yang punya foto DAN koordinat (latitude NOT NULL AND longitude NOT NULL)
}

private function hitungAkurasi(int $userId, int $bulan, int $tahun): ?float {
    // NULL jika verified+rejected == 0
    // verified / (verified + rejected) * 100 jika ada
}

public function hitungSkor(int $userId, int $bulan, int $tahun): array {
    // ...
    $akurasi = $this->hitungAkurasi($userId, $bulan, $tahun);
    if ($akurasi === null) {
        // Redistribusi bobot: 30/80, 25/80, 25/80
        $skorTotal = ($frekuensi * 0.375) + ($ketepatan * 0.3125) + ($kelengkapan * 0.3125);
    } else {
        $skorTotal = ($frekuensi * 0.30) + ($ketepatan * 0.25) + ($kelengkapan * 0.25) + ($akurasi * 0.20);
    }
    return [
        'skor_frekuensi' => round($frekuensi, 1),
        'skor_ketepatan' => round($ketepatan, 1),
        'skor_kelengkapan' => round($kelengkapan, 1),
        'skor_akurasi' => $akurasi !== null ? round($akurasi, 1) : null,
        'skor_total' => round($skorTotal, 1),
        // ...
    ];
}
```


---

## Controller Design

### `MonitoringController`

```php
class MonitoringController extends Controller {
    use LogsActivity;

    private MonitoringReport $reportModel;
    private EvaluasiPetugas $evaluasiModel;
    private CacheManager $cache;

    // GET /monitoring — halaman dashboard utama
    // Render view index.php, data diload via AJAX ke /api/monitoring/stats dan /api/monitoring/charts
    public function index(): void {}

    // GET /monitoring/petugas — halaman tabel peringkat petugas
    // Render view petugas.php, data diload via AJAX ke /api/monitoring/petugas
    public function petugas(): void {}

    // GET /monitoring/petugas/{id} — detail aktivitas satu petugas
    public function detailPetugas(int $id): void {}

    // GET /monitoring/evaluasi — rekapitulasi skor bulanan
    public function evaluasi(): void {}

    // GET /monitoring/export — unduh Excel monitoring (req 4)
    public function exportExcel(): void {}

    // GET /monitoring/print — halaman cetak PDF monitoring (req 4)
    public function printPdf(): void {}

    // GET /monitoring/evaluasi/export — unduh Excel evaluasi (req 8), admin only
    public function exportEvaluasi(): void {}

    // GET /monitoring/evaluasi/print — halaman cetak evaluasi PDF (req 8)
    public function printEvaluasi(): void {}

    // POST /monitoring/evaluasi/catatan — simpan/hapus catatan (req 9), admin only
    public function saveCatatan(): void {}

    // POST /monitoring/evaluasi/target — simpan target bulanan (req 7), admin only
    public function saveTarget(): void {}

    private function checkAdminOrOperator(): void {
        $this->checkAuth();
        if (!in_array($_SESSION['role'] ?? '', ['admin', 'operator'], true)) {
            http_response_code(403);
            $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
            $this->redirect('dashboard');
        }
    }

    private function checkAdminOnly(): void {
        $this->checkAuth();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $this->json(['success' => false, 'error' => 'Forbidden'], 403);
        }
    }

    private function parsePeriode(): array {
        // Parsing ?preset=today|week|month|year atau ?date_from=&date_to=
        // Return ['date_from' => 'Y-m-d', 'date_to' => 'Y-m-d']
        // Default: 30 hari terakhir (untuk dashboard/grafik)
        // Default: bulan berjalan (untuk petugas/evaluasi)
    }

    private function validateDateParam(string $date): bool {
        // Validasi format Y-m-d dan range logis
    }
}
```

### `Api\MonitoringApiController`

```php
class MonitoringApiController extends BaseApiController {

    // GET /api/monitoring/stats?preset=&date_from=&date_to=
    // Return JSON: {'total': 0, 'hama': 0, 'irigasi': 0, 'lainnya': 0, 'cache_time': '...'}
    public function stats(): void {}

    // GET /api/monitoring/charts?preset=&date_from=&date_to=&type=bar|pie|trend
    // Return JSON: {'labels': [], 'datasets': [...]}  — format Chart.js
    public function charts(): void {}

    // GET /api/monitoring/petugas?preset=&date_from=&date_to=&kecamatan_id=&kategori=
    // Return JSON: [{'user_id':1, 'nama':'...', 'total':5, 'kategori_dominan':'hama', 'avg_days':2.3}]
    public function petugas(): void {}
}
```


---

## Caching Strategy

Semua endpoint AJAX menggunakan CacheManager dengan prefix `monitoring:`.

| Cache Key Pattern | TTL | Invalidasi |
|---|---|---|
| `monitoring:stats:{dateFrom}:{dateTo}` | 900 detik | Submit/verify/reject/archive laporan |
| `monitoring:charts:trend:{dateFrom}:{dateTo}` | 900 detik | Submit/verify/reject laporan |
| `monitoring:charts:breakdown:{dateFrom}:{dateTo}` | 900 detik | Submit laporan |
| `monitoring:petugas:{dateFrom}:{dateTo}:{filters_hash}` | 900 detik | Submit/verify laporan |
| `monitoring:evaluasi:skor:{userId}:{bulan}:{tahun}` | 900 detik | Submit/verify laporan |

**Invalidasi cache:** Di setiap controller yang melakukan submit/verify/reject/archive, tambahkan:
```php
CacheManager::getInstance()->clearPrefix('monitoring:');
```

Pola ini mengikuti `clearDashboardCache()` yang sudah ada di `LaporanLainnyaController`.

---

## View Design

### `monitoring/index.php` — Dashboard Utama

Layout 3-bagian menggunakan AdminLTE cards:

```
┌─────────────────────────────────────────────────┐
│  FILTER BAR (periode preset + custom date range) │
│  [Hari Ini] [7 Hari] [Bulan Ini] [Tahun Ini]     │
│  [Kustom: __/__/__ s/d __/__/__]  [Terapkan]     │
│  Label: "Data per 07/08/2026 10:30 (WIB)"        │
└─────────────────────────────────────────────────┘
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│  TOTAL   │ │  HAMA    │ │ IRIGASI  │ │ LAINNYA  │
│  [n]     │ │  [n]     │ │  [n]     │ │  [n]     │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
┌────────────────────────┐ ┌──────────────────────┐
│  GRAFIK BATANG         │ │   DIAGRAM LINGKARAN  │
│  (per kategori)        │ │   (proporsi)         │
└────────────────────────┘ └──────────────────────┘
┌─────────────────────────────────────────────────┐
│  GRAFIK GARIS TREN (jumlah per hari)            │
└─────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────┐
│  [🔽 Export Excel]  [🖨️ Cetak PDF]              │
│  (disembunyikan untuk operator)                  │
└─────────────────────────────────────────────────┘
```

**Library grafik:** Chart.js (sudah tersedia di AdminLTE). Konfigurasi: `responsive: true`, `maintainAspectRatio: false`, `plugins.tooltip.enabled: true`.

### `monitoring/petugas.php` — Peringkat Petugas

```
┌─────────────────────────────────────────────────────────────────────┐
│  FILTER: [Periode ▼]  [Kecamatan ▼]  [Kategori ▼]  [Terapkan]      │
└─────────────────────────────────────────────────────────────────────┘
┌────┬──────────────────┬────────┬───────────────┬────────────────────┐
│  # │  Nama Petugas    │  Total │ Kat. Dominan  │  Avg. Waktu Verif  │
├────┼──────────────────┼────────┼───────────────┼────────────────────┤
│  1 │  [Link: Nama]    │   12   │    Hama       │      2.3 hari      │
│  2 │  [Link: Nama]    │    8   │    Irigasi    │       –            │
└────┴──────────────────┴────────┴───────────────┴────────────────────┘
```

### `monitoring/detail_petugas.php` — Riwayat Satu Petugas

```
┌───────────────────────────────────────────────────────────────┐
│  ← Kembali  |  Petugas: [Nama Petugas]                        │
│  FILTER PERIODE: [Periode ▼]                                  │
├───────────┬───────────┬──────────────┬──────────────────────  │
│   Total   │  Verified │    Ditolak   │  Avg. Waktu Verifikasi │
│    [n]    │    [n]    │     [n]      │       [n] hari         │
├───────────┴───────────┴──────────────┴──────────────────────  │
│  Daftar Laporan (tabel, sortir: terbaru dulu)                 │
│  Kode | Tgl Kejadian | Kategori | Wilayah | Status | Tgl Verif│
└───────────────────────────────────────────────────────────────┘
```

### `monitoring/evaluasi.php` — Skor Evaluasi Bulanan

```
┌─────────────────────────────────────────────────────────────────┐
│  Pilih Periode: [Bulan ▼] [Tahun ▼]  [Lihat]                   │
│  Target Laporan/Bulan: [10] [Simpan] (admin only)              │
└─────────────────────────────────────────────────────────────────┘
┌────┬──────────┬──────────┬─────────┬─────────┬─────────┬───────┐
│  # │  Petugas │  Wilayah │ Frekuensi│ Ketepatan│Kelengkpn│ Skor │
├────┼──────────┼──────────┼──────────┼──────────┼─────────┼───────┤
│  1 │  [Link]  │ Kec. A   │  100.0  │   80.0   │   90.0  │  90.5 │
└────┴──────────┴──────────┴──────────┴──────────┴─────────┴───────┘
[ 🔽 Unduh Excel ] (admin only)  [ 🖨️ Cetak PDF ]
```

**Klik link nama petugas** → buka panel accordion/modal dengan form catatan + skor breakdown + catatan tersimpan.


---

## Security Design

### Middleware Baru: `admin_or_operator`

Ditambahkan di `Router::applyMiddleware()`:

```php
case 'admin_or_operator':
    if (!isset($_SESSION['user_id'])) {
        // Redirect ke login (bukan JSON) karena ini web route
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'operator'], true)) {
        http_response_code(403);
        // Load error view atau redirect dengan error message
        $_SESSION['error'] = 'Anda tidak memiliki akses ke halaman ini';
        header('Location: ' . BASE_URL . 'dashboard');
        exit;
    }
    break;
```

### Validasi Input di Controller

```php
private function validateFilterParams(array $params): array {
    $errors = [];

    if (isset($params['date_from']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['date_from'])) {
        $errors[] = 'date_from: format harus Y-m-d';
    }
    if (isset($params['date_to']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['date_to'])) {
        $errors[] = 'date_to: format harus Y-m-d';
    }
    if (isset($params['kecamatan_id']) && !ctype_digit((string)$params['kecamatan_id'])) {
        $errors[] = 'kecamatan_id: harus integer positif';
    }
    if (isset($params['kategori']) && !in_array($params['kategori'], ['hama', 'irigasi', 'lainnya'], true)) {
        $errors[] = 'kategori: harus salah satu dari hama, irigasi, lainnya';
    }
    if (isset($params['user_id']) && !ctype_digit((string)$params['user_id'])) {
        $errors[] = 'user_id: harus integer positif';
    }

    if (!empty($errors)) {
        http_response_code(422);
        $this->json(['success' => false, 'errors' => $errors]);
    }

    return $params; // sanitized
}
```

### Invalidasi Cache di Controller yang Sudah Ada

Tambahkan `CacheManager::getInstance()->clearPrefix('monitoring:');` ke method berikut tanpa mengubah logika bisnis yang ada:

- `LaporanLainnyaController::submit()`, `verify()`, `reject()`, `archive()`
- `LaporanHamaController`: method submit, verify, reject
- `LaporanController` (irigasi): method submit, verify, reject, archive

---

## Export Design

### Excel Export (SimpleXLSXWriter)

```php
private function buildMonitoringExcel(array $data, string $dateFrom, string $dateTo): string {
    require_once ROOT_PATH . '/app/helpers/SimpleXLSXWriter.php';
    $writer = new SimpleXLSXWriter();

    // Header metadata (3 baris)
    $writer->writeSheetRow('Sheet1', ['Monitoring Pelaporan JAGAPADI']);
    $writer->writeSheetRow('Sheet1', [
        date('d/m/Y', strtotime($dateFrom)) . ' – ' . date('d/m/Y', strtotime($dateTo))
    ]);
    $writer->writeSheetRow('Sheet1', ['Dicetak pada: ' . date('d/m/Y H:i') . ' WIB']);
    $writer->writeSheetRow('Sheet1', []); // baris kosong

    // Header kolom
    $writer->writeSheetRow('Sheet1', [
        'Tanggal', 'Kategori', 'Jumlah Laporan', 'Terverifikasi', 'Ditolak', 'Petugas'
    ]);

    // Data rows
    foreach ($data as $row) {
        $writer->writeSheetRow('Sheet1', [
            $row['tanggal'], $row['kategori'], $row['jumlah'],
            $row['terverifikasi'], $row['ditolak'], $row['petugas']
        ]);
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'monitoring_');
    $writer->writeToFile($tmpFile);
    return $tmpFile;
}
```

### PDF Export (Print-to-PDF HTML)

Route `GET /monitoring/print` menghasilkan halaman HTML yang:
1. Include `<link rel="stylesheet">` AdminLTE print styles
2. Mengandung tabel data yang sama persis dengan view monitoring
3. Mengeksekusi `window.print()` via `onload` JavaScript
4. Menyertakan `@media print { .no-print { display: none; } }` di CSS

---

## Mapping Requirements ke Komponen

| Requirement | Komponen yang Mengimplementasikan |
|---|---|
| Req 1 (Kontrol Akses) | Middleware `admin_or_operator`, `checkAdminOnly()` |
| Req 2 (Statistik) | `MonitoringReport::getStatsSummary()`, `Api\MonitoringApiController::stats()`, `_stat_cards.php` |
| Req 3 (Visualisasi) | `MonitoringReport::getDailyTrend()`, `Api\MonitoringApiController::charts()`, Chart.js di `index.php` |
| Req 4 (Ekspor) | `MonitoringController::exportExcel()`, `MonitoringController::printPdf()`, SimpleXLSXWriter |
| Req 5 (Aktivitas Petugas) | `MonitoringReport::getPetugasRanking()`, `Api\MonitoringApiController::petugas()`, `petugas.php` |
| Req 6 (Detail Petugas) | `MonitoringReport::getLaporanByPetugas()`, `MonitoringController::detailPetugas()`, `detail_petugas.php` |
| Req 7 (Skor Evaluasi) | `EvaluasiPetugas::hitungSkor()` |
| Req 8 (Rekap & Unduh) | `EvaluasiPetugas::getRekapBulanan()`, `MonitoringController::exportEvaluasi()`, `evaluasi.php` |
| Req 9 (Catatan Manual) | `EvaluasiPetugas::saveCatatan()`, `MonitoringController::saveCatatan()`, form di `evaluasi.php` |
| Req 10 (Cache) | CacheManager dengan TTL 900s, `clearPrefix('monitoring:')` di controller laporan |
| Req 11 (Responsif) | Bootstrap 4 grid `col-md-3`, `table-responsive`, Chart.js `responsive: true` |
| Req 12 (Keamanan) | PDO prepared statements, `validateFilterParams()`, CSRF di form mutasi, `htmlspecialchars()` output |

