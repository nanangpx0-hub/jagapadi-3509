# Clone JAGAPADI dari GitHub — Panduan Lengkap

> **Repository:** `https://github.com/nanangpx0-hub/jagapadi-3509`  
> **Branch:** `main`  
> **Remote yang terkonfigurasi:** `origin` → `https://github.com/nanangpx0-hub/jagapadi-3509.git`

> **Dokumen terkait:**  
> - Deploy ke server: [`PANDUAN_DEPLOYMENT.md`](PANDUAN_DEPLOYMENT.md)  
> - VPS/Nginx: [`DEPLOY.md`](DEPLOY.md)  
> - Domain & Cloudflare: [`PANDUAN_DOMAIN_CLOUDFLARE_JAGAPADI_MY_ID.md`](PANDUAN_DOMAIN_CLOUDFLARE_JAGAPADI_MY_ID.md)  
> - Akses PC lain: [`PANDUAN_AKSES_PC_LAIN.md`](PANDUAN_AKSES_PC_LAIN.md)

---

## Daftar Isi

1. [Prasyarat](#1-prasyarat)
2. [Clone Repository](#2-clone-repository)
3. [Instal Dependency](#3-instal-dependency)
4. [Buat File Environment (.env)](#4-buat-file-environment-env)
5. [Buat Database & Import](#5-buat-database--import)
6. [Konfigurasi Tambahan](#6-konfigurasi-tambahan)
7. [Jalankan Aplikasi](#7-jalankan-aplikasi)
8. [Verifikasi End-to-End](#8-verifikasi-end-to-end)
9. [Update Berkala (Git Pull)](#9-update-berkala-git-pull)
10. [Troubleshooting](#10-troubleshooting)
11. [Pengingat Keamanan](#11-pengingat-keamanan)

---

## 1. Prasyarat

### 1.1 Software yang diperlukan (Windows + Laragon)

| Software | Minimum | Verifikasi |
|---|---|---|
| **OS** | Windows 10/11 64-bit | `winver` |
| **Laragon** | 3.x+ (Apache 2.4, PHP 8.2, MySQL 8.0) | `laragon` → Start All |
| **PHP** | 8.2.32+ | `php -v` |
| **Composer** | 2.x | `composer --version` |
| **Git** | 2.x | `git --version` |
| **MySQL client** | 8.0+ (via Laragon) | `mysql --version` |
| **Ekstensi PHP** | `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `curl`, `zip`, `json`, `xml` | `php -m` |

Cek ekstensi PHP:
```powershell
php -m | findstr "pdo_mysql mbstring fileinfo gd curl zip json"
```

> Jika ada yang tidak ada: buka Laragon menu **PHP → Extensions** → centang ekstensi yang belum → Restart.

### 1.2 Akun GitHub

- Punya akun [GitHub](https://github.com) (gratis sudah cukup)
- **Opsional** namun direkomendasikan: [Personal Access Token (PAT)](https://github.com/settings/tokens) dengan scope `repo` (untuk push)

---

## 2. Clone Repository

### Langkah 2.1 — Clone ke direktori Laragon

```powershell
# Buka PowerShell
cd C:\laragon\www
git clone https://github.com/nanangpx0-hub/jagapadi-3509.git
cd jagapadi-3509
```

### Langkah 2.2 — Verifikasi

```powershell
git remote -v
# Output:
# origin  https://github.com/nanangpx0-hub/jagapadi-3509.git (fetch)
# origin  https://github.com/nanangpx0-hub/jagapadi-3509.git (push)

git branch -a
# Output:
# * main
#   remotes/origin/main

git log --oneline -5
# Output: 5 commit terbaru
```

### Langkah 2.3 — Cek status bersih dari file sensitif

```powershell
git status --short
# Output seharusnya BERSIH atau hanya file yang memang tidak di-track
# (Jangan ada .env, config/config.php, dll.)
```

Jika ada `.env` atau `config/config.php` di output, remove:
```powershell
git restore --staged .env config/config.php 2>nul
Remove-Item .env, config/config.php -ErrorAction SilentlyContinue
```

---

## 3. Instal Dependency

### 3.1 — Backend Composer (di dalam folder `backend/`)

```powershell
cd C:\laragon\www\jagapadi-3509\backend
composer install --no-dev --optimize-autoloader
```

Verifikasi:
```powershell
php -r "require 'vendor/autoload.php'; echo 'OK';"
# Output: OK
```

### 3.2 — Root dependencies

File di root (`index.php`, `app/`) menggunakan **autoload PSR-4 bawaan** yang sudah ada di `index.php:140-157`. Tidak perlu `composer install` di root.

### 3.3 — Frontend (opsional)

Jika ingin menggunakan Vite/build tool (untuk asset modernization):
```powershell
cd C:\laragon\www\jagapadi-3509
npm ci
```

> Ini opsional; aplikasi sudah jalan tanpa ini.

---

## 4. Buat File Environment (.env)

### 4.1 — Salin .env.example

```powershell
cd C:\laragon\www\jagapadi-3509
Copy-Item .env.example .env
```

### 4.2 — Edit .env

Buka `.env` di editor (VS Code, Notepad++, atau IDE favorit).

Isi minimum yang **wajib** diganti:

```ini
# ============================================
# JAGAPADI — Environment (dari .env.example)
# ============================================

# Aplikasi
APP_NAME=JAGAPADI
APP_ENV=local          # development
APP_DEBUG=true         # development; production -> false
APP_URL=http://localhost/jagapadi-3509

# Database (sesuaikan nama DB yang sudah dibuat di §5)
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jagapadi_local          # <-- Ganti jika beda
DB_USER=root                    # Default Laragon = root, password kosong
DB_PASS=                        # Default Laragon = kosong
DB_CHARSET=utf8mb4

# Cache
CACHE_ENABLED=true
CACHE_DRIVER=file
CACHE_PREFIX=jagapadi
CACHE_DEFAULT_TTL=60

# API Authentication
JWT_SECRET=<HASIL-generate>     # <-- WAJIB diganti
JWT_EXPIRY=3600

# CORS (untuk akses dari localhost, origin lain)
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://localhost:3000,http://192.168.1.0/16

# Email (opsional)
SMTP_HOST=smtp.mailtrap.io
SMTP_PORT=2525
SMTP_USER=your_user
SMTP_PASS=your_pass
SMTP_FROM=no-reply@jagapadi.test
SMTP_FROM_NAME=JAGAPADI System

# Fitur
AUTO_APPROVE_ENABLED=false
SESSION_LIFETIME=28800
```

### 4.3 — Generate JWT_SECRET

```powershell
php -r "echo bin2hex(random_bytes(32));"
```

Salin output, tempel di `JWT_SECRET=...` di `.env`.

### 4.4 — Permission .env

```powershell
# Jangan pernah commit .env ke Git!
# Laravel/Laragon akan membacanya otomatis.
```

> **Penting:** `.env` sudah di `.gitignore` (lihat `.gitignore:6-8`). File ini **TIDAK** akan ikut commit/push.

---

## 5. Buat Database & Import

### 5.1 — Jalankan MySQL via Laragon

Pastikan Laragon sudah **Start All** (Apache + MySQL berjalan).

### 5.2 — Buat database

```powershell
# Via MySQL CLI
"C:\laragon\bin\mysql\mysql8.0.30\bin\mysql" -u root -e "CREATE DATABASE IF NOT EXISTS jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Atau via phpMyAdmin:
1. Buka `http://localhost/phpmyadmin`
2. **Databases** → **Create database** → `jagapadi_local` → Collation `utf8mb4_unicode_ci` → **Create**

### 5.3 — Import database

**Opsi A — Via CLI:**

Jika ada file `.sql` backup di repositori:
```powershell
# Cek apakah ada dump
dir database\*.sql

# Import (ganti nama file sesuai yang ada)
"C:\laragon\bin\mysql\mysql8.0.30\bin\mysql" -u root jagapadi_local < "C:\laragon\www\jagapadi-3509\database\jagapadi_local_dump.sql"
```

> Catatan: File dump SQL (kecuali `backend/database/jagapadi_local_dump.sql` yang di-allow `.gitignore` baris 177) **biasanya tidak di-commit** ke GitHub karena ukuran besar dan mengandung data sensitif.

**Opsi B — Jalankan migrasi:**

File migrasi sudah di repository. Jalankan:
```powershell
cd C:\laragon\www\jagapadi-3509
php database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php
```

Ini akan membuat tabel `evaluasi_akurasi_panen` dan `evaluasi_akurasi_logs` jika belum ada.

**Opsi C — Import via phpMyAdmin (untuk file `.sql` yang sudah Anda punya):**

1. Buka `http://localhost/phpmyadmin`
2. Pilih database `jagapadi_local`
3. Tab **Import** → Choose File → Character set `utf8mb4` → **Go**

### 5.4 — Verifikasi tabel

```powershell
"C:\laragon\bin\mysql\mysql8.0.30\bin\mysql" -u root -e "SHOW TABLES FROM jagapadi_local"
```

Hasil harus mencakup minimal: `users`, `evaluasi_akurasi_panen`, `data_ksa_bulanan`, `laporan_hama`, `laporan_irigasi`, `feedback`, dll.

---

## 6. Konfigurasi Tambahan

### 6.1 — Buat `config/config.php` (opsional)

```powershell
# Jika belum ada, buat manual
New-Item -ItemType Directory -Force -Path "C:\laragon\www\jagapadi-3509\config" | Out-Null
```

Isi `config/config.php`:
```php
<?php
// Batas koordinat Kabupaten Jember (WGS84)
if (!defined('JEMBER_LAT_MIN')) define('JEMBER_LAT_MIN', -8.480000);
if (!defined('JEMBER_LAT_MAX')) define('JEMBER_LAT_MAX', -7.960000);
if (!defined('JEMBER_LON_MIN')) define('JEMBER_LON_MIN', 113.280000);
if (!defined('JEMBER_LON_MAX')) define('JEMBER_LON_MAX', 113.980000);

// BPS WebAPI (opsional)
if (!defined('BPS_API_KEY'))      define('BPS_API_KEY', getenv('BPS_API_KEY') ?: '');
if (!defined('BPS_API_BASE_URL')) define('BPS_API_BASE_URL', getenv('BPS_API_BASE_URL') ?: 'https://webapi.bps.go.id/v1');
if (!defined('BPS_API_TIMEOUT'))  define('BPS_API_TIMEOUT', (int)(getenv('BPS_API_TIMEOUT') ?: 30));
```

> File `config/config.php` juga sudah di `.gitignore` (baris 122).

### 6.2 — Pastikan .htaccess aktif

File `.htaccess` sudah di root (`C:\laragon\www\jagapadi-3509\.htaccess`). Pastikan:
- Apache `mod_rewrite` aktif (Laragon biasanya sudah)
- `AllowOverride All` di `httpd.conf`

Cek di Laragon: **Menu → Apache → httpd.conf** → pastikan ada `LoadModule rewrite_module`.

### 6.3 — Atur document root (jika perlu)

Document root JAGAPADI root adalah **folder `jagapadi-3509` itu sendiri** (bukan `backend/public`). Ini karena `index.php` di root adalah front controller utama.

Di **Laragon → Menu → File → Server Configuration**, atur document root ke:
```
C:\laragon\www\jagapadi-3509
```

Atau gunakan virtual host bawaan Laragon yang otomatis men-point ke folder di `C:\laragon\www\`.

---

## 7. Jalankan Aplikasi

### 7.1 — Start Laragon

```powershell
# Buka Laragon → Start All (Apache + MySQL)
# Atau klik "Start All" di tray icon Laragon
```

### 7.2 — Buka browser

```
http://localhost/jagapadi-3509
http://localhost/jagapadi-3509/login
http://localhost/jagapadi-3509/dashboard
http://localhost/jagapadi-3509/evaluasi
```

### 7.3 — Login pertama

Jika belum ada akun admin, buat via SQL:
```sql
INSERT INTO users (username, password, nama_lengkap, role, aktif, must_change_password)
VALUES ('admin', '$2y$12$...', 'Administrator', 'admin', 1, 1);
```

Hasil hash password:
```powershell
php -r "echo password_hash('StrongAdminPass!123', PASSWORD_BCRYPT, ['cost'=>12]);"
```

Atau jalankan seed (hanya development):
```powershell
cd C:\laragon\www\jagapadi-3509\backend
php scripts/seed.php
```

---

## 8. Verifikasi End-to-End

### 8.1 — Health check

```powershell
curl http://localhost/jagapadi-3509/api/v1/health
# Expected: {"success":true,"database":"connected",...}
```

### 8.2 — Web app

Buka browser:
1. `http://localhost/jagapadi-3509/login` → form login tampil ✅
2. Login admin → dashboard terbuka ✅
3. `http://localhost/jagapadi-3509/evaluasi` → dashboard evaluasi tampil ✅
4. `http://localhost/jagapadi-3509/api/v1/health` → JSON ✅

### 8.3 — CSRF & Security

```powershell
# Cek .env tidak terekspos
curl http://localhost/jagapadi-3509/.env
# Expected: 403 / 404 (bukan 200)

# Cek CSRF
# Submit form POST ke /evaluasi/store tanpa token → 403
```

### 8.4 — PHPUnit (opsional)

```powershell
# Jalankan test suite
php backend/vendor/phpunit/phpunit/phpunit --configuration phpunit.xml

# Test spesifik evaluasi
php backend/vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --filter "EvaluasiAkurasi"
```

Expected: semua test OK.

---

## 9. Update Berkala (Git Pull)

### 9.1 — Pull kode terbaru

```powershell
cd C:\laragon\www\jagapadi-3509
git pull origin main
```

### 9.2 — Install dependency baru (jika ada)

```powershell
cd backend
composer install --no-dev --optimize-autoloader
```

### 9.3 — Jalankan migrasi baru (jika ada)

```powershell
cd ..
php database/migrations/<nama_migration_baru>.php
```

### 9.4 — Clear cache

```powershell
# Jika ada cache file
Remove-Item -Recurse -Force storage/cache/* -ErrorAction SilentlyContinue
```

### 9.5 — Jangan lupa

- **Jangan** `git pull` jika ada `.env` yang belum dibuat di server (akan conflict)
- **Jangan** commit `.env` ke repositori
- File `.env`, `config/config.php`, `*.sql`, `backend/vendor/` **selalu diabaikan** oleh `.gitignore`

---

## 10. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| `404` semua kecuali `/` | `mod_rewrite` tidak aktif | Aktifkan di `httpd.conf`, pastikan `.htaccess` `AllowOverride All` |
| `500` / white screen | `APP_DEBUG=true` → cek `storage/logs/` | Baca log error, biasanya `.env` salah atau DB tidak connect |
| `DB connection failed` | `.env` DB salah / MySQL mati | Cek `php -r "require 'app/core/Database.php';"`, pastikan MySQL jalan |
| `Class 'PDO' not found` | Ekstensi `pdo_mysql` tidak aktif | Laragon → PHP → Extensions → centang `pdo_mysql` → Restart |
| `Composer not found` | Composer tidak di PATH | Instal Composer atau gunakan `php composer.phar` |
| `403 CSRF` | Session expired | Refresh halaman → kirim ulang form |
| `Permission denied` `.env` | `.env` permission salah | `chmod 600 .env` atau hapus lalu recreate |
| `Git pull` conflict `.env` | `.env` sudah di-commit sebelumnya | `git restore --staged .env` lalu `git checkout -- .env` |
| `Table 'jagapadi_local.xxx' doesn't exist` | Database belum diimport | Import `.sql` atau jalankan migrasi |
| `404 /api/v1/*` | `Alias /api/v1` belum dikonfigurasi | Tambah `Alias /api/v1 "C:/laragon/www/jagapadi-3509/backend/public"` di Apache config |
| `Login loop` | `APP_BASE_URL` salah | Set `APP_URL=http://localhost/jagapadi-3509` di `.env` |
| `Assets CSS/JS hilang` | Base URL salah / path asset | Cek `BASE_URL` di `.env`, pastikan `public/assets/` ada |
| `Upload gagal` | Permission folder upload | `chmod -R 775 backend/public/assets/uploads/` |

**Diagnosis cepat:**
```powershell
php -v                          # PHP versi
php -m | findstr pdo_mysql      # PDO aktif?
mysql -u root -e "SELECT 1"     # MySQL jalan?
php scripts/check_db_connection.php  # Koneksi DB
curl http://localhost/jagapadi-3509/api/v1/health  # App jalan?
```

---

## 11. Pengingat Keamanan

### File yang **TIDAK boleh** di-commit / di-push:

| File | Mengapa | Status `.gitignore` |
|---|---|---|
| `.env`, `.env.local` | Credential DB, JWT, API keys | ✅ Di-ignore |
| `config/config.php` | Konstanta sensitif | ✅ Di-ignore |
| `*.sql`, `*.sql.gz` | Data produksi | ✅ Di-ignore |
| `backend/vendor/` | Dependency, bukan source | ✅ Di-ignore |
| `backend/storage/*` | Cache, logs, uploads | ✅ Di-ignore |
| `*.key`, `*.pem`, `*.crt` | SSL/private key | ✅ Di-ignore |
| `google-services.json` | FCM credentials | ✅ Di-ignore |
| `*.jks`, `key.properties` | APK signing keys | ✅ Di-ignore |
| `cookies.txt`, `cj.txt`, dll | Data sensitif debug | ✅ Di-ignore |
| `public/uploads/*` | File pengguna | ✅ Di-ignore |

### Jangan pernah:
- Commit `.env`, password, JWT_SECRET, DB password ke GitHub
- Push file `.sql` yang berisi data produksi
- Push `google-services.json`, `*.jks`, `key.properties`
- Share `config/config.php` ke publik

### Pastikan:
- `APP_DEBUG=false` di production
- `APP_ENV=production` di production
- `CORS_ALLOWED_ORIGINS` spesifik (bukan `*`)
- `JWT_SECRET` 64+ karakter acak
- `.env` permission `600`

---

## Checklist Cepat Clone

```
[ ] Git terinstall: git --version
[ ] Laragon terinstall & Start All
[ ] cd C:\laragon\www && git clone https://github.com/nanangpx0-hub/jagapadi-3509.git
[ ] cd jagapadi-3509\backend && composer install --no-dev --optimize-autoloader
[ ] php -r "require 'vendor/autoload.php'; echo 'OK';" → OK
[ ] Copy .env.example → .env
[ ] Edit .env: APP_URL, DB_*, JWT_SECRET (generate), CORS_ALLOWED_ORIGINS
[ ] Buat database: CREATE DATABASE jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
[ ] Import DB atau jalankan migrasi: php database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php
[ ] Verifikasi tabel: SHOW TABLES FROM jagapadi_local
[ ] Buat config/config.php (opsional)
[ ] Buka browser: http://localhost/jagapadi-3509
[ ] curl /api/v1/health → connected
[ ] Login → dashboard tampil
[ ] /evaluasi → dashboard evaluasi tampil
[ ] phpunit → semua test OK
```

---

*Dokumen ini melengkapi [`PANDUAN_DEPLOYMENT.md`](PANDUAN_DEPLOYMENT.md) (deploy ke server cPanel/Jagoan Hosting), [`DEPLOY.md`](DEPLOY.md) (VPS Ubuntu/Nginx), dan [`PANDUAN_DOMAIN_CLOUDFLARE_JAGAPADI_MY_ID.md`](PANDUAN_DOMAIN_CLOUDFLARE_JAGAPADI_MY_ID.md) (domain & SSL).*

*Setelah clone & setup, Anda bisa mengakses dari PC lain via LAN (`http://192.168.x.x/jagapadi-3509`) atau internet via Cloudflare Tunnel (`https://jagapadi.my.id`) — lihat [`PANDUAN_AKSES_PC_LAIN.md`](PANDUAN_AKSES_PC_LAIN.md).*

*Jangan pernah menempel isi `.env`, `config/config.php`, `*.sql`, `*.key`, atau password ke dokumen ini atau chat.*
