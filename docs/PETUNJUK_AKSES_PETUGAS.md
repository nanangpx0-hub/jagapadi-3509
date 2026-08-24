# Petunjuk dan Panduan Akses JAGAPADI untuk Role Petugas

> **JAGAPADI** — Jember Agrikultur Gapai Prestasi Digital  
> **Peran Pengguna**: `petugas` (Petugas Pengamat Hama / POPT, Penyuluh Pertanian Lapangan / PPL, Mantri Tani)  
> **Wilayah Operasional**: Kabupaten Jember (Kode Wilayah: `3509`)  
> **Versi Panduan**: 1.0.0 · Agustus 2026

---

## DAFTAR ISI

1. [Pengenalan Peran Petugas](#1-pengenalan-peran-petugas)
2. [Cara Masuk (Login) ke Sistem](#2-cara-masuk-login-ke-sistem)
   - [A. Akses melalui Web Browser (Komputer / HP)](#a-akses-melalui-web-browser-komputer--hp)
   - [B. Akses melalui Aplikasi Android (Flutter Mobile)](#b-akses-melalui-aplikasi-android-flutter-mobile)
3. [Ketentuan Keamanan & Kewajiban Ganti Password](#3-ketentuan-keamanan--kewajiban-ganti-password)
4. [Tampilan Antarmuka & Menu Khusus Petugas](#4-tampilan-antarmuka--menu-khusus-petugas)
5. [Panduan Langkah Demi Langkah Pelaporan Lapangan](#5-panduan-langkah-demi-langkah-pelaporan-lapangan)
   - [5.1 Laporan Hama & Penyakit Tanaman (OPT)](#51-laporan-hama--penyakit-tanaman-opt)
   - [5.2 Laporan Kondisi Jaringan Irigasi](#52-laporan-kondisi-jaringan-irigasi)
   - [5.3 Laporan Sektoral Tambahan (Pupuk, Panen, Cuaca, Alsintan)](#53-laporan-sektoral-tambahan-pupuk-panen-cuaca-alsintan)
6. [Fitur Masukan, Saran, dan Aduan (Feedback)](#6-fitur-masukan-saran-dan-aduan-feedback)
7. [Penanganan Mode Offline & Sinyal Lemah](#7-penanganan-mode-offline--sinyal-lemah)
8. [Batasan Hak Akses & Privasi Data Petugas](#8-batasan-hak-akses--privasi-data-petugas)
9. [Bantuan & Kontak Teknis](#9-bantuan--kontak-teknis)

---

## 1. PENGENALAN PERAN PETUGAS

Sebagai **Petugas Lapangan** di sistem JAGAPADI, Anda memiliki peran vital dalam mengumpulkan data faktual pertanian dari sawah dan hamparan kelompok tani di seluruh Kabupaten Jember.

### Tugas Utama Petugas di JAGAPADI:
- Mencatat serangan Hama & Penyakit Tanaman (OPT) secara presisi dengan koordinat GPS dan foto.
- Melaporkan kondisi fisik saluran air dan ketersediaan debit irigasi pertanian.
- Melaporkan data ubinan/panen, aplikasi pemupukan, cuaca mikro, dan inventaris alsintan.
- Memantau status laporan yang diajukan (*Draf*, *Dikirim*, *Diverifikasi*, *Ditolak*).
- Menyampaikan masukan/kendala operasional aplikasi ke Dinas Pertanian melalui modul Feedback.

---

## 2. CARA MASUK (LOGIN) KE SISTEM

### A. Akses melalui Web Browser (Komputer / HP)

Anda dapat membuka browser (Google Chrome, Mozilla Firefox, Safari, atau Microsoft Edge) dari laptop, tablet, maupun smartphone.

#### 1. Buka Alamat Website:
- **Dari Komputer Server / Localhost**:  
  👉 `http://localhost/jagapadi-3509/`
- **Dari HP / Laptop yang Terhubung ke Wi-Fi / LAN yang Sama**:  
  👉 `http://192.168.10.5/jagapadi-3509/`

```
┌─────────────────────────────────────────────────────────────┐
│                    JAGAPADI - LOGIN SISTEM                  │
│                                                             │
│   Username : [ petugas01                              ]     │
│   Password : [ •••••••••••••                          ]     │
│                                                             │
│              [     MASUK KE SISTEM     ]                    │
└─────────────────────────────────────────────────────────────┘
```

#### 2. Langkah Login Web:
1. Masukkan **Username** akun Petugas Anda (Contoh: `petugas01`, `petugas02`, dst.).
2. Masukkan **Password** akun Anda.
3. Klik tombol **Masuk ke Sistem**.
4. Jika berhasil, Anda akan otomatis diarahkan ke **Dashboard Khusus Petugas**.

---

### B. Akses melalui Aplikasi Android (Flutter Mobile)

Bagi petugas yang menggunakan smartphone Android di lapangan:

1. Buka aplikasi **JAGAPADI Mobile** pada layar smartphone Anda.
2. Pastikan smartphone terhubung ke internet (data seluler atau Wi-Fi kantor BPP).
3. Masukkan **Username** dan **Password** Petugas Anda pada formulir login.
4. Tekan tombol **Masuk**.
5. Sistem akan mengunduh token keamanan dan menyinkronkan data master wilayah secara otomatis.

---

## 3. KETENTUAN KEAMANAN & KEWAJIBAN GANTI PASSWORD

Untuk melindungi akun dan keabsahan data laporan:

> [!IMPORTANT]
> **Kewajiban Ganti Password Pertama Kali (*Must Change Password*)**:
> Jika Anda menggunakan akun baru atau password default dari administrator, sistem akan secara otomatis mengarahkan Anda ke halaman **Ganti Password** saat pertama kali login.

### Langkah Ganti Password:
1. Masukkan **Password Lama / Password Default**.
2. Masukkan **Password Baru** Anda (Gunakan kombinasi minimal 8 karakter dengan huruf dan angka).
3. Konfirmasi ulang **Password Baru**.
4. Klik **Simpan Password Baru**.
5. Setelah berhasil, gunakan password baru tersebut untuk setiap sesi login berikutnya.

> [!CAUTION]
> Jangan pernah membagikan username dan password Anda kepada pihak lain. Setiap laporan yang dikirim akan secara hukum tercatat atas nama akun pemilik yang mengirim.

---

## 4. TAMPILAN ANTARMUKA & MENU KHUSUS PETUGAS

Setelah berhasil login, menu di bilah navigasi kiri (*sidebar*) dirancang khusus untuk operasional Petugas:

```
┌─────────────────────────────────────────────────────────────┐
│  🌾 JAGAPADI - Menu Petugas                                 │
├─────────────────────────────────────────────────────────────┤
│  📊 Dashboard Petugas       → Ringkasan kinerja & status    │
│  🐛 Laporan Hama (OPT)      → Daftar & input serangan hama  │
│  💧 Laporan Irigasi         → Kondisi saluran & pintu air   │
│  📋 Laporan Lainnya         → Pupuk, Panen, Cuaca, Alsintan │
│  💬 Masukan & Saran         → Kanal feedback & aduan sistem │
│  👤 Profil Saya             → Info akun & ganti sandi       │
│  🚪 Keluar (Logout)         → Selesai sesi bertugas         │
└─────────────────────────────────────────────────────────────┘
```

### Kartu Indikator Status di Dashboard Petugas:
Dashboard menampilkan 4 kartu rekapitulasi pekerjaan Anda sendiri:
- 🔵 **Total Laporan**: Jumlah seluruh laporan yang pernah Anda buat.
- 🟡 **Draf**: Laporan yang tersimpan sementara dan belum Anda kirim ke dinas.
- 🟠 **Menunggu Verifikasi (Submitted)**: Laporan yang sudah dikirim dan sedang diperiksa admin.
- 🟢 **Diverifikasi**: Laporan resmi yang telah disahkan oleh tim verifikator.

---

## 5. PANDUAN LANGKAH DEMI LANGKAH PELAPORAN LAPANGAN

### 5.1 Laporan Hama & Penyakit Tanaman (OPT)

Gunakan modul ini saat menemukan serangan wereng, penggerek batang, blas, tikus, atau OPT lainnya di hamparan sawah.

1. Buka menu **Laporan Hama (OPT)** $\rightarrow$ Klik tombol **Buat Laporan Baru** (`/laporan/create`).
2. **Tanggal Kejadian**: Pilih tanggal faktual saat Anda melakukan pengamatan di petak sawah.
3. **Pilih Wilayah**:
   - Kabupaten: `Kabupaten Jember`
   - Kecamatan: Pilih kecamatan tugas Anda (misal: `Wuluhan`).
   - Desa: Pilih desa lokasi sawah (misal: `Dukuhdempit`).
4. **Alamat Lengkap / Blok Sawah**: Tuliskan nama blok sawah, nomor petak, atau nama kelompok tani (Poktan).
5. **Ambil Titik Koordinat (GPS)**:
   - Klik tombol **📍 Ambil Lokasi Saat Ini** (pastikan GPS di HP aktif), atau
   - Geser pin pada peta interaktif tepat di atas petak sawah yang diamati.
   - *Pastikan Lintang (Latitude) bertanda minus (sekitar $-8.17$) dan Bujur (Longitude) bernilai positif (sekitar $113.70$).*
6. **Data Serangan OPT**:
   - **Jenis OPT**: Pilih jenis hama/penyakit dari daftar master (misal: *Wereng Batang Coklat*).
   - **Tingkat Keparahan**: Pilih `Ringan` (<25%), `Sedang` (25–50%), atau `Berat` (>50%).
   - **Populasi / Intensitas**: Isi rata-rata kepadatan hama per rumpun (misal: `18.5` ekor/rumpun) atau % daun terserang. *(Jangan diisi angka luas hektar!)*
   - **Luas Serangan (Ha)**: Masukkan luas sawah yang benar-benar terserang dalam satuan Hektar (misal: `1.25` Ha).
7. **Unggah Foto Bukti**:
   - Ambil foto tanaman terserang dari jarak dekat (*close-up*) di lokasi lahan.
8. **Catatan Tindakan**: Tuliskan rekomendasi atau tindakan awal petani (misal: pengeringan berselang / semprot agens hayati).
9. **Kirim Laporan**:
   - Klik **Simpan Draf** jika data belum selesai, atau
   - Klik **Kirim Laporan** untuk menerbitkan Nomor Laporan resmi (Contoh: `LH-20260821-0001`).

---

### 5.2 Laporan Kondisi Jaringan Irigasi

Gunakan modul ini untuk melaporkan kondisi saluran primer, sekunder, tersier, atau pintu air.

1. Buka menu **Laporan Irigasi** $\rightarrow$ Klik **Buat Laporan Irigasi** (`/irigasi/create`).
2. Masukkan **Nama Saluran** (misal: *Saluran Sekunder BD-3*) dan **Daerah Irigasi (DI)**.
3. Pilih **Jenis Saluran**: `Primer`, `Sekunder`, atau `Tersier`.
4. Pilih **Ketersediaan Debit Air**: `Cukup`, `Kurang`, atau `Kering`.
5. Tentukan **Kondisi Fisik**: `Bagus / Baik`, `Sedang / Rusak Ringan`, atau `Rusak / Rusak Berat`.
6. Masukkan **Luas Layanan Sawah (Ha)** yang menggantungkan air pada saluran tersebut.
7. Ambil titik koordinat GPS dan unggah foto bagian saluran/tanggul yang rusak.
8. Tuliskan tindakan darurat yang telah dilakukan bersama HIPPA/P3A pada kolom **Aksi Dilakukan**.
9. Klik **Kirim Laporan**.

---

### 5.3 Laporan Sektoral Tambahan (Pupuk, Panen, Cuaca, Alsintan)

Pada menu **Laporan Lainnya** (`/laporan-lainnya/create`), pilih jenis laporan yang sesuai:
- **Laporan Pupuk**: Catat jenis pupuk (`Urea`, `NPK`), dosis aplikasi (`kg/ha`), dan metode tebar/kocor.
- **Laporan Panen / Ubinan**: Catat komoditas padi/jagung, luas panen (Ha), hasil panen (Ton GKG), dan harga jual di tingkat petani (Rp/kg).
- **Laporan Cuaca Mikro**: Catat kondisi cuaca cerah/hujan, suhu udara (°C), kelembaban (%), dan kecepatan angin saat pengamatan.
- **Laporan Bantuan / Kondisi Alsintan**: Catat jenis traktor/combine harvester, jumlah unit, dan status kondisi operasional mesin.

---

## 6. FITUR MASUKAN, SARAN, DAN ADUAN (FEEDBACK)

Sebagai petugas lapangan, suara dan kendala teknis Anda sangat didengar oleh tim pengembang dinas melalui menu **Masukan & Saran** (`/feedback`).

```
┌─────────────────────────────────────────────────────────────┐
│                 KIRIM MASUKAN / SARAN BARU                  │
├─────────────────────────────────────────────────────────────┤
│   Jenis Masukan : [ Bug Report / Error               ▼ ]    │
│   Tingkat Urgensi: ( ) Rendah  (•) Medium  ( ) Tinggi       │
│   Judul Masukan : [ Peta di Desa X lambat terbuka      ]    │
│   Deskripsi     : [ Saat sinyal 3G, peta satelit butuh ]    │
│                   [ waktu lama untuk memuat...         ]    │
│   Lampiran Foto : [ Pilih Berkas... (Screenshot)       ]    │
│                                                             │
│                 [      KIRIM MASUKAN      ]                 │
└─────────────────────────────────────────────────────────────┘
```

### Langkah Mengirim Masukan:
1. Buka menu **Masukan & Saran** $\rightarrow$ Klik **Buat Masukan Baru** (`/feedback/create`).
2. Pilih **Jenis Masukan**:
   - `Bug Report`: Jika ada error, tombol tidak berfungsi, atau aplikasi macet.
   - `Fitur Baru`: Jika ingin mengusulkan fitur atau menu baru.
   - `Saran Peningkatan`: Jika ada saran perbaikan alur kerja.
3. Masukkan **Judul** (minimal 5 karakter) dan **Deskripsi Rinci** (minimal 20 karakter).
4. Pilih **Prioritas** (`Rendah`, `Medium`, `Tinggi`).
5. Lampirkan tangkapan layar (*screenshot*) bukti kendala jika ada.
6. Klik **Kirim Masukan**.
7. Anda dapat memantau tanggapan dan catatan penyelesaian dari Administrator pada halaman detail aduan Anda.

---

## 7. PENANGANAN MODE OFFLINE & SINYAL LEMAH

Saat berada di area pelosok persawahan dengan sinyal internet minim:

1. **Gunakan Tombol Simpan Draf**: Formulir web dan aplikasi mobile tetap dapat menyimpan data isian Anda secara lokal tanpa harus langsung terkirim ke server.
2. **GPS Tetap Berfungsi Tanpa Kuota**: Sensor GPS smartphone dapat mengunci koordinat lintang/bujur secara akurat meskipun tanpa koneksi internet.
3. **Kirim Saat Berada di Jaringan Stabil**: Begitu Anda kembali ke area BPP atau lokasi berkuota internet/Wi-Fi, buka daftar draf laporan Anda dan tekan tombol **Kirim Laporan (Submit)**.

---

## 8. BATASAN HAK AKSES & PRIVASI DATA PETUGAS

Sistem JAGAPADI menerapkan prinsip isolasi data ketat (*strict data ownership*):

| Hak Akses Petugas | Boleh / Bisa? | Penjelasan Teknis |
|---|:---:|---|
| Melihat & mengedit laporan miliknya sendiri | ✅ **Bisa** | Seluruh draf dan riwayat laporan pribadi dapat diakses penuh. |
| Melihat laporan milik petugas lain | ❌ **Tidak Bisa** | Terkunci di tingkat server untuk menjaga privasi kerja petugas. |
| Menghapus laporan yang sudah berstatus Dikirim/Diverifikasi | ❌ **Tidak Bisa** | Laporan resmi yang sudah terbit nomor hanya dapat dihapus/diarsipkan oleh Admin. |
| Mengubah status laporan menjadi Terverifikasi | ❌ **Tidak Bisa** | Verifikasi adalah wewenang mutlak Tim Admin Dinas Pertanian. |
| Mengakses menu Master Wilayah / Master OPT | ❌ **Tidak Bisa** | Hanya Admin yang berhak menambah/mengubah master data resmi. |
| Mengakses Rekap Global Seluruh Kabupaten | ❌ **Tidak Bisa** | Petugas hanya berfokus pada wilayah tugas dan data miliknya. |

---

## 9. BANTUAN & KONTAK TEKNIS

Jika Anda mengalami kendala login, lupa kata sandi, atau memerlukan panduan teknis lebih lanjut:

- **Helpdesk Dinas Pertanian**: Bidang Perlindungan Tanaman Pangan Kabupaten Jember
- **Kanal Aduan Cepat**: Menu **Masukan & Saran** (`/feedback`) pada aplikasi JAGAPADI
- **Pembaruan Sistem**: Hubungi Administrator Dinas untuk reset password akun jika akun Anda terkunci.

---

*Panduan ini disusun untuk kelancaran tugas lapangan Petugas POPT, PPL, dan Mantri Tani dalam mewujudkan kedaulatan pangan digital Kabupaten Jember.*
