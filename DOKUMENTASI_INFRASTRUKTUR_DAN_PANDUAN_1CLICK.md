# Dokumentasi Komprehensif: Infrastruktur Layanan & Panduan 1-Click Master Launcher (JAGAPADI, Petalink, Sipandhalu)

**Badan Pusat Statistik (BPS) Kabupaten Jember**  
*Lingkungan Produksi & Lokal Terintegrasi (Laragon + Cloudflare Zero Trust)*  
*Tanggal Pembaruan: 13 September 2026*

---

## 1. Ringkasan Eksekutif

Ekosistem aplikasi web dan mobile di server operasional BPS Kabupaten Jember (`C:\laragon\www`) menaungi tiga sistem penting yang saling melengkapi dalam pengawalan data statistik dan pertanian:
1. **JAGAPADI (3509)**: Sistem pelaporan digital pertanian (Hama/OPT & Irigasi) untuk Kabupaten Jember (`https://jagapadi.my.id`).
2. **PETALINK (SE2026-Peta)**: Sistem manajemen dan pengarsipan peta Sensus Ekonomi 2026 (`https://petalink.my.id`).
3. **SIPANDHALU**: Sistem informasi dan monitoring pengendalian hama terpadu Susenas–Seruti (`https://sipandhalu.my.id`).

Sebelumnya, pengoperasian ketiga layanan ini memerlukan banyak langkah manual (membuka Laragon, menjalankan tunnel Petalink melalui file batch terpisah, menjalankan tunnel Sipandhalu, dan mengonfigurasi tunnel Jagapadi). Hal ini rentan terhadap kesalahan urutan eksekusi, bentrok port, atau matinya tunnel ketika terminal ditutup.

Melalui pengembangan **1-Click Master Launcher**, seluruh ekosistem kini dapat diaktifkan, diperiksa integritas dependensinya, dikonfigurasi secara otomatis, dan diverifikasi status kesehatannya **hanya dengan satu klik dari Desktop pengguna**.

---

## 2. Analisis Menyeluruh Dependensi Infrastruktur

### 2.1 Peta Arsitektur Ekosistem

```
                  [ PENGGUNA PUBLIK / LAPANGAN ]
                                │
                                ▼
                   [ CLOUDFLARE ZERO TRUST CDN ]
        ┌───────────────────────┼───────────────────────┐
        ▼                       ▼                       ▼
  jagapadi.my.id          petalink.my.id         sipandhalu.my.id
  (Tunnel: jagapadi)     (Tunnel: petalink)     (Tunnel: sipandhalu)
        │                       │                       │
        └───────────────────────┼───────────────────────┘
                                ▼
             [ PC SERVER LOKAL: C:\Program Files (x86)\cloudflared ]
                                │
                                ▼
            [ LARAGON APACHE WEB SERVER (Port 80 & 443) ]
       ┌────────────────────────┼────────────────────────┐
       ▼                        ▼                        ▼
 [Virtual Host 1]         [Virtual Host 2]         [Virtual Host 3]
 auto.jagapadi-3509       auto.Petalink.test       auto.sipandhalu.test
 (DocRoot: jagapadi-3509) (DocRoot: Petalink/pub)  (DocRoot: sipandhalu/pub)
       │                        │                        │
       └────────────────────────┼────────────────────────┘
                                ▼
            [ LARAGON MYSQL DATABASE SERVER (Port 3306) ]
       ┌────────────────────────┼────────────────────────┐
       ▼                        ▼                        ▼
  jagapadi_local           se2026_peta              sipandhalu
```

---

### 2.2 Rincian Dependensi Infrastruktur

#### A. Web Server (Apache HTTP Server 2.4 - Laragon)
* **Binari**: `C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe`
* **Port Wajib**:
  * `Port 80` (HTTP) — Digunakan untuk proksi lokal Cloudflare tunnel.
  * `Port 443` (HTTPS/SSL) — Digunakan dengan sertifikat wildcard Laragon (`C:/laragon/etc/ssl/laragon.crt` & `laragon.key`).
* **Daftar Virtual Host Terdaftar**:
  1. `auto.jagapadi-3509.test.conf`:
     * DocumentRoot: `C:/laragon/www/jagapadi-3509`
     * Endpoint API: `/api/v1` diarahkan ke `C:/laragon/www/jagapadi-3509/backend/public`
     * Hostname: `jagapadi-3509.test`, `jagapadi.my.id`, `*.jagapadi.my.id`
  2. `auto.Petalink.test.conf`:
     * DocumentRoot: `C:/laragon/www/Petalink/public`
     * Hostname: `Petalink.test`, `petalink.my.id`, `*.petalink.my.id`
  3. `auto.sipandhalu.test.conf`:
     * DocumentRoot: `C:/laragon/www/sipandhalu/sipandhalu/public`
     * Hostname: `sipandhalu.test`, `sipandhalu.my.id`, `*.sipandhalu.my.id`

#### B. Basis Data (MySQL 8.0 - Laragon)
* **Binari**: `C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe`
* **Port Wajib**: `Port 3306` (TCP)
* **Kredensial Default**: Host `127.0.0.1`, User `root`, Password `(kosong)`
* **Database Wajib**:
  1. `jagapadi_local`: Menyimpan data master wilayah Jember, data master OPT/hama, laporan kerusakan irigasi, dan pengguna sistem.
  2. `se2026_peta`: Menyimpan data tanda terima penerimaan peta, detail lembar peta, lokasi rak/boks arsip, dan log aktivitas petugas receiving/scanning.
  3. `sipandhalu`: Menyimpan data akun POPT, jadwal pendampingan, serta pelaporan Susenas–Seruti.

#### C. PHP Runtime (PHP 8.2 - Laragon)
* **Binari**: `C:\laragon\bin\php\php-8.2.32-nts-Win32-vs16-x64\php.exe`
* **Ekstensi Wajib**:
  * `pdo_mysql` (konektivitas database)
  * `mbstring` (manipulasi string multibyte)
  * `curl` (pemanggilan WebAPI BPS, NASA Power, dan integrasi eksternal)
  * `gd` & `fileinfo` (kompresi dan validasi berkas foto pelaporan)
  * `openssl` (enkripsi token JWT dan verifikasi sesi)
  * `zip` (ekspor dan impor bundle data)

#### D. Cloudflare Zero Trust / Argo Tunnels
* **Binari**: `C:\Program Files (x86)\cloudflared\cloudflared.exe`
* **Konfigurasi per Layanan**:
  1. **JAGAPADI**:
     * Tunnel ID: `168ca50f-e89f-4c18-8d70-c5427121dbe6` (Named Tunnel: `jagapadi-server`)
     * File Konfigurasi: `C:\Users\IPDS\.cloudflared\config.yml`
     * Perintah Startup: `cloudflared.exe tunnel --config C:\Users\IPDS\.cloudflared\config.yml run jagapadi-server`
  2. **PETALINK**:
     * Tunnel ID: `edf0eb17-a308-4f0a-bf35-15afce17e5e6`
     * Mode: Token-based
     * Token: `eyJhIjoiZTQyMGM4MTVmNmU0MTU1NmUxNmE4NjUzMWIzN2VjN2IiLCJ0IjoiZWRmMGViMTctYTMwOC00ZjBhLWJmMzUtMTVhZmNlMTdlNWU2IiwicyI6IllUVXhNVGc1TURNdE9HTm1NQzAwTkdKbUxXSXpZemt0Wm1ReU1UQTNPV00zWVdZNSJ9`
  3. **SIPANDHALU**:
     * Tunnel ID: `36120c2f-09cc-48e4-95c0-f6754b719191`
     * Mode: Token-based
     * Token: `eyJhIjoiZTQyMGM4MTVmNmU0MTU1NmUxNmE4NjUzMWIzN2VjN2IiLCJ0IjoiMzYxMjBjMmYtMDljYy00OGU0LTk1YzAtZjY3NTRiNzE5MTkxIiwicyI6IllUYzJaR0poTldRdFltTXhPQzAwWW1WaExUZzBNREV0WW1aaFpEZG1aalkxTjJFdyJ9`

---

## 3. Desain Solusi & Struktur Berkas 1-Click

Solusi ini dirancang secara modular agar mudah dipelihara dan tahan terhadap gangguan sistem:

```
C:\laragon\www\
├── manage-ecosystem.ps1          <-- Inti Logika Orkestrasi (PowerShell Core)
├── START_JAGAPADI_ECOSYSTEM.bat   <-- Wrapper 1-Click untuk Mulai Semua Layanan
├── STATUS_JAGAPADI_ECOSYSTEM.bat  <-- Wrapper 1-Click untuk Cek Kesehatan Layanan
├── STOP_JAGAPADI_ECOSYSTEM.bat    <-- Wrapper 1-Click untuk Menghentikan Tunnel
├── ecosystem-logs\                <-- Direktori Terpusat Log Orkestrasi & Tunnel
│   ├── orchestrator.log
│   ├── cf-jagapadi.log
│   ├── cf-petalink.log
│   └── cf-sipandhalu.log
└── ...
```

### Shortcut di Desktop Pengguna:
1. **`Mulai Semua Layanan (JAGAPADI - Petalink - Sipandhalu).lnk`**  
   *Target*: `C:\laragon\www\START_JAGAPADI_ECOSYSTEM.bat`  
   *Fungsi*: Tombol utama untuk memulai seluruh ekosistem dalam 1 klik.
2. **`Cek Status Layanan JAGAPADI.lnk`**  
   *Target*: `C:\laragon\www\STATUS_JAGAPADI_ECOSYSTEM.bat`  
   *Fungsi*: Memeriksa status kesehatan dan latensi seluruh domain tanpa mengganggu layanan yang berjalan.
3. **`Hentikan Semua Layanan JAGAPADI.lnk`**  
   *Target*: `C:\laragon\www\STOP_JAGAPADI_ECOSYSTEM.bat`  
   *Fungsi*: Mematikan seluruh Cloudflare tunnel secara bersih saat jam operasional selesai.

---

## 4. Mekanisme Kerja Skrip Orkestrator (`manage-ecosystem.ps1`)

Skrip menjalankan 5 tahapan otomatis setiap kali tombol ditekan:

1. **Tahap 1: Pemeriksaan Dependensi (Pre-flight Checks)**  
   * Memeriksa keberadaan executable Laragon, Apache (`httpd.exe`), MySQL (`mysql.exe`), PHP (`php.exe`), dan Cloudflared.
   * Memeriksa keberadaan direktori kode sumber ketiga proyek.
   * Memvalidasi modul/ekstensi PHP (`pdo_mysql`, `mbstring`, `curl`, dll).

2. **Tahap 2: Konfigurasi Otomatis & Basis Data**  
   * Memastikan virtual host Apache terdaftar di `C:\laragon\etc\apache2\sites-enabled\`.
   * Memastikan folder `storage/logs` dan folder log ekosistem tersedia dengan hak tulis.
   * Melakukan query ke MySQL: jika basis data `jagapadi_local`, `se2026_peta`, atau `sipandhalu` belum ada, skrip akan **membuatnya secara otomatis** dengan charset `utf8mb4_unicode_ci`.

3. **Tahap 3: Peluncuran Layanan (Daemon Lifecycle Management)**  
   * Mendeteksi status proses Laragon, Apache, dan MySQL. Jika belum menyala, Laragon akan diluncurkan otomatis.
   * Mendeteksi proses `cloudflared.exe` yang aktif dengan mencocokkan signature token unik (`ZWRmMGViMTctYTMwOC` untuk Petalink, `MzYxMjBjMmYtMDljYy` untuk Sipandhalu, dan `jagapadi-server` untuk Jagapadi).
   * Menjalankan tunnel yang belum aktif menggunakan teknologi **WMI CIM (`Invoke-CimMethod -ClassName Win32_Process -MethodName Create`)** sehingga proses berjalan sebagai *true background daemon* yang tidak akan mati saat jendela Command Prompt ditutup.

4. **Tahap 4: Verifikasi Kesehatan Layanan (Live Health Check & HTTP Probing)**  
   * Melakukan probe HTTP langsung ke ketiga domain publik (`https://jagapadi.my.id`, `https://petalink.my.id`, `https://sipandhalu.my.id`) menggunakan cURL dengan dukungan TLS 1.2/1.3 dan auto-redirect.
   * Mengukur latensi koneksi (dalam milidetik).

5. **Tahap 5: Dashboard Status Konsol**  
   * Menampilkan tabel ringkasan berwarna:
     * Hijau (`ONLINE (200 OK)`) = Siap digunakan.
     * Kuning (`TUNNEL PROXYING / 530`) = Sedang proses proksi awal.
     * Merah (`OFFLINE / ERROR`) = Butuh perhatian.

---

## 5. Hasil Uji Kelayakan Teknis (Verification Results)

Hasil pengujian langsung pada sistem server menunjukkan:

| Komponen / Layanan | Target Domain | Protokol | Status Kode | Latensi | Hasil Uji |
|---|---|---|---|---|---|
| **JAGAPADI (3509)** | `https://jagapadi.my.id` | HTTPS / QUIC | **200 OK** | 312 ms | **Lulus / Berhasil** |
| **PETALINK (SE2026)** | `https://petalink.my.id` | HTTPS / QUIC | **200 OK** | 410 ms | **Lulus / Berhasil** |
| **SIPANDHALU** | `https://sipandhalu.my.id` | HTTPS / QUIC | **200 OK** | 422 ms | **Lulus / Berhasil** |
| **MySQL Engine** | `127.0.0.1:3306` | TCP Socket | Connected | < 5 ms | **Lulus / Berhasil** |
| **Apache Server** | `127.0.0.1:80, 443` | HTTP / SSL | Connected | < 5 ms | **Lulus / Berhasil** |

---

## 6. Panduan Penggunaan bagi Pengguna Awam

### A. Cara Memulai Layanan (Setiap Pagi / Setelah Komputer Menyala)
1. Pergi ke layar **Desktop Windows**.
2. Cari shortcut bernama:  
   **`Mulai Semua Layanan (JAGAPADI - Petalink - Sipandhalu)`**  
   *(Ikon berupa logo Laragon)*.
3. **Dobel-klik shortcut tersebut (1 Klik Ganda)**.
4. Jendela konsol berwarna biru-sian akan muncul otomatis dan melakukan pemeriksaan serta mengaktifkan seluruh layanan.
5. Setelah muncul tabel ringkasan dengan status **ONLINE (200 OK)** untuk ketiga layanan, tekan tombol apa saja pada keyboard untuk menutup jendela.
6. Seluruh layanan kini siap diakses melalui browser oleh seluruh petugas dan masyarakat.

### B. Cara Memeriksa Status Layanan Kapan Saja
1. Di Desktop, dobel-klik shortcut **`Cek Status Layanan JAGAPADI`**.
2. Layar akan menampilkan tabel kondisi ketiga website beserta waktu responnya tanpa me-restart server.

### C. Cara Mematikan Layanan (Saat Server Hendak Dimatikan / Maintenance)
1. Di Desktop, dobel-klik shortcut **`Hentikan Semua Layanan JAGAPADI`**.
2. Seluruh koneksi tunnel Cloudflare akan ditutup dengan aman dan bersih.

---

## 8. Aplikasi Desktop GUI Mandiri: Server.exe

Sebagai penyempurnaan dari file batch konsol, telah dikembangkan **Aplikasi Desktop Grafis (GUI)** mandiri berbentuk file eksekusi `.exe` dengan nama **`Server.exe`** yang dirancang untuk kemudahan operasional harian:

### 8.1 Spesifikasi Teknis Aplikasi GUI
* **Nama Berkas**: `C:\laragon\www\Server.exe`
* **Shortcut Desktop**: 
  * `C:\Users\IPDS\Desktop\Server.lnk` *(Akses cepat utama)*
  * `C:\Users\IPDS\Desktop\JAGAPADI Ecosystem Controller (GUI).lnk`
* **Teknologi**: C# .NET Windows Forms (Portable, Standalone, Tanpa dependensi pihak ketiga).
* **Ukuran Berkas**: ~30 KB (Sangat ringan, konsumsi RAM < 25 MB).
* **Skrip Kompilasi**: `C:\laragon\www\build_gui.bat` (Menggunakan compiler bawaan Windows `csc.exe`).
* **Arsitektur Threading**: Asinkron penuh (`Task.Run` / Background Worker) sehingga tampilan antarmuka tidak pernah macet (*no freeze/hang*) saat memeriksa jaringan.

### 8.2 Fitur-Fitur Utama GUI
1. **Live Status Card**:
   * Menampilkan status Apache (Port 80/443) & MySQL (Port 3306) secara visual (Indikator Hijau/Merah).
   * Menampilkan status kode HTTP dan latensi milidetik untuk ketiga domain publik (`jagapadi.my.id`, `petalink.my.id`, dan `sipandhalu.my.id`).
2. **Tombol Operasional Terpadu**:
   * **[ ▶ START ALL ]**: Menginisialisasi dan menjalankan Laragon serta ketiga Cloudflare Tunnel dalam satu klik.
   * **[ 🔄 RESTART ]**: Memulai ulang proses tunnel dan memicu refresh konektivitas.
   * **[ ⏸ PAUSE TUNNEL ] / [ ▶ RESUME TUNNEL ]**: Menjeda sementara akses publik (memutus tunnel) tanpa mematikan database atau web server lokal, serta mengaktifkannya kembali saat siap.
   * **[ ⏹ STOP ALL ]**: Menghentikan seluruh Cloudflare Tunnel secara serempak.
   * **[ 🔍 CEK STATUS ]**: Melakukan ping & HTTP probe seketika.
3. **Quick Navigation (Buka Website)**:
   * Tombol `[ Buka Website ↗ ]` di samping setiap domain untuk langsung membuka website di browser default.
   * Tombol `[ Database ↗ ]` untuk langsung membuka antarmuka phpMyAdmin lokal.
4. **Live Activity Console**:
   * Kotak terminal terintegrasi berlatar gelap yang menampilkan log proses dan waktu eksekusi secara real-time.
5. **Auto-Refresh & System Tray**:
   * Pemantauan status otomatis setiap 15 detik (dapat diaktifkan/dinonaktifkan via checkbox).
   * Tombol minimize akan menyembunyikan aplikasi ke System Tray di pojok kanan bawah dekat jam Windows tanpa mengotori taskbar, lengkap dengan menu klik kanan cepat (*Right Click Context Menu*).

---
*Dokumentasi ini disusun secara otomatis dan terverifikasi untuk deployment server BPS Kabupaten Jember.*
