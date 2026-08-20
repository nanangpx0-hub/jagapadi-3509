# Laporan Audit Kode Mobile Flutter JAGAPADI — Role Petugas

> **Tanggal Audit**: 11 Agustus 2026  
> **Target Role**: Petugas Lapangan (`!auth.isAdmin`)  
> **Lokasi Kode**: `mobile/lib/`  
> **Auditor**: Senior Mobile Engineer & Technical Lead

---

## 1. Matriks Kelengkapan Fitur (API vs Flutter Mobile)

| Endpoint API | Modul Mobile | Status Implementation | Catatan / Gaps |
|---|---|---|---|
| `POST /api/v1/auth/login` | `auth` | ✅ Terimplementasi | JWT storage & user session |
| `POST /api/v1/auth/refresh` | `core` | ✅ Terimplementasi | Interceptor auto-refresh di `api_client.dart` |
| `POST /api/v1/auth/logout` | `auth` | ✅ Terimplementasi | Clear storage & FCM token unregister |
| `POST /api/v1/auth/change-password` | `auth`/`profile` | ✅ Terimplementasi | `ProfileScreen` |
| `GET /api/v1/me` | `auth`/`profile` | ✅ Terimplementasi | Profile viewer |
| `GET /api/v1/wilayah/*` | `wilayah` | ✅ Terimplementasi | `WilayahPicker` cascading |
| `GET /api/v1/opt?aktif=1` | `hama` | ✅ Terimplementasi | `LaporanHamaProvider.loadOptList()` |
| `GET /api/v1/laporan-hama` | `hama` | 🟡 Sebagian | List ada, tapi filter status & search belum lengkap |
| `POST /api/v1/laporan-hama` | `hama` | 🟡 Sebagian | Form ada, belum offline-first draft |
| `GET /api/v1/laporan-hama/{id}` | `hama` | 🟡 Sebagian | Detail ada, belum ada timeline status & map mini |
| `PUT /api/v1/laporan-hama/{id}` | `hama` | ✅ Terimplementasi | Edit Draf |
| `DELETE /api/v1/laporan-hama/{id}` | `hama` | ✅ Terimplementasi | Delete Draf |
| `POST /api/v1/laporan-hama/{id}/submit` | `hama` | ✅ Terimplementasi | Submit Draf |
| `POST /api/v1/laporan-hama/{id}/resubmit` | `hama` | ✅ Terimplementasi | Resubmit Ditolak |
| `POST /api/v1/laporan-hama/{id}/foto` | `hama` | ✅ Terimplementasi | Single photo upload |
| `GET /api/v1/laporan-irigasi` | `irigasi` | 🟡 Sebagian | Belum ada filter status & search |
| `POST /api/v1/laporan-irigasi` | `irigasi` | 🟡 Sebagian | Belum offline-first draft |
| `GET /api/v1/dashboard/stats` | `home` | 🔴 Belum Ada | Belum ada statistik laporan milik petugas |
| `GET /api/v1/notifications` | `notifications` | 🟡 Sebagian | Deep link ke go_router perlu penyesuaian |

---

## 2. Kualitas Kode & Technical Debt

### Issue & Severity Matrix

| Issue ID | Severity | File / Lokasi | Deskripsi Masalah | Solusi & Rekomendasi |
|---|---|---|---|---|
| **TD-01** | **MAJOR** | `home_screen.dart:126` | Menggunakan `Navigator.of(context).pushNamed()` padahal app berbasis `go_router`. Navigasi rentan error. | Ganti ke `context.go()` atau `context.push()`. |
| **TD-02** | **MAJOR** | Form screens | Input tanggal menggunakan `TextFormField` manual (teks polos). Berisiko format tanggal salah (`YYYY-MM-DD`). | Ganti dengan native `showDatePicker()`. |
| **TD-03** | **MAJOR** | Provider & Network | Tidak ada local storage fallback saat offline. Draf langsung dikirim via Dio API. | Tambahkan `sqflite` + `connectivity_plus` untuk offline draft queue. |
| **TD-04** | **MEDIUM** | List Screens | List laporan hama & irigasi belum memiliki filter status (`Draf`, `Submitted`, `Diverifikasi`, `Ditolak`) dan search bar. | Tambahkan `FilterChip` + `SearchBar` dengan debounce. |
| **TD-05** | **MEDIUM** | Form Screens | Preview foto setelah `ImagePicker` hanya berupa indikator teks KB (tanpa thumbnail foto & tombol hapus). | Buat `FotoPicker` widget dengan thumbnail preview & file size MB/KB human readable. |
| **TD-06** | **MEDIUM** | Detail Screens | Tidak ada visual timeline status (Draf → Dikirim → Diverifikasi/Ditolak) dan peta mini lokasi. | Buat `StatusTimeline` widget & peta mini OpenStreetMap dengan `flutter_map`. |
| **TD-07** | **MINOR** | `theme.dart` | `AppColors.textSecondary` (#888888) memiliki contrast ratio rendah terhadap background putih. | Adjust ke `#767676` agar memenuhi standar WCAG AA (4.5:1). |

---

## 3. Evaluasi UX Gaps & Keamanan

1. **UX Lapangan**:
   - Loading indicator masih menggunakan spinner tunggal yang dapat memicu layout shift. Perlu Skeleton loading.
   - Tidak ada dialog konfirmasi ringkasan sebelum submit laporan.
   - Pesan error API dari HTTP 429 (rate limit) dan 422 belum distandarisasi dengan ramah pengguna.
2. **Keamanan**:
   - JWT Token tersimpan aman di `flutter_secure_storage` (`AppSecureStorage`).
   - `ApiClient` mengisolasi header `Authorization` dengan Bearer token.
   - Tidak ada token atau secret yang di-log ke console produksi.

---

## 4. Offline Readiness

- **Status Saat Ini**: Belum memiliki dukungan offline. Jika pengguna kehilangan sinyal internet saat mengisi draf, aplikasi akan menampilkan error koneksi.
- **Rencana Mitigasi**: Implementasi SQLite (`sqflite`) sebagai buffer lokal pertama (local-first draft), kemudian sinkronisasi otomatis ketika `ConnectivityService` mendeteksi koneksi online. Submit laporan tetap diwajibkan online.

---

## 5. Prioritas Perbaikan (Roadmap Effort S/M/L)

1. **FASE 1: Form UX & Search/Filter List & Dashboard Petugas** *(Effort: M)*
2. **FASE 2: Offline-First Local Draft Queue (sqflite + connectivity)** *(Effort: L)*
3. **FASE 3: Detail Timeline Status + Peta Mini + Deep Link Notif** *(Effort: M)*
4. **FASE 4: Keamanan Input Validation + Skeleton Loading & Accessibility** *(Effort: S)*
5. **FASE 5: Unit & Integration Testing (Mockito & integration_test)** *(Effort: M)*
6. **FASE 6: Build Release APK & Production Config** *(Effort: S)*