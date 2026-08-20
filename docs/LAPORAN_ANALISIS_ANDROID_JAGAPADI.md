# Laporan Analisis Mendalam — Aplikasi Android JAGAPADI
**Versi Aplikasi**: 1.1.1+4  
**Tanggal Analisis**: Agustus 2026  
**Metodologi**: Audit langsung terhadap source code, konfigurasi Android, dan arsitektur sistem

---

## Daftar Isi
1. [Inventarisasi Fitur](#1-inventarisasi-fitur)
2. [Analisis Arsitektur](#2-analisis-arsitektur)
3. [Evaluasi Kinerja](#3-evaluasi-kinerja)
4. [Analisis Keamanan Data](#4-analisis-keamanan-data)
5. [Kompatibilitas Android](#5-kompatibilitas-android)
6. [Evaluasi UI/UX](#6-evaluasi-uiux)
7. [Mode Offline](#7-mode-offline)
8. [Temuan Kritis & Status Perbaikan](#8-temuan-kritis--status-perbaikan)
9. [Rekomendasi Terukur](#9-rekomendasi-terukur)

---

## 1. Inventarisasi Fitur

### 1.1 Fitur Utama

| No | Fitur | Modul | Status | Keterangan |
|----|-------|-------|--------|------------|
| F01 | Autentikasi JWT | `auth/` | ✅ Lengkap | Login online + offline, auto-refresh token 401 |
| F02 | Mode Login Offline | `auth/providers/auth_provider.dart` | ✅ Lengkap | Verifikasi kredensial via hash tersimpan |
| F03 | Laporan Hama/OPT | `hama/` | ✅ Lengkap | CRUD, draf, submit, resubmit, verifikasi |
| F04 | Laporan Irigasi | `irigasi/` | ✅ Lengkap | CRUD, draf, submit, resubmit, verifikasi |
| F05 | Laporan Pupuk | `pupuk/` | ✅ Lengkap | CRUD + workflow |
| F06 | Laporan Panen | `panen/` | ✅ Lengkap | CRUD + workflow |
| F07 | Laporan Cuaca | `cuaca/` | ✅ Lengkap | CRUD + workflow |
| F08 | Laporan Alat & Sarana | `alat_sarana/` | ✅ Lengkap | CRUD + workflow |
| F09 | Laporan Terpadu | `laporan/` | ✅ Lengkap | Gabungan semua laporan, filter, search |
| F10 | Dashboard Statistik | `home/` | ✅ Lengkap | KPI aktif/draf/ditolak per tipe |
| F11 | Notifikasi In-App | `notifications/` | ✅ Lengkap | Polling 60s, FCM push, mark read |
| F12 | Profil & Ubah Password | `profile/` | ✅ Lengkap | Force change password didukung |
| F13 | Ekspor PDF | `core/pdf_export_service.dart` | ✅ Lengkap | Preview, simpan, bagikan |
| F14 | Peta Mini Koordinat | `core/widgets/mini_map_preview.dart` | ✅ Lengkap | OSM tile, tap → Google Maps |
| F15 | Upload Foto | `core/widgets/foto_picker.dart` | ✅ Lengkap | Validasi tipe + ukuran 10MB |
| F16 | GPS Auto-fill | Form screens | ✅ Lengkap | `geolocator` package |

### 1.2 Fitur Pendukung

| No | Fitur | Implementasi | Nilai Tambah |
|----|-------|-------------|-------------|
| P01 | Offline Draft Queue | SQLite `local_drafts` | Laporan tersimpan saat tanpa internet |
| P02 | Auto-Sync saat Online | `app.dart` + `sync_service.dart` | Sinkronisasi otomatis saat koneksi pulih |
| P03 | Network Diagnostics | `network_diagnostic.dart` | DNS→TCP→HTTP, pesan error actionable |
| P04 | Skeleton Loading | `core/widgets/skeleton_card.dart` | Shimmer placeholder saat fetch |
| P05 | Status Timeline | `core/widgets/status_timeline.dart` | Visualisasi alur laporan |
| P06 | Status Badge | `core/widgets/status_badge.dart` | Warna semantik per status |
| P07 | Connection Banner | `core/widgets/connection_error_banner.dart` | Panduan troubleshoot per jenis error |
| P08 | Dark Mode | `core/theme.dart` | ThemeMode.system, Material 3 |
| P09 | Responsive Layout | `AppBreakpoints` di theme.dart | 1/2/3 kolom grid adaptif |
| P10 | Bottom Navigation | `home_screen.dart` | Route-aware 4 tab |
| P11 | Lokalisasi Bahasa ID | `app.dart` | `flutter_localizations` |
| P12 | FCM Push Notification | `core/fcm/fcm_service.dart` | Entity whitelist, graceful degradasi |
| P13 | Predictive Back | `theme.dart` pageTransitions | Android 13+ gesture predictive |
| P14 | Konfirmasi Submit | Form screens | Dialog ringkasan sebelum kirim |

---

## 2. Analisis Arsitektur

### 2.1 Pola Desain yang Digunakan

```
┌─────────────────────────────────────────────────────────────┐
│                    JAGAPADI Android                          │
├──────────────┬───────────────────┬──────────────────────────┤
│   Routing    │  State Management │      Data Layer          │
│  go_router   │  Provider pattern │  ApiClient (Dio)         │
│  GoRouter    │  ChangeNotifier   │  LocalDb (sqflite)       │
│  Auth guard  │  MultiProvider    │  SecureStorage           │
│  Safe parse  │  Singleton API    │  SyncService             │
└──────────────┴───────────────────┴──────────────────────────┘
```

**Kekuatan Arsitektur:**
- `ApiClient` dibuat sebagai singleton di `_JagapadiAppState` — tidak ada memory leak
- Semua Provider terdaftar di root `MultiProvider` — tidak ada `ProviderNotFoundException`
- `int.tryParse()` di router menggantikan `int.parse()` yang rawan crash
- `WidgetsBindingObserver` di `app.dart` untuk sync saat resume dari background

**Keterbatasan yang Tersisa:**
- Tidak ada BLoC/Riverpod — Provider murni berarti state sering di-notify untuk perubahan kecil
- Tidak ada repository layer: provider langsung memanggil `ApiClient`

### 2.2 Dependency Graph

```
main.dart
  └─ JagapadiApp (StatefulWidget)
       ├─ ApiClient (singleton)
       ├─ ConnectivityService (singleton)
       ├─ AuthProvider (ApiClient sendiri)
       ├─ NotificationProvider
       ├─ DashboardProvider
       ├─ LaporanTerpaduProvider
       ├─ LaporanHamaProvider
       ├─ LaporanIrigasiProvider
       ├─ LaporanPupukProvider
       ├─ LaporanPanenProvider
       ├─ LaporanCuacaProvider
       ├─ LaporanAlatSaranaProvider
       └─ WilayahProvider
```

### 2.3 Alur Data

```
User Action → Screen → Provider → ApiClient → Backend API
                                      ↓
                                  LocalDb (offline)
                                      ↓
                              SyncService (online trigger)
```

---

## 3. Evaluasi Kinerja

### 3.1 Waktu Muat (Estimasi Berdasarkan Kode)

| Operasi | Estimasi | Faktor |
|---------|----------|--------|
| Cold start | 2–4 detik | Flutter engine init + SecureStorage async + FCM init |
| Warm start | < 1 detik | Widget tree cache + token cache |
| Login API | 1–3 detik | Jaringan + JWT generate server |
| Load list laporan | 0.5–2 detik | Pagination 20 item, network dependent |
| Sync draf offline | 1–5 detik | Per item: 1 POST + opsional 1 upload foto |

**Catatan konfigurasi timeout:**
- `connectTimeout = 20.000ms` — sesuai untuk 4G
- `receiveTimeout = 30.000ms` — cukup untuk respons JSON
- `uploadTimeout = 120.000ms` — memadai untuk foto 10MB di 3G

### 3.2 Penggunaan RAM

**Sumber konsumsi utama:**
| Komponen | Estimasi RAM |
|----------|-------------|
| Flutter engine | ~35–50 MB |
| 9 ChangeNotifier aktif | ~5–10 MB |
| Dio + interceptors | ~3–5 MB |
| SQLite (sqflite) | ~2–5 MB |
| firebase_messaging | ~5–8 MB |
| flutter_map tiles (cache) | ~10–20 MB |
| **Total estimasi** | **~60–100 MB** |

**Penilaian**: Di bawah batas 200MB recommended untuk perangkat mid-range Android 8+.

### 3.3 Penggunaan CPU & Baterai

**Operasi berulang yang berpotensi drain baterai:**

| Operasi | Interval | Dampak |
|---------|----------|--------|
| Polling notifikasi | 60 detik | Rendah — hanya 1 HTTP GET kecil |
| Connectivity check | Event-driven | Minimal |
| `didChangeAppLifecycleState` sync | Setiap resume | Rendah — 1 operasi async |
| Geolocator | On-demand | Sedang — GPS drain saat aktif |

**Masalah yang terdeteksi:**
- `NotificationProvider._pollTimer` tidak pause saat app di background (AppLifecycleState.paused)
- Di background, timer tetap berjalan di Dart isolate — boros ~4 req/menit

### 3.4 Penggunaan Penyimpanan

| Data | Lokasi | Enkripsi |
|------|--------|----------|
| JWT token | flutter_secure_storage | ✅ Keystore |
| User JSON | flutter_secure_storage | ✅ Keystore |
| Offline credentials | flutter_secure_storage | ✅ Keystore + SHA-256 |
| Draft lokal | sqflite (jagapadi_drafts.db) | ❌ Tidak terenkripsi |
| Foto upload | temp path | ❌ Tidak terenkripsi |
| PDF ekspor | JAGAPADI_Export/ | ❌ Tidak terenkripsi |

---

## 4. Analisis Keamanan Data

### 4.1 Implementasi Keamanan yang Sudah Baik ✅

| Aspek | Implementasi |
|-------|-------------|
| Token storage | `flutter_secure_storage` — Android Keystore API |
| HTTP cleartext | `usesCleartextTraffic="false"` di AndroidManifest |
| Network security config | Hanya izinkan HTTP ke `10.0.2.2`, `localhost`, `192.168.10.5` |
| Offline credential | PBKDF2/SHA-256 hashing via `crypto` package |
| FCM entity whitelist | Validasi `entity` dari payload FCM sebelum navigasi |
| Route ID parsing | `int.tryParse()` — tidak bisa dieksploitasi dengan injection |
| Validasi foto | Magic bytes + MIME + ukuran sebelum upload |
| CSRF | Session + CSRF token di web; JWT bearer di mobile |
| ProGuard | `minifyEnabled true` di release build |
| Logging | `kDebugMode` guard — tidak ada log di production |

### 4.2 Kerentanan yang Perlu Perhatian

| ID | Kerentanan | Tingkat Risiko | Detail |
|----|-----------|----------------|--------|
| SEC-01 | SQLite draft tidak terenkripsi | 🟡 Sedang | `jagapadi_drafts.db` menyimpan payload laporan (nama OPT, koordinat GPS, catatan) dalam plaintext. Dapat dibaca di perangkat yang sudah di-root. |
| SEC-02 | Tidak ada certificate pinning | 🟡 Sedang | Aplikasi menggunakan system CA store. Di jaringan korporasi/publik, MITM via corporate proxy bisa intercept traffic. Krusial untuk data pertanian pemerintah. |
| SEC-03 | Foto di temp path tidak terhapus setelah upload | 🟢 Rendah | File foto yang diambil kamera disimpan di temp path OS. Jika app crash sebelum upload, file tersisa di storage. |
| SEC-04 | IP `192.168.10.5` hardcoded di network_security_config | 🟡 Sedang | IP LAN development spesifik hardcoded di production config. Di environment lain, ini menjadi entri cleartext yang tidak diperlukan. |
| SEC-05 | PDF ekspor tidak terproteksi | 🟢 Rendah | Dokumen PDF yang disimpan di `JAGAPADI_Export/` accessible oleh app lain di device yang sama. |

### 4.3 Izin Akses Android

```xml
<uses-permission android:name="android.permission.INTERNET"/>           ✅ Wajib
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/> ✅ Wajib
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/> ✅ Wajib (GPS)
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION"/> ✅ Redundan tapi aman
<uses-permission android:name="android.permission.CAMERA"/>             ✅ Wajib (foto)
```

**Penilaian**: Semua izin yang diminta proporsional dan memiliki justifikasi fungsional. Tidak ada izin berlebihan (tidak ada READ_CONTACTS, RECORD_AUDIO, atau izin sensitif lain).

**Fitur hardware bersifat opsional:**
```xml
<uses-feature android:name="android.hardware.camera" android:required="false"/>    ✅
<uses-feature android:name="android.hardware.location.gps" android:required="false"/> ✅
```

Ini memastikan aplikasi dapat diinstall di device tanpa kamera/GPS (misalnya tablet atau HP entry-level).

### 4.4 Kepatuhan Regulasi Privasi

| Aspek | Status | Catatan |
|-------|--------|---------|
| Data minimalism | ✅ | Hanya mengumpulkan data yang diperlukan untuk pelaporan |
| Data residency | ✅ | Data tersimpan di server Pemkab Jember (on-premise) |
| Enkripsi transmisi | ✅ Production | HTTPS wajib di production, `usesCleartextTraffic=false` |
| Hak hapus data | ⚠️ Perlu verifikasi | Tidak ada mekanisme request hapus akun di mobile |
| Transparansi izin | ✅ | `required="false"` untuk GPS dan kamera |

---

## 5. Kompatibilitas Android

### 5.1 Target Konfigurasi

| Parameter | Nilai | Implikasi |
|-----------|-------|-----------|
| `minSdk` | 24 (Android 7.0) | Mendukung 98%+ perangkat aktif di Indonesia |
| `targetSdk` | 35 (Android 15) | Mengikuti kebijakan Google Play 2024/2025 |
| `compileSdk` | 36 | Akses API terbaru |
| Flutter SDK | ^3.0.0 | Dart null safety, Material 3 |

### 5.2 Matriks Kompatibilitas Fitur

| Fitur | Android 7 (API 24) | Android 8 (API 26) | Android 11 (API 30) | Android 14 (API 34) |
|-------|--------------------|--------------------|---------------------|---------------------|
| Login JWT | ✅ | ✅ | ✅ | ✅ |
| GPS | ✅ | ✅ | ⚠️ Perlu runtime permission | ✅ |
| Kamera | ✅ | ✅ | ✅ | ⚠️ Media permissions baru |
| Push Notification | ✅ | ✅ | ✅ | ⚠️ POST_NOTIFICATIONS wajib |
| flutter_secure_storage | ✅ | ✅ | ✅ | ✅ |
| Predictive Back | ❌ (fallback) | ❌ (fallback) | ❌ (fallback) | ✅ |
| Network Security Config | ✅ | ✅ | ✅ | ✅ |
| SQLite sqflite | ✅ | ✅ | ✅ | ✅ |

**Masalah yang teridentifikasi:**
- Android 13+ (API 33+): `POST_NOTIFICATIONS` permission belum diminta di runtime → notifikasi push tidak tampil
- Android 14 (API 34): Foto access perlu `READ_MEDIA_IMAGES` jika memilih dari galeri

### 5.3 Variasi Layar

| Ukuran Layar | Lebar (dp) | Kolom Grid | Status |
|-------------|------------|------------|--------|
| HP Kecil (5") | ~360dp | 1 | ✅ |
| HP Standar (6") | ~393–412dp | 1 | ✅ |
| HP Besar (6.7") | ~480dp | 1–2 | ✅ |
| Tablet 7" | ~600dp | 2 | ✅ |
| Tablet 10" | ~800dp | 2–3 | ✅ |
| Desktop/Large | ≥960dp | 3 | ✅ |

`AppBreakpoints.columnsForWidth()` memastikan grid menu responsif.

---

## 6. Evaluasi UI/UX

### 6.1 Kekuatan UI/UX

| Aspek | Penilaian | Detail |
|-------|-----------|--------|
| **Design System** | ⭐⭐⭐⭐⭐ | Material 3 lengkap — ColorScheme, token spacing, radius, tipografi konsisten |
| **Aksesibilitas** | ⭐⭐⭐⭐ | Semantics label di semua elemen interaktif, touch target ≥48dp |
| **Dark Mode** | ⭐⭐⭐⭐⭐ | Skema warna penuh, `ThemeMode.system` |
| **Responsivitas** | ⭐⭐⭐⭐ | Grid adaptif, ConstrainedBox di form/profil |
| **Feedback Loading** | ⭐⭐⭐⭐ | Skeleton shimmer, loading indicator terintegrasi |
| **Error State** | ⭐⭐⭐⭐⭐ | Pesan kontekstual per jenis error, tombol retry |
| **Offline Indicator** | ⭐⭐⭐⭐⭐ | Badge "Mode Offline" di hero header, banner di form |
| **Konfirmasi Destruktif** | ⭐⭐⭐⭐⭐ | Dialog konfirmasi logout, hapus draf, submit laporan |

### 6.2 Alur Pengguna Utama

**Alur Buat & Kirim Laporan:**
```
Home → Menu Hama/OPT → Buat Laporan → Isi Form → [Simpan Draf | Kirim]
                                                        ↓                ↓
                                                  Tersimpan lokal    Dialog konfirmasi
                                                  (online: sync)    → Kirim ke Admin
```

**Alur Offline:**
```
Login offline → Home (mode offline badge) → Buat Laporan → Simpan Draf
     ↓                                                         ↓
Kredensial terverifikasi                              Tersimpan SQLite
dari local hash                                            ↓
                                                   Koneksi pulih →
                                                   Auto-sync otomatis
```

### 6.3 Hambatan UX yang Ditemukan

| ID | Hambatan | Dampak | Lokasi |
|----|---------|--------|--------|
| UX-01 | Polling notifikasi tidak berhenti di background | 🟡 Konsumsi baterai | `notification_provider.dart` |
| UX-02 | Form laporan tidak menyimpan state saat navigasi balik | 🟠 Data hilang | Semua form screen |
| UX-03 | `WilayahPicker` melakukan fetch ulang setiap kali dibuka | 🟡 Lambat di jaringan lambat | `wilayah_provider.dart` |
| UX-04 | Tidak ada indikator progress upload foto | 🟡 User tidak tahu progres | Upload foto form |
| UX-05 | Scroll infinit tanpa indikator "akhir data" | 🟢 Kebingungan pengguna | List screen semua laporan |
| UX-06 | `POST_NOTIFICATIONS` permission belum di-request | 🔴 FCM tidak tampil di Android 13+ | `main.dart` / `fcm_service.dart` |
| UX-07 | Tidak ada onboarding/walkthrough untuk pengguna baru | 🟡 Kurva belajar | App pertama kali dibuka |

---

## 7. Mode Offline

### 7.1 Arsitektur Offline yang Diimplementasikan

```
┌──────────────────────────────────────────────────────────────┐
│                    OFFLINE ARCHITECTURE                       │
├─────────────────────────────┬────────────────────────────────┤
│    Lapisan Persistensi      │    Mekanisme Sinkronisasi      │
│                             │                                │
│  flutter_secure_storage     │  ConnectivityService           │
│  └─ JWT token               │  └─ Stream perubahan koneksi   │
│  └─ User JSON               │  └─ Auto-trigger sync          │
│  └─ Offline credential hash │                                │
│                             │  SyncService                   │
│  SQLite (sqflite)           │  └─ 6 endpoint mapping         │
│  └─ local_drafts table      │  └─ Guard duplikasi sinkronisasi│
│  └─ sync_state tracking     │  └─ Retry state machine        │
│  └─ photo_synced flag       │  └─ 422 validation detection   │
│  └─ user_id isolation       │                                │
│  └─ retry_count             │  WidgetsBindingObserver        │
│                             │  └─ Sync on app resume         │
└─────────────────────────────┴────────────────────────────────┘
```

### 7.2 Fitur Offline yang Berfungsi

| Fitur | Online | Offline | Sync Otomatis |
|-------|--------|---------|---------------|
| Login | JWT API | Hash lokal | N/A |
| Buat Draf Laporan | Server + SQLite | SQLite only | ✅ Saat online |
| Edit Draf | Server + SQLite | SQLite only | ✅ |
| Lihat Draf Tersimpan | ✅ | ✅ (SQLite) | N/A |
| Submit Laporan | ✅ | ❌ Ditolak dengan pesan jelas | N/A |
| Lihat Riwayat Laporan | ✅ | ❌ Tidak ada cache | ❌ |
| Notifikasi | ✅ Polling | ❌ Tidak tersedia | N/A |

### 7.3 Kelemahan Mode Offline

| ID | Kelemahan | Dampak Operasional |
|----|-----------|-------------------|
| OFF-01 | Tidak ada cache list laporan | Petugas tidak bisa lihat riwayat laporan saat offline |
| OFF-02 | WilayahPicker butuh jaringan | Form tidak bisa diisi wilayah baru saat offline |
| OFF-03 | OPT picker butuh jaringan | Form hama tidak bisa dipilih OPT saat offline |
| OFF-04 | Draft conflict resolution tidak ada | Jika laporan diedit online dan offline bersamaan, yang terakhir menang tanpa peringatan |

---

## 8. Temuan Kritis & Status Perbaikan

### 8.1 Bug Kritis (Sudah Diperbaiki dalam Iterasi Sebelumnya)

| ID | Bug | Perbaikan |
|----|-----|-----------|
| BUG-C1 | `ProviderNotFoundException` crash semua screen | ✅ Semua provider didaftarkan di root `MultiProvider` |
| BUG-C2 | `int.parse()` crash dari deep link FCM | ✅ Diganti `int.tryParse()` + `_InvalidRouteScreen` |
| BUG-C3 | Memory leak `TextEditingController` di `DateField` | ✅ Dikonversi ke `StatefulWidget` dengan `_displayCtrl.dispose()` |
| BUG-C4 | Duplikasi draf lokal setiap "Simpan Draf" | ✅ `_localDraftId` tracking + `updateDraft` vs `insertDraft` |
| BUG-C5 | Race condition submit: `targetId` null | ✅ `_saveDraft()` return `int?` server ID |
| BUG-C6 | `ApiClient` baru setiap `build()` | ✅ Field di `_JagapadiAppState` |
| BUG-C7 | `SyncService` endpoint salah untuk pupuk/panen | ✅ `_kEndpointMap` explicit per tipe |
| BUG-C8 | FCM entity tidak divalidasi | ✅ Whitelist set `_allowedEntities` |

### 8.2 Bug yang Masih Ada

| ID | Bug | Tingkat | File | Perbaikan yang Direkomendasikan |
|----|-----|---------|------|--------------------------------|
| BUG-M1 | `POST_NOTIFICATIONS` tidak diminta di Android 13+ | 🔴 Kritis | `main.dart` | Tambah runtime permission request sebelum FCM init |
| BUG-M2 | `NotificationProvider` polling tidak pause di background | 🟠 Sedang | `notification_provider.dart` | Implement `AppLifecycleObserver` di provider |
| BUG-M3 | WilayahProvider tidak di-invalidate saat logout | 🟡 Ringan | `wilayah_provider.dart` | Clear cache di `clearAll()` atau listen AuthProvider |
| BUG-M4 | `LaporanHamaProvider.loadOptList()` cache tidak di-invalidate | 🟡 Ringan | `laporan_hama_provider.dart` | Clear `_optList` di logout |
| BUG-M5 | `healthUrl` tidak menggunakan path yang benar | 🟡 Ringan | `config.dart` | `healthUrl = '$baseUrl/health'` sudah benar tapi perlu validasi format |
| BUG-M6 | `NotificationProvider` dua sumber `_unreadCount` | 🟡 Ringan | `notification_provider.dart` | Unifikasi ke satu sumber: selalu dari API |

---

## 9. Rekomendasi Terukur

### 9.1 Prioritas Tinggi (Implementasi < 1 Minggu)

#### R-01: Tambah POST_NOTIFICATIONS Permission (Android 13+)

**File**: `mobile/lib/main.dart`  
**Masalah**: FCM push tidak tampil di Android 13+  
**Implementasi**:
```dart
// Di main.dart, sebelum FcmService.init()
import 'package:permission_handler/permission_handler.dart';

// Sudah ada di pubspec.yaml: permission_handler: ^11.3.1
if (Platform.isAndroid) {
  final sdk = int.tryParse(
    await DeviceInfoPlugin().androidInfo.then((i) => i.version.sdkInt.toString())
  ) ?? 0;
  if (sdk >= 33) {
    await Permission.notification.request();
  }
}
```

---

#### R-02: Pause Polling Notifikasi saat Background

**File**: `mobile/lib/features/notifications/providers/notification_provider.dart`  
**Masalah**: Timer 60s berjalan terus meski app di background — boros baterai  
**Implementasi**:
```dart
class NotificationProvider extends ChangeNotifier with WidgetsBindingObserver {
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused) {
      stopPolling();
    } else if (state == AppLifecycleState.resumed) {
      startPolling();
    }
  }
  
  // Tambahkan di constructor:
  // WidgetsBinding.instance.addObserver(this);
  
  // Tambahkan di dispose():
  // WidgetsBinding.instance.removeObserver(this);
}
```

---

#### R-03: Hapus IP Development dari Production Config

**File**: `mobile/android/app/src/main/res/xml/network_security_config.xml`  
**Masalah**: IP `192.168.10.5` hardcoded — tidak relevan di production  
**Solusi**: Gunakan per-buildType config atau flavor:
- `src/main/` → hanya `base-config` HTTPS
- `src/debug/` → tambah cleartext development hosts

---

### 9.2 Prioritas Sedang (Implementasi 1–2 Minggu)

#### R-04: Enkripsi SQLite Database

**Package yang direkomendasikan**: `sqflite_sqlcipher` atau `drift` dengan encryption  
**Alasan**: Data laporan pertanian (koordinat GPS, kondisi sawah, identitas petugas) bersifat sensitif dan wajib dilindungi di perangkat yang mungkin di-root.

```dart
// Contoh implementasi dengan sqflite_sqlcipher:
final db = await openEncryptedDatabase(
  path,
  password: await _getDatabaseKey(), // dari Keystore via flutter_secure_storage
  version: 2,
);
```

---

#### R-05: Cache Wilayah & OPT Offline

**Masalah**: Form laporan tidak bisa diisi lengkap saat offline karena picker butuh network  
**Solusi**: Tambahkan tabel `cache_wilayah` dan `cache_opt` di SQLite:
```sql
CREATE TABLE cache_opt (
  id INTEGER PRIMARY KEY,
  nama_opt TEXT NOT NULL,
  jenis TEXT NOT NULL,
  aktif INTEGER NOT NULL DEFAULT 1,
  cached_at TEXT NOT NULL
);

CREATE TABLE cache_wilayah (
  id INTEGER PRIMARY KEY,
  level TEXT NOT NULL, -- 'kabupaten'|'kecamatan'|'desa'
  parent_id INTEGER,
  nama TEXT NOT NULL,
  cached_at TEXT NOT NULL
);
```

---

#### R-06: Certificate Pinning untuk Production

**Risiko tanpa pinning**: MITM attack via corporate proxy / rogue CA  
**Implementasi**:
```dart
// Di ApiClient, tambahkan pinning untuk production
if (!kDebugMode) {
  (_dio.httpClientAdapter as IOHttpClientAdapter).createHttpClient = () {
    final client = HttpClient();
    client.badCertificateCallback = (cert, host, port) {
      // Verifikasi SHA-256 fingerprint sertifikat server
      final fingerprint = sha256.convert(cert.der).toString();
      return _trustedFingerprints.contains(fingerprint);
    };
    return client;
  };
}
```

---

#### R-07: Cache List Laporan untuk Offline Viewing

**File baru**: `core/cache_service.dart`  
**Solusi**: Simpan response list laporan terakhir ke `local_laporan_cache` di SQLite dengan TTL 24 jam. Saat offline, tampilkan cache dengan banner "Menampilkan data terakhir diperbarui pada [timestamp]".

---

### 9.3 Prioritas Rendah (Backlog)

| ID | Rekomendasi | Estimasi |
|----|-------------|---------|
| R-08 | Onboarding screen untuk pengguna baru | 3 hari |
| R-09 | Indikator progress upload foto (linear progress indicator) | 1 hari |
| R-10 | "End of list" indicator di scroll infinit | 0.5 hari |
| R-11 | Invalidate OPT cache saat logout | 1 jam |
| R-12 | Unifikasi sumber `_unreadCount` di NotificationProvider | 2 jam |
| R-13 | Hapus file foto temp setelah upload berhasil | 1 jam |
| R-14 | Proteksi PDF dengan password opsional | 2 hari |
| R-15 | Deep link langsung ke detail laporan dari notifikasi web | 1 hari |

---

## Ringkasan Eksekutif

### Penilaian Keseluruhan

| Dimensi | Nilai | Keterangan |
|---------|-------|------------|
| **Fungsional** | 9/10 | 16 fitur utama + 14 fitur pendukung, semua alur kerja utama berfungsi |
| **Arsitektur** | 8/10 | Clean separation, provider terdaftar benar, singleton ApiClient |
| **Keamanan** | 7/10 | JWT Keystore baik, perlu enkripsi SQLite + certificate pinning |
| **Performa** | 8/10 | RAM < 100MB, timeout wajar, issue kecil di background polling |
| **Kompatibilitas** | 8/10 | Android 7–15 didukung, issue POST_NOTIFICATIONS Android 13+ |
| **UI/UX** | 9/10 | Material 3 konsisten, dark mode, aksesibilitas baik, responsive |
| **Mode Offline** | 7/10 | Draft queue solid, perlu cache list + wilayah/OPT offline |

### Status Kesiapan Production

```
✅ Sudah production-ready untuk fungsi inti pelaporan
⚠️  Perlu perbaikan sebelum submit Play Store:
    - POST_NOTIFICATIONS permission (R-01) — wajib Android 13+
    - Hapus IP development dari network_security_config (R-03)
🔄 Direkomendasikan dalam sprint berikutnya:
    - Enkripsi SQLite (R-04)
    - Cache wilayah/OPT offline (R-05)
    - Pause polling background (R-02)
```

---

*Dokumen ini berdasarkan analisis source code langsung pada versi 1.1.1+4, Agustus 2026.*
