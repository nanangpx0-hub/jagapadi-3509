# Konsep dan Definisi (Kondef) Variabel JAGAPADI

> Buku referensi untuk petugas lapangan, admin, dan pengelola data.
>
> **JAGAPADI** = Jember Agrikultur Gapai Prestasi Digital
>
> Versi: 1.0.0 · Diverifikasi terhadap formulir, schema, dan validasi sistem: 20 Agustus 2026
>
> Nama teknis kolom (misalnya `latitude`) dicantumkan agar petugas dapat mencocokkan label di aplikasi dengan nama di laporan ekspor.

---

## Cara memakai buku ini

1. Cari nama yang terlihat di formulir (contoh: **Populasi/Intensitas**) pada [Indeks A–Z](#indeks-az-nama-tampilan).
2. Baca arti, cara mengisi, dan fungsi di sistem.
3. Perhatikan bagian **Wajib saat kirim** — draf boleh belum lengkap; laporan yang dikirim harus memenuhi syarat kirim.
4. Satuan dan pilihan nilai mengikuti yang tertulis di formulir. Jangan menukar satuan sendiri (misalnya mengisi hektar ke kolom yang meminta ekor per rumpun).

Istilah singkat:

| Istilah | Arti untuk petugas |
|---|---|
| Variabel | Setiap isian, pilihan, atau data yang disimpan sistem (tanggal, koordinat, luas, status, dan sebagainya) |
| Nama tampilan | Tulisan di layar aplikasi |
| Nama sistem | Nama kolom di database/ekspor |
| Wajib saat kirim | Harus terisi sebelum laporan dikirim (bukan draf) |
| Master data | Daftar resmi yang sudah disediakan admin (wilayah, jenis OPT) |

---

## 1. Konsep dasar yang dipakai di seluruh sistem

### 1.1 Wilayah kerja

JAGAPADI mencatat kejadian pertanian di **Kabupaten Jember** (kode wilayah BPS `3509`). Kabupaten, kecamatan, dan desa harus berjenjang: desa milik kecamatan yang dipilih, kecamatan milik kabupaten yang dipilih.

### 1.2 Dua jenis data

| Jenis | Contoh | Siapa yang mengisi |
|---|---|---|
| **Laporan lapangan** | Hama/OPT, irigasi, panen, cuaca, pupuk, alat | Petugas (web atau Android) |
| **Data pendukung / dashboard** | Curah hujan BMKG, harga gabah, luas panen BPS/KSA | Sistem, admin, atau impor resmi |

Laporan lapangan menjadi titik di peta dan angka di grafik **setelah** statusnya bukan draf (kecuali petugas melihat pekerjaan miliknya sendiri).

### 1.3 Status laporan (nilai resmi)

Nilai yang disimpan sistem:

| Nilai sistem | Label yang sering tampil | Arti untuk petugas |
|---|---|---|
| `Draf` | Draf | Belum selesai / belum dikirim. Tidak masuk statistik resmi, peta agregat, dan ekspor default |
| `Submitted` | Dikirim / Aktif / Baru masuk | Sudah dikirim. Menunggu atau sudah masuk data operasional |
| `Diverifikasi` | Terverifikasi / Aktif | Admin sudah mengesahkan (alur Backend v1) |
| `Ditolak` | Ditolak | Admin menolak; petugas pemilik dapat memperbaiki lalu mengirim ulang |
| `Diarsipkan` | Diarsipkan | Tidak lagi dihitung sebagai data aktif, tetapi tidak dihapus |

**Catatan runtime:** di web terintegrasi, sebagian alur laporan hama dapat langsung berstatus `Submitted` tanpa antrean verifikasi. Di Backend v1 (web petugas + API mobile), alur resmi adalah Draf → Dikirim → Diverifikasi/Ditolak. Petugas mengikuti status yang tampil di aplikasi yang sedang dipakai.

### 1.4 Nomor laporan

Nomor unik (contoh pola hama `LH-…`, irigasi `LI-…`) **baru dibuat saat laporan pertama kali dikirim**, bukan saat draf. Jika laporan ditolak lalu dikirim ulang, **nomor yang sama dipertahankan**.

### 1.5 Kepemilikan data

Petugas hanya melihat dan mengubah laporan **miliknya**. Identitas pelapor diambil dari akun login, bukan dari isian bebas. Admin melihat data seluruh kabupaten sesuai menu yang diizinkan.

---

## 2. Variabel lokasi dan koordinat

Bagian ini menjelaskan **Latitude (Lintang)** dan **Longitude (Bujur)** secara lengkap, karena keduanya dipakai hampir di semua formulir laporan dan peta dashboard.

### 2.1 Latitude — Lintang

| | |
|---|---|
| **Nama tampilan** | Latitude (Lintang) |
| **Nama sistem** | `latitude` |
| **Arti** | Posisi titik ke utara atau selatan garis khatulistiwa. Angka negatif berarti di belahan bumi selatan |
| **Satuan** | Derajat desimal (bukan derajat-menit-detik) |
| **Rentang sah** | −90 sampai 90 |
| **Contoh Jember** | sekitar **−8,17** (selalu bertanda minus) |
| **Cara isi** | Ketik manual, pilih di peta, atau ambil GPS ponsel/browser |
| **Fungsi** | Menempatkan pin laporan di peta; membedakan lokasi yang namanya mirip; mendukung geotagging foto kejadian |

**Cara memahami untuk petugas:** bayangkan garis mendatar di peta. Jember berada di selatan khatulistiwa, jadi lintangnya **minus**. Jika angka menjadi positif (misalnya `8,17` tanpa minus), titik akan “terlempar” ke belahan bumi utara dan tidak berada di Jember.

Ketelitian sistem sekitar 7 angka di belakang koma (`DECIMAL(10,7)`). Tidak perlu menghitung sendiri; biarkan GPS atau peta yang mengisi.

### 2.2 Longitude — Bujur

| | |
|---|---|
| **Nama tampilan** | Longitude (Bujur) |
| **Nama sistem** | `longitude` |
| **Arti** | Posisi titik ke timur atau barat garis bujur nol (Greenwich). Indonesia berada di timur, jadi angkanya positif |
| **Satuan** | Derajat desimal |
| **Rentang sah** | −180 sampai 180 |
| **Contoh Jember** | sekitar **113,70** |
| **Cara isi** | Sama seperti lintang; **lintang dan bujur harus diisi berpasangan** |
| **Fungsi** | Bersama lintang, membentuk satu titik GPS yang unik di muka bumi |

**Cara memahami untuk petugas:** bayangkan garis tegak di peta. Kabupaten Jember sekitar bujur 113–114. Jika tertukar dengan lintang (misalnya mengisi 113 di kolom lintang), titik akan salah tempat.

### 2.3 Aturan pasangan koordinat

- Isi **keduanya** atau **keduanya dikosongkan** (pada draf). Jangan hanya salah satu.
- Mode isian di web: **Manual**, **Peta interaktif**, atau **Lokasi saat ini** (perlu izin GPS).
- Akurasi GPS dapat tampil sebagai indikator; jika sinyal buruk, pindah ke tempat terbuka atau pilih titik di peta.
- Koordinat kecamatan di master wilayah dipakai sebagai acuan peta, **bukan** pengganti koordinat kejadian di lahan.

### 2.4 Lokasi tekstual (pelengkap koordinat)

| Nama tampilan | Nama sistem | Arti dan fungsi |
|---|---|---|
| Alamat lengkap | `alamat_lengkap` | Keterangan tempat yang dipahami manusia (blok, RT/RW, nama petani, patokan). Maksimal 300 karakter |
| Lokasi | `lokasi` | Nama singkat tempat (maksimal 255 karakter). Melengkapi alamat |
| Kabupaten | `kabupaten_id` | Pilih dari daftar. Saat ini hanya Jember |
| Kecamatan | `kecamatan_id` | Harus anak kabupaten yang dipilih |
| Desa/kelurahan | `desa_id` | Harus anak kecamatan yang dipilih |

Wilayah menjawab pertanyaan “di desa mana?”, koordinat menjawab “tepat di titik mana di lahan itu?”. Keduanya dibutuhkan agar peta dan rekap per kecamatan konsisten.

---

## 3. Variabel bersama semua laporan lapangan

Variabel berikut muncul berulang pada hama, irigasi, dan laporan lain.

### 3.1 Identitas dan waktu

| Nama tampilan | Nama sistem | Definisi | Fungsi |
|---|---|---|---|
| ID laporan | `id` | Nomor urut internal, dibuat sistem | Menghubungkan foto, riwayat, dan tautan detail. Bukan nomor yang dibaca petugas sehari-hari |
| Nomor laporan | `nomor_laporan` | Kode unik resmi setelah dikirim | Pencarian, surat, ekspor, notifikasi |
| Kode laporan (lainnya) | `kode_laporan` | Kode pada modul laporan lainnya (web terintegrasi) | Identitas alternatif di modul itu |
| Tanggal / tanggal kejadian | `tanggal`, `tanggal_kejadian` | Hari observasi di lapangan, format `YYYY-MM-DD` | Urutan waktu grafik, filter daftar, musim tanam |
| Pelapor | `user_id` | Akun yang membuat laporan | Hak akses: petugas hanya miliknya |
| Waktu dibuat | `created_at` | Saat pertama disimpan | Audit |
| Waktu diubah | `updated_at` | Saat terakhir disimpan | Audit |

**Cara isi tanggal:** isi tanggal **kejadian di lahan**, bukan tanggal petugas sempat membuka aplikasi di rumah (kecuali memang observasi hari itu).

### 3.2 Foto dan catatan

| Nama tampilan | Nama sistem | Definisi | Fungsi |
|---|---|---|---|
| Foto | `foto` (unggahan) → `foto_url` (penyimpanan) | Gambar bukti di lokasi. Format JPG/PNG/WEBP. File besar dapat dikompres otomatis | Bukti visual untuk verifikasi dan dokumentasi. Pada banyak alur kirim, foto **wajib** |
| Catatan | `catatan` | Teks bebas (hingga sekitar 5.000 karakter di Backend v1) | Gejala, nama petani, tindakan, cuaca saat observasi |
| Deskripsi | `deskripsi` | Teks bebas pada laporan lainnya | Sama seperti catatan, nama berbeda di modul itu |

Foto harus diambil di lokasi kejadian (bukan foto dari internet). Sistem memeriksa jenis file; unggah yang lolos disimpan dengan nama acak agar aman.

### 3.3 Verifikasi (diisi admin, bukan petugas)

| Nama tampilan | Nama sistem | Definisi |
|---|---|---|
| Diverifikasi oleh | `verified_by` | Akun admin yang mengesahkan atau menolak |
| Waktu verifikasi | `verified_at` | Waktu keputusan |
| Catatan verifikasi | `catatan_verifikasi` | Alasan tolak atau catatan admin untuk petugas |

Petugas **tidak** boleh mengisi kolom ini dari HP. Jika laporan ditolak, baca catatan verifikasi, perbaiki draf, lalu kirim ulang.

### 3.4 Metadata teknis (bukan isian petugas)

| Nama sistem | Arti |
|---|---|
| `ip_pengirim` | Alamat jaringan saat kirim; untuk audit keamanan |
| `include_draft` | Parameter filter: apakah draf ikut dihitung. Default tidak |

---

## 4. Laporan hama / OPT

Modul ini mencatat serangan **Organisme Pengganggu Tanaman** (hama, penyakit, atau gulma) di lahan.

### 4.1 OPT yang dilaporkan

| Nama tampilan | Nama sistem | Definisi | Fungsi |
|---|---|---|---|
| Jenis OPT / OPT | `master_opt_id` | Pilihan dari daftar resmi (contoh: Wereng Batang Coklat, Blast) | Mengelompokkan laporan, grafik “OPT teratas”, peringatan ETL |

Jangan mengetik nama OPT bebas jika daftar sudah tersedia. Jika OPT belum ada, laporkan ke admin agar dimasukkan ke master.

### 4.2 Tingkat keparahan

| Nama tampilan | Nama sistem | Nilai | Arti praktis |
|---|---|---|---|
| Tingkat keparahan | `tingkat_keparahan` | `Ringan`, `Sedang`, `Berat` | Penilaian ringkas kondisi tanaman/lahan |

Gunakan keparahan sesuai kondisi nyata di lahan, bukan “asal sedang”. Nilai ini mewarnai peta dan rekap sebaran.

Pada web terintegrasi, jika keparahan **Berat**, populasi/intensitas tidak boleh kosong.

### 4.3 Populasi / Intensitas

Ini variabel yang paling sering ditanyakan petugas.

| | |
|---|---|
| **Nama tampilan** | Populasi / Intensitas |
| **Nama sistem** | `populasi` |
| **Arti konsep** | Ukuran **kepadatan atau banyaknya OPT** pada satuan pengamatan yang sama dengan acuan pengendalian (ETL). Bukan “jumlah penduduk” |
| **Nilai** | Angka ≥ 0, boleh desimal |
| **Wajib saat kirim** | Ya, pada Backend v1. Pada web terintegrasi wajib jika keparahan Berat |

**Cara mengisi yang benar**

1. Lihat jenis OPT yang dipilih.
2. Ikuti **satuan ETL** OPT itu (lihat master OPT), misalnya:
   - `ekor/rumpun` — hitung rata-rata ekor wereng per rumpun contoh;
   - `%` — persentase rumpun terserang, anakan mati, atau intensitas penyakit sesuai petunjuk POPT;
   - satuan lain yang tertulis di master.
3. Isi **satu angka ringkasan** hasil pengamatan (rata-rata contoh, bukan tebakan kasar).

**Fungsi di sistem**

- Menjadi ukuran intensitas serangan di daftar laporan dan popup peta.
- Dibandingkan dengan **ETL acuan**. Jika populasi **lebih besar dari ETL**, sistem dapat menandai peringatan (ikon “melampaui ETL”).
- Masuk rata-rata populasi per tingkat keparahan di analitik.

**Hubungan dengan luas serangan di web terintegrasi**

Di formulir web terintegrasi terdapat aturan: **luas serangan (ha) tidak boleh lebih besar daripada angka populasi/intensitas**. Aturan itu mencegah angka luas yang “melebihi” nilai intensitas yang diisi di formulir yang sama. Artinya petugas harus mengisi kedua kolom secara konsisten. Jika satuan populasi adalah ekor/rumpun atau persen, **jangan menyamakan angkanya dengan hektar**; isi populasi sesuai satuan OPT, dan isi luas terpisah sebagai hektar lahan yang benar-benar terserang.

Contoh:

- Wereng, ETL 10 ekor/rumpun, hasil hitung 18 ekor/rumpun → populasi/intensitas = **18**.
- Lahan terserang 1,25 ha dari petak 2 ha → luas serangan = **1,25** (bukan 18).

### 4.4 Luas serangan

| | |
|---|---|
| **Nama tampilan** | Luas serangan (Ha) |
| **Nama sistem** | `luas_serangan` |
| **Arti** | Luas lahan yang **terserang OPT** pada observasi itu, dalam hektar |
| **Rentang** | 0 sampai 9.999,99 |
| **Fungsi** | Agregat “total hektar terserang” di dashboard, grafik, dan ekspor |

Isi luas yang benar-benar diamati, bukan luas seluruh desa. Nol hanya jika memang belum ada luasan terukur (draf).

### 4.5 ETL acuan dan satuan ETL (dari master OPT, bukan diketik petugas)

| Nama tampilan | Nama sistem | Definisi | Fungsi |
|---|---|---|---|
| ETL acuan | `etl_acuan` | Ambang Ekonomi (Economic Threshold Level): batas populasi/intensitas di mana tindakan pengendalian biasanya mulai layak | Pembanding otomatis terhadap isian populasi |
| Satuan ETL | `satuan_etl` | Satuan ambang, contoh `ekor/rumpun` atau `%` | Menjelaskan cara baca populasi |

Jika ETL kosong, sistem tidak membandingkan. Jika terisi dan populasi di atas ETL, laporan ditandai sebagai perlu perhatian.

---

## 5. Laporan irigasi (kejadian di saluran)

Laporan petugas tentang **kondisi saluran/pintu air** di lapangan, berbeda dari data debit harian bendungan yang diimpor ke dashboard.

### 5.1 Variabel khusus irigasi

| Nama tampilan | Nama sistem | Definisi | Nilai / catatan | Fungsi |
|---|---|---|---|---|
| Nama saluran | `nama_saluran` | Nama saluran yang diamati | Contoh: Saluran Primer Bondoyudo. Minimal 3 karakter di web terintegrasi | Identitas objek irigasi |
| Daerah irigasi | `daerah_irigasi` | Nama DI atau bendung terkait (Backend v1) | Teks, maksimal 200 karakter | Mengelompokkan saluran ke DI |
| Jenis saluran | `jenis_saluran` | Hierarki jaringan (web terintegrasi) | `Primer`, `Sekunder`, `Tersier` | Klasifikasi teknis |
| Debit air | `debit_air` | Ketersediaan air di saluran saat observasi | `Cukup`, `Kurang`, `Kering` | Indikator kekeringan operasional |
| Kondisi fisik / kondisi saluran | `kondisi_fisik` | Keadaan bangunan/saluran | **Backend v1:** `Bagus`, `Sedang`, `Tidak Bagus`, `Rusak`. **Web terintegrasi:** `Baik`, `Rusak Ringan`, `Rusak Berat` (disimpan setara dengan nilai lama) | Prioritas perbaikan |
| Luas layanan | `luas_layanan` | Luas sawah yang dilayani saluran (ha), web terintegrasi | Angka desimal | Dampak jika saluran bermasalah |
| Status perbaikan | `status_perbaikan` | Status tindak lanjut | Diisi sesuai pilihan formulir | Melacak apakah sudah ditangani |
| Aksi dilakukan | `aksi_dilakukan` | Tindakan yang sudah diambil petugas/petani | Teks | Jejak penanganan |
| Nama pelapor (isian) | `nama_pelapor` | Nama yang tercatat di form tertentu | Pelengkap; kepemilikan tetap dari akun login | Dokumentasi |

Ditambah variabel bersama: tanggal, wilayah, lintang, bujur, foto, catatan, status.

---

## 6. Laporan lainnya (web terintegrasi)

Selain hama dan irigasi, petugas memilih **jenis laporan**, lalu mengisi field dinamis yang disimpan sebagai `data_json`.

### 6.1 Variabel kerangka

| Nama tampilan | Nama sistem | Definisi |
|---|---|---|
| Jenis laporan | `jenis_id` | Mengacu `master_jenis_laporan` |
| Data khusus jenis | `data_json` | Kumpulan isian sesuai jenis (varietas, luas, dan lain-lain) |
| Status (modul ini) | `status` | Dapat memakai ejaan `draft` pada jalur kompatibilitas lama; makna tetap “belum dikirim” |

### 6.2 Jenis bawaan dan variabel di dalamnya

**Penanaman bibit baru** (`bibit_baru`)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Nama varietas | `nama_varietas` | Varietas yang ditanam |
| Jumlah bibit | `jumlah_bibit` | Banyaknya bibit |
| Sumber bibit | `sumber_bibit` | Asal bibit (opsional) |

**Rumah kaca** (`rumah_kaca`)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Jumlah unit | `jumlah_unit` | Banyak rumah kaca |
| Luas (m²) | `luas_m2` | Luas bangunan |
| Komoditas | `komoditas` | Tanaman di dalam rumah kaca |

**Panen** (`panen` — jenis laporan lainnya)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Komoditas | `komoditas` | Komoditas yang dipanen |
| Luas panen (Ha) | `luas_ha` | Luas yang dipanen |
| Estimasi ton | `estimasi_ton` | Perkiraan hasil |

**Bantuan alsintan** (`bantuan_alsintan`)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Nama alat | `nama_alat` | Jenis alsintan |
| Jumlah | `jumlah` | Unit diterima |
| Sumber bantuan | `sumber_bantuan` | Instansi/program |

**Kerusakan cuaca** (`kerusakan_cuaca`)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Jenis cuaca | `jenis_cuaca` | Misalnya banjir, angin kencang, kekeringan |
| Luas terdampak (Ha) | `luas_terdampak_ha` | Luas lahan rusak |

---

## 7. Laporan tambahan Backend v1 (pupuk, panen, cuaca, alat sarana)

Modul ini ada di API/web Backend v1. Semua memakai variabel bersama (tanggal, wilayah, lintang, bujur, foto, catatan, status) plus field khusus.

### 7.1 Laporan pupuk

| Nama tampilan | Nama sistem | Arti | Fungsi |
|---|---|---|---|
| Jenis pupuk | `jenis_pupuk` | Nama/jenis pupuk yang diaplikasikan (wajib kirim) | Rekap pemupukan |
| Dosis | `dosis` | Takaran yang dipakai | Analisis dosis |
| Satuan dosis | `satuan_dosis` | Misalnya kg/ha | Agar dosis dapat dibandingkan |
| Metode aplikasi | `metode_aplikasi` | Cara tebar/kocor/dll | Praktik lapang |
| OPT terkait | `master_opt_id` | Opsional, jika pemupukan terkait OPT | Keterkaitan hama–budidaya |

### 7.2 Laporan panen (Backend v1)

| Nama tampilan | Nama sistem | Arti | Fungsi |
|---|---|---|---|
| Komoditas | `komoditas` | Komoditas panen (wajib kirim) | Rekap komoditas |
| Luas panen | `luas_panen` | Hektar dipanen | Agregat luas |
| Volume panen | `volume_panen` | Jumlah hasil | Agregat produksi lapangan |
| Satuan | `satuan` | Satuan volume (kg, kuintal, ton) | Konsistensi angka |
| Harga per unit | `harga_per_unit` | Harga di tingkat petani/lokasi | Konteks ekonomi |

### 7.3 Laporan cuaca lapangan

Ini observasi petugas di titik lahan, **bukan** otomatis mengganti data BMKG.

| Nama tampilan | Nama sistem | Arti | Fungsi |
|---|---|---|---|
| Kondisi cuaca | `kondisi_cuaca` | Ringkasan cuaca saat observasi (wajib kirim) | Klasifikasi kejadian |
| Suhu | `suhu` | Suhu udara (°C) | Catatan iklim mikro |
| Kelembaban | `kelembaban` | Kelembaban relatif (%) | Konteks penyakit |
| Curah hujan | `curah_hujan` | Curah hujan yang diamati (mm) | Konteks banjir/kekeringan |
| Kecepatan angin | `kecepatan_angin` | Kecepatan angin | Konteks rebah/kerusakan |
| Arah angin | `arah_angin` | Arah (teks, misalnya Timur) | Pelengkap |

### 7.4 Laporan alat dan sarana

| Nama tampilan | Nama sistem | Arti | Fungsi |
|---|---|---|---|
| Jenis sarana | `jenis_sarana` | Kategori sarana (wajib kirim) | Rekap jenis |
| Nama alat | `nama_alat` | Nama alat (wajib kirim) | Identitas aset/kejadian |
| Jumlah | `jumlah` | Banyak unit | Inventaris lapangan |
| Kondisi | `kondisi` | Keadaan alat/sarana | Prioritas perbaikan |

---

## 8. Master OPT (daftar resmi yang dibaca petugas)

Petugas memilih OPT; admin mengelola atribut berikut.

| Nama tampilan | Nama sistem | Definisi |
|---|---|---|
| Kode OPT | `kode_opt` | Kode ringkas |
| Nama OPT | `nama_opt` | Nama yang tampil di formulir |
| Nama ilmiah | `nama_ilmiah` | Nama Latin |
| Nama lokal | `nama_lokal` | Nama yang dikenal petani setempat |
| Jenis | `jenis` | `hama`, `penyakit`, atau `gulma` |
| Status karantina | `status_karantina` | Informasi karantina jika relevan |
| Tingkat bahaya | `tingkat_bahaya` | Klasifikasi risiko |
| Kategori | `kategori` | Pengelompokan tambahan |
| Kingdom … genus | `kingdom`, `filum`, `kelas`, `ordo`, `famili`, `genus` | Taksonomi (rujukan) |
| ETL acuan / satuan ETL | lihat [bagian 4.5](#45-etl-acuan-dan-satuan-etl-dari-master-opt-bukan-diketik-petugas) | Ambang pengendalian |
| Foto referensi | `foto_url` | Gambar pengenalan |
| Deskripsi | `deskripsi` | Ciri dan dampak |
| Rekomendasi | `rekomendasi` | Saran pengendalian umum |
| Referensi | `referensi` | Sumber pustaka |
| Aktif | `aktif` | Hanya OPT aktif yang boleh dipilih |

---

## 9. Master wilayah

| Nama tampilan | Nama sistem | Definisi | Fungsi |
|---|---|---|---|
| Kabupaten | `nama_kabupaten`, `kode` | Kabupaten/kota; Jember = `3509` | Filter dan FK laporan |
| Kecamatan | `nama_kecamatan`, `kode` | Kecamatan di bawah kabupaten | Filter, peta, rekap |
| Lintang/bujur kecamatan | `latitude`, `longitude` pada `master_kecamatan` | Titik acuan kecamatan | Peta administratif |
| Desa | `nama_desa`, `kode` | Desa/kelurahan | Lokasi laporan terhalus |
| Provinsi (data BPS) | `master_provinsi` / `kode_provinsi` | Jawa Timur = `35` | Impor data antar-kabupaten |

---

## 10. Pengguna, peran, dan akses

| Nama tampilan | Nama sistem | Definisi |
|---|---|---|
| Username | `username` | Nama login |
| Nama lengkap | `nama_lengkap` | Nama yang tampil di laporan |
| Email | `email` | Kontak akun |
| Peran | `role` | `petugas`, `admin`, dan dapat pula `operator`, `statistisi`, `viewer` sesuai kebijakan runtime |
| Aktif | `aktif` | Akun nonaktif tidak dapat login |
| Wajib ganti password | `must_change_password` | Paksa ganti sandi pertama kali |

Password tidak pernah ditampilkan kembali. Jangan membagikan akun.

---

## 11. Saran dan aduan (feedback)

| Nama tampilan | Nama sistem | Nilai / arti |
|---|---|---|
| Jenis | `jenis_feedback` | Misalnya bug, fitur baru, peningkatan |
| Judul | `judul` | Ringkasan masukan |
| Deskripsi | `deskripsi` | Penjelasan |
| Prioritas | `prioritas` | `low`, `medium`, `high`, `critical` |
| Status | `status` | `diterima`, `dalam_proses`, `selesai`, `ditolak` |
| Lampiran | `attachment_url` | File pendukung |
| Catatan admin | `admin_notes` | Tanggapan pengelola |
| Jumlah dukungan | `vote_count` | Berapa pengguna mendukung usulan yang sama |

Feedback petugas bersifat pribadi (riwayat sendiri). Rekap global hanya untuk admin.

---

## 12. Variabel dashboard dan data pendukung

Bagian ini untuk petugas yang membaca grafik/peta, dan untuk operator data. Bukan semua kolom diisi dari formulir harian petugas.

### 12.1 Data irigasi operasional (debit bendungan/DI)

Tabel `data_irigasi` (berbeda dari laporan saluran petugas).

| Nama tampilan | Nama sistem | Satuan / nilai | Arti |
|---|---|---|---|
| Daerah irigasi | `daerah_irigasi` | Nama DAM/DI | Objek pemantauan |
| Luas sawah | `luas_sawah` | Hektar | Luas layanan DI |
| Debit air | `debit_air` | Liter/detik | Volume aliran |
| Status pintu | `status_pintu` | Aman / Waspada / Kritis | Status operasional |
| Metode data | `metode_data` | `aktual`, `manual`, `simulasi` | Kualitas sumber |

### 12.2 Curah hujan (agregat)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Curah hujan | `curah_hujan` | Tinggi hujan, default **mm** (`satuan`) |
| Lokasi | `lokasi` | Stasiun/wilayah, sering “Jember” |
| Sumber data | `sumber_data` | Asal angka (BMKG, impor, dsb.) |
| Lintang/bujur stasiun | `latitude`, `longitude` | Titik pengukuran |

### 12.3 Kecepatan angin (agregat)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Kecepatan angin | `kecepatan_angin` | Kecepatan rata-rata; default **km/jam** |
| Kecepatan maksimum | `kecepatan_max` | Puncak |
| Arah angin | `arah_angin` (derajat) dan `arah_angin_desc` (mata angin) | Arah datangnya angin |

### 12.4 Harga komoditas

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Jenis komoditas | `jenis_komoditas` | `gabah_kering_panen` (GKP), `gabah_kering_giling` (GKG), `beras_medium`, `beras_premium` |
| Harga | `harga` | Rupiah per kg (`satuan` default `Rp/kg`) |
| Metode data | `metode_data` | `aktual`, `estimasi`, `simulasi`, `manual` |
| Alert harga | `tipe_alert`, `persentase` | Naik/turun/fluktuasi; ambang peringatan sekitar 5%, kritis sekitar 10% |

### 12.5 Produksi gabah lapangan (`produksi_gabah`)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| ID unik | `unique_id` | Kode rekaman produksi |
| Musim tanam | `musim_tanam` | Musim (MH/MK sesuai pilihan aplikasi) |
| Tahun | `tahun` | Tahun tanam/panen |
| Nama lokasi | `nama_lokasi` | Identitas lahan/kelompok |
| Varietas | `varietas` | Varietas padi |
| Luas tanam | `luas_tanam` | Hektar ditanam |
| Luas panen | `luas_panen` | Hektar dipanen |
| Produksi total | `produksi_total` | Hasil (ton; dashboard sering menampilkan **ton GKG**) |
| Kadar air | `kadar_air` | Persen; rentang wajar sistem sekitar 10–30% |
| Grade kualitas | `grade_kualitas` | Mutu gabah |
| Harga gabah | `harga_gabah` | Harga terkait rekaman |
| Produktivitas | dihitung | Produksi per hektar; rentang wajar sistem sekitar 1–15 ton/ha |

### 12.6 Data pertanian BPS tahunan

| Nama tampilan | Nama sistem | Satuan / arti |
|---|---|---|
| Tahun | `tahun` | Tahun data |
| Kabupaten/kota | `kabupaten_kota` | Nama wilayah BPS |
| Kode wilayah | `kode_wilayah` | Kode BPS |
| Luas panen | `luas_panen` | Hektar |
| Produksi gabah | `produksi_gabah` | Ton |
| Produksi beras | `produksi_beras` | Ton |
| Produktivitas | `produktivitas` | Kuintal/ha pada skema BPS ini |
| Tipe sumber | `sumber_data_type` | `ksa`, `resmi_webapi`, `manual`, `simulasi` |
| Skenario | `tipe_skenario` | `baseline`, `optimis`, `pesimis` |
| Tervalidasi | `is_validated` | Apakah angka sudah divalidasi |

### 12.7 Data KSA bulanan

Survei Kerangka Sampel Area BPS, per bulan (bukan hanya tahunan).

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Tahun, bulan | `tahun`, `bulan` | Periode |
| Luas panen / produksi gabah / produksi beras / produktivitas | sama seperti BPS | Angka bulanan |
| Status data | `status_data` | `tetap`, `sementara`, `potensi` |

Angka **tetap** adalah rilis resmi; **sementara/potensi** masih dapat berubah.

### 12.8 Evaluasi akurasi panen

Membandingkan estimasi daerah dengan rilis BPS.

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Luas estimasi daerah | `luas_estimasi_daerah` | Angka internal/daerah (ha) |
| Luas rilis BPS | `luas_rilis_bps` | Angka BPS (ha) |
| Deviasi absolut | `deviasi_absolut` | Selisih ha |
| Persentase bias | `persentase_bias` | Selisih relatif |
| Status akurasi | `status_akurasi` | Sangat akurat (&lt; 5%), perlu perhatian (5–10%), bias tinggi (&gt; 10%) |

### 12.9 Analisis produksi bulanan (narasi risiko)

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Total luas panen | `total_luas_panen` | Agregat periode |
| Faktor penyebab utama | `faktor_penyebab_utama` | Faktor yang dianggap paling berpengaruh |
| Skor risiko cuaca / hama / total | `skor_risiko_cuaca`, `skor_risiko_hama`, `skor_risiko_total` | Skor internal 0–lebih tinggi = lebih berisiko |
| Curah hujan lag-1 | `avg_curah_hujan_lag1` | Rata-rata hujan bulan sebelumnya |
| Laporan hama lag-1 | `total_laporan_hama_lag1`, `laporan_hama_berat_lag1` | Jumlah laporan (termasuk yang berat) bulan sebelumnya |
| Narasi | `narasi_otomatis`, `narasi_final` | Teks penjelasan; yang final dapat disunting |
| Status analisis | `status_analisis` | `draft`, `published`, `archived` |

**Lag-1** artinya memakai data **bulan sebelumnya**, karena dampak cuaca/hama pada panen sering tertunda.

---

## 13. Notifikasi dan perangkat

| Nama tampilan | Nama sistem | Arti |
|---|---|---|
| Judul / isi notifikasi | `title`, `body` | Pesan ke pengguna (laporan diverifikasi, ditolak, dsb.) |
| Jenis notifikasi | `type` | Kode peristiwa |
| Sudah dibaca | `read_at` | Kosong = belum dibaca |
| Token perangkat | `token` pada `device_tokens` | Identitas HP untuk push notification |
| Platform | `platform` | `android`, `ios`, `web` |

---

## 14. Satuan yang sering dipakai (ringkasan)

| Satuan | Dipakai untuk |
|---|---|
| Derajat desimal | Lintang, bujur |
| Hektar (Ha) | Luas serangan, luas tanam, luas panen, luas layanan, luas terdampak |
| mm | Curah hujan |
| °C | Suhu |
| % | Kelembaban, kadar air, intensitas penyakit, ETL bertipe persen |
| ekor/rumpun | Populasi wereng/walang sesuai ETL |
| Liter/detik | Debit data irigasi operasional |
| km/jam | Kecepatan angin agregat |
| ton, kuintal, kg | Produksi dan volume panen — selalu lihat label kolom |
| Rp/kg | Harga komoditas |
| kuintal/ha atau ton/ha | Produktivitas — skema BPS memakai kuintal/ha; produksi gabah lapangan memakai ton/ha |

---

## 15. Checklist isi laporan hama yang benar

1. Tanggal = hari observasi di lahan.
2. OPT dipilih dari daftar; pahami satuan ETL-nya.
3. Wilayah: kabupaten → kecamatan → desa, lalu alamat yang bisa dikenali.
4. Lintang negatif (sekitar −8,1 s.d. −8,3) dan bujur sekitar 113,5 s.d. 114,2 untuk Jember.
5. Keparahan sesuai kondisi tanaman.
6. **Populasi/intensitas** sesuai satuan OPT (bukan luas lahan).
7. **Luas serangan** dalam hektar, tidak melebihi luas petak yang diamati.
8. Foto asli di lokasi.
9. Kirim jika sudah lengkap; simpan draf jika sinyal lemah.

---

## Indeks A–Z nama tampilan

| Nama di layar | Bagian |
|---|---|
| Aksi dilakukan | [5. Laporan irigasi](#5-laporan-irigasi-kejadian-di-saluran) |
| Alamat lengkap | [2.4 Lokasi tekstual](#24-lokasi-tekstual-pelengkap-koordinat) |
| Arah angin | [7.3](#73-laporan-cuaca-lapangan), [12.3](#123-kecepatan-angin-agregat) |
| Catatan / catatan verifikasi | [3.2](#32-foto-dan-catatan), [3.3](#33-verifikasi-diisi-admin-bukan-petugas) |
| Curah hujan | [7.3](#73-laporan-cuaca-lapangan), [12.2](#122-curah-hujan-agregat) |
| Daerah irigasi | [5](#5-laporan-irigasi-kejadian-di-saluran), [12.1](#121-data-irigasi-operasional-debit-bendungandi) |
| Debit air | [5](#5-laporan-irigasi-kejadian-di-saluran) (kategori) vs [12.1](#121-data-irigasi-operasional-debit-bendungandi) (liter/detik) |
| Desa | [2.4](#24-lokasi-tekstual-pelengkap-koordinat), [9](#9-master-wilayah) |
| Dosis / satuan dosis | [7.1](#71-laporan-pupuk) |
| ETL acuan | [4.5](#45-etl-acuan-dan-satuan-etl-dari-master-opt-bukan-diketik-petugas) |
| Foto | [3.2](#32-foto-dan-catatan) |
| Harga | [7.2](#72-laporan-panen-backend-v1), [12.4](#124-harga-komoditas) |
| Intensitas | [4.3 Populasi / Intensitas](#43-populasi--intensitas) |
| Jenis saluran / jenis pupuk / jenis sarana / jenis OPT | [5](#5-laporan-irigasi-kejadian-di-saluran), [7](#7-laporan-tambahan-backend-v1-pupuk-panen-cuaca-alat-sarana), [4.1](#41-opt-yang-dilaporkan) |
| Kabupaten / kecamatan | [2.4](#24-lokasi-tekstual-pelengkap-koordinat), [9](#9-master-wilayah) |
| Kadar air | [12.5](#125-produksi-gabah-lapangan-produksi_gabah) |
| Kelembaban | [7.3](#73-laporan-cuaca-lapangan) |
| Kecepatan angin | [7.3](#73-laporan-cuaca-lapangan), [12.3](#123-kecepatan-angin-agregat) |
| Komoditas | [6.2](#62-jenis-bawaan-dan-variabel-di-dalamnya), [7.2](#72-laporan-panen-backend-v1) |
| Kondisi fisik / kondisi cuaca / kondisi alat | [5](#5-laporan-irigasi-kejadian-di-saluran), [7.3](#73-laporan-cuaca-lapangan), [7.4](#74-laporan-alat-dan-sarana) |
| Latitude (Lintang) | [2.1](#21-latitude--lintang) |
| Longitude (Bujur) | [2.2](#22-longitude--bujur) |
| Luas panen / luas serangan / luas tanam / luas layanan | [4.4](#44-luas-serangan), [7.2](#72-laporan-panen-backend-v1), [12.5](#125-produksi-gabah-lapangan-produksi_gabah), [5](#5-laporan-irigasi-kejadian-di-saluran) |
| Metode aplikasi / metode data | [7.1](#71-laporan-pupuk), [12.1](#121-data-irigasi-operasional-debit-bendungandi), [12.4](#124-harga-komoditas) |
| Musim tanam | [12.5](#125-produksi-gabah-lapangan-produksi_gabah) |
| Nama saluran / nama alat / nama varietas | [5](#5-laporan-irigasi-kejadian-di-saluran), [7.4](#74-laporan-alat-dan-sarana), [6.2](#62-jenis-bawaan-dan-variabel-di-dalamnya) |
| Nomor laporan | [1.4](#14-nomor-laporan), [3.1](#31-identitas-dan-waktu) |
| OPT | [4.1](#41-opt-yang-dilaporkan), [8](#8-master-opt-daftar-resmi-yang-dibaca-petugas) |
| Populasi | [4.3](#43-populasi--intensitas) |
| Produksi / produktivitas | [12.5](#125-produksi-gabah-lapangan-produksi_gabah), [12.6](#126-data-pertanian-bps-tahunan) |
| Status laporan | [1.3](#13-status-laporan-nilai-resmi) |
| Status data KSA | [12.7](#127-data-ksa-bulanan) |
| Status akurasi | [12.8](#128-evaluasi-akurasi-panen) |
| Status pintu | [12.1](#121-data-irigasi-operasional-debit-bendungandi) |
| Suhu | [7.3](#73-laporan-cuaca-lapangan) |
| Tanggal | [3.1](#31-identitas-dan-waktu) |
| Tingkat keparahan | [4.2](#42-tingkat-keparahan) |
| Volume panen | [7.2](#72-laporan-panen-backend-v1) |

---

## Sumber yang dipakai menyusun buku ini

Definisi diselaraskan dengan implementasi aktual: formulir `app/views/laporan/create.php` dan `irigasi/create.php`, validator Backend v1 (`LaporanHamaValidator` dan sejenisnya), schema `laporan_hama` / `laporan_irigasi` / laporan tambahan, seed OPT (`etl_acuan`, `satuan_etl`), master jenis laporan lainnya, serta model dashboard (KSA, BPS, curah hujan, angin, harga, produksi gabah, evaluasi).

Jika label di layar berbeda dengan buku ini, ikuti label layar untuk pengisian, lalu cari padanannya di indeks.

---

*Dokumen ini untuk pemahaman konsep petugas. Kontrak teknis API tetap merujuk `docs/API.md`; skema kolom merujuk `docs/DATABASE.md`.*
