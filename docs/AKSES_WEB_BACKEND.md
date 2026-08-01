# Akses Web Backend JAGAPADI

Panduan praktis untuk mengakses web admin JAGAPADI secara lokal maupun di server production.

## 1. Ringkasan

- Web admin menggunakan PHP session + CSRF.
- API mobile/Android menggunakan JWT pada path `/api/v1/...`.
- Document root aplikasi adalah `backend/public`, bukan root repository.
- Entry point backend: `backend/public/index.php`.
- Route login web: `/login`.
- Health endpoint publik: `/api/v1/health`.

## 2. Prasyarat

- Laragon (Apache/Nginx + MySQL) atau PHP 8.2 CLI
- Composer
- Ekstensi PHP: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`, `json`, `curl`
- MySQL 8 / MariaDB 10.6+
- Migrasi database telah dijalankan

## 3. Setup lokal (Laragon)

### 3.1 Buka project

```powershell
cd c:\laragon\www\jagapadi-3509
```

### 3.2 Install dependency backend

```powershell
cd backend
composer install
```

> Linux/macOS: `cd backend && composer install`

### 3.3 Siapkan file environment

```powershell
Copy-Item .env.example .env
```

Edit `backend\.env` dan isi paling minimal:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_BASE_URL=http://localhost:8080`
- `JWT_SECRET` (gunakan string acak minimal 64 karakter)
- `CORS_ALLOWED_ORIGINS` untuk origin lokal

### 3.4 Buat database dan jalankan migrasi

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd backend
php scripts/migrate.php
```

> Perintah migrasi menjalankan semua file di `backend/database/migrations/`.

### 3.5 Seed data dev (hanya lokal)

```powershell
cd backend
php scripts/seed.php
```

> Seed hanya untuk `APP_ENV=local` atau `APP_ENV=development`.

Akun seed lokal:

- `admin` / `ChangeMeAdmin!123`
- `petugas01` / `ChangeMePetugas!123`

Segera ganti password setelah login pertama jika akun seed digunakan di lingkungan non-dev.

### 3.6 Pilih document root

#### Opsi A — Virtual host Laragon

Arahkan document root ke:

```text
c:\laragon\www\jagapadi-3509\backend\public
```

#### Opsi B — Server PHP bawaan

```powershell
cd c:\laragon\www\jagapadi-3509\backend
php -S localhost:8080 -t public
```

### 3.7 URL akses utama

- Web login: `http://localhost:8080/login`
- Dashboard: `http://localhost:8080/dashboard`
- Health API: `http://localhost:8080/api/v1/health`

### 3.8 Login web

Gunakan akun seed lokal untuk mencoba web admin/petugas.

Setelah login, akses menu yang tersedia berdasarkan peran.

### 3.9 Menu web yang tersedia

Fitur yang benar-benar tersedia di backend saat ini:

- Dashboard
- Laporan Hama
- Laporan Irigasi
- Export
- Notifikasi
- Wilayah (admin)
- OPT (admin)

## 4. Cek cepat "web hidup"

| Cek | URL / perintah | Hasil diharapkan |
|---|---|---|
| Health | `GET /api/v1/health` | JSON berhasil, database connected |
| Login page | `http://localhost:8080/login` | Form login tampil |
| Login admin | form login | Menu dashboard muncul |
| CSRF | submit form POST ke `/login` | Tidak 403 dengan token valid |

Contoh cek cepat:

```powershell
curl http://localhost:8080/api/v1/health
```

## 5. Akses di jaringan LAN (opsional)

Untuk mengakses dari perangkat lain di jaringan lokal:

- Gunakan IP host: `http://192.168.x.x:8080`
- Pastikan firewall membuka port yang digunakan
- Untuk server PHP bawaan, gunakan bind `0.0.0.0` jika perlu

> Jangan gunakan mode ini untuk production tanpa HTTPS dan kontrol origin.

## 6. Production (ringkas)

Referensi ringkas:

- `docs/DEPLOY.md`
- `docs/GO_LIVE_CHECKLIST.md`

Produksi harus memakai:

- `APP_ENV=production`
- `APP_DEBUG=false`
- Document root `backend/public`
- `CORS_ALLOWED_ORIGINS` hanya origin yang diizinkan
- HTTPS, tidak pakai seed password

## 7. Troubleshooting

- 404 semua route: document root salah atau rewrite tidak aktif
- DB connection failed: cek `backend/.env` dan MySQL
- 500 blank: cek `APP_DEBUG`, periksa log di `backend/storage/logs`
- Login loop / CSRF 403: cek `APP_BASE_URL`, session, dan cookie browser
- Assets CSS/JS hilang: cek base URL / path asset
- Upload gagal: cek permission folder `backend/public/assets/uploads`

## 8. Keamanan singkat

- Jangan expose file `.env`
- Ganti `JWT_SECRET` dan password admin/petugas
- Gunakan rate limit login
- Jangan commit `google-services.json`, `.jks`, `key.properties`, atau file secret lain
