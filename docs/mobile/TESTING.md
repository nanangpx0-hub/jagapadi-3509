# Pengujian Mobile JAGAPADI

## 1. Perintah

```sh
cd mobile
flutter analyze        # 0 error, 0 warning (info lint lama dibiarkan)
flutter test           # semua test lulus
flutter build apk --debug   # verifikasi build (jika lingkungan tersedia)
```

## 2. Hasil

- `flutter analyze`: 0 error, 0 warning. Info lint yang tersisa adalah
  info yang sudah ada sebelumnya (mis. `prefer_const_constructors` di
  berkas test lama, `deprecated_member_use` DropdownButtonFormField `value`).
- `flutter test`: **236 test lulus** (semua 18 berkas test lama tetap lulus
  + 7 berkas test baru + `local_db_test.dart` + `sync_service_test.dart`).

## 3. Test Baru (9 berkas)

| Berkas | Cakupan |
|--------|---------|
| `test/core/permissions_test.dart` | Matriks role: admin penuh, petugas/operator/statistisi/viewer, role tak dikenal fail-closed, ekstensi `UserPermissions` |
| `test/core/photo_validator_test.dart` | Magic bytes JPEG/PNG/WebP, byte acak, berkas kosong/tidak ada/ekstensi salah/isi tidak cocok/10 MB, berkas valid |
| `test/core/operation_id_test.dart` | Format `op-` + 32 hex, unik antar pemanggilan |
| `test/core/gps_service_test.dart` | Fake `GpsPlatform`: service mati, denied, deniedForever, sukses, minta izin, 2 percobaan gagal, timeout, sukses di percobaan kedua |
| `test/core/laporan_validators_test.dart` | Tanggal (format, komponen ketat, rentang), angka (empty/negatif/positif/max), koordinat, catatan 2000, wajib/enum, `ModuleValidators` hama/cuaca/koordinat/wilayah |
| `test/core/offline_login_policy_test.dart` | Lockout: hitungan, kunci saat max, kunci berakhir, tidak menambah saat terkunci, reset, max kustom; verifier versi: saat ini/legacy/tak dikenal |
| `test/features/home/dashboard_provider_test.dart` | State success/empty/error/offline/stale, cache dipertahankan, reset, query tahun |
| `test/core/local_db_test.dart` | insert/get (client_operation_id otomatis/eksplisit, fotoPath, StateError tanpa user), update/status (updateDraft reset ke pending, markSynced → pending_photo bila ada foto, markPhotoSynced, markFailed terminal + retry_count), query (getSyncableDrafts eksklusi terminal/synced, getUnsyncedDrafts termasuk terminal, filter tipe, deleteDraft), migration v1/v2 → v3 (kolom `synced_at`/`client_operation_id`/`photo_synced`/`updated_at` ditambahkan) |
| `test/core/sync_service_test.dart` | POST + `Idempotency-Key` → synced; draf berfoto → payload dulu, lalu upload → synced; 422 → `failed_validation` terminal (tidak di-retry); 409 → `conflict` terminal; `pending_photo` + `photoSynced=true` → tanpa upload → synced; pending_update → PUT; tipe tak dikenal dilewati; error jaringan → tetap pending + last_error; guard konkurensi (sinkronisasi kedua ditolak) |

## 4. Pola Stub

- `ApiClient` di-stub dengan subclass manual (tanpa build_runner),
  mengikuti pola `test/features/laporan/laporan_terpadu_provider_test.dart`.
- `GpsPlatform` di-stub dengan fake `implements GpsPlatform`; `Position`
  sintetis dengan data tetap.
- `OfflineLockPolicy` memakai jam tetap (`DateTime` konstan) agar
  deterministik.
- `LocalDb` diuji memakai `sqflite_common_ffi` (dev_dependencies) dengan
  database SQLite asli di `Directory.systemTemp` per test + hook
  `@visibleForTesting` (`LocalDb.testDbPath`, `LocalDb.resetForTesting`),
  storage secure di-mock lewat `MethodChannel`
  `plugins.it_nomads.com/flutter_secure_storage`.

## 5. Yang Belum Dapat Diverifikasi Otomatis

| Item | Alasan | Pengganti |
|------|--------|-----------|
| `flutter build apk` | Bergantung lingkungan Flutter SDK | Jalankan `flutter build apk --debug` bila SDK tersedia |
| Widget test form (6 form) | Form bergantung Provider/ConnectivityService/geolocator | Cakupan logika di level validator & service (diuji) |
| Enkripsi SQLite lokal | Membutuhkan package tambahan (`sqlcipher`) | Risiko didokumentasikan di [SECURITY.md](SECURITY.md) |
| Uji perangkat fisik (GPS, kamera, FCM) | Membutuhkan perangkat Android | Uji manual; dokumentasikan di sini |

## 6. Rekomendasi Tahap Berikutnya

1. Widget test untuk `home_screen` (state dashboard error/offline/stale).
2. Golden test untuk form bila diperlukan.
3. Konfirmasi kebijakan role `operator` di backend → sesuaikan matriks &
   test.
4. Uji sinkronisasi end-to-end di perangkat (draf → submit → verifikasi).