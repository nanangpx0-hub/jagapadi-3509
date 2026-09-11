# Panduan Operasional & Dokumentasi Hosting Server Lokal JAGAPADI via Cloudflare Tunnel

> **Tanggal Konfigurasi:** 29 Agustus 2026  
> **Domain Utama:** [https://jagapadi.my.id](https://jagapadi.my.id)  
> **Domain Alternatif:** [https://www.jagapadi.my.id](https://www.jagapadi.my.id)  
> **Status:** Online & Produksi

---

## 1. Ringkasan Arsitektur & Spesifikasi

Server online JAGAPADI dijalankan langsung dari PC server lokal dengan memanfaatkan teknologi **Cloudflare Tunnel (Argo Tunnel)**. Dengan metode ini, server lokal dapat diakses secara publik dengan aman tanpa memerlukan IP Publik Statis, tanpa membuka port (*port forwarding*) di router/modem ISP, dan kebal terhadap CGNAT.

### Spesifikasi Infrastruktur:
- **Perangkat Keras:** Intel(R) Xeon(R) CPU E3-1220 v6 @ 3.00GHz (4 Cores), RAM 32 GB DDR4.
- **Sistem Operasi:** Microsoft Windows 11 Pro 64-bit.
- **Web Server & Stack:** Laragon (Apache 2.4.54, PHP 8.2.32, MySQL 8.0.30).
- **Domain Registrar:** DomaiNesia (`jagapadi.my.id`).
- **DNS & Proxy Manager:** Cloudflare Free Plan (Nameservers: `abby.ns.cloudflare.com` & `camilo.ns.cloudflare.com`).
- **Tunnel Client:** `cloudflared` v2026.8.2 (64-bit).

---

## 2. Diagram Alur Koneksi

```mermaid
flowchart LR
    subgraph Klien["Pengguna & Aplikasi"]
        User["Browser / Petugas"]
        App["Aplikasi Android (Flutter)"]
    end

    subgraph Cloudflare["Cloudflare Global Network"]
        CF_Edge["Cloudflare Edge (SSL / WAF / DDoS Protection)"]
        CF_DNS["DNS Anycast (jagapadi.my.id)"]
    end

    subgraph Server_Lokal["PC Server Lokal (Intel Xeon)"]
        CFTunnel["Cloudflared Tunnel (Background Service)"]
        Apache["Apache 2.4 (Port 80)"]
        PHP["PHP 8.2 (JAGAPADI App)"]
        MySQL[("MySQL 8.0 Database")]
    end

    User -->|HTTPS| CF_Edge
    App -->|HTTPS API v1| CF_Edge
    CF_Edge <==>|Terkoneksi Aman via QUIC/HTTP2| CFTunnel
    CFTunnel -->|Internal HTTP| Apache
    Apache --> PHP
    PHP --> MySQL
```

---

## 3. Detail Konfigurasi Teknis

### A. Konfigurasi Cloudflare Tunnel
- **Nama Tunnel:** `jagapadi-server`
- **Tunnel UUID:** `168ca50f-e89f-4c18-8d70-c5427121dbe6`
- **Lokasi File Konfigurasi:** `C:\Users\IPDS\.cloudflared\config.yml`
- **Lokasi Kredensial:** `C:\Users\IPDS\.cloudflared\168ca50f-e89f-4c18-8d70-c5427121dbe6.json`
- **Lokasi Sertifikat Asal:** `C:\Users\IPDS\.cloudflared\cert.pem`

**Isi file `config.yml`:**
```yaml
tunnel: 168ca50f-e89f-4c18-8d70-c5427121dbe6
credentials-file: C:\Users\IPDS\.cloudflared\168ca50f-e89f-4c18-8d70-c5427121dbe6.json

ingress:
  - hostname: jagapadi.my.id
    service: http://127.0.0.1:80
  - hostname: www.jagapadi.my.id
    service: http://127.0.0.1:80
  - service: http_status:404
```

### B. Konfigurasi VirtualHost Apache (Laragon)
- **File:** `C:\laragon\etc\apache2\sites-enabled\auto.jagapadi-3509.test.conf`
- **Isi Konfigurasi:**
```apache
<VirtualHost *:80> 
    DocumentRoot "C:/laragon/www/jagapadi-3509"
    ServerName jagapadi-3509.test
    ServerAlias *.jagapadi-3509.test jagapadi.my.id *.jagapadi.my.id
    <Directory "C:/laragon/www/jagapadi-3509">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### C. Penyesuaian `.htaccess`
- **File:** `C:\laragon\www\jagapadi-3509\.htaccess`
- Aturan redirect HTTPS disesuaikan untuk membaca header `X-Forwarded-Proto` dari Cloudflare:
```apache
RewriteCond %{HTTPS} off
RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]
RewriteCond %{HTTP_HOST} !^localhost$ [NC]
RewriteCond %{HTTP_HOST} !^127\.0\.0\.1$ [NC]
RewriteCond %{HTTP_HOST} !^10\. [NC]
RewriteCond %{HTTP_HOST} !^192\.168\. [NC]
RewriteCond %{HTTP_HOST} !^172\.(1[6-9]|2[0-9]|3[0-1])\. [NC]
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### D. Mekanisme Auto-Start (Otomatis Aktif Saat PC Dinyalakan)
- **Lokasi Skrip:** `C:\Users\IPDS\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup\start_cloudflare_tunnel.vbs`
- Skrip ini akan otomatis dijalankan oleh Windows saat user login, memicu `cloudflared` berjalan di latar belakang tanpa memunculkan jendela hitam terminal.

---

## 4. Prosedur Operasional Standar (SOP) & Perawatan

### 1. Menghidupkan Server
1. Nyalakan PC Server.
2. Login ke Windows.
3. Laragon akan otomatis berjalan (atau buka Laragon dan klik **"Start All"** jika belum diset auto-start).
4. Cloudflare Tunnel otomatis berjalan di latar belakang.
5. Domain `https://jagapadi.my.id` langsung online.

### 2. Memeriksa Status Server & Tunnel
Buka PowerShell dan jalankan:
```powershell
# Cek apakah proses cloudflared sedang berjalan
Get-Process cloudflared -ErrorAction SilentlyContinue

# Uji respons domain dari server lokal
curl.exe -I https://jagapadi.my.id
```
Jika respons menghasilkan `HTTP/1.1 200 OK`, server bekerja dengan sempurna.

### 3. Menjalankan / Menghentikan Tunnel Manual (Opsional)
Jika sewaktu-waktu ingin menjalankan tunnel secara manual di jendela terminal:
```powershell
# Hentikan proses latar belakang
Stop-Process -Name cloudflared -Force

# Jalankan manual untuk memantau trafik langsung
& "C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --config "C:\Users\IPDS\.cloudflared\config.yml" run jagapadi-server
```

---

## 5. Panduan Keamanan & Rekomendasi Tambahan

1. **UPS (Baterai Cadangan):**
   Pastikan PC Server terhubung ke UPS agar tidak mati mendadak saat listrik padam.
2. **Pengaturan BIOS (Auto Power On):**
   Masuk ke BIOS PC (tekan `DEL` / `F2` saat menyalakan PC), cari menu **Power Management** dan aktifkan `AC Back` / `Restore on AC Power Loss` ke status **Power On / Always On**. Hal ini membuat PC otomatis hidup kembali ketika listrik menyala setelah padam.
3. **Windows Sleep & Update:**
   - Masuk ke *Settings > System > Power & battery* -> Set **Screen and sleep** ke **Never**.
   - Atur jadwal restart Windows Update di luar jam kerja aktif.
4. **Cadangan Database Rutin (Backup):**
   Lakukan ekspor rutin database `jagapadi_db` melalui HeidiSQL atau buat cron job cadangan berkala ke drive penyimpanan aman.

---

## 6. Kontak & Akses Penting
- **URL Web Utama:** [https://jagapadi.my.id](https://jagapadi.my.id)
- **URL Alternatif:** [https://www.jagapadi.my.id](https://www.jagapadi.my.id)
- **Endpoint API Mobile:** `https://jagapadi.my.id/api/v1`
- **Dashboard Cloudflare:** [dash.cloudflare.com](https://dash.cloudflare.com)
- **Registrar DomaiNesia:** [client.domainesia.com](https://client.domainesia.com)
