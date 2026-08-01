# Panduan Pengguna JAGAPADI

> **J**ember **A**grikultur **G**apai **P**restasi **D**igital
> Sistem Pelaporan Pertanian untuk Kabupaten Jember

---

## Daftar Isi

1. [Tentang JAGAPADI](#1-tentang-jagapadi)
2. [Persiapan Awal](#2-persiapan-awal)
3. [Akses Web (Admin & Petugas)](#3-akses-web-admin--petugas)
   - 3.1 Login Web
   - 3.2 Dashboard
   - 3.3 Ubah Password (Wajib)
   - 3.4 Navigasi Utama Web
4. [Aplikasi Android (Petugas Lapangan)](#4-aplikasi-android-petugas-lapangan)
   - 4.1 Install Aplikasi
   - 4.2 Login Aplikasi
   - 4.3 Halaman Utama
   - 4.4 Ubah Password di Aplikasi
5. [Fitur Laporan Hama/OPT](#5-fitur-laporan-hamaopt)
   - 5.1 Membuat Laporan Baru
   - 5.2 Menyimpan Draft
   - 5.3 Mengirim Laporan
   - 5.4 Melihat Detail Laporan
   - 5.5 Mengedit Draft
6. [Fitur Laporan Irigasi](#6-fitur-laporan-irigasi)
   - 6.1 Membuat Laporan Irigasi
   - 6.2 Field Khusus Irigasi
7. [Fitur Verifikasi (Admin)](#7-fitur-verifikasi-admin)
   - 7.1 Melihat Antrian
   - 7.2 Memverifikasi Laporan
   - 7.3 Menolak Laporan
   - 7.4 Mengarsipkan
8. [Notifikasi](#8-notifikasi)
   - 8.1 Notifikasi di Web
   - 8.2 Notifikasi di Aplikasi
9. [Ekspor Data](#9-ekspor-data)
10. [Pemecahan Masalah Umum](#10-pemecahan-masalah-umum)
11. [Tips Keamanan](#11-tips-keamanan)

---

## 1. Tentang JAGAPADI

JAGAPADI adalah sistem pelaporan pertanian untuk Wilayah Kabupaten Jember. Dua jenis laporan yang dapat dibuat:

| Jenis Laporan | Contoh Isi |
|--------------|------------|
| **Hama/OPT** | Serangan hama wereng, tikus, ulat, dll. |
| **Irigasi** | Saluran rusak, debit air berkurang, banjir, dll. |

**Dua peran pengguna:**

| Peran | Tugas | Platform |
|-------|-------|----------|
| **Petugas Lapangan** | Membuat & mengirim laporan dari lapangan | Aplikasi Android + Web |
| **Admin/Verifikator** | Memeriksa & memverifikasi laporan | Web |

---

## 2. Persiapan Awal

### Yang Anda Butuhkan

| Untuk | Kebutuhan |
|-------|-----------|
| **Web** | Browser (Chrome, Firefox, Edge) + Koneksi internet + Akun JAGAPADI |
| **Android** | Smartphone Android + Koneksi internet + Akun JAGAPADI |

### Dapatkan Akun

Akun JAGAPADI dibuat oleh **Admin Dinas**. Anda akan menerima:
- **Username** (nama pengguna)
- **Password sementara**
- **Role** (Petugas atau Admin)

> Hubungi Admin Dinas jika belum memiliki akun.

---

## 3. Akses Web (Admin & Petugas)

### 3.1 Login Web

**Langkah-langkah:**

1. Buka browser (Chrome/Firefox/Edge)
2. Ketik alamat web JAGAPADI:
   ```
   https://jagapadi.example.go.id
   ```
3. Halaman login akan tampil:

   *[Ilustrasi: Halaman Login Web]*

   | Field | Cara Isi |
   |-------|----------|
   | **Username** | Ketik username yang diberikan Admin |
   | **Password** | Ketik password sementara |

4. Klik tombol **Login**

5. **Berhasil?** Anda akan masuk ke halaman Dashboard.

6. **Gagal?** Akan muncul pesan error seperti "Username atau password salah". Hubungi Admin jika lupa password.

> **Tips:** Jika pertama kali login, Anda akan diminta mengubah password (lihat bagian 3.3).

### 3.2 Dashboard

Setelah login, halaman Dashboard akan menampilkan:

*[Ilustrasi: Halaman Dashboard Web]*

| Elemen | Fungsi |
|--------|--------|
| **Kartu Statistik** | Jumlah laporan aktif, menunggu verifikasi, ditolak |
| **Grafik Bulanan** | Tren laporan per bulan (Chart.js) |
| **Peta** | Sebaran laporan di wilayah Jember (Leaflet) |
| **Menu Navigasi** | Sidebar atau navbar untuk akses fitur lain |

### 3.3 Ubah Password (Wajib)

Saat **pertama kali login** atau jika **Admin meminta perubahan password**:

1. Setelah login, Anda akan otomatis diarahkan ke halaman **Ubah Password**
2. Isi field berikut:

   *[Ilustrasi: Form Ubah Password]*

   | Field | Cara Isi |
   |-------|----------|
   | **Password Saat Ini** | Password yang baru saja dipakai login |
   | **Password Baru** | Minimal 8 karakter, kombinasi huruf besar, huruf kecil, angka, dan simbol. Contoh: `Jagapadi2024!` |
   | **Konfirmasi Password** | Ketik ulang password baru |

3. Klik **Simpan**
4. Jika sukses, Anda akan login ulang secara otomatis

> **Penting:** Simpan password baru Anda di tempat aman. Jangan bagikan ke siapa pun.

### 3.4 Navigasi Utama Web

| Menu | Untuk |
|------|-------|
| **Dashboard** | Melihat statistik, grafik, dan peta |
| **Laporan Hama** | Membuat, melihat, dan mengelola laporan Hama/OPT |
| **Laporan Irigasi** | Membuat, melihat, dan mengelola laporan Irigasi |
| **Ekspor** | Mengunduh data laporan dalam format CSV atau Excel |
| **Notifikasi** | Melihat pemberitahuan (bell icon di pojok kanan atas) |
| **Profil** | Mengubah password dan logout |

---

## 4. Aplikasi Android (Petugas Lapangan)

### 4.1 Install Aplikasi

**Cara 1 — Install dari file APK (yang diterima dari Admin):**

1. Buka file APK yang diterima (via WhatsApp / link download)
2. Klik **Install** (jika muncul peringatan "Install from unknown sources", izinkan)
3. Tunggu hingga instalasi selesai
4. Aplikasi JAGAPADI siap digunakan

**Cara 2 — Play Store (jika tersedia):**

1. Buka Google Play Store
2. Cari "JAGAPADI"
3. Klik **Install**

### 4.2 Login Aplikasi

1. Buka aplikasi JAGAPADI

   *[Ilustrasi: Halaman Login Aplikasi]*

2. Masukkan **Username** dan **Password**
3. Klik tombol **Masuk**
4. Jika berhasil, Anda masuk ke halaman utama

   *[Ilustrasi: Halaman Utama Aplikasi]*

### 4.3 Halaman Utama

Menu utama di aplikasi:

| Card Menu | Fungsi |
|-----------|--------|
| **Antrian Verifikasi** *(hanya Admin)* | Lihat laporan yang menunggu verifikasi |
| **Laporan Hama** | Buat & kirim laporan hama/OPT |
| **Laporan Irigasi** | Buat & kirim laporan irigasi |
| **Notifikasi** | Lihat pemberitahuan terbaru |
| **Profil** | Ubah password & logout |

### 4.4 Ubah Password di Aplikasi

Jika saat login muncul pesan "Silakan ubah password terlebih dahulu":

1. Anda akan otomatis dibawa ke halaman **Profil**
2. Isi form Ubah Password:
   - **Password Saat Ini**
   - **Password Baru** (min 8 karakter, kombinasi huruf besar, kecil, angka, simbol)
   - **Konfirmasi Password Baru**
3. Klik **Simpan**
4. Setelah berhasil, Anda akan masuk ke halaman utama

---

## 5. Fitur Laporan Hama/OPT

### 5.1 Membuat Laporan Baru

**Web:**
1. Klik menu **Laporan Hama** di navigasi
2. Klik tombol **Buat Laporan** atau **+**
3. Isi formulir laporan

*[Ilustrasi: Form Laporan Hama]*

**Aplikasi Android:**
1. Dari halaman utama, ketuk card **Laporan Hama**
2. Ketuk tombol **+** di pojok kanan atas
3. Isi formulir laporan

### 5.2 Field Wajib Laporan

| Field | Penjelasan | Contoh |
|-------|-----------|--------|
| **Tanggal** | Tanggal kejadian | `16-07-2026` |
| **OPT** | Jenis hama yang menyerang | Wereng Batang Coklat |
| **Tingkat Keparahan** | Ringan/Sedang/Berat | Ringan |
| **Luas Serangan** | Luas area terkena (hektar) | `0.5` |
| **Populasi** | Perkiraan populasi hama | `10` |
| **Kabupaten** | Wilayah kejadian | Jember |
| **Kecamatan** | Kecamatan lokasi | Kaliwates |
| **Desa** | Desa lokasi | Kepatihan |
| **Lokasi** | Alamat atau titik lokasi | Jl. Raya Kepatihan No.10 |
| **Latitude** | Koordinat lintang | `-8.1845` |
| **Longitude** | Koordinat bujur | `113.6682` |
| **Foto** | Dokumentasi kondisi | Jepret dari kamera |

> **GPS:** Di aplikasi Android, koordinat bisa diisi otomatis dengan tombol GPS.

### 5.3 Menyimpan Draft

**Draft** adalah laporan yang disimpan sementara, **belum dikirim** ke Admin.

- Klik **Simpan Draft** atau **action: draft** untuk menyimpan sementara
- Draft masih bisa diedit nanti
- Draft **tidak** memiliki nomor laporan

### 5.4 Mengirim Laporan

- Klik **Kirim** atau **action: submit** setelah yakin data lengkap
- Laporan yang sudah dikirim mendapat **nomor laporan** (contoh: `LH-20260716-0001`)
- Status berubah menjadi **Submitted**
- Laporan masuk ke antrian Admin untuk diverifikasi

### 5.5 Melihat Detail Laporan

1. Dari daftar laporan, klik laporan yang ingin dilihat
2. Detail menampilkan semua informasi laporan:
   - Status
   - Nomor laporan (jika sudah submit)
   - Data lengkap
   - Foto
   - Riwayat verifikasi (jika sudah diproses Admin)

### 5.6 Mengedit Draft

Hanya laporan dengan status **Draf** yang bisa diedit:
1. Buka detail laporan
2. Klik **Edit**
3. Perbaiki data
4. Simpan atau Kirim ulang

### 5.7 Resubmit (Kirim Ulang)

Jika laporan **Ditolak** Admin:
1. Buka detail laporan
2. Klik **Edit** untuk memperbaiki data sesuai alasan penolakan
3. Klik **Kirim Ulang** (Resubmit)
4. Laporan kembali ke antrian Admin dengan status **Submitted**

---

## 6. Fitur Laporan Irigasi

### 6.1 Membuat Laporan Irigasi

Cara sama seperti Laporan Hama, bedanya pada menu dan field:

| Platform | Menu |
|----------|------|
| **Web** | Laporan Irigasi → Buat Laporan |
| **Aplikasi** | Card "Laporan Irigasi" → **+** |

### 6.2 Field Khusus Irigasi

| Field | Penjelasan | Contoh |
|-------|-----------|--------|
| **Nama Saluran** | Nama saluran irigasi | Saluran Sekunder Kaliwates |
| **Daerah Irigasi** | Area yang dialiri | Daerah Irigasi Bedadung |
| **Kondisi Fisik** | Baik/Rusak Ringan/Rusak Berat | Rusak Ringan |
| **Debit Air** | Normal/Berkurang/Kering | Berkurang |
| **Latitude/Longitude** | Koordinat lokasi | -8.1845 / 113.6682 |

---

## 7. Fitur Verifikasi (Admin)

### 7.1 Melihat Antrian

**Web** — Setelah login sebagai Admin:

1. Dashboard: lihat kartu **Antrian Verifikasi** untuk jumlah laporan menunggu
2. Klik menu **Laporan Hama** atau **Laporan Irigasi**
3. Filter status **Submitted** untuk melihat laporan yang perlu diverifikasi

**Aplikasi Android** (Admin):

1. Card **Antrian Verifikasi** di halaman utama
2. Ketuk untuk melihat daftar laporan menunggu

### 7.2 Memverifikasi Laporan

1. Buka detail laporan
2. Periksa data dan foto
3. Jika valid, klik tombol **Verifikasi**
4. Tambahkan catatan (opsional)
5. Status berubah menjadi **Diverifikasi**

### 7.3 Menolak Laporan

1. Buka detail laporan
2. Klik tombol **Tolak**
3. Tulis **alasan penolakan** (minimal 10 karakter, jelaskan apa yang perlu diperbaiki)
4. Klik konfirmasi
5. Status berubah menjadi **Ditolak**
6. Petugas akan menerima notifikasi dan bisa memperbaiki laporan

### 7.4 Mengarsipkan

Laporan **Diverifikasi** bisa diarsipkan agar tidak muncul di daftar aktif:
1. Buka detail laporan
2. Klik tombol **Arsipkan**
3. Status berubah menjadi **Diarsipkan**

---

## 8. Notifikasi

### 8.1 Notifikasi di Web

- **Bell Icon** di pojok kanan atas menampilkan jumlah notifikasi belum dibaca
- Klik bell untuk melihat 5 notifikasi terbaru
- Klik **Lihat Semua** untuk membuka halaman notifikasi
- Notifikasi terjadi saat:
  - Laporan dikirim Petugas → Admin mendapat notifikasi
  - Laporan diverifikasi/ditolak/diarsipkan → Petugas mendapat notifikasi

### 8.2 Notifikasi di Aplikasi

- **Badge merah** di icon lonceng menunjukkan jumlah notifikasi belum dibaca
- Card **Notifikasi** di halaman utama untuk melihat semua notifikasi
- Ketuk notifikasi untuk membuka detail laporan terkait
- Notifikasi push (FCM) akan muncul di layar ponsel jika diaktifkan

---

## 9. Ekspor Data

Fitur **Ekspor** untuk mengunduh data laporan (Admin & Petugas):

1. Klik menu **Ekspor** di web
2. Pilih pengaturan:

   *[Ilustrasi: Form Ekspor]*

   | Pengaturan | Pilihan |
   |------------|---------|
   | **Jenis Laporan** | Hama atau Irigasi |
   | **Format File** | CSV (bisa dibuka Excel) atau XLSX (Excel) |
   | **Status** | Submitted / Diverifikasi / Ditolak / Diarsipkan |
   | **Wilayah** | Kabupaten/Kecamatan/Desa (opsional) |
   | **Rentang Tanggal** | Dari tanggal s/d tanggal |

3. Klik **Download**
4. File akan terunduh

> **Catatan:** Maksimal 10.000 baris per ekspor. Jika lebih, persempit filter.

---

## 10. Pemecahan Masalah Umum

### Login

| Masalah | Solusi |
|---------|--------|
| **"Username atau password salah"** | Cek kembali username dan password. Hubungi Admin jika lupa. |
| **"Terlalu banyak percobaan" (429)** | Tunggu 15 menit sebelum mencoba lagi. |
| **Tidak bisa login sama sekali** | Hubungi Admin Dinas untuk reset password. |

### Laporan

| Masalah | Solusi |
|---------|--------|
| **Tombol Kirim tidak aktif** | Pastikan semua field wajib terisi. Cek pesan error merah di form. |
| **Foto gagal diupload** | Pastikan ukuran file ≤ 10MB. Format: JPEG, PNG, atau WebP. |
| **Tidak bisa edit laporan** | Hanya laporan status **Draf** atau **Ditolak** yang bisa diedit. |
| **Koordinat tidak valid** | Format: angka desimal. Contoh: `-8.1845` (bukan `-8°18'45"`). |

### Aplikasi Android

| Masalah | Solusi |
|---------|--------|
| **Aplikasi tidak bisa dibuka** | Restart ponsel. Coba install ulang. |
| **"Koneksi gagal"** | Pastikan koneksi internet aktif. Cek URL backend dengan Admin. |
| **GPS tidak berfungsi** | Aktifkan GPS di pengaturan ponsel. Izinkan akses lokasi ke aplikasi. |

---

## 11. Tips Keamanan

### Password

| Aturan | Penjelasan |
|--------|-----------|
| **Jangan bagikan** | Password bersifat rahasia. Jangan beri tahu siapa pun. |
| **Ganti secara berkala** | Disarankan ganti password setiap 3 bulan. |
| **Jangan gunakan password yang sama** | Gunakan password berbeda dari akun media sosial/email. |
| **Logout setelah selesai** | Pastikan logout, terutama di perangkat bersama. |

### Akun

- Jika mencurigai akun diretas, segera hubungi Admin untuk reset password
- Jangan simpan password di browser publik
- Laporkan aktivitas mencurigakan ke Admin

### Data

- Pastikan data laporan akurat sebelum dikirim
- Dokumentasi foto yang jelas membantu proses verifikasi
- Koordinat lokasi yang tepat memudahkan tindak lanjut

---

## Glossary

| Istilah | Arti |
|---------|------|
| **Draf** | Laporan yang disimpan sementara, belum dikirim |
| **Submitted** | Laporan sudah dikirim, menunggu verifikasi Admin |
| **Diverifikasi** | Laporan sudah disetujui Admin |
| **Ditolak** | Laporan ditolak Admin, perlu perbaikan dan kirim ulang |
| **Diarsipkan** | Laporan selesai diproses, disimpan sebagai arsip |
| **OPT** | Organisme Pengganggu Tanaman (hama/penyakit) |
| **FCM** | Firebase Cloud Messaging — notifikasi push ke ponsel |

---

> **Dokumen ini dapat diperbarui sewaktu-waktu.**
> Untuk bantuan lebih lanjut, hubungi Admin Dinas atau tim teknis JAGAPADI.
