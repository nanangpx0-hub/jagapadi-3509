# Panduan Operasional: Sinkronisasi Media JAGAPADI via Rclone (Pull Model)

Dokumen ini berisi panduan komprehensif implementasi **Solusi 1: Rclone via SFTP/FTPS (Pull Model)** untuk melakukan pencadangan (*backup*) dan sinkronisasi berkas media (foto laporan hama OPT, foto irigasi, usulan OPT, dan lampiran lainnya) dari hosting cPanel (`jagapadi.bpsjember.my.id`) ke lingkungan lokal Laragon Windows.

---

## 1. Arsitektur Solusi: Pull Model

```
+------------------------------------+             +------------------------------------+
|       Hosting cPanel Production    |             |       Workstation / Server Lokal   |
|     (jagapadi.bpsjember.my.id)     |             |       (Laragon Windows 11/10)      |
|                                    |             |                                    |
|   /public_html/public/uploads/     |  SFTP/FTPS  |   C:\laragon\www\jagapadi-3509\    |
|   ├── opt/                         |  (Pull:     |   └── public/uploads/              |
|   ├── irigasi/                     |   Port 22   |       ├── opt/                     |
|   ├── laporan/                     |   atau 21)  |       ├── irigasi/                 |
|   ├── feedback/                    | ==========> |       ├── laporan/                 |
|   └── usulan-opt/                  |             |       ├── feedback/                |
|                                    |             |       └── usulan-opt/              |
+------------------------------------+             +------------------------------------+
                                                             ^
                                                             | (Scheduled Task / CLI)
                                                   +-------------------+
                                                   | scripts/          |
                                                   | sync_media_rclone |
                                                   +-------------------+
```

### Mengapa Memilih Pull Model?
1. **Keamanan Workstation Lokal (Zero Inbound Exposure):**
   Server lokal di kantor BPS berada di balik NAT dan firewall tanpa IP publik statis. Dengan Pull Model, komputer lokal bertindak sebagai *client* yang melakukan *outbound request*, sehingga tidak perlu membuka port atau membuat konfigurasi port-forwarding yang berisiko.
2. **Isolasi Kredensial (Least Privilege):**
   Kredensial server lokal tidak pernah disimpan di server hosting cPanel. Jika suatu saat server hosting mengalami insiden, penyerang tidak memiliki akses apa pun ke infrastruktur lokal BPS.
3. **Efisiensi Resource Hosting:**
   Shared hosting cPanel memiliki batasan ketat (*throttling* CloudLinux LVE seperti batasan CPU, IOPS, dan proses EP). Eksekusi perbandingan file dan enkripsi ditangani oleh prosesor lokal, sehingga server cPanel tidak terbebani.
4. **Idempoten & Hemat Bandwidth:**
   Rclone menggunakan flag `--update` dan `--use-mtime` sehingga hanya mengunduh file baru atau file yang waktu modifikasinya lebih baru. File yang sudah identik tidak akan diunduh ulang.

---

## 2. Persiapan di cPanel Hosting

### Opsi A: SFTP (SSH File Transfer Protocol) — *Sangat Direkomendasikan*
SFTP berjalan di atas SSH (biasanya port `22` atau port kustom hosting) dengan enkripsi penuh.

1. Masuk ke **cPanel** `https://jagapadi.bpsjember.my.id:2083`.
2. Di bagian **Security**, klik **SSH Access**.
3. Pastikan status SSH aktif untuk akun cPanel Anda.
4. Catat:
   - **Host:** `jagapadi.bpsjember.my.id` (atau IP server hosting).
   - **Port:** `22` (hubungi penyedia hosting jika menggunakan port SSH alternatif, misal `2222`).
   - **Username:** Username login cPanel Anda.
   - **Password:** Password cPanel Anda.
5. Catat direktori absolut folder uploads cPanel:
   - Umumnya: `/home/{username}/public_html/public/uploads`
   - Atau relative path: `public_html/public/uploads`

---

### Opsi B: FTPS (FTP over Explicit TLS) — *Alternatif jika SSH Dinonaktifkan*
Gunakan FTPS jika paket hosting Anda tidak menyediakan akses SSH/SFTP.

1. Di cPanel, buka menu **FTP Accounts**.
2. Buat akun FTP khusus, misalnya:
   - **Log in:** `backup_media` (menjadi `backup_media@jagapadi.bpsjember.my.id`).
   - **Password:** Gunakan generator password yang kuat.
   - **Directory:** Arahkan ke `public_html/public/uploads` atau `public_html`.
3. Klik **Create FTP Account**.
4. Catat:
   - **Host:** `jagapadi.bpsjember.my.id`
   - **Port:** `21`
   - **Username:** `backup_media@jagapadi.bpsjember.my.id`
   - **TLS Mode:** Wajib Explicit FTPS (`tls = true`).

---

## 3. Instalasi & Setup Konfigurasi di Laragon Windows

### Langkah 1: Instalasi Binary Rclone
Proyek JAGAPADI telah menyediakan skrip otomatisasi pemasangan rclone:

Buka PowerShell di direktori proyek JAGAPADI, lalu jalankan:
```powershell
powershell -ExecutionPolicy Bypass -File scripts\ensure_rclone.ps1
```
Skrip ini akan:
- Mengunduh binary resmi `rclone.exe` Windows amd64.
- Memasangnya langsung ke `C:\laragon\bin\rclone.exe`.
- Menambahkan `C:\laragon\bin` ke User PATH Windows.

*Verifikasi instalasi:*
```powershell
rclone version
```

---

### Langkah 2: Buat Profil Koneksi (`config/rclone.conf`)

1. Salin file template:
   ```powershell
   Copy-Item config\rclone.conf.example config\rclone.conf
   ```
2. Enkripsi password akun hosting Anda menggunakan utility `rclone obscure`:
   ```powershell
   rclone obscure "PasswordAsliAkunHostingAnda"
   ```
   *Contoh output:* `a1b2c3d4e5f6g7h8...` (string acak terenkripsi).
3. Buka `config/rclone.conf` dengan text editor (VS Code, Notepad, dll).
4. Masukkan string hasil enkripsi ke opsi `pass = ...`:
   ```ini
   [cpanel_sftp]
   type = sftp
   host = jagapadi.bpsjember.my.id
   user = jagapadi_user
   port = 22
   pass = a1b2c3d4e5f6g7h8...
   use_insecure_cipher = false
   md5sum_command = md5sum
   sha1sum_command = sha1sum
   shell_type = unix
   ```
5. Simpan file `config/rclone.conf`.

> [!WARNING]
> **JANGAN PERNAH** meng-commit berkas `config/rclone.conf` ke repositori Git. Berkas ini telah didaftarkan dalam `.gitignore` demi keamanan kredensial hosting.

---

### Langkah 3: Setup File Environment Backup (`scripts/.env.backup`)

1. Salin file template:
   ```powershell
   Copy-Item scripts\.env.backup.example scripts\.env.backup
   ```
2. Sesuaikan nilai variabel sesuai lingkungan hosting Anda:
   ```ini
   RCLONE_EXE_PATH=C:\laragon\bin\rclone.exe
   RCLONE_CONFIG_PATH=config/rclone.conf
   REMOTE_NAME=cpanel_sftp
   REMOTE_UPLOADS_PATH=/home/jagapadi/public_html/public/uploads
   LOCAL_UPLOADS_PATH=public/uploads
   SYNC_MODE=copy
   BANDWIDTH_LIMIT=10M
   LOG_LEVEL=INFO
   LOG_FILE=storage/logs/rclone_media_sync.log
   TRANSFERS=4
   CHECKERS=8
   RETRIES=3
   ```

---

## 4. Eksekusi Sinkronisasi Manual

Tersedia dua metode eksekusi manual: via **PowerShell** dan via **PHP CLI**.

### Metode 1: Menggunakan PowerShell Script (`scripts/sync_media_rclone.ps1`)

Skrip PowerShell ini dilengkapi penanganan warna konsol, validasi pre-flight, dan logging otomatis.

*1. Uji Coba Simulasi (Dry-Run — Tidak Mengunduh/Mengubah Berkas):*
```powershell
powershell -File scripts\sync_media_rclone.ps1 -DryRun
```

*2. Jalankan Sinkronisasi Penuh (Default Mode: `copy`):*
```powershell
powershell -File scripts\sync_media_rclone.ps1
```

*3. Menjalankan dengan Batas Bandwidth Tertentu (misal 2 MB/s):*
```powershell
powershell -File scripts\sync_media_rclone.ps1 -BandwidthLimit 2M
```

*4. Menjalankan Menggunakan Profil FTP:*
```powershell
powershell -File scripts\sync_media_rclone.ps1 -RemoteName cpanel_ftp -RemotePath /public/uploads
```

---

### Metode 2: Menggunakan PHP CLI Script (`scripts/sync_media_rclone.php`)

Skrip PHP memanfaatkan `MediaSyncService` yang terintegrasi dengan arsitektur JAGAPADI.

*1. Simulasi Dry-Run:*
```powershell
php scripts/sync_media_rclone.php --dry-run
```

*2. Eksekusi Sinkronisasi Standar:*
```powershell
php scripts/sync_media_rclone.php
```

*3. Mode Detail (Verbose Output):*
```powershell
php scripts/sync_media_rclone.php --verbose
```

*4. Melihat Opsi Bantuan:*
```powershell
php scripts/sync_media_rclone.php --help
```

---

## 5. Otomasi Penjadwalan: Windows Task Scheduler

Agar pencadangan media berjalan otomatis tanpa intervensi manual, daftarkan tugas harian di Windows Task Scheduler. Direkomendasikan dijalankan setiap malam pukul **02:00 WIB** saat beban jaringan rendah.

### Opsi A: Pendaftaran Otomatis via PowerShell (Direkomendasikan)
Buka PowerShell dengan hak akses **Administrator**, lalu salin perintah berikut:

```powershell
$ProjectDir = "C:\laragon\www\jagapadi-3509"
$ScriptPath = "$ProjectDir\scripts\sync_media_rclone.ps1"

$Action = New-ScheduledTaskAction `
    -Execute "PowerShell.exe" `
    -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -File `"$ScriptPath`"" `
    -WorkingDirectory $ProjectDir

$Trigger = New-ScheduledTaskTrigger -Daily -At "02:00AM"

$Principal = New-ScheduledTaskPrincipal `
    -UserId "$env:USERDOMAIN\$env:USERNAME" `
    -LogonType S4U `
    -RunLevel Highest

$Settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Hours 2)

Register-ScheduledTask `
    -TaskName "JAGAPADI_Media_Sync_Daily" `
    -Action $Action `
    -Trigger $Trigger `
    -Principal $Principal `
    -Settings $Settings `
    -Description "Pencadangan berkala media uploads JAGAPADI dari cPanel hosting ke Laragon (Pull Model)" -Force

Write-Host "[OK] Task JAGAPADI_Media_Sync_Daily berhasil didaftarkan!" -ForegroundColor Green
```

### Opsi B: Pendaftaran Manual via Antarmuka Grafis (`taskschd.msc`)
1. Tekan tombol `Win + R`, ketik `taskschd.msc`, lalu tekan **Enter**.
2. Pada panel kanan, klik **Create Task...** (bukan Create Basic Task).
3. Tab **General**:
   - Name: `JAGAPADI_Media_Sync_Daily`
   - Pilih: *Run whether user is logged on or not* (atau *Run only when user is logged on* jika menggunakan akun lokal).
   - Centang: *Run with highest privileges*.
4. Tab **Triggers**:
   - Klik **New...**
   - Begin the task: *On a schedule* -> *Daily*.
   - Start: Atur jam `02:00:00`.
   - Recur every: `1` days.
   - Klik **OK**.
5. Tab **Actions**:
   - Klik **New...**
   - Action: *Start a program*.
   - Program/script: `powershell.exe`
   - Add arguments: `-ExecutionPolicy Bypass -WindowStyle Hidden -File "C:\laragon\www\jagapadi-3509\scripts\sync_media_rclone.ps1"`
   - Start in: `C:\laragon\www\jagapadi-3509`
   - Klik **OK**.
6. Tab **Settings**:
   - Centang: *Run task as soon as possible after a scheduled start is missed*.
   - Stop the task if it runs longer than: `2 hours`.
7. Klik **OK** dan masukkan password akun Windows Anda jika diminta.

---

## 6. Pemantauan & Analisis Log

Seluruh aktivitas transfer dicatat di berkas:
```
storage/logs/rclone_media_sync.log
```

Untuk melihat 20 baris catatan log terakhir secara *real-time*:
```powershell
Get-Content storage\logs\rclone_media_sync.log -Tail 20 -Wait
```

Contoh keluaran log sukses:
```
2026/09/17 02:00:01 INFO  : Starting media pull sync...
2026/09/17 02:00:05 INFO  : opt/hama_wereng_123.jpg: Copied (new)
2026/09/17 02:00:06 INFO  : irigasi/saluran_456.jpg: Copied (new)
2026/09/17 02:00:10 NOTICE: 2.345 MiB / 2.345 MiB, 100%, 450.12 KiB/s, ETA 0s (xfr#2/2, chk#148/148)
2026/09/17 02:00:10 INFO  : There was nothing to transfer
```

---

## 7. Troubleshooting & FAQ

### 1. Error: `ssh: handshake failed: ssh: unable to authenticate`
- **Penyebab:** Password cPanel salah atau format hasil enkripsi `rclone obscure` tidak cocok.
- **Solusi:** Jalankan kembali `rclone obscure "password_baru"` dan perbarui nilai `pass = ...` di `config/rclone.conf`.

### 2. Error: `directory not found` pada remote
- **Penyebab:** Path folder uploads di cPanel tidak sesuai dengan struktur akun hosting.
- **Solusi:**
  - Cek apakah direktori root cPanel Anda menggunakan format `/home/namauser/public_html/public/uploads` atau `/public_html/public/uploads`.
  - Anda dapat menguji daftar folder di remote menggunakan perintah:
    ```powershell
    rclone lsd cpanel_sftp:/ --config=config/rclone.conf
    ```

### 3. Error: `connection refused` pada Port 22
- **Penyebab:** Akses SSH dinonaktifkan oleh penyedia hosting, atau port SSH diubah (misal port `2222` atau `2200`).
- **Solusi:**
  - Tanyakan kepada administrator/penyedia hosting port SSH yang aktif.
  - Alternatif: Gunakan profil `cpanel_ftp` (port 21 dengan TLS) yang telah disediakan di template `config/rclone.conf.example`.

### 4. Perbedaan Mode `copy` vs `sync`
- **Mode `copy` (Direkomendasikan):**
  Hanya menyalin file baru dan file yang diperbarui dari remote ke lokal. Jika petugas menghapus foto di cPanel, salinan di lokal **tetap aman dan tidak terhapus**. Sangat cocok untuk tujuan backup/arsip historis.
- **Mode `sync` (Hati-hati):**
  Menyamakan persis isi lokal dengan remote. Jika file di remote cPanel terhapus, maka file di lokal **akan ikut terhapus secara permanen**. Hanya gunakan jika Anda memang memerlukan replikasi 1:1 yang ketat.

### 5. Penggunaan Bandwidth yang Terlalu Besar
- **Solusi:** Tambahkan parameter `-BandwidthLimit 5M` pada PowerShell atau `--limit=5M` pada PHP CLI untuk membatasi kecepatan download maksimal menjadi 5 MB/detik, sehingga tidak mengganggu bandwidth internet operasional kantor.
