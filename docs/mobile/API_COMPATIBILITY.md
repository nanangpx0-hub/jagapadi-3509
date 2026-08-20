# Kompatibilitas API Mobile JAGAPADI

Dokumen kontrak antara mobile dan backend (`docs/API.md`). Tahap ini TIDAK
mengubah endpoint; hanya menambah header opsional pada request.

## 1. Perubahan Request (Tidak Breaking)

### 1.1 Header `Idempotency-Key`

- Dikirim pada POST/PUT laporan (draft & submit) untuk semua modul:
  `/laporan-hama`, `/laporan-irigasi`, `/laporan-pupuk`, `/laporan-panen`,
  `/laporan-cuaca`, `/laporan-alat-sarana`.
- Nilai: `client_operation_id` draf lokal, format `op-<32hex>`,
  dibuat sekali per draf.
- Header bersifat opsional (draf lama bernilai null → header tidak
  dikirim), sehingga backend lama tetap kompatibel.

**Status backend (TERVERIFIKASI):** implementasi sudah ada dan aktif:
- `backend/app/Helpers/Idempotency.php` — hash deterministik request
  (user+method+path+input ter-normalisasi, urutan key tidak berpengaruh,
  isi `foto`/`file` diabaikan agar hash stabil saat retry), TTL 24 jam.
- `backend/app/Middleware/IdempotencyMiddleware.php` — key sama + payload
  sama → replay respons tersimpan; key sama + payload beda → 409;
  entry `processing` (konkurensi) → tunggu lalu replay; unique constraint
  mencegah duplikasi bersamaan.
- Terpasang di rute POST/PUT semua modul laporan (hama, irigasi, pupuk,
  panen, cuaca, alat_sarana) beserta submit/resubmit/verifikasi/tolak/
  arsip/upload foto (`backend/config/routes.php`), SETELAH middleware
  autentikasi/otorisasi.
- Tabel: `idempotency_keys` (migration `022_add_idempotency_and_token_version.sql`).
- Test: `backend/tests/Unit/IdempotencyTest.php` — 9 test lulus;
  seluruh suite backend 203 test lulus.

### 1.2 Retry 5xx mobile

- Mobile melakukan retry otomatis (1×) hanya untuk status
  500/502/503/504 **dan** hanya bila request membawa `Idempotency-Key`.
- Request lain (termasuk upload foto) tidak di-retry otomatis.

## 2. Endpoint yang Dipakai Mobile (Tidak Berubah)

| Modul | List/Create | Detail | Upload foto |
|-------|-------------|--------|-------------|
| hama | `/api/v1/laporan-hama` | `/api/v1/laporan-hama/{id}` | `/api/v1/laporan-hama/{id}/foto` |
| irigasi | `/api/v1/laporan-irigasi` | … | … |
| pupuk | `/api/v1/laporan-pupuk` | … | … |
| panen | `/api/v1/laporan-panen` | … | … |
| cuaca | `/api/v1/laporan-cuaca` | … | … |
| alat_sarana | `/api/v1/laporan-alat-sarana` | … | … |
| dashboard | `/api/v1/dashboard/stats?tahun=YYYY` | | |

Catatan: `include_draft=false` default tetap dipakai (dashboard, peta,
analisis, ekspor) sesuai aturan proyek.

## 3. Kebijakan Role yang Perlu Dikonfirmasi Backend

- Mobile memperlakukan role `operator` setara `petugas` (bisa membuat &
  mengirim laporan). Bila backend membatasi operator, sesuaikan matriks
  `RolePermissions` (fail-closed tidak akan menampilkan aksi terlarang).
- Role `statistisi`/`viewer` tidak mendapat aksi tulis di mobile —
  otorisasi final tetap di backend.

## 4. Validasi yang Diseragamkan (Referensi Backend)

- Foto: magic bytes JPEG/PNG/WebP, ekstensi jpg/jpeg/png/webp, ≤ 10 MB.
- Tanggal: `YYYY-MM-DD`, rentang 2020–hari ini.
- Catatan: ≤ 2000 karakter.
- Koordinat: lat ∈ [-90,90], lng ∈ [-180,180].
- Enum: daftar persis (`tingkat_keparahan`, `kondisi_fisik`, `debit_air`,
  `jenis_pupuk`, `metode_aplikasi`, `musim_tanam`, `kondisi_cuaca`,
  `jenis_sarana`, `kondisi`).

## 5. Hal yang TIDAK Berubah

- Format JSON amplop (`success`, `data`, `message`, `errors`).
- Auth JWT (`Authorization: Bearer`).
- Status laporan & transisi (Draf→Submitted→Diverifikasi/Ditolak, dst).
- Endpoint dashboard/peta/ekspor.