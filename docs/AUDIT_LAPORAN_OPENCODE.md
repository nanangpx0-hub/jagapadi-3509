# Audit Modul Laporan & Status Transition Backend JAGAPADI

**Auditor:** OpenCode AI  
**Tanggal:** 2026-07-17  
**Target:** Laporan Hama, Laporan Irigasi, Status Transition

---

## A. Ringkasan

| Metrik | Nilai |
|--------|-------|
| **Skor Laporan** | **9.0 / 10** |
| Temuan Kritis | 0 |
| Temuan Tinggi | 0 |
| Temuan Sedang | 3 |
| Temuan Rendah | 2 |
| **Status** | **Stabil, beberapa hardening dan konsistensi** |

---

## B. Temuan

### JGP-LAP-001 — Sedang — Verify, reject, archive tidak dibungkus transaction

**File:**
- `backend/app/Services/LaporanHamaService.php:261-289` (verify), `291-321` (reject), `324-351` (archive)
- `backend/app/Services/LaporanIrigasiService.php:261-289` (verify), `291-322` (reject), `324-352` (archive)

```php
// verify — tanpa transaction
LaporanHama::updateStatusAndVerification($id, LaporanStatus::DIVERIFIKASI, $adminId, $catatanTrimmed);
ActivityLog::log(...);
```

**Dampak:** Jika `ActivityLog::log()` gagal (misal DB transient error), status laporan sudah berubah tapi tidak tercatat di log. Tidak ada rollback.

**Perbaikan:** Bungkus `updateStatusAndVerification()` + `ActivityLog::log()` dalam `beginTransaction()` / `commit()` / `rollBack()`.

**Effort:** Kecil

---

### JGP-LAP-002 — Sedang — Filter `include_draft=false` menimpa filter status lain untuk petugas

**File:**
- `backend/app/Services/LaporanHamaService.php:230-238`
- `backend/app/Services/LaporanIrigasiService.php:230-238`

```php
$queryFilters = $filters;
if (!$includeDraft && $currentUser['role'] === 'petugas') {
    $queryFilters['status'] = 'Submitted';  // ← menimpa status filter apapun
}
```

**Dampak:** Jika petugas memanggil `?include_draft=false&status=Diverifikasi`, filter `Diverifikasi` diabaikan. Hasil tetap hanya `Submitted`.

**Perbaikan:** Gabungkan logika: jika `include_draft=false`, filter status bukan Draf, bukan timpa mentah. Misal: `$queryFilters['status'] = $filters['status'] ?? 'Submitted'` lalu tambahkan logika `NOT IN ('Draf')`.

**Effort:** Kecil

---

### JGP-LAP-003 — Sedang — Nomor laporan bisa duplikat jika counter di-reset manual di DB

**File:** `backend/app/Helpers/NomorLaporanGenerator.php:26-31`

```php
$stmt = $pdo->prepare("
    INSERT INTO `nomor_laporan_counter` (`prefix`, `tanggal`, `counter`)
    VALUES (?, ?, 1)
    ON DUPLICATE KEY UPDATE `counter` = `counter` + 1
");
```

**Dampak:** Counter menggunakan `ON DUPLICATE KEY UPDATE` yang atomic di level DB. Namun, jika record counter dihapus manual atau counter di-reset, nomor laporan yang sama bisa dibuat ulang. Ini risiko operasional, bukan celah keamanan.

**Perbaikan:** Tambahkan validasi uniqueness di kolom `nomor_laporan` di tabel `laporan_hama` dan `laporan_irigasi` (UNIQUE INDEX). Sehingga jika duplikat terjadi, INSERT akan gagal.

**Effort:** Kecil (1 migration + 1 catch di service)

---

### JGP-LAP-004 — Rendah — Nama field verify/archive tidak konsisten antara API dan Web

| Endpoint | API | Web |
|----------|-----|-----|
| verify | `catatan` | `catatan_verifikasi` |
| reject | `alasan` | `alasan` |
| archive | `catatan` | `catatan_verifikasi` |

**File:**
- `Api/LaporanHamaController.php:120` — `$input['catatan']`
- `Web/LaporanHamaController.php:242` — `$input['catatan_verifikasi']`
- `Api/LaporanHamaController.php:159` — `$input['catatan']`
- `Web/LaporanHamaController.php:302` — `$input['catatan_verifikasi']`
- `LaporanHamaService.php:274` — parameter `?string $catatan` (sama untuk verify & archive)

**Dampak:** Developer bisa bingung field mana yang benar. Jika integrasi pihak ketiga menggunakan field berbeda, error 422.

**Perbaikan:** Seragamkan. Pilih satu: `catatan` untuk API dan Web.

**Effort:** Kecil

---

### JGP-LAP-005 — Rendah — `createDraft` memanggil `invalidateCache` padahal draft tidak tampil di dashboard default

**File:**
- `backend/app/Services/LaporanHamaService.php:48`
- `backend/app/Services/LaporanIrigasiService.php:48`

```php
DashboardService::invalidateCache();  // dipanggil setiap create draft
```

Padahal default dashboard adalah `include_draft=false`, jadi draft tidak memengaruhi statistik.

**Dampak:** Inefisiensi cache — setiap pembuatan draft membersihkan cache dashboard yang mungkin sedang dipakai. Tidak ada dampak keamanan atau fungsional.

**Perbaikan:** Hanya invalidate cache untuk operasi yang mengubah status menjadi non-Draf (submit, verify, reject, archive, resubmit).

**Effort:** Kecil

---

## C. Checklist

| Item | Status | Bukti |
|------|--------|-------|
| **SQL Injection** | | |
| Semua query pakai prepared statement | **PASS** | `LaporanHama.php`, `LaporanIrigasi.php`, `DashboardService.php` — 100% parameterized |
| Filter whitelist kolom | **PASS** | `ALLOWED_FILTERS` const, switch-case whitelist di `buildListQuery()` |
| **Scope Petugas vs Admin** | | |
| Petugas hanya lihat laporan sendiri | **PASS** | `findAccessibleById()` — `AND user_id = ?` untuk petugas |
| Admin lihat semua laporan | **PASS** | `findAccessibleById()` — tanpa filter user_id untuk admin |
| Petugas tidak bisa verify/reject/archive | **PASS** | Service verify/reject/archive hardcode `role: 'admin'` di panggilan `findAccessibleById()` |
| Admin tidak bisa resubmit milik petugas | **PASS** | `resubmit()` hardcode `role: 'petugas'` |
| **Status Transition** | | |
| Draf → Submitted ✅ (petugas) | **PASS** | `TRANSITIONS[Draf]` tidak ada, tapi `submitDraft()` bypass matrix dengan cek `status === 'Draf'` langsung |
| Submitted → Diverifikasi ✅ (admin) | **PASS** | `assertCanTransition('Submitted', 'Diverifikasi', 'admin')` |
| Submitted → Ditolak ✅ (admin) | **PASS** | `assertCanTransition('Submitted', 'Ditolak', 'admin')` |
| Diverifikasi → Diarsipkan ✅ (admin) | **PASS** | `assertCanTransition('Diverifikasi', 'Diarsipkan', 'admin')` |
| Ditolak → Submitted ✅ (petugas) | **PASS** | `assertCanTransition('Ditolak', 'Submitted', 'petugas')` |
| Ditolak → Draf ✅ (petugas) | **PASS** | `assertCanTransition('Ditolak', 'Draf', 'petugas')` |
| Draf tidak bisa diverifikasi | **PASS** | `assertCanTransition('Draf', 'Diverifikasi')` akan throw karena Draf tidak ada di TRANSITIONS |
| **Nomor Laporan** | | |
| Prefix LH dan LI | **PASS** | `NomorLaporanGenerator::ALLOWED_PREFIXES = ['LH', 'LI']` |
| Atomic counter (ON DUPLICATE KEY) | **PASS** | `NomorLaporanGenerator.php:26-31` |
| Transaction + inTransaction check | **PASS** | `NomorLaporanGenerator.php:20-23` — mulai transaksi hanya jika belum dalam transaksi |
| Nomor dibuat hanya saat Submitted | **PASS** | `createAndSubmit()` dan `submitDraft()` — `$nomor = NomorLaporanGenerator::generate()` hanya dipanggil saat submit |
| Draft tidak punya nomor | **PASS** | `createDraft()` tidak memanggil generator |
| **Validasi** | | |
| Draft: validasi field opsional | **PASS** | `validateDraft()` — hanya validasi field yang diisi |
| Submit: validasi field wajib | **PASS** | `validateSubmit()` — required fields + tipe |
| OPT valid dan aktif | **PASS** | `MasterOpt::find($id) && $opt['aktif']` |
| Wilayah hirarki valid | **PASS** | Kabupaten → Kecamatan → Desa cross-check |
| Tanggal format Y-m-d | **PASS** | `DateTime::createFromFormat('Y-m-d')` |
| **Authorization Verify/Reject/Archive/Resubmit** | | |
| verify: admin only | **PASS** | Role check di controller + service |
| reject: admin only | **PASS** | Role check + `assertCanTransition()` |
| reject: alasan min 10 karakter | **PASS** | `mb_strlen($alasan) < 10` → 422 |
| reject: alasan max 2000 karakter | **PASS** | `mb_strlen($alasan) > 2000` → 422 |
| archive: admin only | **PASS** | Role check + `assertCanTransition()` |
| resubmit: petugas owner only | **PASS** | `findAccessibleById(..., ['role'=>'petugas'])` + `assertCanTransition()` |
| **Activity Log** | | |
| Draft created | **PASS** | `'laporan_hama_draft_created'` / `'laporan_irigasi_draft_created'` |
| Draft updated | **PASS** | `'laporan_hama_draft_updated'` / `'laporan_irigasi_draft_updated'` |
| Draft deleted | **PASS** | `'laporan_hama_draft_deleted'` / `'laporan_irigasi_draft_deleted'` |
| Submitted | **PASS** | `'laporan_hama_submitted'` / `'laporan_irigasi_submitted'` |
| Verified | **PASS** | `'laporan_hama_verified'` / `'laporan_irigasi_verified'` |
| Rejected | **PASS** | `'laporan_hama_rejected'` / `'laporan_irigasi_rejected'` |
| Archived | **PASS** | `'laporan_hama_archived'` / `'laporan_irigasi_archived'` |
| Resubmitted | **PASS** | `'laporan_hama_resubmitted'` / `'laporan_irigasi_resubmitted'` |
| Photo uploaded / deleted | **PASS** | `'laporan_hama_photo_uploaded'` / `'laporan_hama_photo_deleted'` |
| **Cache Invalidation** | | |
| `invalidateCache()` dipanggil di semua state change | **PASS** | createDraft, updateDraft, deleteDraft, createAndSubmit, submitDraft, verify, reject, archive, resubmit — semua panggil `DashboardService::invalidateCache()` |

---

## D. Test Manual

### 1. Flow lengkap Hama (draft → submit → verify → archive)
```bash
# 1. Login petugas
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"petugas01","password":"ChangeMePetugas!123"}' | jq -r '.data.token')

# 2. Buat draft
DRAFT=$(curl -s -X POST http://localhost:8080/api/v1/laporan-hama \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tanggal":"2026-07-17","master_opt_id":1,"kabupaten_id":1}' | jq -r '.data.id')

# 3. Submit draft
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/$DRAFT/submit \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tanggal":"2026-07-17","master_opt_id":1,"kabupaten_id":1,"kecamatan_id":1,"desa_id":1,"tingkat_keparahan":"Ringan","luas_serangan":1.5,"populasi":100}'

# 4. Login admin
ADMIN_TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"ChangeMeAdmin!123"}' | jq -r '.data.token')

# 5. Admin verifikasi
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/$DRAFT/verifikasi \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"catatan":"Laporan valid"}'

# 6. Admin arsip
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/$DRAFT/archive \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json"
```

### 2. Reject dengan alasan pendek
```bash
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id}/tolak \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"alasan":"pendek"}'
# Harus 422 — min 10 karakter
```

### 3. Petugas akses endpoint verify
```bash
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id}/verifikasi \
  -H "Authorization: Bearer $TOKEN_PETUGAS" \
  -H "Content-Type: application/json"
# Harus 403 Forbidden
```

### 4. include_draft=false + status filter
```bash
curl -s "http://localhost:8080/api/v1/laporan-hama?include_draft=false&status=Diverifikasi" \
  -H "Authorization: Bearer $TOKEN_PETUGAS"
# Saat ini return Submitted (bug JGP-LAP-002), seharusnya Diverifikasi tanpa Draf
```

### 5. Verifikasi draft (harus gagal)
```bash
curl -s -X POST http://localhost:8080/api/v1/laporan-hama/{id_draft}/verifikasi \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json"
# Harus 409 — transisi Draf → Diverifikasi tidak diizinkan
```

---

## E. Ringkasan Perbaikan Prioritas

| ID | Severity | Perbaikan | Effort |
|----|----------|-----------|--------|
| JGP-LAP-001 | Sedang | Bungkus verify/reject/archive dalam transaction | Kecil |
| JGP-LAP-002 | Sedang | Filter `include_draft=false` tanpa timpa status lain | Kecil |
| JGP-LAP-003 | Sedang | UNIQUE INDEX pada `nomor_laporan` | Kecil |
| JGP-LAP-004 | Rendah | Seragamkan field `catatan` vs `catatan_verifikasi` | Kecil |
| JGP-LAP-005 | Rendah | Hanya invalidate cache untuk non-Draf operations | Kecil |

---

**Siap lanjut pass perbaikan atau audit modul berikutnya.**
