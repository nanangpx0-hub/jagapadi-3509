# Dokumentasi Fitur Laporan Hama JAGAPADI

Dokumen ini menjadi rujukan utama untuk memahami fitur Laporan Hama di JAGAPADI, baik untuk developer, reviewer, maupun AI agent yang akan membantu pengembangan berikutnya.

## 1. Ringkasan Fitur

Fitur Laporan Hama digunakan untuk mencatat kejadian serangan hama atau penyakit/OPT pada wilayah pertanian. Data laporan berisi pelapor, OPT, tanggal, wilayah, alamat, koordinat GPS, tingkat keparahan, populasi/intensitas, luas serangan, catatan, foto, status, dan riwayat perubahan status.

Tujuan fitur ini dalam JAGAPADI:

- Menjadi kanal input kejadian OPT dari petugas/user.
- Menyediakan data operasional untuk daftar laporan, detail laporan, dashboard, grafik, dan peta sebaran.
- Mendukung analisis risiko dan narasi data pertanian melalui data serangan hama.
- Menjaga histori data lama tanpa menghapus status historis.

Pengguna utama:

- `petugas`: membuat dan mengelola laporan miliknya.
- `operator`: melihat, mengedit, dan mengarsipkan laporan.
- `admin`: akses pengelolaan penuh, termasuk bulk delete.
- `viewer`: melihat data/dashboard, tanpa fungsi ubah data pada fitur laporan.

Perubahan penting terbaru: fitur ini tidak lagi memakai approval operator/admin. Laporan baru langsung disimpan sebagai `Submitted` dan langsung menjadi laporan aktif. Operator/admin tidak lagi approve/reject, tetapi tetap bisa mengelola data melalui lihat, edit, arsipkan, atau hapus jika ada kesalahan sesuai hak akses.

## 2. Alur Bisnis Terbaru

Alur baru:

1. Petugas/user login.
2. Petugas, operator, atau admin membuka halaman tambah laporan.
3. Pengguna mengisi data laporan hama dan mengirim form.
4. Controller menyimpan laporan dengan status `Submitted`.
5. Laporan langsung aktif dan muncul pada daftar laporan, dashboard, grafik, dan peta jika memenuhi filter data.
6. Operator/admin dapat melihat detail, mengedit, mengarsipkan, atau menghapus sesuai aturan.
7. Tidak ada proses menunggu verifikasi.
8. Tidak ada approve/reject pada alur aktif baru.

Perbandingan alur:

| Alur lama | Alur baru |
|---|---|
| Input -> Menunggu Verifikasi -> Diverifikasi/Ditolak | Input -> Langsung Aktif -> Dikelola jika perlu |

Catatan kompatibilitas: data lama berstatus `Diverifikasi` dan `Ditolak` tetap harus aman. `Diverifikasi` masih dihitung sebagai data aktif historis, sedangkan `Ditolak` tetap menjadi status historis.

## 3. Role dan Hak Akses

| Role | Fungsi utama | Hak akses terkait laporan hama |
|---|---|---|
| `admin` | Administrator sistem | Melihat semua laporan, membuat laporan, membuat laporan atas nama user lain, edit semua laporan, arsipkan, hapus, bulk delete. |
| `operator` | Operator pengelola data | Melihat semua laporan, membuat laporan, edit semua laporan, arsipkan. Route API archive juga membatasi ke admin/operator. |
| `viewer` | Pembaca data | Melihat dashboard/data. Tidak diberi akses create/edit/archive/delete pada controller laporan. |
| `petugas` | Petugas lapangan/pelapor | Membuat laporan, melihat laporan miliknya sendiri, edit laporan miliknya sendiri, delete melalui controller jika milik sendiri. UI tabel hanya menampilkan delete petugas untuk `Draf` atau `Ditolak`; controller delete saat ini hanya mengecek ownership. Perlu verifikasi manual untuk konsistensi aturan delete. |

Akun testing dari `users-testing.md`:

| Username | Role | Fungsi testing |
|---|---|---|
| `admin_test` | `admin` | Verifikasi akses penuh, create atas nama user, edit, arsipkan, hapus, bulk delete, dashboard semua data. |
| `operator_test` | `operator` | Verifikasi create, edit semua laporan, arsipkan, tidak bulk delete admin-only. |
| `viewer_test` | `viewer` | Verifikasi hanya lihat data/dashboard dan tidak bisa mengubah laporan. |
| `petugas_test` | `petugas` | Verifikasi create laporan sendiri, laporan langsung aktif, daftar hanya laporan sendiri, edit laporan sendiri. |

Jangan menaruh password production di dokumentasi fitur. Detail seed/testing lokal tetap merujuk ke `users-testing.md`.

## 4. Status Laporan

| Status | Arti | Pemakaian saat ini |
|---|---|---|
| `Draf` | Laporan belum final jika alur draf masih digunakan. | Tidak menjadi status default create saat ini karena create web/API menyetel `Submitted`. Masih didukung untuk kompatibilitas dan UI. |
| `Submitted` | Laporan baru/laporan aktif. | Status utama untuk laporan baru. Langsung masuk dashboard/rekap/peta sebagai data aktif. |
| `Diverifikasi` | Data lama/historis yang dahulu sudah diverifikasi. | Tetap aman dan dihitung sebagai aktif historis. Jangan dipakai sebagai tujuan approval baru. |
| `Ditolak` | Data lama/historis yang dahulu ditolak. | Tetap disimpan untuk histori. Tidak termasuk rekap aktif. |
| `Diarsipkan` | Laporan tidak aktif/diarsipkan. | Dipakai untuk mengeluarkan laporan dari rekap aktif tanpa menghapus data. |

Definisi laporan aktif untuk dashboard/rekap umumnya:

```text
Submitted + Diverifikasi
```

`Diarsipkan` dan `Ditolak` tidak termasuk rekap aktif. `Draf` tidak dihitung sebagai laporan aktif.

## 5. Database dan Tabel Terkait

Tabel transaksi dan pendukung fitur:

| Tabel | Fungsi | Catatan |
|---|---|---|
| `laporan_hama` | Tabel utama laporan hama. | Berisi field pelapor, OPT, tanggal, lokasi, koordinat, tingkat keparahan, populasi, luas serangan, foto, status, catatan, field legacy approval, dan field wilayah. |
| `laporan_status_history` | Riwayat perubahan status laporan. | Ditulis saat create (`null -> Submitted`), edit yang mengaktifkan ulang, dan archive. Digunakan pada detail laporan. |
| `honor_pelaporan` | Data honor eksternal yang mereferensikan `laporan_hama_id`. | Ada FK ke `laporan_hama`. Harus ikut dipertimbangkan saat maintenance transaksi laporan. |
| `laporan_hama_tags` | Relasi laporan hama dengan `tags`. | Ada jika fitur tag dipakai. Create laporan menyimpan tag dari input form jika tersedia. |
| `activity_log` | Log aktivitas global. | Dipakai untuk aktivitas terkait `laporan_hama`; maintenance hanya menghapus baris dengan `table_name = 'laporan_hama'`. |
| `notifications` | Notifikasi aplikasi. | Digunakan untuk alert ETL dan legacy notifikasi approval. Berdasarkan script maintenance, tidak ada FK langsung ke `laporan_hama`, sehingga tidak dibersihkan otomatis. |

Tabel master dan data lintas modul yang tidak boleh dihapus saat maintenance laporan hama:

- `users`
- `master_opt`
- `tags`
- `master_kabupaten`
- `master_kecamatan`
- `master_desa`
- tabel padi/BPS seperti `data_pertanian_bps` dan `produksi_gabah`
- tabel irigasi seperti `data_irigasi` dan `laporan_irigasi`
- tabel gabah/beras dan harga komoditas
- setting aplikasi dan tabel master lain

Migration terkait:

| File | Tujuan |
|---|---|
| `database/migrations/2026_05_01_add_diarsipkan_status_to_laporan_hama.php` | Menambahkan nilai enum `Diarsipkan` ke kolom `laporan_hama.status`. Migration idempotent: jika status sudah ada, tidak mengubah apa pun. Rollback mengubah status `Diarsipkan` menjadi `Submitted`, lalu mengembalikan enum lama. |

Catatan penting: dump `jagapadi.sql` yang dibaca masih menunjukkan enum lama tanpa `Diarsipkan`. Sumber kebenaran untuk perubahan terbaru adalah migration di atas dan kode aplikasi yang sudah memakai `Diarsipkan`.

## 6. File dan Tanggung Jawab Teknis

| File | Fungsi | Catatan penting |
|---|---|---|
| `app/controllers/LaporanController.php` | Controller web laporan hama. | `create()` menyimpan status `Submitted`. `edit()` mempertahankan status aktif atau mengubah `Draf`/`Ditolak` menjadi `Submitted`. `archive()` set status `Diarsipkan`. `verify()` sudah mengembalikan HTTP 410 dan redirect/error. |
| `app/controllers/Api/LaporanHamaController.php` | API session-backed laporan hama. | `store()` menyetel status `Submitted`. `archive()` hanya admin/operator. `destroy()` delete API dibatasi admin, kecuali logika petugas ownership di kondisi tertentu. |
| `app/controllers/Api/DashboardController.php` | API statistik dashboard umum. | `pending_reports`/`pending_verifications` sudah 0. `verified_reports` menghitung `Submitted + Diverifikasi`. |
| `app/controllers/Api/DashboardMapApiController.php` | API peta dashboard. | Endpoint hama memanggil `DashboardDataAggregator::getHamaMapData()`, lalu mengubah hasil ke GeoJSON. |
| `app/controllers/Api/DashboardChartsApiController.php` | API grafik dashboard. | Endpoint `hama()` memakai `DashboardDataAggregator::getHamaSummary()`. |
| `app/controllers/DashboardController.php` | Controller web dashboard. | Dashboard utama, map, charts memakai model `LaporanHama` dan filter user untuk petugas. |
| `app/models/LaporanHama.php` | Model utama laporan hama. | Agregasi dashboard/peta/top OPT umumnya memakai `status IN ('Submitted','Diverifikasi')`. Method `verify()` masih ada untuk kompatibilitas kode legacy, tetapi approval aktif sudah dinonaktifkan di controller. |
| `app/services/DashboardDataAggregator.php` | Service agregasi dashboard dan peta. | Hama summary/map/top OPT memakai `Submitted + Diverifikasi`. Cache dashboard ada di `storage/cache/dashboard`. |
| `app/services/DataStoryService.php` | Service analisis narasi data. | Memakai data hama sebagai lagging indicator, tetapi ada indikasi nama kolom lama (`tanggal_laporan`, `intensitas_serangan`, `jenis_hama`) yang perlu diverifikasi terhadap schema `laporan_hama` saat ini. |
| `app/views/laporan/create.php` | UI tambah laporan. | Form input OPT, wilayah, alamat, koordinat, keparahan, populasi, luas, catatan, foto, tag/autosave/offline. Tidak ada tombol approve/reject. |
| `app/views/laporan/edit.php` | UI edit laporan. | Status dipertahankan via hidden input. Edit status legacy `Draf`/`Ditolak` akan diaktifkan ke `Submitted` oleh controller. |
| `app/views/laporan/index.php` | UI daftar laporan. | Filter dan tabel AJAX. Badge status menampilkan `Submitted`/`Diverifikasi` sebagai `Aktif`. Ada tombol lihat/edit/archive/delete sesuai role. |
| `app/views/laporan/table_content.php` | Partial tabel laporan. | Menampilkan status aktif untuk `Submitted`/`Diverifikasi`; menampilkan archive untuk admin/operator. |
| `app/views/laporan/view.php` | UI detail laporan. | Menampilkan detail, status sebagai `Aktif` untuk `Submitted`/`Diverifikasi`, peta titik, foto, riwayat status. Tidak ada tombol approve/reject. |
| `app/views/dashboard/index.php` | Dashboard utama. | Label kartu: `Baru Masuk` dan `Laporan Aktif`. Recent reports membedakan `Submitted` sebagai `Baru Masuk`, `Diverifikasi` sebagai `Lama (Diverifikasi)`. |
| `app/views/dashboard/map.php` | Dashboard peta. | Default filter status adalah `Semua Aktif`. UI filter punya `Baru Masuk` dan `Lama (Diverifikasi)`. |
| `app/views/dashboard/charts.php` | Dashboard grafik. | Tab `Sebaran Hama` mengambil data dari API charts hama. Label `Terverifikasi` masih ada di kartu chart dan perlu dipertimbangkan untuk penyelarasan istilah. |
| `app/core/Router.php` | Router API. | Mendefinisikan route API laporan hama, dashboard, peta, charts, dan AJAX `/laporan/fetch`. |
| `database/migrations/2026_05_01_add_diarsipkan_status_to_laporan_hama.php` | Migration status arsip. | Wajib dijalankan sebelum fitur archive dipakai pada database yang belum punya enum `Diarsipkan`. |
| `database/maintenance/clear_laporan_hama.sql` | Script maintenance lokal. | Mengosongkan transaksi laporan hama saja. Wajib backup sebelum eksekusi. |
| `users-testing.md` | Dokumentasi akun testing. | Berisi akun satu role satu user untuk validasi hak akses. |

## 7. Route dan Endpoint

Route web reguler ditangani oleh `index.php` dengan pola:

```text
/{controller}/{method}/{params...}
```

Route utama web laporan:

| Route | Method | Handler | Fungsi |
|---|---|---|---|
| `/laporan` | GET | `LaporanController@index` | Daftar laporan. Petugas hanya melihat laporan sendiri; admin/operator/viewer melihat sesuai controller/view. |
| `/laporan/create` | GET/POST | `LaporanController@create` | Form dan proses tambah laporan. Role: admin/operator/petugas. Status baru: `Submitted`. |
| `/laporan/edit/{id}` | GET/POST | `LaporanController@edit` | Form dan proses edit laporan. Role: admin/operator/petugas pemilik. |
| `/laporan/detail/{id}` | GET | `LaporanController@detail` | Detail laporan dan riwayat status. |
| `/laporan/archive/{id}` | POST | `LaporanController@archive` | Arsipkan laporan. Role: admin/operator. |
| `/laporan/delete/{id}` | POST/DELETE | `LaporanController@delete` | Hapus laporan. Role: admin/operator/petugas, dengan petugas dibatasi ke laporan sendiri. |
| `/laporan/bulkDelete` | POST | `LaporanController@bulkDelete` | Bulk delete. Role: admin. |
| `/laporan/fetch` | GET | `LaporanController@fetch` | AJAX pagination/search/sort/filter status untuk daftar laporan. |
| `/laporan/verify/{id}` | POST | `LaporanController@verify` | Legacy approval route. Saat ini mengembalikan HTTP 410 dan tidak boleh diaktifkan kembali. |

Route API laporan hama di `app/core/Router.php`:

| Endpoint | Method | Handler | Catatan |
|---|---|---|---|
| `/api/laporan-hama` | GET | `Api\LaporanHamaController@index` | List API dengan pagination dan filter. Petugas otomatis difilter ke `user_id` sendiri. |
| `/api/laporan-hama/{id}` | GET | `Api\LaporanHamaController@show` | Detail API. Petugas hanya bisa akses laporan sendiri. |
| `/api/laporan-hama` | POST | `Api\LaporanHamaController@store` | Create API. Status default `Submitted`. |
| `/api/laporan-hama/{id}` | PUT | `Api\LaporanHamaController@update` | Update API. |
| `/api/laporan-hama/{id}/archive` | POST | `Api\LaporanHamaController@archive` | Archive API. Role admin/operator. |
| `/api/laporan-hama/{id}` | DELETE | `Api\LaporanHamaController@destroy` | Delete API. Middleware route admin-only di Router, dan controller juga punya pengecekan tambahan. |

Route API dashboard/peta/grafik:

| Endpoint | Method | Fungsi |
|---|---|---|
| `/api/dashboard/stats` | GET | Statistik dashboard. |
| `/api/dashboard/charts` | GET | Data chart dashboard lama/umum. |
| `/api/dashboard/activities` | GET | Aktivitas terbaru. |
| `/api/dashboard/alerts` | GET | Alert dashboard. |
| `/api/dashboard/map/layers` | GET | Daftar layer peta. |
| `/api/dashboard/map/hama` | GET | Titik hama GeoJSON. |
| `/api/dashboard/map/hamaSummary` | GET | Ringkasan hama per kecamatan. |
| `/api/dashboard/map/all` | GET | Data semua layer peta. |
| `/api/dashboard/charts/hama` | GET | Data grafik sebaran hama. |
| `/api/dashboard/charts/summary` | GET | Ringkasan grafik dashboard. |
| `/api/dashboard/charts/export` | GET | Export data grafik. |

Catatan route approval lama: jangan mengaktifkan kembali approve/reject. Jika menemukan route, tombol, atau JS yang memanggil verify/reject, perlakukan sebagai legacy dan hapus/disable sesuai requirement.

## 8. UI/UX Fitur Laporan Hama

Halaman daftar laporan:

- Menampilkan tabel laporan dengan foto, tanggal, OPT, lokasi, keparahan, populasi, status, pelapor, tanggal dibuat, dan aksi.
- AJAX pagination/search/sort memakai `/laporan/fetch`.
- Badge status menyatukan `Submitted` dan `Diverifikasi` sebagai `Aktif`.
- Admin melihat checkbox dan bulk delete.
- Admin/operator melihat tombol archive untuk laporan yang belum `Diarsipkan`.
- Tidak ada tombol approve/reject pada UI daftar.

Halaman tambah laporan:

- Role admin/operator/petugas bisa membuat laporan.
- Admin dapat membuat laporan atas nama user aktif lain.
- Input utama: tanggal, OPT, kabupaten/kecamatan/desa, alamat lengkap, koordinat, tingkat keparahan, populasi/intensitas, luas serangan, catatan, foto, dan tags.
- Koordinat bisa diisi manual, dipilih dari peta, atau dari lokasi browser.
- Create controller menyimpan status `Submitted`.

Halaman edit laporan:

- Admin/operator dapat edit semua laporan.
- Petugas dapat edit laporan miliknya.
- Status disimpan sebagai hidden input, tetapi controller mengubah `Draf`/`Ditolak` menjadi `Submitted` saat diedit agar aktif kembali.
- Upload foto baru akan mengganti foto lama jika file lama ditemukan.

Halaman detail laporan:

- Menampilkan detail laporan, status, pelapor, catatan, foto, peta titik, dan riwayat status.
- Admin/operator dapat edit dan arsipkan.
- Tidak ada action approve/reject.
- Data lama `Diverifikasi`/`Ditolak` tetap dapat menampilkan informasi verifikator/catatan verifikasi sebagai histori.

Dashboard utama:

- Kartu `Baru Masuk` merepresentasikan laporan `Submitted`.
- Kartu `Laporan Aktif` merepresentasikan laporan aktif (`Submitted + Diverifikasi`).
- Recent reports memakai label `Baru Masuk` untuk `Submitted` dan `Lama (Diverifikasi)` untuk `Diverifikasi`.

Dashboard peta:

- Filter status default adalah `Semua Aktif`.
- Opsi UI yang tersedia: `Semua Aktif`, `Baru Masuk`, `Lama (Diverifikasi)`.
- Perlu verifikasi: UI mengirim parameter `status`, tetapi `DashboardMapApiController` dan `DashboardDataAggregator::getHamaMapData()` yang dibaca hanya memakai `year` dan selalu mengambil `Submitted + Diverifikasi`.

Dashboard charts:

- Tab `Sebaran Hama` mengambil endpoint `/api/dashboard/charts/hama`.
- Data hama aktif pada aggregator memakai `Submitted + Diverifikasi`.
- Perlu penyesuaian istilah pada UI charts jika masih menampilkan label `Terverifikasi` sebagai label utama; istilah yang disarankan adalah `Laporan Aktif`.

Istilah UI yang harus digunakan:

- `Baru Masuk`
- `Laporan Aktif`
- `Semua Aktif`
- `Lama (Diverifikasi)`
- `Diarsipkan`

Istilah yang harus dihindari untuk alur baru:

- `Pending Verifikasi`
- `Menunggu Persetujuan`
- `Approve`
- `Reject`
- `Terverifikasi` sebagai label utama laporan aktif

`Diverifikasi` dan `Ditolak` boleh tetap muncul untuk data historis, tetapi jangan menjadi alur aktif baru.

## 9. Dashboard dan Peta

Laporan hama masuk dashboard segera setelah tersimpan sebagai `Submitted`.

Sumber data dashboard:

- `DashboardController@index` mengambil statistik, top pests, monthly stats, dan recent reports dari model `LaporanHama`.
- `LaporanHama::getDashboardStats()` menghitung `terverifikasi` sebagai `Submitted + Diverifikasi`, dan `pending_verifikasi` bernilai 0.
- `LaporanHama::getTopPests()`, `getSeverityDistribution()`, `getAreaStatsByMonth()`, `getMapData()`, `getTopKecamatan()`, `getCriticalReports()` memakai filter aktif `status IN ('Submitted','Diverifikasi')`.
- `DashboardDataAggregator` juga memakai `Submitted + Diverifikasi` untuk hama summary, top OPT, by kecamatan, dan map data.

Default peta harus menampilkan `Semua Aktif`, bukan hanya `Diverifikasi`.

Filter yang disarankan:

- `Semua Aktif`: `Submitted + Diverifikasi`
- `Baru Masuk`: `Submitted`
- `Lama (Diverifikasi)`: `Diverifikasi`
- `Diarsipkan`: opsional jika ingin mode audit/administrasi, tidak untuk default rekap aktif

## 10. Maintenance Database

Script maintenance yang ditemukan:

```text
database/maintenance/clear_laporan_hama.sql
```

Tujuan script:

- Mengosongkan data transaksi fitur laporan hama saja.
- Menghapus isi `laporan_hama`, `laporan_status_history`, `laporan_hama_tags`, dan `honor_pelaporan` yang terkait laporan hama.
- Menghapus `activity_log` hanya untuk baris dengan `table_name = 'laporan_hama'`.
- Reset auto increment pada tabel transaksi terkait.

Script tidak menghapus:

- `users`
- `master_opt`
- `tags`
- `master_kabupaten`
- `master_kecamatan`
- `master_desa`
- data padi/BPS
- data irigasi
- data gabah/beras
- setting aplikasi
- file foto di filesystem, misalnya `public/uploads/laporan`
- `notifications`, karena tidak ada FK langsung ke `laporan_hama`

Aturan wajib sebelum menjalankan script:

- Backup database terlebih dahulu.
- Review ulang target database.
- Jangan jalankan di production tanpa approval eksplisit.
- Sadari bahwa file foto bisa menjadi orphan karena script tidak menghapus file upload.

## 11. Checklist Testing Manual

- [ ] Pastikan berada di branch fitur/dokumentasi, bukan `main`.
- [ ] Login sebagai `admin_test`.
- [ ] Verifikasi admin dapat melihat semua laporan.
- [ ] Verifikasi admin dapat membuat laporan baru.
- [ ] Verifikasi laporan baru admin langsung berstatus `Submitted`/aktif.
- [ ] Verifikasi admin dapat edit laporan.
- [ ] Verifikasi admin dapat arsipkan laporan.
- [ ] Verifikasi admin dapat hapus/bulk delete sesuai kebutuhan testing lokal.
- [ ] Login sebagai `operator_test`.
- [ ] Verifikasi operator dapat melihat semua laporan.
- [ ] Verifikasi operator dapat membuat laporan baru.
- [ ] Verifikasi laporan baru operator langsung aktif.
- [ ] Verifikasi operator dapat edit dan arsipkan.
- [ ] Verifikasi operator tidak memiliki bulk delete admin-only.
- [ ] Login sebagai `viewer_test`.
- [ ] Verifikasi viewer dapat melihat dashboard/daftar sesuai UI yang tersedia.
- [ ] Verifikasi viewer tidak bisa membuat laporan.
- [ ] Verifikasi viewer tidak bisa edit/archive/delete.
- [ ] Login sebagai `petugas_test`.
- [ ] Petugas membuat laporan baru.
- [ ] Pastikan laporan langsung aktif tanpa approve/reject.
- [ ] Pastikan laporan muncul di daftar laporan petugas.
- [ ] Pastikan petugas hanya melihat laporan miliknya.
- [ ] Pastikan dashboard menampilkan laporan baru.
- [ ] Pastikan peta menampilkan laporan baru jika koordinat valid dan tahun filter sesuai.
- [ ] Pastikan tidak ada tombol approve/reject di daftar/detail.
- [ ] Pastikan route `/laporan/verify/{id}` mengembalikan 410/menolak alur approval.
- [ ] Pastikan data lama `Diverifikasi` tetap tampil dan tetap dihitung aktif.
- [ ] Pastikan data lama `Ditolak` tetap aman dan tidak otomatis terhapus.
- [ ] Arsipkan satu laporan testing, lalu pastikan status menjadi `Diarsipkan`.
- [ ] Pastikan data `Diarsipkan` tidak masuk rekap aktif/dashboard/peta default.
- [ ] Pastikan riwayat status tercatat di detail laporan.
- [ ] Pastikan `activity_log`/audit trail terkait tetap sesuai kebutuhan jika ada perubahan data.

## 12. Risiko dan Catatan Teknis

- Karena laporan langsung aktif tanpa approval, data salah bisa langsung tampil pada dashboard dan peta.
- Mitigasi dilakukan melalui edit, arsipkan, hapus sesuai role, riwayat status, `activity_log`, dan pembatasan hak akses.
- File foto bisa menjadi orphan jika data dihapus dari database tanpa menghapus file di filesystem.
- Status lama `Diverifikasi` dan `Ditolak` harus tetap dijaga untuk kompatibilitas data lama.
- Jangan menghapus kolom legacy approval seperti `verified_by`, `verified_at`, dan `catatan_verifikasi` tanpa analisis menyeluruh karena detail laporan, histori, dan data lama masih dapat bergantung pada field tersebut.
- `LaporanHama::verify()` masih ada di model untuk kode legacy. Controller web `verify()` sudah menghentikan alur dengan HTTP 410. Jangan memakai method model ini untuk alur baru.
- `DashboardDataAggregator` memakai cache. Setelah perubahan laporan, controller memanggil `clearDashboardCache()` pada create/edit/archive/delete; jika ada jalur perubahan baru, pastikan cache dashboard ikut dibersihkan.
- Perlu verifikasi: `DataStoryService::getHamaLag()` terlihat memakai nama kolom lama (`tanggal_laporan`, `intensitas_serangan`, `jenis_hama`) yang tidak terlihat pada fillable/model `laporan_hama` saat ini. Jangan ubah tanpa review schema dan test.
- Perlu verifikasi: `app/views/dashboard/map.php` punya filter status di UI, tetapi API/agregator yang dibaca tidak menerapkan parameter status. Default `Semua Aktif` sudah benar, tetapi filter spesifik perlu diuji manual.
- Perlu verifikasi: `LaporanHama::getMonthlyTrends()` dan `getAreaStatistics()` tidak terlihat membatasi status aktif pada potongan kode yang dibaca, berbeda dengan beberapa method dashboard lain. Jika dipakai untuk rekap aktif, perlu review lanjutan.

## 13. Instruksi untuk AI Agent

- Jangan mengembalikan alur approval.
- Jangan menambahkan tombol approve/reject.
- Jangan menjadikan `Diverifikasi` sebagai filter default.
- Jangan menghapus data lama tanpa backup.
- Jangan mengubah tabel master.
- Jangan menyentuh modul irigasi/gabah/beras/padi jika tugas hanya laporan hama.
- Jangan commit file runtime, backup SQL, storage upload, atau `composer.lock` jika tidak diperlukan.
- Selalu gunakan branch baru.
- Semua perubahan harus melalui PR dan CI.
- Jika mengubah database, buat migration/script yang aman dan reversible.
- Jika menemukan status `Diverifikasi`/`Ditolak`, perlakukan sebagai histori/kompatibilitas, bukan alur baru.
- Jika menemukan kode approval legacy, dokumentasikan atau nonaktifkan sesuai requirement, jangan diaktifkan kembali.
- Jika mengubah dashboard/peta, pastikan default adalah `Semua Aktif` (`Submitted + Diverifikasi`).
- Jika mengubah data destructive/maintenance, wajib minta backup dan review.

## 14. Riwayat Perubahan Penting

| Perubahan | Dampak |
|---|---|
| Laporan hama tanpa approval | Laporan baru langsung berstatus `Submitted` dan aktif. |
| Dashboard/peta memakai Semua Aktif | Data aktif mencakup `Submitted + Diverifikasi`, bukan hanya `Diverifikasi`. |
| Label dashboard diganti | `Pending Verifikasi`/`Terverifikasi` diganti menjadi `Baru Masuk`/`Laporan Aktif` pada dashboard utama. |
| Kode approval lama pada view dibersihkan | UI detail/daftar tidak menampilkan tombol approve/reject. |
| Route approval lama dinonaktifkan | `LaporanController::verify()` mengembalikan HTTP 410 dan pesan bahwa approval dinonaktifkan. |
| Status `Diarsipkan` ditambahkan | Operator/admin dapat mengeluarkan laporan dari rekap aktif tanpa menghapus data. |
| Script clear laporan hama dibuat | Maintenance lokal dapat mengosongkan transaksi laporan hama tanpa menghapus master data. |
| Akun testing 1 role = 1 user dibuat | `admin_test`, `operator_test`, `viewer_test`, `petugas_test` tersedia untuk validasi role. |

## 15. File yang Dipelajari

- `app/controllers/LaporanController.php`
- `app/controllers/Api/LaporanHamaController.php`
- `app/controllers/Api/DashboardController.php`
- `app/controllers/Api/DashboardMapApiController.php`
- `app/controllers/Api/DashboardChartsApiController.php`
- `app/controllers/DashboardController.php`
- `app/models/LaporanHama.php`
- `app/services/DashboardDataAggregator.php`
- `app/services/DataStoryService.php`
- `app/views/laporan/create.php`
- `app/views/laporan/edit.php`
- `app/views/laporan/index.php`
- `app/views/laporan/table_content.php`
- `app/views/laporan/view.php`
- `app/views/dashboard/index.php`
- `app/views/dashboard/map.php`
- `app/views/dashboard/charts.php`
- `app/core/Router.php`
- `index.php`
- `database/migrations/2026_05_01_add_diarsipkan_status_to_laporan_hama.php`
- `database/maintenance/clear_laporan_hama.sql`
- `users-testing.md`
- `jagapadi.sql` untuk melihat struktur tabel referensi, tanpa menjalankan query database

## 16. Hal yang Perlu Diverifikasi Manual

- Pastikan migration `2026_05_01_add_diarsipkan_status_to_laporan_hama.php` sudah dijalankan pada database lokal/staging yang dipakai.
- Uji route `/laporan/verify/{id}` benar-benar mengembalikan 410 dan tidak punya tombol pemicu di UI.
- Uji filter status peta: UI mengirim `status`, tetapi API/agregator yang dibaca belum terlihat menerapkan filter tersebut.
- Uji `DataStoryService` karena indikasi penggunaan nama kolom lama pada query hama.
- Uji konsistensi aturan delete petugas antara UI dan controller.
- Uji bahwa `Diarsipkan` tidak masuk dashboard/peta aktif setelah laporan diarsipkan.
- Uji viewer secara manual karena pembatasan create/edit ada di controller laporan, sedangkan visibility menu/tombol juga tergantung layout.
