# QA Checklist — JAGAPADI

> Panduan regresi manual sebelum deploy.

---

## Auth

- [ ] Login web (`/login`) sukses dengan admin
- [ ] Login web sukses dengan petugas
- [ ] Login gagal → error flash message
- [ ] Rate limit: 5x gagal login → 429 / flash error
- [ ] Logout → session cleared → redirect ke `/login`
- [ ] Login API (`POST /api/v1/auth/login`) → JWT returned
- [ ] API 401 tanpa token / token invalid
- [ ] API change-password works
- [ ] CSRF: submit form tanpa `_csrf_token` → 419

## Wilayah (Admin)

- [ ] List kabupaten/kecamatan/desa
- [ ] Create/edit/delete kabupaten
- [ ] Hapus kabupaten dengan child → 409
- [ ] Create/edit/delete kecamatan
- [ ] Create/edit/delete desa

## OPT (Admin)

### Usulan OPT (root runtime `/usulan-opt`)

**Menu & akses**
- [ ] Admin melihat menu "Usulan OPT"; Petugas melihat "Usulan OPT Saya"; Operator/Statistisi/Viewer tidak melihat menu usulan
- [ ] Guest diarahkan ke login; Petugas hanya melihat usulan miliknya
- [ ] Petugas A tidak dapat membuka/mengedit/menghapus/resubmit usulan Petugas B (IDOR)
- [ ] Operator/Petugas memanggil semua endpoint review ditolak role

**Form mandiri Petugas**
- [ ] `/usulan-opt/create` tampil mobile-friendly; tombol Simpan Draf dan Kirim untuk Review berbeda jelas
- [ ] Validasi: nama lokal/jenis/komoditas/ciri/tanggal wajib; wilayah hierarki valid; koordinat range; enum allowlist; panjang maksimal
- [ ] Field administratif (status/reviewer/master/user_id) dari payload diabaikan
- [ ] Draf tersimpan tanpa foto; Kirim menuntut wilayah lengkap + minimal 1 foto
- [ ] Old input + pesan validasi aman muncul setelah gagal; exception mentah tidak pernah tampil

**Siklus Draf/Perlu Perbaikan**
- [ ] Edit hanya untuk status `Draf`/`Perlu Perbaikan`; status lain ditolak dengan pesan
- [ ] Update bersyarat: saat status berganti bersamaan, Petugas menerima conflict dan keputusan Admin tidak tertimpa
- [ ] Hapus Draf hanya milik sendiri + konfirmasi; tanpa bulk delete
- [ ] Catatan perbaikan Admin tampil menonjol; resubmit mengembalikan `Menunggu Review` dan catatan lama tetap ada di timeline

**Review Admin**
- [ ] POST tanpa/salah CSRF → 403, data utuh (semua endpoint mutasi)
- [ ] Minta Perbaikan: hanya dari `Menunggu Review`; catatan ≥10 karakter; notifikasi ke pemilik
- [ ] Setujui membuka finalisasi lengkap; duplikat kode/nama → arahan Gabungkan, bukan HTTP 500
- [ ] Gabungkan hanya master aktif jenis sama; Tolak Permanen alasan ≥10; label "Ditolak Permanen"
- [ ] Approve membuat master aktif + relink laporan (`usulan_opt_id` dipertahankan) dalam satu transaksi; retry tidak menduplikasi apa pun
- [ ] Timeline status, galeri foto, laporan terkait, kandidat duplikat, usia antrean tampil di detail/admin queue

**Foto**
- [ ] Upload spoofing/MIME palsu/dimension >6000px/>5 MB/>5 file ditolak; ekstensi dari MIME; nama acak
- [ ] Direktori upload memiliki `.htaccess` non-eksekusi; traversal path ditolak
- [ ] File baru terhapus bila transaksi DB gagal; file lama tak terhapus sebelum sukses
- [ ] Petugas menambah/menghapus foto hanya pada `Draf`/`Perlu Perbaikan` miliknya

**Data**
- [ ] Riwayat transisi lengkap di `usulan_opt_status_history`; audit `activity_log` untuk 10 aksi
- [ ] Notifikasi type stabil dengan entity `usulan_opt` + id + web_path
- [ ] Filter/pencarian SQL injection aman; output XSS ter-escape
- [ ] Semua route statis terdaftar eksplisit di `config/web_routes.php`
- [ ] `/optsaya` membuka modul yang sama dengan `/usulan-opt`; Admin global dan Petugas hanya data miliknya
- [ ] Impor template `.xlsx` dan legacy `.xls` membuat Draf milik session; field `user_id`, status, reviewer dari file tidak diterima
- [ ] Impor menolak ekstensi/MIME-signature spoofing, file kosong, file >10 MB, struktur header salah, dan >5.000 baris
- [ ] Impor parsial menampilkan total/berhasil/gagal serta nomor baris dan alasan; kegagalan satu baris tidak menggagalkan baris valid
- [ ] Tanggal Excel, `YYYY-MM-DD`, `DD/MM/YYYY`, dan angka desimal koma dinormalisasi; nilai di luar range tetap ditolak validator domain
- [ ] Ekspor XLSX mengikuti filter aktif, nama file bertimestamp, tipe tanggal/angka benar, maksimal 10.000 baris
- [ ] Petugas A tidak dapat mengekspor data Petugas B; Admin dapat mengekspor semua data sesuai filter

**Laporan Hama (jalur lama)**
- [ ] Memilih "OPT baru" pada Laporan Hama tetap membuat usulan `Menunggu Review` milik pemilik laporan
- [ ] Laporan gagal disimpan ⇒ usulan ikut rollback (tidak ada orphan); Admin membuat atas nama Petugas ⇒ ownership mengikuti Petugas

**Regresi lintas modul**
- [ ] Setujui Baru membuka form finalisasi (validasi sama `/opt/create`); foto usulan menjadi foto master
- [ ] Duplikat kode/nama case-insensitive → peringatan + arahan Gabungkan, TIDAK HTTP 500
- [ ] Dua keputusan bersamaan hanya satu berhasil; retry tidak menggandakan master/notifikasi/audit
- [ ] Payload XSS tampil ter-escape; input SQL injection tersimpan literal
- [ ] Layout desktop/tablet/mobile tanpa wrapper ganda/overflow; label-for lengkap; tombol ber-teks/aria-label
- [ ] `/opt` tetap berfungsi setelah pemakaian MasterOptService bersama; tombol Tambah/Edit hanya Admin

**Master OPT (regresi umum)**
- [ ] List OPT (filter jenis, q, aktif)
- [ ] Create OPT
- [ ] Edit OPT
- [ ] Hapus OPT (hard delete if no references, soft deactivate if referenced)
- [ ] Upload/delete foto OPT
- [ ] Non-admin hanya melihat OPT aktif

## Laporan Hama

- [ ] Buat draft → status `Draf`, nomor_laporan NULL
- [ ] Edit draft
- [ ] Delete draft
- [ ] Submit draft → status `Submitted`, nomor_laporan `LH-...`
- [ ] Create + submit langsung (action=submit)
- [ ] Detail laporan (owner & admin)
- [ ] Petugas hanya melihat laporan sendiri
- [ ] List filter: status, tanggal, wilayah, OPT, q, pagination
- [ ] Upload foto draft/ditolak
- [ ] Hapus foto draft/ditolak
- [ ] Upload foto status selain draft/ditolak → 409

## Laporan Irigasi

- [ ] Buat draft → status `Draf`, nomor_laporan NULL
- [ ] Edit draft
- [ ] Delete draft
- [ ] Submit draft → status `Submitted`, nomor_laporan `LI-...`
- [ ] Create + submit langsung
- [ ] Detail laporan (owner & admin)
- [ ] Petugas hanya melihat laporan sendiri
- [ ] List filter: status, tanggal, wilayah, kondisi_fisik, debit_air, pagination
- [ ] Upload foto draft/ditolak
- [ ] Hapus foto draft/ditolak

## Verifikasi (Admin)

- [ ] Lihat list submitted (hama & irigasi)
- [ ] Verifikasi submitted → status `Diverifikasi`, verified_at terisi
- [ ] Tolak submitted → status `Ditolak`, alasan di catatan_verifikasi
- [ ] Alasan tolak < 10 karakter → 422
- [ ] Arsip diverifikasi → status `Diarsipkan`
- [ ] Resubmit ditolak → status `Submitted`, verified_at NULL
- [ ] Transisi ilegal → 409
- [ ] Petugas tidak bisa verifikasi

## Dashboard & Cache

- [ ] Halaman dashboard render KPI, chart, map
- [ ] Filter tahun berfungsi
- [ ] Stats JSON match halaman
- [ ] Chart data bulanan akurat
- [ ] Map menampilkan titik laporan
- [ ] Invalidate cache: submit/verify/reject → stats berubah

## Export

- [ ] Form filter export
- [ ] Export CSV (hama & irigasi)
- [ ] Export XLSX (hama & irigasi)
- [ ] Filter status/tanggal/wilayah dihormati
- [ ] Max 10.000 rows → 422 jika lebih
- [ ] Petugas hanya export data sendiri
- [ ] Temp file dihapus setelah download

## Notifikasi

- [ ] Petugas submit → admin dapat `laporan_submitted`
- [ ] Petugas resubmit → admin dapat `laporan_resubmitted`
- [ ] Admin verifikasi → owner dapat `laporan_verified`
- [ ] Admin tolak → owner dapat `laporan_rejected` (cuplikan alasan)
- [ ] Admin arsip → owner dapat `laporan_archived`
- [ ] Bell badge unread count akurat
- [ ] Mark read → badge berkurang
- [ ] Mark all read → badge 0
- [ ] Klik notif → redirect ke detail laporan
- [ ] Petugas A tidak lihat notif Petugas B
- [ ] Polling 60 detik tidak overload

## Upload Foto

- [ ] File valid (JPEG/PNG/WebP) → sukses
- [ ] File jahat (.php, .exe) → 422
- [ ] File >10MB → 422
- [ ] Magic bytes valid → reject file dengan ekstensi .jpg tapi konten text
- [ ] Delete foto → foto_url NULL
- [ ] Path traversal dicegah

## Security

- [ ] APP_DEBUG=false → no stack trace
- [ ] Session cookie httponly, samesite=Lax
- [ ] CSRF token required for all POST/PUT/DELETE web
- [ ] X-Content-Type-Options: nosniff
- [ ] X-Frame-Options: DENY
- [ ] CSP header aktif
- [ ] Upload .htaccess protects from PHP execution
- [ ] Rate limit: export 20/jam, notif poll 120/jam, API 1000/jam

## Role Scope

- [ ] Admin: melihat semua laporan, verifikasi, kelola wilayah + OPT
- [ ] Petugas: hanya laporan sendiri, tidak bisa verifikasi
- [ ] API JWT: role enforcement
- [ ] Web session: role enforcement via middleware

---

## Environment

| Env | Expected |
|-----|----------|
| `.env` | Tidak di-commit ke repo |
| `JWT_SECRET` | Minimal 64 karakter, unik per environment |
| `APP_DEBUG` | `false` di production |
| `storage/cache/` | Writable |
| `storage/logs/` | Writable |
| `storage/tmp/` | Writable |
| `public/assets/uploads/` | Writable |

---

## Otorisasi Edit Laporan (PUT)

Backend: `LaporanPolicy::editDenial()` di semua `updateDraft()`; UI web/mobile
menyembunyikan menu edit untuk non-pemilik.

- [ ] Petugas A edit Draf milik A via API -> 200, data berubah, status tetap
- [ ] Petugas B edit laporan milik A (IDOR) -> 404 NotFound, data tidak berubah
- [ ] Admin PUT endpoint petugas -> 404 (endpoint khusus pemilik)
- [ ] Pemilik edit status Submitted/Diverifikasi/Diarsipkan -> 409 Conflict
- [ ] Body berisi `user_id`/`status` ilegal -> diabaikan server
- [ ] ID 0 / negatif / non-numerik -> 404 tanpa error SQL
- [ ] Web: Petugas B membuka `/laporan-hama/{idA}/edit` -> redirect + flash error
- [ ] Web: tombol Edit hanya tampil untuk pemilik pada baris Draf/Ditolak sendiri
- [ ] Mobile: tombol Edit/Kirim disembunyikan bila `l.userId != auth.user.id`
      (widget/unit test `report_edit_access_test.dart`)
