# Panduan Upload GitHub & Deploy Hosting — JAGAPADI

**Domain:** https://jagapadi.bpsjember.my.id  
**Repository:** https://github.com/nanangpx0-hub/jagapadi-3509  
**Server:** Jagoan Hosting — user `bpsjembe`  
**Database hosting:** `bpsjembe_jagapadi_3509`  
**Database lokal:** `jagapadi_local`

---

## Daftar Isi

1. [Prasyarat](#1-prasyarat)
2. [Upload Kode ke GitHub](#2-upload-kode-ke-github)
3. [Deploy ke Hosting — Pertama Kali](#3-deploy-ke-hosting--pertama-kali)
4. [Deploy Update Berikutnya](#4-deploy-update-berikutnya)
5. [Import Database](#5-import-database)
6. [Pengujian Pasca-Deploy](#6-pengujian-pasca-deploy)
7. [Pemeliharaan Rutin](#7-pemeliharaan-rutin)
8. [Rollback Jika Terjadi Masalah](#8-rollback-jika-terjadi-masalah)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Prasyarat

### Di Komputer Lokal (Windows + Laragon)

- Git sudah terinstall dan dikonfigurasi
- PHP 8.2 tersedia via Laragon
- Database lokal `jagapadi_local` sudah berjalan di MySQL Laragon
- Personal Access Token (PAT) GitHub sudah disiapkan

> **Cara buat PAT GitHub:**  
> GitHub → foto profil → Settings → Developer settings → Personal access tokens → Tokens (classic) → Generate new token → centang scope `repo` → Generate token → **salin dan simpan tokennya**

### Di Server Hosting

- SSH aktif (Terminal di cPanel atau SSH client)
- Repository sudah di-clone di `/home/bpsjembe/repositories/jagapadi`
- Script deploy ada di `/home/bpsjembe/deploy_jagapadi.sh`
- PHP 8.2 aktif di cPanel MultiPHP Manager
- Ekstensi PHP aktif: `pdo_mysql`, `fileinfo`, `mbstring`, `gd`, `curl`, `xml`

---

## 2. Upload Kode ke GitHub

Lakukan langkah ini **setiap kali ada perubahan kode** yang ingin dikirim ke GitHub.

### Langkah 2.1 — Audit file sebelum commit

Buka PowerShell di folder proyek:

```powershell
cd C:\laragon\www\jagapadi-3509
git status
```

Pastikan **tidak ada** file berikut di output:

```
.env
.env.local
config/config.php
*.sql
cookies.txt
```

Jika ada, keluarkan dari staging:

```powershell
git restore --staged .env
git restore --staged config/config.php
```

### Langkah 2.2 — Stage file yang berubah

```powershell
# Tambahkan file secara spesifik — hindari git add .
git add app/controllers/NamaController.php
git add app/views/modul/namaview.php
git add config/web_routes.php

# Cek apa yang akan di-commit
git diff --cached --stat
```

### Langkah 2.3 — Commit dengan pesan yang jelas

Format pesan commit yang digunakan di proyek ini:

| Prefix | Digunakan Untuk |
|--------|----------------|
| `feat:` | Fitur baru |
| `fix:` | Perbaikan bug |
| `docs:` | Perubahan dokumentasi |
| `refactor:` | Refactoring tanpa ubah fungsi |
| `test:` | Tambah atau perbaiki test |
| `chore:` | Konfigurasi, dependency, tooling |

```powershell
git commit -m "feat: tambah fitur rekap feedback untuk admin"
```

### Langkah 2.4 — Push ke GitHub

```powershell
git push origin main
```

Jika muncul prompt autentikasi:
- **Username:** `nanangpx0-hub`
- **Password:** isi dengan Personal Access Token (bukan password login GitHub)

### Langkah 2.5 — Verifikasi di GitHub

Buka https://github.com/nanangpx0-hub/jagapadi-3509 dan pastikan:

- [ ] Commit terbaru muncul dengan pesan yang benar
- [ ] File `.env` tidak ada di daftar file
- [ ] File `config/config.php` tidak ada di daftar file

---

## 3. Deploy ke Hosting — Pertama Kali

Lakukan bagian ini **hanya saat deploy pertama kali** ke server yang belum pernah di-deploy.

### Langkah 3.1 — Login ke server via SSH

```bash
ssh bpsjembe@jagapadi.bpsjember.my.id
```

Atau gunakan **Terminal** di cPanel Jagoan Hosting.

### Langkah 3.2 — Verifikasi repository di server

```bash
cd /home/bpsjembe/repositories/jagapadi
git log --oneline -3
git remote -v
```

Remote harus menampilkan:
```
origin  https://github.com/nanangpx0-hub/jagapadi-3509.git (fetch)
origin  https://github.com/nanangpx0-hub/jagapadi-3509.git (push)
```

Jika remote salah, perbaiki:

```bash
git remote set-url origin https://github.com/nanangpx0-hub/jagapadi-3509.git
git fetch origin
git reset --hard origin/main
```

### Langkah 3.3 — Generate JWT Secret

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Salin outputnya — akan dipakai di file `.env`.

### Langkah 3.4 — Buat file `.env` di server

```bash
cat > /home/bpsjembe/jagapadi.bpsjember.my.id/.env << 'ENVEOF'
APP_NAME=JAGAPADI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://jagapadi.bpsjember.my.id

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bpsjembe_jagapadi_3509
DB_USER=bpsjembe_nanangpx
DB_PASS=ISI_PASSWORD_DATABASE_ANDA
DB_CHARSET=utf8mb4

CACHE_ENABLED=true
CACHE_DRIVER=file
CACHE_PREFIX=jagapadi
CACHE_DEFAULT_TTL=60

JWT_SECRET=ISI_HASIL_GENERATE_LANGKAH_3_3
JWT_EXPIRY=3600

CORS_ALLOWED_ORIGINS=https://jagapadi.bpsjember.my.id,https://bpsjember.my.id

ADMIN_EMAIL=admin@bpsjember.my.id
SMTP_FROM=no-reply@bpsjember.my.id
SMTP_FROM_NAME=JAGAPADI System

SESSION_LIFETIME=28800
AUTO_APPROVE_ENABLED=false
ENVEOF
```

Set permission:

```bash
chmod 600 /home/bpsjembe/jagapadi.bpsjember.my.id/.env
```

### Langkah 3.5 — Buat file `config/config.php` di server

```bash
mkdir -p /home/bpsjembe/jagapadi.bpsjember.my.id/config

cat > /home/bpsjembe/jagapadi.bpsjember.my.id/config/config.php << 'EOF'
<?php
/**
 * Konfigurasi aplikasi — dibuat manual di server, tidak di-track Git.
 */

// Batas koordinat Kabupaten Jember (WGS84)
if (!defined('JEMBER_LAT_MIN')) define('JEMBER_LAT_MIN', -8.480000);
if (!defined('JEMBER_LAT_MAX')) define('JEMBER_LAT_MAX', -7.960000);
if (!defined('JEMBER_LON_MIN')) define('JEMBER_LON_MIN', 113.280000);
if (!defined('JEMBER_LON_MAX')) define('JEMBER_LON_MAX', 113.980000);

// BPS WebAPI
if (!defined('BPS_API_KEY'))      define('BPS_API_KEY',      getenv('BPS_API_KEY')      ?: '');
if (!defined('BPS_API_BASE_URL')) define('BPS_API_BASE_URL', getenv('BPS_API_BASE_URL') ?: 'https://webapi.bps.go.id/v1');
if (!defined('BPS_API_TIMEOUT'))  define('BPS_API_TIMEOUT',  (int)(getenv('BPS_API_TIMEOUT') ?: 30));
EOF

chmod 600 /home/bpsjembe/jagapadi.bpsjember.my.id/config/config.php
```

### Langkah 3.6 — Perbaiki script deploy dari CRLF ke LF

> Hanya perlu dilakukan sekali jika script dibuat di Windows.

```bash
sed -i 's/\r//' /home/bpsjembe/deploy_jagapadi.sh
file /home/bpsjembe/deploy_jagapadi.sh
# Output harus: Bourne-Again shell script ... text executable (tanpa CRLF)
```

### Langkah 3.7 — Jalankan script deploy

```bash
bash /home/bpsjembe/deploy_jagapadi.sh
```

Output yang diharapkan:
```
== Validasi prasyarat ==
== Backup web root saat ini ==
== Selamatkan file konfigurasi production ==
== Pull kode terbaru dari GitHub ==
== Pastikan direktori runtime ada ==
== Bersihkan web root ==
== Salin kode baru ==
== Hapus file development dari web root ==
== Restore konfigurasi production ==
== Set permission ==
== Bersihkan temp ==
== DEPLOY SELESAI ==
```

---

## 4. Deploy Update Berikutnya

Setelah deploy pertama berhasil, proses update cukup dua langkah:

### Langkah 4.1 — Push kode terbaru ke GitHub (dari lokal)

```powershell
# Di komputer lokal
cd C:\laragon\www\jagapadi-3509
git add file-yang-berubah
git commit -m "fix: deskripsi perbaikan"
git push origin main
```

### Langkah 4.2 — Jalankan deploy di server

```bash
# Di server via SSH atau Terminal cPanel
bash /home/bpsjembe/deploy_jagapadi.sh
```

Script otomatis:
- Backup web root sebelum update
- `git pull` dari GitHub
- Bersihkan dan salin kode baru
- Restore `.env` dan `config/config.php`
- Set permission

> **Catatan:** File `.env` dan `config/config.php` di server **tidak akan tertimpa** karena keduanya tidak ada di repository Git.

---

## 5. Import Database

### 5.1 — Export dari database lokal (Windows)

Buka browser, akses `http://localhost/phpmyadmin`:

1. Klik database `jagapadi_local`
2. Tab **Export** → Method: **Custom**
3. Hilangkan centang **Add CREATE DATABASE / USE statement**
4. Centang **Add DROP TABLE**
5. Character set: `utf8mb4`
6. Klik **Export** → simpan sebagai `jagapadi-schema.sql`

### 5.2 — Upload file SQL ke server

**Via cPanel File Manager:**

1. Login cPanel → File Manager
2. Navigasi ke `/home/bpsjembe/`
3. Upload `jagapadi-schema.sql`

**Atau via SCP dari PowerShell lokal:**

```powershell
scp C:\Users\IPDS\Desktop\jagapadi-schema.sql bpsjembe@jagapadi.bpsjember.my.id:/home/bpsjembe/
```

### 5.3 — Import di server

```bash
mysql -u bpsjembe_nanangpx -p'ISI_PASSWORD_DATABASE' bpsjembe_jagapadi_3509 \
  < /home/bpsjembe/jagapadi-schema.sql
```

### 5.4 — Verifikasi tabel berhasil dibuat

```bash
mysql -u bpsjembe_nanangpx -p'ISI_PASSWORD_DATABASE' bpsjembe_jagapadi_3509 \
  -e "SHOW TABLES;"
```

Tabel yang harus ada minimal:
```
users
laporan_hama
laporan_irigasi
feedback
feedback_votes
feedback_status_history
notifications
kecamatan
desa
kabupaten
```

### 5.5 — Hapus file SQL setelah selesai

```bash
rm /home/bpsjembe/jagapadi-schema.sql
```

---

## 6. Pengujian Pasca-Deploy

Lakukan setelah setiap deploy, buka browser dan uji satu per satu:

| No | URL | Yang Diharapkan |
|----|-----|----------------|
| 1 | `https://jagapadi.bpsjember.my.id/login` | Halaman login tampil tanpa error |
| 2 | `https://jagapadi.bpsjember.my.id/dashboard` (tanpa login) | Diarahkan ke `/login` |
| 3 | Login sebagai Admin | Dashboard terbuka, menu lengkap |
| 4 | Login sebagai Petugas | Dashboard terbuka, menu terbatas |
| 5 | `/laporan` | Daftar laporan tampil |
| 6 | `/laporan/create` | Form laporan tampil |
| 7 | `/feedback` | Daftar masukan tampil sesuai role |
| 8 | `/feedback/admin-summary` | Tampil untuk Admin, ditolak untuk Petugas |
| 9 | `/dashboard/map` | Peta Leaflet muncul |
| 10 | `/export/excel` | File Excel terunduh |

### Pengujian keamanan — semua harus 403 atau 404

```
https://jagapadi.bpsjember.my.id/.env
https://jagapadi.bpsjember.my.id/config/config.php
https://jagapadi.bpsjember.my.id/public/uploads/
https://jagapadi.bpsjember.my.id/.git/config
```

### Cek error log di server

```bash
tail -30 /home/bpsjembe/jagapadi.bpsjember.my.id/error_log 2>/dev/null || echo "Log kosong"
```

---

## 7. Pemeliharaan Rutin

| Frekuensi | Aktivitas |
|-----------|-----------|
| Setiap deploy | Backup database production sebelum migration baru |
| Setiap minggu | Cek error log di server |
| Setiap bulan | Verifikasi SSL masih aktif |
| Setiap bulan | Cek ukuran folder backup, hapus yang lebih dari 30 hari |
| Setiap kuartal | Full backup via cPanel Backup Wizard |

### Hapus backup lama secara manual

```bash
# Lihat daftar backup
ls -lh /home/bpsjembe/backups/

# Hapus backup lebih dari 30 hari
find /home/bpsjembe/backups/ -name "jagapadi-backup-*.tar.gz" -mtime +30 -delete
```

### Backup database production sebelum update skema

```bash
mysqldump -u bpsjembe_nanangpx -p'ISI_PASSWORD_DATABASE' \
  bpsjembe_jagapadi_3509 \
  > /home/bpsjembe/backups/db-backup-$(date +%F-%H%M%S).sql
```

---

## 8. Rollback Jika Terjadi Masalah

### Rollback kode ke versi sebelumnya

```bash
# Lihat daftar backup yang tersedia
ls -lh /home/bpsjembe/backups/

# Restore dari backup (ganti nama file sesuai backup yang diinginkan)
cd /home/bpsjembe
tar -xzf backups/jagapadi-backup-2026-08-24-104745.tar.gz
```

### Rollback ke commit Git tertentu

```bash
# Di server
cd /home/bpsjembe/repositories/jagapadi

# Lihat riwayat commit
git log --oneline -10

# Reset ke commit yang stabil (ganti abc1234 dengan hash yang diinginkan)
git reset --hard abc1234

# Jalankan deploy dengan kode yang sudah di-reset
bash /home/bpsjembe/deploy_jagapadi.sh
```

### Rollback database

```bash
# Restore dari backup database yang dibuat sebelum deploy
mysql -u bpsjembe_nanangpx -p'ISI_PASSWORD_DATABASE' bpsjembe_jagapadi_3509 \
  < /home/bpsjembe/backups/db-backup-TANGGAL.sql
```

---

## 9. Troubleshooting

### Error: `fatal: destination path already exists`

Repository sudah pernah di-clone. Tidak perlu clone ulang, cukup:

```bash
cd /home/bpsjembe/repositories/jagapadi
git remote set-url origin https://github.com/nanangpx0-hub/jagapadi-3509.git
git fetch origin
git reset --hard origin/main
```

### Error: `$'\r': command not found`

Script mengandung karakter Windows (CRLF). Konversi ke LF:

```bash
sed -i 's/\r//' /home/bpsjembe/deploy_jagapadi.sh
```

### Error: `set: pipefail: invalid option name`

Sama dengan masalah CRLF di atas — jalankan perintah `sed` yang sama.

### Halaman menampilkan error 500

```bash
# Cek error log
tail -50 /home/bpsjembe/jagapadi.bpsjember.my.id/error_log

# Pastikan .env ada dan terbaca
ls -la /home/bpsjembe/jagapadi.bpsjember.my.id/.env

# Pastikan config/config.php ada
ls -la /home/bpsjembe/jagapadi.bpsjember.my.id/config/config.php
```

### Koneksi database gagal

```bash
# Test koneksi langsung
mysql -u bpsjembe_nanangpx -p'ISI_PASSWORD_DATABASE' bpsjembe_jagapadi_3509 \
  -e "SELECT 1;" && echo "Koneksi OK" || echo "Koneksi GAGAL"
```

### Upload foto tidak bisa disimpan

```bash
# Cek dan set permission direktori upload
ls -la /home/bpsjembe/jagapadi.bpsjember.my.id/public/uploads/
chmod -R 775 /home/bpsjembe/jagapadi.bpsjember.my.id/public/uploads/
```

### Semua URL selain index.php menghasilkan 404

`mod_rewrite` mungkin tidak aktif. Hubungi Jagoan Hosting untuk mengaktifkan `mod_rewrite` di domain `jagapadi.bpsjember.my.id`.

---

## Checklist Deploy Cepat

Gunakan checklist ini setiap kali melakukan deploy update:

```
LOKAL
[ ] git status bersih dari file sensitif (.env, config/config.php)
[ ] git add hanya file yang berubah
[ ] git commit dengan pesan yang deskriptif
[ ] git push origin main berhasil
[ ] Verifikasi di GitHub — tidak ada file sensitif

SERVER
[ ] bash /home/bpsjembe/deploy_jagapadi.sh berhasil (output: DEPLOY SELESAI)
[ ] Commit yang tampil di output = commit yang di-push

PENGUJIAN
[ ] https://jagapadi.bpsjember.my.id/login tampil normal
[ ] Login Admin berhasil
[ ] Login Petugas berhasil
[ ] Fitur yang diubah berjalan sesuai ekspektasi
[ ] Error log tidak ada Fatal error baru
```

---

*Panduan ini dibuat berdasarkan kondisi aktual proyek JAGAPADI per Agustus 2026.*  
*Untuk pertanyaan teknis, lihat juga `docs/DEPLOY.md` dan `docs/SMOKE_TEST.md`.*
