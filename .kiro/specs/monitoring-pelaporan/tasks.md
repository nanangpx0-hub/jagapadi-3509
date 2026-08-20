# Implementation Tasks — Monitoring Pelaporan JAGAPADI

## Task 1: Migration & Konfigurasi Database

Buat dua migration baru untuk mendukung modul monitoring.

### Sub-tasks:
- [ ] 1.1 Buat `database/migrations/2026_08_07_create_evaluasi_petugas.php`
  - Buat tabel `evaluasi_petugas` sesuai schema di design.md
  - Sertakan rollback (`--rollback` flag) yang drop tabel
  - Jalankan migration untuk memverifikasi
- [ ] 1.2 Buat `database/migrations/2026_08_07_create_monitoring_config.php`
  - Buat tabel `monitoring_config` dengan seed default `target_laporan_bulanan = 10`
  - Sertakan rollback

**Requirement yang dicakup:** Req 7 (target_laporan_bulanan), Req 9 (evaluasi_petugas)

---

## Task 2: Model MonitoringReport

Buat `app/models/MonitoringReport.php` dengan semua method query agregat lintas tabel.

### Sub-tasks:
- [ ] 2.1 Implementasikan konstruktor dengan injeksi PDO dan CacheManager
- [ ] 2.2 Implementasikan `getStatsSummary(string $dateFrom, string $dateTo): array`
  - Query UNION ALL ketiga tabel laporan
  - Handle perbedaan nama kolom: `tanggal` vs `tanggal_kejadian`
  - Handle perbedaan format status: `draft` (hama/lainnya) vs `Draf` (irigasi)
  - Bungkus dengan CacheManager, key: `monitoring:stats:{dateFrom}:{dateTo}`
- [ ] 2.3 Implementasikan `getDailyTrend(string $dateFrom, string $dateTo): array`
  - GROUP BY DATE per kategori, isi 0 untuk hari tanpa laporan
  - Bungkus dengan CacheManager, key: `monitoring:charts:trend:{dateFrom}:{dateTo}`
- [ ] 2.4 Implementasikan `getPetugasRanking(string $dateFrom, string $dateTo, array $filters): array`
  - JOIN dengan `users`, filter role = 'petugas', aktif = 1
  - Hitung kategori dominan dengan tie-breaking alfabetis
  - Hitung avg waktu verifikasi hanya dari laporan `verified`
  - Bungkus dengan CacheManager
- [ ] 2.5 Implementasikan `getLaporanByPetugas(int $userId, string $dateFrom, string $dateTo): array`
  - UNION ALL ketiga tabel, JOIN kecamatan dan desa
  - Sertakan `kategori` sebagai kolom literal ('hama', 'irigasi', 'lainnya')
- [ ] 2.6 Implementasikan `getStatsPetugas(int $userId, string $dateFrom, string $dateTo): array`
- [ ] 2.7 Implementasikan `getExportData(...)` untuk keperluan ekspor tanpa LIMIT
- [ ] 2.8 Implementasikan helper `buildDateRange(string $preset): array`
  - Preset: `today`, `week`, `month`, `year`, `last30` (default)
- [ ] 2.9 Tulis unit test sederhana: jalankan `php -r` untuk memverifikasi query tidak error dengan data kosong

**Requirement yang dicakup:** Req 2, 3, 5, 6, 10


---

## Task 3: Model EvaluasiPetugas

Buat `app/models/EvaluasiPetugas.php` untuk kalkulasi skor dan CRUD catatan.

### Sub-tasks:
- [ ] 3.1 Implementasikan `hitungSkor(int $userId, int $bulan, int $tahun): array`
  - Query UNION ALL ketiga tabel untuk data laporan bulan tersebut
  - Kalkulasi 4 parameter sesuai formula Requirement 7
  - Handle redistribusi bobot saat akurasi = null (Req 7 kriteria 6)
  - Bulatkan semua nilai ke 1 desimal
- [ ] 3.2 Implementasikan `getRekapBulanan(int $bulan, int $tahun): array`
  - Iterasi semua user aktif dengan role `petugas`
  - Panggil `hitungSkor()` untuk setiap petugas
  - Tentukan `wilayah_utama` (kecamatan terbanyak, tie-break alfabetis)
  - Gabungkan dengan catatan dari tabel `evaluasi_petugas`
  - Urutkan berdasarkan `skor_total` DESC
- [ ] 3.3 Implementasikan `saveCatatan(int $userId, int $bulan, int $tahun, string $catatan, int $createdBy): bool`
  - Jika catatan kosong setelah trim: DELETE record
  - Jika ada: UPSERT ke `evaluasi_petugas`
  - Sanitasi `htmlspecialchars()` sebelum simpan
- [ ] 3.4 Implementasikan `getCatatan(int $userId, int $bulan, int $tahun): ?array`
  - Return array dengan catatan, nama admin, timestamp; atau null
- [ ] 3.5 Implementasikan `getTargetBulanan(): int` dan `saveTargetBulanan(int $target, int $updatedBy): bool`
  - Baca/tulis ke tabel `monitoring_config`

**Requirement yang dicakup:** Req 7, 8, 9

---

## Task 4: Controller Web MonitoringController

Buat `app/controllers/MonitoringController.php`.

### Sub-tasks:
- [ ] 4.1 Implementasikan konstruktor, checkAdminOrOperator(), checkAdminOnly(), parsePeriode(), validateFilterParams()
- [ ] 4.2 Implementasikan `index()` — render `monitoring/index.php` dengan data minimal (periode default, csrf token)
- [ ] 4.3 Implementasikan `petugas()` — render `monitoring/petugas.php`
- [ ] 4.4 Implementasikan `detailPetugas(int $id)` — validasi user_id, cek aktif + role petugas, render `monitoring/detail_petugas.php`
- [ ] 4.5 Implementasikan `evaluasi()` — render `monitoring/evaluasi.php`, load rekap bulanan server-side
- [ ] 4.6 Implementasikan `exportExcel()` — cek admin, build Excel via SimpleXLSXWriter, set headers, output file
- [ ] 4.7 Implementasikan `printPdf()` — render `monitoring/print_monitoring.php` dengan window.print()
- [ ] 4.8 Implementasikan `exportEvaluasi()` — cek admin, build Excel rekap skor, set headers, output file
- [ ] 4.9 Implementasikan `printEvaluasi()` — render `monitoring/print_evaluasi.php`
- [ ] 4.10 Implementasikan `saveCatatan()` — POST, admin only, CSRF, validasi max 1000 char, panggil model
- [ ] 4.11 Implementasikan `saveTarget()` — POST, admin only, CSRF, validasi integer 1-100

**Requirement yang dicakup:** Req 1, 4, 6, 8, 9, 12

---

## Task 5: API Controller MonitoringApiController

Buat `app/controllers/Api/MonitoringApiController.php`.

### Sub-tasks:
- [ ] 5.1 Implementasikan `stats()` — GET, validasi params, panggil `getStatsSummary()`, return JSON dengan `cache_time`
- [ ] 5.2 Implementasikan `charts()` — GET, return format Chart.js untuk bar, pie, dan trend berdasarkan `?type=`
- [ ] 5.3 Implementasikan `petugas()` — GET, validasi filter params, panggil `getPetugasRanking()`, return JSON array

**Requirement yang dicakup:** Req 2, 3, 5, 10, 12

---

## Task 6: Middleware admin_or_operator

Tambahkan middleware baru di Router.php tanpa mengubah logika routing yang ada.

### Sub-tasks:
- [ ] 6.1 Di `Router::applyMiddleware()`, tambahkan case `admin_or_operator`:
  - Cek session login (redirect ke login jika belum)
  - Cek role admin atau operator (redirect ke dashboard dengan error jika bukan)
- [ ] 6.2 Tambahkan semua route monitoring ke `Router::loadApiRoutes()` sesuai spesifikasi di design.md
- [ ] 6.3 Verifikasi: akses `/monitoring` sebagai petugas → redirect 403, sebagai admin → OK

**Requirement yang dicakup:** Req 1, 12


---

## Task 7: View — Dashboard Monitoring (index.php)

Buat `app/views/monitoring/index.php` dan partial views.

### Sub-tasks:
- [ ] 7.1 Buat `app/views/monitoring/_filter_bar.php` — komponen filter periode (preset buttons + custom datepicker)
  - Tombol: [Hari Ini] [7 Hari] [Bulan Ini] [Tahun Ini] [Kustom ▼]
  - Label timestamp cache di bawah filter bar
- [ ] 7.2 Buat `app/views/monitoring/_stat_cards.php` — 4 kartu Bootstrap AdminLTE (Total, Hama, Irigasi, Lainnya)
  - Skeleton loading animation saat data sedang dimuat
  - Pesan error jika fetch gagal
- [ ] 7.3 Buat `app/views/monitoring/index.php`:
  - Include `layouts/header.php`
  - Include `_filter_bar.php` dan `_stat_cards.php`
  - Canvas untuk 3 grafik Chart.js (bar, pie, garis tren)
  - Tombol Export Excel dan Cetak PDF (conditional: sembunyikan untuk operator)
  - JavaScript AJAX untuk fetch `/api/monitoring/stats` dan `/api/monitoring/charts`
  - Validasi client-side: date_from > date_to → alert, tidak fetch
  - Include `layouts/footer.php`

**Requirement yang dicakup:** Req 2, 3, 4, 10, 11

---

## Task 8: View — Peringkat Petugas (petugas.php)

Buat `app/views/monitoring/petugas.php`.

### Sub-tasks:
- [ ] 8.1 Filter bar: periode + kecamatan dropdown (load via AJAX dari wilayah API) + kategori select
- [ ] 8.2 Tabel peringkat dengan kolom: No, Nama (link ke detail), Total, Kategori Dominan, Avg Waktu Verifikasi
- [ ] 8.3 Empty state saat data kosong
- [ ] 8.4 JavaScript AJAX untuk fetch `/api/monitoring/petugas` dan update tabel

**Requirement yang dicakup:** Req 5, 10, 11

---

## Task 9: View — Detail Petugas (detail_petugas.php)

Buat `app/views/monitoring/detail_petugas.php`.

### Sub-tasks:
- [ ] 9.1 Header dengan nama petugas, link kembali ke petugas.php
- [ ] 9.2 4 kartu statistik ringkasan (total, verified, rejected, avg waktu)
- [ ] 9.3 Filter periode
- [ ] 9.4 Tabel riwayat laporan dengan kode laporan sebagai link ke halaman detail laporan masing-masing
- [ ] 9.5 Empty state untuk petugas tanpa laporan

**Requirement yang dicakup:** Req 6, 11

---

## Task 10: View — Evaluasi Petugas (evaluasi.php)

Buat `app/views/monitoring/evaluasi.php`.

### Sub-tasks:
- [ ] 10.1 Pilih bulan/tahun form + tombol [Lihat] + input target (admin only, dengan CSRF)
- [ ] 10.2 Tabel rekapitulasi skor dengan kolom sesuai Req 8
  - Akurasi "–" jika null
  - Skor total dengan warna (merah < 50, kuning 50-75, hijau > 75)
- [ ] 10.3 Per-baris: link/tombol accordion untuk expand detail breakdown skor + form catatan
  - Form textarea catatan (admin only, max 1000 karakter dengan counter)
  - Tombol Simpan Catatan dengan CSRF (admin only)
  - Tampilkan catatan tersimpan + nama admin + timestamp (semua role)
- [ ] 10.4 Tombol Unduh Excel (admin only) dan Cetak PDF
- [ ] 10.5 Empty state jika tidak ada data evaluasi

**Requirement yang dicakup:** Req 7, 8, 9, 11

---

## Task 11: View — Print Templates

Buat view khusus cetak tanpa sidebar AdminLTE.

### Sub-tasks:
- [ ] 11.1 Buat `app/views/monitoring/print_monitoring.php` — tabel data monitoring untuk cetak
  - CSS `@media print` untuk menyembunyikan elemen non-print
  - `<script>window.onload = function(){ window.print(); }</script>`
- [ ] 11.2 Buat `app/views/monitoring/print_evaluasi.php` — tabel rekap skor evaluasi untuk cetak
  - Header: judul, periode, tanggal cetak

**Requirement yang dicakup:** Req 4, 8

---

## Task 12: Invalidasi Cache di Controller yang Sudah Ada

Tambahkan invalidasi cache `monitoring:` di tiga controller laporan tanpa mengubah logika bisnis.

### Sub-tasks:
- [ ] 12.1 Di `LaporanLainnyaController`: tambahkan `CacheManager::getInstance()->clearPrefix('monitoring:')` di `submit()`, `verify()`, `reject()`, `archive()`
- [ ] 12.2 Di controller laporan hama: tambahkan di method yang mengubah status laporan
- [ ] 12.3 Di controller laporan irigasi: tambahkan di method yang mengubah status laporan
- [ ] 12.4 Verifikasi: setelah submit laporan → cache monitoring terinvalidasi → request berikutnya ambil data baru

**Requirement yang dicakup:** Req 10

---

## Task 13: Navigasi Menu

Tambahkan link menu "Monitoring Pelaporan" ke sidebar aplikasi.

### Sub-tasks:
- [ ] 13.1 Baca `app/views/layouts/sidebar.php` (atau file navbar yang relevan)
- [ ] 13.2 Tambahkan item menu dengan ikon `fas fa-chart-bar` di bawah atau di samping menu laporan
- [ ] 13.3 Kondisikan: hanya tampil untuk role `admin` dan `operator`
- [ ] 13.4 Tambahkan sub-menu: Dashboard, Aktivitas Petugas, Evaluasi Kinerja

**Requirement yang dicakup:** Req 1, 11

---

## Task 14: Pengujian Fungsionalitas

Verifikasi semua acceptance criteria utama.

### Sub-tasks:
- [ ] 14.1 Akses kontrol: login sebagai petugas → 403; login sebagai operator → tampil tanpa tombol admin; login sebagai admin → tampil lengkap
- [ ] 14.2 Filter periode: test semua preset + kustom, validasi tanggal terbalik
- [ ] 14.3 Statistik: buat laporan baru, submit → cek angka bertambah setelah cache TTL habis atau setelah invalidasi
- [ ] 14.4 Skor evaluasi: buat 10+ laporan dengan variasi (ada foto, ada koordinat, submit dalam 3 hari) → cek formula hitungan manual sesuai
- [ ] 14.5 Catatan evaluasi: simpan, edit, hapus (teks kosong), cek tampil di halaman operator (readonly)
- [ ] 14.6 Ekspor Excel: unduh file, buka di Excel, verifikasi header metadata dan data rows
- [ ] 14.7 Responsif: akses di mobile viewport (< 768px), verifikasi layout 1 kolom dan tabel scrollable
- [ ] 14.8 CSRF: kirim POST tanpa CSRF token → 403

**Requirement yang dicakup:** Semua (Req 1-12)

