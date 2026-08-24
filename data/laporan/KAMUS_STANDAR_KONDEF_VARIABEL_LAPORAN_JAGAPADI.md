# KAMUS STANDAR KONSEP DAN DEFINISI (KONDEF)
## VARIABEL LAPORAN SISTEM JAGAPADI

**JAGAPADI — Jember Agrikultur Gapai Prestasi Digital**  
**Dokumen**: Standar Acuan Variabel dan Metadata Pelaporan Pertanian Terpadu Kabupaten Jember  
**Versi**: 2.0.0  
**Status Dokumen**: Dokumen Acuan Resmi  
**Sasaran Pengguna**: Petugas Lapangan (POPT/PPL), Administrator Sistem, Operator Data, Analis/Statistisi Pertanian, dan Pengembang Sistem.

---

## Pengesahan

Dokumen ini menjadi pedoman baku untuk pengumpulan, pengolahan, validasi, pertukaran, analisis, dan penyajian data laporan pada Sistem JAGAPADI. Setiap pengembangan formulir, basis data, API, dashboard, dan prosedur verifikasi wajib mengacu pada definisi serta aturan yang ditetapkan dalam dokumen ini.

---

## Daftar Isi

1. Ketentuan Umum Pelaporan
2. Status, Tata Kelola, dan Audit Laporan
3. Variabel Universal
4. Variabel Spasial dan Geotagging
5. Variabel Laporan OPT
6. Variabel Laporan Irigasi
7. Variabel Laporan Sektoral
8. Variabel Laporan Dinamis
9. Variabel Feedback
10. Variabel Statistik dan Analitik
11. Matriks Variabel Utama
12. Checklist Pengisian

---

# 1. Ketentuan Umum Pelaporan

Sistem JAGAPADI menghimpun data lapangan dan data sektoral pertanian Kabupaten Jember. Seluruh data laporan harus akurat, dapat ditelusuri, terverifikasi, memiliki satuan yang jelas, dan dapat dipertanggungjawabkan untuk kebutuhan operasional maupun analitik.

## 1.1 Prinsip pengelolaan data

- **Keterlacakan**: Setiap laporan harus dapat ditelusuri melalui identitas pelapor, nomor laporan, waktu pencatatan, lokasi, dan riwayat perubahan status.
- **Keabsahan sumber**: `user_id` ditetapkan server dari sesi autentikasi; nilai tersebut tidak boleh diisi bebas oleh pengguna pada formulir.
- **Integritas wilayah**: Kecamatan harus merupakan turunan dari kabupaten yang dipilih; desa/kelurahan harus merupakan turunan dari kecamatan yang dipilih.
- **Konsistensi satuan**: Nilai numerik wajib memiliki satuan yang sesuai. Nilai populasi/intensitas OPT tidak boleh digunakan sebagai luas lahan, dan sebaliknya.
- **Keamanan data**: Input disimpan menggunakan parameterized query/prepared statement. Teks ditampilkan dengan mekanisme escaping untuk mencegah XSS.
- **Kelengkapan saat pengiriman**: Atribut bertanda wajib harus lolos validasi sebelum laporan dapat berubah menjadi `submitted`.

## 1.2 Istilah waktu

| Variabel | Definisi | Pengelola |
|---|---|---|
| `tanggal_kejadian` | Tanggal kejadian atau tanggal pengamatan faktual di lapangan | Petugas, tervalidasi sistem |
| `created_at` | Waktu pertama kali rekaman dibuat di server | Sistem |
| `updated_at` | Waktu perubahan terakhir pada rekaman | Sistem |
| `submitted_at` | Waktu laporan resmi dikirimkan | Sistem |
| `verified_at` | Waktu laporan diverifikasi atau ditolak | Sistem/Admin |

---

# 2. Status, Tata Kelola, dan Audit Laporan

## 2.1 Enum status baku

Nilai `status` yang digunakan secara baku pada seluruh modul laporan lapangan adalah:

| Nilai Sistem | Label Antarmuka | Definisi |
|---|---|---|
| `draft` | Draf | Laporan belum dikirim secara resmi dan belum masuk agregasi statistik resmi |
| `submitted` | Dikirim | Laporan telah dikirim oleh petugas dan menunggu pemeriksaan |
| `verified` | Diverifikasi | Laporan telah diperiksa serta disahkan oleh admin/verifikator |
| `rejected` | Ditolak | Laporan dikembalikan kepada pelapor untuk diperbaiki |
| `archived` | Diarsipkan | Laporan tervalidasi yang disimpan untuk kebutuhan historis/audit |

> Sistem dapat menampilkan label berbahasa Indonesia, namun penyimpanan database dan API menggunakan nilai sistem huruf kecil sebagaimana tabel di atas.

## 2.2 Transisi status yang diizinkan

| Status Asal | Status Tujuan | Pelaksana | Ketentuan |
|---|---|---|---|
| `draft` | `submitted` | Pemilik laporan | Seluruh atribut wajib telah valid |
| `submitted` | `verified` | Admin/verifikator | Pemeriksaan dinyatakan selesai dan valid |
| `submitted` | `rejected` | Admin/verifikator | `catatan_verifikasi` wajib diisi |
| `rejected` | `submitted` | Pemilik laporan | Laporan telah diperbaiki dan dikirim ulang |
| `verified` | `archived` | Admin | Dilakukan sesuai kebijakan arsip |

Setiap perubahan status wajib dicatat pada tabel `laporan_status_history` dengan atribut minimal: `laporan_id`, `status_lama`, `status_baru`, `changed_by`, `changed_at`, dan `catatan`.

## 2.3 Nomor laporan

| Atribut | Ketentuan |
|---|---|
| Nama teknis | `nomor_laporan` atau `kode_laporan` |
| Definisi | Identitas unik laporan yang diterbitkan otomatis pada pengiriman pertama |
| Format | Alfanumerik terstruktur, misalnya `LH-20260820-0001` |
| Penerbitan | Dibuat atomik saat status berubah dari `draft` menjadi `submitted` |
| Keunikan | Wajib memiliki `UNIQUE constraint` secara global |
| Pengiriman ulang | Nomor awal tetap digunakan saat laporan yang ditolak dikirim ulang |

---

# 3. Variabel Universal

| Variabel | Nama Teknis | Tipe/Format | Definisi dan Aturan |
|---|---|---|---|
| Jenis laporan | `jenis_laporan` / `jenis_id` | Enum atau FK master | Klasifikasi utama laporan. Wajib dipilih pada pembuatan laporan dan tidak dapat diubah setelah nomor laporan diterbitkan. |
| Tanggal kejadian | `tanggal_kejadian` / `tanggal` | `DATE` (`YYYY-MM-DD`) | Tanggal observasi atau kejadian. Tidak boleh melebihi tanggal saat pengiriman. Pengisian lebih dari 30 hari ke belakang memerlukan otorisasi data historis. |
| Pelapor | `user_id` | Integer, FK `users.id` | Identitas akun pelapor. Diisi otomatis dari sesi autentikasi server. |
| Status | `status` | Enum baku | Mengikuti enum dan transisi pada Bab 2. |
| Foto dokumentasi | `foto_url` | `VARCHAR(300)` | Path bukti visual. Wajib saat submit kecuali terdapat pengecualian resmi yang tercatat. Maksimum 5 MB per berkas. |
| Catatan lapangan | `catatan` / `deskripsi` | `TEXT` | Narasi kondisi, gejala, tindakan awal, dan konteks lapangan. Maksimum 5.000 karakter. |
| Verifikator | `verified_by` | Integer, FK `users.id` | Admin/verifikator yang mengesahkan atau menolak laporan. |
| Waktu verifikasi | `verified_at` | `DATETIME` | Waktu tindakan verifikasi atau penolakan. |
| Catatan verifikasi | `catatan_verifikasi` | `TEXT` | Alasan penolakan, arahan perbaikan, atau hasil pemeriksaan. Wajib saat status menjadi `rejected`. |

## 3.1 Nilai jenis laporan

Jenis laporan harus bersumber dari master data aktif. Nilai contoh meliputi `hama`, `irigasi`, `pupuk`, `panen`, `cuaca`, `alat_sarana`, `bibit_baru`, `rumah_kaca`, `kerusakan_cuaca`, serta kategori feedback seperti `bug`, `fitur_baru`, dan `peningkatan`.

## 3.2 Standar unggah foto

- Format yang diterima: `.jpg`, `.jpeg`, `.png`, dan `.webp`; modul feedback dapat menerima PDF bila diizinkan.
- Validasi dilakukan berdasarkan tipe konten aktual melalui inspeksi MIME/magic bytes, bukan hanya ekstensi.
- Nama berkas harus dihasilkan acak oleh server.
- Direktori unggahan tidak boleh mengizinkan eksekusi skrip.
- Jumlah foto per laporan dan pengecualian kewajiban foto harus ditetapkan oleh konfigurasi modul.

---

# 4. Variabel Spasial dan Geotagging

| Variabel | Nama Teknis | Tipe/Format | Definisi dan Aturan |
|---|---|---|---|
| Kabupaten | `kabupaten_id` | Integer, FK master | Wilayah administratif tingkat kabupaten. Untuk JAGAPADI menggunakan Kabupaten Jember, kode BPS `3509`. |
| Kecamatan | `kecamatan_id` | Integer, FK master | Wilayah di bawah kabupaten. Wajib menjadi turunan kabupaten yang dipilih. |
| Desa/kelurahan | `desa_id` | Integer, FK master | Wilayah di bawah kecamatan. Wajib menjadi turunan kecamatan yang dipilih. |
| Alamat lengkap | `alamat_lengkap` | `VARCHAR(300)` | Alamat, blok sawah, nomor petak, kelompok tani, atau penanda lokal. |
| Nama lokasi | `lokasi` | `VARCHAR(300)` | Nama hamparan, aset, atau lokasi pengamatan. |
| Latitude | `latitude` | `DECIMAL(10,7)` | Lintang WGS 84 dalam derajat desimal. |
| Longitude | `longitude` | `DECIMAL(10,7)` | Bujur WGS 84 dalam derajat desimal. |

## 4.1 Aturan koordinat

- Latitude dan longitude wajib diisi berpasangan.
- Rentang global: latitude `-90` sampai `90`; longitude `-180` sampai `180`.
- Rentang validasi operasional Jember: latitude sekitar `-8.55` sampai `-8.00`; longitude sekitar `113.40` sampai `114.05`.
- Pada status `draft`, koordinat dapat kosong. Pada status `submitted`, koordinat harus valid.
- Sistem disarankan melakukan validasi titik terhadap poligon wilayah desa/kecamatan terpilih apabila data poligon tersedia.

---

# 5. Variabel Laporan OPT

| Variabel | Nama Teknis | Tipe/Format | Definisi dan Aturan |
|---|---|---|---|
| Jenis OPT | `master_opt_id` | Integer, FK `master_opt.id` | Jenis organisme pengganggu tumbuhan yang dipilih dari master aktif. |
| Tingkat keparahan | `tingkat_keparahan` | Enum | Klasifikasi dampak serangan: `ringan`, `sedang`, atau `berat`. |
| Nilai pengamatan | `nilai_pengamatan` | `DECIMAL(10,2)` | Nilai hasil pengamatan populasi atau intensitas. Tidak boleh negatif. |
| Jenis pengukuran | `jenis_pengukuran` | Enum | Menjelaskan apakah nilai merupakan `populasi` atau `intensitas`. |
| Satuan pengukuran | `satuan_pengukuran` | FK/teks terkendali | Satuan nilai pengamatan, misalnya `ekor/rumpun` atau `% daun terserang`. |
| Luas serangan | `luas_serangan` | `DECIMAL(8,2)` Ha | Luas hamparan yang terdampak secara nyata. Nilai valid 0,01 sampai 9.999,99 Ha. |
| ETL acuan | `etl_acuan` | `DECIMAL(10,2)` | Ambang ekonomi dari `master_opt`; tidak diinput manual oleh petugas. |
| Satuan ETL | `satuan_etl` | `VARCHAR(50)` | Satuan ETL yang sesuai dengan jenis OPT. |

## 5.1 Klasifikasi keparahan

| Nilai | Definisi operasional |
|---|---|
| `ringan` | Gejala kerusakan kurang dari 25% atau berada pada tingkat terkendali sesuai pedoman OPT dan komoditas. |
| `sedang` | Gejala kerusakan sekitar 25% sampai 50% atau telah memerlukan pengendalian aktif. |
| `berat` | Gejala kerusakan di atas 50%, terdapat ancaman puso, atau memerlukan penanganan terpadu skala hamparan. |

> Ambang keparahan harus dapat disesuaikan menurut jenis OPT, komoditas, fase pertumbuhan, dan pedoman teknis yang berlaku. Jika `tingkat_keparahan` bernilai `berat`, nilai pengamatan dan satuannya wajib tersedia.

---

# 6. Variabel Laporan Irigasi

| Variabel | Nama Teknis | Tipe/Format | Definisi dan Aturan |
|---|---|---|---|
| Nama saluran | `nama_saluran` | `VARCHAR(150)` | Nomenklatur saluran irigasi. Minimal 3 karakter dan tidak ambigu. |
| Daerah irigasi | `daerah_irigasi` | `VARCHAR(200)` | Nama daerah irigasi atau bendung pengatur. |
| Jenis saluran | `jenis_saluran` | Enum | `primer`, `sekunder`, atau `tersier`. |
| Status ketersediaan air | `status_ketersediaan_air` | Enum | `cukup`, `kurang`, atau `kering`, berdasarkan observasi lapangan. |
| Debit terukur | `debit_terukur_lps` | `DECIMAL(10,2)` | Debit operasional dalam liter per detik jika tersedia dari alat ukur/sensor. |
| Kondisi fisik | `kondisi_fisik` | Enum | `baik`, `rusak_ringan`, `rusak_sedang`, atau `rusak_berat`. |
| Luas layanan | `luas_layanan` | `DECIMAL(10,2)` Ha | Total luas sawah fungsional yang dilayani. Nilai harus lebih dari nol. |
| Aksi dilakukan | `aksi_dilakukan` | `VARCHAR(255)` | Tindakan mitigasi yang telah dilakukan. |
| Status perbaikan | `status_perbaikan` | Enum | `belum_ditangani`, `proses_perbaikan`, atau `selesai`. |

Laporan kondisi fisik `rusak_ringan`, `rusak_sedang`, atau `rusak_berat` wajib disertai foto bukti.

---

# 7. Variabel Laporan Sektoral

## 7.1 Pemupukan

| Variabel | Nama Teknis | Tipe/Format | Aturan |
|---|---|---|---|
| Jenis pupuk | `jenis_pupuk` | FK master/teks terkendali | Wajib diisi saat submit. |
| Dosis | `dosis` | Decimal positif | Wajib lebih dari nol. |
| Satuan dosis | `satuan_dosis` | FK master/teks terkendali | Contoh: `kg/ha`, `kuintal/ha`, `gram/tanaman`. |
| Metode aplikasi | `metode_aplikasi` | Enum/teks terkendali | Contoh: `tebar`, `kocor`, `tugal`, `foliar`. |
| OPT terkait | `master_opt_id` | Integer, FK, opsional | Diisi jika pemupukan berkaitan dengan pemulihan pascaserangan OPT. |

## 7.2 Panen

| Variabel | Nama Teknis | Tipe/Format | Aturan |
|---|---|---|---|
| Komoditas | `komoditas` | FK master/teks terkendali | Jenis tanaman dan, bila ada, varietas. |
| Luas panen | `luas_panen` | Decimal Ha | Wajib lebih dari nol. |
| Volume panen | `volume_panen` | Decimal | Wajib lebih dari nol. |
| Satuan hasil | `satuan_panen` | Enum/FK master | Nilai baku: `kg`, `kuintal`, `ton_gkp`, `ton_gkg`. |
| Harga per unit | `harga_per_unit` | Decimal Rupiah | Harga pada tingkat petani atau transaksi yang dicatat. |

## 7.3 Cuaca mikro

| Variabel | Nama Teknis | Tipe/Format | Aturan |
|---|---|---|---|
| Kondisi cuaca | `kondisi_cuaca` | Enum | Contoh: `cerah`, `berawan`, `hujan_ringan`, `hujan_lebat`, `angin_kencang`. |
| Suhu udara | `suhu` | Decimal °C | Rentang validasi operasional ditentukan per konfigurasi; contoh 18–40 °C. |
| Kelembaban | `kelembaban` | Decimal % | Rentang 0–100. |
| Curah hujan | `curah_hujan` | Decimal mm | Nilai tidak boleh negatif. |
| Kecepatan angin | `kecepatan_angin` | Decimal km/jam | Nilai tidak boleh negatif. |
| Arah angin | `arah_angin` | Derajat/arah mata angin | Dapat berupa azimut 0–360 atau nilai master arah mata angin. |

## 7.4 Alat dan sarana pertanian

| Variabel | Nama Teknis | Tipe/Format | Aturan |
|---|---|---|---|
| Jenis sarana | `jenis_sarana` | FK master/teks terkendali | Kategori alsintan atau sarana. |
| Nama alat/merek | `nama_alat` | Teks | Minimal 3 karakter. |
| Jumlah unit | `jumlah` | Integer positif | Minimal 1. |
| Kondisi alat | `kondisi` | Enum | `baik`, `rusak_ringan`, `rusak_berat`, atau `tidak_beroperasi`. |

---

# 8. Variabel Laporan Dinamis

Laporan dinamis disimpan dalam tabel `laporan_lainnya` dengan atribut umum dan payload terstruktur `data_json`. Atribut umum tetap mengikuti standar universal, spasial, dan status pada dokumen ini.

## 8.1 Atribut kerangka

`id`, `kode_laporan`, `jenis_id`, `judul`, `tanggal_kejadian`, `user_id`, `kabupaten_id`, `kecamatan_id`, `desa_id`, `lokasi`, `latitude`, `longitude`, `deskripsi`, `foto_url`, `status`, dan `data_json`.

## 8.2 Subjenis dan atribut minimum

| Subjenis | Atribut `data_json` minimum |
|---|---|
| `bibit_baru` | `nama_varietas`, `jumlah_bibit`, `satuan_bibit`, `sumber_bibit` |
| `rumah_kaca` | `jumlah_unit`, `luas_m2`, `komoditas` |
| `bantuan_alsintan` | `nama_alat`, `jumlah`, `sumber_bantuan` |
| `kerusakan_cuaca` | `jenis_cuaca`, `luas_terdampak_ha` |

Setiap subjenis wajib memiliki JSON schema atau validator server-side tersendiri agar struktur dan tipe data konsisten.

---

# 9. Variabel Feedback

| Variabel | Nama Teknis | Tipe/Format | Aturan |
|---|---|---|---|
| ID feedback | `id` | Bigint PK | Diterbitkan otomatis. |
| Pelapor | `user_id` | Integer FK | Diambil dari sesi aktif. |
| Jenis feedback | `jenis_feedback` | Enum | `bug`, `fitur_baru`, atau `peningkatan`. |
| Judul | `judul` | `VARCHAR(255)` | Wajib, panjang 5–255 karakter. |
| Deskripsi | `deskripsi` | `TEXT` | Wajib, panjang 20–5.000 karakter. |
| Prioritas | `prioritas` | Enum | `rendah`, `medium`, atau `tinggi`; default `medium`. |
| Status penanganan | `status` | Enum | `diterima`, `dalam_proses`, `selesai`, atau `ditolak`. |
| Lampiran | `attachment_url` | Path | Opsional; maksimum 5 MB dan harus lolos validasi MIME. |
| Catatan admin | `admin_notes` | `TEXT` | Tanggapan resmi dari admin. |
| Admin pemroses | `processed_by` | Integer FK | Terisi otomatis saat pemrosesan. |
| Waktu pemrosesan | `processed_at` | `DATETIME` | Terisi otomatis saat status diperbarui. |
| Dukungan | `vote_count` | Integer | Sinkron dari tabel suara; pelapor tidak dapat memberikan suara pada feedback sendiri. |

---

# 10. Variabel Statistik dan Analitik

## 10.1 Statistik pertanian BPS/KSA

Atribut utama: `tahun`, `bulan`, `kabupaten_kota`, `kode_wilayah`, `luas_panen`, `produksi_gabah`, `produksi_beras`, `produktivitas`, `status_data`, `sumber_data_type`, dan `tipe_skenario`.

## 10.2 Produksi gabah lapangan

Atribut utama: `unique_id`, `musim_tanam`, `varietas`, `luas_tanam`, `luas_panen`, `produksi_total`, `kadar_air`, `grade_kualitas`, `harga_gabah`, dan `produktivitas`.

## 10.3 Operasional irigasi

Atribut utama: `debit_terukur_lps`, `status_pintu`, dan `metode_data` (`aktual`, `manual`, atau `simulasi`).

## 10.4 Cuaca agregat

Atribut utama: `curah_hujan`, `kecepatan_angin`, `kecepatan_max`, `arah_angin`, dan `arah_angin_desc`.

## 10.5 Evaluasi akurasi

| Variabel | Definisi |
|---|---|
| `luas_estimasi_daerah` | Estimasi luas panen dari rekap daerah, dalam Ha |
| `luas_rilis_bps` | Luas panen rilis BPS, dalam Ha |
| `deviasi_absolut` | \( |Estimasi - BPS| \) |
| `persentase_bias` | \(\frac{|Estimasi - BPS|}{BPS} \times 100\%\) |
| `status_akurasi` | `sangat_akurat` (<5%), `perlu_perhatian` (5–10%), atau `bias_tinggi` (>10%) |

## 10.6 Analitik produksi bulanan

Atribut utama: `total_luas_panen`, `faktor_penyebab_utama`, `skor_risiko_cuaca`, `skor_risiko_hama`, `skor_risiko_total`, `avg_curah_hujan_lag1`, `total_laporan_hama_lag1`, `laporan_hama_berat_lag1`, `narasi_otomatis`, `narasi_final`, dan `status_analisis`.

---

# 11. Matriks Variabel Utama

| No. | Variabel | Kolom Database | Format | Wajib saat Submit | Modul |
|---:|---|---|---|---|---|
| 1 | Jenis laporan | `jenis_laporan` / `jenis_id` | Enum/FK | Ya | Seluruh modul |
| 2 | Nomor laporan | `nomor_laporan` | Alfanumerik | Otomatis | Seluruh laporan lapangan |
| 3 | Tanggal kejadian | `tanggal_kejadian` | Date | Ya | Seluruh modul |
| 4 | Pelapor | `user_id` | Integer FK | Otomatis | Seluruh modul |
| 5 | Kabupaten | `kabupaten_id` | Integer FK | Ya | Seluruh modul |
| 6 | Kecamatan | `kecamatan_id` | Integer FK | Ya | Seluruh modul |
| 7 | Desa/kelurahan | `desa_id` | Integer FK | Ya | Seluruh modul |
| 8 | Lokasi/alamat | `lokasi`, `alamat_lengkap` | String | Ya | Laporan lapangan |
| 9 | Koordinat | `latitude`, `longitude` | Decimal | Ya | Laporan lapangan |
| 10 | Foto | `foto_url` | File path | Ya* | Laporan lapangan |
| 11 | Deskripsi | `catatan`, `deskripsi` | Text | Disarankan | Seluruh modul |
| 12 | Status | `status` | Enum baku | Otomatis | Seluruh modul |
| 13 | Jenis OPT | `master_opt_id` | Integer FK | Ya | OPT |
| 14 | Keparahan OPT | `tingkat_keparahan` | Enum | Ya | OPT |
| 15 | Nilai pengamatan OPT | `nilai_pengamatan` | Decimal | Kondisional | OPT |
| 16 | Luas serangan | `luas_serangan` | Decimal Ha | Ya | OPT |
| 17 | Nama saluran | `nama_saluran` | String | Ya | Irigasi |
| 18 | Ketersediaan air | `status_ketersediaan_air` | Enum | Ya | Irigasi |
| 19 | Debit terukur | `debit_terukur_lps` | Decimal L/detik | Opsional | Irigasi |
| 20 | Kondisi fisik | `kondisi_fisik` | Enum | Ya | Irigasi |
| 21 | Luas layanan | `luas_layanan` | Decimal Ha | Ya | Irigasi |
| 22 | Dosis pupuk | `jenis_pupuk`, `dosis` | FK/String, Decimal | Ya | Pupuk |
| 23 | Panen | `komoditas`, `luas_panen`, `volume_panen` | String/FK, Decimal | Ya | Panen |
| 24 | Cuaca mikro | `kondisi_cuaca` | Enum | Ya | Cuaca |
| 25 | Alsintan | `jenis_sarana`, `kondisi` | FK/String, Enum | Ya | Alat dan sarana |
| 26 | Prioritas feedback | `prioritas` | Enum | Ya | Feedback |
| 27 | Verifikator | `verified_by` | Integer FK | Otomatis | Laporan lapangan |
| 28 | Waktu verifikasi | `verified_at` | Datetime | Otomatis | Laporan lapangan |

\* Dapat dikecualikan hanya berdasarkan kebijakan modul dan alasan yang tercatat.

---

# 12. Checklist Pengisian Laporan

Sebelum memilih **Kirim Laporan**, petugas wajib memastikan bahwa:

1. Tanggal pengamatan sesuai dengan tanggal faktual kegiatan atau kejadian.
2. Kabupaten, kecamatan, dan desa dipilih secara berjenjang dan sesuai lokasi.
3. Latitude dan longitude telah diperoleh dengan benar serta berada di wilayah pelaporan.
4. Jenis laporan, OPT, komoditas, pupuk, atau alsintan dipilih dari master data yang berlaku.
5. Semua nilai angka menggunakan satuan yang tepat dan tidak tercampur.
6. Luas serangan, luas layanan, atau luas panen dinyatakan dalam hektar sesuai kondisi nyata.
7. Foto bukti diambil dari kondisi lapangan dan dapat dibaca dengan jelas.
8. Catatan lapangan memuat gejala, kondisi, dan tindakan yang telah dilakukan.
9. Laporan diperiksa kembali sebelum dikirim; gunakan status `draft` apabila koneksi atau data belum memadai.

---

## Penutup

Dokumen ini ditetapkan sebagai standar acuan baku pengumpulan, pengolahan, validasi, dan penyajian data Sistem JAGAPADI Kabupaten Jember. Perubahan terhadap definisi variabel, enum, satuan, atau aturan validasi wajib melalui pengendalian versi dokumen dan penyesuaian terkoordinasi pada basis data, API, formulir, serta dashboard.
