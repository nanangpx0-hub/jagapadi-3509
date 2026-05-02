# Data Dictionary

Dokumen ini menjelaskan istilah data lokal JAGAPADI yang sering tidak dipahami oleh AI umum.

## Wilayah dan Kode BPS

JAGAPADI memakai kode wilayah BPS/MFD untuk master wilayah.

- Provinsi Jawa Timur: `35`.
- Kabupaten/kota: 4 digit, contoh Jember `3509`.
- Kecamatan: 7 digit, contoh Jenggawah `3509100`.
- Desa/kelurahan: 10 digit, contoh Jatimulyo `3509100008`.

Relasi kode:

- `LEFT(kode_kecamatan, 4)` harus sama dengan `kode_kabupaten`.
- `LEFT(kode_desa, 7)` harus sama dengan `kode_kecamatan`.

Tabel utama:

- `master_kabupaten.kode_kabupaten`
- `master_kecamatan.kode_kecamatan`
- `master_desa.kode_desa`

## MFD Jawa Timur 2025_1.2025

MFD adalah Master File Desa dari BPS. Data pembanding lokal ada di:

- `data/mfd/mfd_jawa_timur_2025_1.csv`

Kolom penting CSV:

- `periode`
- `kode_provinsi_bps`
- `nama_provinsi`
- `kode_kabupaten_bps`
- `nama_kabupaten`
- `kode_kecamatan_bps`
- `nama_kecamatan`
- `kode_desa_bps`
- `nama_desa_bps`
- `kode_dagri`
- `nama_desa_dagri`
- `sumber`
- `scraped_at`

Periode yang dipakai:

- `2025_1.2025`

Sumber:

- SIG BPS Kode Relasi BPS-Kemendagri.

## BPS vs Dagri

Jangan mencampur kode BPS dengan kode Dagri/Kemendagri.

- `kode_desa_bps`: 10 digit tanpa titik, dipakai sebagai kode master desa JAGAPADI.
- `kode_dagri`: format bertitik seperti `35.09.xx.xxxx`, namespace berbeda.
- Nama BPS dan Dagri bisa berbeda tipis; jangan update massal nama tanpa review.

## Temuan MFD Jember

Kode Kabupaten Jember:

- `3509`

Hasil compare MFD 2025_1.2025:

- MFD Jember: 31 kecamatan, 248 desa/kelurahan.
- JAGAPADI sebelum maintenance: 31 kecamatan, 246 desa aktif.
- Desa Jember yang missing:
  - `3509100008` - `JATIMULYO`, parent `3509100` `JENGGAWAH`
  - `3509730008` - `BANJAR SENGON`, parent `3509730` `PATRANG`

Validasi relasi parent terakhir dari compare:

- Kecamatan salah kabupaten: empty set.
- Desa salah kecamatan: empty set.

## Status Laporan Hama

Status dasar yang digunakan di `laporan_hama`:

- `Draf`
- `Submitted`
- `Diverifikasi`
- `Ditolak`

Ada migration `database/migrations/2026_05_01_add_diarsipkan_status_to_laporan_hama.php` untuk status arsip. Cek environment sebelum mengasumsikan status `Diarsipkan` tersedia.

## Role Aplikasi

Role yang muncul di controller dan routing:

- `admin`
- `operator`
- `statistisi`
- `petugas`
- role/API eksternal melalui middleware token

Cek akses di controller, `app/core/Router.php`, dan session sebelum membuka data atau action baru.

## File Data yang Jangan Di-commit

File data lokal dan hasil generate biasanya tidak boleh ikut commit kecuali task eksplisit menyetujui sumber dan alasan commit:

- `data/*.csv`
- `data/mfd/*.csv`
- `data/mfd/cache/`
- dump SQL dan backup database
- `storage/`, `logs/`, `public/uploads/`

Jika file data harus direview, jelaskan sumber, alasan, dan dampaknya di PR.
