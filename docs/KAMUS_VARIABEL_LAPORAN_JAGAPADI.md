# Kamus Standar Konsep dan Definisi (Kondef) Variabel Laporan JAGAPADI

> **JAGAPADI** — Jember Agrikultur Gapai Prestasi Digital  
> **Dokumen**: Standar Acuan Variabel dan Metadata Pelaporan Pertanian Terpadu Kabupaten Jember  
> **Versi**: 2.0.0 (Diverifikasi menyeluruh terhadap formulir input, schema database, model bisnis, validator API, serta dokumentasi sistem)  
> **Sasaran Pengguna**: Petugas Lapangan (POPT / PPL), Administrator Sistem, Operator Data, Analis / Statistisi Pertanian, Pengembang Sistem.

---

## DAFTAR ISI

1. [Pendahuluan & Ketentuan Umum Pelaporan](#1-pendahuluan--ketentuan-umum-pelaporan)
2. [Variabel Identitas, Tata Kelola & Workflow Bersama (Universal)](#2-variabel-identitas-tata-kelola--workflow-bersama-universal)
3. [Variabel Spasial, Geografis & Lokasi Kerja (Geotagging)](#3-variabel-spasial-geografis--lokasi-kerja-geotagging)
4. [Variabel Laporan Hama & Penyakit Tanaman (OPT)](#4-variabel-laporan-hama--penyakit-tanaman-opt)
5. [Variabel Laporan Kondisi & Jaringan Irigasi](#5-variabel-laporan-kondisi--jaringan-irigasi)
6. [Variabel Laporan Sektoral Tambahan (Pupuk, Panen, Cuaca, Alat & Sarana)](#6-variabel-laporan-sektoral-tambahan-pupuk-panen-cuaca-alat--sarana)
7. [Variabel Laporan Dinamis Terintegrasi (Laporan Lainnya)](#7-variabel-laporan-dinamis-terintegrasi-laporan-lainnya)
8. [Variabel Masukan, Saran, dan Aduan (Feedback)](#8-variabel-masukan-saran-dan-aduan-feedback)
9. [Variabel Data Statistik Pendukung, Agregat Sektoral & Dashboard Analitik](#9-variabel-data-statistik-pendukung-agregat-sektoral--dashboard-analitik)
10. [Matriks Perbandingan Cepat Variabel Laporan](#10-matriks-perbandingan-cepat-variabel-laporan)
11. [Petunjuk & Checklist Pengisian Laporan Berkualitas](#11-petunjuk--checklist-pengisian-laporan-berkualitas)

---

## 1. PENDAHULUAN & KETENTUAN UMUM PELAPORAN

Sistem Pelaporan JAGAPADI mengintegrasikan berbagai jenis data lapangan dan sektoral pertanian di Kabupaten Jember (Kode Wilayah BPS: `3509`). Seluruh variabel yang dikumpulkan dalam sistem dirancang untuk memberikan informasi yang akurat, terverifikasi, dan dapat dipertanggungjawabkan guna mendukung pengambilan kebijakan pangan dan perlindungan tanaman.

### 1.1 Struktur Status Laporan Resmi
Setiap dokumen laporan lapangan memiliki siklus status resmi sebagai berikut:
- **`Draf`**: Laporan tersimpan di perangkat lokal atau server namun belum diserahkan secara resmi. Data berstatus Draf tidak dimasukkan ke dalam perhitungan statistik resmi, peta agregat publik, maupun ekspor data default.
- **`Submitted` (Dikirim)**: Laporan telah diserahkan secara resmi oleh petugas. Pada tahap ini, nomor registrasi laporan unik diterbitkan secara atomik.
- **`Diverifikasi`**: Laporan telah diperiksa dan disahkan oleh Administrator / Verifikator Dinas Pertanian.
- **`Ditolak`**: Laporan dikembalikan kepada pelapor karena ketidaksesuaian data, bukti foto kurang jelas, atau kesalahan koordinat. Petugas dapat memperbaiki data dan melakukan pengiriman ulang (*resubmit*).
- **`Diarsipkan`**: Laporan yang telah diverifikasi dan telah melewati masa aktif operasional, tetap disimpan untuk kebutuhan audit historis tanpa mempengaruhi agregat aktif.

### 1.2 Prinsip Kepemilikan & Integritas Data
1. **Pemisahan Hak Akses (*Ownership*)**: Identitas pelapor (`user_id`) selalu ditentukan dari sesi otentikasi login resmi di server, bukan dari input teks bebas.
2. **Kemandirian Satuan**: Pengisian angka tidak boleh mencampurkan satuan (misal: mengisi angka hektar pada kolom populasi hama atau sebaliknya).
3. **Perekaman Mentah & Sanitasi Output**: Nilai teks disimpan dalam bentuk teks asli bersih (*trimmed raw string*) dan diproteksi dengan *prepared statements*, sedangkan konversi karakter khusus (*HTML escape*) dilakukan pada saat penyajian data guna mencegah kerentanan keamanan (XSS dan SQL Injection).

---

## 2. VARIABEL IDENTITAS, TATA KELOLA & WORKFLOW BERSAMA (UNIVERSAL)

Variabel-variabel ini terdapat di seluruh modul laporan lapangan untuk memastikan keterlacakan (*traceability*) dan keabsahan hukum data.

---

### 2.1 Jenis Laporan
- **Nama Teknis / Database**: `jenis_laporan` / `jenis_id` / `jenis_feedback`
- **Definisi Resmi**: Klasifikasi utama yang menentukan kategori tematik kejadian atau objek pertanian yang sedang dilaporkan.
- **Tujuan & Fungsi**: Mengarahkan formulir isian dinamis, menentukan skema validasi data, mengelompokkan arsip data di database, serta memetakan laporan ke modul analitik terkait.
- **Format Data**: Pilihan Terbatas (*Enumeration / Lookup ID*).
- **Nilai Sah & Contoh**:
  - `hama` (Laporan Serangan Organisme Pengganggu Tumbuhan)
  - `irigasi` (Laporan Kondisi Jaringan Irigasi)
  - `pupuk` (Laporan Ketersediaan / Aplikasi Pemupukan)
  - `panen` (Laporan Realisasi & Produktivitas Panen)
  - `cuaca` (Laporan Dinamika Iklim Mikro Lapangan)
  - `alat_sarana` (Laporan Bantuan & Kondisi Alsintan)
  - `bibit_baru`, `rumah_kaca`, `kerusakan_cuaca` (Sub-jenis Laporan Lainnya)
  - `bug`, `fitur_baru`, `peningkatan` (Jenis Masukan/Feedback)
- **Aturan & Keterbatasan**: Wajib dipilih di awal pembuatan laporan; tidak dapat diubah setelah laporan diterbitkan menjadi nomor registrasi resmi.

---

### 2.2 Tanggal Kejadian / Tanggal Observasi
- **Nama Teknis / Database**: `tanggal_kejadian` / `tanggal`
- **Definisi Resmi**: Waktu kalender saat petugas melakukan pengamatan faktual langsung atau saat peristiwa pertanian terjadi di lapangan.
- **Tujuan & Fungsi**: Menentukan kronologi kejadian, menyusun deret waktu (*time series*) dinamika serangan hama/panen, menghubungkan kejadian dengan data historis cuaca BMKG pada tanggal yang sama, serta menentukan periode musim tanam (Musim Hujan/Musim Kemarau).
- **Format Data**: Tanggal standar ISO 8601 (`YYYY-MM-DD`).
- **Contoh Nilai**: `2026-08-20`
- **Aturan & Keterbatasan**: 
  - Wajib diisi saat pengiriman (*submit*).
  - Tidak boleh melebihi tanggal hari ini (*future date is rejected*).
  - Disarankan tidak lebih lampau dari 30 hari kalender kecuali untuk penginputan data historis terotorisasi.

---

### 2.3 Nomor Laporan / Kode Registrasi
- **Nama Teknis / Database**: `nomor_laporan` / `kode_laporan`
- **Definisi Resmi**: Identifier unik alfanumerik terstandarisasi yang diterbitkan oleh sistem secara otomatis saat laporan pertama kali dikirimkan (*Submitted*).
- **Tujuan & Fungsi**: Menjadi rujukan tunggal pelacakan berkas, pencarian cepat pada sistem administrasi, penulisan surat rekomendasi dinas, serta tanda bukti resmi pelaporan.
- **Format Data**: String Alfanumerik Terstruktur (`VARCHAR(20)`).
- **Contoh Nilai**: 
  - `LH-20260820-0001` (Laporan Hama)
  - `LI-20260820-0003` (Laporan Irigasi)
  - `LL-20260820-0012` (Laporan Lainnya)
- **Aturan & Keterbatasan**: 
  - Bersifat unik secara global (*UNIQUE constraint*).
  - Tidak dibuat pada saat laporan masih berstatus `Draf`.
  - Jika laporan `Ditolak` lalu diperbaiki dan dikirim ulang (*resubmit*), nomor laporan awal tetap dipertahankan (tidak menerbitkan nomor baru).

---

### 2.4 ID Pelapor / Pengguna
- **Nama Teknis / Database**: `user_id` / `nama_pelapor`
- **Definisi Resmi**: Identitas akun pengguna terdaftar yang menyusun dan mengirimkan dokumen laporan.
- **Tujuan & Fungsi**: Menegakkan prinsip akuntabilitas data, membatasi hak pengubahan hanya kepada pemilik laporan (*ownership check*), dan menyusun rekap kinerja lapangan petugas per wilayah tugas.
- **Format Data**: Integer Tak Bertanda (*Foreign Key `users.id`*).
- **Contoh Nilai**: `2` (terhubung ke username: `petugas01`, nama: `Budi Santoso, S.P.`)
- **Aturan & Keterbatasan**: Ditetapkan secara otomatis oleh server dari sesi login aktif; dilarang keras menerima input manual dari formulir client.

---

### 2.5 Status Laporan
- **Nama Teknis / Database**: `status`
- **Definisi Resmi**: Keadaan administratif terkini dari dokumen laporan dalam alur verifikasi sistem JAGAPADI.
- **Tujuan & Fungsi**: Mengontrol hak edit pelapor, menentukan keterlibatan data dalam agregasi statistik dinas, dan memicu notifikasi ke perangkat pengguna.
- **Format Data**: Pilihan Terbatas (*Enumeration*).
- **Nilai Sah**: `Draf`, `Submitted`, `Diverifikasi`, `Ditolak`, `Diarsipkan`.
- **Contoh Nilai**: `Submitted` (di antarmuka pengguna dapat dilabeli sebagai *Dikirim*).
- **Aturan & Keterbatasan**: 
  - Status `Draf` hanya dapat diubah menjadi `Submitted` oleh pemilik laporan.
  - Perubahan ke `Diverifikasi`, `Ditolak`, dan `Diarsipkan` hanya dapat dilakukan oleh role `admin`.
  - Setiap perubahan status wajib mencatat entri riwayat pada tabel audit `laporan_status_history`.

---

### 2.6 Foto Dokumentasi Lapangan
- **Nama Teknis / Database**: `foto` (file input) / `foto_url` (path penyimpanan)
- **Definisi Resmi**: Berkas visual autentik yang diambil di lokasi lahan pertanian sebagai bukti faktual kondisi tanaman, OPT, saluran irigasi, atau sarana pertanian.
- **Tujuan & Fungsi**: Menyediakan bukti visual primer bagi verifikator untuk mengonfirmasi kebenaran serangan OPT atau kerusakan saluran tanpa harus selalu melakukan kunjungan fisik langsung.
- **Format Data**: File Gambar / Dokumen Path (`VARCHAR(300)`). Ekstensi yang didukung: `.jpg`, `.jpeg`, `.png`, `.webp`.
- **Contoh Nilai**: `public/uploads/laporan/2026/08/lh_2_1786495909_a1b2c3d4.jpg`
- **Aturan & Keterbatasan**: 
  - Wajib diunggah saat pengiriman laporan resmi (*submit*).
  - Ukuran file maksimal 5 MB.
  - Wajib divalidasi menggunakan deteksi tipe konten asli (*magic bytes MIME inspection* via `finfo`).
  - Nama file diacak di server dan disimpan dalam direktori yang dilindungi `.htaccess` (larangan eksekusi skrip).

---

### 2.7 Catatan Lapangan / Deskripsi Rinci
- **Nama Teknis / Database**: `catatan` / `deskripsi`
- **Definisi Resmi**: Uraian teks naratif bebas dari petugas yang memuat konteks tambahan mengenai gejala spesifik, tindakan awal yang telah dilakukan petani, riwayat perlakuan lahan, atau kondisi agroklimat mikro di sekitar petak amatan.
- **Tujuan & Fungsi**: Melengkapi data kuantitatif dengan konteks kualitatif lapangan untuk mempermudah diagnosa rekomendasi perlindungan tanaman.
- **Format Data**: Teks Bebas (`TEXT` / Multibyte String).
- **Contoh Nilai**: `"Ditemukan koloni nimfa wereng batang coklat instar 2 dan 3 pada pangkal batang padi varietas Inpari 32, umur tanaman 45 HST. Petani telah dihimbau untuk mengeringkan lahan secara berselang."`
- **Aturan & Keterbatasan**: Panjang teks maksimal 5.000 karakter (`mb_strlen`). Disimpan dalam format teks asli (tanpa tags HTML berbahaya) dan di-*escape* saat ditampilkan di browser.

---

### 2.8 Administrator Verifikator, Waktu & Catatan Verifikasi
- **Nama Teknis / Database**: `verified_by`, `verified_at`, `catatan_verifikasi`
- **Definisi Resmi**: Data audit verifikasi yang mencatat identitas verifikator, stempel waktu persetujuan/penolakan, serta alasan administratif dari tindakan tersebut.
- **Tujuan & Fungsi**: Memberikan umpan balik resmi kepada petugas lapangan jika laporan ditolak, serta menjadi bukti audit legalitas pengesahan data statistik daerah.
- **Format Data**: 
  - `verified_by`: Integer (`users.id`)
  - `verified_at`: Timestamp / Datetime (`YYYY-MM-DD HH:MM:SS`)
  - `catatan_verifikasi`: Teks Bebas (`TEXT`)
- **Contoh Nilai**:
  - `verified_by`: `1` (Admin Dinas Pertanian)
  - `verified_at`: `2026-08-20 14:30:00`
  - `catatan_verifikasi`: `"Disetujui. Rekomendasi pengendalian agens hayati Beauveria bassiana telah dikoordinasikan dengan Brigade Proteksi Tanaman."`
- **Aturan & Keterbatasan**: Hanya dapat diisi atau diperbarui oleh sistem backend melalui sesi otorisasi role `admin`.

---

## 3. VARIABEL SPASIAL, GEOGRAFIS & LOKASI KERJA (GEOTAGGING)

Seluruh laporan di JAGAPADI berbasis spasial (*geotagged*) untuk memungkinkan pemetaan peta sebaran titik (*point map*), analisis klaster serangan hama, dan pembuatan peta tematik poligon kecamatan.

---

### 3.1 Kabupaten
- **Nama Teknis / Database**: `kabupaten_id` / `nama_kabupaten` / `kabupaten_kota`
- **Definisi Resmi**: Wilayah administratif tingkat II yang menjadi lingkup yurisdiksi pelaporan pertanian.
- **Tujuan & Fungsi**: Menjadi entitas induk hierarki spasial, filter regional dashboard, dan kunci relasi data statistik BPS.
- **Format Data**: Integer Tak Bertanda (*Foreign Key `master_kabupaten.id`*) atau Kode BPS (`VARCHAR(10)`).
- **Nilai Sah & Standar**: `3509` / `Kabupaten Jember` (Induk utama sistem JAGAPADI).
- **Aturan & Keterbatasan**: Wajib terisi dan terdaftar di tabel `master_kabupaten`.

---

### 3.2 Kecamatan
- **Nama Teknis / Database**: `kecamatan_id` / `nama_kecamatan`
- **Definisi Resmi**: Pembagian wilayah administratif di bawah kabupaten yang menjadi basis penetapan wilayah kerja petugas BPP (Balai Penyuluhan Pertanian).
- **Tujuan & Fungsi**: Pengelompokan agregasi utama pada peta zonasi bahaya, statistik luas panen, dan perankingan kecamatan paling rawan serangan hama.
- **Format Data**: Integer Tak Bertanda (*Foreign Key `master_kecamatan.id`*).
- **Contoh Nilai**: `12` (Kecamatan Wuluhan), `5` (Kecamatan Ambulu), `18` (Kecamatan Tanggul).
- **Aturan & Keterbatasan**: Wajib menjadi turunan resmi (*child*) dari kabupaten yang dipilih.

---

### 3.3 Desa / Kelurahan
- **Nama Teknis / Database**: `desa_id` / `nama_desa`
- **Definisi Resmi**: Satuan wilayah administratif terkecil setingkat desa atau kelurahan tempat hamparan sawah atau lokasi kejadian berada.
- **Tujuan & Fungsi**: Menentukan kepemilikan spasial terkecil untuk penyaluran bantuan sarana dan penugasan regu pengendali hama tingkat desa.
- **Format Data**: Integer Tak Bertanda (*Foreign Key `master_desa.id`*).
- **Contoh Nilai**: `145` (Desa Dukuhdempit), `88` (Desa Karanganyar).
- **Aturan & Keterbatasan**: Wajib menjadi turunan resmi dari kecamatan yang dipilih (*hierarchical integrity validation*).

---

### 3.4 Alamat Lengkap & Nama Lokasi Lahan
- **Nama Teknis / Database**: `alamat_lengkap`, `lokasi`
- **Definisi Resmi**: Deskripsi alamat berbasis penamaan lokal manusia yang mencakup nama blok persawahan, nomor petak, kelompok tani (Poktan), atau tanda pengenal medan terdekat.
- **Tujuan & Fungsi**: Memandu petugas teknis atau brigade lapangan saat menuju titik lokasi fisik di lapangan.
- **Format Data**: String Teks (`VARCHAR(255)` / `VARCHAR(300)`).
- **Contoh Nilai**: 
  - `alamat_lengkap`: `"Blok Sawah Lor, RT 02 / RW 05, Hamparan Poktan Tani Makmur"`
  - `lokasi`: `"Persawahan Sumber Salak"`
- **Aturan & Keterbatasan**: Maksimal 300 karakter. Disarankan menyertakan nama kelompok tani untuk mempermudah verifikasi.

---

### 3.5 Latitude (Garis Lintang) & Longitude (Garis Bujur)
- **Nama Teknis / Database**: `latitude`, `longitude`
- **Definisi Resmi**: Koordinat geografis titik presisi lokasi kejadian di permukaan bumi yang dinyatakan dalam derajat desimal (*Decimal Degrees*) berbasis datum WGS 84.
- **Tujuan & Fungsi**: Menempatkan penanda (*pin marker*) secara akurat pada peta Leaflet/OpenStreetMap, menghitung radius keterpaparan serangan hama terhadap sawah sekitarnya, serta analisis klaster spasial.
- **Format Data**: Angka Desimal Presisi Tinggi (`DECIMAL(10, 7)`).
- **Contoh Nilai Jember**:
  - `latitude`: `-8.2541200` (Bertanda negatif karena terletak di Belahan Bumi Selatan / LS).
  - `longitude`: `113.6124500` (Bertanda positif karena terletak di Belahan Bumi Timur / BT).
- **Aturan & Keterbatasan**: 
  - Batas Rentang Sah Global: Lintang `-90.0` s.d. `90.0`, Bujur `-180.0` s.d. `180.0`.
  - Batas Geografis Kabupaten Jember: Lintang sekitar `-8.55` s.d. `-8.00`, Bujur sekitar `113.40` s.d. `114.05`.
  - Wajib diisi berpasangan (tidak boleh hanya mengisi salah satu).
  - Pada laporan berstatus Draf koordinat boleh kosong, namun pada status `Submitted` koordinat wajib valid.

---

## 4. VARIABEL LAPORAN HAMA & PENYAKIT TANAMAN (OPT)

Modul ini digunakan oleh Petugas Pengamat Organisme Pengganggu Tumbuhan (POPT) dan PPL untuk melaporkan serangan hama, penyakit, atau gulma pada pertanaman padi dan palawija.

---

### 4.1 Jenis OPT / Master OPT
- **Nama Teknis / Database**: `master_opt_id` / `nama_opt`
- **Definisi Resmi**: Jenis organisme pengganggu tumbuhan (hama, patogen penyebab penyakit, atau gulma) spesifik yang menyerang tanaman berdasarkan klasifikasi taksonomi resmi dinas.
- **Tujuan & Fungsi**: Mengidentifikasi agen perusak tanaman, mengaitkan pengamatan dengan Ambang Ekonomi (*ETL*) acuan, dan memicu rekomendasi teknis pengendalian spesifik.
- **Format Data**: Integer Tak Bertanda (*Foreign Key `master_opt.id`*).
- **Contoh Nilai**:
  - `1` (Wereng Batang Coklat / *Nilaparvata lugens*)
  - `2` (Penggerek Batang Padi / *Scirpophaga incertulas*)
  - `3` (Penyakit Blas / *Pyricularia oryzae*)
  - `4` (Tikus Sawah / *Rattus argentiventer*)
  - `5` (Hawar Daun Bakteri / *Xanthomonas oryzae*)
- **Aturan & Keterbatasan**: Wajib memilih dari data master aktif yang disediakan; tidak boleh mengetik nama OPT secara bebas di luar master.

---

### 4.2 Tingkat Keparahan Serangan
- **Nama Teknis / Database**: `tingkat_keparahan`
- **Definisi Resmi**: Kategori penilaian kualitatif terstandarisasi yang menggambarkan intensitas kerusakan fisik pada tegakan tanaman atau potensi kehilangan hasil akibat serangan OPT.
- **Tujuan & Fungsi**: Mengklasifikasikan tingkat kegawatan situasi, menentukan kode warna penanda pada peta (*hijau = Ringan, kuning/oranye = Sedang, merah = Berat*), dan memicu mobilisasi bantuan darurat pestisida/agens hayati.
- **Format Data**: Pilihan Terbatas (*Enumeration*).
- **Nilai Sah & Konsep Penilaian**:
  - **`Ringan`**: Gejala kerusakan tanaman < 25%, populasi OPT masih di bawah atau mendekati ambang toleransi, tanaman masih mampu melakukan kompensasi pertumbuhan.
  - **`Sedang`**: Kerusakan tanaman antara 25% – 50%, populasi OPT telah mencapai ambang ekonomi, diperlukan tindakan pengendalian aktif segera.
  - **`Berat`**: Kerusakan tanaman > 50% hingga terjadi kematian tanaman/puso, populasi OPT melimpah, memerlukan penanganan terpadu skala hamparan luas.
- **Contoh Nilai**: `Sedang`
- **Aturan & Keterbatasan**: Wajib diisi saat pengiriman. Jika dipilih `Berat`, variabel *Populasi / Intensitas* wajib diisi angka riil hasil pengamatan.

---

### 4.3 Populasi / Intensitas Serangan
- **Nama Teknis / Database**: `populasi`
- **Definisi Resmi**: Kerapatan kuantitatif rata-rata individu hama per unit sampel amatan atau persentase intensitas serangan penyakit pada rumpun/daun contoh.
- **Tujuan & Fungsi**: Menjadi dasar kalkulasi ilmiah apakah serangan hama telah melampaui Ambang Pengendalian Ekonomi (*Economic Threshold Level / ETL*), serta bahan kalkulasi tren laju perkembangbiakan OPT.
- **Format Data**: Angka Desimal Positif (`DECIMAL(10, 2)`).
- **Contoh Nilai**:
  - `18.50` (pada OPT Wereng: berarti rata-rata 18,5 ekor per rumpun amatan).
  - `12.00` (pada Penyakit Blas: berarti 12% daun terserang bercak).
  - `3.00` (pada Penggerek Batang: berarti 3 kelompok telur per m² atau 3% sundep).
- **Aturan & Keterbatasan**: 
  - Nilai harus $\ge 0$.
  - Satuan ukuran populasi mengikuti atribut `satuan_etl` dari jenis OPT yang dipilih (tidak boleh diasumsikan sebagai hektar lahan).
  - Pada formulir terintegrasi, angka luas serangan (Ha) tidak boleh melebihi angka populasi jika satuannya bertaraf persentase.

---

### 4.4 Luas Serangan Hama
- **Nama Teknis / Database**: `luas_serangan`
- **Definisi Resmi**: Total luasan hamparan sawah/lahan yang secara nyata mengalami gejala serangan OPT pada waktu pengamatan, dinyatakan dalam satuan Hektar (Ha).
- **Tujuan & Fungsi**: Menghitung akumulasi total luasan sawah terdampak di tingkat desa, kecamatan, dan kabupaten guna pelaporan neraca perlindungan tanaman ke tingkat provinsi/kementerian.
- **Format Data**: Angka Desimal Positif (`DECIMAL(8, 2)`).
- **Contoh Nilai**: `2.50` (berarti 2,5 hektar sawah terserang).
- **Aturan & Keterbatasan**: 
  - Rentang nilai sah: `0.01` s.d. `9999.99` Ha.
  - Luas serangan yang dilaporkan tidak boleh melebihi luas baku total hamparan sawah di blok/desa tersebut.

---

### 4.5 ETL Acuan & Satuan ETL
- **Nama Teknis / Database**: `etl_acuan`, `satuan_etl`
- **Definisi Resmi**: Nilai ambang batas ekonomi (*Economic Threshold Level*) baku dan satuan pengukurannya yang bersumber dari pedoman teknis Direktorat Perlindungan Tanaman Pangan untuk masing-masing spesies OPT.
- **Tujuan & Fungsi**: Menjadi angka pembanding otomatis di sistem (*benchmark*). Jika `populasi > etl_acuan`, sistem secara otomatis menyalakan indikator peringatan bahaya (*Alert: Melampaui ETL*).
- **Format Data**: 
  - `etl_acuan`: Angka Desimal (`DECIMAL(10, 2)`)
  - `satuan_etl`: String Kode Satuan (`VARCHAR(50)`)
- **Contoh Nilai**:
  - Wereng Batang Coklat: `etl_acuan` = `10.00`, `satuan_etl` = `ekor/rumpun`
  - Penggerek Batang (Fase Vegetatif): `etl_acuan` = `5.00`, `satuan_etl` = `% anakan terpotong (sundep)`
  - Tikus Sawah: `etl_acuan` = `2.00`, `satuan_etl` = `% liang aktif / rumpun rusak`
- **Aturan & Keterbatasan**: Terbaca otomatis dari tabel referensi `master_opt`, tidak diinput manual oleh petugas pelapor.

---

## 5. VARIABEL LAPORAN KONDISI & JARINGAN IRIGASI

Modul ini mencatat kondisi fisik infrastruktur jaringan irigasi dan ketersediaan debit pasokan air irigasi pertanian di tingkat saluran primer, sekunder, dan tersier.

---

### 5.1 Nama Saluran & Daerah Irigasi (DI)
- **Nama Teknis / Database**: `nama_saluran`, `daerah_irigasi`
- **Definisi Resmi**: Nomenklatur resmi saluran pembawa air irigasi beserta nama Daerah Irigasi (DI) atau bendung pengatur yang menaunginya.
- **Tujuan & Fungsi**: Mengidentifikasi aset jaringan air irigasi yang mengalami kendala guna penyusunan prioritas rehabilitasi infrastruktur pertanian.
- **Format Data**: Teks Singkat (`VARCHAR(150)` / `VARCHAR(200)`).
- **Contoh Nilai**: 
  - `nama_saluran`: `"Saluran Sekunder Bedadung Hilir Ruas BD-4"`
  - `daerah_irigasi`: `"DI Bedadung (Kapasitas Layanan 9.000 Ha)"`
- **Aturan & Keterbatasan**: `nama_saluran` wajib diisi minimal 3 karakter; tidak boleh disingkat secara ambigu.

---

### 5.2 Jenis / Tingkat Saluran
- **Nama Teknis / Database**: `jenis_saluran`
- **Definisi Resmi**: Hierarki klasifikasi teknis saluran irigasi dalam sistem pembagian air irigasi.
- **Tujuan & Fungsi**: Mengelompokkan wewenang pengelolaan (Pusat, Provinsi, Kabupaten, atau P3A/Kelompok Tani Desa).
- **Format Data**: Pilihan Terbatas (*Enumeration*).
- **Nilai Sah**: 
  - `Primer` (Saluran utama penyadap air dari bendung induk)
  - `Sekunder` (Saluran pembagi air ke petak-petak tersier)
  - `Tersier` (Saluran distribusi langsung ke kuarter/petak sawah petani)
- **Contoh Nilai**: `Sekunder`
- **Aturan & Keterbatasan**: Wajib dipilih sesuai nomenklatur jaringan irigasi dinas PU Pengairan / Pertanian.

---

### 5.3 Ketersediaan Debit Air
- **Nama Teknis / Database**: `debit_air`
- **Definisi Resmi**: Tingkat kecukupan volume aliran air yang mengalir pada saluran irigasi pada saat dilakukan pengamatan visual.
- **Tujuan & Fungsi**: Menjadi indikator dini potensi kekeringan (*water scarcity*) atau krisis pembagian air pada masa tanam aktif.
- **Format Data**: Pilihan Terbatas Kualitatif (*Enumeration*) atau Numerik Liter/Detik pada tabel operasional bendung.
- **Nilai Sah Lapangan**:
  - `Cukup` (Volume air memenuhi kebutuhan seluruh petak tersier terlayani)
  - `Kurang` (Volume air menyusut, terjadi giliran pembagian air)
  - `Kering` (Saluran tidak berair sama sekali / mengering)
- **Contoh Nilai**: `Kurang`
- **Aturan & Keterbatasan**: Diisi berdasarkan kondisi faktual saat inspeksi lapangan.

---

### 5.4 Kondisi Fisik Saluran / Bangunan
- **Nama Teknis / Database**: `kondisi_fisik`
- **Definisi Resmi**: Tingkat keutuhan konstruksi fisik dinding saluran, tanggul, pintu air (*sluice gate*), gorong-gorong, atau bangunan bagi/sadap.
- **Tujuan & Fungsi**: Menentukan derajat kerusakan fisik untuk pemetaan anggaran pemeliharaan rutin maupun perbaikan darurat pasca bencana.
- **Format Data**: Pilihan Terbatas (*Enumeration*).
- **Nilai Sah**: 
  - `Bagus` / `Baik` (Konstruksi utuh, tidak ada kebocoran atau sedimentasi parah)
  - `Sedang` / `Rusak Ringan` (Terjadi retak rambut, sedimentasi lumpur sedang, atau rembesan kecil)
  - `Tidak Bagus` / `Rusak` / `Rusak Berat` (Tanggul longsor, pasangan batu ambrol, pintu air rusak/hilang, saluran putus)
- **Contoh Nilai**: `Rusak Ringan`
- **Aturan & Keterbatasan**: Wajib disertai foto bukti kerusakan pada bagian yang mengalami kendala fisik.

---

### 5.5 Luas Layanan Sawah
- **Nama Teknis / Database**: `luas_layanan` / `luas_sawah`
- **Definisi Resmi**: Total luas area persawahan fungsional yang menggantungkan pasokan air irigasinya dari saluran atau daerah irigasi yang dilaporkan, dinyatakan dalam satuan Hektar (Ha).
- **Tujuan & Fungsi**: Mengukur besaran dampak risiko kekeringan atau gagal panen jika saluran tersebut mengalami kerusakan atau kekeringan total.
- **Format Data**: Angka Desimal Positif (`DECIMAL(10, 2)`).
- **Contoh Nilai**: `450.00` (berarti saluran melayani 450 Ha sawah).
- **Aturan & Keterbatasan**: Nilai harus $> 0$.

---

### 5.6 Aksi Dilakukan & Status Perbaikan
- **Nama Teknis / Database**: `aksi_dilakukan`, `status_perbaikan`
- **Definisi Resmi**: Tindakan mitigasi yang telah diambil di lapangan (misal: kerja bakti pengerukan sedimentasi, penambalan darurat dengan karung pasir) serta status tindak lanjut penanganannya.
- **Tujuan & Fungsi**: Memantau partisipasi P3A/petani dan melacak apakah kerusakan telah selesai ditangani oleh dinas terkait.
- **Format Data**: Teks Bebas (`VARCHAR(255)`) & Pilihan Terbatas (`belum_ditangani`, `proses_perbaikan`, `selesai`).
- **Contoh Nilai**: `"Pemasangan bronjong kawat darurat bersama HIPPA setempat."`

---

## 6. VARIABEL LAPORAN SEKTORAL TAMBAHAN (PUPUK, PANEN, CUACA, ALAT & SARANA)

Variabel-variabel ini mencakup aspek budidaya, agroklimat mikro, dan mekanisasi pertanian.

---

### 6.1 Variabel Laporan Pemupukan (Pupuk)
Mencatat aplikasi pupuk di tingkat pertanaman atau ketersediaan stok pupuk bersubsidi/non-subsidi di kelompok tani.

| Variabel | Nama Sistem | Format Data | Definisi & Fungsi | Contoh Nilai | Aturan Khusus |
|---|---|---|---|---|---|
| **Jenis Pupuk** | `jenis_pupuk` | Teks / Whitelist | Nama formula atau jenis pupuk kimia/organik yang diaplikasikan. | `Urea`, `NPK Phonska`, `SP-36`, `Pupuk Organik Granul` | Wajib diisi saat submit. |
| **Dosis Aplikasi** | `dosis` | Angka Desimal | Takaran atau kuantum pupuk yang diberikan per satuan luas amatan. | `250.00` | Harus berupa angka $> 0$. |
| **Satuan Dosis** | `satuan_dosis` | Teks Pendek | Satuan ukuran takaran dosis pupuk. | `kg/ha`, `kuintal/ha`, `gram/tanaman` | Wajib konsisten dengan angka dosis. |
| **Metode Aplikasi** | `metode_aplikasi` | Pilihan / Teks | Cara pemberian pupuk pada tanaman/tanah. | `Tebar`, `Kocor`, `Tugal / Benam`, `Semprot Daun (Foliar)` | Menjelaskan efisiensi serapan hara. |
| **OPT Terkait** | `master_opt_id` | Integer (FK) | Relasi jika pemupukan dilakukan untuk pemulihan pasca serangan hama. | `1` (Wereng) atau `NULL` | Bersifat opsional. |

---

### 6.2 Variabel Laporan Panen Lapangan (Produksi Tingkat Petani)
Mencatat hasil ubinan atau realisasi panen padi dan palawija aktual di tingkat petak sawah.

| Variabel | Nama Sistem | Format Data | Definisi & Fungsi | Contoh Nilai | Aturan Khusus |
|---|---|---|---|---|---|
| **Komoditas** | `komoditas` | Teks / Pilihan | Jenis tanaman pangan yang dipanen beserta varietasnya. | `Padi Inpari 32`, `Padi Ciherang`, `Jagung Hibrida Pioneer` | Wajib diisi saat submit. |
| **Luas Panen** | `luas_panen` | Angka Desimal (Ha) | Luas petak sawah yang telah selesai dipanen secara riil. | `1.75` | Nilai dalam satuan Hektar, $> 0$. |
| **Volume Hasil Panen** | `volume_panen` | Angka Desimal | Total berat kotor/bersih biomassa panen yang diperoleh. | `12.25` | Angka numerik $> 0$. |
| **Satuan Hasil** | `satuan` | Pilihan Terbatas | Satuan berat hasil panen. | `ton`, `kuintal`, `kg` | Standar resmi: `ton GKP` atau `ton GKG`. |
| **Harga per Unit** | `harga_per_unit` | Angka Moneter | Nilai transaksi jual beli gabah/komoditas di tingkat petani pada saat panen. | `6500.00` (berarti Rp 6.500 per kg) | Dinyatakan dalam mata uang Rupiah. |

---

### 6.3 Variabel Laporan Cuaca & Iklim Mikro Lapangan
Mencatat parameter meteorologi aktual di lokasi pengamatan lahan pertanian.

| Variabel | Nama Sistem | Format Data | Definisi & Fungsi | Contoh Nilai | Aturan Khusus |
|---|---|---|---|---|---|
| **Kondisi Cuaca** | `kondisi_cuaca` | Pilihan Terbatas | Keadaan visual atmosfer saat observasi lapangan. | `Cerah`, `Berawan`, `Hujan Ringan`, `Hujan Lebat`, `Angin Kencang` | Wajib dipilih. |
| **Suhu Udara** | `suhu` | Angka Desimal (°C) | Derajat panas udara di sekitar pertanaman. | `31.50` | Rentang wajar: $18.0^\circ\text{C} - 40.0^\circ\text{C}$. |
| **Kelembaban Udara** | `kelembaban` | Angka Desimal (%) | Persentase kejenuhan uap air di udara. | `85.00` | Rentang: $0\% - 100\%$. Kelembaban tinggi memicu penyakit jamur/bakteri. |
| **Curah Hujan** | `curah_hujan` | Angka Desimal (mm) | Tebal air hujan yang tertampung per satuan waktu. | `24.00` | Nilai $\ge 0$ mm. |
| **Kecepatan Angin** | `kecepatan_angin` | Angka Desimal | Laju pergerakan udara horizontal. | `15.00` (km/jam) | Nilai $\ge 0$. |
| **Arah Angin** | `arah_angin` | Teks / Derajat | Arah asal hembusan angin. | `Timur Laut`, `Selatan`, `225°` | Mengetahui arah penyebaran spora/hama. |

---

### 6.4 Variabel Laporan Alat & Sarana Pertanian (Alsintan)
Mencatat inventarisasi, penyaluran bantuan, dan status kelayakan operasional mesin pertanian.

| Variabel | Nama Sistem | Format Data | Definisi & Fungsi | Contoh Nilai | Aturan Khusus |
|---|---|---|---|---|---|
| **Jenis Sarana** | `jenis_sarana` | Pilihan / Teks | Kategori fungsional alat mesin pertanian. | `Traktor Roda 2`, `Combine Harvester`, `Pompa Air`, `Transplanter` | Wajib diisi. |
| **Nama Alat / Merek** | `nama_alat` | Teks | Merek dagang, tipe spesifik, atau nomor seri alsintan. | `Quick Kubota G1000`, `Yanmar AW70V` | Minimal 3 karakter. |
| **Jumlah Unit** | `jumlah` | Integer Positif | Banyaknya unit alsintan yang dilaporkan. | `2` (unit) | Bilangan bulat $\ge 1$. |
| **Kondisi Alat** | `kondisi` | Pilihan Terbatas | Status kesiapan fisik dan operasional mesin. | `Baik`, `Rusak Ringan`, `Rusak Berat`, `Tidak Beroperasi` | Menentukan kebutuhan suku cadang/bengkel. |

---

## 7. VARIABEL LAPORAN DINAMIS TERINTEGRASI (LAPORAN LAINNYA)

Pada runtime web terintegrasi, laporan dinamis disimpan dengan kerangka tabel `laporan_lainnya` yang menampung isian khusus dalam atribut terstruktur `data_json`.

### 7.1 Atribut Kerangka Utama
- **`id`** (`BIGINT`): Identifier unik basis data.
- **`kode_laporan`** (`VARCHAR(50)`): Kode registrasi alternatif laporan lainnya.
- **`jenis_id`** (`INT`): Referensi jenis laporan pada `master_jenis_laporan`.
- **`judul`** (`VARCHAR(255)`): Judul ringkas laporan kejadian pertanian.
- **`tanggal_kejadian`** (`DATE`): Waktu kalender terjadinya peristiwa di lahan.
- **`user_id`, `kabupaten_id`, `kecamatan_id`, `desa_id`**: Kunci relasi pengguna dan hierarki wilayah kerja.
- **`lokasi`, `latitude`, `longitude`**: Koordinat dan alamat lokasi geotagging.
- **`deskripsi`** (`TEXT`): Catatan narasi detail pengamatan.
- **`foto_url`** (`VARCHAR(255)`): Path file bukti foto lapangan.
- **`status`** (`ENUM`): Status dokumen (`draft`, `published`, `archived` / `Draf`, `Submitted`, `Diverifikasi`).

### 7.2 Kamus Variabel Dinamis per Sub-Jenis (`data_json`)

#### A. Penanaman Bibit Baru (`bibit_baru`)
- **`nama_varietas`** (*Teks, String*): Varietas benih yang ditanam (Contoh: `"Inpari 32 HDB"`).
- **`jumlah_bibit`** (*Angka/Teks*): Volume atau bobot benih yang disebar/ditanam (Contoh: `"50 kg"` atau `50`).
- **`sumber_bibit`** (*Teks*): Asal perolehan benih (Contoh: `"Bantuan Mandiri Benih Dinas Pertanian"`).

#### B. Pembangunan / Operasional Rumah Kaca (`rumah_kaca`)
- **`jumlah_unit`** (*Integer*): Banyaknya bangunan green house yang beroperasi (Contoh: `4`).
- **`luas_m2`** (*Angka Desimal*): Luas total lantai bangunan rumah kaca dalam meter persegi (Contoh: `800.00`).
- **`komoditas`** (*Teks*): Jenis komoditas bernilai tinggi yang dibudidayakan (Contoh: `"Melon Hidroponik / Benih Cabai"`).

#### C. Bantuan Sarana & Alsintan (`bantuan_alsintan`)
- **`nama_alat`** (*Teks*): Tipe dan model alat mesin yang diserahkan (Contoh: `"Hand Traktor Roda 2 Singkal"`).
- **`jumlah`** (*Integer*): Banyaknya unit yang diserahterimakan (Contoh: `1`).
- **`sumber_bantuan`** (*Teks*): Nama program/institusi pemberi bantuan (Contoh: `"APBD Kabupaten Jember TA 2026"`).

#### D. Kerusakan Akibat Cuaca Ekstrem (`kerusakan_cuaca`)
- **`jenis_cuaca`** (*Teks/Pilihan*): Fenomena cuaca penyebab kerusakan (Contoh: `"Banjir Luapan Sungai"`, `"Angin Puting Beliung"`, `"Kekeringan Ekstrem"`).
- **`luas_terdampak_ha`** (*Angka Desimal*): Luas total sawah yang mengalami gagal panen atau rusak (Contoh: `15.50` Ha).

---

## 8. VARIABEL MASUKAN, SARAN, DAN ADUAN (FEEDBACK)

Modul Feedback (`/feedback` dan `/feedback/admin-summary`) berfungsi sebagai kanal komunikasi dua arah antara Petugas Lapangan dengan Administrator Dinas terkait kendala aplikasi, masukan fitur, maupun aduan kendala budidaya.

---

### 8.1 Detail Variabel Modul Feedback

| Variabel | Nama Sistem | Format Data | Definisi Resmi & Fungsi | Nilai Sah / Contoh | Aturan Khusus |
|---|---|---|---|---|---|
| **ID Masukan** | `id` | Bigint (PK) | Nomor unik identifikasi feedback di database. | `105` | Diterbitkan otomatis oleh sistem. |
| **Pelapor** | `user_id` | Integer (FK) | ID akun petugas yang menyampaikan feedback. | `2` (Petugas Lapangan 01) | Diambil dari sesi login aktif di server. |
| **Jenis Masukan** | `jenis_feedback` | Pilihan Terbatas | Kategori tema pengaduan/saran yang diajukan. | `bug` (Laporan Error), `fitur_baru` (Permintaan Fitur), `peningkatan` (Saran Penyempurnaan) | Wajib dipilih dari whitelist. |
| **Judul Masukan** | `judul` | String Teks | Intisari atau pokok permasalahan saran. | `"Tombol Ambil Lokasi GPS Lambat Terbaca"` | Wajib diisi, panjang 5 s.d. 255 karakter (`mb_strlen`). |
| **Deskripsi Rinci** | `deskripsi` | Teks Bebas (`TEXT`) | Penjelasan lengkap, kronologi kendala, atau usulan perbaikan. | `"Saat berada di Desa Andongsari sinyal lemah menyebabkan form tidak membaca GPS otomatis..."` | Wajib diisi, panjang 20 s.d. 5.000 karakter (`mb_strlen`). |
| **Tingkat Prioritas** | `prioritas` | Pilihan Terbatas | Derajat urgensi penanganan masalah menurut pelapor. | `rendah`, `medium`, `tinggi` | Default sistem: `medium`. |
| **Status Penanganan** | `status` | Pilihan Terbatas | Tahap progres penanganan masukan oleh tim teknis/admin. | `diterima` (Menunggu), `dalam_proses` (Sedang Ditangani), `selesai` (Tuntas), `ditolak` (Tidak Valid) | Default: `diterima`. Hanya admin yang dapat mengubah. |
| **Lampiran Bukti** | `attachment_url` | String Path | Tangkapan layar (*screenshot*) atau berkas PDF pendukung. | `public/uploads/feedback/2026/08/fb_2_17864959_abc123.png` | Opsional, max 5 MB, validasi tipe gambar / PDF. |
| **Catatan Admin** | `admin_notes` | Teks Bebas | Tanggapan resmi atau solusi dari admin untuk pelapor. | `"Pembaruan modul offline GPS telah dirilis pada versi v1.4.2."` | Disimpan mentah, di-escape saat render tampilan. |
| **Admin Pemroses** | `processed_by` | Integer (FK) | Akun admin yang memproses dan menanggapi masukan. | `1` (Admin Sistem) | Terisi otomatis saat update status. |
| **Waktu Pemrosesan** | `processed_at` | Datetime | Waktu stempel saat status masukan diperbarui. | `2026-08-20 15:45:00` | Format standar ISO waktu. |
| **Jumlah Dukungan** | `vote_count` | Integer | Akumulasi dukungan (*upvote*) dari rekan petugas lain terhadap usulan fitur tersebut. | `12` | Sinkron otomatis dari tabel `feedback_votes`. Petugas tidak dapat vote miliknya sendiri. |

---

## 9. VARIABEL DATA STATISTIK PENDUKUNG, AGREGAT SEKTORAL & DASHBOARD ANALITIK

Variabel-variabel ini bersumber dari impor data BPS, stasiun meteorologi BMKG, scraping resmi pasar pangan, atau hasil agregasi algoritma kecerdasan buatan (*Storytelling Analytics*).

---

### 9.1 Data Pertanian BPS & Kerangka Sampel Area (KSA) Bulanan
- **`tahun`** (`INT`): Tahun kalender data statistik (Contoh: `2025`, `2026`).
- **`bulan`** (`INT`): Angka bulan data statistik `1` (Januari) s.d. `12` (Desember), atau `0` untuk tahunan.
- **`kabupaten_kota`** (`VARCHAR(100)`): Nama entitas wilayah kabupaten pencatatan BPS (Contoh: `"Kabupaten Jember"`).
- **`kode_wilayah`** (`VARCHAR(10)`): Kode wilayah terstandarisasi BPS (Contoh: `"3509"`).
- **`luas_panen`** (`DECIMAL(12, 2)`): Luas panen padi rilis resmi BPS dalam satuan Hektar (Contoh: `118540.50` Ha).
- **`produksi_gabah`** (`DECIMAL(12, 2)`): Estimasi total produksi Gabah Kering Giling (GKG) dalam satuan Ton (Contoh: `654210.00` Ton).
- **`produksi_beras`** (`DECIMAL(12, 2)`): Estimasi konversi produksi beras pangan dalam satuan Ton (Contoh: `376170.00` Ton).
- **`produktivitas`** (`DECIMAL(8, 2)`): Angka produktivitas rata-rata lahan dalam satuan Kuintal/Ha (Contoh: `55.18` Ku/Ha) atau Ton/Ha.
- **`status_data`** (`VARCHAR(20)`): Status validitas rilis data BPS (`tetap`, `sementara`, `potensi`).
- **`sumber_data_type`** (`VARCHAR(50)`): Asal metode pengambilan data (`ksa`, `resmi_webapi`, `manual`, `simulasi`).
- **`tipe_skenario`** (`VARCHAR(20)`): Skenario proyeksi data analitik (`baseline`, `optimis`, `pesimis`).

---

### 9.2 Data Produksi Gabah Lapangan (`produksi_gabah`)
- **`unique_id`** (`VARCHAR(64)`): Kunci identitas transaksi rekaman produksi di tingkat hamparan/gilingan.
- **`musim_tanam`** (`VARCHAR(20)`): Identifikasi siklus tanam (`MH` = Musim Hujan, `MK 1` = Musim Kemarau I, `MK 2` = Musim Kemarau II).
- **`varietas`** (`VARCHAR(100)`): Galur varietas benih padi yang dibudidayakan (Contoh: `"Ciherang"`, `"Inpari 32"`, `"BK Situbondo"`).
- **`luas_tanam`** & **`luas_panen`** (`DECIMAL(10, 2)`): Luas baku lahan yang ditanam dan dipanen dalam satuan Hektar.
- **`produksi_total`** (`DECIMAL(10, 2)`): Berat total timbangan gabah yang dihasilkan dalam satuan Ton GKG.
- **`kadar_air`** (`DECIMAL(5, 2)`): Persentase kelembaban biji gabah hasil panen (Contoh: `14.50`%; rentang wajar: $12\% - 25\%$).
- **`grade_kualitas`** (`VARCHAR(20)`): Klasifikasi mutu fisik gabah (`Grade A`, `Grade B`, `Grade C`, `Standard`).
- **`harga_gabah`** (`DECIMAL(10, 2)`): Harga jual gabah per kilogram di tingkat penggilingan/petani (Contoh: `Rp 6.800,00/kg`).
- **`produktivitas`** (`DECIMAL(6, 2)`): Nilai hasil bagi antara total produksi dengan luas panen (Contoh: `6.45` Ton/Ha).

---

### 9.3 Data Operasional Irigasi & Bendung (`data_irigasi`)
- **`debit_air`** (`DECIMAL(10, 2)`): Volume air sesaat yang dialirkan bendungan dalam satuan Liter per Detik (Contoh: `4500.00` L/detik).
- **`status_pintu`** (`VARCHAR(30)`): Status siaga elevasi dan bukaan pintu air (`Aman`, `Waspada`, `Kritis / Siaga Banjir`).
- **`metode_data`** (`VARCHAR(30)`): Cara pencatatan debit (`aktual` via sensor telemetri, `manual` pembacaan peilschaal, `simulasi`).

---

### 9.4 Data Parameter Cuaca Agregat BMKG (`curah_hujan`, `kecepatan_angin`)
- **`curah_hujan`** (`DECIMAL(8, 2)`): Akumulasi curah hujan harian/bulanan terukur di stasiun meteorologi dalam milimeter (`mm`). Kategori: $0\text{ mm}$ (Berawan/Cerah), $1-20\text{ mm}$ (Hujan Ringan), $21-50\text{ mm}$ (Sedang), $>50\text{ mm}$ (Lebat/Ekstrem).
- **`kecepatan_angin`** & **`kecepatan_max`** (`DECIMAL(6, 2)`): Kecepatan angin rata-rata dan hembusan puncak (*wind gust*) dalam satuan `km/jam`.
- **`arah_angin`** & **`arah_angin_desc`** (`DECIMAL(5, 2)` & `VARCHAR(50)`): Derajat azimut arah ($0^\circ - 360^\circ$) dan penamaan mata angin (Contoh: `225°` / `"Barat Daya"`).

---

### 9.5 Data Harga Komoditas Pasar (`harga_komoditas`)
- **`jenis_komoditas`** (`VARCHAR(50)`): Jenis produk pangan pokok (`gabah_kering_panen`, `gabah_kering_giling`, `beras_medium`, `beras_premium`).
- **`harga`** (`DECIMAL(10, 2)`): Rata-rata harga pasar harian di Kabupaten Jember dalam satuan Rupiah per Kilogram (`Rp/kg`).
- **`tipe_alert`** & **`persentase`**: Indikator lonjakan fluktuasi harga (`kenaikan`, `penurunan`, `stabil`) beserta persentase perubahannya dibandingkan hari sebelumnya.

---

### 9.6 Data Evaluasi Akurasi Panen (`evaluasi_akurasi`)
- **`luas_estimasi_daerah`** (`DECIMAL(12, 2)`): Angka estimasi luas panen bulanan hasil rekapitulasi mandiri dinas/petugas (Ha).
- **`luas_rilis_bps`** (`DECIMAL(12, 2)`): Angka rilis luas panen resmi metode KSA dari BPS (Ha).
- **`deviasi_absolut`** (`DECIMAL(12, 2)`): Selisih mutlak antara angka daerah dengan angka BPS ($|\text{Estimasi} - \text{BPS}|$).
- **`persentase_bias`** (`DECIMAL(5, 2)`): Persentase penyimpangan ($\frac{|\text{Estimasi} - \text{BPS}|}{\text{BPS}} \times 100\%$).
- **`status_akurasi`** (`VARCHAR(50)`): Klasifikasi mutu estimasi daerah (`Sangat Akurat` [< 5%], `Perlu Perhatian` [5% – 10%], `Bias Tinggi` [> 10%]).

---

### 9.7 Data Analisis Storytelling & Narasi Risiko Produksi (`analisis_produksi_bulanan`)
- **`total_luas_panen`** (`DECIMAL(12, 2)`): Total luas panen tervalidasi pada periode amatan (Ha).
- **`faktor_penyebab_utama`** (`VARCHAR(100)`): Variabel lingkungan yang paling dominan mempengaruhi anomali hasil panen (Contoh: `"Serangan Hama Wereng Batang Coklat"`, `"Curah Hujan Tinggi Lag-1"`).
- **`skor_risiko_cuaca`**, **`skor_risiko_hama`**, **`skor_risiko_total`** (`DECIMAL(5, 2)`): Indeks skor risiko komposit (Skala 0 – 100) yang dihitung dari bobot cuaca ekstrem dan intensitas serangan OPT.
- **`avg_curah_hujan_lag1`**, **`total_laporan_hama_lag1`**, **`laporan_hama_berat_lag1`**: Parameter lingkungan 1 bulan sebelum masa panen (*Lag-1*) untuk menangkap efek tunda (*delayed impact*) iklim terhadap pertumbuhan tanaman.
- **`narasi_otomatis`** (`TEXT`): Teks narasi analisis komprehensif yang dibangkitkan secara otomatis oleh mesin analitik.
- **`narasi_final`** (`TEXT`): Teks narasi resmi yang telah disunting, dikurasi, dan disahkan oleh Kepala Seksi Data / Statistisi Pertanian.
- **`status_analisis`** (`VARCHAR(20)`): Status publikasi narasi (`draft`, `published`, `archived`).

---

## 10. MATRIKS PERBANDINGAN CEPAT VARIABEL LAPORAN

Tabel ini merangkum perbandingan seluruh variabel utama di lintas modul laporan lapangan JAGAPADI:

| No | Nama Variabel | Kolom Database | Tipe & Format Data | Wajib Saat Submit? | Modul Pengguna |
|---|---|---|---|---|---|
| 1 | **Jenis Laporan** | `jenis_laporan` / `jenis_id` | Enumeration / ID | Ya | Seluruh Modul |
| 2 | **Nomor Laporan** | `nomor_laporan` | Alfanumerik (20) | Sistem (Auto) | Seluruh Modul Lapangan |
| 3 | **Tanggal Kejadian** | `tanggal_kejadian` / `tanggal` | Date (`YYYY-MM-DD`) | Ya | Seluruh Modul |
| 4 | **ID Pelapor** | `user_id` | Integer (FK Users) | Sistem (Auth) | Seluruh Modul |
| 5 | **Kabupaten** | `kabupaten_id` | Integer (FK Master) | Ya (Kode 3509) | Seluruh Modul |
| 6 | **Kecamatan** | `kecamatan_id` | Integer (FK Master) | Ya | Seluruh Modul |
| 7 | **Desa / Kelurahan** | `desa_id` | Integer (FK Master) | Ya | Seluruh Modul |
| 8 | **Alamat / Lokasi** | `alamat_lengkap`, `lokasi` | String (255–300) | Ya | Seluruh Modul Lapangan |
| 9 | **Latitude (Lintang)** | `latitude` | Decimal (10,7) | Ya ($-8.55$ s.d. $-8.00$) | Seluruh Modul Lapangan |
| 10 | **Longitude (Bujur)** | `longitude` | Decimal (10,7) | Ya ($113.40$ s.d. $114.05$) | Seluruh Modul Lapangan |
| 11 | **Foto Bukti** | `foto_url` | String Path (File) | Ya (Maks 5 MB) | Seluruh Modul Lapangan |
| 12 | **Catatan / Deskripsi** | `catatan` / `deskripsi` | Text (Maks 5.000 Karakter) | Opsional / Disarankan | Seluruh Modul |
| 13 | **Status Laporan** | `status` | Enumeration | Sistem (Default Draf) | Seluruh Modul |
| 14 | **Jenis OPT** | `master_opt_id` | Integer (FK Master OPT) | Ya | Laporan Hama (OPT) |
| 15 | **Tingkat Keparahan** | `tingkat_keparahan` | Enum (Ringan, Sedang, Berat) | Ya | Laporan Hama (OPT) |
| 16 | **Populasi / Intensitas** | `populasi` | Decimal (10,2) $\ge 0$ | Ya (Wajib di v1/Berat) | Laporan Hama (OPT) |
| 17 | **Luas Serangan** | `luas_serangan` | Decimal (8,2) Hektar | Ya | Laporan Hama (OPT) |
| 18 | **Nama Saluran** | `nama_saluran` | String (Min 3 Karakter) | Ya | Laporan Irigasi |
| 19 | **Jenis Saluran** | `jenis_saluran` | Enum (Primer, Sekunder, Tersier) | Ya | Laporan Irigasi |
| 20 | **Debit Ketersediaan Air** | `debit_air` | Enum (Cukup, Kurang, Kering) | Ya | Laporan Irigasi |
| 21 | **Kondisi Fisik Saluran** | `kondisi_fisik` | Enum (Bagus, Sedang, Rusak) | Ya | Laporan Irigasi |
| 22 | **Luas Layanan Sawah** | `luas_layanan` | Decimal (10,2) Hektar | Ya | Laporan Irigasi |
| 23 | **Jenis Pupuk & Dosis** | `jenis_pupuk`, `dosis` | String, Decimal | Ya | Laporan Pupuk |
| 24 | **Komoditas & Luas Panen** | `komoditas`, `luas_panen` | String, Decimal (Ha) | Ya | Laporan Panen |
| 25 | **Volume & Satuan Panen** | `volume_panen`, `satuan` | Decimal, Enum (ton/kg) | Ya | Laporan Panen |
| 26 | **Kondisi Cuaca Lapangan** | `kondisi_cuaca` | Enum | Ya | Laporan Cuaca |
| 27 | **Jenis & Kondisi Alsintan**| `jenis_sarana`, `kondisi` | String, Enum | Ya | Laporan Alat Sarana |
| 28 | **Prioritas Feedback** | `prioritas` | Enum (rendah, medium, tinggi) | Ya | Feedback Petugas |
| 29 | **Verifikator Admin** | `verified_by` | Integer (FK Users) | Sistem (Saat Verifikasi) | Seluruh Modul Lapangan |
| 30 | **Waktu Verifikasi** | `verified_at` | Datetime | Sistem (Saat Verifikasi) | Seluruh Modul Lapangan |

---

## 11. PETUNJUK & CHECKLIST PENGISIAN LAPORAN BERKUALITAS

Bagi seluruh petugas pengamat di lapangan, ikuti 9 langkah checklist berikut sebelum menekan tombol **Kirim Laporan**:

1. [x] **Tanggal Pengamatan**: Pastikan tanggal yang diinput adalah tanggal aktual saat petugas memeriksa petak sawah.
2. [x] **Hierarki Wilayah**: Pilih Kabupaten (Jember) $\rightarrow$ Kecamatan $\rightarrow$ Desa secara berurutan, lalu lengkapi nama kelompok tani / blok sawah pada alamat.
3. [x] **Titik Koordinat Presisi**: Pastikan nilai Latitude bernilai negatif (sekitar $-8.17$) dan Longitude bernilai positif (sekitar $113.70$). Gunakan tombol *Ambil Lokasi Saat Ini* di ruang terbuka.
4. [x] **Pemilihan Master Data**: Pilih nama OPT atau jenis alsintan dari menu pilihan resmi (jangan menggunakan penamaan lokal yang tidak baku).
5. [x] **Kesesuaian Populasi & Satuan**: Isi angka populasi rata-rata sesuai satuan acuan OPT (misal: ekor/rumpun untuk wereng, % daun rusak untuk blas). **Jangan menyamakan angka populasi dengan angka luas hektar**.
6. [x] **Luas Serangan Riil**: Masukkan luas sawah yang benar-benar terserang dalam satuan Hektar (bukan luas seluruh hamparan desa).
7. [x] **Foto Asli Lapangan**: Lampirkan foto jarak dekat (*close-up*) gejala serangan tanaman atau kerusakan fisik bangunan irigasi yang diambil langsung di lokasi.
8. [x] **Catatan Tindakan**: Berikan penjelasan singkat mengenai tindakan penanganan yang telah dilakukan petani setempat.
9. [x] **Kirim / Simpan Draf**: Jika jaringan seluler tidak stabil, simpan sebagai **Draf**. Setelah jaringan normal, periksa kembali kelengkapan data lalu lakukan **Kirim Laporan** (*Submit*).

---

*Dokumen Kamus Variabel ini disahkan sebagai standar acuan baku pengumpulan, pengolahan, validasi, dan penyajian data sistem informasi JAGAPADI Kabupaten Jember.*
