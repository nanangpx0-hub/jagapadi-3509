
# Dokumentasi Aplikasi JAGAPADI (Jember Agrikultur Gapai Prestasi Digital)

Dokumentasi lengkap aplikasi JAGAPADI untuk pengembangan, pemeliharaan, dan penggunaan.

---

## Daftar Isi
1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Arsitektur Aplikasi](#2-arsitektur-aplikasi)
3. [Panduan Instalasi dan Konfigurasi](#3-panduan-instalasi-dan-konfigurasi-lingkungan-pengembangan)
4. [Dokumentasi Fungsional Fitur](#4-dokumentasi-fungsional-fitur-aplikasi)
5. [Dokumentasi API Endpoint](#5-dokumentasi-api-endpoint)
6. [Struktur Database](#6-struktur-database)
7. [Panduan Pengembangan dan Standar Kode](#7-panduan-pengembangan-dan-standar-kode)
8. [Panduan Pengujian](#8-panduan-pengujian-aplikasi)
9. [Panduan Deployment dan Pemeliharaan Produksi](#9-panduan-deployment-dan-pemeliharaan-produksi)
10. [Pemeliharaan dan Riwayat Versi](#10-pemeliharaan-dan-riwayat-versi)
11. [Panduan Troubleshooting Umum](#11-panduan-troubleshooting-umum)
12. [Lampiran](#12-lampiran)

---

## 1. Ringkasan Eksekutif

### 1.1 Latar Belakang
JAGAPADI adalah aplikasi web berbasis PHP yang dikembangkan oleh **BPS Kabupaten Jember** untuk mendukung transformasi pertanian tradisional menjadi pertanian cerdas berbasis data. Aplikasi ini membantu petani, operator, dan administrator dalam memantau dan mengelola data pertanian secara terintegrasi.

### 1.2 Tujuan Utama
- Memudahkan monitoring dan manajemen data hama/OPT (Organisme Pengganggu Tanaman)
- Mengelola data irigasi dan IoT (sensor/aktuator)
- Memantau kondisi cuaca dan curah hujan
- Menganalisis harga komoditas dan produksi gabah
- Menyediakan dashboard analitik dan storytelling data

### 1.3 Target Pengguna
- **Petani**: Mengakses informasi pertanian, membuat laporan
- **Operator**: Memverifikasi laporan, mengelola data
- **Statistisi**: Menganalisis data, membuat storytelling
- **Admin**: Mengelola pengguna, master data, dan sistem

### 1.4 Lingkup Fitur Utama
| Modul | Deskripsi |
|-------|-----------|
| Auth &amp; User Management | Login, role, profil, pergantian password |
| Laporan Hama/OPT | Input laporan, peta, analytics, export |
| Master Wilayah | Kabupaten, kecamatan, desa (berbasis kode BPS) |
| Irigasi &amp; IoT | Data irigasi, laporan, rules, monitoring sensor |
| Cuaca &amp; Angin | Curah hujan, kecepatan angin, scraping |
| Harga &amp; Gabah/Beras | Harga komoditas, produksi, analytics |
| Feedback | Pelaporan bug/usulan fitur |
| Storytelling/Evaluasi | Analisis produksi, evaluasi akurasi panen |

---

## 2. Arsitektur Aplikasi

### 2.1 Stack Teknologi

#### Backend
- **Bahasa**: PHP &gt;= 8.2
- **Framework**: Custom MVC (tanpa framework full-stack)
- **Database**: MySQL/MariaDB (via PDO)
- **Dependency**: Composer (untuk autoload dan PHPUnit)

#### Frontend
- **View**: Server-rendered PHP (`.php` di `app/views/`)
- **CSS/JS Library**:
  - AdminLTE 3.2 (dashboard template)
  - Bootstrap 4.6
  - jQuery 3.6.0
  - Chart.js 4.4.0
  - Leaflet 1.9.4 (peta) + MarkerCluster 1.5.3
- **Build Tool**: Vite 5.4.0

#### Alat Pengembangan
- **Testing**: PHPUnit 11.0
- **CI/CD**: GitHub Actions (`.github/workflows/ci.yml`)
- **Version Control**: Git

### 2.2 Struktur Direktori
```
jagapadi/
├── app/                     # Aplikasi inti
│   ├── controllers/         # Controller (web &amp; API)
│   │   ├── Api/             # Controller API
│   ├── core/                # Core framework (Router, Model, dll)
│   ├── helpers/             # Helper functions
│   ├── middleware/          # Middleware (auth, dll)
│   ├── models/              # Model (interaksi database)
│   ├── services/            # Service layer (bisnis logic)
│   └── views/               # View (template PHP)
├── config/                  # Konfigurasi
│   ├── config.php           # Konfigurasi umum
│   └── database.php         # Konfigurasi database
├── database/                # Database
│   ├── migrations/          # Migration
│   └── maintenance/         # Script maintenance
├── public/                  # Aset publik
│   ├── css/                 # File CSS
│   ├── js/                  # File JS
│   └── manifest.json        # PWA manifest
├── scripts/                 # Script operasional
├── storage/                 # Storage (upload, cache)
├── tests/                   # Test (PHPUnit)
├── .env.example             # Template konfigurasi
├── index.php                # Entry point aplikasi
├── composer.json            # Konfigurasi Composer
├── package.json             # Konfigurasi npm/Vite
└── vite.config.js           # Konfigurasi Vite
```

### 2.3 Diagram Arsitektur Sistem
```
┌─────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│   Pengguna      │────────▶│    Web Server    │────────▶│   Aplikasi PHP   │
│  (Browser/App)  │◀────────│  (Apache/Nginx)  │◀────────│   (index.php)    │
└─────────────────┘         └──────────────────┘         └────────┬─────────┘
                                                                   │
                          ┌────────────────────────────────────────┼─────────────────────────────────┐
                          │                                        │                                 │
                          ▼                                        ▼                                 ▼
                  ┌─────────────────┐                    ┌──────────────────┐           ┌──────────────────────┐
                  │     Router      │                    │   Controllers    │           │     Services/        │
                  │ (Routing API &amp;  │                    │   (Handle HTTP   │           │     Helpers          │
                  │    Web)         │                    │    Requests)     │           └──────────┬───────────┘
                  └────────┬────────┘                    └────────┬─────────┘                      │
                           │                                      │                                │
                           ▼                                      ▼                                ▼
                  ┌─────────────────┐                    ┌──────────────────┐           ┌──────────────────────┐
                  │     Models      │                    │      Views       │           │     Database         │
                  │  (Interaksi DB) │◀──────────────────▶│  (Render HTML)   │◀──────────│   (MySQL/MariaDB)    │
                  └─────────────────┘                    └──────────────────┘           └──────────────────────┘
```

### 2.4 Alur Komunikasi Antar Komponen
1. **Request Masuk**: Pengguna mengirim request ke `index.php` (entry point)
2. **Routing**:
   - Jika request dimulai dengan `/api/`, ditangani oleh `Router.php` (API routes)
   - Jika tidak, ditangani oleh routing web konvensional (`/controller/method/params`)
3. **Controller**: Memproses request, memanggil model/service
4. **Model**: Berinteraksi dengan database via PDO
5. **View**: Merender HTML untuk response web
6. **Response**: Mengembalikan HTML (web) atau JSON (API) ke pengguna

---

## 3. Panduan Instalasi dan Konfigurasi Lingkungan Pengembangan

### 3.1 Prasyarat
- PHP &gt;= 8.2 (dengan ekstensi: PDO, PDO_MySQL, mbstring, json)
- Composer
- Node.js &amp; npm (untuk Vite)
- MySQL/MariaDB 5.7+ atau 10.2+
- Web server (Apache/Nginx, atau Laragon untuk Windows)

### 3.2 Langkah Instalasi

#### 1. Clone Repositori
```bash
git clone https://github.com/your-repo/jagapadi.git
cd jagapadi
```

#### 2. Instal Dependensi PHP
```bash
composer install
```

#### 3. Instal Dependensi Frontend
```bash
npm install
```

#### 4. Konfigurasi Environment
Salin `.env.example` menjadi `.env` dan edit sesuai konfigurasi lokal:
```bash
cp .env.example .env
```

Edit file `.env`:
```env
# Application
APP_NAME=JAGAPADI
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/jagapadi

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bpsjembe_jagapadi
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

#### 5. Siapkan Database
- Buat database baru di MySQL (misal: `bpsjembe_jagapadi`)
- Import schema database (jika ada file SQL di `database/`)

#### 6. Jalankan Aplikasi di Mode Pengembangan
- **Web Server**: Pastikan web server berjalan dan menunjuk ke direktori `jagapadi/`
- **Vite (Frontend)**:
  ```bash
  npm run dev
  ```
- Akses aplikasi di browser: `http://localhost/jagapadi`

---

## 4. Dokumentasi Fungsional Fitur Aplikasi

### 4.1 Autentikasi dan Pengelolaan Pengguna
- **Login**: Pengguna masuk dengan email dan password
- **Role**: Terdapat 4 role: `admin`, `operator`, `statistisi`, `petani`
- **Profil**: Mengedit data pribadi dan avatar
- **Pergantian Password**: Fitur untuk mengganti password

### 4.2 Laporan Hama/OPT
- **Buat Laporan**: Input data laporan hama, lokasi (peta), foto
- **Lihat Laporan**: Daftar laporan dengan filter dan pagination
- **Verifikasi Laporan**: Operator/admin dapat memverifikasi laporan
- **Peta Interaktif**: Lihat sebaran laporan di peta Leaflet
- **Export**: Export laporan ke Excel/PDF

### 4.3 Irigasi dan IoT
- **Data Irigasi**: Lihat dan kelola data irigasi
- **Laporan Irigasi**: Buat laporan kondisi irigasi
- **IoT Sensor**: Monitor sensor dan kontrol aktuator via API
- **Rule Engine**: Atur aturan otomatis untuk irigasi

### 4.4 Cuaca dan Angin
- **Curah Hujan**: Lihat data curah hujan harian/mingguan
- **Kecepatan Angin**: Monitor kecepatan angin
- **Scraping**: Otomatis tarik data cuaca dari sumber eksternal

### 4.5 Harga dan Gabah/Beras
- **Harga Komoditas**: Lihat harga komoditas pertanian
- **Produksi Gabah**: Input dan analisis data produksi gabah
- **Analytics**: Grafik dan analisis harga serta produksi

### 4.6 Feedback
- **Buat Feedback**: Laporkan bug atau usulkan fitur baru
- **Vote Feedback**: Dukung feedback yang diinginkan
- **Tracking Status**: Lihat status feedback (diproses, selesai, dll)

### 4.7 Storytelling dan Evaluasi
- **Analisis Data**: Buat narasi analisis data pertanian
- **Evaluasi Akurasi Panen**: Hitung dan evaluasi akurasi prediksi panen
- **Dashboard Storytelling**: Visualisasi data dengan narasi

---

## 5. Dokumentasi API Endpoint

### 5.1 Format Response
Semua response API menggunakan format JSON.

#### Response Sukses
```json
{
  "success": true,
  "data": { /* data */ },
  "message": "Operation successful",
  "timestamp": "2026-07-16 10:00:00"
}
```

#### Response Error
```json
{
  "success": false,
  "error": "Error type",
  "message": "Error description",
  "timestamp": "2026-07-16 10:00:00"
}
```

### 5.2 Autentikasi API
- **Session Auth**: Digunakan untuk API internal (memerlukan login web)
- **API Key**: Digunakan untuk API eksternal (header `X-API-Key`)

### 5.3 Daftar Endpoint API

#### 5.3.1 Laporan Hama API
| Metode | Endpoint | Deskripsi | Middleware |
|--------|----------|-----------|------------|
| GET    | `/api/laporan-hama` | Dapatkan semua laporan | `auth` |
| GET    | `/api/laporan-hama/{id}` | Dapatkan detail laporan | `auth` |
| POST   | `/api/laporan-hama` | Buat laporan baru | `auth` |
| POST   | `/api/laporan-hama/{id}/archive` | Arsipkan laporan | `auth` |
| PUT    | `/api/laporan-hama/{id}` | Update laporan | `auth` |
| DELETE | `/api/laporan-hama/{id}` | Hapus laporan | `auth`, `admin` |

#### 5.3.2 Wilayah API
| Metode | Endpoint | Deskripsi | Middleware |
|--------|----------|-----------|------------|
| GET    | `/api/wilayah/kabupaten` | Dapatkan daftar kabupaten | `rate_limit` |
| GET    | `/api/wilayah/kecamatan` | Dapatkan daftar kecamatan | `rate_limit` |
| GET    | `/api/wilayah/desa` | Dapatkan daftar desa | `rate_limit` |
| GET    | `/api/wilayah/hierarchy` | Dapatkan hierarki wilayah | `rate_limit` |
| GET    | `/api/wilayah/search` | Cari wilayah | `rate_limit` |

#### 5.3.3 Dashboard API
| Metode | Endpoint | Deskripsi | Middleware |
|--------|----------|-----------|------------|
| GET    | `/api/dashboard/stats` | Dapatkan statistik dashboard | `auth` |
| GET    | `/api/dashboard/charts` | Dapatkan data grafik | `auth` |
| GET    | `/api/dashboard/map/all` | Dapatkan semua layer peta | `auth` |

#### 5.3.4 IoT/Pengairan API
| Metode | Endpoint | Deskripsi | Middleware |
|--------|----------|-----------|------------|
| GET    | `/api/pengairan/sensor` | Dapatkan daftar sensor | `auth` |
| POST   | `/api/pengairan/sensor/{id}/update` | Update data sensor | `auth` |
| POST   | `/api/pengairan/aktuator/{id}/control` | Kontrol aktuator | `auth` |

*(Untuk daftar endpoint lengkap, lihat `app/core/Router.php`)*

---

## 6. Struktur Database

### 6.1 Daftar Tabel Utama

| Tabel | Deskripsi |
|-------|-----------|
| `users` | Data pengguna aplikasi |
| `master_kabupaten` | Master data kabupaten |
| `master_kecamatan` | Master data kecamatan |
| `master_desa` | Master data desa |
| `master_opt` | Master data OPT (hama) |
| `laporan_hama` | Data laporan hama |
| `laporan_irigasi` | Data laporan irigasi |
| `data_irigasi` | Data observasi irigasi |
| `curah_hujan` | Data curah hujan |
| `kecepatan_angin` | Data kecepatan angin |
| `harga_komoditas` | Data harga komoditas |
| `produksi_gabah` | Data produksi gabah |
| `feedback` | Data feedback pengguna |
| `audit_log_wilayah` | Audit log perubahan wilayah |

### 6.2 Contoh Schema Tabel

#### Tabel `users`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | ID pengguna |
| `nama_lengkap` | VARCHAR(255) | NOT NULL | Nama lengkap |
| `email` | VARCHAR(255) | UNIQUE, NOT NULL | Email |
| `password` | VARCHAR(255) | NOT NULL | Password (hashed) |
| `role` | ENUM | NOT NULL | Role (`admin`, `operator`, `statistisi`, `petani`) |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Waktu dibuat |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Waktu diupdate |

#### Tabel `laporan_hama`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | INT | PRIMARY KEY, AUTO_INCREMENT | ID laporan |
| `user_id` | INT | FOREIGN KEY | ID pengguna yang membuat |
| `master_opt_id` | INT | FOREIGN KEY | ID OPT |
| `tanggal` | DATE | NOT NULL | Tanggal laporan |
| `lokasi` | TEXT | NOT NULL | Deskripsi lokasi |
| `latitude` | DECIMAL(10,8) | | Koordinat lintang |
| `longitude` | DECIMAL(11,8) | | Koordinat bujur |
| `status` | ENUM | DEFAULT 'Draf' | Status laporan |
| `created_at` | TIMESTAMP | | |

*(Untuk schema lengkap, lihat `DATABASE_SCHEMA.md` atau dump database)*

---

## 7. Panduan Pengembangan dan Standar Kode

### 7.1 Aturan Penulisan Kode

#### 7.1.1 Konvensi Penamaan
- **Controller**: `NamaController` (PascalCase), misal: `LaporanHamaController`
- **Model**: `NamaModel` (PascalCase), misal: `LaporanHama`
- **Method**: `namaMethod` (camelCase), misal: `store`, `update`
- **Variabel**: `namaVariabel` (camelCase), misal: `$dataLaporan`
- **Konstanta**: `NAMA_KONSTANTA` (UPPER_SNAKE_CASE), misal: `DB_HOST`

#### 7.1.2 Struktur File
- **Controller**: Di `app/controllers/` (web) dan `app/controllers/Api/` (API)
- **Model**: Di `app/models/`
- **View**: Di `app/views/`, diurutkan per modul (misal: `app/views/laporan/`)
- **Service**: Di `app/services/`

#### 7.1.3 Style Guide
- Gunakan indentasi 4 spasi
- Akhiri baris dengan `;` (untuk PHP)
- Gunakan kurung kurawal `{}` bahkan untuk blok satu baris
- Tambahkan komentar untuk kode yang kompleks

### 7.2 Git Workflow
1. **Buat Branch Baru**: Dari `main`
   ```bash
   git checkout main
   git pull origin main
   git checkout -b feature/nama-fitur
   ```
2. **Commit Perubahan**:
   ```bash
   git add .
   git commit -m "Add: Deskripsi singkat perubahan"
   ```
3. **Push ke Remote**:
   ```bash
   git push origin feature/nama-fitur
   ```
4. **Buat Pull Request**: Di GitHub, buat PR untuk merge ke `main`

### 7.3 Panduan Membuat Fitur Baru
1. Buat branch baru sesuai scope fitur
2. Buat model (jika diperlukan) di `app/models/`
3. Buat controller di `app/controllers/` atau `app/controllers/Api/`
4. Tambahkan route (jika API) di `app/core/Router.php`
5. Buat view di `app/views/` (jika web)
6. Test fitur secara manual
7. Jalankan test suite (jika ada)
8. Buat Pull Request

---

## 8. Panduan Pengujian Aplikasi

### 8.1 Jenis Pengujian
- **Unit Test**: Test unit kode (model, service)
- **Integration Test**: Test interaksi antar komponen
- **End-to-End (E2E) Test**: Test alur aplikasi dari awal sampai akhir

### 8.2 Menjalankan Test
Aplikasi menggunakan PHPUnit untuk testing.

```bash
# Jalankan semua test
./vendor/bin/phpunit

# Jalankan test dengan coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### 8.3 Standar Cakupan Test
- Target cakupan test minimal 70% untuk kode inti
- Test harus meliputi scenario sukses dan gagal

---

## 9. Panduan Deployment dan Pemeliharaan Produksi

### 9.1 Langkah Deployment

#### 1. Build Aplikasi untuk Produksi
```bash
# Build frontend (Vite)
npm run build

# Install dependensi PHP (tanpa dev)
composer install --no-dev --optimize-autoloader
```

#### 2. Konfigurasi Environment Produksi
Edit `.env`:
```env
APP_ENV=production
APP_DEBUG=false
```

#### 3. Upload File ke Server
Upload semua file ke server (kecuali `node_modules/`, `.git/`, file testing)

#### 4. Konfigurasi Web Server
Pastikan web server menunjuk ke direktori `jagapadi/` dan file `index.php` sebagai entry point.

#### 5. Jalankan Migration/Seeder (jika ada)
```bash
# Contoh: Jalankan script migration di scripts/
php scripts/migrate.php
```

### 9.2 Konfigurasi Server
- PHP &gt;= 8.2
- MySQL/MariaDB dengan charset `utf8mb4`
- Web server dengan mod_rewrite (untuk URL clean)
- SSL/TLS aktif (HTTPS)

### 9.3 Prosedur Backup Database
Jalankan backup secara periodik (misal harian):
```bash
# Backup database
mysqldump -u [user] -p [database] &gt; backup_$(date +%Y%m%d).sql
```

### 9.4 Pemantauan Produksi
- Monitor log error di `storage/logs/`
- Monitor uptime server
- Monitor penggunaan resource (CPU, RAM, disk)

---

## 10. Pemeliharaan dan Riwayat Versi

### 10.1 Tabel Riwayat Versi

| Versi | Tanggal | Perubahan |
|-------|---------|-----------|
| 1.0.0 | 2026-07-16 | Rilis pertama, fitur dasar lengkap |

*(Untuk riwayat lebih detail, lihat `CHANGELOG.md`)*

### 10.2 Panduan Mencatat Pembaruan
Setiap kali melakukan rilis baru:
1. Update nomor versi di `package.json` dan `config/version.php`
2. Tambahkan entri di `CHANGELOG.md`
3. Buat tag Git:
   ```bash
   git tag -a v1.0.1 -m "Rilis v1.0.1"
   git push origin v1.0.1
   ```

---

## 11. Panduan Troubleshooting Umum

### 11.1 Masalah Instalasi
- **Error koneksi database**: Periksa konfigurasi di `.env` dan pastikan database berjalan
- **Dependency tidak terinstall**: Jalankan `composer install` dan `npm install`

### 11.2 Masalah Runtime
- **Error 404**: Periksa routing dan pastikan controller/method ada
- **Error 500**: Aktifkan `APP_DEBUG=true` di `.env` untuk melihat error detail
- **Session tidak berfungsi**: Periksa konfigurasi session di `php.ini`

### 11.3 Masalah Database
- **Query lambat**: Tambahkan index pada kolom yang sering di-filter
- **Koneksi putus**: Periksa konfigurasi database dan batas koneksi

---

## 12. Lampiran

### 12.1 Daftar Referensi
- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md) - Ringkasan proyek
- [TECH_STACK.md](TECH_STACK.md) - Detail stack teknologi
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) - Schema database
- [DATA_DICTIONARY.md](DATA_DICTIONARY.md) - Kamus data
- [AGENTS.md](AGENTS.md) - Panduan kolaborasi agent AI
- [CHANGELOG.md](CHANGELOG.md) - Riwayat perubahan

### 12.2 Kontak Tim Pengembang
- BPS Kabupaten Jember - [Website](https://jemberkab.bps.go.id/)

### 12.3 Catatan Tambahan
- Dokumentasi ini akan terus diperbarui sesuai perkembangan aplikasi
- Untuk pertanyaan atau masalah, silakan buat issue di repositori GitHub

---

**Terima kasih telah menggunakan JAGAPADI!** 🚜🌾
