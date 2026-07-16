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

### Web Routes

| Method | Path | Auth | Deskripsi |
|--------|------|------|-----------|
| GET | `/login` | Public | Form login |
| POST | `/login` | Public | Proses login |
| POST | `/logout` | Session | Logout |
| GET | `/dashboard` | Session | Dashboard utama |
| GET | `/password/change` | Session | Form ganti password |
| POST | `/password/change` | Session | Proses ganti password |