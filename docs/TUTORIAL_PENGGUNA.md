# Panduan Pengguna JAGAPADI

JAGAPADI adalah sistem pelaporan pertanian untuk Kabupaten Jember yang mencakup pelaporan hama/Organisme Pengganggu Tanaman (OPT) dan kondisi irigasi. Panduan ini akan membantu kamu menggunakan aplikasi secara optimal.

---

## Daftar Isi
1. [Persiapan Awal](#persiapan-awal)
2. [Navigasi Dasar](#navigasi-dasar)
3. [Tutorial Fitur Inti](#tutorial-fitur-inti)
   - [Autentikasi](#autentikasi)
   - [Laporan Hama/OPT](#laporan-hamaopt)
   - [Laporan Irigasi](#laporan-irigasi)
   - [Notifikasi](#notifikasi)
   - [Dashboard](#dashboard)
   - [Export Data](#export-data)
4. [Penyelesaian Masalah Umum](#penyelesaian-masalah-umum)
5. [Tips dan Praktik Terbaik](#tips-dan-praktik-terbaik)
6. [Kontak Dukungan](#kontak-dukungan)

---

## 1. Persiapan Awal
### Persyaratan Sistem
- **Mobile App**: Android 8.0+ (iOS rencana mendatang)
- **Web Admin**: Browser modern (Chrome, Firefox, Safari, Edge versi terbaru)
- **Koneksi Internet**: Stabil untuk mengunggah foto dan menyinkronkan data

### Mendapatkan Akses
Untuk menggunakan aplikasi JAGAPADI, kamu harus memiliki akun yang dibuat oleh administrator sistem. Hubungi admin kecamatan untuk mendapatkan kredensial.

#### Kredensial Seed Lokal (Hanya Pengembangan)
Untuk testing lokal, gunakan akun berikut:
- Admin: `admin` / `ChangeMeAdmin!123`
- Petugas: `petugas01` / `ChangeMePetugas!123`

> **PERINGATAN**: Segera ganti password setelah login pertama!

---

## 2. Navigasi Dasar
### Antarmuka Aplikasi Mobile
Setelah login, kamu akan melihat layar utama dengan menu navigasi di bagian bawah:
- **Beranda**: Dashboard statistik ringkas
- **Laporan Hama**: Daftar dan buat laporan hama/OPT
- **Laporan Irigasi**: Daftar dan buat laporan irigasi
- **Notifikasi**: Notifikasi sistem (verifikasi, penolakan, dll.)
- **Profil**: Profil pengguna dan ganti password

### Antarmuka Web Admin
- **Sidebar Kiri**: Menu navigasi utama (Dashboard, Laporan, Master Data, Export, Notifikasi)
- **Navbar Atas**: Nama pengguna, notifikasi badge, dan tombol logout
- **Konten Utama**: Halaman yang sedang dibuka (misal: list laporan)

---

## 3. Tutorial Fitur Inti

### Autentikasi
#### Login
1. Buka aplikasi mobile atau halaman web login (`/login`)
2. Masukkan username dan password
3. Tekan tombol "Login"
4. Jika `must_change_password` aktif, kamu akan diarahkan untuk ganti password segera

#### Ganti Password
1. Buka halaman "Profil" di mobile atau `/password/change` di web
2. Masukkan password lama, password baru, dan konfirmasi password baru
3. Tekan tombol "Simpan"

> **Kebijakan Password**: Minimal 8 karakter, harus ada huruf besar, huruf kecil, angka, dan karakter khusus.

---

### Laporan Hama/OPT

#### Membuat Draf Laporan
1. Buka menu "Laporan Hama"
2. Tekan tombol "+" (mobile) atau "Buat Laporan Baru" (web)
3. Isi formulir:
   - **Tanggal Laporan**: Tanggal observasi
   - **Wilayah**: Pilih Kabupaten → Kecamatan → Desa
   - **Jenis OPT**: Pilih jenis hama/penyakit/gulma
   - **Tingkat Keparahan**: Ringan / Sedang / Berat
   - **Luas Serangan (ha)**: Luas lahan yang terkena serangan
   - **Populasi**: Estimasi jumlah OPT per satuan luas
   - **Lokasi Detail**: Deskripsi lokasi (misal: Blok Sawah Utara)
   - **Koordinat**: Tekan "Ambil Lokasi" untuk mendapatkan GPS otomatis
   - **Catatan**: Catatan tambahan (opsional)
4. Tekan "Simpan Draf"

#### Mengirim Laporan (Submit)
1. Buka detail laporan draf
2. Periksa semua data sudah benar
3. Tekan "Kirim Laporan"
4. Setelah dikirim, laporan menjadi tidak bisa diedit dan mendapatkan nomor laporan (contoh: `LH-20260716-0001`)

#### Mengunggah Foto
1. Buka detail laporan dengan status **Draf** atau **Ditolak**
2. Tekan "Unggah Foto"
3. Pilih foto dari galeri (hanya JPEG/PNG/WebP, max 10MB)
4. Foto terkompres otomatis jika ukuran lebih dari 2MB
5. Foto hanya bisa diubah saat laporan berstatus Draf atau Ditolak

#### Alur Status Laporan
```
Draf → Submitted → Diverifikasi → Diarsipkan
              ↓
           Ditolak → [Edit] → Resubmit → Submitted
```

---

### Laporan Irigasi
Langkah-langkahnya mirip dengan laporan hama, dengan perbedaan pada formulir:
- **Nama Saluran**: Nama saluran irigasi
- **Daerah Irigasi**: Nama daerah irigasi (opsional)
- **Kondisi Fisik Saluran**: Bagus / Sedang / Tidak Bagus / Rusak
- **Debit Air**: Cukup / Kurang / Kering

Nomor laporan irigasi memiliki prefix `LI-`.

---

### Notifikasi
Kamu akan mendapatkan notifikasi saat:
- Laporanmu diverifikasi oleh admin (`laporan_verified`)
- Laporanmu ditolak oleh admin (`laporan_rejected`)
- Laporanmu diarsipkan (`laporan_archived`)
- (Untuk admin) Ada laporan baru yang masuk (`laporan_submitted` atau `laporan_resubmitted`)

#### Menandai Notifikasi Sudah Dibaca
1. Buka halaman "Notifikasi"
2. Tekan notifikasi untuk melihat detail (otomatis ditandai dibaca)
3. Atau tekan "Tandai Semua Dibaca" untuk menandai semua notifikasi sekaligus

---

### Dashboard
Halaman dashboard menampilkan ringkasan statistik:
- Jumlah laporan aktif (Submitted + Diverifikasi)
- Jumlah laporan per status
- Grafik bulanan jumlah laporan
- Peta interaktif titik lokasi laporan

Petugas hanya melihat laporan milik sendiri. Admin melihat semua laporan.

---

### Export Data
Kamu bisa mengekspor data laporan ke format CSV atau Excel (XLSX).
1. Buka halaman "Export"
2. Pilih jenis laporan: Hama atau Irigasi
3. Atur filter (opsional):
   - Status laporan
   - Rentang tanggal
   - Wilayah (Kabupaten/Kecamatan/Desa)
4. Pilih format file: CSV atau XLSX
5. Tekan "Unduh File"

> **Batasan Export**:
> - Maksimal 10.000 baris
> - Rentang tanggal maksimal 366 hari
> - Petugas hanya bisa mengekspor data miliknya sendiri

---

## 4. Penyelesaian Masalah Umum

### Masalah Login
- **"Username atau password salah"**: Periksa kembali username dan password (perhatikan huruf besar/kecil)
- **"Terlalu banyak percobaan login"**: Coba lagi setelah 15 menit atau hubungi admin
- **Token JWT expired**: Logout lalu login kembali atau gunakan refresh token

### Masalah Unggah Foto
- **"File bukan gambar yang diizinkan"**: Pastikan file kamu JPEG/PNG/WebP
- **"File terlalu besar"**: Ukuran file maksimal 10MB; kompres foto terlebih dahulu
- **"Status laporan tidak mengizinkan perubahan foto"**: Foto hanya bisa diubah saat laporan berstatus Draf atau Ditolak

### Laporan Ditolak
1. Buka notifikasi penolakan untuk melihat alasan
2. Edit laporan sesuai alasan penolakan
3. Tekan "Kirim Ulang Laporan" untuk submit kembali

### Koordinat GPS Tidak Akurat
- Pastikan lokasi diaktifkan di perangkatmu
- Berada di area terbuka untuk mendapatkan sinyal GPS yang lebih baik
- Kamu juga bisa memasukkan koordinat secara manual

---

## 5. Tips dan Praktik Terbaik
1. **Gunakan Draf**: Gunakan fitur draf untuk menyimpan laporan yang belum selesai
2. **Isi Data Lengkap**: Isi semua kolom yang diperlukan untuk memudahkan verifikasi
3. **Unggah Foto Jelas**: Unggah foto yang jelas untuk membantu verifikasi
4. **Periksa Kembali Sebelum Submit**: Pastikan semua data benar sebelum mengirim laporan
5. **Segera Perbaiki Laporan yang Ditolak**: Jika laporanmu ditolak, perbaiki dan kirim ulang sesegera mungkin
6. **Perbarui Password Secara Berkala**: Ganti password minimal 3 bulan sekali
7. **Periksa Notifikasi Secara Rutin**: Pastikan kamu selalu meninjau notifikasi untuk mengetahui status laporanmu

---

## 6. Kontak Dukungan
Jika kamu mengalami masalah yang tidak bisa diselesaikan dengan panduan ini, hubungi:
- **Admin Kabupaten/Kecamatan**: Untuk masalah akun atau verifikasi laporan
- **Email Dukungan Teknis**: support@jagapadi.example (ganti dengan email sesuai konfigurasi)

---

## Lampiran
- [Dokumentasi API](./API.md)
- [Checklist Go-Live](./GO_LIVE_CHECKLIST.md)
- [Checklist QA](./QA_CHECKLIST.md)
