# Matriks Role & Permission Mobile JAGAPADI

Matriks terpusat di `lib/core/permissions.dart`. UI (home screen, detail
screen, router) memeriksa kapabilitas ini untuk menyembunyikan aksi.
**UI bukan security boundary** — backend tetap memvalidasi otorisasi.

## 1. Kapabilitas

`ReportCapability`: canViewDashboard, canViewAllReports, canCreateReport,
canEditOwnReport, canSubmitReport, canVerifyReport, canRejectReport,
canArchiveReport, canExportReport, canManageMasterData.

## 2. Matriks

| Kapabilitas | admin | petugas | operator* | statistisi | viewer | tak dikenal |
|-------------|:-----:|:-------:|:---------:|:----------:|:------:|:-----------:|
| canViewDashboard | ✔ | ✔ | ✔ | ✔ | ✔ | ✘ |
| canViewAllReports | ✔ | ✘ | ✘ | ✔ | ✔ | ✘ |
| canCreateReport | ✔ | ✔ | ✔ | ✘ | ✘ | ✘ |
| canEditOwnReport | ✔ | ✔ | ✔ | ✘ | ✘ | ✘ |
| canSubmitReport | ✔ | ✔ | ✔ | ✘ | ✘ | ✘ |
| canVerifyReport | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ |
| canRejectReport | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ |
| canArchiveReport | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ |
| canExportReport | ✔ | ✔ | ✔ | ✔ | ✘ | ✘ |
| canManageMasterData | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ |

\* Operator diperlakukan setara petugas untuk penulisan laporan — kebijakan
ini perlu dikonfirmasi backend (lihat API_COMPATIBILITY.md).

## 3. Efek di UI

| Area | Perilaku |
|------|----------|
| Bottom nav | Tab "Sinkron" hanya muncul bila `canCreateReport`; indeks tab profil disesuaikan (admin 3, lain 2) |
| Menu grid | Item report/izin ditekan sesuai `actionLabel()`/`actionHint()` per role; "Semua Laporan" hanya untuk yang punya `canViewAllReports`/`canCreateReport` |
| Detail screen | Tombol Verifikasi/Tolak/Arsip hanya bila `canVerifyReport` dkk.; tombol kirim/ubah hanya bila `canSubmitReport` |
| Router | Rute `/…/create` & `/…/edit` diblokir (redirect ke /home) bila user tidak punya `canCreateReport`/`canEditOwnReport` |
| Logout | Data dashboard dibersihkan agar user berikutnya tidak melihat data user sebelumnya |

## 4. Kebijakan Fail-Closed

- Role yang tidak dikenal → tidak ada kapabilitas (read-only total).
- `auth.user?.can(...) ?? false` — saat user null, semua aksi disembunyikan.
- Logika verifikasi ("Draf tidak boleh diverifikasi", "hanya admin yang
  verifikasi") tetap dijalankan backend; mobile hanya menyembunyikan tombol.

## 5. Cara Memakai di Kode

```dart
import '../../../core/permissions.dart';

final auth = context.watch<AuthProvider>();
if (auth.user?.can(ReportCapability.canVerifyReport) ?? false) { ... }
```