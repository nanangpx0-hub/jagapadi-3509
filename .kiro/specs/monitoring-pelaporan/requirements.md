# Requirements Document

## Introduction

Fitur **Monitoring Pelaporan** adalah modul baru pada aplikasi JAGAPADI (sistem pelaporan pertanian Kabupaten Jember) yang memberikan visibilitas penuh kepada administrator dan operator terhadap aktivitas pelaporan dari seluruh petugas. Fitur ini mencakup tiga sub-sistem: (1) Dashboard monitoring dengan visualisasi dan ekspor data, (2) Pemantauan aktivitas individual petugas, dan (3) Sistem evaluasi kinerja petugas terintegrasi.

Fitur ini dibangun di atas tabel yang sudah ada (`laporan_hama`, `laporan_irigasi`, `laporan_lainnya`, `users`, `master_desa`, `master_kecamatan`, `master_kabupaten`) dengan role akses yang sudah ada (`admin`, `operator`, `petugas`), dan mengikuti pola MVC PHP 8.2 native serta UI framework AdminLTE (Bootstrap 4) yang digunakan di seluruh aplikasi.

---

## Glossary

- **Monitoring_Dashboard**: Halaman pusat yang menampilkan ringkasan dan visualisasi data pelaporan
- **Petugas**: Pengguna dengan role `petugas` yang mengirimkan laporan pertanian di lapangan
- **Admin**: Pengguna dengan role `admin` yang memiliki akses penuh terhadap seluruh fitur monitoring dan evaluasi
- **Operator**: Pengguna dengan role `operator` yang memiliki akses baca terhadap fitur monitoring dan evaluasi, namun tidak dapat menambahkan catatan evaluasi
- **Laporan**: Entri data yang dikirimkan petugas, dapat berupa laporan hama (tabel `laporan_hama`), laporan irigasi (tabel `laporan_irigasi`), atau laporan lainnya (tabel `laporan_lainnya`)
- **Kategori_Laporan**: Klasifikasi laporan berdasarkan jenisnya: `hama`, `irigasi`, atau `lainnya`
- **Periode_Monitoring**: Rentang waktu yang digunakan untuk memfilter data monitoring, meliputi harian, mingguan, bulanan, tahunan, dan kustom
- **Skor_Evaluasi**: Nilai numerik (0–100) yang dihasilkan secara otomatis setiap bulan untuk setiap petugas berdasarkan empat parameter kinerja
- **Parameter_Evaluasi**: Empat dimensi penilaian kinerja petugas: frekuensi laporan, ketepatan waktu, kelengkapan data, dan tingkat akurasi
- **Catatan_Evaluasi**: Teks bebas yang ditambahkan admin secara manual ke rekam evaluasi seorang petugas
- **CacheManager**: Sistem cache internal aplikasi JAGAPADI yang digunakan untuk menyimpan hasil query agregat
- **SimpleXLSXWriter**: Library PHP ringan yang sudah tersedia di `app/helpers/SimpleXLSXWriter.php` untuk ekspor file Excel `.xlsx`

---

## Requirements

### Requirement 1: Kontrol Akses Monitoring

**User Story:** Sebagai admin atau operator, saya ingin memastikan bahwa hanya pengguna yang berwenang yang dapat mengakses fitur monitoring, sehingga data kinerja petugas tidak bocor ke pihak yang tidak berhak.

#### Acceptance Criteria

1. WHEN pengguna dengan role `petugas` mengakses URL apapun di bawah path modul monitoring, THE Monitoring_Dashboard SHALL mengembalikan respons HTTP 403 dan menampilkan pesan yang mengindikasikan bahwa pengguna tidak memiliki akses, tanpa menampilkan konten monitoring apapun.
2. WHEN pengguna yang belum login atau sesinya sudah berakhir mengakses URL apapun di bawah path modul monitoring, THE Monitoring_Dashboard SHALL mengalihkan pengguna ke halaman login tanpa menampilkan konten monitoring apapun.
3. WHEN pengguna dengan role `admin` mengakses modul monitoring, THE Monitoring_Dashboard SHALL menampilkan daftar data kinerja seluruh petugas, detail evaluasi per petugas, kontrol tambah catatan evaluasi, dan kontrol unduh skor evaluasi.
4. WHEN pengguna dengan role `operator` mengakses modul monitoring, THE Monitoring_Dashboard SHALL menampilkan daftar data kinerja seluruh petugas dan detail evaluasi per petugas, namun menyembunyikan kontrol tambah catatan evaluasi dan kontrol unduh skor evaluasi dari tampilan UI.

---

### Requirement 2: Dashboard Monitoring — Ringkasan Statistik

**User Story:** Sebagai admin, saya ingin melihat ringkasan jumlah laporan yang dikirimkan dalam periode tertentu berdasarkan kategori, sehingga saya dapat mengidentifikasi jenis pelaporan yang paling aktif dan tren aktivitas pelaporan.

#### Acceptance Criteria

1. WHILE Monitoring_Dashboard aktif, THE Monitoring_Dashboard SHALL menampilkan total keseluruhan laporan dari ketiga kategori (`hama`, `irigasi`, `lainnya`) yang berstatus `submitted`, `verified`, `rejected`, atau `archived` (tidak termasuk `draft`) untuk Periode_Monitoring yang sedang aktif.
2. THE Monitoring_Dashboard SHALL menampilkan jumlah laporan yang dipisahkan per Kategori_Laporan (`hama`, `irigasi`, `lainnya`) dalam satu tampilan ringkasan kartu statistik.
3. WHEN pengguna memilih Periode_Monitoring, THE Monitoring_Dashboard SHALL memperbarui seluruh angka ringkasan sesuai rentang waktu yang dipilih tanpa memuat ulang halaman penuh.
4. THE Monitoring_Dashboard SHALL mendukung Periode_Monitoring berikut: harian (hari ini), mingguan (7 hari terakhir), bulanan (bulan kalender berjalan), tahunan (tahun kalender berjalan), dan kustom (pengguna memilih tanggal mulai dan tanggal akhir).
5. IF rentang tanggal kustom yang dipilih memiliki tanggal mulai lebih besar dari tanggal akhir, THEN THE Monitoring_Dashboard SHALL menampilkan pesan validasi "Tanggal mulai tidak boleh lebih besar dari tanggal akhir" dan tidak menjalankan query ke database. Rentang di mana tanggal mulai sama dengan tanggal akhir adalah valid dan diperlakukan sebagai rentang satu hari.
6. WHILE data sedang dimuat dari server, THE Monitoring_Dashboard SHALL menampilkan indikator loading pada setiap kartu statistik.
7. THE Monitoring_Dashboard SHALL menyimpan hasil query ringkasan statistik ke CacheManager dengan TTL 900 detik (15 menit). Cache diperbarui pada request berikutnya setelah TTL berakhir.
8. WHEN data gagal dimuat dari server karena error jaringan atau database, THE Monitoring_Dashboard SHALL menampilkan pesan error pada kartu statistik yang bersangkutan dan mempertahankan angka terakhir yang berhasil dimuat (jika tersedia).

---

### Requirement 3: Dashboard Monitoring — Visualisasi Data

**User Story:** Sebagai admin, saya ingin melihat data pelaporan dalam bentuk grafik interaktif, sehingga saya dapat dengan cepat memahami tren dan perbandingan antar kategori laporan.

#### Acceptance Criteria

1. WHILE Monitoring_Dashboard aktif dengan Periode_Monitoring yang dipilih, THE Monitoring_Dashboard SHALL menampilkan grafik batang yang memvisualisasikan jumlah laporan berstatus bukan `draft` per kategori (`hama`, `irigasi`, `lainnya`) untuk periode tersebut.
2. WHILE Monitoring_Dashboard aktif dengan Periode_Monitoring yang dipilih, THE Monitoring_Dashboard SHALL menampilkan diagram lingkaran yang memvisualisasikan proporsi laporan berstatus bukan `draft` per kategori terhadap total laporan.
3. WHILE Monitoring_Dashboard aktif dengan Periode_Monitoring yang dipilih, THE Monitoring_Dashboard SHALL menampilkan grafik garis tren yang memvisualisasikan jumlah laporan berstatus bukan `draft` per hari dalam rentang Periode_Monitoring, dengan nilai 0 untuk hari yang tidak memiliki laporan.
4. WHEN pengguna mengarahkan kursor ke titik data pada grafik, THE Monitoring_Dashboard SHALL menampilkan tooltip berisi label dan nilai numerik titik data tersebut.
5. WHEN Periode_Monitoring diubah, THE Monitoring_Dashboard SHALL memperbarui seluruh grafik secara bersamaan dalam waktu tidak lebih dari 3 detik menggunakan data yang sesuai dengan periode baru.
6. IF tidak ada data laporan untuk periode yang dipilih, THEN THE Monitoring_Dashboard SHALL menampilkan grafik dengan sumbu dan legenda tetap terlihat, disertai teks "Tidak ada data untuk periode ini" di area gambar.
7. THE Monitoring_Dashboard SHALL menggunakan Periode_Monitoring default 30 hari terakhir pada saat halaman pertama kali dimuat, sebelum pengguna melakukan perubahan filter.

---

### Requirement 4: Ekspor Data Monitoring

**User Story:** Sebagai admin, saya ingin mengunduh data monitoring dalam format PDF atau Excel, sehingga data dapat digunakan untuk dokumentasi, pelaporan ke pimpinan, atau keperluan arsip.

#### Acceptance Criteria

1. WHEN admin menekan tombol ekspor Excel, THE Monitoring_Dashboard SHALL memicu unduhan file `.xlsx` yang dihasilkan menggunakan library SimpleXLSXWriter yang sudah tersedia di aplikasi.
2. WHEN admin menekan tombol ekspor PDF, THE Monitoring_Dashboard SHALL membuka halaman cetak HTML di tab baru yang dirancang untuk mode cetak (print-to-PDF), sesuai pola yang sudah digunakan di aplikasi.
3. WHEN pengguna memilih ekspor, THE Monitoring_Dashboard SHALL mengekspor data sesuai dengan kombinasi Periode_Monitoring aktif, filter kategori aktif, dan filter wilayah aktif pada saat tombol ekspor ditekan. Jika tidak ada filter aktif, seluruh data pada Periode_Monitoring yang dipilih diekspor.
4. THE file ekspor Excel SHALL mengandung kolom: tanggal, kategori laporan, jumlah laporan, jumlah terverifikasi, jumlah ditolak, dan nama petugas pengirim.
5. THE file ekspor SHALL menyertakan metadata pada tiga baris pertama: (1) nama laporan "Monitoring Pelaporan JAGAPADI", (2) Periode_Monitoring yang diekspor dalam format "DD/MM/YYYY – DD/MM/YYYY", dan (3) tanggal waktu ekspor dalam format "Dicetak pada: DD/MM/YYYY HH:mm WIB".
6. IF query ekspor menghasilkan lebih dari 10.000 baris, THEN THE Monitoring_Dashboard SHALL menampilkan dialog konfirmasi "Data yang akan diekspor sangat besar (lebih dari 10.000 baris). Lanjutkan?" sebelum memproses unduhan.
7. WHEN admin hanya memiliki role `operator`, THE Monitoring_Dashboard SHALL menyembunyikan tombol ekspor data monitoring dari tampilan UI.
8. IF query ekspor menghasilkan nol baris data, THEN THE Monitoring_Dashboard SHALL membatalkan proses unduhan dan menampilkan pesan "Tidak ada data untuk diekspor pada periode dan filter yang dipilih."

---

### Requirement 5: Pemantauan Aktivitas Individual Petugas

**User Story:** Sebagai admin, saya ingin melihat daftar petugas beserta statistik aktivitas pelaporan masing-masing, sehingga saya dapat mengidentifikasi petugas yang paling aktif maupun yang perlu mendapat perhatian khusus.

#### Acceptance Criteria

1. THE Monitoring_Dashboard SHALL menampilkan daftar petugas yang diurutkan secara default dari petugas dengan jumlah laporan berstatus bukan `draft` terbanyak ke tersedikit, dengan Periode_Monitoring default adalah bulan kalender berjalan pada saat halaman pertama kali dimuat.
2. THE Monitoring_Dashboard SHALL menampilkan informasi berikut untuk setiap petugas: nama lengkap, total laporan berstatus bukan `draft` yang dikirimkan, Kategori_Laporan yang paling sering muncul (jika ada dua kategori dengan jumlah sama, tampilkan kategori dengan nilai string terkecil secara alfabetis), dan rata-rata waktu dari status `submitted` hingga `verified` dalam satuan hari dibulatkan ke 1 desimal (hanya laporan berstatus `verified` yang dihitung; jika tidak ada, tampilkan "–").
3. WHEN pengguna memilih filter kecamatan, THE Monitoring_Dashboard SHALL memperbarui daftar petugas hanya menampilkan petugas yang memiliki minimal satu laporan berasal dari kecamatan tersebut dalam Periode_Monitoring aktif.
4. WHEN pengguna memilih filter Periode_Monitoring, THE Monitoring_Dashboard SHALL memperbarui daftar petugas dan seluruh statistiknya sesuai periode yang dipilih.
5. WHEN pengguna memilih filter Kategori_Laporan, THE Monitoring_Dashboard SHALL memperbarui daftar petugas hanya berdasarkan laporan pada kategori yang dipilih dalam Periode_Monitoring aktif.
6. IF tidak ada petugas yang memiliki laporan pada kombinasi periode dan filter yang dipilih, THEN THE Monitoring_Dashboard SHALL menampilkan teks "Tidak ada data petugas untuk filter yang dipilih" menggantikan tabel.
7. WHEN pengguna mengklik nama petugas, THE Monitoring_Dashboard SHALL menavigasi ke halaman detail aktivitas petugas yang bersangkutan.
8. WHEN pengguna mengaktifkan lebih dari satu filter secara bersamaan (misalnya filter kecamatan dan filter kategori), THE Monitoring_Dashboard SHALL menerapkan seluruh filter secara kombinasi (AND logic) dan memperbarui daftar sesuai hasilnya.

---

### Requirement 6: Halaman Detail Aktivitas Petugas

**User Story:** Sebagai admin, saya ingin melihat riwayat lengkap laporan seorang petugas dalam satu halaman, sehingga saya dapat menganalisis pola pelaporan dan kinerja individual secara mendalam.

#### Acceptance Criteria

1. WHEN admin memilih seorang petugas, THE Monitoring_Dashboard SHALL menampilkan daftar seluruh laporan berstatus `submitted`, `verified`, `rejected`, atau `archived` yang dikirimkan oleh petugas tersebut, diurutkan dari laporan terbaru ke terlama, dengan kolom: kode laporan, tanggal kejadian, kategori, wilayah (desa/kecamatan), status, dan tanggal verifikasi (ditampilkan "–" jika belum diverifikasi).
2. THE Monitoring_Dashboard SHALL menampilkan statistik ringkasan di bagian atas halaman detail: total laporan, jumlah terverifikasi, jumlah ditolak, dan rata-rata waktu verifikasi (dihitung sebagai durasi hari dari `submitted` ke `verified` hanya untuk laporan `verified`; ditampilkan "–" jika tidak ada laporan terverifikasi).
3. IF petugas yang dipilih tidak memiliki laporan apapun dalam Periode_Monitoring aktif, THEN THE Monitoring_Dashboard SHALL menampilkan pesan "Petugas ini belum memiliki laporan pada periode yang dipilih" menggantikan daftar laporan, dan menampilkan nilai "0" untuk semua statistik ringkasan.
4. WHEN pengguna memilih Periode_Monitoring pada halaman detail, THE Monitoring_Dashboard SHALL memfilter daftar laporan dan memperbarui statistik ringkasan sesuai periode tersebut.
5. WHEN pengguna mengklik kode laporan pada daftar, THE Monitoring_Dashboard SHALL menavigasi ke halaman detail laporan yang bersangkutan tanpa kehilangan sesi admin.

---

### Requirement 7: Kalkulasi Skor Evaluasi Petugas

**User Story:** Sebagai admin, saya ingin sistem menghitung skor kinerja petugas secara otomatis setiap bulan menggunakan formula yang transparan, sehingga penilaian kinerja bersifat objektif dan konsisten.

#### Acceptance Criteria

1. THE Monitoring_Dashboard SHALL menghitung Skor_Evaluasi bulanan setiap petugas menggunakan formula berikut dengan bobot masing-masing parameter:

   - **Frekuensi (bobot 30%)**: `MIN((jumlah_laporan_bulan_ini / target_laporan_bulanan) × 100, 100)`
   - **Ketepatan Waktu (bobot 25%)**: `(jumlah_laporan_submitted_dalam_3_hari_sejak_tanggal_kejadian / total_laporan_submitted) × 100`
   - **Kelengkapan Data (bobot 25%)**: `(jumlah_laporan_submitted_dengan_foto_DAN_koordinat / total_laporan_submitted) × 100`
   - **Akurasi (bobot 20%)**: `(jumlah_laporan_verified / (jumlah_laporan_verified + jumlah_laporan_rejected)) × 100`
   - **Skor_Evaluasi** = `(Frekuensi × 0.30) + (Ketepatan_Waktu × 0.25) + (Kelengkapan × 0.25) + (Akurasi × 0.20)`

2. IF seorang petugas tidak memiliki laporan berstatus `submitted` pada bulan yang dihitung, THEN THE Monitoring_Dashboard SHALL menetapkan nilai 0 untuk parameter Frekuensi, Ketepatan Waktu, dan Kelengkapan Data, sehingga Skor_Evaluasi dasar adalah 0. Parameter Akurasi dihitung mengikuti kriteria 6.
3. THE Monitoring_Dashboard SHALL menggunakan `target_laporan_bulanan` default sebesar 10 laporan per bulan per petugas. Admin dapat mengubah nilai target ini melalui halaman pengaturan sistem, dan nilai yang diubah berlaku untuk seluruh perhitungan skor sejak perubahan disimpan.
4. THE Monitoring_Dashboard SHALL menampilkan breakdown nilai numerik setiap Parameter_Evaluasi (nilai sebelum dikali bobot), nilai bobot masing-masing, dan kontribusi berbobot setiap parameter, secara bersamaan dengan Skor_Evaluasi akhir.
5. THE Monitoring_Dashboard SHALL menampilkan Skor_Evaluasi dalam format numerik dengan satu angka desimal (contoh: 87.5).
6. WHEN seorang petugas tidak memiliki laporan berstatus `verified` maupun `rejected` pada bulan yang dihitung, THE Monitoring_Dashboard SHALL menampilkan nilai parameter Akurasi sebagai "–" (tidak dapat dihitung), dan menghitung Skor_Evaluasi menggunakan redistribusi bobot proporsional: Frekuensi mendapat bobot 37.5% (30/80), Ketepatan Waktu mendapat bobot 31.25% (25/80), Kelengkapan Data mendapat bobot 31.25% (25/80).

---

### Requirement 8: Rekap dan Unduh Skor Evaluasi

**User Story:** Sebagai admin, saya ingin mengunduh rekapitulasi skor evaluasi bulanan seluruh petugas, sehingga dokumen tersebut dapat digunakan sebagai bahan penilaian kinerja resmi.

#### Acceptance Criteria

1. THE Monitoring_Dashboard SHALL menampilkan tabel rekapitulasi Skor_Evaluasi semua petugas aktif untuk bulan dan tahun yang dipilih. Pada saat halaman pertama kali dimuat, periode default adalah bulan dan tahun kalender berjalan. Jika tidak ada petugas aktif dengan laporan pada periode tersebut, tabel menampilkan teks "Tidak ada data evaluasi untuk periode ini."
2. WHEN admin memilih bulan dan tahun pada halaman evaluasi, THE Monitoring_Dashboard SHALL menampilkan Skor_Evaluasi yang dihitung berdasarkan laporan berstatus bukan `draft` pada periode bulan tersebut, menggunakan formula dari Requirement 7.
3. WHEN admin menekan tombol unduh rekapitulasi, THE Monitoring_Dashboard SHALL memicu unduhan file `.xlsx` menggunakan SimpleXLSXWriter yang berisi data rekapitulasi sesuai bulan dan tahun yang sedang ditampilkan.
4. THE file ekspor rekapitulasi Skor_Evaluasi SHALL mengandung kolom: nama petugas, wilayah tugas (kecamatan dengan jumlah laporan terbanyak pada periode tersebut; jika ada dua kecamatan dengan jumlah sama, tampilkan yang pertama secara alfabetis), total laporan, skor frekuensi, skor ketepatan waktu, skor kelengkapan data, skor akurasi (ditampilkan "–" jika tidak dapat dihitung sesuai Requirement 7 kriteria 6), dan Skor_Evaluasi akhir.
5. THE file ekspor SHALL menyertakan tiga baris header: (1) "Rekapitulasi Evaluasi Kinerja Petugas JAGAPADI", (2) "Periode: [Nama Bulan] [Tahun]" (contoh: "Periode: Januari 2026"), (3) "Dicetak pada: DD/MM/YYYY HH:mm WIB".
6. WHEN pengguna dengan role `operator` mengakses halaman evaluasi, THE Monitoring_Dashboard SHALL menyembunyikan tombol unduh rekapitulasi dari tampilan UI.
7. IF tabel rekapitulasi tidak memiliki baris data (tidak ada petugas aktif dengan laporan pada periode tersebut), THEN THE Monitoring_Dashboard SHALL membatalkan proses unduhan dan menampilkan pesan "Tidak ada data untuk diekspor."

---

### Requirement 9: Catatan Evaluasi Manual oleh Admin

**User Story:** Sebagai admin, saya ingin menambahkan catatan manual pada evaluasi seorang petugas, sehingga temuan lapangan atau konteks khusus yang tidak tertangkap oleh metrik otomatis dapat dicatat secara resmi.

#### Acceptance Criteria

1. WHEN admin mengakses halaman evaluasi seorang petugas untuk bulan tertentu, THE Monitoring_Dashboard SHALL menampilkan formulir textarea untuk memasukkan Catatan_Evaluasi, dengan panjang maksimum 1.000 karakter.
2. WHEN admin menyimpan Catatan_Evaluasi, THE Monitoring_Dashboard SHALL menyimpan catatan ke tabel `evaluasi_petugas` dengan field: `user_id`, `periode_bulan`, `periode_tahun`, `catatan`, `created_by`, `created_at`, `updated_at`.
3. IF admin menyimpan teks yang setelah di-trim tidak mengandung karakter non-spasi (string kosong atau hanya spasi), THEN THE Monitoring_Dashboard SHALL menghapus catatan yang sudah tersimpan sebelumnya untuk periode dan petugas tersebut (jika ada), tanpa menyimpan catatan baru.
4. IF catatan mengandung karakter HTML khusus (seperti `<`, `>`, `"`, `'`, `&`), THEN THE Monitoring_Dashboard SHALL meng-escape karakter tersebut sebelum menyimpan ke database dan sebelum merender ke HTML, sehingga karakter ditampilkan sebagai teks literal.
5. IF catatan evaluasi tersimpan ada untuk petugas dan periode tertentu, THEN THE Monitoring_Dashboard SHALL menampilkan catatan tersebut beserta nama admin yang membuat catatan dan timestamp pembuatan dalam format "DD/MM/YYYY HH:mm WIB".
6. WHEN pengguna dengan role `operator` mengakses halaman evaluasi, THE Monitoring_Dashboard SHALL menyembunyikan formulir input Catatan_Evaluasi. Jika ada catatan tersimpan, catatan tersebut tetap ditampilkan sesuai kriteria 5.
7. WHEN admin berhasil menyimpan atau menghapus Catatan_Evaluasi, THE Monitoring_Dashboard SHALL menampilkan notifikasi konfirmasi keberhasilan. Jika operasi gagal karena error server, THE Monitoring_Dashboard SHALL menampilkan pesan error yang menjelaskan kegagalan.

---

### Requirement 10: Pembaruan Data Real-Time

**User Story:** Sebagai admin, saya ingin data pada Monitoring_Dashboard selalu mencerminkan kondisi terkini dengan jeda tidak lebih dari 15 menit, sehingga keputusan yang diambil berdasarkan data monitoring tetap relevan.

#### Acceptance Criteria

1. THE Monitoring_Dashboard SHALL menyimpan seluruh hasil query agregat (ringkasan statistik, data grafik, daftar aktivitas petugas) ke CacheManager dengan TTL maksimum 900 detik (15 menit). IF CacheManager tidak tersedia, THE Monitoring_Dashboard SHALL tetap menyajikan data melalui query langsung ke database tanpa menampilkan error kepada pengguna.
2. WHEN sebuah laporan baru disubmit, diverifikasi, ditolak, atau diarsipkan melalui modul pelaporan yang sudah ada, THE Monitoring_Dashboard SHALL menginvalidasi cache dengan prefix `monitoring:` sehingga request berikutnya mengambil data terbaru dari database.
3. WHEN data monitoring selesai dimuat, THE Monitoring_Dashboard SHALL menampilkan label "Data per [tanggal-waktu]" dalam format "DD/MM/YYYY HH:mm (WIB)" berdasarkan timestamp cache aktif.
4. WHEN halaman Monitoring_Dashboard dimuat pertama kali (cache miss atau cold start), THE Monitoring_Dashboard SHALL mengambil data langsung dari database dan menampilkan label "Data per [tanggal-waktu request saat ini] (langsung dari database)" dalam format yang sama.

---

### Requirement 11: Tampilan Responsif AdminLTE

**User Story:** Sebagai admin, saya ingin mengakses Monitoring_Dashboard baik dari desktop maupun perangkat mobile, sehingga monitoring dapat dilakukan kapan saja dan di mana saja.

#### Acceptance Criteria

1. THE Monitoring_Dashboard SHALL menggunakan komponen dan kelas CSS dari AdminLTE (Bootstrap 4) yang sudah digunakan di aplikasi, tanpa menambahkan framework CSS baru.
2. IF lebar viewport lebih kecil dari 768px, THEN THE Monitoring_Dashboard SHALL menampilkan layout satu kolom untuk seluruh konten halaman.
3. IF lebar viewport lebih besar dari atau sama dengan 768px, THEN THE Monitoring_Dashboard SHALL menampilkan layout minimum dua kolom untuk kartu statistik dan panel filter.
4. THE Monitoring_Dashboard SHALL memastikan seluruh tabel data dapat di-scroll secara horizontal pada viewport mobile menggunakan kelas `table-responsive` Bootstrap.
5. THE Monitoring_Dashboard SHALL memastikan seluruh grafik visualisasi memiliki lebar 100% dari lebar container-nya sehingga menyesuaikan ukuran secara otomatis mengikuti perubahan ukuran layar.

---

### Requirement 12: Keamanan dan Integritas Data

**User Story:** Sebagai admin sistem, saya ingin memastikan bahwa seluruh operasi pada modul monitoring mengikuti standar keamanan yang berlaku di aplikasi JAGAPADI, sehingga tidak ada celah keamanan yang diperkenalkan oleh fitur baru ini.

#### Acceptance Criteria

1. THE Monitoring_Dashboard SHALL menggunakan PDO prepared statements untuk seluruh query database yang melibatkan parameter dari input pengguna (filter tanggal, filter wilayah, filter kategori, ID petugas).
2. WHEN menerima parameter filter dari URL atau form, THE Monitoring_Dashboard SHALL memvalidasi tipe data dan format setiap parameter sebelum digunakan dalam query: tanggal harus format `Y-m-d`, ID pengguna atau wilayah harus integer positif, kategori harus salah satu dari nilai yang terdaftar pada tabel referensi kategori (`hama`, `irigasi`, `lainnya`).
3. IF parameter filter yang diterima tidak lolos validasi, THEN THE Monitoring_Dashboard SHALL menolak request dengan respons HTTP 422 dan pesan error yang menjelaskan parameter mana yang tidak valid, tanpa memproses query ke database.
4. THE Monitoring_Dashboard SHALL menerapkan CSRF token pada seluruh form yang bersifat mutasi data, khususnya form penyimpanan Catatan_Evaluasi dan pengaturan `target_laporan_bulanan`. WHEN CSRF token tidak valid atau tidak ada, THE Monitoring_Dashboard SHALL menolak request dengan HTTP 403.
5. WHEN pengguna dengan role `operator` mengirimkan request POST/PUT/DELETE ke endpoint penyimpanan catatan evaluasi atau pengaturan target laporan, THE Monitoring_Dashboard SHALL mengembalikan HTTP 403 tanpa memproses permintaan.
6. THE Monitoring_Dashboard SHALL menggunakan `htmlspecialchars()` pada seluruh output teks dinamis yang berasal dari database sebelum dirender ke HTML.
