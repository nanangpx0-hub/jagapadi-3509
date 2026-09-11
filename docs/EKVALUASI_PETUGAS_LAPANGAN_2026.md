# Kerangka Evaluasi Aplikasi JAGAPADI — Perspektif Petugas Lapangan

> **Versi**: 1.0.0  
> **Tanggal**: 10 September 2026  
> **Target Pengguna**: Petugas Lapangan (role `petugas`)  
> **Durasi Pengisian**: Kurang dari 15 menit  
> **Tujuan**: Mengumpulkan penilaian kuantitatif dan umpan balik kualitatif yang actionable untuk pengembangan aplikasi JAGAPADI  
> **Batasan**: Dokumen ini bersifat deskriptif semata — tidak mengubah atau menambahkan kode aplikasi yang ada.

---

## Panduan Penggunaan

### Cara Mengisi

| Komponen | Metode |
|---|---|
| Penilaian kuantitatif | Skala 1–5 pada setiap indikator (1 = Sangat Buruk, 5 = Sangat Baik) |
| Nilai plus (keunggulan) | Cantumkan aspek positif yang dirasakan petugas |
| Nilai minus (kekurangan) | Cantumkan hambatan, bug, atau aspek yang perlu ditingkatkan |
| Umpan balik kualitatif | Deskripsi terbuka untuk setiap kategori |
| Prioritas perbaikan | Pilih tingkat prioritas: Kritis, Tinggi, Sedang, Rendah |

### Skala Penilaian

| Nilai | Interpretasi |
|---|---|
| 1 | Sangat Buruk — Tidak berfungsi atau sangat menghambat pekerjaan |
| 2 | Buruk — Sering bermasalah, sangat mengurangi produktivitas |
| 3 | Cukup — Berfungsi tapi dengan kendala signifikan |
| 4 | Baik — Berfungsi baik dengan sedikit kendala |
| 5 | Sangat Baik — Sangat membantu, tidak ada kendala berarti |

### Catatan Pengisian

- Isi setiap bagian secara jujur sesuai pengalaman pribadi
- Beri contoh spesifik pada kolom umpan balik kualitatif (kapan, di mana, kejadian apa)
- Jika tidak pernah menggunakan fitur tertentu, cantumkan "Tidak Pernah Menggunakan"
- Formulir dirancang untuk diisi dalam **10–15 menit**

---

## KATEGORI 1: Kegunaan Antarmuka dan Pengalaman Pengguna (UX/UI) di Lingkungan Lapangan

### Indikator Penilaian

| ID | Indikator | Penjelasan | Bobot |
|---|---|---|---|
| 1.1 | Kemudahan navigasi menu utama | Seberapa mudah menemukan fitur yang dibutuhkan (Laporan Hama, Irigasi, dll.) saat bekerja di lapangan | Tinggi |
| 1.2 | Kejelasan tampilan form laporan | Apakah field-field pada form laporan mudah dipahami dan diisi tanpa instruksi tambahan | Tinggi |
| 1.3 | Desain responsif terhadap kondisi layar kecil | Tampilan aplikasi tetap terbaca dan operasional saat menggunakan layar ponsel/Android dalam kondisi lapangan (sinar matahari, genggam satu tangan) | Tinggi |
| 1.4 | Konsistensi antar modul laporan | Keseragaman antarmuka antara form Laporan Hama, Irigasi, Pupuk, Panen, Cuaca, dan Alat/Sarana | Sedang |
| 1.5 | Kecepatan akses ke form pelaporan baru | Berapa lama proses dari membuka aplikasi hingga mulai mengisi laporan baru | Sedang |
| 1.6 | Keterbacaan teks dan ikon dalam kondisi luar ruangan | Apakah teks, tombol, dan ikon cukup besar dan kontras untuk dibaca di bawah sinar matahari | Sedang |
| 1.7 | Kesederhanaan proses submit laporan | Apakah proses pengiriman laporan (Draf → Submitted) intuitif dan tidak membingungkan | Sedang |
| 1.8 | Visualisasi data dashboard (grafik, statistik) | Seberapa mudah membaca dan memahami informasi yang disajikan di dashboard Petugas | Rendah |

### Kolom Nilai Plus (Keunggulan UX/UI)

| Aspek | Deskripsi Spesifik | Bukti/Demonstrasi |
|---|---|---|
| _Tuliskan keunggulan antarmuka yang Anda rasakan_ | Jelaskan fitur UI yang memudahkan pekerjaan Anda di lapangan | Sebutkan modul/field yang dimaksud |

### Kolom Nilai Minus (Kekurangan UX/UI)

| Aspek | Deskripsi Spesifik | Dampak pada Pekerjaan |
|---|---|---|
| _Tuliskan kekurangan antarmuka yang Anda alami_ | Jelaskan masalah UI yang menghambat pekerjaan | Jelaskan konsekuensi waktu/efisiensi |

### Umpan Balik Kualitatif Kategori 1

```
📝 Umpan Balik:
```

- **Saran perbaikan UI/UX**:
```
```

- **Fitur yang paling sering Anda gunakan**:
```
```

- **Hal yang paling membingungkan**:
```
```

---

## KATEGORI 2: Kinerja Aplikasi dalam Kondisi Jaringan Terbatas

### Indikator Penilaian

| ID | Indikator | Penjelasan | Bobot |
|---|---|---|---|
| 2.1 | Kemampuan menyimpan draf saat offline | Seberapa baik aplikasi menyimpan laporan secara lokal ketika tidak ada koneksi internet | Kritis |
| 2.2 | Proses sinkronisasi otomatis saat koneksi kembali | Seberapa cepat dan andal proses sync draf lokal ke server ketika jaringan pulih | Kritis |
| 2.3 | Penanganan error koneksi | Apakah pesan error yang muncul mudah dipahami dan memberikan panduan perbaikan | Kritis |
| 2.4 | Durasi dan keandalan proses upload foto | Seberapa cepat dan berhasil upload foto laporan, terutama dalam kondisi sinyal lemah | Kritis |
| 2.5 | Mekanisme retry tanpa duplikasi data | Apakah sistem mencegah duplikasi data saat pengguna mencoba mengirim ulang setelah timeout | Tinggi |
| 2.6 | Penggunaan data seluler/efisiensi bandwidth | Seberapa besar kuota data yang dikonsumsi aplikasi dalam satu sesi operasi lapangan | Sedang |
| 2.7 | Kecepatan respons saat jaringan stabil | Seberapa cepat aplikasi merespons saat koneksi baik (load form, tampil data) | Sedang |
| 2.8 | Indikator status koneksi | Apakah ada indikator visual yang menunjukkan status koneksi (online/offline/syncing) | Sedang |
| 2.9 | Kompatibilitas dengan berbagai kondisi jaringan | Apakah aplikasi berfungsi di jaringan 3G, 4G, WiFi, dan area dengan sinyal sangat lemah | Tinggi |

### Kolom Nilai Plus (Keunggulan Kinerja Jaringan)

| Aspek | Deskripsi Spesifik | Bukti/Demonstrasi |
|---|---|---|
| _Tuliskan aspek kinerja jaringan yang baik_ | Jelaskan fitur yang berjalan baik tanpa/jaringan terbatas | Sebutkan skenario spesifik |

### Kolom Nilai Minus (Kekurangan Kinerja Jaringan)

| Aspek | Deskripsi Spesifik | Dampak pada Pekerjaan |
|---|---|---|
| _Tuliskan masalah kinerja jaringan yang dialami_ | Jelaskan kapan dan apa yang terjadi | Sebutkan frekuensi dan konsekuensi |

### Umpan Balik Kualitatif Kategori 2

```
📝 Umpan Balik:
```

- **Skenario offline yang paling sering Anda alami**:
```
```

- **Masalah sync yang paling sering terjadi**:
```
```

- **Rekomendasi peningkatan mode offline**:
```
```

---

## KATEGORI 3: Kesesuaian Fitur dengan Kebutuhan Tugas Harian Petugas

### Indikator Penilaian

| ID | Indikator | Penjelasan | Bobot |
|---|---|---|---|
| 3.1 | Kelengkapan field data laporan Hama | Apakah semua informasi yang dibutuhkan untuk laporan Hama/OPT tersedia dalam form (OPT, tingkat keparahan, luas serangan, populasi, koordinat, foto) | Kritis |
| 3.2 | Kelengkapan field data laporan Irigasi | Apakah semua informasi yang dibutuhkan untuk laporan Irigasi tersedia dalam form (saluran, daerah irigasi, kondisi fisik, debit air) | Kritis |
| 3.3 | Kesesuaian jenis laporan dengan tugas lapangan | Apakah keenam jenis laporan (Hama, Irigasi, Pupuk, Panen, Cuaca, Alat/Sarana) mencakup semua kebutuhan pelaporan harian | Kritis |
| 3.4 | Mudahnya memilih dan mencari master data (Wilayah, OPT) | Seberapa mudah menemukan dan memilih wilayah (kabupaten → kecamatan → desa) dan jenis OPT dalam form | Tinggi |
| 3.5 | Fitur pencarian dan filter laporan | Kemampuan menyaring laporan berdasarkan status, tanggal, jenis, dan pencarian teks | Tinggi |
| 3.6 | Integrasi fotografi dan geotagging | Kemudahan mengambil foto dan mendapatkan koordinat GPS otomatis dalam form laporan | Tinggi |
| 3.7 | Kesesuaian sistem workflow (Draf → Submitted → Diverifikasi → Diarsipkan) | Apakah alur kerja ini mencerminkan proses nyata di lapangan | Tinggi |
| 3.8 | Fitur usulan OPT | Seberapa berguna fitur pengusulan jenis OPT baru bagi petugas yang menemukan hama/penyakit baru | Sedang |
| 3.9 | Ekspor data laporan | Kemampuan mengekspor laporan dalam format yang berguna (Excel/PDF) untuk keperluan administrasi | Sedang |
| 3.10 | Notifikasi dan informasi status laporan | Ketersediaan dan ketepatan notifikasi status laporan (disetujui, ditolak, diverifikasi) | Sedang |
| 3.11 | Ketersediaan data cuaca BPS dan harga komoditas | Seberapa berguna data cuaca, harga komoditas, dan data BPS yang tersedia untuk referensi kerja | Rendah |
| 3.12 | Kesesuaian dengan pola kerja petugas di lapangan | Apakah fitur-fitur tersebut sesuai dengan ritme kerja petugas (pagi/siang/sore, hari kerja/hari libur) | Tinggi |

### Kolom Nilai Plus (Keunggulan Fitur)

| Fitur | Deskripsi Manfaat | Frekuensi Penggunaan |
|---|---|---|
| _Tuliskan fitur yang sangat membantu_ | Jelaskan mengapa fitur ini bermanfaat | Seberapa sering digunakan |

### Kolom Nilai Minus (Kekurangan Fitur)

| Fitur yang Kurang | Deskripsi Masalah | Dampak pada Tugas |
|---|---|---|
| _Tuliskan fitur yang kurang memadai_ | Jelaskan apa yang kurang | Jelaskan bagaimana ini menghambat pekerjaan |

### Umpan Balik Kualitatif Kategori 3

```
📝 Umpan Balik:
```

- **Fitur yang paling sering Anda butuhkan tetapi belum tersedia**:
```
```

- **Laporan jenis mana yang paling sulit diisi**:
```
```

- **Hal yang ingin Anda tambahkan ke dalam form laporan**:
```
```

---

## KATEGORI 4: Reliabilitas Sistem dan Akurasi Data yang Disimpan

### Indikator Penilaian

| ID | Indikator | Penjelasan | Bobot |
|---|---|---|---|
| 4.1 | Kestabilan data saat proses submit | Apakah data laporan selalu tersimpan dengan benar saat submit, tanpa kehilangan field atau duplikasi | Kritis |
| 4.2 | Keakuratan koordinat GPS | Seberapa akurat dan dapat diandalkan koordinat yang dicatat otomatis untuk lokasi laporan | Kritis |
| 4.3 | Kestabilan data selama sync offline-ke-online | Apakah data lokal tidak berubah, hilang, atau rusak selama proses sinkronisasi | Kritis |
| 4.4 | Mekanisme penomoran laporan | Apakah nomor laporan yang dihasilkan sistem unik, berurutan, dan tidak pernah duplikat | Tinggi |
| 4.5 | Ketepatan validasi data input | Apakah sistem validasi mencegah pengisian data yang salah format (tanggal, angka, koordinat) secara tepat | Tinggi |
| 4.6 | Konsistensi data antara versi web dan mobile | Apakah data yang dilihat di web (admin/self) sama persis dengan yang terlihat di aplikasi mobile | Tinggi |
| 4.7 | Keamanan dan privasi data laporan | Apakah data laporan hanya dapat diakses oleh petugas pemilik dan admin yang berwenang | Tinggi |
| 4.8 | Ketersediaan dan kelengkapan riwayat perubahan status | Apakah riwayat status laporan (Draf → Submitted → Diverifikasi, dll.) lengkap dan dapat ditelusuri | Sedang |
| 4.9 | Kestabilan sistem saat digunakan bersamaan | Apakah aplikasi tetap stabil ketika banyak petugas menggunakan sistem secara bersamaan | Sedang |
| 4.10 | Ketahanan data terhadap crash/forced close | Apakah laporan yang sedang dikerjakan tidak hilang jika aplikasi ditutup paksa atau terjadi crash | Kritis |

### Kolom Nilai Plus (Keunggulan Reliabilitas)

| Aspek | Deskripsi Spesifik | Bukti Kejadian |
|---|---|---|
| _Tuliskan pengalaman positif terkait keandalan data_ | Jelaskan kapan data tersimpan dengan sempurna | Sebutkan kejadian spesifik |

### Kolom Nilai Minus (Kekurangan Reliabilitas)

| Aspek | Deskripsi Spesifik | Dampak terhadap Data |
|---|---|---|
| _Tuliskan pengalaman negatif terkait keandalan data_ | Jelaskan kapan data hilang/rusak | Jelaskan dampaknya |

### Umpan Balik Kualitatif Kategori 4

```
📝 Umpan Balik:
```

- **Pernahkah data laporan hilang atau rusak?**:
```
```

- **Pernahkah nomor laporan duplikat atau salah?**:
```
```

- **Pernahkah data berbeda antara web dan mobile?**:
```
```

---

## KATEGORI 5: Kemudahan Aksesibilitas untuk Pengguna dengan Berbagai Tingkat Literasi Digital

### Indikator Penilaian

| ID | Indikator | Penjelasan | Bobot |
|---|---|---|---|
| 5.1 | Kemudahan pemahaman istilah teknis | Apakah istilah-istilah teknis yang digunakan (Draf, Submitted, Diverifikasi, OPT, dll.) mudah dipahami oleh petugas dengan latar belakang pendidikan beragam | Tinggi |
| 5.2 | Ketersediaan panduan/bantuan kontekstual | Apakah ada panduan, tooltip, atau help yang tersedia di dalam aplikasi saat petugas bingung | Tinggi |
| 5.3 | Kemudahan proses login pertama kali | Seberapa mudah petugas yang baru pertama kali menggunakan aplikasi dapat login dan mulai bekerja | Tinggi |
| 5.4 | Ketersediaan instruksi penggantian password awal | Apakah petugas yang mendapat password sementara mengetahui cara menggantinya | Sedang |
| 5.5 | Ukuran font dan elemen antarmuka yang dapat disesuaikan | Apakah ukuran teks dan elemen UI dapat disesuaikan untuk pengguna dengan penglihatan terbatas | Sedang |
| 5.6 | Dukungan bahasa dan lokalitas | Apakah semua teks aplikasi menggunakan bahasa Indonesia yang baku dan mudah dipahami | Tinggi |
| 5.7 | Kesederhanaan proses upload foto dan lampiran | Apakah proses mengambil/mengunggah foto untuk bukti laporan dapat dilakukan oleh petugas dengan pengalaman digital terbatas | Tinggi |
| 5.8 | Kesulitan teknis yang dihadapi petugas non-digital-native | Hambatan teknis spesifik yang dialami petugas yang kurang paham teknologi digital | Tinggi |
| 5.9 | Ketersediaan jalur kontak/support untuk masalah teknis | Apakah ada cara mudah untuk menghubungi pihak yang bisa membantu ketika mengalami masalah teknis | Sedang |
| 5.10 | Ketahanan aplikasi terhadap kesalahan input pengguna | Seberapa baik aplikasi menangani kesalahan input tanpa crash atau kehilangan data | Tinggi |

### Kolom Nilai Plus (Keunggulan Aksesibilitas)

| Aspek | Deskripsi Spesifik | Siapa yang Diuntungkan |
|---|---|---|
| _Tuliskan aspek yang memudahkan pengguna dengan berbagai tingkat literasi_ | Jelaskan fitur yang inklusif | Kelompok usia/pendidikan tertentu |

### Kolom Nilai Minus (Kekurangan Aksesibilitas)

| Hambatan | Deskripsi Spesifik | Siapa yang Terdampak |
|---|---|---|
| _Tuliskan hambatan aksesibilitas yang ditemui_ | Jelaskan kesulitan yang dialami | Petugas dengan literasi digital renda/tertua |

### Umpan Balik Kualitatif Kategori 5

```
📝 Umpan Balik:
```

- **Tingkat literasi digital Anda**:
```
□ Pemula □ Menengah □ Mahir □ Ahli
```

- **Hal paling sulit dipelajari saat pertama menggunakan aplikasi**:
```
```

- **Hal yang ingin diubah agar lebih mudah dimengerti**:
```
```

- **Rekan kerja Anda yang paling kesulitan dengan aplikasi ini**:
```
```

---

## KATEGORI 6: Dukungan Teknis yang Tersedia bagi Petugas Lapangan

### Indikator Penilaian

| ID | Indikator | Penjelasan | Bobot |
|---|---|---|---|
| 6.1 | Ketersediaan dan ketepatan pesan error | Apakah pesan error yang ditampilkan menunjukkan penyebab dan solusi yang jelas | Tinggi |
| 6.2 | Kemudahan mengakses halaman umpan balik (feedback) | Seberapa mudah petugas menemukan dan mengirimkan laporan masalah/bug/saran ke admin | Tinggi |
| 6.3 | Respon terhadap laporan masalah (waktu penanganan) | Seberapa cepat admin/teknis merespons dan menyelesaikan laporan masalah dari petugas | Kritis |
| 6.4 | Kualitas komunikasi balasan dari admin | Apakah balasan admin menjelaskan masalah dan solusi dengan jelas | Tinggi |
| 6.5 | Ketersediaan dokumentasi penggunaan | Apakah ada panduan yang mudah diakses dan dipahami untuk penggunaan aplikasi di lapangan | Tinggi |
| 6.6 | Ketersediaan kontak teknis darurat | Apakah petugas mengetahui siapa yang harus dihubungi saat terjadi masalah kritis di lapangan | Tinggi |
| 6.7 | Fitur bantuan dalam aplikasi (in-app help) | Apakah ada fitur bantuan yang langsung dapat diakses tanpa keluar dari aplikasi | Sedang |
| 6.8 | Ketersediaan tutorial/panduan visual | Apakah ada panduan visual (screenshot, video, infografis) untuk memudahkan pembelajaran | Sedang |
| 6.9 | Mekanisme pelaporan bug yang efektif | Apakah formulir pelaporan bug mengumpulkan informasi yang cukup untuk diagnosis (deskripsi, screenshot, konteks) | Tinggi |
| 6.10 | Penanganan laporan penolakan laporan (Ditolak → Draf) | Seberapa baik komunikasi dan petunjuk ketika laporan ditolak oleh admin | Tinggi |

### Kolom Nilai Plus (Keunggulan Dukungan Teknis)

| Aspek | Deskripsi Spesifik | Waktu Respon |
|---|---|---|
| _Tuliskan pengalaman positif dengan dukungan teknis_ | Jelaskan kapan dukungan teknis membantu | Berapa lama responsnya |

### Kolom Nilai Minus (Kekurangan Dukungan Teknis)

| Aspek | Deskripsi Spesifik | Dampak |
|---|---|---|
| _Tuliskan pengalaman negatif dengan dukungan teknis_ | Jelaskan kapan dukungan kurang memadai | Berapa lama menunggu |

### Umpan Balik Kualitatif Kategori 6

```
📝 Umpan Balik:
```

- **Cara paling efektif Anda menghubungi admin/teknis**:
```
```

- **Informasi yang paling Anda butuhkan saat mengalami masalah**:
```
```

- **Apakah Anda tahu siapa yang harus dihubungi saat darurat?**:
```
□ Ya □ Tidak
```

- **Saran untuk sistem dukungan teknis yang lebih baik**:
```
```

---

## FORMULIR EKVALUASI RINGKASAN

### Identitas Pengisi

| Field | Isian |
|---|---|
| Nama Lengkap | |
| NIP / Username | |
| Kabupaten/Kecamatan/Desa | |
| Jabatan/Role | |
| Lama Bekerja sebagai Petugas (tahun) | |
| Tingkat Literasi Digital | □ Pemula □ Menengah □ Mahir □ Ahli |
| Tanggal Pengisian | |
| Perangkat yang Digunakan | □ Android □ Web Browser □ Lainnya |
| Versi Aplikasi/OS | |
| Koneksi Jaringan Utama | □ WiFi □ 4G/5G □ 3G □ Campuran □ Offline |

### Skor Keseluruhan

| Kategori | Skor Rata-rata (1-5) | Bobot | Skor Tertimbang |
|---|---|---|---|
| 1. UX/UI | | 20% | |
| 2. Kinerja Jaringan | | 20% | |
| 3. Kesesuaian Fitur | | 20% | |
| 4. Reliabilitas & Akurasi | | 15% | |
| 5. Aksesibilitas | | 10% | |
| 6. Dukungan Teknis | | 15% | |
| **TOTAL** | | **100%** | **/5** |

### Tiga Hal Terpenting

```
✅ 3 KEUNGGULAN TERBESAR APLIKASI:
1. 
2. 
3. 

⚠️ 3 KEKURANGAN TERBESAR YANG PERLU DIPERBAIKI:
1. 
2. 
3. 

🚀 3 FITUR PRIORITAS PENGENBANGAN:
1. 
2. 
3. 
```

### Penilaian Akhir

```
📊 Apakah Anda merekomendasikan aplikasi JAGAPADI kepada rekan petugas lain?
□ Sangat Direkomendasikan
□ Direkomendasikan dengan Catatan
□ Netral
□ Tidak Direkomendasikan

💬 Kesan terakhir Anda tentang aplikasi JAGAPADI:

```

---

## REKOMENDASI PERBAIKAN DAN PENGEMBANGAN TERSTRUKTUR

### Kerangka Prioritasi Berdasarkan Hasil Evaluasi

#### Prioritas KRITIS (Harus segera ditangani)

| No | Temuan | Dampak Jika Tidak Ditangani | Rekomendasi Aksi |
|---|---|---|---|
| K1 | Ketidakmampuan menyimpan draf saat offline berujung kehilangan data | Petugas kehilangan seluruh hasil kerja lapangan | Pastikan mekanisme local-first storage berfungsi optimal dengan auto-save berkala dan redundansi lokal |
| K2 | Duplikasi data saat sync retry | Data laporan ganda, inkonsistensi statistik | Validasi idempotency key yang ketat di sisi server dan client, tambahkan konfirmasi sebelum retry |
| K3 | Proses submit laporan gagal tanpa petunjuk jelas | Petugas tidak tahu harus berbuat apa, laporan terhambat | Perbaiki pesan error dengan petunjuk actionable spesifik per jenis error |

#### Prioritas TINGGI (Perlu diperbaiki dalam siklus pengembangan berikutnya)

| No | Temuan | Dampak | Rekomendasi Aksi |
|---|---|---|---|
| T1 | Upload foto lambat/agagal di jaringan lemah | Laporan tidak lengkap tanpa foto, verifikasi tertunda | Implementasi kompresi foto di client, support upload resume, optimasi ukuran file |
| T2 | Kurangnya panduan kontekstual dalam aplikasi | Petugas pemula kesulitan, banyak pertanyaan dukungan | Tambahkan tooltip, help icon di setiap form, dan quick reference guide |
| T3 | Koordinat GPS tidak akurat di area tertentu | Lokasi laporan salah, masalah verifikasi | Tambahkan opsi konfirmasi/manual override koordinat, tambahkan indikator akurasi GPS |
| T4 | Respon dukungan teknis lambat | Masalah teknis menumpuk, menurunkan produktivitas | Tetapkan SLA respons, buat FAQ/pengetahuan lapangan, dan jalur kontak darurat |

#### Prioritas SEDANG (Peningkatan fitur dan pengalaman)

| No | Temuan | Dampak | Rekomendasi Aksi |
|---|---|---|---|
| S1 | Konsistensi UI antar modul laporan belum merata | Petugas harus beradaptasi antar jenis laporan | Standardisasi desain form dan navigasi seluruh modul laporan |
| S2 | Tidak ada indikator status sinkronisasi yang jelas | Petugas tidak tahu apakah data sudah tersimpan di server | Tambahkan status indicator (syncing/success/failed) yang terlihat jelas |
| S3 | Keterbatasan jenis laporan untuk kebutuhan khusus | Beberapa fenomena tidak tercakup | Evaluasi penambahan jenis laporan baru berdasarkan kebutuhan petugas |
| S4 | Tidak ada fitur offline tutorial/panduan | Petugas kesulitan belajar tanpa internet | Buat panduan offline yang tersedia dalam aplikasi |

#### Prioritas RENDAH (Peningkatan jangka panjang)

| No | Temuan | Dampak | Rekomendasi Aksi |
|---|---|---|---|
| R1 | Konsumsi data seluler tinggi | Petugas dengan kuota terbatas enggan menggunakan | Optimasi payload API, implementasi data-saver mode |
| R2 | Ukuran font tidak dapat disesuaikan | Pengguna dengan penglihatan terbatas kesulitan | Tambahkan opsi pengaturan ukuran font dalam settings |
| R3 | Dashboard visual kurang intuitif | Petugas tidak langsung memahami statistik dirinya | Sederhanakan dashboard, tambahkan tooltip pada grafik |

---

### Ringkasan Area Perbaikan Berdasarkan Temuan Evaluasi

#### A. Perbaikan Teknis Inti
1. **Mekanisme offline-first**: Pastikan penyimpanan lokal (SQLite) berfungsi sebagai sumber data utama dengan sync yang andal. Implementasi auto-save berkala dan validasi integritas data sebelum dan sesudah sync.
2. **Resiliensi jaringan**: Tingkatkan diagnostik jaringan otomatis, implementasi exponential backoff untuk retry, dan tampilkan status koneksi secara eksplisit.
3. **Validasi yang lebih baik**: Tambahkan validasi real-time pada form untuk mencegah kesalahan input sebelum submit. Pastikan pesan error spesifik dan actionable.
4. **Upload foto robust**: Implementasi kompresi otomatis, support upload chunked/resume, dan kemampuan retry upload tanpa duplikasi.

#### B. Perbaikan Fitur dan Konten
1. **Standarisasi UI/UX**: Konsistensi desain form, navigasi, dan terminologi di seluruh jenis laporan.
2. **Panduan pengguna**: Buat panduan cepat (quick reference) untuk setiap jenis laporan yang dapat diakses offline.
3. **Klarifikasi workflow**: Pastikan petugas memahami status laporan mereka kapan saja dengan visual status yang jelas.
4. **Fitur pencarian dan filter**: Tingkatkan kemampuan mencari dan menyaring laporan dengan filter gabungan.

#### C. Perbaikan Dukungan dan Komunikasi
1. **Saluran komunikasi**: Tetapkan kontak dukungan teknis yang jelas dan mudah diakses (WhatsApp, telepon, atau email).
2. **FAQ dan pengetahuan**: Kumpulkan pertanyaan umum petugas dan buat FAQ yang mudah diakses.
3. **Siklus feedback**: Pastikan feedback petugas mendapat respon dalam waktu yang wajar dan petugas mendapat informasi pembaruan status.
4. **Pelatihan**: Adakan sesi onboarding dan pendampingan untuk petugas baru, terutama yang memiliki tingkat literasi digital rendah.

#### D. Pengembangan Fitur Mendatang (Rekomendasi Berdasarkan Kategori)
1. **Notifikasi push yang lebih kontekstual**: Tidak hanya status verifikasi, tetapi juga pengingat laporan mendadak, peringatan cuaca ekstrem, dan pemberitahuan pengumuman.
2. **Mode hemat data**: Opsi untuk mengurangi penggunaan data dengan mengunduh data master secara berkala dan mengoptimasi ukuran payload.
3. **Integrasi data eksternal**: Sinkronisasi dengan data BPS, prakiraan cuaca, dan harga komoditas yang lebih otomatis dan terkini.
4. **Export dan pelaporan mandiri**: Kemampuan petugas mengekspor ringkasan laporan mereka dalam format yang lebih mudah dibaca (PDF, grafik).
5. **Aksesibilitas lanjutan**: Dukungan untuk ukuran font besar, mode kontras tinggi, dan navigasi keyboard untuk tablet.

---

## Lampiran: Matriks Indikator Lengkap

### Ringkasan Jumlah Indikator per Kategori

| Kategori | Indikator Kuantitatif | Indikator Plus/Minus | Total Item |
|---|---|---|---|
| 1. UX/UI | 8 | 2 | 10 |
| 2. Kinerja Jaringan | 9 | 2 | 11 |
| 3. Kesesuaian Fitur | 12 | 2 | 14 |
| 4. Reliabilitas & Akurasi | 10 | 2 | 12 |
| 5. Aksesibilitas | 10 | 2 | 12 |
| 6. Dukungan Teknis | 10 | 2 | 12 |
| **TOTAL** | **59** | **12** | **71** |

### Estimasi Waktu Pengisian

| Bagian | Estimasi Waktu |
|---|---|
| Identitas Pengisi | 1 menit |
| Kategori 1 (UX/UI) | 2 menit |
| Kategori 2 (Kinerja Jaringan) | 2 menit |
| Kategori 3 (Kesesuaian Fitur) | 3 menit |
| Kategori 4 (Reliabilitas) | 2 menit |
| Kategori 5 (Aksesibilitas) | 2 menit |
| Kategori 6 (Dukungan Teknis) | 2 menit |
| Skor Keseluruhan | 1 menit |
| Umpan Balik Tambahan | 2–3 menit |
| **TOTAL** | **14–16 menit** |

### Catatan

- Dokumen ini adalah **kerangka indikator evaluasi** dan tidak mengubah kode aplikasi JAGAPADI yang ada
- Semua rekomendasi bersifat **saran perbaikan** berdasarkan perspektif petugas lapangan
- Indikator dapat disesuaikan berdasarkan konteks spesifik wilayah dan kebutuhan operasional
- Formulir sebaiknya dicoba terlebih dahulu kepada 3–5 petugas untuk validasi durasi dan kejelasan sebelum diterapkan secara luas
- Evaluasi ini sebaiknya dilakukan secara periodik (minimal setiap 6 bulan) untuk memantau perkembangan kualitas aplikasi

---

## Riwayat Dokumen

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0.0 | 2026-09-10 | Dokumen pertama — kerangka evaluasi menyeluruh berdasarkan analisis sistem JAGAPADI |

---

> **Dokumen ini disusun berdasarkan analisis menyeluruh terhadap:**
> - Arsitektur sistem JAGAPADI (PHP 8.2 MVC + Flutter Android)
> - Workflow operasional petugas (Draf → Submitted → Diverifikasi → Diarsipkan)
> - Fitur mobile (offline-first, sync queue, idempotency keys, FCM, GPS, foto upload)
> - Sistem feedback dan dukungan teknis yang ada
> - Dokumentasi teknis (`PETUGAS_BACKEND_AI_GUIDE.md`, `BLUEPRINT.md`, `IMPLEMENTASI_PETUGAS_LAPORAN_FEEDBACK_DASHBOARD.md`)
> - Kode sumber aplikasi (controller, model, views, service)
>
> **Tidak ada perubahan kode yang dilakukan. Dokumen ini murni bersifat analitis dan rekomendatif.**
