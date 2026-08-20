# Audit dan Perbaikan Fitur Harga Komoditas

Tanggal audit: 11 Agustus 2026  
URL: `/hargaKomoditas`

## Alur data setelah perbaikan

1. Admin memilih sumber dan periode pengambilan data.
2. `HargaKomoditasScraper` memvalidasi tahun, bulan, dan sumber.
3. Sumber `siskaperbapo` mengambil harga beras Kabupaten Jember dari endpoint
   SISKAPERBAPO. Data disimpan sebagai `aktual`.
4. Estimasi GKP/GKG hanya dibuat ketika harga beras medium pada tanggal yang
   sama tersedia. Estimasi diberi sumber dan metode `estimasi` serta keterangan
   rumus; estimasi tidak diklaim sebagai observasi dinas.
5. Sumber `simulation` hanya berjalan jika dipilih eksplisit. Hasilnya
   deterministik, diberi metode `simulasi`, dan tidak memuat tanggal masa depan.
6. Scraper dan import memakai upsert dengan grain unik:
   `tanggal + jenis_komoditas + lokasi + sumber_data`.
7. Tabel, total, statistik, kartu ringkasan, grafik, peta, dan ekspor memakai
   filter yang sama. Default analisis adalah `non_simulasi`.
8. Alert dihitung dari perubahan rata-rata harian data non-simulasi per
   komoditas, bukan dari dua baris lokasi/sumber yang kebetulan terakhir.

## Temuan dan dampak

| Temuan | Dampak sebelum perbaikan | Perbaikan |
|---|---|---|
| `countAll`, statistik, dan ringkasan mengabaikan sebagian filter | Tabel kosong dapat menampilkan total/statistik berisi data lain | Satu pembentuk filter dipakai seluruh query |
| Pengambilan ulang selalu `INSERT` | 872 duplikasi aktif dan agregasi berbobot ganda | Unique key dan upsert idempoten |
| Gagal mengambil sumber resmi otomatis membuat simulasi | Data sintetis terlihat seperti hasil sumber yang dipilih | Tidak ada fallback otomatis; status `no_data` dikembalikan |
| Estimasi gabah memakai nilai default dan label Dinas | Estimasi dapat dianggap data resmi | Estimasi hanya dari observasi medium yang tersedia dan diberi label eksplisit |
| Periode masa depan diganti tanggal hari ini | Data tersimpan pada periode yang salah | Periode masa depan ditolak dan target kosong tetap kosong |
| Kode lokasi simulasi membaca nama kolom yang salah | Semua lokasi memperoleh kode `35.09` | Menggunakan kolom `master_kecamatan.kode` |
| Simulasi memakai angka acak global | Hasil berubah setiap eksekusi | Simulasi deterministik berdasarkan tanggal/lokasi/komoditas |
| Perubahan harga membandingkan dua baris, bukan dua hari | Alert palsu akibat lokasi/sumber berbeda pada hari yang sama | Rata-rata per tanggal dan alert unik per komoditas/tanggal |
| Koordinat peta diacak setiap permintaan | Marker berpindah dan tidak merepresentasikan kecamatan | Koordinat dari `master_kecamatan`; fallback hanya untuk pusat Jember |
| Fungsi invalidasi cache dipanggil tanpa implementasi lokal yang valid | Mutasi tertentu berpotensi fatal atau menyajikan cache lama | Menggunakan implementasi `Controller` dan invalidasi setelah mutasi sukses |
| Preview alert/import belum konsisten CSRF dan output dinamis tidak di-escape | Risiko mutasi tanpa token dan stored XSS | CSRF, validasi upload, pembersihan file sementara, serta escape HTML |
| Warna grafik menghasilkan nilai RGBA tidak valid | Warna area grafik dapat gagal dirender | Format `rgba(r, g, b, a)` yang valid |

## Migrasi integritas

Migrasi `2026_08_11_fix_harga_komoditas_integrity.php`:

- menambahkan `metode_data`;
- mengklasifikasikan data lama;
- menyalin duplikasi ke `harga_komoditas_duplicate_backup_20260811` sebelum
  menghapus baris aktif yang berlebih;
- menambahkan `uk_harga_observation`;
- menyalin alert lama ke `harga_alerts_backup_20260811` dan membangun ulang
  alert harian;
- aman dijalankan ulang.

Hasil migrasi lokal: 872 baris duplikat diarsipkan, 0 grup duplikat tersisa,
dan alert non-simulasi aktif menjadi 13 pasangan komoditas/tanggal yang unik.

## Verifikasi

- PHPUnit: 45 test, 160 assertion, seluruhnya lulus.
- Test khusus harga komoditas: 7 test, 26 assertion, seluruhnya lulus.
- Pengambilan SISKAPERBAPO periode berjalan: 32 observasi berhasil diproses,
  0 gagal.
- Pengambilan ulang periode yang sama: 0 insert, 0 update, 32 tidak berubah.
- Endpoint dengan lokasi yang tidak ada mengembalikan tabel 0, total 0,
  statistik kosong, dan ringkasan total 0.
- Endpoint tabel, grafik, dan peta merespons sukses dengan data non-simulasi.
- Request mutasi alert tanpa CSRF ditolak dengan HTTP 419.
- PHP lint lulus dan blok JavaScript utama hasil render lulus pemeriksaan sintaks.

## Risiko tersisa

Ketersediaan dan struktur respons SISKAPERBAPO berada di luar kendali aplikasi.
Implementasi sekarang gagal secara transparan tanpa membuat data sintetis,
mencatat kegagalan, dan dapat dijalankan ulang tanpa menggandakan data setelah
sumber pulih. Estimasi gabah tetap merupakan model sederhana 52%/60%; untuk
analisis kebijakan, observasi gabah resmi sebaiknya diintegrasikan ketika sumber
resmi yang stabil tersedia.
