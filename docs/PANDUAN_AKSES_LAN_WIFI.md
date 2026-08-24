# Panduan Konfigurasi Akses Web Backend JAGAPADI via Jaringan LAN dan Wi-Fi

> **JAGAPADI** — Jember Agrikultur Gapai Prestasi Digital  
> **Dokumen**: Panduan Konfigurasi Jaringan Lokal (LAN / Wi-Fi) Multi-Device  
> **Versi**: 1.0.0  
> **Tanggal Uji & Verifikasi**: 21 Agustus 2026

---

## 1. Ringkasan Arsitektur Jaringan Lokal

Agar backend web JAGAPADI dapat diakses secara stabil dari laptop lain, smartphone Android, tablet, atau perangkat IoT yang berada dalam satu jaringan lokal (LAN kabel atau Wi-Fi yang sama), sistem dikonfigurasi dengan skema berikut:

```
                  ┌──────────────────────────────────────────────┐
                  │          Router Wi-Fi / Switch LAN           │
                  │              (Gateway: 192.168.10.1)         │
                  └──────────────────────┬───────────────────────┘
                                         │
                 ┌───────────────────────┼───────────────────────┐
                 │                       │                       │
      ┌──────────▼──────────┐ ┌──────────▼──────────┐ ┌──────────▼──────────┐
      │  Host Server Laragon│ │  Smartphone Android │ │   Laptop Klien      │
      │  (192.168.10.5)     │ │  (192.168.10.X)     │ │   (192.168.10.Y)    │
      │  Apache Port 80/443 │ │  Flutter App / Web  │ │   Browser Web       │
      └─────────────────────┘ └─────────────────────┘ └─────────────────────┘
```

---

## 2. Alamat IP Host & URL Akses Resmi

### 2.1 Alamat IP Server Host
- **IP Address LAN/Wi-Fi Aktif**: `192.168.10.5`
- **Port HTTP**: `80` (Default Laragon Apache)
- **Port HTTPS**: `443`
- **Port Database MySQL/MariaDB**: `3306`

> [!TIP]
> Untuk mengetahui IP server sewaktu-waktu pada Windows PowerShell, jalankan:
> ```powershell
> Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notmatch 'Loopback|vEthernet|WSL' } | Select-Object InterfaceAlias, IPAddress
> ```

### 2.2 Daftar URL Akses dari Perangkat Klien (LAN / Wi-Fi)

| Komponen Aplikasi | URL Akses dari Browser / Klien Jaringan | Keterangan |
|---|---|---|
| **Web Utama (Root UI)** | `http://192.168.10.5/jagapadi-3509/` | Akses web dashboard, peta sebaran, dan pelaporan |
| **Halaman Login Web** | `http://192.168.10.5/jagapadi-3509/auth/login` | Login session multi-role (Petugas, Admin, Operator, dll.) |
| **API Root Internal** | `http://192.168.10.5/jagapadi-3509/api/feedback` | Endpoint API terintegrasi session |
| **Backend v1 Health** | `http://192.168.10.5/jagapadi-3509/backend/public/api/v1/health` | Indikator status kesehatan backend REST API |
| **Backend v1 REST API** | `http://192.168.10.5/jagapadi-3509/backend/public/api/v1/` | Base URL REST API untuk Flutter Android |

---

## 3. Langkah-Langkah Konfigurasi Server

### 3.1 Konfigurasi Web Server Apache (Laragon)
Apache di Laragon secara default telah mengikat (*binding*) pada `0.0.0.0:80` (seluruh kartu jaringan). Pastikan konfigurasi virtual host pada `C:\laragon\etc\apache2\sites-enabled\00-default.conf` mengizinkan akses ke direktori `www`:

```apache
<VirtualHost _default_:80>
    <Directory "C:/laragon/www">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 3.2 Penanganan Dinamis Base URL & CORS (Telah Diterapkan)
1. **Dynamic Base URL** ([`index.php`](file:///c:/laragon/www/jagapadi-3509/index.php)): Sistem otomatis membaca `$_SERVER['HTTP_HOST']` sehingga aset CSS, JS, font, gambar, dan tautan navigasi otomatis mengarah ke `http://192.168.10.5/jagapadi-3509/` tanpa terjadi *broken links*.
2. **CORS Subnet Privat** ([`index.php`](file:///c:/laragon/www/jagapadi-3509/index.php) & [`backend/public/index.php`](file:///c:/laragon/www/jagapadi-3509/backend/public/index.php)): Sistem otomatis mengizinkan *Cross-Origin Resource Sharing* dari semua IP privat lokal (`192.168.x.x`, `10.x.x.x`, `172.16-31.x.x`) sehingga aplikasi web/PWA/mobile yang berjalan pada port atau perangkat berbeda di jaringan yang sama tidak diblokir oleh browser.
3. **Normalisasi Subfolder URI** ([`backend/public/index.php`](file:///c:/laragon/www/jagapadi-3509/backend/public/index.php)): Router API Backend v1 otomatis menormalkan prefix subfolder saat diakses melalui path Apache `/jagapadi-3509/backend/public/`.

---

## 4. Konfigurasi Windows Defender Firewall

Agar perangkat lain dapat menghubungi port 80 pada komputer hosting, aturan *Inbound Rule* harus mengizinkan lalu lintas Apache.

### 4.1 Verifikasi Aturan Firewall
Buka PowerShell (Administrator) dan jalankan:
```powershell
Get-NetFirewallRule -DisplayName "*Apache*" | Select-Object DisplayName, Direction, Action, Enabled, Profile
```

### 4.2 Perintah Membuka Port 80 & 443 (Jika Belum Terbuka)
Jika perangkat lain belum dapat terhubung, tambahkan aturan firewall dengan perintah PowerShell berikut (Jalankan sebagai Administrator):
```powershell
New-NetFirewallRule -DisplayName "Laragon Apache Web Server (HTTP/HTTPS)" -Direction Inbound -LocalPort 80,443 -Protocol TCP -Action Allow -Profile Any -Enabled True
```

---

## 5. Konfigurasi Aplikasi Mobile Flutter (Android)

Untuk menjalankan atau membangun aplikasi Flutter agar terhubung ke server LAN:

### 5.1 Menjalankan Aplikasi pada Perangkat Fisik (Wi-Fi)
Hubungkan HP ke Wi-Fi yang sama dengan komputer host, lalu jalankan:
```powershell
cd c:\laragon\www\jagapadi-3509\mobile
flutter run --dart-define=API_BASE_URL=http://192.168.10.5/jagapadi-3509/backend/public/api/v1
```

### 5.2 Membangun APK Rilis
```powershell
cd c:\laragon\www\jagapadi-3509\mobile
flutter build apk --release --dart-define=API_BASE_URL=http://192.168.10.5/jagapadi-3509/backend/public/api/v1
```

---

## 6. Hasil Pengujian & Benchmark Latency

Pengujian koneksi via IP LAN `192.168.10.5` menunjukkan performa yang sangat stabil dan cepat:

| Endpoint Uji | Metode | Status HTTP | Latency Rata-rata | Ukuran Response |
|---|---|---|---|---|
| `/jagapadi-3509/` | GET | **200 OK** | **16 – 20 ms** | 7.270 bytes |
| `/jagapadi-3509/auth/login` | GET | **200 OK** | **10 – 14 ms** | 7.270 bytes |
| `/public/vendor/css/adminlte.min.css` | GET | **200 OK** | **3 – 7 ms** | 1.397.681 bytes |
| `/public/vendor/js/jquery-3.6.0.min.js`| GET | **200 OK** | **1 – 2 ms** | 90.446 bytes |
| `/backend/public/api/v1/health` | GET | **200 OK** | **40 – 50 ms** | 1.764 bytes |
| `/backend/public/api/v1/auth/login` | POST | **302 Redirect** | **60 – 90 ms** | - |

---

## 7. Troubleshooting & Solusi Kendala Akses

| Gejala Kendala | Kemungkinan Penyebab | Langkah Solusi |
|---|---|---|
| **HP / Laptop lain tidak bisa membuka `http://192.168.10.5`** | *AP Isolation* aktif pada Router Wi-Fi | Matikan fitur *AP Isolation / Client Isolation* pada pengaturan Router Wi-Fi agar sesama perangkat Wi-Fi dapat saling berkomunikasi. |
| **Koneksi `Connection Timed Out`** | Profil jaringan Windows terdeteksi *Public* tanpa aturan firewall | Ubah profil jaringan ke *Private* atau jalankan perintah `New-NetFirewallRule` pada [Bagian 4.2](#42-perintah-membuka-port-80--443-jika-belum-terbuka). |
| **IP Komputer Host Berubah saat Router Restart** | Alokasi DHCP dinamis | Atur *DHCP Static Reservation* pada router untuk MAC Address komputer host agar IP selalu tetap `192.168.10.5`. |
| **Tampilan CSS/JS tidak termuat di HP klien** | Penggunaan hardcoded `localhost` di template | Sistem JAGAPADI telah diperbarui menggunakan `BASE_URL` dinamis otomatis berbasis `$_SERVER['HTTP_HOST']`. |
