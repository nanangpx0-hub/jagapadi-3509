# Petunjuk Teknis Menjalankan Aplikasi JAGAPADI

> **JAGAPADI — Jember Agrikultur Gapai Prestasi Digital**
> Sistem pelaporan pertanian (Hama/OPT & Kondisi Irigasi) untuk Kabupaten Jember.
>
> Panduan ini mencakup semua tahapan yang diperlukan untuk menginstalasi, mengonfigurasi, dan menjalankan aplikasi JAGAPADI di lingkungan lokal maupun produksi.

---

## Daftar Isi

1. [Prasyarat Sistem](#1-prasyarat-sistem)
2. [Instalasi Dependensi Aplikasi](#2-instalasi-dependensi-aplikasi)
3. [Konfigurasi Database](#3-konfigurasi-database)
4. [Menjalankan Aplikasi](#4-menjalankan-aplikasi)
5. [Verifikasi Instalasi](#5-verifikasi-instalasi)
6. [Pemecahan Masalah Umum (Troubleshooting)](#6-pemecahan-masalah-umum-troubleshooting)
7. [Kontak Tim Dukungan](#7-kontak-tim-dukungan)

---

## 1. Prasyarat Sistem

### 1.1 Perangkat Lunak yang Diperlukan

| Komponen | Versi Minimum | Keterangan |
|----------|--------------|------------|
| **PHP** | 8.2 | Backend aplikasi. Wajib dengan ekstensi berikut: `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `curl`, `zip`, `openssl`, `json` |
| **Composer** | 2.x | Dependency manager PHP untuk backend |
| **MySQL / MariaDB** | MySQL 8.0+ / MariaDB 10.6+ | Database aplikasi (charset `utf8mb4`, collation `utf8mb4_unicode_ci`) |
| **Node.js** | 20.x LTS | Untuk build aset frontend (Vite, Chart.js) |
| **npm** | 10.x | Package manager Node.js (terinstal otomatis dengan Node.js) |
| **Flutter SDK** | 3.x (Dart ^3.0.0) | Untuk pengembangan dan build aplikasi mobile Android |
| **Android SDK** | API 24+ | Untuk build dan deploy aplikasi mobile |
| **JDK** | 17 | Diperlukan untuk Flutter/Android build |

### 1.2 Spesifikasi Perangkat Keras Minimum

| Komponen | Spesifikasi Minimum | Rekomendasi |
|----------|-------------------|-------------|
| **CPU** | 2 core | 4 core+ |
| **RAM** | 4 GB | 8 GB+ |
| **Penyimpanan** | 2 GB kosong | 10 GB+ (termasuk database, dependency, build cache) |
| **Resolusi Layar** | 1024×768 | 1920×1080+ |

### 1.3 Ekstensi PHP yang Harus Diinstal

Backend JAGAPADI membutuhkan ekstensi PHP berikut:

```bash
# Linux (Ubuntu/Debian)
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-gd \
  php8.2-mbstring php8.2-curl php8.2-xml php8.2-zip php8.2-fileinfo \
  php8.2-opcache php8.2-bcmath composer

# macOS (Homebrew)
brew install php@8.2 composer

# Windows (Laragon/XAMPP)
# Ekstensi biasanya sudah termasuk. Aktifkan di php.ini:
# extension=pdo_mysql, extension=gd, extension=mbstring, extension=curl, dll.
```

Verifikasi ekstensi PHP yang terpasang:

```bash
php -m | grep -E 'pdo_mysql|gd|mbstring|curl|zip|fileinfo|openssl|json'
```

### 1.4 Dependensi Eksternal per Sistem Operasi

#### Windows

- **Laragon** (disarankan) atau **XAMPP** — menyediakan Apache/Nginx + MySQL + PHP terintegrasi
- **Composer** — unduh dari [getcomposer.org](https://getcomposer.org) dan jalankan `composer install`
- **Node.js** — unduh dari [nodejs.org](https://nodejs.org) (pilih LTS)
- **Flutter SDK** — unduh dari [flutter.dev](https://flutter.dev), ekstrak ke `C:\src\flutter`, tambahkan ke `PATH`
- **Android Studio** — untuk Android SDK dan emulator

#### macOS

```bash
# Homebrew
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# PHP + Composer
brew install php@8.2 composer

# Node.js
brew install node

# Flutter
brew install --cask flutter android-studio

# Xcode command line tools (untuk build)
xcode-select --install
```

#### Linux (Ubuntu 22.04/24.04)

```bash
# Update sistem
sudo apt update && sudo apt upgrade -y

# PHP + ekstensi
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-gd \
  php8.2-mbstring php8.2-curl php8.2-xml php8.2-zip php8.2-fileinfo \
  php8.2-opcache php8.2-bcmath composer unzip git curl

# Node.js (via NodeSource)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL
sudo apt install -y mysql-server-8.0

# Flutter
sudo snap install flutter --classic
# atau: wget dan ekstrak dari flutter.dev
```

---

## 2. Instalasi Dependensi Aplikasi

### 2.1 Kloning Repositori Kode Sumber

```bash
# Kloning repository
git clone https://github.com/nanangpx5-netizen/jagapadi.git jagapadi-3509
cd jagapadi-3509

# Atau jika sudah ada di lokal (misal di Laragon)
cd c:\laragon\www\jagapadi-3509
```

### 2.2 Instalasi Dependensi Backend (PHP)

```bash
# Masuk ke folder backend
cd backend

# Instalasi dependency PHP via Composer
composer install

# Output yang diharapkan:
# Loading composer repositories with package information
# Installing dependencies from lock file
# Package operations: 5 installs, 0 updates, 0 removals
#   - Syncing pclzip/pclzip (v2.8.2) into the project
# Generating optimized autoload files
# Generated optimized autoload files in 0.05 seconds
```

> **Catatan untuk Production**: Gunakan `composer install --no-dev --optimize-autoloader` untuk mengecilkan ukuran dependency.

### 2.3 Instalasi Dependensi Frontend (Node.js)

```bash
# Kembali ke root repository
cd ..

# Instalasi dependency Node.js (Vite, Chart.js, Playwright)
npm install

# Output yang diharapkan:
# added 150+ packages, and audited 200+ packages in 5s
# 50+ packages are looking for funding
# found 0 vulnerabilities
```

### 2.4 Instalasi Dependensi Mobile (Flutter)

```bash
# Masuk ke folder mobile
cd mobile

# Instalasi dependency Flutter
flutter pub get

# Output yang diharapkan:
# Running "flutter pub get" in jagapadi_mobile...
# Created jagapadi_mobile/.dart_tool/package_config.json.
# pub get completed w/o error
```

### 2.5 Konfigurasi Variabel Lingkungan (Environment Variables)

Salin file contoh environment dan sesuaikan dengan konfigurasi lokal Anda:

```bash
# Masuk ke folder backend
cd backend

# Salin file .env.example ke .env
# Windows (PowerShell):
Copy-Item .env.example .env

# Linux/macOS:
cp .env.example .env

# Atur permission (Linux/macOS production):
chmod 640 .env
```

#### Contoh Konfigurasi `.env` untuk Lingkungan Lokal (Development)

Edit file `backend/.env` dengan nilai berikut:

```ini
# --- Application ---
APP_NAME=JAGAPADI
APP_ENV=local
APP_DEBUG=true
APP_BASE_URL=http://localhost:8080
APP_TIMEZONE=Asia/Jakarta

# --- Database ---
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jagapadi_local
DB_USER=root
DB_PASS=                    # kosongkan jika tidak ada password
DB_CHARSET=utf8mb4

# --- JWT (Mobile Auth) ---
# Generate dengan: php -r "echo bin2hex(random_bytes(32));"
JWT_SECRET=f91a0c8ff362bc2e57f88854e7ca99f2e7844dad83563648f55cb6378d2b7ebb
JWT_EXPIRY=3600

# --- Web Session ---
SESSION_NAME=jagapadi_session
LOGIN_MAX_ATTEMPTS=5
LOGIN_DECAY_SECONDS=900

# --- Logging ---
APP_LOG_LEVEL=debug

# --- CORS ---
# Untuk development, biarkan kosong (menggunakan dev defaults localhost)
CORS_ALLOWED_ORIGINS=

# --- Firebase Cloud Messaging ---
FCM_ENABLED=false
FCM_SERVER_KEY=
FCM_PROJECT_ID=
```

#### Contoh Konfigurasi `.env` untuk Produksi

```ini
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://jagapadi.example.go.id

DB_NAME=jagapadi_prod
DB_USER=jagapadi_user
DB_PASS=<strong-random-password>

JWT_SECRET=<64+ random hex characters>
JWT_EXPIRY=3600

CORS_ALLOWED_ORIGINS=https://admin.jagapadi.example.go.id

FCM_ENABLED=false
```

#### Penjelasan Variabel Environment Penting

| Variabel | Wajib | Deskripsi |
|----------|-------|-----------|
| `APP_ENV` | Ya | `local` untuk development, `production` untuk produksi |
| `APP_DEBUG` | Ya | `true` untuk development, **harus `false`** untuk produksi |
| `APP_BASE_URL` | Ya | URL dasar aplikasi (http untuk dev, https untuk prod) |
| `DB_HOST` | Ya | Host database (biasanya `127.0.0.1`) |
| `DB_PORT` | Ya | Port database (biasanya `3306`) |
| `DB_NAME` | Ya | Nama database |
| `DB_USER` | Ya | Username database |
| `DB_PASS` | Ya | Password database |
| `JWT_SECRET` | Ya | Secret key JWT (minimal 64 karakter hex) |
| `CORS_ALLOWED_ORIGINS` | Produksi | Daftar origin yang diizinkan (HTTPS only di produksi) |
| `FCM_ENABLED` | Opsional | Aktifkan push notification (`true`/`false`) |

> **⚠️ Penting**: Jangan pernah commit file `.env` ke repository. File ini berisi informasi sensitif seperti password database dan secret key.

---

## 3. Konfigurasi Database

### 3.1 Membuat Database

#### MySQL / MariaDB

```bash
# Masuk ke MySQL
mysql -u root -p

# Pada prompt MySQL, jalankan:
```

```sql
-- Buat database dengan charset utf8mb4
CREATE DATABASE IF NOT EXISTS jagapadi_local
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Keluar
EXIT;
```

#### Alternatif: Membuat database via command line

```bash
# Linux/macOS
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Windows (PowerShell)
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3.2 Menjalankan Migrasi Database

Migrasi database akan membuat semua tabel yang diperlukan oleh aplikasi. Aplikasi menggunakan sistem migrasi berbasis file SQL di folder `backend/database/migrations/`.

```bash
# Pastikan berada di folder backend
cd backend

# Jalankan migrasi
php scripts/migrate.php
```

**Output yang diharapkan:**

```
=== JAGAPADI Database Migration ===

  [OK]   001_create_schema_migrations.sql
  [OK]   002_create_wilayah_tables.sql
  [OK]   003_create_users_table.sql
  [OK]   004_create_master_opt_table.sql
  [OK]   005_create_laporan_hama_table.sql
  [OK]   006_create_laporan_irigasi_table.sql
  [OK]   007_create_activity_log_and_counter.sql
  [OK]   008_create_verifikasi_indexes.sql
  [OK]   009_create_notifications_table.sql
  [OK]   010_create_device_tokens_table.sql
  [OK]   011_create_jwt_blacklist_table.sql
  [OK]   012_add_user_roles_to_enum.sql
  [OK]   013_add_coordinates_to_master_kecamatan.sql

=== Summary ===
  Executed: 13
  Skipped:  0
  Batch:    1
```

> **Catatan**: Jika migrasi sudah pernah dijalankan, file yang sudah dieksekusi akan dilewati (`[SKIP]`). Sistem melacak migrasi yang sudah dijalankan di tabel `schema_migrations`.

### 3.3 Pengisian Data Awal (Seeding)

Seed data hanya boleh dijalankan di lingkungan **lokal/development**, bukan di produksi. Seed akan mengisi data master (wilayah Jember, OPT, dan akun pengguna default).

```bash
# Pastikan berada di folder backend
cd backend

# Pastikan APP_ENV=local atau APP_ENV=development di .env
# Jalankan seed
php scripts/seed.php
```

**Output yang diharapkan:**

```
=== JAGAPADI Database Seed ===
  Environment: local

  [OK]   001_seed_wilayah_jember.sql
  [OK]   002_seed_users_local.sql
  [OK]   003_seed_master_opt.sql

--- Seed Users ---
  [OK]   admin created (role: admin)
  [OK]   petugas01 created (role: petugas)
  [OK]   operator01 created (role: operator)
  [OK]   statistisi01 created (role: statistisi)

=== Summary ===
  SQL seed files executed: 3
  Users seeded: 4 (admin, petugas01, operator01, statistisi01)
  Users:        4
  Master OPT:   15
  Kabupaten:    1
  Kecamatan:    20
  Desa:         220
```

#### Akun Seed Lokal (Development)

| Username | Password | Role | Keterangan |
|----------|----------|------|------------|
| `admin` | `ChangeMeAdmin!123` | admin | Administrator sistem |
| `petugas01` | `ChangeMePetugas!123` | petugas | Petugas lapangan |
| `operator01` | `ChangeMeOperator!123` | operator | Operator irigasi |
| `statistisi01` | `ChangeMeStatistisi!123` | statistisi | Statistisi |

> **⚠️ Penting**: Ganti password semua akun seed setelah login pertama. Akun seed tidak boleh digunakan di lingkungan produksi.

#### Seed Dummy Data (Opsional, untuk Testing/QA)

Untuk mengisi data dummy yang lebih banyak (untuk testing performa atau QA):

```bash
# Pastikan database tersedia
php scripts/seed_dummy.php --count=1000 --db=jagapadi_local --scenario=all

# Untuk membersihkan data dummy:
php scripts/seed_dummy.php --clean --db=jagapadi_local
```

---

## 4. Menjalankan Aplikasi

### 4.1 Mode Pengembangan (Development)

#### Backend (PHP Built-in Server)

Cara termudah untuk menjalankan backend di lingkungan development adalah menggunakan PHP built-in server:

```bash
# Masuk ke folder backend
cd backend

# Jalankan server PHP di port 8080
php -S localhost:8080 -t public

# Atau (alternatif):
cd backend/public
php -S localhost:8080
```

**Output yang diharapkan:**

```
[Sun Jul 20 10:00:00 2026] PHP 8.2.20 Development Server (http://localhost:8080) started
[Sun Jul 20 10:00:00 2026] [Worker PID 1234] Loading worker
[Sun Jul 20 10:00:00 2026] [Worker PID 1234] Worker spawned
```

#### Frontend (Vite Development Server)

Jika proyek menggunakan Vite untuk aset frontend:

```bash
# Di root repository
npm run dev

# Output yang diharapkan:
#   vite v5.4.0  ready in 500 ms
#
#   ➜  Local:   http://localhost:5173/
#   ➜  Network: use --host to expose
```

#### Mobile (Flutter Development)

```bash
# Masuk ke folder mobile
cd mobile

# Jalankan di emulator atau perangkat yang terhubung
flutter run

# Untuk menentukan API base URL development:
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1

# Output yang diharapkan:
# Launching lib/main.dart on Android SDK built for emulator in debug mode...
# Running podspec...
# Connected device is AVD (mobile)
# W/ jagapadi_mobile (package:jagapadi_mobile)...
```

> **Catatan**: `10.0.2.2` adalah alamat IP khusus emulator Android yang merujuk ke host machine (localhost). Gunakan ini untuk mengakses backend yang berjalan di komputer Anda.

### 4.2 Mode Produksi (Production)

#### Backend (PHP-FPM + Nginx)

Untuk produksi, gunakan PHP-FPM dengan web server Nginx. Ikuti panduan lengkap di `docs/DEPLOY.md`.

**Langkah singkat:**

1. **Install PHP-FPM dan Nginx** (lihat bagian 1.4)
2. **Clone dan install dependency**:

```bash
cd /var/www
git clone https://github.com/nanangpx5-netizen/jagapadi.git jagapadi
cd jagapadi/backend
composer install --no-dev --optimize-autoloader
```

3. **Konfigurasi `.env`** untuk produksi (lihat bagian 2.5)
4. **Buat database dan jalankan migrasi** (lihat bagian 3)
5. **Atur permission direktori**:

```bash
cd /var/www/jagapadi

# Direktori yang perlu writable oleh web server
chown -R www-data:www-data backend/storage/cache
chown -R www-data:www-data backend/storage/logs
chown -R www-data:www-data backend/storage/tmp
chown -R www-data:www-data backend/public/assets/uploads

# Set permission
find backend/storage -type d -exec chmod 775 {} \;
find backend/public/assets/uploads -type d -exec chmod 775 {} \;

# Lindungi .env
chmod 640 backend/.env
```

6. **Konfigurasi Nginx** (lihat `docs/DEPLOY.md` bagian 6 untuk konfigurasi lengkap)
7. **Restart PHP-FPM dan Nginx**:

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

8. **Setup HTTPS** dengan Let's Encrypt:

```bash
sudo certbot --nginx -d jagapadi.example.go.id
sudo certbot renew --dry-run
```

#### Mobile (Flutter Release Build)

```bash
cd mobile

# Build APK release dengan API URL produksi
flutter build apk --release \
  --dart-define=API_BASE_URL=https://jagapadi.example.go.id/api/v1 \
  --dart-define=FCM_ENABLED=true

# Atau build App Bundle (untuk Google Play Store)
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://jagapadi.example.go.id/api/v1 \
  --dart-define=FCM_ENABLED=true

# Output APK: mobile/build/app/outputs/flutter-apk/app-release.apk
```

### 4.3 Mode Debug

#### Backend Debug

Untuk mode debug, pastikan `APP_DEBUG=true` di `.env`:

```ini
APP_ENV=local
APP_DEBUG=true
```

Jalankan server development:

```bash
cd backend
php -S localhost:8080 -t public
```

Log aplikasi akan tersedia di `backend/storage/logs/app.log`.

#### Mobile Debug

```bash
cd mobile
flutter run --debug
```

Untuk melihat log debug:

```bash
flutter logs
```

#### Frontend Debug

```bash
npm run dev
```

Akses Vite dev server di `http://localhost:5173`.

---

## 5. Verifikasi Instalasi

### 5.1 Health Check Endpoint

Setelah aplikasi berjalan, verifikasi dengan mengakses health endpoint:

```bash
# Development (PHP built-in server)
curl -sS http://localhost:8080/api/v1/health | jq .

# Production
curl -sS https://jagapadi.example.go.id/api/v1/health | jq .
```

**Response 200 (database tersambung):**

```json
{
  "success": true,
  "message": "JAGAPADI is healthy",
  "data": {
    "app": "JAGAPADI",
    "environment": "local",
    "time": "2026-07-20T10:00:00+07:00",
    "database": "connected"
  }
}
```

**Response 503 (database tidak tersedia):**

```json
{
  "success": false,
  "error": "DatabaseUnavailable",
  "message": "Layanan database tidak tersedia."
}
```

### 5.2 URL Akses yang Dapat Diakses

#### Web Admin (Session Auth)

| URL | Deskripsi | Auth |
|-----|-----------|------|
| `http://localhost:8080/login` | Form login | Public |
| `http://localhost:8080/dashboard` | Dashboard utama | Session |
| `http://localhost:8080/laporan-hama` | List laporan hama | Session |
| `http://localhost:8080/laporan-irigasi` | List laporan irigasi | Session |
| `http://localhost:8080/export` | Form export data | Session |
| `http://localhost:8080/notifications` | List notifikasi | Session |
| `http://localhost:8080/wilayah` | Master wilayah (admin) | Admin |
| `http://localhost:8080/opt` | Master OPT (admin) | Admin |

#### API Endpoints (JWT Auth)

| URL | Deskripsi | Auth |
|-----|-----------|------|
| `http://localhost:8080/api/v1/health` | Health check | Public |
| `http://localhost:8080/api/v1/auth/login` | Login mobile (JWT) | Public |
| `http://localhost:8080/api/v1/auth/refresh` | Refresh token | JWT |
| `http://localhost:8080/api/v1/auth/logout` | Logout | JWT |
| `http://localhost:8080/api/v1/auth/change-password` | Ubah password | JWT |
| `http://localhost:8080/api/v1/me` | Profil user | JWT |
| `http://localhost:8080/api/v1/dashboard/stats` | Statistik dashboard | JWT |
| `http://localhost:8080/api/v1/laporan-hama` | CRUD laporan hama | JWT |
| `http://localhost:8080/api/v1/laporan-irigasi` | CRUD laporan irigasi | JWT |
| `http://localhost:8080/api/v1/wilayah/kabupaten` | Master kabupaten | JWT |
| `http://localhost:8080/api/v1/opt` | Master OPT | JWT |
| `http://localhost:8080/api/v1/export/hama` | Export laporan hama | JWT |
| `http://localhost:8080/api/v1/notifications` | Notifikasi | JWT |

### 5.3 Endpoint Health Check yang Dapat Diuji

#### 1. Health Check

```bash
curl -sS http://localhost:8080/api/v1/health
```

**Indikator sukses:** Response 200 dengan `"database": "connected"`.

#### 2. API Authentication (Login)

```bash
# Login sebagai admin
curl -sS -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"ChangeMeAdmin!123"}' | jq .
```

**Response yang diharapkan:**

```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "username": "admin",
      "nama_lengkap": "Administrator JAGAPADI",
      "role": "admin",
      "is_active": 1,
      "must_change_password": true
    }
  }
}
```

Simpan token untuk pengujian selanjutnya:

```bash
TOKEN="eyJhbGciOiJIUzI1NiIs..."
```

#### 3. Master Data (Wilayah)

```bash
# List kabupaten
curl -sS http://localhost:8080/api/v1/wilayah/kabupaten \
  -H "Authorization: Bearer $TOKEN" | jq '.success'
```

**Indikator sukses:** `true`

#### 4. Laporan Hama (Create Draft)

```bash
curl -sS -X POST http://localhost:8080/api/v1/laporan-hama \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "draft",
    "tanggal": "2026-07-20",
    "master_opt_id": 1,
    "kabupaten_id": 1,
    "kecamatan_id": 1,
    "desa_id": 1,
    "tingkat_keparahan": "Sedang",
    "luas_serangan": 1.5,
    "populasi": 10,
    "lokasi": "Blok sawah utara"
  }' | jq .
```

**Indikator sukses:** Response 201 dengan `"status": "Draf"` dan `"nomor_laporan": null`.

#### 5. Notifikasi (Unread Count)

```bash
curl -sS http://localhost:8080/api/v1/notifications/unread-count \
  -H "Authorization: Bearer $TOKEN" | jq '.data.count'
```

**Indikator sukses:** Angka integer (0 atau lebih).

#### 6. Export (CSV)

```bash
curl -sS -o /dev/null -w "%{http_code}" \
  "http://localhost:8080/api/v1/export/hama?format=csv" \
  -H "Authorization: Bearer $TOKEN"
```

**Indikator sukses:** `200`

### 5.4 Indikator Status Aplikasi Berjalan Normal

| Indikator | Status Normal | Cara Cek |
|-----------|--------------|----------|
| **Health endpoint** | HTTP 200, `"database": "connected"` | `curl /api/v1/health` |
| **Login API** | HTTP 200, mengembalikan JWT token | `curl /api/v1/auth/login` |
| **Web login page** | HTTP 200, form login tampil | Buka `http://localhost:8080/login` di browser |
| **Dashboard** | HTTP 200, charts dan map render | Login lalu akses `/dashboard` |
| **Database connection** | Semua query berhasil | Periksa log di `storage/logs/app.log` |
| **Cache directory** | Dapat ditulis | `ls -la storage/cache/` |
| **Upload directory** | Dapat ditulis | `ls -la public/assets/uploads/` |
| **Log files** | Log baru ditambahkan | `tail -f storage/logs/app.log` |

### 5.5 Verifikasi Web Admin (Browser)

1. Buka browser dan akses `http://localhost:8080/login`
2. Login dengan akun seed: `admin` / `ChangeMeAdmin!123`
3. Setelah login, pastikan:
   - Dashboard tampil dengan charts dan statistik
   - Menu navigasi tersedia (Laporan Hama, Laporan Irigasi, Export, Notifikasi)
   - Tombol logout berfungsi
4. Cek konsol browser (F12) untuk memastikan tidak ada error 404 pada aset CSS/JS

---

## 6. Pemecahan Masalah Umum (Troubleshooting)

### 6.1 Masalah Koneksi Database

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL/MariaDB tidak berjalan | Jalankan `sudo systemctl start mysql` (Linux) atau start MySQL di Laragon/XAMPP |
| `SQLSTATE[HY000] [1045] Access denied` | Username/password database salah | Periksa `DB_USER` dan `DB_PASS` di `.env` |
| `SQLSTATE[42000] [1049] Unknown database` | Database belum dibuat | Jalankan `CREATE DATABASE jagapadi_local ...` |
| `DatabaseUnavailable` (503) | Aplikasi tidak dapat connect ke DB | Pastikan MySQL berjalan, cek konfigurasi `.env`, pastikan port 3306 terbuka |

**Langkah verifikasi:**

```bash
# Pastikan MySQL berjalan
mysql -u root -p -e "SHOW DATABASES;"

# Uji koneksi dari PHP
php -r "
require 'backend/vendor/autoload.php';
require 'backend/.env';
\$pdo = new PDO('mysql:host=127.0.0.1;dbname=jagapadi_local', 'root', '');
echo 'Connected: ' . (\$pdo ? 'YES' : 'NO');
"
```

### 6.2 Masalah Composer / Dependency

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `composer: command not found` | Composer belum terinstal | Instal Composer: `brew install composer` (macOS) atau unduh dari getcomposer.org |
| `PHP version does not meet requirements` | PHP versi < 8.2 | Upgrade ke PHP 8.2+ |
| `pclzip/pclzip not found` | Dependency belum terpasang | Jalankan `composer install` di folder `backend/` |
| `Class 'App\Core\Env' not found` | Autoloader belum dibuild | Jalankan `composer dump-autoload` |

**Langkah verifikasi:**

```bash
cd backend
composer install
composer dump-autoload
php -r "require 'vendor/autoload.php'; echo 'Autoload OK';"
```

### 6.3 Masalah Environment (.env)

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `500 Internal Server Error` | File `.env` tidak ditemukan | Salin `.env.example` ke `.env`: `cp .env.example .env` |
| `JWT_SECRET not set` | Secret JWT kosong | Generate: `php -r "echo bin2hex(random_bytes(32));"` dan isi ke `JWT_SECRET` |
| `CORS error` di browser | Origin tidak diizinkan | Tambahkan origin ke `CORS_ALLOWED_ORIGINS` di `.env` |
| `APP_BASE_URL` salah | URL dasar tidak sesuai | Pastikan `APP_BASE_URL` sesuai dengan URL server |

### 6.4 Masalah Migrasi Database

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `Migration dihentikan. Perbaiki error` | SQL error pada migration | Baca pesan error, perbaiki SQL, hapus tabel yang gagal, jalankan ulang `php scripts/migrate.php` |
| `already executed` | Migrasi sudah pernah dijalankan | Migrasi dilewati otomatis, tidak perlu dihapus |
| `schema_migrations table not found` | Tabel tracking belum ada | Biasanya dibuat otomatis oleh script migrasi |

**Reset migrasi (development only):**

```bash
# Hapus semua tabel dan jalankan ulang
mysql -u root -p -e "DROP DATABASE jagapadi_local; CREATE DATABASE jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd backend
php scripts/migrate.php
php scripts/seed.php
```

### 6.5 Masalah Web Server / Routing

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `404 Not Found` semua route | Document root salah | Pastikan document root mengarah ke `backend/public/`, bukan `backend/` |
| `403 Forbidden` | Permission salah | Periksa permission direktori, pastikan web server dapat membaca file |
| `500 Internal Server Error` | .htaccess tidak berfungsi (Apache) | Pastikan `mod_rewrite` aktif: `a2enmod rewrite && systemctl restart apache2` |
| Route tidak ditemukan | Rewrite rule tidak aktif | Untuk Nginx, gunakan konfigurasi yang benar (lihat DEPLOY.md) |

**Verifikasi document root:**

```bash
# PHP built-in server (development)
cd backend
php -S localhost:8080 -t public

# Pastikan index.php dapat diakses
curl -I http://localhost:8080/
# Harus mengembalikan 200 OK
```

### 6.6 Masalah Permission

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `Failed to write cache file` | `storage/cache/` tidak writable | `chmod -R 775 storage/cache/` |
| `Failed to write log` | `storage/logs/` tidak writable | `chmod -R 775 storage/logs/` |
| `Upload failed` | `public/assets/uploads/` tidak writable | `chmod -R 775 public/assets/uploads/` |
| `.env` dapat dibaca publik | Permission terlalu longgar | `chmod 640 .env` |

**Perbaiki semua permission (Linux/macOS):**

```bash
cd /path/to/jagapadi/backend

# Direktori yang perlu writable
chmod -R 775 storage/cache/
chmod -R 775 storage/logs/
chmod -R 775 storage/tmp/
chmod -R 775 public/assets/uploads/

# Lindungi .env
chmod 640 .env

# Pastikan .htaccess ada di uploads
ls -la public/assets/uploads/.htaccess
# Jika tidak ada, buat file .htaccess:
echo "php_flag engine off" > public/assets/uploads/.htaccess
```

### 6.7 Masalah Flutter / Mobile

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `flutter: command not found` | Flutter belum di PATH | Tambahkan Flutter ke PATH: `export PATH="$PATH:/path/to/flutter/bin"` |
| `Connection refused` di emulator | Backend tidak berjalan atau API URL salah | Pastikan backend berjalan, gunakan `10.0.2.2` untuk emulator |
| `Cleartext HTTP blocked` | Android memblokir HTTP non-HTTPS | Gunakan HTTPS atau `10.0.2.2` untuk emulator |
| `google-services.json not found` | Firebase belum dikonfigurasi | Salin dari Firebase Console ke `mobile/android/app/` |
| `FCM token registration failed` | Backend FCM tidak enabled | Pastikan `FCM_ENABLED=true` dan `FCM_SERVER_KEY` di `.env` |

**Verifikasi Flutter:**

```bash
flutter doctor
# Pastikan semua centang hijau (atau ada solusi untuk yang bermasalah)
```

### 6.8 Masalah JWT / Auth

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `401 Unauthorized` | Token JWT tidak valid atau kadaluarsa | Login ulang, refresh token dengan `/api/v1/auth/refresh` |
| `403 Forbidden` | Role tidak sesuai | Pastikan user memiliki role yang benar |
| `TokenInvalid` | JWT_SECRET berubah | Pastikan `JWT_SECRET` konsisten antara generate dan verify |
| `must_change_password` | Password harus diganti | Ganti password via web atau API `/api/v1/auth/change-password` |

### 6.9 Masalah Upload File

| Error | Penyebab | Solusi |
|-------|----------|--------|
| `File bukan gambar yang diizinkan` | Format tidak didukung | Gunakan JPEG, PNG, atau WebP |
| `Ukuran file melebihi batas` | File > 10 MB | Kompresi gambar sebelum upload |
| `Upload failed: permission denied` | Direktori uploads tidak writable | `chmod 775 public/assets/uploads/` |
| PHP execution in uploads | .htaccess tidak aktif | Pastikan `php_flag engine off` di `.htaccess` |

### 6.10 Masalah Produksi

| Error | Penyebab | Solusi |
|-------|----------|--------|
| Stack trace tampil di error | `APP_DEBUG=true` di produksi | Set `APP_DEBUG=false` di `.env` produksi |
| Mixed content warning | Aset HTTP di halaman HTTPS | Pastikan semua aset menggunakan HTTPS |
| Session tidak persisten | Cookie `secure` tidak sesuai | Pastikan HTTPS aktif, session cookie secure=1 |
| Rate limit terlalu ketat | Terlalu banyak request | Sesuaikan `LOGIN_MAX_ATTEMPTS` dan `LOGIN_DECAY_SECONDS` |

---

## 7. Kontak Tim Dukungan

Jika Anda mengalami masalah yang tidak tercantum dalam bagian troubleshooting di atas, silakan menghubungi tim dukungan melalui saluran berikut:

### 7.1 Saluran Dukungan

| Kanal | Deskripsi | Waktu Respon |
|-------|-----------|-------------|
| **GitHub Issues** | Untuk bug, fitur, dan diskusi teknis | 1–3 hari kerja |
| **Email** | Untuk pertanyaan internal dan dukungan langsung | 1 hari kerja |
| **Dokumentasi** | Panduan lengkap di folder `docs/` | Selalu tersedia |

### 7.2 Melaporkan Masalah

Saat melaporkan masalah, sertakan informasi berikut:

1. **Deskripsi masalah** — Jelaskan apa yang terjadi
2. **Langkah reproduksi** — Langkah-langkah untuk mereproduksi masalah
3. **Environment** — Sistem operasi, versi PHP, versi aplikasi
4. **Log error** — Salin pesan error dari `storage/logs/app.log`
5. **Screenshot** — Jika berkaitan dengan UI

**Template laporan masalah:**

```
Judul: [Singkat] Deskripsi masalah

Environment:
- OS: [Windows/macOS/Linux]
- PHP: [versi]
- App version: [versi/tag]
- APP_ENV: [local/production]

Deskripsi:
[Deskripsikan masalah secara detail]

Langkah Reproduksi:
1. [Langkah 1]
2. [Langkah 2]
3. [Langkah 3]

Expected:
[Apa yang diharapkan]

Actual:
[Apa yang sebenarnya terjadi]

Log/Error:
[Sertakan pesan error atau log yang relevan]
```

### 7.3 Dokumentasi Tambahan

| Dokumen | Deskripsi | Lokasi |
|---------|-----------|--------|
| **Blueprint** | Ringkasan arsitektur dan aturan bisnis | `docs/BLUEPRINT.md` |
| **API Contract** | Kontrak API lengkap | `docs/API.md` |
| **Deployment Guide** | Panduan deployment production | `docs/DEPLOY.md` |
| **Database Schema** | Skema database | `backend/database/schema.sql` |
| **Smoke Test** | Prosedur tes pasca-deploy | `docs/SMOKE_TEST.md` |
| **Go-Live Checklist** | Checklist pra-go-live | `docs/GO_LIVE_CHECKLIST.md` |
| **User Guide** | Panduan penggunaan aplikasi | `docs/PANDUAN_PENGGUNA.md` |
| **Build APK** | Panduan build aplikasi mobile | `docs/BUILD_APK.md` |

---

## Lampiran: Ringkasan Perintah Penting

### Backend

```bash
# Development server
cd backend && php -S localhost:8080 -t public

# Migrasi database
cd backend && php scripts/migrate.php

# Seed data (development only)
cd backend && php scripts/seed.php

# Jalankan test
cd backend && vendor/bin/phpunit

# Lint PHP
cd backend && php -l app/ config/ public/index.php
```

### Frontend

```bash
# Development server (Vite)
npm run dev

# Build production
npm run build

# Preview production build
npm run preview
```

### Mobile

```bash
# Development
cd mobile && flutter run

# Build APK debug
cd mobile && flutter build apk --debug

# Build APK release
cd mobile && flutter build apk --release \
  --dart-define=API_BASE_URL=https://your-domain.com/api/v1

# Build App Bundle (Play Store)
cd mobile && flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://your-domain.com/api/v1
```

---

> **Catatan**: Panduan ini diperbarui secara berkala. Pastikan Anda selalu merujuk ke dokumentasi terbaru di folder `docs/` dan `AGENTS.md` untuk aturan kerja tim.
