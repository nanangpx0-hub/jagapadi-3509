# Pilot Internal JAGAPADI Mobile v1.1.1+4

## Cakupan

Paket ini adalah build debug untuk uji internal 3–5 petugas. APK terhubung ke:

`http://192.168.10.5:8080/api/v1`

HP dan komputer server harus berada pada jaringan LAN/Wi-Fi yang sama. Paket ini bukan APK produksi dan tidak boleh dibagikan di luar kelompok pilot.

## Prasyarat

- Android 8, 10, atau 13 (minimum aplikasi: Android 7/API 24).
- Server lokal aktif dan endpoint `/api/v1/health` merespons HTTP 200.
- Akun petugas uji yang tidak memakai data produksi sensitif.
- Izin instalasi dari sumber tidak dikenal diaktifkan hanya selama instalasi.

## Instalasi

1. Salin `mobile/dist/jagapadi-1.1.1+4-pilot-lan-debug.apk` ke HP.
2. Cocokkan SHA-256 APK dengan nilai di `mobile/dist/SHA256SUMS-pilot.txt`.
3. Instal APK, buka aplikasi, lalu izinkan kamera, lokasi, dan notifikasi saat diminta.
4. Setelah pilot selesai, nonaktifkan kembali izin instalasi dari sumber tidak dikenal.

## Smoke test wajib per perangkat

Catat `Lulus`, `Gagal`, atau `Tidak diuji` untuk setiap skenario.

- [ ] Aplikasi dapat dipasang dan dibuka tanpa crash.
- [ ] Login online berhasil dan halaman utama tampil.
- [ ] Login salah menampilkan pesan yang mudah dipahami.
- [ ] Daftar laporan dapat dimuat dan di-refresh.
- [ ] Buat draf Hama/OPT dengan wilayah, GPS, dan foto.
- [ ] Tutup lalu buka aplikasi; draf tetap tersedia.
- [ ] Matikan jaringan; edit/simpan draf offline tanpa kehilangan data.
- [ ] Aktifkan jaringan; draf tersinkron tanpa duplikasi.
- [ ] Kirim laporan; nomor laporan baru muncul setelah submit, bukan saat draf.
- [ ] Buat dan kirim laporan Irigasi.
- [ ] Status `Submitted`, `Diverifikasi`, dan `Ditolak` tampil benar.
- [ ] Laporan milik petugas lain tidak terlihat.
- [ ] Notifikasi dapat dibuka menuju detail yang sesuai.
- [ ] Tombol, form, dan dialog tidak overflow pada orientasi portrait.
- [ ] Logout berhasil dan token lama tidak dapat dipakai kembali.

## Matriks perangkat

| Petugas | Perangkat | Android | Resolusi | Hasil | Catatan |
|---|---|---:|---|---|---|
| 1 |  | 8 |  |  |  |
| 2 |  | 10 |  |  |  |
| 3 |  | 13 |  |  |  |
| 4 |  |  |  |  |  |
| 5 |  |  |  |  |  |

## Feedback pengguna

Untuk setiap petugas, catat:

1. Tugas apa yang paling sulit diselesaikan?
2. Istilah atau tombol apa yang membingungkan?
3. Apakah form nyaman digunakan di lapangan?
4. Apakah penyimpanan offline dan sinkronisasi dapat dipercaya?
5. Masalah terberat, langkah reproduksi, screenshot, perangkat, dan versi Android.

## Kriteria keputusan

Pilot dinyatakan lulus jika tidak ada crash, kehilangan/duplikasi data, pelanggaran kepemilikan laporan, kegagalan login/sinkronisasi/submit, atau masalah keamanan kategori tinggi. Masalah kosmetik boleh masuk backlog bila tidak menghambat pekerjaan petugas.

