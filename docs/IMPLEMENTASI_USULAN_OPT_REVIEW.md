# Implementasi Review Usulan OPT (Runtime Root)

> Terakhir diperbarui: 24 Agustus 2026
> Scope: modul `/usulan-opt` runtime root/integrated + penyelarasan `/opt`

## Ringkasan

Modul Usulan OPT menyaring proposal master OPT baru dari Petugas sebelum masuk
ke master global. Workflow v2 (migration 24 Agustus 2026):

```text
Draf ──submit──▶ Menunggu Review ─┬─ Setujui (form finalisasi) ─▶ Disetujui + Master baru
   ▲                              ├─ Gabungkan (master aktif, jenis sama) ▶ Digabungkan
   │                              ├─ Minta Perbaikan ─▶ Perlu Perbaikan ─resubmit─▶ Menunggu Review
   │                              └─ Tolak Permanen (alasan ≥10) ─▶ Ditolak Permanen
   └── Hapus Draf (pemilik)
```

Komponen utama:

1. **UsulanOptService** — domain sisi Petugas: normalisasi/validasi payload
   (field administratif diabaikan), validasi hierarki wilayah authoritative,
   koordinat & enum allowlist, createDraft/update conditional/submit/resubmit/
   deleteDraft, `createFromLaporan` untuk jalur Laporan Hama, notifikasi, audit.
2. **UsulanOptReviewService** — keputusan Admin transaksional idempoten:
   requestRevision/approveNew/merge/rejectPermanent dengan lock `FOR UPDATE`,
   penulisan history dalam transaksi yang sama, rollback penuh.
3. **UsulanPhotoUploader** — upload aman terpusat: error/size/magic bytes/MIME
   allowlist/nama acak/checksum/.htaccess non-eksekusi/traversal-guard; file baru
   dihapus bila transaksi gagal; maksimal 5 foto × 5 MB.
4. **MasterOptService** — validator master bersama untuk `/opt/create`,
   `/opt/edit`, dan finalisasi persetujuan; deteksi duplikat case-insensitive.
5. Menu sidebar via `SidebarState::usulanOptMenuLabel()` — Admin "Usulan OPT",
   Petugas "Usulan OPT Saya", role lain tanpa menu; active state semua route
   `/usulan-opt`; hak akses tetap divalidasi backend.

## Migration dan Rollback

`database/migrations/2026_08_24_usulan_opt_workflow_expansion.sql`
(tercatat di `schema_migrations`; backup pra-migration:
`backups/db_backup_pre_usulan_opt_workflow_*.sql`). Isi: ENUM status baru +
migrasi nilai `Ditolak`→`Ditolak Permanen`, kolom identifikasi + FK/CHECK,
indeks `submitted_at`, tabel `usulan_opt_photos` dan
`usulan_opt_status_history`. Rollback plan dokumentatif ada di
`docs/DATABASE.md`.

## Role dan Ownership

| Aksi | Admin | Operator | Statistisi | Viewer | Petugas |
|---|---|---|---|---|---|
| Lihat daftar semua usulan | Ya | Tidak | Tidak | Tidak | Hanya miliknya |
| Detail usulan orang lain | Ya | Tidak | Tidak | Tidak | Tidak |
| Finalisasi/Setujui/Merge/Tolak/Search-master | Ya | Tidak | Tidak | Tidak | Tidak |

- Semua mutasi: POST + CSRF (`requireStateChangingRequest`), role dicek di
  controller (backend authoritative), bukan hanya disembunyikan di view.
- `reviewed_by`, `reviewed_at`, `status`, `master_opt_id` selalu ditetapkan
  server dari sesi; input client tidak dipercaya.
- Petugas melihat menu sidebar "Usulan OPT Saya".

## Workflow Status

```text
Menunggu Review ─┬─ Setujui (form finalisasi) → Disetujui  (+ master baru)
                 ├─ Gabungkan (master aktif, jenis sama) → Digabungkan
                 └─ Tolak (alasan >= 10 karakter)        → Ditolak
```

## Transaksi Persetujuan

```text
BEGIN;
  SELECT * FROM usulan_opt WHERE id=? FOR UPDATE      -- lock + cek status
  INSERT INTO master_opt (...)                        -- unique nama_opt guard
  UPDATE laporan_hama SET master_opt_id=? WHERE usulan_opt_id=?
  UPDATE usulan_opt SET status/master/reviewer/catatan
  INSERT activity_log
  INSERT notifications (ke pemilik)
COMMIT;
```

- Gagal di langkah mana pun → rollback seluruhnya; pengguna hanya melihat
  pesan generik (detail dicatat di `error_log`).
- Keputusan kedua atas usulan yang sudah direview = no-op dengan alasan
  `already_reviewed`; tidak menggandakan master/notifikasi/audit.
- Duplikat (unique constraint atau pre-check case-insensitive) → respons
  terstruktur yang mengarahkan Admin ke alur Gabungkan, bukan HTTP 500.

## Database

Migration append-only:
`database/migrations/2026_08_23_add_usulan_opt_review_indexes.sql`
(indeks `nama_nasional`, `nama_lokal`, `created_at`). Sudah dijalankan pada
database target dan tercatat di `schema_migrations`. Backup pra-migration:
`backups/db_backup_pre_usulan_opt_indexes_*.sql`.

Skema `usulan_opt` sendiri berasal dari migration 21 Agustus 2026 (tidak
diubah). `laporan_hama.usulan_opt_id` dipertahankan sebagai referensi audit
setelah relink `master_opt_id`.

## Endpoint

Lihat `docs/API.md` bagian "Usulan OPT — Web Endpoints". Route statis baru di
`config/web_routes.php`: `usulan-opt/approve-new`, `usulan-opt/search-master`;
path berparameter (`detail/{id}`, `finalize/{id}`) dilayani konvensi router.

## Impor dan Ekspor Excel

- `/optsaya` adalah alias kompatibilitas untuk halaman utama `/usulan-opt`.
- Admin dan Petugas dapat mengimpor `.xlsx` atau legacy `.xls` maksimal 10 MB
  dan 5.000 baris menggunakan template resmi. Setiap baris valid disimpan
  sebagai `Draf`; owner selalu berasal dari session dan bukan dari workbook.
- Validasi mencakup signature file, ekstensi, struktur/urutan header, tanggal,
  angka, enum, panjang teks, koordinat, serta hierarki wilayah bila ID wilayah
  diisi. Impor bersifat parsial dan menampilkan ringkasan per nomor baris.
- Ekspor menghormati filter daftar dan scope ownership, dibatasi 10.000 baris,
  memakai nama file bertimestamp, serta menulis seluruh nilai teks sebagai tipe
  string untuk mencegah formula injection saat file dibuka di Excel.

## Pengujian

| Jenis | File | Cakupan |
|---|---|---|
| Unit | `tests/Unit/MasterOptServiceValidationTest.php` | normalize/validate/enum/panjang/ETL |
| Integration | `tests/Integration/UsulanOptReviewDatabaseTest.php` | approve+relink+notif+audit, idempotensi, duplikat, merge aktif/jenis, tolak <10 karakter, payload XSS/SQLi, ownership scoping model |

Jalankan:

```powershell
php backend\vendor\phpunit\phpunit\phpunit --configuration phpunit.xml --filter "MasterOptServiceValidationTest|UsulanOptReviewDatabaseTest"
```

## Pemeliharaan

- Ubah aturan validasi master hanya di `MasterOptService` agar ketiga jalur
  (create/edit/finalisasi) tetap konsisten.
- Tambah enum/status baru harus menyentuh: konstanta service, ENUM kolom DB
  (migration baru), tab view, dan filter allowlist model.
