# Laporan Pengujian UI/UX Mobile JAGAPADI v1.1

Tanggal pengujian: 14 Agustus 2026

## Cakupan otomatis

| Pemeriksaan | Target | Hasil |
|---|---:|---|
| Kontras primary/onPrimary | WCAG AA ≥ 4,5:1 | Lulus |
| Kontras surface/onSurface terang | WCAG AAA ≥ 7:1 | Lulus |
| Kontras surface/onSurface gelap | WCAG AAA ≥ 7:1 | Lulus |
| Target sentuh tombol | ≥ 48dp | Lulus |
| Ponsel 360dp | 1 kolom | Lulus |
| Tablet 600–959dp | 2 kolom | Lulus |
| Layar ≥ 960dp | 3 kolom | Lulus |
| Dart analyzer pada file perubahan | 0 error | Lulus |
| Flutter unit/widget suite | seluruh tes lulus | Lulus |
| Build APK release Android | APK terbentuk | Lulus |

## Skenario visual yang divalidasi lewat implementasi

- Teks panjang menggunakan maksimum baris dan ellipsis untuk mencegah overflow.
- Beranda dibatasi lebar maksimum dan kartu berubah jumlah kolom berdasarkan ruang tersedia.
- Tema terang dan gelap memakai pasangan warna teks/surface berbeda.
- Seluruh menu memiliki ikon, judul, deskripsi, label semantik, ripple, dan target sentuh memadai.

## Pengujian lapangan yang masih diperlukan

Tidak ada kelompok pengguna atau perangkat Android fisik yang tersedia dalam sesi pengembangan ini. Karena itu, klaim feedback pengguna langsung belum dibuat. Sebelum rilis produksi, lakukan sesi dengan minimal lima petugas:

1. Gunakan satu perangkat kecil (≤ 5,5 inci), dua perangkat Android umum, dan satu tablet.
2. Minta peserta menemukan fitur Hama, membuat draf, membaca status offline, dan membuka notifikasi tanpa arahan.
3. Catat tingkat keberhasilan tugas, waktu penyelesaian, salah tekan, dan rating kemudahan 1–5.
4. Kriteria penerimaan: keberhasilan tugas ≥ 90%, salah tekan ≤ 1 per tugas, dan median kemudahan ≥ 4/5.
5. Ulangi dengan text scale Android 100%, 130%, dan 150%, tema terang/gelap, serta pencahayaan luar ruang.

## Risiko tersisa

- Perbedaan metrik font sistem antar-vendor Android perlu diperiksa pada perangkat sasaran.
- Review TalkBack end-to-end dan pengujian kamera/GPS memerlukan perangkat fisik.
- Semua halaman memperoleh tema global, tetapi halaman dengan layout khusus tetap perlu inspeksi visual regresi pada perangkat sasaran.
