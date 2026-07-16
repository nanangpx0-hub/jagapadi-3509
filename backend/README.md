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
│   ├── Core/           # Framework inti (Env, Database, Router, Controller, Request, Security, Jwt, Model, CacheManager)
│   ├── Controllers/    # Controller (Api/ dan Web/)
│   ├── Helpers/        # Fungsi pembantu (RateLimiter, PasswordValidator, SecureImageUploader, ImageCompressor, LaporanStatus, NomorLaporanGenerator, CsvWriter, XlsxWriter)
│   ├── Middleware/      # Middleware (Csrf, WebAuth, ApiAuth, Admin)
│   ├── Models/          # Model database (User, ActivityLog, MasterOpt, MasterKabupaten, MasterKecamatan, MasterDesa, LaporanHama, LaporanIrigasi)
│   ├── Services/        # Service layer (WilayahService, MasterOptService, LaporanHamaService, LaporanIrigasiService, DashboardService, ExportService)
│   └── Views/           # Template view (layouts, auth, dashboard)
├── config/             # Konfigurasi (routes.php)
├── database/
│   ├── migrations/     # File migrasi SQL (8 file)
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
| GET | `/api/v1/dashboard/stats` | JWT | Dashboard stats (query: tahun) |
| GET | `/api/v1/dashboard/charts/hama` | JWT | Chart hama bulanan |
| GET | `/api/v1/dashboard/charts/irigasi` | JWT | Chart irigasi bulanan |
| GET | `/api/v1/dashboard/map/hama` | JWT | GeoJSON titik laporan hama |
| GET | `/api/v1/dashboard/map/irigasi` | JWT | GeoJSON titik laporan irigasi |
| GET | `/api/v1/wilayah/kabupaten` | JWT | List kabupaten |
| GET | `/api/v1/wilayah/kecamatan?kabupaten_id=` | JWT | List kecamatan per kabupaten |
| GET | `/api/v1/wilayah/desa?kecamatan_id=` | JWT | List desa per kecamatan |
| GET | `/api/v1/opt` | JWT | List OPT (filter: jenis, q, aktif) |
| GET | `/api/v1/laporan-hama` | JWT | List laporan hama (filter: status, tanggal, wilayah, OPT, q, page, limit, include_draft) |
| POST | `/api/v1/laporan-hama` | JWT | Create draft/submit laporan hama |
| GET | `/api/v1/laporan-hama/{id}` | JWT | Detail laporan hama |
| PUT | `/api/v1/laporan-hama/{id}` | JWT | Update draft (owner only) |
| DELETE | `/api/v1/laporan-hama/{id}` | JWT | Delete draft (owner only) |
| POST | `/api/v1/laporan-hama/{id}/submit` | JWT | Submit draft (owner only) |
| POST | `/api/v1/laporan-hama/{id}/verifikasi` | JWT (admin) | Verifikasi laporan Submitted |
| POST | `/api/v1/laporan-hama/{id}/tolak` | JWT (admin) | Tolak laporan Submitted |
| POST | `/api/v1/laporan-hama/{id}/archive` | JWT (admin) | Arsipkan laporan Diverifikasi |
| POST | `/api/v1/laporan-hama/{id}/resubmit` | JWT (petugas) | Kirim ulang laporan Ditolak |
| GET | `/api/v1/laporan-irigasi` | JWT | List laporan irigasi (filter: status, tanggal, wilayah, kondisi_fisik, debit_air, q, page, limit, include_draft) |
| POST | `/api/v1/laporan-irigasi` | JWT | Create draft/submit laporan irigasi |
| GET | `/api/v1/laporan-irigasi/{id}` | JWT | Detail laporan irigasi |
| PUT | `/api/v1/laporan-irigasi/{id}` | JWT | Update draft (owner only) |
| DELETE | `/api/v1/laporan-irigasi/{id}` | JWT | Delete draft (owner only) |
| POST | `/api/v1/laporan-irigasi/{id}/submit` | JWT | Submit draft (owner only) |
| POST | `/api/v1/laporan-irigasi/{id}/verifikasi` | JWT (admin) | Verifikasi laporan Submitted |
| POST | `/api/v1/laporan-irigasi/{id}/tolak` | JWT (admin) | Tolak laporan Submitted |
| POST | `/api/v1/laporan-irigasi/{id}/archive` | JWT (admin) | Arsipkan laporan Diverifikasi |
| POST | `/api/v1/laporan-irigasi/{id}/resubmit` | JWT (petugas) | Kirim ulang laporan Ditolak |
| POST | `/api/v1/opt/{id}/foto` | JWT (admin) | Upload foto OPT |
| POST | `/api/v1/opt/{id}/foto/delete` | JWT (admin) | Hapus foto OPT |
| POST | `/api/v1/laporan-hama/{id}/foto` | JWT | Upload foto laporan hama (Draf/Ditolak only) |
| POST | `/api/v1/laporan-hama/{id}/foto/delete` | JWT | Hapus foto laporan hama (Draf/Ditolak only) |
| POST | `/api/v1/laporan-irigasi/{id}/foto` | JWT | Upload foto laporan irigasi (Draf/Ditolak only) |
| POST | `/api/v1/laporan-irigasi/{id}/foto/delete` | JWT | Hapus foto laporan irigasi (Draf/Ditolak only) |
| GET | `/api/v1/export/hama` | JWT | Export laporan hama (format=csv|xlsx, filter status/tanggal/wilayah) |
| GET | `/api/v1/export/irigasi` | JWT | Export laporan irigasi (format=csv|xlsx, filter status/tanggal/wilayah) |
| GET | `/api/v1/notifications` | JWT | List notifikasi (pagination + filter unread) |
| GET | `/api/v1/notifications/unread-count` | JWT | Jumlah notifikasi belum dibaca |
| POST | `/api/v1/notifications/{id}/read` | JWT | Tandai satu notifikasi telah dibaca |
| POST | `/api/v1/notifications/read-all` | JWT | Tandai semua notifikasi telah dibaca |
| DELETE | `/api/v1/notifications/{id}` | JWT | Hapus notifikasi |

### Web Routes

| Method | Path | Auth | Deskripsi |
|--------|------|------|-----------|
| GET | `/login` | Public | Form login |
| POST | `/login` | Public | Proses login |
| POST | `/logout` | Session | Logout |
| GET | `/dashboard` | Session | Dashboard utama (KPI + chart + map) |
| GET | `/dashboard/stats.json` | Session | Dashboard stats JSON |
| GET | `/dashboard/charts/hama.json` | Session | Chart hama JSON |
| GET | `/dashboard/charts/irigasi.json` | Session | Chart irigasi JSON |
| GET | `/dashboard/map/hama.json` | Session | GeoJSON hama |
| GET | `/dashboard/map/irigasi.json` | Session | GeoJSON irigasi |
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
| GET | `/laporan-hama` | Session | List laporan hama (petugas: own, admin: all) |
| GET | `/laporan-hama/create` | Session | Form buat laporan hama |
| POST | `/laporan-hama` | Session | Simpan draft/kirim laporan hama |
| GET | `/laporan-hama/{id}` | Session | Detail laporan hama |
| GET | `/laporan-hama/{id}/edit` | Session | Form edit draft |
| POST | `/laporan-hama/{id}` | Session | Update draft |
| POST | `/laporan-hama/{id}/submit` | Session | Submit draft |
| POST | `/laporan-hama/{id}/delete` | Session | Delete draft |
| POST | `/laporan-hama/{id}/verifikasi` | Session (admin) | Verifikasi laporan |
| POST | `/laporan-hama/{id}/tolak` | Session (admin) | Tolak laporan |
| POST | `/laporan-hama/{id}/archive` | Session (admin) | Arsipkan laporan |
| POST | `/laporan-hama/{id}/resubmit` | Session (petugas) | Kirim ulang laporan |
| GET | `/laporan-irigasi` | Session | List laporan irigasi (petugas: own, admin: all) |
| GET | `/laporan-irigasi/create` | Session | Form buat laporan irigasi |
| POST | `/laporan-irigasi` | Session | Simpan draft/kirim laporan irigasi |
| GET | `/laporan-irigasi/{id}` | Session | Detail laporan irigasi |
| GET | `/laporan-irigasi/{id}/edit` | Session | Form edit draft |
| POST | `/laporan-irigasi/{id}` | Session | Update draft |
| POST | `/laporan-irigasi/{id}/submit` | Session | Submit draft |
| POST | `/laporan-irigasi/{id}/delete` | Session | Delete draft |
| POST | `/laporan-irigasi/{id}/verifikasi` | Session (admin) | Verifikasi laporan |
| POST | `/laporan-irigasi/{id}/tolak` | Session (admin) | Tolak laporan |
| POST | `/laporan-irigasi/{id}/archive` | Session (admin) | Arsipkan laporan |
| POST | `/laporan-irigasi/{id}/resubmit` | Session (petugas) | Kirim ulang laporan |
| POST | `/opt/{id}/foto` | Session (admin) | Upload foto OPT |
| POST | `/opt/{id}/foto/delete` | Session (admin) | Hapus foto OPT |
| POST | `/laporan-hama/{id}/foto` | Session | Upload foto laporan hama |
| POST | `/laporan-hama/{id}/foto/delete` | Session | Hapus foto laporan hama |
| POST | `/laporan-irigasi/{id}/foto` | Session | Upload foto laporan irigasi |
| POST | `/laporan-irigasi/{id}/foto/delete` | Session | Hapus foto laporan irigasi |
| GET | `/export` | Session | Form export filter |
| POST | `/export/hama` | Session | Unduh export hama (CSRF) |
| POST | `/export/irigasi` | Session | Unduh export irigasi (CSRF) |
| GET | `/notifications` | Session | List notifikasi |
| GET | `/notifications/unread-count.json` | Session | JSON unread count (badge) |
| GET | `/notifications/recent.json` | Session | JSON 5 notifikasi terbaru |
| POST | `/notifications/{id}/read` | Session | Tandai baca notifikasi |
| POST | `/notifications/read-all` | Session | Tandai semua notifikasi dibaca |
| POST | `/notifications/{id}/delete` | Session | Hapus notifikasi |

---

## Production Checklist

### Security

| Item | Command / Action |
|------|-----------------|
| Generate JWT secret | `php -r "echo bin2hex(random_bytes(32));"` — set hasilnya ke `JWT_SECRET` |
| Disable debug | Set `APP_ENV=production` dan `APP_DEBUG=false` di `.env` production |
| HTTPS | Pastikan HTTPS aktif. Session `cookie_secure=1` otomatis jika HTTPS |
| Harden uploads | Verifikasi `public/assets/uploads/.htaccess` ada (php engine off) |
| Rate limiting | Aktif secara default — login 5 gagal/15 menit, API 1000/jam, export 20/jam, notifikasi poll 120/jam |
| CSP headers | Terpasang otomatis via `public/index.php` |

### Maintenance

| Tugas | Cara |
|-------|------|
| Prune notifikasi lama | Pasang cron job: `0 3 * * 0 cd /path/backend && php -r "require 'vendor/autoload.php'; \$_SERVER['REQUEST_URI']='/'; new \App\Services\NotificationService()->pruneOlderThan(90);"` (hapus notifikasi > 90 hari) |
| Backup database | `mysqldump -u DB_USER -p DB_NAME > backup/jagapadi-$(date +\%Y\%m\%d).sql` |
| Log rotation | Log di `storage/logs/app.log` — gunakan logrotate atau cron untuk rotasi mingguan |

### Folder Permissions

| Path | Permission | Notes |
|------|-----------|-------|
| `storage/cache/` | 0775 | Writable by web server |
| `storage/logs/` | 0775 | Writable by web server |
| `storage/tmp/` | 0775 | Writable by web server (export temp files) |
| `public/assets/uploads/` | 0775 | Writable by web server (image uploads) |
