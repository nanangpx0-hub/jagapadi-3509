# Panduan Akses Aplikasi JAGAPADI dari PC Lain

> **Tujuan:** Membuka aplikasi JAGAPADI (web + database) dari PC/laptop lain, baik di **jaringan LAN lokal** maupun **via internet**.  
> **Status saat ini:** `https://jagapadi.my.id` **sudah live** via Cloudflare Tunnel di PC server lama. Panduan ini mencakup semua skenario akses.

> **Dokumen terkait:**  
> - Cloudflare Tunnel & SOP harian: [`PANDUAN_HOSTING_SERVER_LOKAL_CLOUDFLARE.md`](PANDUAN_HOSTING_SERVER_LOKAL_CLOUDFLARE.md)  
> - Deployment VPS/cPanel: [`DEPLOY.md`](DEPLOY.md) · [`PANDUAN_DEPLOYMENT.md`](PANDUAN_DEPLOYMENT.md)  
> - Konfigurasi domain & SSL: [`PANDUAN_DOMAIN_CLOUDFLARE_JAGAPADI_MY_ID.md`](PANDUAN_DOMAIN_CLOUDFLARE_JAGAPADI_MY_ID.md)

---

## Daftar Isi

1. [Gambaran: 3 Skenario Akses](#1-gambaran-3-skenario-akses)
2. [Skenario A — LAN Lokal (WiFi/Jaringan Sama)](#2-skenario-a--lan-lokal-wifi-jaringan-sama)
3. [Skenario B — Internet via Cloudflare Tunnel (Sudah Live)](#3-skenario-b--internet-via-cloudflare-tunnel-sudah-live)
4. [Skenario C — Full Copy ke PC Lain (App + DB Pindah)](#4-skenario-c--full-copy-ke-pc-lain-app--db-pindah)
5. [Skenario D — VPS / Hosting Jarak Jauh](#5-skenario-d--vps--hosting-jarak-jauh)
6. [Akses Database dari PC Lain](#6-akses-database-dari-pc-lain)
7. [Keamanan & Catatan Penting](#7-keamanan--catatan-penting)
8. [Troubleshooting](#8-troubleshooting)
9. [Checklist Cetak](#9-checklist-cetak)

---

## 1. Gambaran: 3 Skenario Akses

| # | Skenario | Kapan Dipakai | Perlu |
|---|---|---|---|
| **A** | LAN lokal (WiFi/hubungi satu jaringan) | PC lain di rumah/kantor **sama** dengan server | IP lokal server |
| **B** | Internet (Cloudflare Tunnel) | PC mana saja, **mana pun** internetnya | Domain `jagapadi.my.id` sudah live |
| **C** | Pindah total ke PC lain | Server lama mati/rusak/ganti PC | Backup DB + file, PC baru |
| **D** | VPS/Hosting jarak jauh | Ingin server terpusat tanpa PC nyala | VPS + deploy |

```mermaid
flowchart LR
    subgraph LAN["A. LAN Lokal"]
        PC2["PC/Laptop Lain"] -->|"192.168.x.x"| Server["PC Server (Laragon)"]
    end
    subgraph Internet["B. Internet (Cloudflare)"]
        PC3["PC Mana Saja"] -->|"https://jagapadi.my.id"| CF["Cloudflare Edge"]
        CF -->|Tunnel| Server
    end
    subgraph Pindah["C. Full Copy"]
        PC4["PC Baru"] -->|"Copy + Import DB"| Server2["PC Baru (Server Baru)"]
    end
    subgraph VPS["D. VPS"]
        PC5["PC Mana Saja"] -->|"https://domain.com"| VPS["VPS (Nginx + PHP + MySQL)"]
    end
```

---

## 2. Skenario A — LAN Lokal (WiFi/Jaringan Sama)

### Prasyarat
- PC server (Laragon) dan PC lain terhubung ke **WiFi/jaringan yang sama**
- Laragon berjalan (Apache + MySQL aktif)
- JAGAPADI sudah bisa dibuka di `http://localhost/jagapadi-3509` di server itu sendiri

### Langkah

#### 2.1 — Cek IP Lokal Server

Di PC server, buka PowerShell:

```powershell
ipconfig | findstr "IPv4"
```

Hasil contoh:
```
IPv4 Address. . . . . . . . . . . : 192.168.1.100
```

Catat IP ini (misal: `192.168.1.100`). IP bersifat **dinamis** — bisa berubah saat reboot router.

#### 2.2 — Pastikan Apache mendengarkan semua interface

Buka `C:\laragon\etc\apache2\httpd.conf`, cari:

```apache
Listen 80
```

Pastikan **tidak** tertulis `Listen 127.0.0.1:80` (yang hanya menerima localhost). Seharusnya:

```apache
Listen 80
# atau Listen 0.0.0.0:80  (menerima semua IP)
```

#### 2.3 — Firewall Windows — Izinkan Apache

```powershell
# Cek apakah rule sudah ada
Get-NetFirewallRule -DisplayName "*Apache*" | Select-Object DisplayName, Enabled

# Jika belum, buat:
New-NetFirewallRule -DisplayName "Apache HTTP" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow
```

#### 2.4 — Buka dari PC lain

Di browser PC lain, ketik:

```
http://192.168.1.100/jagapadi-3509
```

> **Catatan:** Karena document root JAGAPADI root adalah `C:\laragon\www\jagapadi-3509` (bukan `backend/public`), URL-nya **tidak perlu** `/backend/public`. File `index.php` di root yang menjadi front controller.

#### 2.5 — URL akses lengkap

| Fungsi | URL dari PC lain |
|---|---|
| Dashboard web | `http://192.168.1.100/jagapadi-3509` |
| Login admin | `http://192.168.1.100/jagapadi-3509/login` |
| API mobile | `http://192.168.1.100/jagapadi-3509/api/v1` |
| Health | `http://192.168.1.100/jagapadi-3509/api/v1/health` |
| Evaluasi | `http://192.168.1.100/jagapadi-3509/evaluasi` |

> `.htaccess` sudah mengizinkan IP LAN (`192.168.*`, `10.*`, `172.16-31.*`) agar **tidak** redirect ke HTTPS. Ini memang disengaja untuk akses pengembangan lokal (lihat `/.htaccess:23-24`).

#### 2.6 — Agar IP tidak berubah (opsional)

Agar IP tidak berubah saat router reboot, atur **static IP** di adapter Windows server:

```powershell
# Cek adapter name
Get-NetAdapter | Select-Object Name, Status, LinkSpeed

# Set static IP (ganti sesuai subnet Anda)
New-NetIPAddress -InterfaceAlias "Ethernet" -IPAddress 192.168.1.100 -PrefixLength 24 -DefaultGateway 192.168.1.1
Set-DnsClientServerAddress -InterfaceAlias "Ethernet" -ServerAddresses 192.168.1.1
```

Atau di router, set **DHCP Reservation** berdasarkan MAC address adapter server.

---

## 3. Skenario B — Internet via Cloudflare Tunnel (Sudah Live)

> **Cara paling mudah:** Tidak perlu konfigurasi apa pun di PC lain.

### Langkah

1. **Pastikan Cloudflare Tunnel jalan** di PC server (lihat [`PANDUAN_HOSTING_SERVER_LOKAL_CLOUDFLARE.md`](PANDUAN_HOSTING_SERVER_LOKAL_CLOUDFLARE.md) §4):

   ```powershell
   Get-Process cloudflared
   Get-ScheduledTask -TaskName "Cloudflared-Jagapadi"
   ```

2. **Buka browser di PC mana saja** (di rumah, kantor, warnet, HP):

   ```
   https://jagapadi.my.id
   ```

3. **Semua modul tersedia:**

   ```
   https://jagapadi.my.id/dashboard
   https://jagapadi.my.id/login
   https://jagapadi.my.id/evaluasi
   https://jagapadi.my.id/api/v1/health
   https://jagapadi.my.id/api/v1/auth/login
   ```

4. **Login** dengan akun admin/petugas yang sudah ada. Database yang digunakan **sama** (di PC server lokal).

### Keunggulan

- **Tanpa VPN, tanpa port forwarding**
- **HTTPS otomatis** (Cloudflare edge certificate)
- **Tahan CGNAT** (tidak butuh IP publik)
- **WAF + DDoS protection** Cloudflare
- Cocok untuk **akses mobile** (Flutter app juga pakai `https://jagapadi.my.id/api/v1`)

### Catatan

- PC server **harus nyala** agar tunnel aktif
- Jika PC server mati, domain menampilkan `503` atau error Cloudflare
- Solusi jika PC server sering mati: pindah ke **Skenario D (VPS)**

---

## 4. Skenario C — Full Copy ke PC Lain (App + DB Pindah)

### 4.1 — Backup di Server Lama

#### 4.1.1 — Backup file

```powershell
# Di PC server, di root proyek
cd C:\laragon\www\jagapadi-3509

# Salin seluruh folder ke USB / eksternal / jaringan lokal
# Exclude file sensitif
robocopy . D:\Backup\jagapadi-exclude .env .env.local config/config.php *.sql *.key *.pem *.log /XD vendor /XD node_modules /XD storage/cache /XD storage/logs
```

Atau via PowerShell (zip tanpa file sensitif):

```powershell
$exclude = @('.env','.env.local','config/config.php','*.sql','*.log','*.key')
Compress-Archive -Path 'C:\laragon\www\jagapadi-3509\*' -DestinationPath 'D:\Backup\jagapadi-backup.zip' -CompressionLevel Optimal
```

> **Jangan** ikut zip: `.env`, `config/config.php`, `*.sql`, `*.key`, `*.pem`, `*.log`. File ini berisi kredensial.

#### 4.1.2 — Backup database

```powershell
# Export via mysqldump
"C:\laragon\bin\mysql\mysql8.0.30\bin\mysqldump" -u root -p --default-character-set=utf8mb4 --single-transaction --routines --triggers --add-drop-database jagapadi_local > D:\Backup\jagapadi_db_20260913.sql
```

Atau via phpMyAdmin:
1. Buka `http://localhost/phpmyadmin`
2. Pilih database `jagapadi_local` → **Export** → Custom → Character set `utf8mb4` → **Go** → Simpan file `.sql`

#### 4.1.3 — Catat credential

- DB Name: `jagapadi_local`
- DB User: `root` (default Laragon)
- DB Pass: kosong (default Laragon) atau sesuai `.env`

### 4.2 — Siapkan PC Baru

PC baru memerlukan **tumpukan teknis yang sama**:

| Software | Versi minimum | Keterangan |
|---|---|---|
| OS | Windows 10/11 | — |
| Laragon | 3.x+ | Termasuk Apache, PHP 8.2, MySQL 8.0 |
| PHP Extensions | `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `curl`, `zip`, `json`, `xml` | Cek `php -m` |
| Composer | 2.x | Untuk `backend/` dependency |
| Git | 2.x | Opsional (untuk pull dari GitHub) |
| MySQL client | 8.0+ | Untuk import DB |

Instal Laragon: [https://laragon.org](https://laragon.org)

### 4.3 — Restore di PC Baru

#### 4.3.1 — Salin file

```powershell
# Ekstrak backup ke C:\laragon\www\
# Hasil: C:\laragon\www\jagapadi-3509\
```

#### 4.3.2 — Buat database

```powershell
# Buka phpMyAdmin di PC baru: http://localhost/phpmyadmin
# Atau via CLI:
"C:\laragon\bin\mysql\mysql8.0.30\bin\mysql" -u root -p -e "CREATE DATABASE IF NOT EXISTS jagapadi_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### 4.3.3 — Import database

```powershell
"C:\laragon\bin\mysql\mysql8.0.30\bin\mysql" -u root -p jagapadi_local < D:\Backup\jagapadi_db_20260913.sql
```

#### 4.3.4 — Jalankan migrasi (jika ada)

```powershell
cd C:\laragon\www\jagapadi-3509
php database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php
```

#### 4.3.5 — Atur `.env`

Salin `.env.example` → `.env`. Isi minimal:

```ini
APP_ENV=local
APP_DEBUG=true
APP_BASE_URL=http://localhost/jagapadi-3509

DB_NAME=jagapadi_local
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

JWT_SECRET=<generate: php -r "echo bin2hex(random_bytes(32));">
CORS_ALLOWED_ORIGINS=http://localhost:8080,http://localhost:3000,http://192.168.1.0/16
```

#### 4.3.6 — Jalankan

```powershell
# Start Laragon (Start All)
# Buka browser: http://localhost/jagapadi-3509
```

### 4.4 — Verifikasi

| No | Cek | Hasil yang Diharapkan |
|---|---|---|
| 1 | `http://localhost/jagapadi-3509/api/v1/health` | JSON `{"success":true,"database":"connected"}` |
| 2 | `http://localhost/jagapadi-3509/login` | Form login tampil |
| 3 | Login admin | Dashboard terbuka, data evaluasi ada |
| 4 | `http://localhost/jagapadi-3509/evaluasi` | Dashboard evaluasi tampil |
| 5 | `/evaluasi/generateSnapshot` | Snapshot jalan (CSRF OK) |

### 4.5 — Opsional: GitHub Clone

Jika proyek sudah di GitHub:

```powershell
cd C:\laragon\www
git clone https://github.com/nanangpx0-hub/jagapadi-3509.git
cd jagapadi-3509
cp .env.example .env
# Edit .env sesuai §4.3.5
composer install --no-dev --optimize-autoloader  # untuk backend/
php database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php
```

Lalu import DB sesuai §4.3.3.

---

## 5. Skenario D — VPS / Hosting Jarak Jauh

### 5.1 — Pilih VPS

| Provider | Mulai | Cocok untuk |
|---|---|---|
| **Hetzner** | €3.79/bulan | VPS murah, SSD, lokasi Jerman/Finlandia |
| **DigitalOcean** | $4/bulan | Dokumentasi bagus, global |
| **Vultr** | $2.50/bulan | Cepat, Jakarta region tersedia |
| **AWS EC2** | gratis 12 bulan (t2.micro) | Gratis sementara, fitur lengkap |
| **Contabo** | €4.99/bulan | RAM besar murah |

### 5.2 — Setup VPS (Ubuntu 22.04/24.04)

Lihat template **lengkap** di [`DEPLOY.md`](DEPLOY.md) §1–14:

```bash
# 1. Update
sudo apt update && sudo apt upgrade -y

# 2. Install LEMP
sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-zip php8.2-fileinfo composer certbot python3-certbot-nginx unzip

# 3. Clone (atau upload via SFTP/SCP)
cd /var/www
git clone https://github.com/nanangpx0-hub/jagapadi-3509.git jagapadi
cd jagapadi/backend
composer install --no-dev --optimize-autoloader

# 4. Database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS jagapadi_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Import DB (upload file .sql via SCP, lalu)
mysql -u root -p jagapadi_prod < /home/user/jagapadi_db_backup.sql

# 6. .env
cp .env.example .env
# Edit: DB_*, APP_URL=https://domainanda.com, JWT_SECRET, CORS_ALLOWED_ORIGINS
chmod 600 .env

# 7. Nginx
# Lihat template DEPLOY.md §6

# 8. SSL
sudo certbot --nginx -d domainanda.com -d www.domainanda.com

# 9. Start
sudo systemctl restart php8.2-fpm nginx
```

### 5.3 — Akses dari PC lain

Buka browser:

```
https://domainanda.com
https://domainanda.com/login
https://domainanda.com/evaluasi
https://domainanda.com/api/v1/health
```

---

## 6. Akses Database dari PC Lain

### 6.1 — Dari LAN (LAN-only, aman)

Jika hanya perlu **melihat/menjalankan query** database dari PC lain di jaringan yang sama:

#### 6.1.1 — Izinkan remote MySQL (server lama)

```powershell
# Cek bind-address
Get-Content "C:\laragon\bin\mysql\mysql8.0.30\data\my.ini" | Select-String "bind-address"

# Default Laragon: bind-address = 127.0.0.1 (hanya localhost)
# Ganti jadi:
# bind-address = 0.0.0.0  (menerima semua IP)
```

Edit `C:\laragon\bin\mysql\mysql8.0.30\data\my.ini`:

```ini
[mysqld]
# bind-address = 127.0.0.1     # KOMENTAR atau ganti
bind-address = 0.0.0.0
```

Restart MySQL di Laragon.

#### 6.1.2 — Firewall MySQL

```powershell
New-NetFirewallRule -DisplayName "MySQL Remote" -Direction Inbound -Protocol TCP -LocalPort 3306 -Action Allow -RemoteAddress 192.168.1.0/24
```

#### 6.1.3 — Buat user remote (opsional, lebih aman)

```sql
-- Di phpMyAdmin atau MySQL CLI
CREATE USER 'remote_user'@'192.168.1.%' IDENTIFIED BY 'password_kuat';
GRANT SELECT ON jagapadi_local.* TO 'remote_user'@'192.168.1.%';
FLUSH PRIVILEGES;
```

> Beri `SELECT` saja (read-only) agar tidak bisa ubah data dari PC lain.

#### 6.1.4 — Hubungkan dari PC lain

Gunakan **HeidiSQL**, **DBeaver**, atau **MySQL Workbench**:

- **Host:** `192.168.1.100` (IP server)
- **Port:** `3306`
- **User:** `root` atau `remote_user`
- **Password:** sesuai `.env`
- **Database:** `jagapadi_local`

### 6.2 — Via SSH Tunnel (internet, aman)

Jika ingin akses database via internet tanpa expose port:

```powershell
# Dari PC lain, buat tunnel ke server via SSH
# (butuh SSH aktif di server, atau pakai Cloudflare Tunnel)

# Opsi 1: SSH langsung (VPS)
ssh -L 3306:127.0.0.1:3306 user@server_ip
# Kemudian connect HeidiSQL ke localhost:3306

# Opsi 2: Cloudflare Tunnel + local proxy
# Lebih kompleks, gunakan VPS sebagai jump host
```

### 6.3 — Via Cloudflare Tunnel (sudah live)

Jika menggunakan Tunnel (`jagapadi.my.id`), akses database **tidak bisa langsung** via Cloudflare (Cloudflare tidak expose MySQL port 3306). Untuk akses DB jarak jauh:

- **Pakai LAN** (§6.1) atau **SSH Tunnel** (§6.2)
- Atau pindah ke **VPS** (§5) yang buka port MySQL via SSH tunnel

---

## 7. Keamanan & Catatan Penting

### 7.1 — Jangan expose langsung ke internet tanpa perlindungan

| Yang **harus** | Yang **jangan** |
|---|---|
| HTTPS (Cloudflare atau Let's Encrypt) | HTTP polos di internet |
| CSRF token untuk semua mutasi POST | Form tanpa CSRF |
| `APP_DEBUG=false` di production | `APP_DEBUG=true` di internet |
| `.env` permission `600`, tidak di Git | `.env` tertempel di repository |
| `CORS_ALLOWED_ORIGINS` specific | `CORS_ALLOWED_ORIGINS=*` |
| `bind-address = 127.0.0.1` untuk MySQL | MySQL port 3306 open ke `0.0.0.0` tanpa batas |
| `.htaccess` block `.env/.git/.sql` | File sensitif bisa diakses publik |
| `server_tokens off` di Nginx | Versi server terbuka |

### 7.2 — Izin role

- **Admin:** akses penuh (dashboard, evaluasi, CRUD, snapshot, import, pengguna)
- **Statistisi:** **read-only** — dashboard, grafik, evaluasi, export (tidak bisa tambah/edit/hapus)
- **Petugas:** hanya resource miliknya sendiri

`index.php:41-47` (`checkEvaluationReadAccess`) sudah memisahkan read access untuk `admin` + `statistisi`.

### 7.3 — Backup rutin

| Data | Frekuensi | Cara |
|---|---|---|
| Database | Harian | `mysqldump` → `.sql.gz` |
| File proyek | Mingguan | `robocopy` / `rsync` |
| Uploads | Mingguan | `tar.gz` folder `storage/uploads/` |
| `.env` | Manual | Password manager / USB terenkripsi |

```powershell
# Backup DB manual
"C:\laragon\bin\mysql\mysql8.0.30\bin\mysqldump" -u root -p --default-character-set=utf8mb4 --single-transaction jagapadi_local | gzip > "D:\Backup\db_$(Get-Date -Format 'yyyyMMdd').sql.gz"
```

### 7.4 — UPS & Auto Power On

Jika PC server adalah mesin utama:
- **UPS** agar tidak mati mendadak saat listrik padam
- **BIOS → AC Back → Power On** agar PC otomatis hidup saat listrik pulih
- **Scheduled Task** cloudflared auto-start saat login

---

## 8. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| `403 Forbidden` dari PC LAN | `.htaccess` deny IP LAN | Cek `RewriteCond` baris 23-24 — pastikan tidak ada `192.168` di block list |
| `404` semua route kecuali `/` | Document root salah | Pastikan document root = `C:\laragon\www\jagapadi-3509` (bukan `backend/public`) |
| `500` / blank | `APP_DEBUG=true` di `.env` | Cek `storage/logs/laravel.log` untuk error detail |
| `DB connection failed` | `.env` salah / MySQL mati | `php -r "require 'app/core/Database.php';"` atau cek `php scripts/check_db_connection.php` |
| `403 CSRF` | Session expired / token mismatch | Refresh halaman, kirim ulang form |
| Cloudflare `1033` / `526` | Tunnel mati / cert expired | Cek `Get-Process cloudflared`, `cloudflared tunnel info jagapadi-server` |
| `ERR_TOO_MANY_REDIRECTS` | Loop HTTP↔HTTPS | Pastikan `.htaccess` cek `X-Forwarded-Proto` (baris 20) |
| `CORS error` dari Flutter app | Origin tidak di allowlist | Set `CORS_ALLOWED_ORIGINS=https://jagapadi.my.id` di `.env` |
| `SQL syntax error` import | Dump dari versi MySQL berbeda | Import via phpMyAdmin (auto convert) atau gunakan `mysql --force` |
| `Access denied for user` | User/host salah | `CREATE USER 'user'@'%' IDENTIFIED BY 'pass'; GRANT ALL` |
| MySQL `1045 Access denied` | Password salah | Reset via `mysqld --skip-grant-tables` atau cek `.env` |

**Perintah diagnosis:**

```powershell
# Dari PC server
php -v                                          # PHP 8.2+
mysql -u root -e "SELECT 1"                     # MySQL jalan
php scripts/check_db_connection.php             # Koneksi DB
cloudflared tunnel info jagapadi-server         # Tunnel status
Get-Process cloudflared                         # Tunnel jalan?
netstat -an | findstr ":80"                     # Apache listening?
Get-NetFirewallRule -DisplayName "*Apache*"     # Firewall?

# Dari PC lain (LAN)
ping 192.168.1.100                            # Ping berhasil?
curl http://192.168.1.100/jagapadi-3509/api/v1/health
```

---

## 9. Checklist Cetak

### Akses LAN (Skenario A)
```
[ ] PC server & PC lain di jaringan sama
[ ] ipconfig server catat IP (mis. 192.168.1.100)
[ ] Apache Listen 80 (bukan 127.0.0.1:80)
[ ] Firewall Windows izinkan port 80
[ ] Buka browser: http://192.168.1.100/jagapadi-3509
[ ] Login admin berhasil
[ ] /evaluasi tampil
[ ] .htaccess mengizinkan IP 192.168.* (tidak redirect HTTPS)
```

### Akses Internet via Tunnel (Skenario B)
```
[ ] cloudflared jalan di server (Get-Process cloudflared)
[ ] Scheduled Task "Cloudflared-Jagapadi" aktif
[ ] Buka https://jagapadi.my.id dari PC mana saja
[ ] curl -I https://jagapadi.my.id → 200, server: cloudflare
[ ] /api/v1/health → connected
[ ] Login admin berhasil
```

### Pindah ke PC Baru (Skenario C)
```
[ ] Backup file (exclude .env, config, *.sql)
[ ] Backup DB via mysqldump / phpMyAdmin
[ ] Catat credential DB (name, user, pass)
[ ] Install Laragon di PC baru
[ ] Salin file ke C:\laragon\www\jagapadi-3509\
[ ] Buat database jagapadi_local
[ ] Import .sql
[ ] Buat .env dari .env.example, isi credential
[ ] php database/migrations/2026_09_12_create_evaluasi_akurasi_tables.php
[ ] Buka http://localhost/jagapadi-3509
[ ] /api/v1/health → connected
[ ] Login → data evaluasi ada
```

### VPS (Skenario D)
```
[ ] VPS aktif (Ubuntu 22.04+)
[ ] Install LEMP + PHP 8.2 + Composer
[ ] Clone repo / upload file
[ ] Buat database prod, import .sql
[ ] Edit .env (APP_URL=https, DB_*, CORS_ALLOWED_ORIGINS)
[ ] Nginx config (DEPLOY.md §6)
[ ] Certbot SSL
[ ] php-fpm + nginx restart
[ ] curl -I https://domainanda.com → 200
[ ] Login → data ada
```

---

*Dokumen ini melengkapi [`PANDUAN_HOSTING_SERVER_LOKAL_CLOUDFLARE.md`](PANDUAN_HOSTING_SERVER_LOKAL_CLOUDFLARE.md) (detail Tunnel & SOP) dan [`DEPLOY.md`](DEPLOY.md) (VPS/cPanel). Pilih skenario yang sesuai kebutuhan.*

*Jangan pernah menempel isi `.env`, `config/config.php`, `*.sql`, `*.key`, atau password ke dokumen ini atau chat.*
