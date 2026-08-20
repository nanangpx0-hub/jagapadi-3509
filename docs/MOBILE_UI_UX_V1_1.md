# Peningkatan UI/UX Mobile JAGAPADI v1.1

## Tujuan

Pembaruan ini membuat antarmuka petugas lebih mudah dipindai, nyaman dibaca di luar ruangan, konsisten, dan tetap efektif pada ponsel kecil maupun tablet.

## Keputusan desain

### Warna dan aksesibilitas

- Warna utama diubah menjadi hijau agrikultur `#176B3A` untuk memperkuat identitas JAGAPADI.
- Pasangan warna utama/putih dan surface/teks diuji terhadap rumus kontras WCAG 2.x.
- Warna status tidak menjadi satu-satunya pembeda; status tetap disertai ikon atau teks.
- Tema gelap mengikuti pengaturan sistem Android untuk kenyamanan pada pencahayaan rendah.

### Tipografi

- Font sistem Android digunakan agar selalu tersedia secara offline dan mengikuti preferensi aksesibilitas perangkat.
- Judul menggunakan bobot 700, subjudul 600, dan isi 400.
- Line-height isi 1,45–1,50 untuk meningkatkan keterbacaan paragraf dan label panjang.

### Spacing dan bentuk

- Token spacing menggunakan kelipatan grid 8dp: 8, 16, 24, 32, dan 48dp; 4/12dp dipakai sebagai interval antara.
- Radius standar 8, 12, dan 20dp.
- Semua tombol memiliki target sentuh minimum 48dp; tombol aksi utama setinggi 52dp.

### Navigasi dan beranda

- Beranda mengikuti pola referensi operasional: header hijau penuh, sapaan personal, kartu statistik mengambang, judul daftar dengan aksi sinkron, dan navigasi bawah permanen.
- Navigasi bawah berisi Beranda, Laporan, Sinkron, dan Profil; istilah disesuaikan dengan tugas petugas JAGAPADI.
- Menu beranda menggunakan kartu berikon, judul singkat, deskripsi tindakan, dan affordance panah.
- Tata letak berubah otomatis menjadi 1 kolom pada ponsel, 2 kolom pada tablet kecil, dan 3 kolom pada layar lebar.
- Lebar konten dibatasi 1120dp agar baris teks dan kartu tidak terlalu melebar.
- Header menampilkan identitas pengguna, peran, dan status offline secara langsung.
- Badge notifikasi memiliki label semantik untuk pembaca layar.

### Feedback interaksi

- Kartu menggunakan `InkWell` sehingga sentuhan memberikan ripple Material.
- Navigasi Android menggunakan transisi predictive-back Material.
- SnackBar mengambang, progress indicator, skeleton, refresh gesture, dan pesan kosong/error dipertahankan sebagai feedback sistem.

## Komponen sumber utama

- `mobile/lib/core/theme.dart`: token, warna, tipografi, tema komponen, breakpoint.
- `mobile/lib/app.dart`: theme terang/gelap dan pilihan theme sistem.
- `mobile/lib/features/home/screens/home_screen.dart`: beranda responsif dan menu fitur.
- `mobile/test/core/theme_accessibility_test.dart`: validasi kontras, breakpoint, dan ukuran sentuh.

## Panduan penggunaan lanjutan

Komponen baru wajib memakai `AppSpacing`, `AppRadius`, `Theme.of(context).textTheme`, dan `Theme.of(context).colorScheme`. Hindari nilai warna langsung untuk teks utama dan hindari target sentuh di bawah 48dp.
