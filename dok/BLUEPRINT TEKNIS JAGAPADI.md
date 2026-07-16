<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# BLUEPRINT TEKNIS JAGAPADI

**Jember Agrikultur Gapai Prestasi Digital**
**Versi dokumen:** 3.0 Rebuild
**Tanggal:** 16 Juli 2026
**Instansi:** BPS Kabupaten Jember
**URL produksi:** `https://jagapadi.bpsjember.my.id`
**Repository:** `github.com/nanangpx5-netizen/jagapadi`
**Status:** Dokumen acuan tunggal untuk rebuild, pengembangan, deployment, dan pemeliharaan

Dokumen ini menggabungkan rancangan teknis rebuild, keputusan arsitektur web–Flutter, kebijakan draf yang dianalisis, serta panduan penyusunan bertahap.[^1][^2]

***

## 1. Pendahuluan

### 1.1 Definisi Sistem

JAGAPADI adalah sistem informasi pertanian digital untuk pengumpulan, penyimpanan, verifikasi, analisis, dan visualisasi dua jenis laporan lapangan:

1. **Laporan Hama/OPT** — pencatatan serangan Organisme Pengganggu Tanaman pada lahan padi.
2. **Laporan Kondisi Irigasi** — pencatatan kondisi fisik saluran dan debit air irigasi pertanian.

Sistem dioperasikan di wilayah Kabupaten Jember dan melayani pengguna web (admin/operator) serta petugas lapangan melalui aplikasi mobile Flutter.[^2][^1]

### 1.2 Latar Belakang Rebuild

Aplikasi lama dibangun ulang dari nol untuk mengatasi masalah kritis:


| Masalah lama | Solusi rebuild |
| :-- | :-- |
| Arbitrary file upload | Validasi magic bytes, MIME, ekstensi, nama acak |
| XSS dan output tidak aman | Escaping HTML, security headers |
| Kredensial di kode | `.env` terpisah, tidak di-commit |
| Query SQL tanpa parameterisasi | PDO prepared statements |
| Logika bisnis campur view | MVC ringan + service layer |
| Web dan mobile terpisah | Satu backend, dual client |
| Statistik tidak fleksibel | Filter draf / tanpa draf |

[^2]

### 1.3 Tujuan Versi 3.0

1. Menyediakan pelaporan lapangan yang aman, terstruktur, dan dapat diverifikasi.
2. Menyimpan **draf ke server** agar data awal dapat dimonitor dan dianalisis.
3. Menyediakan filter eksplisit **dengan draf** atau **tanpa draf** pada dashboard, peta, analisis, dan ekspor.
4. Melayani web admin dan Flutter petugas dari satu REST API.
5. Menjalankan rule engine analisis risiko dan rekomendasi.
6. Siap dideploy di shared hosting cPanel dengan SSH/Git.
7. Memungkinkan offline draf di Flutter dengan sinkronisasi ke server.

### 1.4 Ruang Lingkup Versi 1

| Modul | Web | Flutter | Prioritas |
| :-- | --: | --: | :-- |
| Autentikasi \& profil | Ya | Ya | Wajib |
| Manajemen pengguna | Ya | Tidak | Wajib |
| Master wilayah | Ya | Baca/sinkron | Wajib |
| Master OPT | Ya | Baca/sinkron | Wajib |
| Laporan hama | Ya | Ya | Wajib |
| Laporan irigasi | Ya | Ya | Wajib |
| Analisis rule-based | Ya | Ya | Wajib |
| Filter draf / non-draf | Ya | Ya | Wajib |
| Verifikasi laporan | Ya | Tidak | Wajib |
| Dashboard \& grafik | Ya | Ringkasan | Wajib |
| Peta sebaran | Ya | Ringkasan | Wajib |
| Ekspor CSV/XLSX | Ya | Tidak | Wajib |
| Offline sync | Tidak | Ya | Wajib |
| IoT / cuaca / harga / storytelling | - | - | Versi berikutnya |

Modul lanjutan (IoT, cuaca, harga, produksi, feedback, storytelling) disiapkan setelah fondasi pelaporan stabil.[^1]

### 1.5 Asumsi dan Batasan

- Hosting production adalah shared hosting Jagoan Hosting/cPanel.
- PHP 8.2 dan MariaDB 10.6 tersedia.
- SSL aktif pada subdomain `jagapadi.bpsjember.my.id`.
- Target mobile pertama adalah **Android via Flutter**; iOS dapat menyusul.
- Analisis versi 1 bersifat **rule-based**, bukan machine learning.
- Data resmi default **tidak** memasukkan draf; draf dapat diikutsertakan lewat filter.

***

## 2. Visi Produk dan Prinsip Desain

### 2.1 Prinsip Utama

1. **Single Source of Truth** — satu database, satu backend, banyak klien.
2. **Security by Default** — keamanan diterapkan sejak fondasi.
3. **Draft is Data** — draf masuk sistem, dianalisis, tetapi ditandai belum final.
4. **Official vs Operational View** — statistik resmi dan monitoring cepat dipisahkan filter.
5. **Mobile-First Field Capture** — Flutter dioptimalkan untuk petugas lapangan.
6. **Admin-First Verification** — verifikasi dan ekspor dilakukan di web.
7. **Offline Resilience** — draf lokal disinkronkan saat jaringan tersedia.
8. **Auditability** — setiap aksi penting tercatat.

### 2.2 Definisi Data Resmi vs Data Operasional

| Jenis | Status yang dihitung | Penggunaan |
| :-- | :-- | :-- |
| **Data resmi** | `Submitted`, `Diverifikasi` | Laporan manajemen, ekspor formal, KPI |
| **Data operasional** | `Draf` + data resmi | Monitoring cepat, deteksi dini, peta kerja |
| **Data penolakan** | `Ditolak` | Evaluasi kualitas pelaporan |
| **Data arsip** | `Diarsipkan` | Riwayat jangka panjang |

Default dashboard dan ekspor resmi: **tanpa draf**. Admin dapat mengaktifkan filter **termasuk draf**.

***

## 3. Aktor dan Matriks Hak Akses

### 3.1 Role Versi 1

Untuk menjaga kejelasan implementasi, versi 1 memakai dua role inti:


| Role | Deskripsi |
| :-- | :-- |
| **admin** | Mengelola sistem, master data, pengguna, verifikasi, dashboard, ekspor |
| **petugas** | Membuat draf/submit laporan lapangan melalui web atau Flutter |

Role tambahan (`operator`, `pengawas`, `pencacah`, `viewer`) dapat ditambahkan kemudian tanpa mengubah arsitektur, asalkan otorisasi berbasis middleware role.[^2]

### 3.2 Matriks Akses

| Fitur | Admin | Petugas |
| :-- | --: | --: |
| Login web | Ya | Ya |
| Login Flutter | Ya | Ya |
| Kelola pengguna | Ya | Tidak |
| Kelola wilayah | Ya | Tidak |
| Kelola master OPT | Ya | Tidak |
| Buat/edit draf milik sendiri | Ya | Ya |
| Submit laporan milik sendiri | Ya | Ya |
| Lihat draf orang lain | Ya | Tidak |
| Lihat seluruh laporan | Ya | Tidak |
| Verifikasi / tolak / arsip | Ya | Tidak |
| Dashboard resmi | Ya | Ya (terbatas) |
| Dashboard termasuk draf | Ya | Draf sendiri saja |
| Peta | Ya | Ya (terbatas) |
| Ekspor | Ya | Tidak |
| Lihat hasil analisis | Ya | Ya |

### 3.3 Aturan Kepemilikan Data

- Petugas hanya dapat membaca dan mengubah laporan **milik sendiri**.
- Petugas hanya dapat mengubah laporan berstatus `Draf` atau `Ditolak` (untuk perbaikan).
- Admin dapat membaca seluruh laporan.
- Admin tidak memverifikasi draf yang belum di-submit.
- Soft-delete tidak digunakan pada versi 1; arsip dilakukan lewat status `Diarsipkan`.

***

## 4. Arsitektur Sistem

### 4.1 Diagram Konteks

```text
┌──────────────────────┐        ┌──────────────────────┐
│   Browser Web        │        │  Flutter Android     │
│   (Admin/Petugas)    │        │  (Petugas Lapangan)  │
└──────────┬───────────┘        └──────────┬───────────┘
           │ HTTPS Session/CSRF            │ HTTPS JWT
           └──────────────┬────────────────┘
                          ▼
           ┌──────────────────────────────┐
           │     Backend PHP 8.2 MVC      │
           │  Router · Middleware · API   │
           │  Services · Rule Engine      │
           │  Upload · Cache · Audit      │
           └──────────────┬───────────────┘
                          │ PDO
                          ▼
           ┌──────────────────────────────┐
           │        MariaDB 10.6          │
           └──────────────────────────────┘
```


### 4.2 Dual Client, Single Backend

| Aspek | Web Browser | Flutter Mobile |
| :-- | :-- | :-- |
| Autentikasi | PHP Session + CSRF | JWT Bearer (+ refresh) |
| State | Stateful | Stateless token |
| Rate limiting | Opsional/ketat di login | Wajib |
| CSRF | Wajib untuk POST/PUT/DELETE | Tidak perlu |
| Response | HTML + JSON AJAX | JSON only |
| Offline | Tidak | Ya (draf lokal) |

[^2]

### 4.3 Alur Request

```text
Request
  → Rate limit?
  → Auth (session / JWT)?
  → Role check?
  → CSRF (web only)?
  → Controller
  → Validator
  → Service / Rule Engine
  → Model / QueryBuilder (PDO)
  → Response HTML/JSON
  → Activity log (jika aksi penting)
```


### 4.4 Komponen Inti Backend

| Komponen | Tanggung jawab |
| :-- | :-- |
| `Router` | Routing web/API, middleware chain |
| `Controller` | Orkestrasi request/response |
| `Service` | Logika bisnis |
| `Model` + `QueryBuilder` | Akses data parameterized |
| `Security` | CSRF, brute-force, sanitasi |
| `JwtService` | Generate/validasi JWT |
| `UploadService` | Validasi dan simpan foto |
| `AnalysisService` | Rule engine risiko/rekomendasi |
| `CacheManager` | Cache dashboard/rate limit |
| `Logger` | Error dan aktivitas |

[^2]

***

## 5. Stack Teknologi

### 5.1 Backend \& Infrastruktur

| Area | Teknologi |
| :-- | :-- |
| Bahasa | PHP 8.2 native |
| Pola | MVC ringan + service layer |
| Database | MariaDB 10.6, `utf8mb4` |
| Akses DB | PDO prepared statements |
| Web server | Apache 2.4 + PHP-FPM / mod_rewrite |
| Auth web | Session + CSRF |
| Auth mobile | JWT HS256 |
| Password | bcrypt cost 12 |
| Cache | File-based |
| Export | CSV / SimpleXLSXWriter |
| Test backend | PHPUnit |

[^2]

### 5.2 Web Frontend

| Area | Teknologi |
| :-- | :-- |
| Template | PHP views server-rendered |
| UI | Bootstrap 4/5 atau AdminLTE |
| Peta | Leaflet + MarkerCluster |
| Grafik | Chart.js |
| AJAX | Fetch/jQuery minimal |

[^1]

### 5.3 Mobile Flutter

| Area | Teknologi |
| :-- | :-- |
| Framework | Flutter + Dart |
| State | Riverpod |
| HTTP | Dio |
| Model JSON | `json_serializable` |
| Secure token | `flutter_secure_storage` |
| Offline DB | Drift/SQLite |
| GPS | `geolocator` |
| Izin | `permission_handler` |
| Kamera/galeri | `image_picker` / `camera` |
| Sinkronisasi | `workmanager` + `connectivity_plus` |
| Peta | `flutter_map` |

Flutter dipilih agar Android dibangun sekarang dan iOS dapat menyusul dari codebase yang sama.[^3]

### 5.4 Hosting Produksi

| Item | Nilai |
| :-- | :-- |
| Domain | `jagapadi.bpsjember.my.id` |
| Hosting | Jagoan Hosting / cPanel |
| User cPanel | `bpsjembe` |
| Home | `/home/bpsjembe` |
| Server | `brave` |
| OS | Linux x86_64 |
| Apache | 2.4.68 |
| MariaDB | 10.6.27 |
| IP shared | `101.50.1.72` |
| Akses | SSH, Git, Terminal, Cron, File Manager |


***

## 6. Struktur Repository (Monorepo)

```text
jagapadi/
├── backend/
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   └── Web/
│   │   ├── Core/
│   │   ├── Helpers/
│   │   ├── Middleware/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Views/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeds/
│   ├── public/                 # document root
│   │   ├── assets/
│   │   ├── uploads/
│   │   ├── .htaccess
│   │   └── index.php
│   ├── storage/
│   │   ├── cache/
│   │   ├── logs/
│   │   └── migrations_ran.txt
│   ├── tests/
│   ├── scripts/
│   ├── .env.example
│   ├── composer.json
│   └── phpunit.xml
├── mobile/
│   └── jagapadi_mobile/
│       ├── lib/
│       │   ├── core/
│       │   ├── data/
│       │   ├── domain/
│       │   ├── features/
│       │   └── main.dart
│       ├── android/
│       ├── ios/
│       ├── assets/
│       ├── test/
│       ├── integration_test/
│       └── pubspec.yaml
├── docs/
│   ├── BLUEPRINT.md
│   ├── API.md
│   ├── DATABASE.md
│   ├── DEPLOYMENT.md
│   └── USER_GUIDE.md
├── scripts/
│   └── deploy_jagapadi.sh
├── .github/workflows/
├── README.md
└── CHANGELOG.md
```

Hanya `backend/` yang dideploy ke hosting. Folder `mobile/` dibangun di mesin developer/CI menjadi APK/AAB.[^2]

***

## 7. Desain Database

### 7.1 Daftar Tabel

| Tabel | Fungsi |
| :-- | :-- |
| `users` | Akun dan role |
| `master_kabupaten` | Referensi kabupaten |
| `master_kecamatan` | Referensi kecamatan |
| `master_desa` | Referensi desa |
| `master_opt` | Master OPT/hama/penyakit/gulma |
| `laporan_hama` | Laporan serangan OPT |
| `laporan_irigasi` | Laporan kondisi irigasi |
| `nomor_laporan_counter` | Counter atomik nomor laporan |
| `analysis_results` | Hasil rule engine |
| `audit_log_wilayah` | Audit perubahan wilayah |
| `activity_log` | Log aktivitas \& keamanan |
| `schema_migrations` | Jejak migrasi |

[^2]

### 7.2 ERD Ringkas

```text
users 1───< laporan_hama >───1 master_opt
users 1───< laporan_irigasi
users 1───< analysis_results (via report)

master_kabupaten 1───< master_kecamatan 1───< master_desa
        │                    │                   │
        └──────── dipakai oleh laporan hama & irigasi

laporan_hama/irigasi 1───< analysis_results
```


### 7.3 DDL Inti

#### users

```sql
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL COMMENT 'bcrypt cost 12',
  email VARCHAR(150) NOT NULL UNIQUE,
  nama_lengkap VARCHAR(150) NOT NULL,
  role ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_password_change_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role),
  INDEX idx_aktif (aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### master wilayah

```sql
CREATE TABLE master_kabupaten (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(10) NOT NULL UNIQUE,
  nama_kabupaten VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE master_kecamatan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kabupaten_id INT UNSIGNED NOT NULL,
  kode VARCHAR(10) NOT NULL UNIQUE,
  nama_kecamatan VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_kabupaten (kabupaten_id),
  FOREIGN KEY (kabupaten_id) REFERENCES master_kabupaten(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE master_desa (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kecamatan_id INT UNSIGNED NOT NULL,
  kode VARCHAR(10) NOT NULL UNIQUE,
  nama_desa VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_kecamatan (kecamatan_id),
  FOREIGN KEY (kecamatan_id) REFERENCES master_kecamatan(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### master_opt

```sql
CREATE TABLE master_opt (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_opt VARCHAR(150) NOT NULL UNIQUE,
  jenis ENUM('hama','penyakit','gulma') NOT NULL,
  etl_acuan DECIMAL(10,2) NULL,
  satuan_etl VARCHAR(30) NULL,
  foto_url VARCHAR(300) NULL,
  deskripsi TEXT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_jenis (jenis),
  INDEX idx_aktif (aktif),
  FULLTEXT INDEX ft_nama (nama_opt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### laporan_hama

```sql
CREATE TABLE laporan_hama (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nomor_laporan VARCHAR(20) NULL UNIQUE COMMENT 'LH-YYYYMMDD-XXXX, diisi saat submit',
  user_id INT UNSIGNED NOT NULL,
  master_opt_id INT UNSIGNED NULL,
  tanggal DATE NULL,
  kabupaten_id INT UNSIGNED NULL,
  kecamatan_id INT UNSIGNED NULL,
  desa_id INT UNSIGNED NULL,
  lokasi VARCHAR(255) NULL,
  alamat_lengkap VARCHAR(300) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(11,7) NULL,
  tingkat_keparahan ENUM('Ringan','Sedang','Berat') NULL,
  luas_serangan DECIMAL(8,2) NULL,
  populasi DECIMAL(10,2) NULL,
  foto_url VARCHAR(300) NULL,
  catatan TEXT NULL,
  status ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan')
    NOT NULL DEFAULT 'Draf',
  verified_by INT UNSIGNED NULL,
  verified_at TIMESTAMP NULL,
  catatan_verifikasi TEXT NULL,
  ip_pengirim VARCHAR(45) NULL,
  client_local_id VARCHAR(64) NULL COMMENT 'ID lokal Flutter untuk sync',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_tanggal (tanggal),
  INDEX idx_kecamatan (kecamatan_id),
  INDEX idx_status_tanggal (status, tanggal),
  INDEX idx_client_local (user_id, client_local_id),
  CONSTRAINT chk_luas CHECK (luas_serangan IS NULL OR (luas_serangan >= 0 AND luas_serangan <= 9999.99)),
  CONSTRAINT chk_lat CHECK (latitude IS NULL OR (latitude BETWEEN -90 AND 90)),
  CONSTRAINT chk_lon CHECK (longitude IS NULL OR (longitude BETWEEN -180 AND 180)),
  FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (master_opt_id) REFERENCES master_opt(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (kabupaten_id) REFERENCES master_kabupaten(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (kecamatan_id) REFERENCES master_kecamatan(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (desa_id) REFERENCES master_desa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (verified_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### laporan_irigasi

```sql
CREATE TABLE laporan_irigasi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nomor_laporan VARCHAR(20) NULL UNIQUE COMMENT 'LI-YYYYMMDD-XXXX',
  user_id INT UNSIGNED NOT NULL,
  tanggal DATE NULL,
  kabupaten_id INT UNSIGNED NULL,
  kecamatan_id INT UNSIGNED NULL,
  desa_id INT UNSIGNED NULL,
  nama_saluran VARCHAR(200) NULL,
  daerah_irigasi VARCHAR(200) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(11,7) NULL,
  kondisi_fisik ENUM('Bagus','Sedang','Tidak Bagus','Rusak') NULL,
  debit_air ENUM('Cukup','Kurang','Kering') NULL,
  foto_url VARCHAR(300) NULL,
  catatan TEXT NULL,
  status ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan')
    NOT NULL DEFAULT 'Draf',
  verified_by INT UNSIGNED NULL,
  verified_at TIMESTAMP NULL,
  catatan_verifikasi TEXT NULL,
  ip_pengirim VARCHAR(45) NULL,
  client_local_id VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_tanggal (tanggal),
  INDEX idx_kecamatan (kecamatan_id),
  INDEX idx_status_tanggal (status, tanggal),
  INDEX idx_client_local (user_id, client_local_id),
  CONSTRAINT chk_lat_ir CHECK (latitude IS NULL OR (latitude BETWEEN -90 AND 90)),
  CONSTRAINT chk_lon_ir CHECK (longitude IS NULL OR (longitude BETWEEN -180 AND 180)),
  FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (kabupaten_id) REFERENCES master_kabupaten(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (kecamatan_id) REFERENCES master_kecamatan(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (desa_id) REFERENCES master_desa(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  FOREIGN KEY (verified_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### analysis_results

```sql
CREATE TABLE analysis_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_type ENUM('hama','irigasi') NOT NULL,
  report_id BIGINT UNSIGNED NOT NULL,
  report_status_at_analysis ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan') NOT NULL,
  analysis_status ENUM('Siap','MenungguData','TidakBerlaku') NOT NULL,
  risk_level ENUM('Rendah','Sedang','Tinggi','Kritis') NULL,
  score DECIMAL(8,2) NULL,
  rules_version VARCHAR(20) NOT NULL,
  reason TEXT NULL,
  recommendation TEXT NULL,
  missing_fields JSON NULL,
  input_snapshot JSON NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 1,
  analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_report (report_type, report_id, is_current),
  INDEX idx_risk (risk_level),
  INDEX idx_status (analysis_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### counter \& log

```sql
CREATE TABLE nomor_laporan_counter (
  prefix VARCHAR(10) NOT NULL,
  tanggal DATE NOT NULL,
  counter INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (prefix, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  table_name VARCHAR(50) NULL,
  record_id BIGINT UNSIGNED NULL,
  description TEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log_wilayah (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  tabel VARCHAR(50) NOT NULL,
  record_id INT UNSIGNED NOT NULL,
  aksi ENUM('INSERT','UPDATE','DELETE') NOT NULL,
  data_lama JSON NULL,
  data_baru JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin (admin_id),
  INDEX idx_tabel_record (tabel, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


### 7.4 Migrasi

Urutan file migrasi:

1. `001_create_users.sql`
2. `002_create_wilayah.sql`
3. `003_create_master_opt.sql`
4. `004_create_laporan_hama.sql`
5. `005_create_laporan_irigasi.sql`
6. `006_create_analysis_and_logs.sql`
7. `007_seed_admin_and_jember.sql`

Setiap migrasi dicatat di `schema_migrations` atau `storage/migrations_ran.txt` agar tidak dijalankan dua kali.[^2]

***

## 8. Workflow Bisnis

### 8.1 Status Laporan

```text
Draf ──submit──► Submitted ──verifikasi──► Diverifikasi ──arsip──► Diarsipkan
   ▲                 │
   │                 └──tolak──► Ditolak ──perbaiki──► Draf/Submitted
```

| Dari | Ke | Pelaku | Syarat |
| :-- | :-- | :-- | :-- |
| - | Draf | Petugas/Admin | Validasi longgar |
| Draf | Submitted | Petugas/Admin | Semua field wajib valid |
| Submitted | Diverifikasi | Admin | - |
| Submitted | Ditolak | Admin | Alasan ≥ 10 karakter |
| Ditolak | Draf/Submitted | Petugas | Perbaikan data |
| Diverifikasi | Diarsipkan | Admin | - |

[^2]

### 8.2 Kebijakan Draf (Keputusan Kunci)

1. Draf **langsung disimpan di server** (bukan hanya lokal).
2. Draf **boleh dianalisis** jika field minimum tersedia.
3. Hasil analisis draf diberi penanda **“Berbasis Draf / Belum Final”**.
4. Dashboard/peta/ekspor memiliki filter:
    - `include_draft=false` (default, data resmi)
    - `include_draft=true` (operasional)
    - `statuses=...` (pilihan status eksplisit)
5. Draf **tidak** dapat diverifikasi sebelum di-submit.
6. Petugas hanya melihat draf miliknya; admin melihat semua.

### 8.3 Nomor Laporan

- Format hama: `LH-YYYYMMDD-XXXX`
- Format irigasi: `LI-YYYYMMDD-XXXX`
- Nomor digenerate **saat submit**, bukan saat draf, agar draf tidak menghabiskan nomor.
- Generate memakai counter atomik:

```sql
INSERT INTO nomor_laporan_counter (prefix, tanggal, counter)
VALUES ('LH', CURDATE(), 1)
ON DUPLICATE KEY UPDATE counter = counter + 1;
```


### 8.4 Generate Analisis

```text
Simpan/update draf atau submit
        │
        ▼
Cek field minimum?
   │              │
  Tidak          Ya
   │              │
   ▼              ▼
MenungguData   Jalankan rule engine
   │              │
   └──── simpan analysis_results (is_current=1)
```


***

## 9. Rule Engine Analisis

### 9.1 Field Minimum

| Jenis | Minimum untuk `Siap` |
| :-- | :-- |
| Hama | tanggal + kecamatan + OPT + (keparahan ATAU populasi ATAU luas) |
| Irigasi | tanggal + kecamatan + (kondisi_fisik ATAU debit_air) |
| Spasial | latitude + longitude untuk peta |

Jika minimum belum terpenuhi: `analysis_status = MenungguData` + daftar `missing_fields`.

### 9.2 Aturan Hama (v1.0)

| Kondisi | Risiko | Rekomendasi |
| :-- | :-- | :-- |
| Keparahan Ringan DAN populasi < ETL | Rendah | Monitoring rutin |
| Keparahan Sedang ATAU populasi 80–100% ETL | Sedang | Tingkatkan pengamatan \& pengendalian terpadu |
| Keparahan Berat ATAU populasi > ETL | Tinggi | Prioritaskan verifikasi lapangan |
| ≥3 laporan Tinggi/Kritis di kecamatan sama dalam 7 hari | Kritis | Peringatan wilayah \& koordinasi respons cepat |

### 9.3 Aturan Irigasi (v1.0)

| Kondisi | Risiko | Rekomendasi |
| :-- | :-- | :-- |
| Fisik Bagus + debit Cukup | Rendah | Pemantauan berkala |
| Fisik Sedang ATAU debit Kurang | Sedang | Inspeksi saluran |
| Fisik Tidak Bagus/Rusak ATAU debit Kering | Tinggi | Prioritas perbaikan |
| ≥3 laporan Tinggi di area sama dalam 7 hari | Kritis | Eskalasi pengelola irigasi |

### 9.4 Versi Aturan

- Simpan `rules_version` (contoh: `hama-1.0`, `irigasi-1.0`).
- Perubahan aturan menaikkan versi.
- Hasil lama tetap tersimpan untuk audit.

***

## 10. Spesifikasi Fungsional Modul

### 10.1 Autentikasi

**Web**

- Login username/password
- Session regenerate
- CSRF token
- Force change password
- Logout menghancurkan session

**Flutter**

- `POST /auth/login` → access token + refresh token
- Access token 3600 detik
- Auto refresh
- Token di secure storage

[^2]

### 10.2 Master Wilayah

- CRUD admin
- Dropdown cascading: Kabupaten → Kecamatan → Desa
- Kode BPS
- Audit log setiap perubahan
- Tidak bisa dihapus jika dipakai laporan


### 10.3 Master OPT

- CRUD admin
- Jenis: hama/penyakit/gulma
- ETL acuan + satuan
- Foto opsional
- Status aktif/nonaktif
- Full-text search nama


### 10.4 Laporan Hama

Field draf (longgar): semua nullable kecuali `user_id`, `status`.
Field submit (ketat): tanggal, wilayah lengkap, OPT, keparahan, luas/populasi sesuai kebijakan, koordinat wajib jika kebijakan mengharuskan, foto opsional.

Aksi:

- simpan draf
- update draf
- upload foto
- submit
- verifikasi / tolak / arsip (admin)


### 10.5 Laporan Irigasi

Field submit ketat: tanggal, wilayah, nama saluran, kondisi fisik, debit air.
Daerah irigasi, koordinat, foto, catatan opsional/sesuai kebijakan.

### 10.6 Dashboard

Kartu:

- total laporan
- draf
- menunggu verifikasi
- diverifikasi
- ditolak
- risiko sedang/tinggi/kritis (resmi)
- indikasi risiko dari draf (jika filter aktif)

Filter:

- periode
- kecamatan/desa
- jenis laporan
- status
- `include_draft`
- tingkat risiko


### 10.7 Peta

- Layer hama
- Layer irigasi
- Marker clustering
- Warna berdasarkan risiko/status
- Marker draf berbeda (opacity/icon putus-putus)
- Popup: nomor/status/risiko/rekomendasi


### 10.8 Ekspor

- Format CSV/XLSX
- Filter sama dengan dashboard
- Kolom wajib: status laporan, status analisis, risk level, apakah data resmi

***

## 11. API REST v1

### 11.1 Konvensi

- Base URL: `https://jagapadi.bpsjember.my.id/api/v1`
- Content-Type: `application/json`
- Auth: `Authorization: Bearer <token>`
- Pagination: `?page=1&limit=20`
- Filter draf: `?include_draft=false|true`
- Filter status: `?statuses=Draf,Submitted,Diverifikasi`

[^2]

### 11.2 Envelope Respons

Sukses:

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 100,
    "include_draft": false
  }
}
```

Error validasi:

```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Data belum valid",
  "errors": {
    "tanggal": "Tanggal wajib diisi",
    "latitude": "Latitude harus antara -90 dan 90"
  }
}
```


### 11.3 Kode HTTP

| Kode | Arti |
| --: | :-- |
| 200 | Sukses |
| 201 | Dibuat |
| 401 | Tidak terautentikasi |
| 403 | Tidak diizinkan |
| 404 | Tidak ditemukan |
| 409 | Konflik |
| 422 | Validasi gagal |
| 429 | Rate limit |
| 500 | Error server |

### 11.4 Endpoint

#### Health \& Auth

| Method | Endpoint | Auth | Keterangan |
| :-- | :-- | :-- | :-- |
| GET | `/health` | Tidak | Health check |
| POST | `/auth/login` | Tidak | Login JWT |
| POST | `/auth/refresh` | Refresh | Perbarui token |
| POST | `/auth/logout` | Ya | Invalidate refresh |
| GET | `/auth/me` | Ya | Profil user |

#### Wilayah \& OPT

| Method | Endpoint | Auth |
| :-- | :-- | :-- |
| GET | `/wilayah/kabupaten` | Ya/rate-limit |
| GET | `/wilayah/kecamatan?kabupaten_id=` | Ya |
| GET | `/wilayah/desa?kecamatan_id=` | Ya |
| GET | `/opt` | Ya |
| GET | `/opt/{id}` | Ya |

#### Laporan Hama

| Method | Endpoint | Keterangan |
| :-- | :-- | :-- |
| GET | `/laporan-hama` | List + filter |
| POST | `/laporan-hama` | Buat draf/submit |
| GET | `/laporan-hama/{id}` | Detail + analisis |
| PUT | `/laporan-hama/{id}` | Update draf |
| POST | `/laporan-hama/{id}/submit` | Submit |
| POST | `/laporan-hama/{id}/foto` | Upload foto |
| POST | `/laporan-hama/{id}/verifikasi` | Admin |
| POST | `/laporan-hama/{id}/tolak` | Admin |
| POST | `/laporan-hama/{id}/archive` | Admin |

#### Laporan Irigasi

Endpoint setara dengan prefix `/laporan-irigasi`.

#### Dashboard \& Analisis

| Method | Endpoint | Keterangan |
| :-- | :-- | :-- |
| GET | `/dashboard/stats?include_draft=` | Ringkasan |
| GET | `/dashboard/charts?include_draft=` | Grafik |
| GET | `/dashboard/map?include_draft=` | GeoJSON |
| GET | `/analysis?include_draft=` | Daftar hasil analisis |
| GET | `/export/laporan-hama` | Ekspor |
| GET | `/export/laporan-irigasi` | Ekspor |

#### Sync Flutter

| Method | Endpoint | Keterangan |
| :-- | :-- | :-- |
| POST | `/sync/push` | Dorong draf/perubahan lokal |
| GET | `/sync/pull` | Tarik master \& status laporan |

### 11.5 Contoh Buat Draf Hama

```http
POST /api/v1/laporan-hama
Authorization: Bearer <token>
Content-Type: application/json

{
  "client_local_id": "uuid-local-001",
  "tanggal": "2026-07-16",
  "kabupaten_id": 1,
  "kecamatan_id": 5,
  "desa_id": 23,
  "master_opt_id": 3,
  "tingkat_keparahan": "Sedang",
  "luas_serangan": 2.5,
  "populasi": 18.0,
  "latitude": -8.1734,
  "longitude": 113.7012,
  "catatan": "Populasi meningkat",
  "status": "Draf"
}
```

Respons:

```json
{
  "success": true,
  "message": "Draf tersimpan",
  "data": {
    "id": 42,
    "status": "Draf",
    "analysis": {
      "analysis_status": "Siap",
      "risk_level": "Sedang",
      "recommendation": "Tingkatkan pengamatan dan pengendalian terpadu",
      "is_official": false
    }
  }
}
```


***

## 12. Spesifikasi Flutter

### 12.1 Fitur Versi 1

1. Login/logout/refresh token
2. Beranda ringkas
3. Sinkron master wilayah \& OPT
4. Form laporan hama
5. Form laporan irigasi
6. GPS + koreksi marker
7. Kamera/galeri + kompresi
8. Draf lokal + draf server
9. Antrean sinkronisasi
10. Riwayat \& detail status
11. Hasil analisis/rekomendasi
12. Filter “sertakan draf saya”
13. Profil \& versi app

### 12.2 Struktur `lib/`

```text
lib/
├── core/
│   ├── config/
│   ├── network/
│   ├── storage/
│   ├── theme/
│   └── utils/
├── data/
│   ├── local/
│   ├── models/
│   ├── remote/
│   └── repositories/
├── domain/
│   ├── entities/
│   ├── repositories/
│   └── usecases/
├── features/
│   ├── auth/
│   ├── dashboard/
│   ├── master_data/
│   ├── laporan_hama/
│   ├── laporan_irigasi/
│   ├── sync/
│   └── profile/
└── main.dart
```


### 12.3 State Draf di Perangkat

| State lokal | Arti |
| :-- | :-- |
| `local_only` | Baru disimpan offline |
| `syncing` | Sedang diunggah |
| `synced_draft` | Sudah di server sebagai Draf |
| `submitted` | Sudah dikirim |
| `sync_failed` | Gagal, akan dicoba ulang |
| `conflict` | Bertentangan dengan server |

### 12.4 Build Config

```bash
flutter build appbundle \
  --dart-define=API_BASE_URL=https://jagapadi.bpsjember.my.id/api/v1
```


***

## 13. Keamanan

### 13.1 Kontrol Keamanan

| Area | Implementasi |
| :-- | :-- |
| HTTPS | Wajib |
| Password | bcrypt cost 12 |
| Session web | regenerate ID, secure cookie |
| CSRF | semua mutasi web |
| JWT | secret ≥ 64 karakter, expiry 3600s |
| SQL Injection | PDO prepared statements |
| XSS | `htmlspecialchars` |
| Upload | magic bytes + MIME + ekstensi + size + nama acak |
| Rate limit | login, submit, API |
| Headers | CSP, nosniff, DENY frame, referrer policy |
| Secrets | `.env` permission 600, tidak di Git |
| Upload dir | nonaktifkan eksekusi PHP |

[^2]

### 13.2 Rate Limit Awal

| Konteks | Batas |
| :-- | :-- |
| Login gagal | 5 / 15 menit / IP |
| Submit laporan | 60 / jam / IP |
| API master publik | 300 / jam / IP |
| Mobile API | 1000 / jam / IP |

### 13.3 Upload

| Jenis | Max | Path |
| :-- | --: | :-- |
| Foto laporan | 10 MB | `uploads/laporan-hama/YYYYMM/` atau `.../laporan-irigasi/YYYYMM/` |
| Foto OPT | 5 MB | `uploads/opt-photos/` |

Format diizinkan: JPG, JPEG, PNG, WebP.[^2]

### 13.4 Password Policy

- Minimal 8 karakter
- 1 huruf besar
- 1 huruf kecil
- 1 angka
- 1 karakter khusus

***

## 14. Environment

```dotenv
APP_NAME=JAGAPADI
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://jagapadi.bpsjember.my.id
APP_TIMEZONE=Asia/Jakarta

DB_HOST=localhost
DB_PORT=3306
DB_NAME=bpsjembe_jagapadi
DB_USER=bpsjembe_jagapadi
DB_PASS=GANTI_PASSWORD_KUAT

JWT_SECRET=GANTI_SECRET_ACAK_MINIMAL_64_KARAKTER
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

API_KEY=GANTI_API_KEY_ACAK_MINIMAL_32

UPLOAD_MAX_SIZE_LAPORAN=10485760
UPLOAD_MAX_SIZE_OPT=5242880

CACHE_DASHBOARD_TTL=300
RULES_VERSION_HAMA=hama-1.0
RULES_VERSION_IRIGASI=irigasi-1.0
DEFAULT_INCLUDE_DRAFT=false
```


***

## 15. Deployment Produksi

### 15.1 Layout Server

```text
/home/bpsjembe/
├── repositories/jagapadi/     # clone Git
├── apps/jagapadi/             # rilis aktif backend
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/                # document root subdomain
│   ├── storage/
│   └── .env
├── backups/jagapadi/
└── deploy_jagapadi.sh
```


### 15.2 Langkah Deploy

1. Backup database + uploads
2. `git pull` ke `repositories/jagapadi`
3. Rsync/copy backend ke `apps/jagapadi` tanpa menimpa `.env`, `storage`, `uploads`
4. `composer install --no-dev --optimize-autoloader`
5. Jalankan migrasi baru
6. Clear cache
7. Health check `GET /api/v1/health`
8. Smoke test login + buat draf + dashboard

### 15.3 Permission

| Path | Permission |
| :-- | :-- |
| Source folders | 755 |
| Source files | 644 |
| `.env` | 600 |
| `storage/` | 775 |
| `public/uploads/` | 775 |

### 15.4 Document Root

Arahkan subdomain ke:

```text
/home/bpsjembe/apps/jagapadi/public
```

Jangan expose folder `app/`, `config/`, `database/`, `storage/`, `.env`.[^2]

### 15.5 Backup

| Data | Frekuensi | Retensi |
| :-- | --: | --: |
| Database | Harian | 30 hari |
| Uploads | Mingguan | 8 minggu |
| Release source | Tiap deploy | 10 rilis |
| `.env` | Saat berubah | Offline/terenkripsi |

[^1][^2]

***

## 16. Petunjuk Penyusunan Tahap demi Tahap

### Tahap 0 — Kickoff \& Penguncian Keputusan

**Tujuan:** semua pihak sepakat sebelum coding.

**Langkah:**

1. Sahkan blueprint ini.
2. Kunci scope versi 1.
3. Siapkan data master wilayah \& OPT.
4. Tentukan ambang rule engine awal.
5. Siapkan akun hosting, domain, SSL, GitHub.
6. Buat backlog issue per modul.

**Output:** keputusan tertutup, backlog siap.

***

### Tahap 1 — Repository \& Standar Kerja

**Tujuan:** fondasi kolaborasi.

**Langkah:**

1. Backup repo \& hosting lama.
2. Buat monorepo `backend/` + `mobile/`.
3. Pasang branch protection `main`.
4. Buat `.gitignore`, `.env.example`, README, CHANGELOG.
5. Siapkan CI lint/test minimal.

**Output:** repo bersih tanpa kredensial.

***

### Tahap 2 — Setup Backend Lokal

**Tujuan:** skeleton backend hidup.

**Langkah:**

1. Instal PHP 8.2, Composer, MariaDB, web server.
2. Aktifkan ekstensi: pdo_mysql, mbstring, json, fileinfo, gd, openssl.
3. Buat bootstrap, router, response JSON.
4. Buat `GET /api/v1/health`.
5. Hubungkan `.env` lokal.

**Kriteria selesai:** health 200, DB connect, error log tersimpan.

[^2]

***

### Tahap 3 — Migrasi Database

**Tujuan:** skema stabil.

**Langkah:**

1. Tulis migrasi 001–007.
2. Jalankan ke DB kosong.
3. Seed admin + wilayah Jember + OPT awal.
4. Uji foreign key \& index.

**Kriteria selesai:** migrasi idempoten, seed berhasil.

***

### Tahap 4 — Core Security

**Tujuan:** fondasi aman.

**Langkah:**

1. Middleware auth session/JWT/role/CSRF/rate-limit.
2. QueryBuilder parameterized.
3. Upload service aman.
4. Security headers.
5. Activity log dasar.

**Kriteria selesai:** request tanpa auth ditolak; upload PHP gagal; CSRF aktif di web.

***

### Tahap 5 — Autentikasi Web \& API

**Tujuan:** login multi-klien.

**Langkah backend:**

1. Login/logout web.
2. Login/refresh/logout API.
3. CRUD user admin.
4. Ganti password.

**Langkah Flutter:**

1. Buat proyek.
2. Login screen + secure storage.
3. Interceptor JWT.

**Kriteria selesai:** akun sama bisa login web \& Flutter.

***

### Tahap 6 — Master Data

**Tujuan:** referensi siap form.

**Langkah:**

1. CRUD wilayah + audit.
2. CRUD OPT.
3. API read master.
4. Flutter sync master ke SQLite.
5. Dropdown cascading.

**Kriteria selesai:** form dapat memilih wilayah/OPT online \& offline setelah sync.

***

### Tahap 7 — Laporan Hama + Analisis Draf

**Tujuan:** modul inti pertama.

**Langkah backend:**

1. CRUD draf longgar.
2. Submit ketat + nomor atomik.
3. Upload foto.
4. Rule engine + `analysis_results`.
5. Verifikasi/tolak/arsip.
6. Filter list `include_draft` / `statuses`.

**Langkah Flutter:**

1. Form draf/submit.
2. GPS + foto.
3. Simpan lokal jika offline.
4. Push draf ke server saat online.
5. Tampilkan hasil analisis.

**Kriteria selesai:** draf masuk DB, bisa dianalisis, muncul di list dengan filter draf.

***

### Tahap 8 — Laporan Irigasi

**Tujuan:** modul setara hama.

**Langkah:** ulangi pola tahap 7 untuk irigasi + rule engine irigasi.

**Kriteria selesai:** alur draf–submit–verifikasi–analisis jalan.

***

### Tahap 9 — Dashboard, Peta, Ekspor

**Tujuan:** monitoring dan pelaporan.

**Langkah:**

1. Stats resmi default tanpa draf.
2. Toggle/filter termasuk draf.
3. Chart.js + Leaflet.
4. Marker draf dibedakan.
5. Banner peringatan data non-final.
6. Ekspor dengan kolom status/analisis.

**Kriteria selesai:** angka resmi dan operasional dapat dibedakan dengan jelas.

***

### Tahap 10 — Offline Sync Flutter

**Tujuan:** ketahanan lapangan.

**Langkah:**

1. Antrean create/update/upload/submit.
2. Retry otomatis.
3. Resolusi konflik sederhana.
4. Indikator status sinkron.

**Kriteria selesai:** laporan offline tidak hilang dan tidak dobel.

***

### Tahap 11 — Testing \& UAT

**Jenis uji:**

- Unit backend (validator, nomor, rule engine, JWT)
- Integration API
- Widget/integration Flutter
- Security (CSRF, role, upload, rate limit)
- UAT petugas \& admin
- Restore backup

**Target:** seluruh alur kritis lulus; cakupan kode inti memadai.[^1]

***

### Tahap 12 — Deploy Produksi

**Langkah:**

1. Buat DB/user cPanel.
2. Set PHP 8.2 + ekstensi.
3. SSL subdomain.
4. Document root ke `public/`.
5. Deploy key read-only.
6. Clone + migrasi + seed.
7. `.env` production.
8. Deploy script + health check.
9. Cron backup.

**Kriteria selesai:** domain HTTPS hidup, login admin berhasil, API health OK.

***

### Tahap 13 — Go-Live \& Hypercare

**Langkah:**

1. Pelatihan admin \& petugas.
2. Distribusi APK internal.
3. Monitoring log/disk/error 2–4 minggu.
4. Perbaiki bug prioritas tinggi.
5. Catat CHANGELOG \& tag versi `v1.0.0`.

***

## 17. Standar Kode

### 17.1 PHP

- Controller/Model: PascalCase
- method/variabel: camelCase
- konstanta: UPPER_SNAKE
- indentasi 4 spasi
- tidak ada query string mentah
- fillable guard pada model
- output HTML di-escape

[^1][^2]

### 17.2 Flutter

- feature-first structure
- repository pattern
- tidak hardcode base URL
- tidak log token
- error user-friendly


### 17.3 Git

```text
feature/<nama>
fix/<nama>
hotfix/<nama>
```

PR wajib sebelum merge ke `main`.[^1]

***

## 18. Pengujian Penerimaan (UAT Checklist)

### Admin

- [ ] Login web
- [ ] Kelola user
- [ ] Kelola wilayah \& OPT
- [ ] Lihat draf petugas
- [ ] Filter dashboard tanpa draf
- [ ] Filter dashboard termasuk draf
- [ ] Verifikasi/tolak laporan
- [ ] Lihat peta \& ekspor
- [ ] Hasil analisis tampil benar


### Petugas

- [ ] Login Flutter
- [ ] Buat draf online → masuk server
- [ ] Buat draf offline → tersimpan lokal
- [ ] Sync draf saat online
- [ ] Submit laporan lengkap
- [ ] GPS \& foto berhasil
- [ ] Lihat analisis draf
- [ ] Perbaiki laporan ditolak


### Keamanan

- [ ] Tidak bisa akses folder sensitif
- [ ] Upload `.php` ditolak
- [ ] Petugas tidak verifikasi
- [ ] Petugas tidak lihat draf orang lain
- [ ] HTTPS aktif

***

## 19. Monitoring \& Pemeliharaan

### 19.1 Yang Dipantau

- Error log PHP
- Login gagal berulang
- Disk uploads/backup
- SSL expiry
- Query dashboard lambat
- Gagal sync Flutter
- Usage CPU/RAM hosting


### 19.2 Pembersihan

- Arsip laporan diverifikasi > 2 tahun (kebijakan opsional)
- Hapus cache kedaluwarsa
- Rotasi log

[^2]

### 19.3 Update Sistem

1. Backup
2. Branch/PR
3. Uji staging/lokal
4. Deploy
5. Migrasi
6. Clear cache
7. Smoke test

***

## 20. Roadmap

| Versi | Isi |
| :-- | :-- |
| **v1.0** | Web + Flutter, hama, irigasi, draf server, analisis, filter draf, dashboard, peta, ekspor |
| **v1.1** | Role tambahan, notifikasi, perbaikan UX lapangan |
| **v1.2** | iOS Flutter release |
| **v2.0** | IoT/sensor, cuaca, harga, produksi, storytelling lanjutan |

[^1]

***

## 21. Definisi Siap Rilis v1.0

Sistem siap go-live jika:

1. Admin dapat mengelola user, wilayah, OPT.
2. Petugas dapat membuat draf online/offline.
3. Draf tersimpan di server dan dapat dianalisis.
4. Filter dengan/tanpa draf bekerja di dashboard, peta, analisis, ekspor.
5. Submit, verifikasi, tolak, arsip berjalan.
6. GPS, foto, nomor laporan, rule engine benar.
7. HTTPS, `.env`, backup, restore sudah diuji.
8. UAT admin \& petugas disetujui.
9. APK/AAB release ditandatangani.
10. Dokumentasi pengguna \& SOP deploy tersedia.

***

## 22. Lampiran

### 22.1 Glosarium

| Istilah | Arti |
| :-- | :-- |
| OPT | Organisme Pengganggu Tanaman |
| ETL | Economic Threshold Level |
| Draf | Data tersimpan belum dikirim final |
| Submitted | Laporan dikirim menunggu verifikasi |
| Data resmi | Submitted + Diverifikasi |
| Data operasional | Termasuk draf |
| Rule engine | Mesin aturan analisis risiko |
| JWT | JSON Web Token untuk Flutter |

### 22.2 Keputusan Arsitektur Tercatat

| Keputusan | Pilihan |
| :-- | :-- |
| Backend | PHP 8.2 native |
| Database | MariaDB |
| Mobile | Flutter |
| Auth web | Session + CSRF |
| Auth mobile | JWT |
| Draf | Masuk server + dianalisis |
| Statistik default | Tanpa draf |
| Deploy | cPanel + Git + document root `public/` |
| Analisis | Rule-based v1 |

### 22.3 Dokumen Turunan yang Harus Dibuat di Repo

- `docs/BLUEPRINT.md` (dokumen ini)
- `docs/API.md`
- `docs/DATABASE.md`
- `docs/DEPLOYMENT.md`
- `docs/USER_GUIDE.md`
- `docs/TEST_PLAN.md`
- `CHANGELOG.md`


### 22.4 Referensi Internal

Spesifikasi teknis rebuild, alur status, API, keamanan, migrasi, dan deployment merujuk pada rancangan JAGAPADI sebelumnya serta dokumentasi aplikasi existing.[^1][^2]

***

## 23. Ringkasan Eksekutif untuk Implementasi

Bangun **satu backend PHP** yang melayani **web admin** dan **Flutter petugas**. Semua draf disimpan di database pusat, dianalisis jika data minimum tersedia, dan ditampilkan melalui filter **dengan draf / tanpa draf**. Statistik resmi default mengecualikan draf agar keputusan manajerial tetap bersih, sementara monitoring operasional tetap dapat melihat indikasi dini dari lapangan.

Urutan kerja yang disarankan: fondasi backend \& database → auth → master data → laporan hama → laporan irigasi → dashboard/peta/filter draf → offline sync Flutter → testing → deploy production → pelatihan \& go-live.

Dokumen ini siap dijadikan acuan coding, review PR, UAT, dan deployment JAGAPADI v1.0.
<span style="display:none">[^4]</span>

<div align="center">⁂</div>

[^1]: Dokumentasi-aplikasi-jagapadi-3509.md

[^2]: jagapadi-new.md

[^3]: https://flutter.dev/

[^4]: https://docs.flutter.dev/deployment/android

