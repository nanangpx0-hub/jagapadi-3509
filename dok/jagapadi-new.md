# JAGAPADI — Blueprint Teknis dan Dokumentasi Lengkap

> Versi: 2.0 (Rebuild)
> Stack: PHP 8.2 Native · MySQL 8.0+ · REST API · Android Ready
> Terakhir diperbarui: 2025

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Arsitektur Sistem](#2-arsitektur-sistem)
3. [Desain Database MySQL](#3-desain-database-mysql)
4. [Struktur Kode PHP](#4-struktur-kode-php)
5. [Alur Kerja Aplikasi](#5-alur-kerja-aplikasi)
6. [Spesifikasi Keamanan](#6-spesifikasi-keamanan)
7. [Dokumentasi API Android](#7-dokumentasi-api-android)
8. [Panduan Instalasi dan Konfigurasi Server](#8-panduan-instalasi-dan-konfigurasi-server)
9. [Panduan Penggunaan](#9-panduan-penggunaan)
10. [Panduan Pemeliharaan Sistem](#10-panduan-pemeliharaan-sistem)

---

## 1. Pendahuluan

### 1.1 Tentang Jagapadi

Jagapadi adalah sistem informasi berbasis web dan Android untuk pengumpulan, verifikasi, dan pengelolaan dua jenis laporan di sektor pertanian:

- **Laporan Hama** — pencatatan serangan Organisme Pengganggu Tanaman (OPT) pada lahan padi.
- **Laporan Kondisi Irigasi** — pencatatan kondisi fisik dan debit saluran irigasi pertanian.

Sistem ini dioperasikan di wilayah Kabupaten Jember dan melibatkan dua peran pengguna: **Petugas Lapangan** yang mengumpulkan data di lapangan, dan **Admin** yang memverifikasi laporan serta mengelola sistem.

### 1.2 Tujuan Rebuild

Versi ini dibangun ulang dari nol untuk mengatasi masalah kritis pada versi lama:

- **Keamanan**: arbitrary file upload, XSS, kredensial terekspos di kode.
- **Arsitektur**: tidak ada pemisahan logika bisnis, query SQL langsung tanpa parameterisasi.
- **Skalabilitas**: satu codebase yang melayani web dan Android secara terpisah.

### 1.3 Tech Stack

| Komponen | Teknologi |
|---|---|
| Backend | PHP 8.2 (native, tanpa framework) |
| Database | MySQL 8.0+ |
| API Auth (Android) | JWT (JSON Web Token) |
| Password Hashing | bcrypt (cost 12) |
| Antarmuka Web | HTML5 + CSS + JavaScript (vanilla/minimal) |
| Peta | Leaflet.js (JavaScript) |
| Grafik | Chart.js |
| Ekspor | SimpleXLSXWriter (PHP) |
| Cache | File-based cache (PHP) |


---

## 2. Arsitektur Sistem

### 2.1 Gambaran Umum

Jagapadi menggunakan arsitektur **single backend, dual client**: satu PHP backend melayani dua klien sekaligus — browser web via sesi, dan aplikasi Android via token JWT.

```
┌────────────────────────────────────────────────────────────────┐
│                     Klien (Browser / Android)                  │
│   Web: form + session cookie │  Android: JWT Bearer header     │
└───────────────┬─────────────────────────┬──────────────────────┘
                │ HTTPS                   │ HTTPS + Bearer/X-API-Key
                ▼                         ▼
┌────────────────────────────────────────────────────────────────┐
│                        Nginx / Apache                          │
│   .htaccess rewrite semua request → public/index.php           │
└───────────────────────────┬────────────────────────────────────┘
                            ▼
┌────────────────────────────────────────────────────────────────┐
│                  public/index.php (Entry Point)                │
│  bootstrap: autoload, env, session start, CORS header          │
└───────────────────────────┬────────────────────────────────────┘
                            ▼
┌────────────────────────────────────────────────────────────────┐
│                  Router (app/core/Router.php)                  │
│  match URI → middleware chain → Controller@method              │
└──────┬──────────────────────────────┬──────────────────────────┘
       │ Web Routes                   │ API Routes (/api/*)
       ▼                              ▼
┌─────────────┐              ┌──────────────────────┐
│ Web          │              │ API Controllers       │
│ Controllers  │              │ (app/controllers/Api/)│
│ (session)    │              │ (token/key auth)      │
└──────┬───────┘              └──────────┬────────────┘
       └────────────┬────────────────────┘
                    ▼
┌────────────────────────────────────────────────────────────────┐
│                   Models (app/models/)                         │
│  Model base → QueryBuilder (parameterized) → PDO → MySQL       │
└────────────────────────────────────────────────────────────────┘
```

### 2.2 Dua Jalur Autentikasi

| Aspek | Web (Browser) | Android |
|---|---|---|
| Mekanisme | PHP Session + CSRF Token | JWT Bearer Token |
| Middleware | `auth` | `mobile_auth` |
| State | Stateful (sesi server) | Stateless (token) |
| Perlindungan CSRF | Wajib (semua POST/PUT/DELETE) | Tidak perlu (no cookies) |
| Rate Limiting | Opsional | Wajib |

### 2.3 Middleware Chain

Setiap request melewati rantai middleware sebelum sampai ke controller:

```
Request masuk
  ├─ rate_limit?          → 429 Too Many Requests jika melebihi batas
  ├─ auth / mobile_auth   → 401 Unauthorized jika tidak valid
  ├─ admin?               → 403 Forbidden jika bukan admin
  ├─ CSRF check           → 403 jika token tidak cocok (session route)
  └─ Controller@method    → proses bisnis
```

### 2.4 Komponen Utama

| Komponen | File | Tanggung Jawab |
|---|---|---|
| Router | `app/core/Router.php` | Routing, middleware chain, CSRF enforcement |
| Controller Base | `app/core/Controller.php` | view(), json(), redirect(), checkAuth() |
| Model Base | `app/core/Model.php` | ORM ringan — CRUD, mass-assignment guard |
| QueryBuilder | `app/core/QueryBuilder.php` | Fluent query builder — semua query parameterized |
| Security | `app/core/Security.php` | CSRF, session destroy, brute-force blocking |
| CacheManager | `app/core/CacheManager.php` | File cache untuk rate limit & dashboard |
| Container | `app/core/Container.php` | Simple DI container |
| ApiAuthMiddleware | `app/middleware/ApiAuthMiddleware.php` | Validasi JWT Bearer / X-API-Key |
| RateLimiter | `app/helpers/RateLimiter.php` | Throttle per-IP per-endpoint |
| Upload_Handler | `app/helpers/OptPhotoUploader.php` | Magic bytes + MIME + ekstensi validation |


---

## 3. Desain Database MySQL

### 3.1 Diagram Relasi Entitas (ERD)

```
master_kabupaten ──< master_kecamatan ──< master_desa
                                              │
users ──< laporan_hama >── master_opt         │
users ──< laporan_irigasi ───────────────────>┘
users ──< audit_log_wilayah (sebagai admin_id)
users ──< activity_log
nomor_laporan_counter (independent — atomic counter per prefix+tanggal)
```

### 3.2 Deskripsi Tabel

| Tabel | Keterangan |
|---|---|
| `master_kabupaten` | Referensi kabupaten (level 1 wilayah) |
| `master_kecamatan` | Referensi kecamatan, FK ke kabupaten |
| `master_desa` | Referensi desa, FK ke kecamatan |
| `audit_log_wilayah` | Log setiap perubahan data wilayah oleh admin |
| `users` | Akun pengguna (admin dan petugas) |
| `master_opt` | Referensi OPT (hama, penyakit, gulma) |
| `laporan_hama` | Laporan serangan OPT — inti sistem |
| `laporan_irigasi` | Laporan kondisi irigasi |
| `activity_log` | Log aktivitas pengguna dan event keamanan |
| `nomor_laporan_counter` | Counter atomic untuk generate nomor laporan unik |

### 3.3 DDL Lengkap

#### Tabel Wilayah

```sql
-- ============================================================
-- TABEL REFERENSI WILAYAH
-- ============================================================

CREATE TABLE master_kabupaten (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode           VARCHAR(10)  NOT NULL UNIQUE COMMENT 'Kode BPS',
    nama_kabupaten VARCHAR(100) NOT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nama (nama_kabupaten)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE master_kecamatan (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kabupaten_id   INT UNSIGNED NOT NULL,
    kode           VARCHAR(10)  NOT NULL UNIQUE,
    nama_kecamatan VARCHAR(100) NOT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kabupaten (kabupaten_id),
    INDEX idx_nama (nama_kecamatan),
    FOREIGN KEY (kabupaten_id) REFERENCES master_kabupaten(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE master_desa (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kecamatan_id INT UNSIGNED NOT NULL,
    kode         VARCHAR(10)  NOT NULL UNIQUE,
    nama_desa    VARCHAR(100) NOT NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kecamatan (kecamatan_id),
    INDEX idx_nama (nama_desa),
    FOREIGN KEY (kecamatan_id) REFERENCES master_kecamatan(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log_wilayah (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED  NOT NULL,
    tabel      VARCHAR(50)   NOT NULL COMMENT 'master_kabupaten | master_kecamatan | master_desa',
    record_id  INT UNSIGNED  NOT NULL,
    aksi       ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    data_lama  JSON NULL,
    data_baru  JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin (admin_id),
    INDEX idx_tabel_record (tabel, record_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### Tabel Pengguna dan Master OPT

```sql
-- ============================================================
-- TABEL PENGGUNA
-- ============================================================

CREATE TABLE users (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username                VARCHAR(50)  NOT NULL UNIQUE,
    password                VARCHAR(255) NOT NULL COMMENT 'bcrypt cost>=12',
    email                   VARCHAR(150) NOT NULL UNIQUE,
    nama_lengkap            VARCHAR(150) NOT NULL,
    role                    ENUM('admin','petugas') NOT NULL DEFAULT 'petugas',
    aktif                   TINYINT(1)   NOT NULL DEFAULT 1,
    must_change_password    TINYINT(1)   NOT NULL DEFAULT 0,
    last_password_change_at TIMESTAMP NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_aktif (aktif),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL MASTER OPT
-- ============================================================

CREATE TABLE master_opt (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_opt    VARCHAR(150)   NOT NULL UNIQUE,
    jenis       ENUM('hama','penyakit','gulma') NOT NULL,
    etl_acuan   DECIMAL(10,2)  NULL COMMENT 'Economic Threshold Level',
    satuan_etl  VARCHAR(30)    NULL COMMENT 'individu/rumpun | %',
    foto_url    VARCHAR(300)   NULL,
    deskripsi   TEXT           NULL,
    aktif       TINYINT(1)     NOT NULL DEFAULT 1,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jenis (jenis),
    INDEX idx_aktif (aktif),
    FULLTEXT INDEX ft_nama (nama_opt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabel Laporan Hama

```sql
-- ============================================================
-- TABEL LAPORAN HAMA
-- ============================================================

CREATE TABLE laporan_hama (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_laporan     VARCHAR(20)    NOT NULL UNIQUE COMMENT 'Format: LH-YYYYMMDD-XXXX',
    user_id           INT UNSIGNED   NOT NULL,
    master_opt_id     INT UNSIGNED   NOT NULL,
    tanggal           DATE           NOT NULL,
    kabupaten_id      INT UNSIGNED   NOT NULL,
    kecamatan_id      INT UNSIGNED   NOT NULL,
    desa_id           INT UNSIGNED   NOT NULL,
    lokasi            VARCHAR(255)   NULL COMMENT 'Deskripsi lokasi bebas',
    alamat_lengkap    VARCHAR(300)   NULL,
    latitude          DECIMAL(10,7)  NULL,
    longitude         DECIMAL(10,7)  NULL,
    tingkat_keparahan ENUM('Ringan','Sedang','Berat') NOT NULL,
    luas_serangan     DECIMAL(8,2)   NULL COMMENT 'Hektar, maks 9999.99',
    populasi          DECIMAL(10,2)  NULL COMMENT 'Individu/rumpun',
    foto_url          VARCHAR(300)   NULL,
    catatan           TEXT           NULL,
    status            ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan')
                      NOT NULL DEFAULT 'Draf',
    verified_by       INT UNSIGNED   NULL,
    verified_at       TIMESTAMP      NULL,
    catatan_verifikasi TEXT          NULL,
    ip_pengirim       VARCHAR(45)    NULL COMMENT 'IPv4 atau IPv6',
    created_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_opt (master_opt_id),
    INDEX idx_status (status),
    INDEX idx_tanggal (tanggal),
    INDEX idx_kecamatan (kecamatan_id),
    INDEX idx_tingkat (tingkat_keparahan),
    INDEX idx_status_tanggal (status, tanggal),

    CONSTRAINT chk_luas CHECK (luas_serangan IS NULL OR (luas_serangan > 0 AND luas_serangan <= 9999.99)),
    CONSTRAINT chk_lat  CHECK (latitude  IS NULL OR (latitude  >= -90  AND latitude  <= 90)),
    CONSTRAINT chk_lon  CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180)),

    FOREIGN KEY (user_id)       REFERENCES users(id)            ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (master_opt_id) REFERENCES master_opt(id)       ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (kabupaten_id)  REFERENCES master_kabupaten(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (kecamatan_id)  REFERENCES master_kecamatan(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (desa_id)       REFERENCES master_desa(id)      ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (verified_by)   REFERENCES users(id)            ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```


#### Tabel Laporan Irigasi, Log Aktivitas, dan Counter

```sql
-- ============================================================
-- TABEL LAPORAN IRIGASI
-- ============================================================

CREATE TABLE laporan_irigasi (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_laporan      VARCHAR(20)    NOT NULL UNIQUE COMMENT 'Format: LI-YYYYMMDD-XXXX',
    user_id            INT UNSIGNED   NOT NULL,
    tanggal            DATE           NOT NULL,
    kabupaten_id       INT UNSIGNED   NOT NULL,
    kecamatan_id       INT UNSIGNED   NOT NULL,
    desa_id            INT UNSIGNED   NOT NULL,
    nama_saluran       VARCHAR(200)   NOT NULL COMMENT 'Nama saluran/bendungan/titik irigasi',
    daerah_irigasi     VARCHAR(200)   NULL COMMENT 'Contoh: Dam Bedadung',
    latitude           DECIMAL(10,7)  NULL,
    longitude          DECIMAL(10,7)  NULL,
    kondisi_fisik      ENUM('Bagus','Sedang','Tidak Bagus','Rusak') NOT NULL,
    debit_air          ENUM('Cukup','Kurang','Kering') NOT NULL,
    foto_url           VARCHAR(300)   NULL,
    catatan            TEXT           NULL,
    status             ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan')
                       NOT NULL DEFAULT 'Draf',
    verified_by        INT UNSIGNED   NULL,
    verified_at        TIMESTAMP      NULL,
    catatan_verifikasi TEXT           NULL,
    ip_pengirim        VARCHAR(45)    NULL,
    created_at         TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_tanggal (tanggal),
    INDEX idx_kecamatan (kecamatan_id),
    INDEX idx_kondisi (kondisi_fisik),
    INDEX idx_status_tanggal (status, tanggal),

    CONSTRAINT chk_lat_ir CHECK (latitude  IS NULL OR (latitude  >= -90  AND latitude  <= 90)),
    CONSTRAINT chk_lon_ir CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180)),

    FOREIGN KEY (user_id)      REFERENCES users(id)            ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (kabupaten_id) REFERENCES master_kabupaten(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (kecamatan_id) REFERENCES master_kecamatan(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (desa_id)      REFERENCES master_desa(id)      ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (verified_by)  REFERENCES users(id)            ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL ACTIVITY LOG
-- ============================================================

CREATE TABLE activity_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    table_name  VARCHAR(50)  NULL,
    record_id   BIGINT UNSIGNED NULL,
    description TEXT NULL,
    ip_address  VARCHAR(45)  NULL,
    user_agent  VARCHAR(500) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- COUNTER NOMOR LAPORAN (atomic increment)
-- ============================================================

CREATE TABLE nomor_laporan_counter (
    prefix   VARCHAR(10) NOT NULL,
    tanggal  DATE        NOT NULL,
    counter  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (prefix, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Counter atomic per-prefix per-tanggal untuk nomor laporan unik';
```

### 3.4 Ringkasan Kolom Kritis

| Kolom | Tabel | Tipe | Keterangan |
|---|---|---|---|
| `status` | `laporan_hama`, `laporan_irigasi` | ENUM | `Draf` → `Submitted` → `Diverifikasi`/`Ditolak` → `Diarsipkan` |
| `tingkat_keparahan` | `laporan_hama` | ENUM | `Ringan`, `Sedang`, `Berat` |
| `kondisi_fisik` | `laporan_irigasi` | ENUM | `Bagus`, `Sedang`, `Tidak Bagus`, `Rusak` |
| `debit_air` | `laporan_irigasi` | ENUM | `Cukup`, `Kurang`, `Kering` |
| `luas_serangan` | `laporan_hama` | DECIMAL(8,2) | 0 < nilai ≤ 9999.99 (CHECK constraint) |
| `latitude` | kedua laporan | DECIMAL(10,7) | -90 s/d 90 (CHECK constraint) |
| `longitude` | kedua laporan | DECIMAL(10,7) | -180 s/d 180 (CHECK constraint) |
| `password` | `users` | VARCHAR(255) | bcrypt, cost 12 |
| `nomor_laporan` | kedua laporan | VARCHAR(20) | `LH-YYYYMMDD-XXXX` / `LI-YYYYMMDD-XXXX` |


---

## 4. Struktur Kode PHP

### 4.1 Struktur Direktori Lengkap

```
jagapadi/
├── public/                          ← document root (Nginx/Apache)
│   ├── index.php                    ← entry point tunggal
│   ├── .htaccess                    ← rewrite semua request → index.php
│   └── assets/
│       ├── css/
│       ├── js/
│       └── uploads/
│           ├── opt-photos/          ← foto referensi Master OPT
│           ├── laporan-hama/        ← foto laporan hama (YYYY/MM/)
│           └── laporan-irigasi/     ← foto laporan irigasi (YYYY/MM/)
│
├── app/
│   ├── controllers/
│   │   ├── Api/                     ← API controllers (response JSON)
│   │   │   ├── BaseApiController.php
│   │   │   ├── AuthController.php   ← login JWT, refresh token
│   │   │   ├── UserController.php
│   │   │   ├── WilayahController.php
│   │   │   ├── OptController.php
│   │   │   ├── LaporanHamaController.php
│   │   │   ├── LaporanIrigasiController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DashboardMapApiController.php
│   │   │   └── DashboardChartsApiController.php
│   │   ├── AuthController.php       ← login/logout web (sesi)
│   │   ├── UserController.php       ← CRUD pengguna (admin only)
│   │   ├── WilayahController.php    ← CRUD wilayah + audit log
│   │   ├── OptController.php        ← CRUD master OPT
│   │   ├── LaporanHamaController.php
│   │   ├── IrigasiController.php
│   │   ├── ExportController.php     ← ekspor XLSX/CSV
│   │   └── DashboardController.php
│   │
│   ├── core/
│   │   ├── Router.php               ← routing, middleware, CSRF
│   │   ├── Controller.php           ← base: view(), json(), redirect()
│   │   ├── Model.php                ← ORM ringan, mass-assign guard
│   │   ├── QueryBuilder.php         ← fluent query builder parameterized
│   │   ├── Security.php             ← CSRF, brute-force, session
│   │   ├── CacheManager.php         ← file cache TTL
│   │   └── Container.php            ← DI container sederhana
│   │
│   ├── middleware/
│   │   └── ApiAuthMiddleware.php    ← validasi JWT / X-API-Key
│   │
│   ├── models/
│   │   ├── User.php                 ← validatePassword(), createUser()
│   │   ├── MasterOpt.php
│   │   ├── LaporanHama.php          ← getDashboardStats(), getTopPests()
│   │   ├── LaporanIrigasi.php
│   │   ├── MasterKabupaten.php
│   │   ├── MasterKecamatan.php
│   │   ├── MasterDesa.php
│   │   ├── AuditLogWilayah.php
│   │   └── ActivityLog.php
│   │
│   ├── helpers/
│   │   ├── LaporanHamaValidator.php    ← validasi field laporan hama
│   │   ├── LaporanIrigasiValidator.php ← validasi field laporan irigasi
│   │   ├── NomorLaporanGenerator.php   ← generate nomor atomic
│   │   ├── LaporanPhotoUploader.php    ← upload foto laporan (10MB)
│   │   ├── OptPhotoUploader.php        ← upload foto OPT (5MB)
│   │   ├── ImageCompressor.php
│   │   ├── RateLimiter.php
│   │   ├── Logger.php
│   │   └── ErrorLogger.php
│   │
│   └── views/
│       ├── layouts/
│       │   ├── main.php             ← layout utama dengan navbar
│       │   └── auth.php             ← layout halaman login
│       ├── auth/                    ← form login, ganti password
│       ├── dashboard/               ← halaman dashboard & statistik
│       ├── laporan-hama/            ← daftar, form, detail, verifikasi
│       ├── irigasi/                 ← daftar, form, detail, verifikasi
│       ├── users/                   ← manajemen pengguna
│       ├── wilayah/                 ← manajemen wilayah
│       └── opt/                     ← manajemen master OPT
│
├── config/
│   ├── config.php                   ← konstanta DB, BASE_URL, ROOT_PATH
│   └── api_config.php               ← JWT secret, API key (dari .env)
│
├── database/
│   └── migrations/                  ← file DDL SQL berurutan
│       ├── 001_create_wilayah_tables.sql
│       ├── 002_create_users_table.sql
│       ├── 003_create_master_opt_table.sql
│       ├── 004_create_laporan_hama_table.sql
│       ├── 005_create_laporan_irigasi_table.sql
│       └── 006_create_activity_log_counter.sql
│
├── storage/
│   └── cache/                       ← file cache rate limiter & dashboard
│
├── tests/                           ← unit test & property test (PHPUnit)
│
├── .env                             ← variabel lingkungan (TIDAK di-commit)
├── .env.example                     ← template .env
└── .htaccess                        ← fallback rewrite
```

### 4.2 Konvensi Kode

- Semua query database menggunakan PDO prepared statements melalui `QueryBuilder`.
- Model mendefinisikan `$fillable` untuk mencegah mass-assignment pada kolom sensitif.
- Semua output HTML melalui `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- Nama file di-generate dengan `bin2hex(random_bytes(16))` — tidak pernah menggunakan nama asli dari klien.
- Controller web memanggil `$this->checkAuth()` di setiap method yang membutuhkan autentikasi.
- Controller API mewarisi `BaseApiController` yang sudah menangani JSON envelope response.


---

## 5. Alur Kerja Aplikasi

### 5.1 Alur Submission dan Verifikasi Laporan Hama

```
Petugas / Admin
    │
    ├─── [Simpan Draf]
    │     ↓
    │     status = Draf
    │     Tidak divalidasi penuh
    │     Tidak masuk statistik
    │
    ├─── [Submit Laporan]
    │     ↓
    │     Validasi semua field wajib
    │     Generate nomor_laporan: LH-YYYYMMDD-XXXX (atomic)
    │     Catat user_id, ip_pengirim, created_at
    │     status = Submitted
    │     ─────────────────────────────────► Admin
    │
    │                      ┌──────────────────────────────┐
    │                      │       Admin meninjau         │
    │                      ├── [Verifikasi]               │
    │                      │    status = Diverifikasi     │
    │                      │    verified_by, verified_at  │
    │                      │    Masuk statistik           │
    │                      │                              │
    │                      ├── [Tolak] (alasan ≥10 char)  │
    │                      │    status = Ditolak          │
    │                      │    catatan_verifikasi wajib  │
    │                      │                              │
    │                      └── [Arsipkan]                 │
    │                           status = Diarsipkan       │
    │                           Tidak masuk statistik     │
    └──────────────────────────────────────────────────────┘
```

**Aturan transisi status yang diizinkan:**

| Dari | Ke | Pelaku | Kondisi |
|---|---|---|---|
| Draf | Submitted | Petugas / Admin | Semua field wajib terisi |
| Submitted | Diverifikasi | Admin | — |
| Submitted | Ditolak | Admin | Alasan minimal 10 karakter |
| Diverifikasi | Diarsipkan | Admin | — |
| Ditolak | Submitted | Petugas | Kirim ulang setelah perbaikan |

**Laporan yang masuk statistik:** hanya status `Submitted` dan `Diverifikasi`.

### 5.2 Alur Submission Laporan Irigasi

Identik dengan alur laporan hama, dengan perbedaan:
- Prefix nomor laporan: `LI` (bukan `LH`).
- Field wajib berbeda: `nama_saluran`, `kondisi_fisik`, `debit_air` (tidak ada `master_opt_id`).
- Tidak ada `tingkat_keparahan`.

### 5.3 Generate Nomor Laporan (Atomic)

Untuk menghindari duplikasi nomor laporan di kondisi konkurensi tinggi, digunakan teknik atomic counter:

```sql
-- Dalam satu transaksi MySQL:
INSERT INTO nomor_laporan_counter (prefix, tanggal, counter)
VALUES ('LH', CURDATE(), 1)
ON DUPLICATE KEY UPDATE counter = counter + 1;

SELECT counter FROM nomor_laporan_counter
WHERE prefix = 'LH' AND tanggal = CURDATE();

-- Format hasil: LH-20241115-0001
```

Format lengkap: `{PREFIX}-{YYYYMMDD}-{counter 4 digit zero-padded}`

### 5.4 Alur Upload Foto

```
Klien kirim multipart/form-data
    ↓
Upload_Handler::validate($file)
    ├─ Cek ukuran file (10MB laporan / 5MB OPT)
    ├─ Baca 12 byte pertama → validasi magic bytes
    │    JPEG : FF D8 FF
    │    PNG  : 89 50 4E 47 0D 0A 1A 0A
    │    WebP : RIFF????WEBP
    ├─ Validasi MIME via finfo_file() → image/jpeg | image/png | image/webp
    ├─ Validasi ekstensi → jpg | jpeg | png | webp
    └─ Gagal salah satu → 422 Unprocessable Entity
    ↓
Rename ke nama acak: bin2hex(random_bytes(16)) + ekstensi asli
Simpan ke: uploads/{modul}/YYYY/MM/{nama_acak}.{ext}
Compress via ImageCompressor jika > 2 MB
    ↓
Simpan path relatif ke kolom foto_url di database
```

### 5.5 Alur Dashboard dengan Cache

```
Request GET /api/dashboard/stats
    ↓
CacheManager::get("dashboard_stats_{role}_{userId}_{tahun}")
    ├─ HIT  → kembalikan data dari cache (TTL 5 menit)
    └─ MISS → query database (agregasi)
              CacheManager::set(key, data, TTL=300)
              kembalikan data segar
```

### 5.6 Alur Login Web

```
Form POST /auth/login
    ↓
Security::validateCsrfToken()        → 403 jika invalid
Security::checkBruteForce(ip, 5, 900) → 429 jika IP terblokir
User::authenticate(username, password)
    ├─ Gagal → catat percobaan gagal, tampilkan pesan generik
    └─ Berhasil:
         session_regenerate_id(true)    ← cegah session fixation
         Cek must_change_password
             ├─ 1 → redirect ke /change-password
             └─ 0 → redirect ke /dashboard
```

### 5.7 Alur Login Android (JWT)

```
POST /api/auth/login
Body: {"username": "...", "password": "..."}
    ↓
User::authenticate(username, password)
    ├─ Gagal → 401 {"success": false, "error": "Unauthorized"}
    └─ Berhasil:
         Generate JWT:
             header  = base64url({"alg":"HS256","typ":"JWT"})
             payload = base64url({"sub":id,"role":role,"exp":now+3600})
             sig     = HMAC-SHA256(header.payload, JWT_SECRET)
         Return {"token":"...", "expires_in":3600, "role":"..."}
```


---

## 6. Spesifikasi Keamanan

### 6.1 Perlindungan CSRF

- Semua rute web yang mengubah data (POST/PUT/DELETE) wajib menyertakan CSRF token.
- Token disimpan di `$_SESSION['csrf_token']` dan di-regenerasi setiap jam.
- Pengiriman: via field `csrf_token` (form HTML) atau header `X-CSRF-Token` (AJAX).
- Validasi menggunakan `hash_equals()` — aman dari timing attack.
- Rute API (`/api/*`) dikecualikan dari CSRF karena bersifat stateless.

```php
// Cara validasi CSRF di Security.php
if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    exit('Invalid CSRF token');
}
```

### 6.2 Rate Limiting

| Konteks | Batas | Window |
|---|---|---|
| Login | 5 percobaan gagal | 15 menit → blokir 15 menit |
| Submit laporan | 60 request | 1 jam / IP |
| API publik (wilayah, OPT) | 300 request | 1 jam / IP |
| Mobile API | 1000 request | 1 jam / IP |

Implementasi: `RateLimiter::apply()` dipanggil dari middleware. Header `X-RateLimit-Limit`, `X-RateLimit-Remaining`, dan `X-RateLimit-Reset` selalu dikembalikan.

### 6.3 Validasi Upload File

Tiga lapis validasi wajib lolos semua:

1. **Magic bytes** — baca 12 byte pertama file, cocokkan dengan signature JPEG/PNG/WebP.
2. **MIME type** — `finfo_file($tmpPath, FILEINFO_MIME_TYPE)` harus `image/jpeg`, `image/png`, atau `image/webp`.
3. **Ekstensi** — `pathinfo($filename, PATHINFO_EXTENSION)` hanya `jpg`, `jpeg`, `png`, atau `webp`.

File disimpan dengan nama acak (`bin2hex(random_bytes(16))`). Direktori upload dilindungi `.htaccess`:

```apache
# public/assets/uploads/.htaccess
php_flag engine off
AddType text/plain .php .php5 .phtml
Options -Indexes
```

### 6.4 Proteksi SQL Injection

- Semua query menggunakan PDO prepared statements melalui `QueryBuilder`.
- Nama kolom di-whitelist oleh `quoteIdentifier()` — tidak pernah menerima nama kolom dari input pengguna.
- Model menggunakan `$fillable` list sebagai mass-assignment protection.
- Nama tabel di-sanitasi: `preg_replace('/[^a-zA-Z0-9_]/', '', $table)`.

```php
// Contoh — QueryBuilder selalu parameterized
$query->where('status', '=', $status)->get();
// Menghasilkan: SELECT * FROM laporan_hama WHERE status = ? — aman.
```

### 6.5 Autentikasi dan Manajemen Sesi

- Password: `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])`.
- Verifikasi: `password_verify($input, $hash)` — aman dari timing attack.
- Setelah login berhasil: `session_regenerate_id(true)` untuk mencegah session fixation.
- Logout: hancurkan session, hapus cookie, redirect ke login.
- Brute-force: 5 gagal dalam 15 menit dari IP yang sama → blokir 15 menit.

### 6.6 Perlindungan XSS

- Semua output ke HTML: `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- Response API selalu `Content-Type: application/json` — tidak ada rendering HTML.
- `Security::sanitizeInput()` membersihkan semua input pengguna sebelum diproses.

### 6.7 Security Headers

Setiap response menyertakan header:

```
Content-Security-Policy: default-src 'self'; img-src 'self' data:
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
```

### 6.8 Validasi Password

Password baru (pembuatan akun dan ganti password) harus memenuhi semua kriteria:
- Minimal 8 karakter
- Minimal 1 huruf besar (A–Z)
- Minimal 1 huruf kecil (a–z)
- Minimal 1 angka (0–9)
- Minimal 1 karakter khusus (!@#$%^&* dsb.)

Fungsi `User::validatePassword($password)` mengembalikan array `['valid' => bool, 'errors' => [...]]`.


---

## 7. Dokumentasi API Android

### 7.1 Konvensi Umum

- **Base URL**: `https://yourdomain.com/jagapadi`
- **Format**: JSON — `Content-Type: application/json`
- **Autentikasi**: Header `Authorization: Bearer <token>` (JWT)
- **Response sukses**: `{"success": true, "data": {...}, "meta": {...}}`
- **Response error**: `{"success": false, "error": "...", "message": "..."}`
- **Pagination**: `?page=1&limit=20` → meta berisi `{total, page, limit, last_page}`

### 7.2 Kode HTTP yang Digunakan

| Kode | Kondisi |
|---|---|
| 200 | Sukses |
| 201 | Data berhasil dibuat |
| 401 | Tidak terautentikasi |
| 403 | Tidak diizinkan (role salah) |
| 404 | Data tidak ditemukan |
| 409 | Konflik (duplikat, FK aktif) |
| 422 | Validasi gagal |
| 429 | Terlalu banyak request |
| 500 | Error server |

### 7.3 Endpoint Autentikasi

#### Login

```
POST /api/auth/login
Content-Type: application/json

Request:
{
  "username": "petugas01",
  "password": "P@ssw0rd!"
}

Response 200:
{
  "success": true,
  "data": {
    "token": "eyJhbGci...",
    "expires_in": 3600,
    "role": "petugas",
    "nama_lengkap": "Ahmad Petugas"
  }
}

Response 401 (gagal):
{
  "success": false,
  "error": "Unauthorized",
  "message": "Username atau password salah"
}
```

#### Refresh Token

```
POST /api/auth/refresh
Authorization: Bearer <token_lama>

Response 200:
{
  "success": true,
  "data": {
    "token": "eyJhbGci...",
    "expires_in": 3600
  }
}
```

### 7.4 Endpoint Wilayah

```
GET /api/wilayah/kabupaten
→ Daftar semua kabupaten (tidak perlu autentikasi)

GET /api/wilayah/kecamatan?kabupaten_id=1
→ Daftar kecamatan milik kabupaten_id

GET /api/wilayah/desa?kecamatan_id=5
→ Daftar desa milik kecamatan_id

Response contoh:
{
  "success": true,
  "data": [
    {"id": 5, "nama_kecamatan": "Kaliwates", "kabupaten_id": 1},
    {"id": 6, "nama_kecamatan": "Sumbersari", "kabupaten_id": 1}
  ]
}
```

### 7.5 Endpoint Master OPT

```
GET /api/opt
GET /api/opt?jenis=hama
GET /api/opt?search=wereng

Response contoh:
{
  "success": true,
  "data": [
    {
      "id": 3,
      "nama_opt": "Wereng Batang Coklat",
      "jenis": "hama",
      "etl_acuan": 15,
      "satuan_etl": "individu/rumpun",
      "foto_url": "/assets/uploads/opt-photos/abc123.jpg"
    }
  ]
}
```

### 7.6 Endpoint Laporan Hama

#### Daftar Laporan

```
GET /api/laporan-hama
Authorization: Bearer <token>
?page=1&limit=20&status=Submitted&kecamatan_id=5
```

#### Submit Laporan

```
POST /api/laporan-hama
Authorization: Bearer <token>
Content-Type: application/json

{
  "tanggal": "2024-11-15",
  "kabupaten_id": 1,
  "kecamatan_id": 5,
  "desa_id": 23,
  "master_opt_id": 3,
  "tingkat_keparahan": "Sedang",
  "luas_serangan": 2.50,
  "populasi": 18.0,
  "latitude": -8.1734,
  "longitude": 113.7012,
  "catatan": "Populasi meningkat dari minggu lalu",
  "status": "Submitted"
}

Response 201:
{
  "success": true,
  "data": {
    "id": 42,
    "nomor_laporan": "LH-20241115-0001",
    "status": "Submitted"
  }
}
```

#### Upload Foto Laporan

```
POST /api/laporan-hama/{id}/foto
Authorization: Bearer <token>
Content-Type: multipart/form-data

foto = [file JPEG/PNG/WebP, max 10MB]

Response 200:
{
  "success": true,
  "data": {"foto_url": "/assets/uploads/laporan-hama/2024/11/a1b2c3.jpg"}
}
```

#### Detail, Update, Verifikasi

```
GET    /api/laporan-hama/{id}              ← detail
PUT    /api/laporan-hama/{id}              ← update (hanya status Draf)
POST   /api/laporan-hama/{id}/verifikasi  ← admin only
POST   /api/laporan-hama/{id}/tolak       ← admin only, body: {"alasan":"..."}
POST   /api/laporan-hama/{id}/archive     ← admin only
```

### 7.7 Endpoint Laporan Irigasi

```
GET    /api/laporan-irigasi
POST   /api/laporan-irigasi
GET    /api/laporan-irigasi/{id}
PUT    /api/laporan-irigasi/{id}
POST   /api/laporan-irigasi/{id}/verifikasi
POST   /api/laporan-irigasi/{id}/tolak
POST   /api/laporan-irigasi/{id}/archive
```

#### Contoh Request Submit Laporan Irigasi

```json
POST /api/laporan-irigasi
{
  "tanggal": "2024-11-15",
  "kabupaten_id": 1,
  "kecamatan_id": 5,
  "desa_id": 23,
  "nama_saluran": "Saluran Sekunder Bedadung 1",
  "daerah_irigasi": "Dam Bedadung",
  "kondisi_fisik": "Sedang",
  "debit_air": "Kurang",
  "latitude": -8.2011,
  "longitude": 113.6890,
  "catatan": "Terjadi kebocoran kecil di km 2",
  "status": "Submitted"
}
```

### 7.8 Endpoint Dashboard

```
GET /api/dashboard/stats         ← ringkasan angka utama
GET /api/dashboard/charts/hama   ← data grafik bulanan laporan hama
GET /api/dashboard/map/hama      ← GeoJSON titik laporan hama
GET /api/dashboard/map/irigasi   ← GeoJSON titik laporan irigasi
```

### 7.9 Contoh Respons Error Validasi (422)

```json
{
  "success": false,
  "error": "Validation Error",
  "errors": {
    "tanggal": "Tanggal wajib diisi",
    "luas_serangan": "Luas serangan harus antara 0 dan 9999.99",
    "latitude": "Latitude harus antara -90 dan 90",
    "master_opt_id": "OPT yang dipilih tidak valid atau tidak aktif"
  }
}
```


---

## 8. Panduan Instalasi dan Konfigurasi Server

### 8.1 Persyaratan Sistem

| Komponen | Minimum | Rekomendasi |
|---|---|---|
| PHP | 8.2 | 8.2+ dengan ext-pdo, ext-pdo_mysql, ext-fileinfo, ext-gd |
| MySQL | 8.0 | 8.0+ |
| Web Server | Apache 2.4 / Nginx 1.18 | Nginx 1.24+ |
| RAM | 512 MB | 2 GB |
| Storage | 10 GB | 50 GB (untuk foto laporan) |
| OS | Ubuntu 20.04 | Ubuntu 22.04 LTS |

### 8.2 Ekstensi PHP yang Diperlukan

```bash
php8.2 -m | grep -E "pdo|pdo_mysql|fileinfo|gd|mbstring|json|openssl"
# Semua harus tampil sebagai output
```

Jika ada yang kurang:
```bash
sudo apt install php8.2-pdo php8.2-mysql php8.2-fileinfo php8.2-gd \
                 php8.2-mbstring php8.2-json php8.2-openssl
```

### 8.3 Langkah Instalasi

#### 1. Clone repository

```bash
git clone https://github.com/your-org/jagapadi.git /var/www/jagapadi
cd /var/www/jagapadi
```

#### 2. Konfigurasi environment

```bash
cp .env.example .env
nano .env
```

Isi file `.env`:
```dotenv
# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jagapadi_db
DB_USER=jagapadi_user
DB_PASS=your_strong_password_here

# Aplikasi
APP_NAME=Jagapadi
APP_URL=https://yourdomain.com/jagapadi
APP_ENV=production
APP_DEBUG=false

# JWT (Android API)
JWT_SECRET=ganti_dengan_string_panjang_acak_minimal_64_karakter
JWT_EXPIRY=3600

# Upload
UPLOAD_MAX_SIZE_LAPORAN=10485760
UPLOAD_MAX_SIZE_OPT=5242880
```

#### 3. Buat database dan jalankan migrasi

```bash
mysql -u root -p -e "
  CREATE DATABASE jagapadi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'jagapadi_user'@'localhost' IDENTIFIED BY 'your_strong_password_here';
  GRANT ALL PRIVILEGES ON jagapadi_db.* TO 'jagapadi_user'@'localhost';
  FLUSH PRIVILEGES;
"

# Jalankan semua migrasi
for f in database/migrations/*.sql; do
    mysql -u jagapadi_user -p jagapadi_db < "$f"
    echo "Ran: $f"
done
```

#### 4. Atur permission direktori

```bash
# Upload directories
mkdir -p public/assets/uploads/{opt-photos,laporan-hama,laporan-irigasi}
chown -R www-data:www-data public/assets/uploads/
chmod -R 755 public/assets/uploads/

# Storage cache
mkdir -p storage/cache
chown -R www-data:www-data storage/
chmod -R 755 storage/

# Proteksi config
chmod 640 .env
chown root:www-data .env
```

#### 5. Konfigurasi Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/jagapadi/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/yourdomain.crt;
    ssl_certificate_key /etc/ssl/private/yourdomain.key;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header Referrer-Policy strict-origin-when-cross-origin;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Larang akses ke config dan storage
    location ~* ^/(config|storage|database|app|tests)/ {
        deny all;
        return 404;
    }

    # Larang eksekusi PHP di direktori upload
    location ~* ^/assets/uploads/.*\.php {
        deny all;
        return 404;
    }
}
```

#### 6. Konfigurasi Apache (alternatif)

```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/jagapadi/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/yourdomain.crt
    SSLCertificateKeyFile /etc/ssl/private/yourdomain.key

    <Directory /var/www/jagapadi/public>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    # Blokir akses ke direktori sensitif
    <DirectoryMatch "^/var/www/jagapadi/(config|storage|database|app|tests)">
        Require all denied
    </DirectoryMatch>
</VirtualHost>
```

### 8.4 Membuat Akun Admin Pertama

Setelah migrasi selesai, buat akun admin pertama via SQL:

```sql
INSERT INTO users (username, password, email, nama_lengkap, role, aktif)
VALUES (
    'admin',
    '$2y$12$hash_bcrypt_disini',  -- ganti dengan: password_hash('password_anda', PASSWORD_BCRYPT, ['cost'=>12])
    'admin@yourdomain.com',
    'Administrator',
    'admin',
    1
);
```

Atau gunakan script PHP:
```bash
php -r "echo password_hash('YourSecurePassword!1', PASSWORD_BCRYPT, ['cost' => 12]);"
```


---

## 9. Panduan Penggunaan

### 9.1 Panduan untuk Admin

#### Masuk ke Sistem

1. Buka browser, akses `https://yourdomain.com/jagapadi`
2. Masukkan username dan password admin
3. Klik **Masuk**

#### Manajemen Pengguna

**Menambah Petugas:**
1. Klik menu **Pengguna** → **Tambah Pengguna**
2. Isi nama lengkap, username, email, dan password
3. Pilih peran: `petugas`
4. Klik **Simpan** — pengguna otomatis aktif

**Menonaktifkan Pengguna:**
1. Buka daftar pengguna
2. Klik ikon **Toggle Status** pada baris pengguna
3. Konfirmasi — pengguna tidak bisa login hingga diaktifkan kembali

**Menghapus Pengguna:**
1. Klik ikon **Hapus** pada baris pengguna
2. Konfirmasi — tindakan dicatat di log aktivitas

#### Manajemen Wilayah

**Menambah Wilayah:**
1. Menu **Wilayah** → **Kabupaten / Kecamatan / Desa**
2. Klik **Tambah**, isi data, klik **Simpan**
3. Setiap perubahan tercatat di audit log wilayah

> Wilayah tidak bisa dihapus jika masih ada laporan yang menggunakannya.

#### Manajemen Master OPT

**Menambah OPT:**
1. Menu **Master OPT** → **Tambah OPT**
2. Isi nama, jenis (hama/penyakit/gulma), nilai ETL
3. Upload foto referensi (opsional, maks 5MB, format JPG/PNG/WebP)
4. Klik **Simpan**

#### Verifikasi Laporan Hama

1. Menu **Laporan Hama** → lihat laporan berstatus **Submitted**
2. Klik **Detail** untuk melihat data lengkap termasuk foto dan koordinat
3. Pilih aksi:
   - **Verifikasi** — laporan masuk statistik aktif
   - **Tolak** — wajib mengisi alasan penolakan (minimal 10 karakter)
   - **Arsipkan** — hanya untuk laporan berstatus Diverifikasi

#### Verifikasi Laporan Irigasi

Proses identik dengan laporan hama, melalui menu **Laporan Irigasi**.

#### Ekspor Data

1. Menu **Ekspor**
2. Pilih jenis laporan: Hama atau Irigasi
3. Filter berdasarkan status, kecamatan, dan rentang tanggal
4. Pilih format: **XLSX** atau **CSV**
5. Klik **Unduh** — file langsung diunduh

---

### 9.2 Panduan untuk Petugas (Web)

#### Membuat Laporan Hama

1. Login ke sistem
2. Klik menu **Laporan Hama** → **Buat Laporan**
3. Isi semua field wajib:
   - **Tanggal** kejadian
   - **Lokasi**: pilih Kabupaten → Kecamatan → Desa (dropdown cascading)
   - **OPT**: pilih dari daftar Master OPT
   - **Tingkat Keparahan**: Ringan / Sedang / Berat
   - **Luas Serangan** (hektar)
   - **Populasi** (individu/rumpun)
   - **Koordinat GPS** (opsional, bisa dari input manual atau deteksi otomatis)
4. Upload foto (opsional, maks 10MB)
5. Pilih aksi:
   - **Simpan Draf** — simpan sementara, belum dikirim ke admin
   - **Kirim Laporan** — laporan langsung masuk antrian verifikasi admin

> Petugas hanya dapat melihat laporan yang dibuat sendiri.

#### Membuat Laporan Irigasi

1. Klik menu **Laporan Irigasi** → **Buat Laporan**
2. Isi field wajib: Tanggal, Lokasi, Nama Saluran, Kondisi Fisik, Debit Air
3. Isi Daerah Irigasi dan koordinat GPS jika tersedia
4. Upload foto kondisi (opsional)
5. Klik **Simpan Draf** atau **Kirim Laporan**

---

### 9.3 Panduan untuk Pengguna Android

Unduh dan pasang aplikasi Jagapadi Android. Saat pertama kali buka:

1. Masuk dengan username dan password yang diberikan admin
2. Token otomatis disimpan — tidak perlu login ulang selama token aktif
3. Ketika token kedaluwarsa (1 jam), aplikasi otomatis memperbarui token

**Membuat Laporan dari Aplikasi:**
1. Tap **+ Laporan Hama** atau **+ Laporan Irigasi**
2. Isi form — dropdown wilayah dimuat otomatis dari server
3. Tap ikon GPS untuk mengisi koordinat secara otomatis
4. Foto bisa diambil langsung dari kamera atau dipilih dari galeri
5. Tap **Kirim** untuk submit, atau **Draf** untuk menyimpan sementara


---

## 10. Panduan Pemeliharaan Sistem

### 10.1 Backup Rutin

#### Backup Database (harian)

```bash
#!/bin/bash
# /etc/cron.daily/jagapadi-backup

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/jagapadi"
mkdir -p "$BACKUP_DIR"

mysqldump \
  --user=jagapadi_user \
  --password="$DB_PASS" \
  --single-transaction \
  --quick \
  --routines \
  jagapadi_db \
  | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Hapus backup lebih dari 30 hari
find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +30 -delete
echo "Backup selesai: db_$DATE.sql.gz"
```

#### Backup File Upload (mingguan)

```bash
# Backup direktori upload
tar -czf "/backups/jagapadi/uploads_$(date +%Y%m%d).tar.gz" \
    /var/www/jagapadi/public/assets/uploads/
```

### 10.2 Log Monitoring

File log yang perlu dipantau:

| Log | Lokasi | Isi |
|---|---|---|
| Activity Log | Tabel `activity_log` | Login, hapus user, hapus wilayah |
| Audit Wilayah | Tabel `audit_log_wilayah` | Semua perubahan data wilayah |
| Error PHP | `/var/log/php8.2-fpm.log` | Fatal error, warning |
| Nginx Access | `/var/log/nginx/access.log` | Semua HTTP request |
| Nginx Error | `/var/log/nginx/error.log` | Error 4xx/5xx |

Memeriksa percobaan login mencurigakan:

```sql
SELECT ip_address, COUNT(*) as percobaan, MAX(created_at) as terakhir
FROM activity_log
WHERE action = 'login_failed'
  AND created_at >= NOW() - INTERVAL 1 DAY
GROUP BY ip_address
HAVING COUNT(*) > 10
ORDER BY percobaan DESC;
```

### 10.3 Pembersihan Data

#### Arsipkan laporan lama (tahunan)

```sql
-- Arsipkan laporan hama yang sudah Diverifikasi lebih dari 2 tahun
UPDATE laporan_hama
SET status = 'Diarsipkan'
WHERE status = 'Diverifikasi'
  AND verified_at < NOW() - INTERVAL 2 YEAR;
```

#### Bersihkan cache

```bash
# Hapus file cache lebih dari 1 jam
find /var/www/jagapadi/storage/cache/ -name "*.cache" -mmin +60 -delete
```

#### Bersihkan activity log lama

```sql
-- Hapus log aktivitas lebih dari 1 tahun
DELETE FROM activity_log
WHERE created_at < NOW() - INTERVAL 1 YEAR;
```

### 10.4 Pembaruan Sistem

Sebelum update kode:
1. Pastikan sudah ada backup database terbaru
2. Buat branch baru sesuai AGENTS.md
3. Uji di environment staging terlebih dahulu
4. Jalankan migrasi database jika ada perubahan skema

```bash
# Update kode (setelah merge PR ke main)
cd /var/www/jagapadi
git pull origin main

# Jalankan migrasi baru jika ada
for f in database/migrations/*.sql; do
    if ! grep -q "$f" /var/www/jagapadi/storage/migrations_ran.txt 2>/dev/null; then
        mysql -u jagapadi_user -p jagapadi_db < "$f"
        echo "$f" >> /var/www/jagapadi/storage/migrations_ran.txt
        echo "Ran: $f"
    fi
done

# Clear cache
find storage/cache/ -name "*.cache" -delete
```

### 10.5 Troubleshooting Umum

| Masalah | Kemungkinan Penyebab | Solusi |
|---|---|---|
| Login gagal terus | IP terblokir brute-force | Hapus record di tabel `activity_log` atau tunggu 15 menit |
| Upload foto gagal | Permission direktori | `chown -R www-data:www-data public/assets/uploads/` |
| Dashboard lambat | Cache expired, data besar | Pastikan TTL 5 menit aktif, tambah indeks jika diperlukan |
| JWT token ditolak | Secret berubah atau token expired | User login ulang; periksa `JWT_SECRET` di `.env` |
| Error 403 pada form | CSRF token invalid / expired | Muat ulang halaman untuk mendapatkan token baru |
| Query lambat | Indeks tidak optimal | Gunakan `EXPLAIN` pada query yang lambat |

### 10.6 Kontak dan Eskalasi

Untuk masalah yang tidak dapat diselesaikan dengan panduan di atas:
1. Periksa log error di `/var/log/nginx/error.log` dan tabel `activity_log`.
2. Dokumentasikan langkah reproduksi masalah.
3. Buat issue di repository dengan label yang sesuai.
4. Sertakan output `EXPLAIN` untuk masalah performa database.

---

*Dokumen ini merupakan referensi teknis lengkap Jagapadi v2.0.*
*Untuk perubahan pada sistem, ikuti prosedur branch dan Pull Request sesuai AGENTS.md.*
