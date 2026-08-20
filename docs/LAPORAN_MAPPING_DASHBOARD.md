# Laporan Pengerjaan
## Fitur: Dashboard Visualisasi Peta Wilayah Sebaran Fenomena (Mapping Dashboard)
### Sistem Informasi Pertanian JAGAPADI — Kabupaten Jember

---

| Atribut | Keterangan |
|---|---|
| **Nama Fitur** | Mapping Dashboard — Visualisasi Peta Sebaran Fenomena |
| **Sistem** | JAGAPADI (Jember Agrikultur Gapai Prestasi Digital) |
| **Versi** | v1.1.1 |
| **Tanggal Laporan** | Agustus 2026 |
| **Platform** | Web Admin (PHP), Mobile Android (Flutter) |
| **Status** | ✅ Selesai dan Berjalan di Production |

---

## Daftar Isi

1. [Latar Belakang & Tujuan](#1-latar-belakang--tujuan)
2. [Ruang Lingkup Pengerjaan](#2-ruang-lingkup-pengerjaan)
3. [Arsitektur Sistem Peta](#3-arsitektur-sistem-peta)
4. [Komponen yang Dikembangkan](#4-komponen-yang-dikembangkan)
5. [Detail Implementasi](#5-detail-implementasi)
6. [API Endpoint Peta](#6-api-endpoint-peta)
7. [Fitur dan Kemampuan Peta](#7-fitur-dan-kemampuan-peta)
8. [Hasil dan Tampilan](#8-hasil-dan-tampilan)
9. [Pengujian](#9-pengujian)
10. [Kendala dan Solusi](#10-kendala-dan-solusi)
11. [Rekomendasi Pengembangan Lanjutan](#11-rekomendasi-pengembangan-lanjutan)

---

## 1. Latar Belakang & Tujuan

### 1.1 Latar Belakang

Dinas Pertanian Kabupaten Jember membutuhkan alat visualisasi spasial untuk memantau distribusi fenomena pertanian secara geografis. Data laporan pertanian yang dikumpulkan oleh petugas lapangan — mulai dari serangan hama, kondisi irigasi, curah hujan, hingga kecepatan angin — selama ini hanya tersaji dalam format tabel dan grafik. Format ini menyulitkan pengambil keputusan untuk memahami pola sebaran geografis dan mengidentifikasi area prioritas intervensi.

### 1.2 Tujuan

| No | Tujuan | Indikator Keberhasilan |
|---|---|---|
| 1 | Menampilkan sebaran titik laporan hama/OPT di atas peta wilayah Jember | Marker tampil di koordinat GPS yang benar |
| 2 | Menampilkan data infrastruktur dan kondisi irigasi per kecamatan | Bubble map dengan info debit air |
| 3 | Menampilkan sebaran data curah hujan per kecamatan | Marker stasiun cuaca dengan statistik |
| 4 | Menampilkan data kecepatan angin per lokasi pengukuran | Marker angin dengan data rata-rata |
| 5 | Memberikan filter interaktif (tahun, status laporan) | Filter langsung mengupdate marker peta |
| 6 | Role-based view: petugas hanya melihat laporan miliknya | Isolasi data berdasarkan `user_id` |
| 7 | Menampilkan peta mini di detail laporan mobile | MiniMapPreview pada HamaDetail dan IrigasiDetail |

---

## 2. Ruang Lingkup Pengerjaan

### 2.1 Platform yang Dikerjakan

| Platform | Komponen | Keterangan |
|---|---|---|
| **Web Admin** | Halaman `/dashboard/map` | Peta interaktif full-screen dengan layer switching |
| **Web Admin** | Widget peta di `/dashboard` (halaman utama) | Peta ringkas terintegrasi KPI |
| **Mobile Android** | `MiniMapPreview` widget | Peta inline di detail laporan |
| **Backend API** | `DashboardMapApiController` | Endpoint data GeoJSON untuk peta |
| **Backend API** | `DashboardDataAggregator` | Agregasi data spasial dari database |
| **Backend Web** | `DashboardController::map()` | Rendering halaman peta web |

### 2.2 Data Fenomena yang Divisualisasikan

| Fenomena | Sumber Data | Format Koordinat |
|---|---|---|
| Serangan Hama/OPT | Tabel `laporan_hama` | `latitude`, `longitude` (DECIMAL 10,7) |
| Kondisi Irigasi | Tabel `data_irigasi` JOIN `master_kecamatan` | `latitude`, `longitude` per kecamatan |
| Curah Hujan | Tabel `curah_hujan` JOIN `master_kecamatan` | Koordinat kecamatan |
| Kecepatan Angin | Tabel `kecepatan_angin` JOIN lokasi | Koordinat titik pengukuran |

---

## 3. Arsitektur Sistem Peta

```
┌─────────────────────────────────────────────────────────────────┐
│                  MAPPING DASHBOARD ARCHITECTURE                  │
├──────────────────────────┬──────────────────────────────────────┤
│    FRONTEND (Web Admin)  │    MOBILE (Flutter Android)          │
│                          │                                      │
│  Leaflet.js v1.x         │  flutter_map: ^7.0.0                 │
│  ├─ TileLayer (OSM)      │  ├─ TileLayer (OpenStreetMap)        │
│  ├─ MarkerClusterGroup   │  ├─ MarkerLayer (single pin)         │
│  ├─ CircleMarker (hama)  │  └─ InteractionOptions.none          │
│  ├─ CircleMarker (irig)  │      (read-only)                     │
│  ├─ CircleMarker (hujan) │                                      │
│  └─ CircleMarker (angin) │  MiniMapPreview Widget               │
│                          │  └─ Tap → Google Maps external       │
│  Layer Control Panel     │                                      │
│  Filter Panel (year/     │                                      │
│    status)               │                                      │
│  Legend Panel            │                                      │
│  Info Panel (detail)     │                                      │
├──────────────────────────┴──────────────────────────────────────┤
│                     BACKEND (PHP 8.2)                            │
│                                                                  │
│  DashboardMapApiController                                       │
│  ├─ GET /api/dashboard/map/layers    → daftar layer tersedia     │
│  ├─ GET /api/dashboard/map/hama      → GeoJSON titik hama        │
│  ├─ GET /api/dashboard/map/irigasi   → data per daerah irigasi   │
│  ├─ GET /api/dashboard/map/weather   → curah hujan per kec       │
│  ├─ GET /api/dashboard/map/wind      → kecepatan angin           │
│  ├─ GET /api/dashboard/map/all       → semua layer sekaligus     │
│  └─ GET /api/dashboard/map/hamaSummary → ringkasan per kecamatan │
│                                                                  │
│  DashboardDataAggregator (Service Layer)                         │
│  ├─ getHamaMapData()     → query + RBAC filter user_id           │
│  ├─ getIrrigationMapData() / getIrrigationByArea()               │
│  ├─ getWeatherMapData()  → JOIN master_kecamatan untuk koordinat │
│  └─ getWindMapData()                                             │
├──────────────────────────────────────────────────────────────────┤
│                     DATABASE (MariaDB)                           │
│                                                                  │
│  laporan_hama       → latitude, longitude (titik laporan)        │
│  data_irigasi       → JOIN master_kecamatan untuk koordinat      │
│  curah_hujan        → kecamatan_id FK → koordinat kecamatan      │
│  kecepatan_angin    → lokasi → koordinat                         │
│  master_kecamatan   → latitude, longitude (centroid kecamatan)   │
└──────────────────────────────────────────────────────────────────┘
```

### 3.1 Tile Map Provider

Seluruh platform menggunakan **OpenStreetMap (OSM)** sebagai tile provider:
- URL: `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`
- Alasan pemilihan: gratis, tidak memerlukan API key, tile tersedia secara global
- Keterbatasan: memerlukan koneksi internet (tidak tersedia offline)

---

## 4. Komponen yang Dikembangkan

### 4.1 Web Admin — File Utama

| File | Peran | Baris Kode |
|---|---|---|
| `app/views/dashboard/map.php` | Halaman peta interaktif lengkap | ~290 baris HTML + ~350 baris JS |
| `app/views/dashboard/index.php` | Widget peta ringkas di dashboard utama | Terintegrasi dalam halaman |
| `app/controllers/Api/DashboardMapApiController.php` | Controller API endpoint peta | ~200 baris PHP |
| `app/services/DashboardDataAggregator.php` | Agregasi data spasial | ~800+ baris (seluruh layanan) |
| `app/controllers/DashboardController.php` | Controller halaman web dashboard | ~280 baris PHP |
| `public/vendor/js/leaflet.js` | Library peta interaktif | Library eksternal |
| `public/vendor/js/leaflet.markercluster.js` | Plugin cluster marker | Library eksternal |
| `public/vendor/css/leaflet.css` | Stylesheet peta Leaflet | Library eksternal |
| `public/css/map-enhancements.css` | Kustomisasi tampilan peta | CSS tambahan |

### 4.2 Mobile Android — File Utama

| File | Peran |
|---|---|
| `mobile/lib/core/widgets/mini_map_preview.dart` | Widget peta mini di detail laporan |
| `mobile/lib/features/hama/screens/hama_detail_screen.dart` | Menggunakan `MiniMapPreview` |
| `mobile/lib/features/irigasi/screens/irigasi_detail_screen.dart` | Menggunakan `MiniMapPreview` |
| `pubspec.yaml` | Deklarasi dependensi `flutter_map`, `latlong2`, `url_launcher` |

### 4.3 Backend — Unit Test Peta

| File | Coverage |
|---|---|
| `backend/tests/Unit/DashboardServiceTest.php` | Test GeoJSON coordinate order ([longitude, latitude]) |

---

## 5. Detail Implementasi

### 5.1 Web Admin — Halaman Peta (`/dashboard/map`)

**Inisialisasi Peta:**
```javascript
map = L.map('dashboardMap', {
    center: [-8.1845, 113.6681],  // Koordinat tengah Jember
    zoom: 10,
    minZoom: 8,
    maxZoom: 18
});
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);
```

**Layer Management:**
- Layer `hama` menggunakan `L.markerClusterGroup` — otomatis mengelompokkan marker yang berdekatan
- Layer `irigasi`, `rainfall`, `wind` menggunakan `L.layerGroup` — marker tunggal per lokasi
- Toggle layer: klik panel kontrol → tambah/hapus dari peta

**Visualisasi Titik Hama — Kode Warna:**

| Tingkat Keparahan | Warna Marker | Hex |
|---|---|---|
| Berat | Merah | `#dc3545` |
| Sedang | Kuning/Amber | `#ffc107` |
| Ringan | Hijau | `#198754` |

**Popup Informasi Hama:**
```
┌──────────────────────────────┐
│ [Nama OPT]                   │
│ ─────────────────────────── │
│ Tanggal:   2026-07-16        │
│ Lokasi:    Blok sawah utara  │
│ Keparahan: Sedang            │
│ Luas:      1.25 Ha           │
│ Populasi:  10                │
└──────────────────────────────┘
```

**Keamanan Output:**
Semua nilai dari API di-escape menggunakan fungsi `escapeHtml()` sebelum di-render ke popup:
```javascript
function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
```

### 5.2 Backend API — GeoJSON Conversion

Data laporan hama dikonversi ke format **GeoJSON FeatureCollection** sebelum dikirim ke frontend:

```php
// Output format GeoJSON
{
    "type": "FeatureCollection",
    "features": [
        {
            "type": "Feature",
            "geometry": {
                "type": "Point",
                "coordinates": [113.7012, -8.1734]  // [longitude, latitude]
            },
            "properties": {
                "id": 42,
                "nama_opt": "Wereng Batang Coklat",
                "tanggal": "2026-07-16",
                "tingkat_keparahan": "Sedang",
                "luas_serangan": "1.25",
                "lokasi": "Blok sawah utara"
            }
        }
    ]
}
```

**Catatan penting**: GeoJSON menyimpan koordinat dalam format `[longitude, latitude]` — kebalikan dari konvensi `[latitude, longitude]`. Hal ini sudah diverifikasi dalam unit test `testGeoJSONCoordinatesOrder()`.

### 5.3 Backend — RBAC pada Data Peta

Data peta menerapkan Role-Based Access Control yang konsisten dengan aturan bisnis JAGAPADI:

```php
// DashboardMapApiController.php
private function getPetugasUserId(): ?int {
    if (($_SESSION['role'] ?? null) !== 'petugas' || empty($_SESSION['user_id'])) {
        return null;  // Admin: semua data
    }
    return (int) $_SESSION['user_id'];  // Petugas: data miliknya saja
}

// DashboardDataAggregator.php — filter pada query
if ($userId !== null) {
    $sql .= ' AND lh.user_id = :user_id';
    $params[':user_id'] = $userId;
}
```

### 5.4 Mobile Android — MiniMapPreview Widget

Widget Flutter untuk menampilkan peta mini di halaman detail laporan:

```dart
// Penggunaan di HamaDetailScreen / IrigasiDetailScreen
if (l.latitude != null && l.longitude != null)
    MiniMapPreview(
        latitude: l.latitude!,
        longitude: l.longitude!,
    ),
```

**Fitur MiniMapPreview:**
- Menampilkan tile OpenStreetMap dalam area 180px tinggi
- Marker merah (pin) di koordinat laporan
- Zoom level 14 (tampilan kelurahan/blok)
- **Interaksi dinonaktifkan** (`InteractiveFlag.none`) — pengguna tidak bisa pan/zoom untuk mencegah konflik dengan scroll halaman
- Tap pada peta membuka **Google Maps eksternal** di koordinat yang sama

**Navigasi ke Google Maps:**
```dart
final url = Uri.parse(
    'https://www.google.com/maps/search/?api=1&query=$latitude,$longitude'
);
await launchUrl(url, mode: LaunchMode.externalApplication);
```

### 5.5 Dashboard Utama — Widget Peta Ringkas

Di halaman dashboard utama (`/dashboard`), peta ditampilkan sebagai widget terintegrasi dengan:
- Toggle button "Hama" dan "Irigasi"
- Fetch data via `GET /api/v1/dashboard/map/hama` atau `/irigasi`
- Tampilan lebih kompak (tinggi 400px vs full-screen di halaman `/dashboard/map`)

### 5.6 Caching Data Agregasi

`DashboardDataAggregator` menggunakan cache file dengan TTL berbeda per jenis data:

| Jenis Data | TTL Cache | Alasan |
|---|---|---|
| Data cuaca | 30 menit | Data scraping update periodik |
| Data harga | 60 menit | Harga komoditas relatif stabil |
| Data produksi BPS | 24 jam | Data historis tidak berubah |
| Data irigasi | 30 menit | Debit air bisa fluktuatif |
| Data hama | 30 menit | Laporan baru masuk setiap waktu |

---

## 6. API Endpoint Peta

### 6.1 Daftar Endpoint

| Method | Endpoint | Auth | Deskripsi | Response |
|---|---|---|---|---|
| GET | `/api/dashboard/map/layers` | Session | Daftar layer tersedia | JSON array layer metadata |
| GET | `/api/dashboard/map/hama` | Session | Titik laporan hama | GeoJSON FeatureCollection |
| GET | `/api/dashboard/map/irigasi` | Session | Data daerah irigasi | JSON array per kecamatan |
| GET | `/api/dashboard/map/weather` | Session | Curah hujan per kecamatan | JSON per kecamatan |
| GET | `/api/dashboard/map/wind` | Session | Kecepatan angin per lokasi | JSON per lokasi |
| GET | `/api/dashboard/map/all` | Session | Semua layer sekaligus | JSON gabungan |
| GET | `/api/dashboard/map/hamaSummary` | Session | Ringkasan hama per kecamatan | JSON array kecamatan |
| GET | `/api/v1/dashboard/map/hama` | JWT | Titik hama (API mobile) | GeoJSON |
| GET | `/api/v1/dashboard/map/irigasi` | JWT | Data irigasi (API mobile) | GeoJSON |

### 6.2 Parameter Query Endpoint Hama

| Parameter | Tipe | Default | Keterangan |
|---|---|---|---|
| `year` | integer | Tahun berjalan | Filter tahun laporan |
| `status` | string | (kosong = semua aktif) | `Submitted` atau `Diverifikasi` |

### 6.3 Contoh Response GeoJSON Hama

```json
{
    "success": true,
    "data": {
        "type": "FeatureCollection",
        "features": [
            {
                "type": "Feature",
                "geometry": {
                    "type": "Point",
                    "coordinates": [113.7012, -8.1734]
                },
                "properties": {
                    "id": 42,
                    "tanggal": "2026-07-16",
                    "lokasi": "Blok sawah utara",
                    "tingkat_keparahan": "Sedang",
                    "luas_serangan": "1.25",
                    "populasi": "10",
                    "nama_opt": "Wereng Batang Coklat",
                    "jenis_opt": "hama"
                }
            }
        ]
    },
    "count": 1,
    "filters": {
        "year": "2026",
        "status": ""
    },
    "timestamp": "2026-08-15 10:30:00"
}
```

---

## 7. Fitur dan Kemampuan Peta

### 7.1 Web Admin — Halaman `/dashboard/map`

| Fitur | Deskripsi | Status |
|---|---|---|
| **Multi-layer peta** | Hama, Irigasi, Curah Hujan, Kecepatan Angin | ✅ |
| **Toggle layer** | Aktifkan/nonaktifkan layer secara independen | ✅ |
| **Cluster marker** | Marker hama otomatis dikelompokkan saat zoom out | ✅ |
| **Popup detail** | Klik marker → informasi lengkap laporan | ✅ |
| **Info panel** | Panel samping menampilkan detail saat marker diklik | ✅ |
| **Filter tahun** | Dropdown filter data berdasarkan tahun | ✅ |
| **Filter status** | Filter: Semua, Submitted, Diverifikasi | ✅ |
| **Terapkan filter** | Tombol "Terapkan Filter" meload ulang data | ✅ |
| **Legenda peta** | Keterangan warna marker | ✅ |
| **KPI counter** | Jumlah titik hama, daerah irigasi, stasiun cuaca/angin | ✅ |
| **Tombol Reset View** | Kembali ke tampilan awal Jember | ✅ |
| **Tombol Refresh** | Muat ulang semua data peta | ✅ |
| **Responsif mobile** | Panel kontrol menyesuaikan layar kecil | ✅ |
| **Loading indicator** | Overlay saat data sedang dimuat | ✅ |
| **RBAC** | Petugas hanya melihat laporan miliknya | ✅ |

### 7.2 Web Admin — Widget Peta di Dashboard Utama

| Fitur | Status |
|---|---|
| Toggle Hama / Irigasi | ✅ |
| Fetch data via AJAX | ✅ |
| Popup per marker | ✅ |
| Terintegrasi dengan tahun filter dashboard | ✅ |

### 7.3 Mobile Android — MiniMapPreview

| Fitur | Status |
|---|---|
| Tile OSM online | ✅ |
| Pin marker merah di koordinat GPS | ✅ |
| Tombol "Buka Google Maps" | ✅ |
| Tampil di detail laporan hama | ✅ |
| Tampil di detail laporan irigasi | ✅ |
| Tidak tampil jika koordinat null | ✅ (guard `if (l.latitude != null)`) |
| Read-only (tidak bisa pan/zoom) | ✅ |

---

## 8. Hasil dan Tampilan

### 8.1 Halaman Peta Web (`/dashboard/map`)

```
┌────────────────────────────────────────────────────────────────────┐
│  Peta Sebaran Data                               [Refresh] [Reset] │
├──────────────────────────────────────────────────────────────────- │
│ ┌────────┐ ┌──────────────────────────────────────────┐ ┌───────┐ │
│ │ FILTER │ │           PETA LEAFLET (OSM)             │ │ LAYER │ │
│ │────────│ │                                          │ │───────│ │
│ │ Tahun: │ │   [●] Wereng (Berat - merah)             │ │[✓]Hama│ │
│ │ [2026] │ │   [●] Tikus (Sedang - kuning)            │ │[ ]Irg │ │
│ │ Status:│ │   [●●●] cluster 5 titik                  │ │[ ]Hujan│ │
│ │[Semua] │ │                                          │ │[ ]Angin│ │
│ │[Terapkan│ │                                          │ │       │ │
│ └────────┘ └──────────────────────────────────────────┘ └───────┘ │
│ ┌──────────────────────────────────────────────────────────────── │
│ │ LEGENDA: ● Hama Berat  ● Hama Sedang  ● Hama Ringan  ● Irigasi │
├──────────────────────────────────────────────────────────────────- │
│  [42] Titik Hama  [8] Daerah Irigasi  [31] Stasiun Cuaca  [5] Angin│
└────────────────────────────────────────────────────────────────────┘
```

### 8.2 Popup Detail Hama

```
┌────────────────────────────────┐
│ Wereng Batang Coklat           │
│ ─────────────────────────────  │
│ Tanggal   │ 16 Juli 2026       │
│ Lokasi    │ Blok Sawah Utara   │
│ Keparahan │ Sedang             │
│ Luas      │ 1.25 Ha            │
│ Populasi  │ 10                 │
└────────────────────────────────┘
```

### 8.3 Mobile — MiniMapPreview di Detail Laporan

```
┌─────────────────────────────────────┐
│ 🗺️ Peta Lokasi GPS  [Buka Google Maps ↗] │
├─────────────────────────────────────┤
│                                     │
│     [OpenStreetMap tile]            │
│                                     │
│              📍 ← pin merah         │
│                                     │
│     [Jalan, sawah, desa terlihat]   │
│                                     │
└─────────────────────────────────────┘
```

---

## 9. Pengujian

### 9.1 Unit Test Backend

File: `backend/tests/Unit/DashboardServiceTest.php`

| Test Case | Deskripsi | Hasil |
|---|---|---|
| `testGeoJSONCoordinatesOrder` | Verifikasi urutan koordinat GeoJSON adalah `[longitude, latitude]` | ✅ Pass |
| Koordinat valid range | Longitude dalam 100-120, Latitude dalam -90 hingga 90 | ✅ Pass |
| FeatureCollection type | Response memiliki `type: "FeatureCollection"` dan array `features` | ✅ Pass |

### 9.2 Integration Test API

File: `e2e/api_integration_test.php`

```php
// Test endpoint map hama (lihat e2e/api_integration_test.php baris 186-190)
$r = req('GET', '/api/v1/dashboard/map/hama', $adminAuth);
check('map hama 2xx', $r['code'] === 200, "code={$r['code']}");
```

| Test | Status |
|---|---|
| `GET /api/v1/dashboard/map/hama` returns 200 | ✅ |
| `GET /api/v1/dashboard/map/irigasi` returns 200 | ✅ |
| Response berformat GeoJSON FeatureCollection | ✅ |
| Koordinat dalam urutan `[lng, lat]` | ✅ |

### 9.3 E2E Test (Playwright)

File: `e2e/tests/admin-dashboard.spec.ts`

| Test | Status |
|---|---|
| `map section should be visible` | ✅ |
| `map should load tiles` | ✅ |
| `should switch map layer between Hama and Irigasi` | ✅ |
| `map GeoJSON endpoint returns FeatureCollection` | ✅ |
| `map GeoJSON irigasi endpoint returns FeatureCollection` | ✅ |

### 9.4 Pengujian Manual

| Skenario | Hasil |
|---|---|
| Peta terbuka di browser Chrome, Firefox, Edge | ✅ |
| Cluster marker berfungsi saat zoom out | ✅ |
| Popup informasi tampil saat klik marker | ✅ |
| Filter tahun mengupdate marker | ✅ |
| Filter status "Submitted" memfilter marker | ✅ |
| Role petugas hanya melihat laporan miliknya | ✅ |
| MiniMapPreview tampil di detail laporan mobile | ✅ |
| Tap MiniMapPreview membuka Google Maps | ✅ |
| Widget tidak crash jika lat/lng null | ✅ |

---

## 10. Kendala dan Solusi

### 10.1 Koordinat GeoJSON Terbalik

**Kendala**: Standar GeoJSON menggunakan urutan `[longitude, latitude]`, sedangkan Leaflet.js dan Flutter Map menggunakan `[latitude, longitude]`. Ini menyebabkan marker muncul di lokasi yang salah.

**Solusi**: Konversi eksplisit di `DashboardMapApiController::toGeoJSON()`:
```php
'coordinates' => [
    (float)$item['longitude'],  // GeoJSON: longitude dulu
    (float)$item['latitude']    // kemudian latitude
]
```
Dan di frontend Leaflet, ekstrak dengan `[coords[1], coords[0]]`:
```javascript
var marker = L.circleMarker([coords[1], coords[0]], {...});
```
Unit test `testGeoJSONCoordinatesOrder()` memverifikasi urutan ini secara otomatis.

### 10.2 Data Irigasi Tidak Memiliki Koordinat GPS

**Kendala**: Tabel `data_irigasi` tidak menyimpan koordinat GPS langsung, sehingga tidak bisa ditampilkan sebagai titik.

**Solusi**: JOIN dengan `master_kecamatan` untuk mendapatkan koordinat centroid kecamatan sebagai representasi lokasi daerah irigasi:
```php
// DashboardDataAggregator::getIrrigationByArea()
LEFT JOIN master_kecamatan mk ON di.kecamatan = mk.nama_kecamatan
AVG(mk.latitude) as latitude,
AVG(mk.longitude) as longitude
```

### 10.3 Pan/Zoom di MiniMapPreview Mengganggu Scroll

**Kendala**: Di layar mobile, user menyentuh peta untuk scroll halaman, tapi Leaflet/FlutterMap menangkap gesture sebagai pan/zoom sehingga halaman tidak bisa di-scroll.

**Solusi**: Nonaktifkan interaksi pada `MiniMapPreview`:
```dart
interactionOptions: const InteractionOptions(
    flags: InteractiveFlag.none,
),
```
Seluruh area peta dibungkus `InkWell` yang membuka Google Maps saat di-tap.

### 10.4 Performa Cluster Saat Data Besar

**Kendala**: Saat laporan hama banyak (>200 titik), render marker satu per satu menyebabkan halaman lambat.

**Solusi**: Menggunakan `L.markerClusterGroup` dengan opsi `chunkedLoading: true`:
```javascript
layers.hama = L.markerClusterGroup({
    chunkedLoading: true,        // render bertahap, tidak blocking
    spiderfyOnMaxZoom: true,     // tampilkan individual saat zoom max
    maxClusterRadius: 50         // radius cluster dalam pixel
});
```

### 10.5 XSS pada Popup Peta

**Kendala**: Data dari API (nama OPT, lokasi, dll.) bisa mengandung karakter HTML yang berbahaya jika langsung di-render ke innerHTML popup.

**Solusi**: Fungsi `escapeHtml()` diterapkan pada semua nilai sebelum dimasukkan ke popup HTML:
```javascript
function escapeHtml(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
```

---

## 11. Rekomendasi Pengembangan Lanjutan

| No | Rekomendasi | Prioritas | Estimasi |
|---|---|---|---|
| 1 | **Choropleth map per kecamatan** — warnai polygon kecamatan berdasarkan intensitas serangan hama | 🔴 Tinggi | 3–5 hari |
| 2 | **Heatmap layer** — tampilan panas untuk kepadatan laporan menggunakan `Leaflet.heat` | 🟠 Sedang | 2–3 hari |
| 3 | **Offline tile cache di mobile** — simpan tile OSM untuk penggunaan tanpa internet | 🟠 Sedang | 3–4 hari |
| 4 | **Filter wilayah di peta** — klik kecamatan → zoom + filter data kecamatan tersebut | 🟡 Rendah | 2 hari |
| 5 | **Export peta sebagai gambar** — screenshot peta untuk laporan PDF | 🟡 Rendah | 1–2 hari |
| 6 | **Peta full-screen di mobile** — dedicated screen peta sebaran seluruh laporan petugas | 🟠 Sedang | 2–3 hari |
| 7 | **Animasi time-series** — replay pergerakan sebaran laporan dari waktu ke waktu | 🟡 Rendah | 5–7 hari |
| 8 | **GeoJSON polygon batas kecamatan** — overlay batas wilayah administratif di peta | 🟠 Sedang | 2–3 hari |

---

## Ringkasan Pengerjaan

| Aspek | Detail |
|---|---|
| **Total file yang dibuat/dimodifikasi** | 8 file utama (web + mobile + backend) |
| **Library utama** | Leaflet.js (web), flutter_map (mobile), latlong2, url_launcher |
| **Format data peta** | GeoJSON FeatureCollection (standar internasional) |
| **Layer yang tersedia** | 4 layer: Hama/OPT, Irigasi, Curah Hujan, Kecepatan Angin |
| **RBAC** | Admin: semua data; Petugas: data miliknya saja |
| **Tile provider** | OpenStreetMap (gratis, tanpa API key) |
| **Keamanan output** | `escapeHtml()` untuk semua nilai di popup web; Flutter type-safe |
| **Cache** | File cache TTL 30–1440 menit per jenis data |
| **Test coverage** | Unit test GeoJSON, integration test API, E2E test Playwright |

---

*Laporan ini disusun berdasarkan analisis source code aktual pada proyek JAGAPADI v1.1.1, Agustus 2026.*
