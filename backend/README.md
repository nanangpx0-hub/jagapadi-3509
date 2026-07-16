# JAGAPADI Backend

PHP 8.2 native backend dengan pola MVC ringan untuk sistem pelaporan pertanian JAGAPADI.

---

## Persyaratan

- PHP >= 8.2
- Composer
- MySQL 8.0+ / MariaDB 10.6+
- Apache mod_rewrite (untuk production)

---

## Setup Lokal

```bash
cd backend
composer install
cp .env.example .env
```

Sesuaikan konfigurasi database di `.env`:

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jagapadi_local
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

## Migrasi Database

Buat database lokal terlebih dahulu:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Jalankan migrasi:

```bash
cd backend
php scripts/migrate.php
```

## Seed Data Lokal

```bash
cd backend
php scripts/seed.php
```

> **Peringatan**: Seed hanya untuk environment `local` / `development`. Seed tidak bisa dijalankan di `production`.

Kredensial seed lokal (wajib ganti setelah login pertama):
- **admin** / ChangeMeAdmin!123
- **petugas01** / ChangeMePetugas!123

## Menjalankan Server Development

```bash
cd backend/public
php -S localhost:8080
```

## Menguji Health Endpoint

```bash
curl -i http://localhost:8080/api/v1/health
```

**Respons yang diharapkan:**
- **200 OK** — aplikasi dan database tersambung
- **503 Service Unavailable** — database belum tersedia atau konfigurasi salah

## Menjalankan Test

```bash
cd backend
vendor/bin/phpunit
```

## Struktur Direktori

```
backend/
├── app/
│   ├── Core/           # Framework inti (Env, Database, Router, Controller, Request, Security, Jwt, Model)
│   ├── Controllers/    # Controller (Api/ dan Web/)
│   ├── Helpers/        # Fungsi pembantu (RateLimiter, PasswordValidator)
│   ├── Middleware/      # Middleware (Csrf, WebAuth, ApiAuth, Admin)
│   ├── Models/          # Model database (User, ActivityLog)
│   ├── Services/        # Service layer (belum diimplementasikan)
│   └── Views/           # Template view (layouts, auth, dashboard)
├── config/             # Konfigurasi (routes.php)
├── database/
│   ├── migrations/     # File migrasi SQL (7 file)
│   ├── seeds/          # File seed SQL
│   └── schema.sql      # Referensi skema lengkap
├── public/             # Document root
│   └── index.php       # Entry point
├── scripts/            # Utility scripts
│   ├── migrate.php     # Migration runner
│   └── seed.php        # Seed runner
├── storage/            # Cache, log, migration tracker
├── tests/              # PHPUnit tests
├── .env.example        # Template konfigurasi
├── composer.json
└── phpunit.xml
```

## API Endpoint

| Method | Path | Auth | Deskripsi |
|--------|------|------|-----------|
| GET | `/api/v1/health` | Public | Pemeriksaan kesehatan aplikasi dan database |
| POST | `/api/v1/auth/login` | Public | Login mobile (mengembalikan JWT) |
| POST | `/api/v1/auth/refresh` | JWT | Refresh token |
| POST | `/api/v1/auth/logout` | JWT | Logout |
| POST | `/api/v1/auth/change-password` | JWT | Ubah password |
| GET | `/api/v1/me` | JWT | Profil user saat ini |
| GET | `/api/v1/wilayah/kabupaten` | JWT | List kabupaten |
| GET | `/api/v1/wilayah/kecamatan?kabupaten_id=` | JWT | List kecamatan per kabupaten |
| GET | `/api/v1/wilayah/desa?kecamatan_id=` | JWT | List desa per kecamatan |
| GET | `/api/v1/opt` | JWT | List OPT (filter: jenis, q, aktif) |

### Web Routes

| Method | Path | Auth | Deskripsi |
|--------|------|------|-----------|
| GET | `/login` | Public | Form login |
| POST | `/login` | Public | Proses login |
| POST | `/logout` | Session | Logout |
| GET | `/dashboard` | Session | Dashboard utama |
| GET | `/password/change` | Session | Form ganti password |
| POST | `/password/change` | Session | Proses ganti password |
| GET | `/wilayah` | Admin | Master wilayah (kab/kec/desa) |
| GET | `/wilayah/kabupaten/create` | Admin | Form tambah kabupaten |
| POST | `/wilayah/kabupaten/store` | Admin | Simpan kabupaten |
| GET | `/wilayah/kabupaten/edit/{id}` | Admin | Form edit kabupaten |
| POST | `/wilayah/kabupaten/update/{id}` | Admin | Update kabupaten |
| POST | `/wilayah/kabupaten/{id}/delete` | Admin | Hapus kabupaten |
| GET | `/wilayah/kecamatan/create` | Admin | Form tambah kecamatan |
| POST | `/wilayah/kecamatan/store` | Admin | Simpan kecamatan |
| GET | `/wilayah/kecamatan/edit/{id}` | Admin | Form edit kecamatan |
| POST | `/wilayah/kecamatan/update/{id}` | Admin | Update kecamatan |
| POST | `/wilayah/kecamatan/{id}/delete` | Admin | Hapus kecamatan |
| GET | `/wilayah/desa/create` | Admin | Form tambah desa |
| POST | `/wilayah/desa/store` | Admin | Simpan desa |
| GET | `/wilayah/desa/edit/{id}` | Admin | Form edit desa |
| POST | `/wilayah/desa/update/{id}` | Admin | Update desa |
| POST | `/wilayah/desa/{id}/delete` | Admin | Hapus desa |
| GET | `/opt` | Admin | Master OPT (list, filter) |
| GET | `/opt/create` | Admin | Form tambah OPT |
| POST | `/opt/store` | Admin | Simpan OPT |
| GET | `/opt/{id}/edit` | Admin | Form edit OPT |
| POST | `/opt/update/{id}` | Admin | Update OPT |
| POST | `/opt/{id}/delete` | Admin | Hapus/nonaktifkan OPT |