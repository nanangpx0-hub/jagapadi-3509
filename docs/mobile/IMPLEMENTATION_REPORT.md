# Implementasi Perbaikan Mobile JAGAPADI

Dokumen ini adalah ringkasan utama program perbaikan aplikasi mobile JAGAPADI.
Detail teknis tersedia di dokumen pendamping:

| Dokumen | Isi |
|---------|-----|
| [IMPLEMENTATION_AUDIT.md](IMPLEMENTATION_AUDIT.md) | Temuan audit & perbaikan yang dilakukan |
| [SECURITY.md](SECURITY.md) | Keamanan: kredensial offline, lockout, upload, permintaan |
| [OFFLINE_SYNC_DESIGN.md](OFFLINE_SYNC_DESIGN.md) | Desain offline-first & sinkronisasi |
| [ROLE_PERMISSION_MATRIX.md](ROLE_PERMISSION_MATRIX.md) | Matriks role & kapabilitas |
| [API_COMPATIBILITY.md](API_COMPATIBILITY.md) | Kompatibilitas API & kebutuhan backend |
| [TESTING.md](TESTING.md) | Strategi & hasil pengujian |

## 1. Ruang Lingkup

Satu tahap perbaikan menyeluruh aplikasi mobile (Flutter) mencakup:

1. **Keamanan**: lockout login offline, versi verifier offline, validasi foto
   (magic bytes), permintaan izin lokasi yang jelas.
2. **Offline-first & sinkronisasi**: status draf lokal terminal
   (`failed_validation`, `conflict`), skip upload foto yang sudah tersinkron,
   reuse draf lokal (tidak membuat draf ganda saat simpan berulang).
3. **Idempotensi**: `client_operation_id` per draf dikirim sebagai header
   `Idempotency-Key` pada setiap simpan/submit agar retry tidak membuat
   laporan duplikat di server.
4. **Validasi konsisten** di 6 modul laporan (hama, irigasi, pupuk, panen,
   cuaca, alat_sarana): tanggal, angka, koordinat, catatan 2000 karakter,
   enum, field wajib submit, foto wajib sebelum submit.
5. **Role & permission terpusat**: matriks `RolePermissions` menggantikan
   pemeriksaan `isAdmin` tersebar; UI menyembunyikan aksi tanpa izin; router
   memblokir rute create/edit tanpa izin.
6. **Dashboard error state**: tidak lagi menampilkan angka nol saat gagal
   dimuat; menampilkan data cache dengan penanda offline/stale + tombol
   retry; data dibersihkan saat logout.
7. **API client**: retry 5xx hanya untuk request idempoten
   (membawa `Idempotency-Key`), progress upload foto, header tambahan.
8. **Pengujian & dokumentasi**.

## 2. Hasil

- `flutter analyze`: **0 error** (hanya info lint yang sudah ada sebelumnya).
- `flutter test`: **236 test lulus** (termasuk 9 berkas test baru, antara
  lain `local_db_test.dart` & `sync_service_test.dart` yang memakai
  `sqflite_common_ffi` sebagai dev_dependency).
- Semua fitur lama dipertahankan; tidak ada dependency baru yang ditambahkan
  (validasi magic bytes diimplementasikan manual tanpa package `mime`).
- Tidak ada secret yang masuk repository.

## 3. Batasan Jujur (tidak diklaim selesai)

| Batasan | Alasan |
|---------|--------|
| Enkripsi SQLite lokal tidak ditambahkan | Membutuhkan package tambahan (`sqlcipher`); risiko didokumentasikan di [SECURITY.md](SECURITY.md) |
| Unit test `LocalDb` & `SyncService` | **SUDAH ADA** (`test/core/local_db_test.dart`, `test/core/sync_service_test.dart`) — memakai `sqflite_common_ffi` di dev_dependencies |
| Header `Idempotency-Key` | **TERVERIFIKASI aktif di backend** (`Idempotency.php` + `IdempotencyMiddleware`, migration 022, 9 unit test lulus) — lihat [API_COMPATIBILITY.md](API_COMPATIBILITY.md) |
| Kebijakan role `operator` | Diperlakukan setara `petugas` di mobile DAN di backend (`PetugasAdminMiddleware` mengizinkan admin/petugas/operator) — konsisten |
| Verifier offline versi lama (tanpa versi) | Diterima sementara (migration bertahap) hingga login online sekali |
| UI bukan security boundary | Otorisasi final tetap di backend; matriks permission hanya menyembunyikan aksi |

## 4. File Utama yang Diubah/Dibuat

**Baru (lib/core)**:
- `permissions.dart` — `ReportCapability`, `RolePermissions`, `UserPermissions`
- `operation_id.dart` — `OperationId.generate()` (32 hex, `op-...`)
- `photo_validator.dart` — magic bytes JPEG/PNG/WebP + ekstensi + ukuran 10 MB
- `gps_service.dart` — `GpsService` + `GpsPlatform` (injectable), 2 percobaan × 12 dtk
- `offline_login_policy.dart` — `OfflineLockPolicy` (5 percobaan, kunci 5 mnt), `OfflineVerifierPolicy` (versi 1)
- `validators/laporan_validators.dart` — `LaporanValidators` + `ModuleValidators` per modul

**Diubah (inti)**:
- `core/secure_storage.dart` — kolom versi verifier, lockout, timestamp online
- `features/auth/providers/auth_provider.dart` — lockout offline, `setRole()`
- `core/local_db.dart` — DB v3 (`client_operation_id`, `photoSynced`), `getDraft`, `getSyncableDrafts`
- `core/sync_service.dart` — header idempotensi, state `conflict`, skip foto tersinkron
- `core/api_client.dart` — `headers` pada post/put, retry 5xx idempoten, progress upload
- `core/router.dart` — role guard create/edit
- `app.dart` — reset dashboard saat logout
- `features/home/providers/dashboard_provider.dart` — `DashboardViewState` + `reset()`
- `features/home/screens/home_screen.dart` — error/offline/stale UI + nav dinamis + menu permission-aware
- 6 provider laporan — parameter `headers` pada `save()`
- 6 detail screen — `auth.user?.can(...)` menggantikan `isAdmin`
- 6 form screen — GpsService, PhotoValidator, validator numerik/koordinat,
  foto wajib submit, reuse draf lokal, header idempotensi

**Test baru**:
- `test/core/permissions_test.dart`, `photo_validator_test.dart`,
  `operation_id_test.dart`, `gps_service_test.dart`,
  `laporan_validators_test.dart`, `offline_login_policy_test.dart`
- `test/features/home/dashboard_provider_test.dart`
- `test/core/local_db_test.dart`, `test/core/sync_service_test.dart`
  (sqflite_common_ffi)

**Dependency (dev)**:
- `sqflite_common_ffi: ^2.3.3` — unit test `LocalDb`/`SyncService` dengan
  database SQLite asli di `Directory.systemTemp`; hook `@visibleForTesting`
  (`LocalDb.testDbPath`, `LocalDb.resetForTesting`) ditambahkan di
  `local_db.dart`.