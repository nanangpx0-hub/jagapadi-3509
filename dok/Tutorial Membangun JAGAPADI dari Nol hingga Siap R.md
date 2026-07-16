<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Tutorial Membangun JAGAPADI dari Nol hingga Siap Rilis

**Versi:** 1.0
**Target:** Web Admin (PHP 8.2) + Flutter Android + REST API
**Hosting:** cPanel / Jagoan Hosting (`jagapadi.bpsjember.my.id`)
**Prinsip:** draf masuk server, bisa dianalisis, filter dengan/tanpa draf

Tutorial ini memandu pembangunan aplikasi **secara berurutan**. Selesaikan satu tahap sampai kriteria “selesai” terpenuhi sebelum lanjut ke tahap berikutnya.[^1][^2]

***

## Peta Tahapan

| Tahap | Nama | Hasil yang diharapkan |
| --: | :-- | :-- |
| 0 | Persiapan \& keputusan | Scope, akun, data master siap |
| 1 | Repository \& standar kerja | Monorepo bersih |
| 2 | Lingkungan lokal backend | Health check hidup |
| 3 | Database \& migrasi | Skema + seed jalan |
| 4 | Core framework \& keamanan | Router, PDO, CSRF, JWT base |
| 5 | Autentikasi web \& API | Login web + JWT Flutter |
| 6 | Master wilayah \& OPT | CRUD + API read |
| 7 | Laporan hama + analisis draf | Draf server + rule engine |
| 8 | Laporan irigasi | Alur setara hama |
| 9 | Dashboard, peta, ekspor | Filter draf/non-draf |
| 10 | Aplikasi Flutter | Form lapangan + GPS + foto |
| 11 | Offline sync Flutter | Antrean sinkronisasi |
| 12 | Testing \& UAT | Siap production |
| 13 | Deploy production | HTTPS live |
| 14 | Go-live \& rilis | Tag `v1.0.0` |

Estimasi realistis tim kecil (1–2 developer): **6–10 minggu**, tergantung kesiapan data dan pengujian.

***

# TAHAP 0 — Persiapan dan Penguncian Keputusan

## 0.1 Tujuan

Semua pihak sepakat sebelum coding agar tidak bolak-balik arsitektur.

## 0.2 Yang harus disiapkan

### Akun \& akses

- Repo GitHub (contoh: `nanangpx5-netizen/jagapadi`)
- Hosting cPanel + SSH
- Domain/subdomain + SSL
- Akun email admin BPS
- Mesin development (Windows/Linux/macOS)


### Keputusan yang dikunci

| Keputusan | Nilai final |
| :-- | :-- |
| Backend | PHP 8.2 native MVC |
| DB | MariaDB / MySQL `utf8mb4` |
| Mobile | Flutter Android dulu |
| Auth web | Session + CSRF |
| Auth mobile | JWT |
| Draf | Masuk DB server + dianalisis |
| Statistik default | **Tanpa draf** |
| Filter | `include_draft=true/false` |
| Role v1 | `admin`, `petugas` |

### Data awal

Siapkan file Excel/CSV:

1. Daftar kecamatan \& desa Kabupaten Jember (kode BPS jika ada)
2. Daftar OPT (nama, jenis hama/penyakit/gulma, ETL jika ada)
3. Daftar petugas lapangan (nama, username, email)

## 0.3 Checklist selesai Tahap 0

- [ ] Blueprint disetujui
- [ ] Scope v1 disepakati (hama, irigasi, dashboard, Flutter)
- [ ] Data wilayah \& OPT tersedia
- [ ] Hosting, domain, GitHub siap
- [ ] 1 admin teknis ditunjuk

***

# TAHAP 1 — Repository dan Standar Kerja

## 1.1 Buat monorepo

```bash
mkdir jagapadi && cd jagapadi
git init
mkdir -p backend mobile docs scripts .github/workflows
```

Struktur target:

```text
jagapadi/
├── backend/          # PHP API + Web
├── mobile/           # Flutter
├── docs/
├── scripts/
├── README.md
├── CHANGELOG.md
└── .gitignore
```


## 1.2 `.gitignore` penting

```gitignore
# env & secrets
.env
**/.env
*.pem
*.key

# backend
backend/vendor/
backend/storage/cache/*
backend/storage/logs/*
backend/public/uploads/*
!backend/public/uploads/.gitkeep
!backend/storage/cache/.gitkeep
!backend/storage/logs/.gitkeep

# flutter
mobile/**/.dart_tool/
mobile/**/build/
mobile/**/.flutter-plugins-dependencies
mobile/**/*.iml

# OS/IDE
.DS_Store
.idea/
.vscode/
```


## 1.3 Branch strategy

```text
main            # production-ready
develop         # integrasi harian (opsional)
feature/*       # fitur baru
fix/*           # perbaikan bug
hotfix/*        # perbaikan production
```

Contoh:

```bash
git checkout -b feature/backend-skeleton
```


## 1.4 Dokumen awal di `docs/`

- `BLUEPRINT.md`
- `API.md` (diisi bertahap)
- `DATABASE.md`
- `DEPLOYMENT.md`
- `TUTORIAL_BUILD.md` (file ini)


## 1.5 Checklist selesai Tahap 1

- [ ] Struktur folder monorepo ada
- [ ] `.gitignore` memblokir secret \& upload
- [ ] README menjelaskan cara clone
- [ ] Branch protection `main` diaktifkan (jika tim)

***

# TAHAP 2 — Lingkungan Lokal Backend

## 2.1 Prasyarat software

| Software | Versi minimal |
| :-- | :-- |
| PHP | 8.2 |
| Composer | 2.x |
| MySQL/MariaDB | 8.0 / 10.6 |
| Apache/Nginx atau Laragon/XAMPP | - |
| Git | terbaru |
| Node.js (opsional UI build) | 18+ |
| Flutter SDK | stable terbaru |
| Android Studio + emulator/device | - |

Ekstensi PHP wajib:

```bash
php -m | grep -E "pdo|pdo_mysql|mbstring|json|fileinfo|gd|openssl"
```

Harus muncul: `pdo`, `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `gd`, `openssl`.[^2]

## 2.2 Skeleton backend

```bash
cd backend
composer init --name=bpsjember/jagapadi --no-interaction
```

Struktur:

```text
backend/
├── app/
│   ├── Controllers/
│   │   ├── Api/
│   │   └── Web/
│   ├── Core/
│   ├── Helpers/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Views/
├── config/
├── database/migrations/
├── public/
│   ├── assets/
│   ├── uploads/
│   ├── .htaccess
│   └── index.php
├── storage/
│   ├── cache/
│   └── logs/
├── tests/
├── .env.example
├── composer.json
└── phpunit.xml
```


## 2.3 Autoload Composer

`composer.json`:

```json
{
  "name": "bpsjember/jagapadi",
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  },
  "require": {
    "php": ">=8.2"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0"
  }
}
```

```bash
composer dump-autoload
```


## 2.4 Entry point `public/index.php`

Tugas minimal:

1. Load autoload
2. Load `.env`
3. Set timezone `Asia/Jakarta`
4. Set security headers
5. Start session (web)
6. Panggil Router

## 2.5 `.htaccess` (Apache)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

Document root lokal harus mengarah ke `backend/public`, **bukan** root `backend/`.[^2]

## 2.6 `.env.example`

```dotenv
APP_NAME=JAGAPADI
APP_ENV=local
APP_DEBUG=true
APP_BASE_URL=http://localhost:8080
APP_TIMEZONE=Asia/Jakarta

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jagapadi_local
DB_USER=root
DB_PASS=

JWT_SECRET=ganti_minimal_64_karakter_acak
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=2592000

UPLOAD_MAX_SIZE_LAPORAN=10485760
UPLOAD_MAX_SIZE_OPT=5242880
CACHE_DASHBOARD_TTL=300
DEFAULT_INCLUDE_DRAFT=false
RULES_VERSION_HAMA=hama-1.0
RULES_VERSION_IRIGASI=irigasi-1.0
```

```bash
cp .env.example .env
```


## 2.7 Health endpoint dulu

Buat route:

```text
GET /api/v1/health
```

Respons:

```json
{
  "success": true,
  "message": "JAGAPADI OK",
  "data": {
    "app": "JAGAPADI",
    "time": "2026-07-16 12:00:00",
    "db": "connected"
  }
}
```


## 2.8 Cara jalankan lokal cepat

```bash
cd backend/public
php -S localhost:8080
```

Uji:

```bash
curl http://localhost:8080/api/v1/health
```


## 2.9 Checklist selesai Tahap 2

- [ ] `composer install` sukses
- [ ] Document root = `public/`
- [ ] `.env` terbaca
- [ ] `/api/v1/health` mengembalikan 200
- [ ] Error log tertulis di `storage/logs`

***

# TAHAP 3 — Database dan Migrasi

## 3.1 Buat database lokal

```sql
CREATE DATABASE jagapadi_local
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```


## 3.2 Buat runner migrasi

File: `backend/scripts/migrate.php`
Fungsi:

1. Baca file SQL di `database/migrations/` secara urut
2. Catat yang sudah dijalankan di `schema_migrations` atau `storage/migrations_ran.txt`
3. Jangan jalankan ulang file yang sama

## 3.3 Urutan migrasi

```text
001_create_users.sql
002_create_wilayah.sql
003_create_master_opt.sql
004_create_laporan_hama.sql
005_create_laporan_irigasi.sql
006_create_analysis_logs_counter.sql
007_seed_admin_jember_opt.sql
```


### Poin desain penting

- Status laporan: `Draf, Submitted, Diverifikasi, Ditolak, Diarsipkan`
- `nomor_laporan` **NULL pada draf**, diisi saat submit
- `client_local_id` untuk sinkron Flutter
- `analysis_results` menyimpan hasil rule engine
- Index pada `status`, `tanggal`, `kecamatan_id`, `(status,tanggal)`

[^2]

## 3.4 Seed admin pertama

Generate hash:

```bash
php -r "echo password_hash('AdminJember!1', PASSWORD_BCRYPT, ['cost'=>12]), PHP_EOL;"
```

Insert user admin + 1 petugas uji + master wilayah Jember + beberapa OPT contoh.

## 3.5 Verifikasi skema

```sql
SHOW TABLES;
DESCRIBE laporan_hama;
DESCRIBE analysis_results;
SELECT id, username, role FROM users;
```


## 3.6 Checklist selesai Tahap 3

- [ ] Semua migrasi jalan tanpa error
- [ ] FK dan index terbentuk
- [ ] Admin bisa di-query
- [ ] Migrasi kedua kali tidak menduplikasi

***

# TAHAP 4 — Core Framework dan Keamanan

## 4.1 Komponen wajib di `app/Core`

| Class | Fungsi |
| :-- | :-- |
| `Router` | Match method + path, middleware chain |
| `Controller` | `view()`, `json()`, `redirect()` |
| `Model` | CRUD ringan + fillable guard |
| `QueryBuilder` | Query parameterized |
| `Database` | PDO singleton |
| `Security` | CSRF, sanitize, brute-force |
| `CacheManager` | File cache |
| `Env` | Loader `.env` |

Semua query **wajib** lewat prepared statements.[^2]

## 4.2 Middleware chain

```text
Request
 → RateLimitMiddleware
 → AuthMiddleware (session/JWT)
 → RoleMiddleware
 → CsrfMiddleware (web mutasi)
 → Controller
```


## 4.3 Envelope JSON standar

Sukses:

```json
{
  "success": true,
  "message": "OK",
  "data": {},
  "meta": {}
}
```

Error:

```json
{
  "success": false,
  "error": "ValidationError",
  "message": "Data tidak valid",
  "errors": {}
}
```


## 4.4 Security headers

Setiap response:

```text
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; img-src 'self' data: https:;
```


## 4.5 Upload service (siapkan dulu meski form belum lengkap)

Validasi 3 lapis:

1. Magic bytes JPEG/PNG/WebP
2. MIME `finfo`
3. Ekstensi whitelist

Rename acak: `bin2hex(random_bytes(16))`.
Simpan ke `public/uploads/.../YYYYMM/`.
Blokir eksekusi PHP di folder upload.[^2]

## 4.6 Checklist selesai Tahap 4

- [ ] Router web + API jalan
- [ ] QueryBuilder aman (tidak ada SQL string mentah)
- [ ] CSRF token generate \& validate
- [ ] Rate limit login bisa diuji
- [ ] Upload invalid ditolak 422

***

# TAHAP 5 — Autentikasi Web dan API

## 5.1 Model User

Fungsi:

- `findByUsername`
- `verifyPassword`
- `validatePasswordPolicy`
- `createUser`
- `updatePassword`

Password policy:

- min 8
- 1 huruf besar
- 1 huruf kecil
- 1 angka
- 1 karakter khusus

[^2]

## 5.2 Auth Web

Route:

```text
GET  /login
POST /login
POST /logout
GET  /change-password
POST /change-password
```

Alur login:

1. Validasi CSRF
2. Cek brute-force IP
3. Authenticate
4. `session_regenerate_id(true)`
5. Jika `must_change_password=1` → ganti password
6. Redirect dashboard

## 5.3 Auth API (Flutter)

```text
POST /api/v1/auth/login
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

Login body:

```json
{
  "username": "petugas1",
  "password": "Password!1"
}
```

Respons:

```json
{
  "success": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "expires_in": 3600,
    "role": "petugas",
    "user": {
      "id": 2,
      "nama_lengkap": "Petugas Satu"
    }
  }
}
```

JWT payload minimal: `sub`, `role`, `exp`, `iat`.

## 5.4 Manajemen user (admin)

```text
GET    /users
POST   /users
GET    /users/{id}
PUT    /users/{id}
POST   /users/{id}/toggle-aktif
POST   /users/{id}/reset-password
```


## 5.5 Uji manual

```bash
# API login
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"AdminJember!1"}'
```


## 5.6 Checklist selesai Tahap 5

- [ ] Login web sukses
- [ ] Login API mengembalikan JWT
- [ ] Token invalid → 401
- [ ] Role petugas tidak bisa kelola user
- [ ] Password lemah ditolak
- [ ] Brute-force login memblokir sementara

***

# TAHAP 6 — Master Wilayah dan Master OPT

## 6.1 Wilayah (admin web + API read)

Web:

- CRUD kabupaten/kecamatan/desa
- Validasi tidak hapus jika dipakai laporan
- Audit log setiap perubahan

API:

```text
GET /api/v1/wilayah/kabupaten
GET /api/v1/wilayah/kecamatan?kabupaten_id=1
GET /api/v1/wilayah/desa?kecamatan_id=5
```


## 6.2 Master OPT

Web admin:

- CRUD OPT
- Upload foto OPT (max 5MB)
- Aktif/nonaktif
- Field ETL acuan

API:

```text
GET /api/v1/opt
GET /api/v1/opt/{id}
GET /api/v1/opt?jenis=hama&search=wereng
```


## 6.3 Seed data Jember

Isi minimal:

- 1 kabupaten (Jember)
- beberapa kecamatan prioritas
- desa di bawahnya
- 10–20 OPT umum padi


## 6.4 Checklist selesai Tahap 6

- [ ] Dropdown cascading web jalan
- [ ] API wilayah/OPT terproteksi auth
- [ ] Hapus wilayah terpakai ditolak
- [ ] Audit log terisi

***

# TAHAP 7 — Laporan Hama + Analisis Draf (Modul Inti)

Ini tahap paling penting.

## 7.1 Aturan bisnis draf

| Aksi | Validasi | Nomor laporan | Analisis | Statistik resmi |
| :-- | :-- | :-- | :-- | :-- |
| Simpan draf | Longgar | Belum dibuat | Ya jika data minimum ada | Tidak (default) |
| Update draf | Longgar | Tetap kosong | Dihitung ulang | Tidak |
| Submit | Ketat semua wajib | Digenerate atomik | Dihitung ulang | Ya (Submitted) |
| Verifikasi | Admin only | Tetap | Snapshot status | Ya |
| Tolak | Alasan ≥ 10 char | Tetap | - | Tidak |

[^2]

## 7.2 Endpoint hama

```text
GET    /api/v1/laporan-hama
POST   /api/v1/laporan-hama
GET    /api/v1/laporan-hama/{id}
PUT    /api/v1/laporan-hama/{id}
POST   /api/v1/laporan-hama/{id}/submit
POST   /api/v1/laporan-hama/{id}/foto
POST   /api/v1/laporan-hama/{id}/verifikasi   # admin
POST   /api/v1/laporan-hama/{id}/tolak        # admin
POST   /api/v1/laporan-hama/{id}/archive      # admin
```

Query list:

```text
?include_draft=false
&statuses=Draf,Submitted
&kecamatan_id=5
&tanggal_dari=2026-07-01
&tanggal_sampai=2026-07-16
&page=1&limit=20
```


## 7.3 Service layer yang dibuat

1. `LaporanHamaService`
2. `LaporanHamaValidator` (mode `draft` vs `submit`)
3. `NomorLaporanGenerator` (prefix `LH`)
4. `AnalysisService` / `HamaRuleEngine`
5. `LaporanPhotoUploader`

## 7.4 Generate nomor atomik saat submit

Dalam transaksi:

```sql
INSERT INTO nomor_laporan_counter (prefix, tanggal, counter)
VALUES ('LH', CURDATE(), 1)
ON DUPLICATE KEY UPDATE counter = counter + 1;

SELECT counter FROM nomor_laporan_counter
WHERE prefix = 'LH' AND tanggal = CURDATE();
```

Format: `LH-20260716-0001`.[^2]

## 7.5 Rule engine hama v1

**Data minimum untuk dianalisis:**

- tanggal
- kecamatan
- OPT
- keparahan **atau** populasi **atau** luas

Jika kurang → `analysis_status = MenungguData`.

Contoh aturan:

- Ringan + populasi < ETL → `Rendah`
- Sedang / mendekati ETL → `Sedang`
- Berat / > ETL → `Tinggi`
- Clustering wilayah 7 hari → `Kritis`

Simpan ke `analysis_results` dengan:

- `report_status_at_analysis`
- `rules_version`
- `is_current = 1`
- `is_official` dihitung dari status laporan


## 7.6 Web admin

Halaman:

- daftar laporan + filter status/draf
- detail + foto + peta mini
- tombol verifikasi/tolak
- badge analisis


## 7.7 Uji skenario wajib

1. Simpan draf sebagian field → status Draf, analisis MenungguData
2. Lengkapi draf → analisis Siap
3. Submit → nomor muncul, status Submitted
4. Admin verifikasi → Diverifikasi
5. Petugas A tidak bisa lihat draf petugas B

## 7.8 Checklist selesai Tahap 7

- [ ] Draf tersimpan di DB server
- [ ] Analisis jalan untuk draf lengkap
- [ ] Nomor hanya saat submit
- [ ] Verifikasi/tolak admin only
- [ ] Filter `include_draft` bekerja di list

***

# TAHAP 8 — Laporan Irigasi

Ulangi pola Tahap 7 dengan perbedaan:


| Aspek | Irigasi |
| :-- | :-- |
| Prefix nomor | `LI` |
| Field unik | `nama_saluran`, `kondisi_fisik`, `debit_air` |
| Tidak ada | `master_opt_id`, `tingkat_keparahan` |
| Rule engine | berdasarkan kondisi fisik + debit |

Endpoint setara: `/api/v1/laporan-irigasi/...`

## Checklist selesai Tahap 8

- [ ] CRUD draf/submit irigasi jalan
- [ ] Analisis irigasi tersimpan
- [ ] Verifikasi admin jalan
- [ ] Foto irigasi terpisah folder

***

# TAHAP 9 — Dashboard, Peta, dan Ekspor

## 9.1 Endpoint

```text
GET /api/v1/dashboard/stats?include_draft=false
GET /api/v1/dashboard/charts?include_draft=false
GET /api/v1/dashboard/map?include_draft=false
GET /api/v1/export/laporan-hama?include_draft=false
GET /api/v1/export/laporan-irigasi?include_draft=false
```


## 9.2 Kartu statistik

- Total laporan
- Draf
- Menunggu verifikasi (`Submitted`)
- Diverifikasi
- Ditolak
- Risiko Tinggi/Kritis (resmi)
- Indikasi risiko dari draf (hanya jika `include_draft=true`)


## 9.3 UX filter

Di web:

- Toggle **Data resmi** / **Termasuk draf**
- Jika termasuk draf, tampilkan banner:

> Tampilan mencakup data draf yang belum dikirim/diverifikasi. Jangan dipakai sebagai laporan resmi.

## 9.4 Peta Leaflet

- Layer hama \& irigasi
- Marker cluster
- Warna risiko
- Marker draf: opacity lebih rendah / ikon berbeda
- Popup: status, risiko, rekomendasi


## 9.5 Cache dashboard

TTL 5 menit. Invalidate cache saat ada submit/verifikasi.[^2]

## 9.6 Ekspor

Kolom minimal:

- nomor_laporan
- status_laporan
- status_analisis
- risk_level
- is_official (`ya/tidak`)
- wilayah, tanggal, petugas, catatan


## 9.7 Checklist selesai Tahap 9

- [ ] Default dashboard tanpa draf
- [ ] Toggle termasuk draf mengubah angka
- [ ] Peta membedakan draf vs resmi
- [ ] Ekspor CSV/XLSX berhasil
- [ ] Cache tidak menampilkan data basi terlalu lama

***

# TAHAP 10 — Aplikasi Flutter (Online Dulu)

## 10.1 Buat proyek

```bash
cd mobile
flutter create jagapadi_mobile
cd jagapadi_mobile
```

Dependensi awal (`pubspec.yaml`):

- `dio`
- `flutter_riverpod`
- `flutter_secure_storage`
- `go_router`
- `geolocator`
- `permission_handler`
- `image_picker`
- `connectivity_plus`
- `intl`
- `json_annotation` + `json_serializable`
- `drift` + `sqlite3_flutter_libs` (untuk offline tahap 11)


## 10.2 Struktur feature-first

```text
lib/
├── core/
│   ├── config/app_config.dart
│   ├── network/dio_client.dart
│   ├── storage/token_storage.dart
│   └── theme/
├── data/
├── domain/
├── features/
│   ├── auth/
│   ├── home/
│   ├── laporan_hama/
│   ├── laporan_irigasi/
│   ├── master_data/
│   ├── sync/
│   └── profile/
└── main.dart
```


## 10.3 Config environment

Jalankan dengan:

```bash
flutter run \
  --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1
```

Catatan:

- Emulator Android → host machine = `10.0.2.2`
- Device fisik → pakai IP LAN laptop


## 10.4 Fitur Flutter tahap ini (online)

1. Login + simpan token aman
2. Auto attach Bearer token
3. Refresh token saat 401
4. Sinkron master wilayah \& OPT
5. Form laporan hama
6. Form laporan irigasi
7. Ambil GPS
8. Ambil foto kamera/galeri + kompres
9. Simpan draf ke server
10. Submit ke server
11. Lihat riwayat \& detail analisis
12. Toggle “sertakan draf saya” di list

## 10.5 Permission Android

Di `AndroidManifest.xml`:

- `INTERNET`
- `ACCESS_FINE_LOCATION`
- `ACCESS_COARSE_LOCATION`
- `CAMERA` (jika perlu)
- permission storage sesuai target SDK


## 10.6 Checklist selesai Tahap 10

- [ ] Login Flutter sukses ke API lokal
- [ ] Master data termuat
- [ ] Draf online masuk DB
- [ ] GPS terisi
- [ ] Foto terunggah
- [ ] Hasil analisis tampil di detail

***

# TAHAP 11 — Offline Sync Flutter

## 11.1 Tujuan

Petugas tetap bisa membuat draf saat sinyal buruk.

## 11.2 Tabel lokal (Drift/SQLite)

- `local_laporan_hama`
- `local_laporan_irigasi`
- `sync_queue`
- `master_*_cache`

Kolom penting lokal:

- `local_id` (UUID)
- `server_id` (nullable)
- `sync_state` (`local_only|syncing|synced_draft|submitted|failed|conflict`)
- `payload_json`
- `updated_at`


## 11.3 Alur

```text
User simpan draf
  → simpan SQLite dulu
  → jika online: push ke server
  → jika offline: masuk antrean

Koneksi kembali
  → worker proses antrean
  → create/update draf
  → upload foto
  → submit jika diminta
  → update server_id + analysis
```


## 11.4 Endpoint bantu sync

```text
POST /api/v1/sync/push
GET  /api/v1/sync/pull?since=ISO8601
```

Gunakan `client_local_id` agar tidak dobel.

## 11.5 Konflik sederhana v1

Jika server lebih baru dan lokal belum di-submit:

- tampilkan peringatan
- opsi: pakai server / kirim ulang lokal sebagai revisi


## 11.6 Checklist selesai Tahap 11

- [ ] Draf offline tidak hilang saat app ditutup
- [ ] Saat online, draf terunggah otomatis
- [ ] Tidak membuat duplikat laporan
- [ ] Status sync terlihat di UI

***

# TAHAP 12 — Testing dan UAT

## 12.1 Backend tests (PHPUnit)

Prioritas unit test:

1. Password policy
2. Nomor laporan generator
3. Validator draf vs submit
4. Rule engine hama/irigasi
5. JWT encode/decode
6. Upload validation
```bash
cd backend
./vendor/bin/phpunit
```

Target cakupan kode inti: memadai untuk service kritis (ideal ≥ 70%).[^1]

## 12.2 API integration test

Gunakan Postman/Insomnia collection:

- auth flow
- create draf
- update draf
- submit
- verifikasi
- filter include_draft
- unauthorized cases


## 12.3 Flutter test

- unit repository/mapper
- widget login/form
- integration: login → buat draf → lihat list


## 12.4 Security checklist

- [ ] SQL injection attempt gagal
- [ ] XSS output ter-escape
- [ ] Upload `.php` ditolak
- [ ] Folder `.env` tidak accessible
- [ ] Petugas tidak bisa verifikasi
- [ ] CSRF web aktif
- [ ] Rate limit login aktif


## 12.5 UAT checklist bisnis

### Admin

- [ ] Login web
- [ ] Kelola user/wilayah/OPT
- [ ] Lihat draf petugas
- [ ] Filter dashboard tanpa draf
- [ ] Filter termasuk draf
- [ ] Verifikasi/tolak
- [ ] Ekspor


### Petugas

- [ ] Login Flutter
- [ ] Buat draf online
- [ ] Buat draf offline + sync
- [ ] Submit
- [ ] Lihat analisis
- [ ] Perbaiki laporan ditolak


## 12.6 Checklist selesai Tahap 12

- [ ] Semua alur kritis lulus
- [ ] Bug blocker = 0
- [ ] Berita acara UAT ditandatangani internal

***

# TAHAP 13 — Deploy Production (cPanel)

## 13.1 Siapkan server

1. Subdomain `jagapadi.bpsjember.my.id`
2. SSL aktif
3. PHP 8.2 + ekstensi wajib
4. Buat database \& user MariaDB
5. SSH key deploy read-only ke GitHub

## 13.2 Layout direktori disarankan

```text
/home/bpsjembe/
├── repositories/jagapadi/
├── apps/jagapadi/            # rilis aktif
│   ├── app/
│   ├── config/
│   ├── public/               # document root
│   ├── storage/
│   └── .env
├── backups/jagapadi/
└── deploy_jagapadi.sh
```


## 13.3 Document root

Arahkan subdomain ke:

```text
/home/bpsjembe/apps/jagapadi/public
```

Jangan expose `app/`, `config/`, `storage/`, `.env`.[^2]

## 13.4 `.env` production

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://jagapadi.bpsjember.my.id
DB_HOST=localhost
DB_NAME=bpsjembe_jagapadi
DB_USER=bpsjembe_jagapadi
DB_PASS=...
JWT_SECRET=...
```

Permission:

```bash
chmod 600 .env
chmod -R 775 storage public/uploads
```


## 13.5 Langkah deploy pertama

1. Clone repo ke `repositories/jagapadi`
2. Copy backend ke `apps/jagapadi`
3. `composer install --no-dev --optimize-autoloader`
4. Buat `.env` production
5. Jalankan migrasi + seed
6. Set document root
7. Uji `https://jagapadi.bpsjember.my.id/api/v1/health`
8. Login admin web

## 13.6 Script deploy berikutnya

Alur aman:

1. Backup DB + uploads
2. `git pull`
3. rsync kode (exclude `.env`, `storage`, `uploads`)
4. composer install
5. migrasi baru
6. clear cache
7. smoke test

## 13.7 Backup otomatis

Cron harian:

```bash
mysqldump ... | gzip > backups/jagapadi/db_$(date +%F).sql.gz
```

Retensi 30 hari. Backup uploads mingguan.[^1][^2]

## 13.8 Checklist selesai Tahap 13

- [ ] HTTPS hidup
- [ ] Health 200
- [ ] Login admin sukses
- [ ] Upload foto sukses
- [ ] Backup cron terpasang
- [ ] Restore backup pernah diuji

***

# TAHAP 14 — Build Rilis Flutter dan Go-Live

## 14.1 Build release Android

1. Buat keystore signing (simpan aman, jangan di Git)
2. Konfigurasi `key.properties`
3. Build:
```bash
flutter build appbundle \
  --dart-define=API_BASE_URL=https://jagapadi.bpsjember.my.id/api/v1
```

Atau APK internal:

```bash
flutter build apk --release \
  --dart-define=API_BASE_URL=https://jagapadi.bpsjember.my.id/api/v1
```


## 14.2 Uji di perangkat nyata

- Login production
- GPS outdoor
- Foto kamera
- Jaringan seluler 4G
- Mode pesawat → draf offline → sync ulang


## 14.3 Pelatihan

- Admin: verifikasi, filter draf, ekspor
- Petugas: buat draf, submit, offline
- Buat SOP 1–2 halaman PDF


## 14.4 Soft launch

1. 3–5 petugas uji 3–7 hari
2. Kumpulkan bug
3. Hotfix jika perlu
4. Baru perluas ke seluruh petugas

## 14.5 Rilis formal

```bash
# update CHANGELOG.md
git checkout main
git pull
git tag -a v1.0.0 -m "Rilis JAGAPADI v1.0.0"
git push origin v1.0.0
```

Isi `CHANGELOG.md`:

- fitur utama
- batasan v1
- catatan migrasi

[^1]

## 14.6 Definisi “siap rilis”

Aplikasi dinyatakan siap rilis jika:

1. Draf masuk server dan bisa dianalisis
2. Filter dengan/tanpa draf benar
3. Submit–verifikasi–tolak stabil
4. Flutter online + offline jalan
5. Dashboard/peta/ekspor akurat
6. Keamanan dasar lulus
7. Backup/restore teruji
8. UAT disetujui
9. APK/AAB production tersedia
10. Tim operasional tahu cara pakai

***

# Panduan Harian Saat Mengembangkan

## Workflow fitur baru

1. Buat branch `feature/nama`
2. Buat/ubah migration jika perlu
3. Model → Service → Controller → Route → View/API
4. Uji manual
5. Tambah test
6. Commit jelas
7. PR ke `main`/`develop`
8. Review → merge → deploy

## Standar commit

```text
feat: tambah submit laporan hama
fix: perbaiki filter include_draft dashboard
docs: update API laporan irigasi
chore: tambah script backup
```


## Aturan kualitas

- Jangan hardcode password/secret
- Jangan commit `uploads` atau `.env`
- Semua input divalidasi server-side
- Semua output HTML di-escape
- Semua endpoint mutasi terproteksi auth/role

***

# Urutan Coding Praktis (Checklist Master)

Gunakan ini sebagai papan progress:

### Backend

- [ ] Skeleton + health
- [ ] DB migrasi + seed
- [ ] Auth web
- [ ] Auth JWT
- [ ] User management
- [ ] Wilayah
- [ ] OPT
- [ ] Laporan hama draf/submit
- [ ] Analisis hama
- [ ] Verifikasi hama
- [ ] Laporan irigasi full
- [ ] Dashboard + filter draf
- [ ] Peta
- [ ] Ekspor
- [ ] Upload aman
- [ ] Activity log
- [ ] Backup script


### Flutter

- [ ] Login/token
- [ ] Master sync
- [ ] Form hama
- [ ] Form irigasi
- [ ] GPS/foto
- [ ] Riwayat/detail
- [ ] Offline queue
- [ ] Build release


### Production

- [ ] Deploy HTTPS
- [ ] Migrasi production
- [ ] UAT
- [ ] Pelatihan
- [ ] Tag v1.0.0

***

# Troubleshooting Cepat

| Gejala | Cek |
| :-- | :-- |
| 404 semua route | Document root belum ke `public/`, rewrite belum aktif |
| 500 blank | `APP_DEBUG=true` lokal, cek `storage/logs` |
| DB connection failed | host/user/pass `.env`, remote DB privilege |
| JWT invalid | `JWT_SECRET` beda antar environment |
| CORS Flutter gagal | header CORS di API, HTTPS mixed content |
| GPS null | permission ditolak, uji outdoor |
| Draf dobel | `client_local_id` tidak dikirim/tidak unik |
| Statistik “aneh” | cek `include_draft` default false |
| Upload gagal | permission folder, size limit PHP `upload_max_filesize` |

[^1]

***

# Hasil Akhir yang Akan Anda Miliki

Setelah semua tahap selesai, Anda memiliki:

1. **Backend PHP** aman di `https://jagapadi.bpsjember.my.id`
2. **Web admin** untuk verifikasi, master data, dashboard, ekspor
3. **Flutter Android** untuk petugas lapangan
4. **Draf server-side** yang tetap bisa dianalisis
5. **Filter data resmi vs operasional**
6. **Rule engine rekomendasi**
7. **Offline sync**
8. **Backup, deploy script, dan tag rilis v1.0.0**

***

## Cara memakai tutorial ini

- Kerjakan **satu tahap per branch/PR**.
- Jangan loncat ke Flutter sebelum API auth + master + laporan hama stabil.
- Setiap akhir tahap, centang kriteria selesai.
- Simpan dokumen ini di `docs/TUTORIAL_BUILD.md` agar tim lain bisa melanjutkan.

Jika Anda ingin langkah berikutnya lebih operasional, saya bisa buatkan **Tahap 2–3 dalam bentuk file skeleton siap tempel** (struktur folder + `index.php` + router + migrasi SQL awal) agar langsung mulai coding hari ini.

<div align="center">⁂</div>

[^1]: Dokumentasi-aplikasi-jagapadi-3509.md

[^2]: jagapadi-new.md

