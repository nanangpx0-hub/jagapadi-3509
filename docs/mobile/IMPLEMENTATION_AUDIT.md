# Audit Implementasi Mobile JAGAPADI

Ringkasan temuan audit kode mobile (Fase A) dan perbaikan yang dilakukan.

## 1. Temuan & Perbaikan

### 1.1 Login offline tanpa batasan percobaan
**Temuan**: `auth_provider._loginOffline` dapat dicoba berulang tanpa batas;
verifier lama tidak memiliki tanda versi, sehingga perubahan format verifier
di masa depan tidak dapat dideteksi.

**Perbaikan**:
- `OfflineLockPolicy` (5 percobaan gagal → kunci 5 menit, jam injectable).
- `OfflineVerifierPolicy` (versi 1; verifier legacy tanpa versi tetap
  diterima untuk migration bertahap).
- `AppSecureStorage` menyimpan `offline_verifier_version`,
  `offline_fail_count`, `offline_lock_until`, `offline_last_online_at`.
- Login offline menolak user non-aktif dan user dengan
  `must_change_password`; kegagalan dicatat, keberhasilan mereset hitungan.

### 1.2 Validasi foto hanya ekstensi
**Temuan**: `ErrorHandler.validatePhoto` hanya memeriksa ekstensi + ukuran;
berkas teks/sampah yang dinamai `.jpg` lolos.

**Perbaikan**: `PhotoValidator` memeriksa magic bytes (JPEG `FF D8 FF`, PNG
`89 50 4E 47...`, WebP `RIFF....WEBP`), kecocokan ekstensi, ukuran maksimal
10 MB, dan keterbacaan berkas. Dipakai di semua form yang memiliki foto.
Validasi ini bukan pengganti keamanan backend (dekompresi gambar penuh
tetap tanggung jawab server).

### 1.3 Draf lokal ganda saat simpan berulang
**Temuan**: 4 form (pupuk, panen, cuaca, alat_sarana) memanggil
`LocalDb.insertDraft` pada SETIAP `_saveDraft`, sehingga satu laporan dapat
menghasilkan banyak baris draf lokal.

**Perbaikan**: state `_localDraftId` — draf pertama di-insert, penyimpanan
berikutnya memakai `updateDraft`. Draf lokal dihapus setelah submit sukses.

### 1.4 Tidak ada idempotensi anti-duplikasi
**Temuan**: request POST/PUT yang timeout setelah server memproses akan
diulang dan menghasilkan laporan duplikat.

**Perbaikan**: `OperationId.generate()` membuat `client_operation_id` sekali
per draf (disimpan di DB lokal kolom baru). Setiap simpan/submit mengirimnya
sebagai header `Idempotency-Key`. Backend perlu mengimplementasikan
deduplikasi (lihat API_COMPATIBILITY.md).

### 1.5 Foto diunggah ulang setiap sinkronisasi
**Temuan**: `sync_service` mengunggah foto untuk draf `pending_photo`
berulang kali.

**Perbaikan**: kolom `photoSynced`; bila foto sudah tersinkron, sinkronisasi
berikutnya melewati upload dan hanya mengirim payload.

### 1.6 Status terminal di-sync ulang terus menerus
**Temuan**: draf dengan status `failed_validation`/`conflict` (baru) akan
dicoba disinkronkan terus menerus.

**Perbaikan**: `getSyncableDrafts()` mengecualikan status terminal;
`getUnsyncedDrafts()` tetap dipakai untuk daftar UI. Konflik HTTP 409
memetakan draf ke status `conflict` (bukan `pending`).

### 1.7 Dashboard menampilkan angka nol saat error
**Temuan**: dashboard merender angka 0 saat request gagal, menyesatkan.

**Perbaikan**: `DashboardViewState` (initial/loading/success/empty/error/
offline/stale). Error tanpa cache → layar error + retry; error dengan cache
→ data lama + penanda offline/stale + retry; `empty` hanya jika semua angka
benar-benar nol. `reset()` dipanggil saat logout.

### 1.8 Pemeriksaan role tersebar (`isAdmin`)
**Temuan**: `auth.isAdmin` dipakai di detail screen, home screen, dan
router; role `operator`/`statistisi`/`viewer` tidak terpetakan.

**Perbaikan**: `RolePermissions` matriks terpusat (fail-closed untuk role
tidak dikenal), `UserPermissions.can()`; router memblokir rute create/edit
tanpa izin; bottom nav & menu grid permission-aware. UI bukan security
boundary — backend tetap melakukan otorisasi final.

### 1.9 GPS tanpa penanganan izin/service
**Temuan**: beberapa form memanggil `Geolocator.getCurrentPosition()` tanpa
cek service/izin; pesan error generik.

**Perbaikan**: `GpsService` alur seragam: cek service → cek izin → minta
izin → tangani denied/deniedForever/disabled → 2 percobaan × 12 dtk →
format 7 desimal + pesan akurasi. `GpsPlatform` abstraksi untuk unit test.

### 1.10 Validasi form tidak konsisten antar modul
**Temuan**: tiap form memiliki validasi sendiri; panjang catatan tidak
dibatasi; koordinat tidak divalidasi; angka negatif bisa masuk.

**Perbaikan**: `LaporanValidators` (tanggal ketat YYYY-MM-DD rentang
2020–H+1, angka, koordinat ±90/±180, catatan 2000) + `ModuleValidators`
per modul (enum persis daftar backend, field wajib submit). Form
menerapkan: validator numerik, koordinat, `maxLength: 2000`, dan foto wajib
sebelum submit (konsisten dengan backend yang mewajibkan foto).

### 1.11 Retry otomatis 5xx tanpa idempotensi
**Temuan**: `api_client` berencana retry semua 5xx; untuk request non-idempoten
ini berbahaya.

**Perbaikan**: retry 5xx (500/502/503/504) hanya jika request membawa header
`Idempotency-Key`. `post`/`put` menerima `headers` tambahan. Upload foto
mendukung `onSendProgress` (dipakai form untuk indikator).

## 2. Hal yang Sengaja TIDAK Diubah

- Skema API/endpoint (tidak ada breaking change).
- Status laporan & alur verifikasi backend.
- Struktur tabel DB lokal selain penambahan kolom via migration v2→v3.
- 18 berkas test lama (semua dipertahankan dan tetap lulus).
- Dependency: tidak ada package baru ditambahkan.

## 3. Risiko Tersisa

| Risiko | Mitigasi |
|--------|----------|
| DB lokal tidak terenkripsi | Dokumentasi (SECURITY.md); backup/restore penuh Android menyertakan data draf |
| `Idempotency-Key` diabaikan backend | Tanpa deduplikasi server, retry manual masih berisiko duplikat — backend wajib implementasi |
| Verifier legacy tanpa versi diterima | Ditolak otomatis bila versi `OfflineVerifierPolicy` dinaikkan |
| Role operator tidak dikonfirmasi | Diperlakukan setara petugas; konfirmasi kebijakan backend |
| Draf lama dari versi app sebelumnya | Migration DB v2→v3 memberi `client_operation_id` null; header diabaikan bila null |
| `Position` di uji GPS memakai data sintetis | Platform nyata (geolocator) tetap dipakai saat runtime |