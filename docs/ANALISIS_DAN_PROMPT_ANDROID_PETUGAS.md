# Analisis Proyek & Rangkaian Prompt Pengembangan Android JAGAPADI — Role Petugas

> **Tanggal Analisis**: Agustus 2026  
> **Versi Proyek**: v1.0.0 Production Ready  
> **Tujuan Dokumen**: Analisis menyeluruh + rangkaian prompt terstruktur siap eksekusi oleh agen AI

---

## BAGIAN A — ANALISIS PROYEK JAGAPADI

---

### A.1 Gambaran Umum Proyek

JAGAPADI (Jember Agrikultur Gapai Prestasi Digital) adalah sistem pelaporan pertanian digital
untuk Kabupaten Jember, Jawa Timur. Sistem mencakup dua jenis laporan:

| Jenis Laporan | Domain |
|---|---|
| Hama/OPT (Organisme Pengganggu Tanaman) | Serangan hama, penyakit tanaman, gulma |
| Kondisi Irigasi | Kerusakan saluran, debit air, daerah irigasi |

**Status Proyek Saat Ini**: v1.0.0 Production Ready — seluruh modul MVP telah
diimplementasikan termasuk backend PHP, web admin, mobile Flutter Android, FCM push
notification, dan CI/CD deployment.

---

### A.2 Arsitektur & Stack Teknologi

```
┌─────────────────────────────────────────────────────────────┐
│                   ARSITEKTUR JAGAPADI                       │
├─────────────────┬───────────────────┬───────────────────────┤
│   MOBILE (Flutter) │  BACKEND (PHP)  │    WEB ADMIN (PHP)    │
│   Android JWT   │  PHP 8.2 MVC      │  Session + CSRF       │
│   Provider      │  PDO + MariaDB    │  Server-rendered      │
│   go_router     │  REST API /api/v1 │                       │
│   FCM Push      │  JWT Auth         │                       │
│   Dio HTTP      │  RBAC (2 roles)   │                       │
└─────────────────┴───────────────────┴───────────────────────┘
                          │
                   MariaDB/MySQL
                   utf8mb4, InnoDB
                   13 tabel aktif
```

**Stack Mobile Flutter (yang sudah ada):**

| Komponen | Library | Versi |
|---|---|---|
| HTTP Client | dio | ^5.4.0 |
| Auth Storage | flutter_secure_storage | ^9.0.0 |
| State Management | provider | ^6.1.1 |
| Routing | go_router | ^14.0.0 |
| GPS | geolocator | ^11.0.0 |
| Kamera | image_picker | ^1.0.7 |
| Tanggal/Format | intl | ^0.19.0 |
| Font | google_fonts | ^6.1.0 |
| Push Notif | firebase_messaging | ^15.1.3 |


---

### A.3 Struktur Modul Mobile Flutter yang Sudah Ada

```
mobile/lib/
├── core/                        # Infrastruktur bersama
│   ├── config.dart              # Base URL, timeout, interval polling
│   ├── api_client.dart          # Dio + JWT interceptor + auto-refresh
│   ├── secure_storage.dart      # JWT token & user data storage
│   ├── router.dart              # go_router dengan auth guard
│   ├── theme.dart               # Material 3, warna, tipografi
│   └── fcm/fcm_service.dart     # FCM push notification service
└── features/
    ├── auth/                    # Login, User model, AuthProvider
    │   ├── models/user.dart
    │   ├── providers/auth_provider.dart
    │   └── screens/login_screen.dart
    ├── hama/                    # Laporan Hama/OPT — LENGKAP ✅
    │   ├── models/laporan_hama.dart
    │   ├── providers/laporan_hama_provider.dart
    │   └── screens/ (list, detail, form)
    ├── irigasi/                 # Laporan Irigasi — LENGKAP ✅
    │   ├── models/laporan_irigasi.dart
    │   ├── providers/laporan_irigasi_provider.dart
    │   └── screens/ (list, detail, form)
    ├── notifications/           # Notifikasi in-app + FCM — LENGKAP ✅
    ├── profile/                 # Profil + Ubah Password — LENGKAP ✅
    ├── home/                    # Home Screen dengan card menu
    └── wilayah/                 # Cascading picker (Kabupaten→Kecamatan→Desa)
```

**Fitur yang sudah berfungsi (terverifikasi dari kode):**

| Fitur | Status | Catatan |
|---|---|---|
| Login JWT dengan must_change_password flow | ✅ Selesai | Auth guard via go_router |
| Auto-refresh JWT token (interceptor Dio) | ✅ Selesai | Retry 1x sebelum logout |
| Laporan Hama CRUD + Draft + Submit | ✅ Selesai | include OPT picker |
| Laporan Irigasi CRUD + Draft + Submit | ✅ Selesai | |
| Upload Foto dari Kamera | ✅ Selesai | Max 10MB, multipart |
| GPS Auto-fill Koordinat | ✅ Selesai | Geolocator |
| Wilayah Cascading Picker | ✅ Selesai | Kab → Kec → Desa |
| Verifikasi/Tolak (Admin) | ✅ Selesai | Hanya tampil jika isAdmin |
| Resubmit laporan Ditolak (Petugas) | ✅ Selesai | |
| Notifikasi in-app + badge | ✅ Selesai | Polling 60 detik |
| FCM Push Notification | ✅ Selesai | Graceful degradasi |
| 422 field error mapping ke form | ✅ Selesai | |
| 429 rate-limit handling | ✅ Selesai | |
| Profil & Change Password | ✅ Selesai | |


---

### A.4 Database Schema — Tabel Relevan untuk Role Petugas

Dari 13 tabel aktif, tabel yang langsung relevan untuk role petugas:

| Tabel | Relevansi |
|---|---|
| `users` | Akun petugas (role='petugas', must_change_password) |
| `laporan_hama` | Laporan OPT milik petugas (ownership enforced di query) |
| `laporan_irigasi` | Laporan irigasi milik petugas |
| `master_opt` | Master OPT untuk picker (hanya aktif=1) |
| `master_kabupaten` / `master_kecamatan` / `master_desa` | Wilayah untuk picker |
| `notifications` | Notifikasi petugas (verified, rejected, archived events) |
| `device_tokens` | FCM token petugas untuk push |
| `activity_log` | Log aktivitas petugas (audit trail) |

**Tipe notifikasi yang diterima petugas:**

| Tipe | Pemicu |
|---|---|
| `laporan_verified` | Admin memverifikasi laporan petugas |
| `laporan_rejected` | Admin menolak laporan petugas |
| `laporan_archived` | Admin mengarsipkan laporan petugas |

---

### A.5 Alur Kerja Role Petugas — State Machine Laporan

```
[OFFLINE]                    [ONLINE]
   │                             │
   ▼                             ▼
Isi Form  ──online──►  POST /laporan-hama (action=draft)
   │                        │
   │                        ▼ status = "Draf"
   │                   [bisa edit/hapus/foto]
   │                        │
   │                   POST /laporan-hama/{id}/submit
   │                        │
   │                        ▼ status = "Submitted"
   │                   nomor_laporan = "LH-YYYYMMDD-XXXX"
   │                        │
   │              ┌──────────┴──────────┐
   │              ▼                     ▼
   │         Admin Verifikasi      Admin Tolak
   │         status="Diverifikasi" status="Ditolak"
   │              │                     │
   │              ▼                     ▼
   │         [notif push]         [notif push]
   │                              Petugas edit
   │                              + resubmit
   │                                   │
   │                                   ▼ status="Submitted"
   │                              (nomor tetap sama)
   │
   ▼
[Offline mode: simpan lokal,
 sync ke server saat online]
```

**Constraint role petugas di API:**
- `GET /laporan-hama` → hanya mengembalikan data `user_id = current_user`
- `PUT /laporan-hama/{id}` → hanya jika status `Draf`, owner only
- `DELETE /laporan-hama/{id}` → hanya jika status `Draf`, owner only
- `POST /laporan-hama/{id}/submit` → hanya Draf → Submitted, owner only
- `POST /laporan-hama/{id}/resubmit` → hanya Ditolak → Submitted, owner only
- Aksi verifikasi/tolak/arsip → 403 Forbidden jika bukan admin


---

### A.6 Analisis Kebutuhan Fungsional Role Petugas

Berdasarkan analisis kode, API contract, dan dokumentasi panduan pengguna, berikut kebutuhan
fungsional spesifik untuk role petugas di aplikasi Android:

#### A.6.1 Kebutuhan yang SUDAH Terpenuhi

| No | Kebutuhan | Implementasi |
|---|---|---|
| F-01 | Login dengan JWT + must_change_password flow | `AuthProvider.login()` + `LoginScreen` |
| F-02 | Buat laporan hama sebagai Draf | `LaporanHamaProvider.save(action='draft')` |
| F-03 | Edit dan hapus Draf laporan hama | `PUT /laporan-hama/{id}`, `DELETE` |
| F-04 | Submit laporan hama ke admin | `POST /laporan-hama/{id}/submit` |
| F-05 | Buat, edit, submit laporan irigasi | `IrigasiFormScreen` + provider |
| F-06 | Upload foto dari kamera (Draf/Ditolak only) | `api_client.uploadFoto()` |
| F-07 | GPS auto-fill koordinat | `Geolocator.getCurrentPosition()` |
| F-08 | Wilayah cascading (Kab → Kec → Desa) | `WilayahPicker` widget |
| F-09 | Kirim ulang (resubmit) laporan Ditolak | `POST /laporan-hama/{id}/resubmit` |
| F-10 | Lihat notifikasi in-app + badge | `NotificationProvider`, polling 60s |
| F-11 | FCM push notification | `FcmService` |
| F-12 | Profil akun + ubah password | `ProfileScreen` + `changePassword()` |
| F-13 | Validasi field error mapping ke form (422) | `_applyFieldErrors()` di form screens |
| F-14 | Auto logout saat token expired (401) | Interceptor Dio + `onUnauthorized` |

#### A.6.2 Kebutuhan yang BELUM Terpenuhi / Perlu Peningkatan

| No | Kebutuhan | Gap | Prioritas |
|---|---|---|---|
| F-15 | **Offline-first draft** (simpan lokal, sync server) | Saat ini langsung ke server; tidak ada local queue | 🔴 Tinggi |
| F-16 | **Indikator status koneksi** di UI | Tidak ada banner offline/online | 🔴 Tinggi |
| F-17 | **Dashboard ringkasan petugas** (statistik laporan milik sendiri) | HomeScreen hanya card menu statis | 🟡 Sedang |
| F-18 | **Filter & search** di list laporan (status, tanggal, wilayah) | List hanya paginasi tanpa filter | 🟡 Sedang |
| F-19 | **Pull-to-refresh + infinite scroll** yang konsisten | Ada sebagian, belum konsisten di irigasi | 🟡 Sedang |
| F-20 | **Preview foto sebelum upload** | Tombol ada tapi preview tidak lengkap | 🟡 Sedang |
| F-21 | **Tanggal picker UI** (DatePicker widget) | Input teks manual (rentan format salah) | 🟡 Sedang |
| F-22 | **Konfirmasi sebelum submit** laporan (dialog ringkasan) | Langsung submit tanpa preview ringkasan | 🟡 Sedang |
| F-23 | **Lihat peta sebaran laporan sendiri** | Tidak ada screen peta di mobile | 🟢 Rendah |
| F-24 | **Export laporan sendiri** dari mobile | Tidak ada fitur download di mobile | 🟢 Rendah |
| F-25 | **Riwayat aktivitas** (log aksi petugas) | Tidak ada riwayat di mobile | 🟢 Rendah |
| F-26 | **Deep link dari notifikasi** ke detail laporan | Sebagian ada, perlu validasi router | 🟡 Sedang |
| F-27 | **Penanganan error jaringan** lebih informatif | Pesan generic, kurang kontekstual | 🟡 Sedang |
| F-28 | **Biometric auth / PIN** untuk keamanan lokal | Tidak ada | 🟢 Rendah |


---

### A.7 Analisis Kebutuhan Non-Fungsional

| Aspek | Kondisi Saat Ini | Kebutuhan Target |
|---|---|---|
| **Performa** | Tidak ada loading skeleton, spinner sederhana | Skeleton loading, lazy load gambar |
| **Offline** | Tidak ada antrian lokal | Antrian SQLite/Hive, sync otomatis saat online |
| **Keamanan** | JWT di SecureStorage ✅, tidak ada biometric | Opsional biometric unlock |
| **Aksesibilitas** | Font Inter, ukuran default | Font size responsif, high contrast |
| **UX Lapangan** | Form panjang, scroll panjang | Stepper form, tab section |
| **Ukuran APK** | Belum dioptimasi | Tree shaking, ProGuard, split ABI |
| **Testing** | Widget test placeholder saja | Unit test provider, integration test |
| **Validasi** | Sebagian di client, sebagian mapping 422 | Validasi client lengkap sebelum API call |

---

### A.8 Potensi Tantangan Teknis

| Tantangan | Deskripsi | Mitigasi |
|---|---|---|
| **Offline-first complexity** | Sinkronisasi draf lokal ke server butuh conflict resolution | Gunakan `sqflite`/`drift`; strategi: local-first, sync on connectivity |
| **Foto di offline mode** | Foto lokal harus di-queue dan upload saat online | Queue upload dengan path lokal, retry mekanisme |
| **Koordinat GPS indoor** | Petugas dalam gedung mungkin gagal GPS | Fallback ke input manual + peta tile picker |
| **Token expiry saat offline** | Jika offline lama, token kadaluarsa tidak bisa di-refresh | Simpan refresh time, notif "harap login ulang saat online" |
| **Versi API evolusi** | Backend bisa berubah tanpa koordinasi | Versioning API sudah di `/api/v1`, tambah version header |
| **Multiple foto per laporan** | Saat ini hanya 1 foto per laporan di schema | Tidak perlu ubah schema — ikuti constraint existing |
| **Wilayah data besar** | 31 kecamatan + ratusan desa Jember | Cache wilayah di local storage, lazy load per level |
| **Gambar besar di galeri** | Foto dari kamera bisa 4-8MB | Sudah ada kompresi di backend; tambah kompresi client-side |

---

### A.9 Evaluasi Kompatibilitas & Risiko

**Kompatibel penuh dengan sistem existing:**
- Semua endpoint API yang dibutuhkan petugas sudah tersedia dan terimplementasi di backend
- JWT auth flow sudah berjalan dan teruji
- Push notification FCM sudah ada di Flutter dan backend
- Schema database tidak perlu diubah untuk fitur dasar petugas

**Risiko yang perlu diperhatikan:**

| Risiko | Level | Dampak | Mitigasi |
|---|---|---|---|
| Offline mode: data tidak tersinkron | 🔴 Tinggi | Data laporan hilang jika app dihapus sebelum sync | Indikator sync status yang jelas, warning sebelum hapus |
| Duplikat laporan saat retry | 🟡 Sedang | Petugas submit 2x akibat timeout | Idempotency key atau deteksi draf duplikat |
| Perubahan API tanpa notifikasi | 🟡 Sedang | App crash karena field baru/hapus | Defensive parsing di model (nullable field) |
| Foto gagal upload silently | 🟡 Sedang | Laporan tanpa foto, petugas tidak tahu | Eksplisit feedback status upload per foto |
| Token expire saat sedang isi form | 🟡 Sedang | Form hilang saat redirect ke login | Simpan draft lokal sebelum redirect |


---

## BAGIAN B — RANGKAIAN PROMPT TERSTRUKTUR

> Setiap prompt di bawah ini **siap dijalankan langsung** oleh agen AI (Kiro, Claude, Gemini,
> GPT-4o, Codex, dll.). Urutan mengikuti tahap pengembangan: Audit → UX → Core Features →
> Offline → Polish → Testing → Deployment.
>
> **Cara membaca format prompt:**
> - `[KONTEKS]` — informasi proyek yang wajib disertakan ke agen
> - `[INSTRUKSI]` — tugas yang harus dikerjakan
> - `[OUTPUT YANG DIHARAPKAN]` — deliverable spesifik
> - `[KRITERIA SELESAI]` — kondisi dianggap done
> - `[FILE REFERENSI]` — file yang harus dibaca sebelum mengerjakan

---

## FASE 0 — AUDIT & PERSIAPAN (1 Prompt)

---

### PROMPT-00 — Audit Menyeluruh Kode Mobile Flutter Existing

```
[KONTEKS PROYEK]
Nama proyek: JAGAPADI (Jember Agrikultur Gapai Prestasi Digital)
Platform mobile: Flutter Android, Dart ^3.0.0
Lokasi kode: mobile/lib/
State management: Provider ^6.1.1
Routing: go_router ^14.0.0
HTTP client: Dio ^5.4.0 dengan JWT interceptor + auto-refresh
Auth: JWT Bearer token (akses 1 jam, refresh via /api/v1/auth/refresh)
Dua role: admin (verifikator) dan petugas (pelapor lapangan)
Backend: PHP 8.2 REST API di /api/v1

[FILE REFERENSI WAJIB DIBACA]
- mobile/pubspec.yaml
- mobile/lib/core/config.dart
- mobile/lib/core/api_client.dart
- mobile/lib/core/router.dart
- mobile/lib/core/theme.dart
- mobile/lib/features/auth/providers/auth_provider.dart
- mobile/lib/features/hama/models/laporan_hama.dart
- mobile/lib/features/hama/providers/laporan_hama_provider.dart
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/hama/screens/hama_form_screen.dart
- mobile/lib/features/hama/screens/hama_detail_screen.dart
- mobile/lib/features/irigasi/ (semua file)
- mobile/lib/features/home/screens/home_screen.dart
- mobile/lib/features/notifications/ (semua file)
- mobile/lib/features/profile/ (semua file)
- mobile/lib/features/wilayah/ (semua file)
- docs/API.md
- docs/BLUEPRINT.md

[INSTRUKSI]
Lakukan audit kode Flutter mobile secara menyeluruh dengan fokus pada role petugas.
Periksa dan laporkan hal-hal berikut:

1. KELENGKAPAN FITUR
   - Identifikasi fitur yang sudah ada vs yang ada di API tapi belum ada di mobile
   - Buat matriks: endpoint API vs implementasi Flutter

2. KUALITAS KODE
   - Periksa konsistensi pola Provider di semua feature
   - Identifikasi code duplication antara hama_provider dan irigasi_provider
   - Periksa apakah semua nullable field di model sudah handled dengan aman
   - Identifikasi potential null safety issue

3. UX GAPS
   - Identifikasi form field yang pakai input teks padahal bisa pakai widget khusus
     (tanggal, koordinat, dll.)
   - Identifikasi loading state yang tidak konsisten
   - Periksa apakah semua error state tampil ke user

4. KEAMANAN
   - Apakah JWT token tersimpan di flutter_secure_storage (bukan SharedPreferences)?
   - Apakah ada data sensitif yang di-log ke console?
   - Apakah validasi input sudah cukup di client-side sebelum API call?

5. OFFLINE READINESS
   - Apakah ada mekanisme simpan lokal saat offline?
   - Apakah ada connectivity check sebelum API call?
   - Apa yang terjadi jika user buka app tanpa internet?

[OUTPUT YANG DIHARAPKAN]
File: mobile/AUDIT_REPORT.md
Berisi:
- Tabel matriks fitur (endpoint vs implementasi Flutter)
- Daftar bug/issue yang ditemukan dengan tingkat keparahan (Critical/Major/Minor)
- Daftar technical debt yang perlu dibereskan
- Rekomendasi prioritas perbaikan (ordered list)
- Perkiraan effort per item (S/M/L)

[KRITERIA SELESAI]
- Semua file referensi sudah dibaca sebelum menulis laporan
- Laporan menggunakan data aktual dari kode, bukan asumsi
- Setiap issue disertai lokasi file dan baris yang relevan
- Rekomendasi konkret dan actionable
```


---

## FASE 1 — PERBAIKAN DASAR & UX FORM (3 Prompt)

---

### PROMPT-01 — Refactor Form Laporan: DatePicker + Konfirmasi Submit

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Perbaikan UX form laporan hama dan irigasi
Constraint: Tidak boleh mengubah API contract atau menambah dependency baru

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/features/hama/screens/hama_form_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_form_screen.dart
- mobile/lib/features/hama/providers/laporan_hama_provider.dart
- mobile/lib/core/theme.dart
- docs/API.md (bagian Laporan Hama dan Laporan Irigasi)

[INSTRUKSI]
Perbaiki UX form laporan dengan tiga perubahan berikut:

PERBAIKAN 1 — DatePicker Widget (mengganti input teks manual)
- Ganti TextFormField untuk field 'tanggal' dengan showDatePicker() Flutter native
- Format output tetap YYYY-MM-DD (sesuai API)
- Tampilkan teks tanggal dalam format "DD MMMM YYYY" untuk readability
  (contoh: "16 Juli 2026") menggunakan package intl yang sudah ada
- Batasi: tidak boleh pilih tanggal > hari ini + 1 hari (toleransi zona waktu)
- Field tetap wajib diisi (validasi sama seperti sebelumnya)

PERBAIKAN 2 — Preview Ringkasan Sebelum Submit
- Tambahkan dialog konfirmasi sebelum aksi "Kirim Laporan"
- Dialog menampilkan ringkasan field utama:
  * Untuk hama: tanggal, nama OPT, keparahan, luas, kecamatan/desa
  * Untuk irigasi: tanggal, nama saluran, kondisi fisik, debit air, kecamatan/desa
- Dua tombol: "Batal" (tutup dialog) dan "Kirim" (lanjutkan submit)
- Jangan tampilkan dialog saat action=draft

PERBAIKAN 3 — Foto Preview yang Lengkap
- Setelah foto dipilih dari kamera, tampilkan thumbnail preview (bukan hanya ukuran KB)
- Tampilkan ukuran file yang lebih manusiawi ("1.2 MB" bukan "1234 KB")
- Tombol hapus foto (X) di sudut thumbnail
- Tampilkan foto existing jika edit laporan (foto_url dari API)

[ATURAN IMPLEMENTASI]
- Ikuti pola kode existing di hama_form_screen.dart
- Gunakan hanya widget Material 3 dan library yang sudah ada di pubspec.yaml
- Jaga konsistensi antara hama_form_screen.dart dan irigasi_form_screen.dart
- Semua string UI dalam Bahasa Indonesia
- Tidak ada perubahan pada file provider atau model

[OUTPUT YANG DIHARAPKAN]
File yang diubah:
- mobile/lib/features/hama/screens/hama_form_screen.dart (versi lengkap)
- mobile/lib/features/irigasi/screens/irigasi_form_screen.dart (versi lengkap)
Opsional tambahan jika perlu:
- mobile/lib/core/widgets/date_field.dart (reusable DatePicker widget)
- mobile/lib/core/widgets/foto_picker.dart (reusable foto picker + preview)

[KRITERIA SELESAI]
- DatePicker terbuka saat tap field tanggal, format output YYYY-MM-DD
- Dialog konfirmasi muncul sebelum submit (tidak sebelum simpan draft)
- Thumbnail foto tampil setelah pick, ada tombol hapus foto
- Foto existing dari API tampil di form edit
- Tidak ada regression pada fungsionalitas save draft dan submit
- Validasi form masih berjalan sebelum save/submit
```

---

### PROMPT-02 — Filter & Search di List Laporan Petugas

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Tambah filter dan search di halaman list laporan (hama dan irigasi)
Role target: Petugas lapangan (bukan admin)
API: GET /api/v1/laporan-hama?status=X&q=Y&page=Z&limit=20&include_draft=true

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/hama/providers/laporan_hama_provider.dart
- mobile/lib/features/irigasi/screens/irigasi_list_screen.dart
- mobile/lib/features/irigasi/providers/laporan_irigasi_provider.dart
- docs/API.md (bagian filter parameter laporan hama dan irigasi)
- mobile/lib/core/theme.dart

[INSTRUKSI]
Tambahkan fitur filter dan pencarian pada halaman list laporan:

FITUR 1 — Filter Chip Status
- Tampilkan FilterChip horizontal di bawah AppBar:
  "Semua" | "Draf" | "Dikirim" | "Diverifikasi" | "Ditolak"
- Chip aktif memiliki background warna primer
- Saat chip dipilih, reload list dengan parameter ?status=X
- "Semua" berarti tidak ada filter status (include_draft=true)
- Petugas tidak punya status "Diarsipkan" di filter (jarang relevan)

FITUR 2 — Search Bar (Opsional, collapsible)
- Icon search di AppBar → expand SearchBar (Material 3 SearchBar widget)
- Search berdasarkan parameter ?q=keyword ke API
- Debounce 500ms sebelum trigger API call (hindari spam request)
- Tombol clear (X) saat ada teks
- Kombinasikan dengan filter chip: boleh filter status + search bersamaan

FITUR 3 — Pull-to-Refresh Konsisten
- Pastikan RefreshIndicator membungkus ListView di kedua screen
- Saat refresh: reset page=1, reload dari awal
- Tampilkan jumlah total hasil di bawah filter ("Menampilkan 12 laporan")

FITUR 4 — Empty State yang Informatif
- Jika list kosong: tampilkan ilustrasi + teks kontekstual
  * Filter "Draf": "Belum ada draf laporan. Buat laporan baru dengan tombol +"
  * Filter "Ditolak": "Tidak ada laporan yang ditolak"
  * Search: "Tidak ada laporan yang cocok dengan pencarian"
- Tombol CTA: "Buat Laporan Baru" (navigasi ke form)

[ATURAN IMPLEMENTASI]
- Update loadList() di provider untuk menerima parameter filter dan q
- Pastikan parameter include_draft=true dikirim agar draf petugas tampil
- Gunakan hanya package yang sudah ada di pubspec.yaml
- Pola kode harus konsisten antara hama_list_screen dan irigasi_list_screen
- Tidak ada perubahan pada API atau backend

[OUTPUT YANG DIHARAPKAN]
File yang diubah:
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/hama/providers/laporan_hama_provider.dart
- mobile/lib/features/irigasi/screens/irigasi_list_screen.dart
- mobile/lib/features/irigasi/providers/laporan_irigasi_provider.dart

[KRITERIA SELESAI]
- Filter chip bekerja dan mengubah hasil list
- Pull-to-refresh mereset page dan reload data
- Empty state informatif tampil sesuai kondisi
- Infinite scroll (loadMore) tetap berfungsi dengan filter aktif
- Search debounce tidak mengirim request per keystroke
- Tidak ada regression pada fitur existing
```


---

### PROMPT-03 — Dashboard Ringkasan Statistik Petugas

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Tambah dashboard ringkasan statistik untuk role petugas di HomeScreen
API endpoint tersedia: GET /api/v1/dashboard/stats?tahun=YYYY
Behavior petugas: API mengembalikan hanya statistik laporan milik petugas
  (user_id = current, di-enforce di backend)

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/features/home/screens/home_screen.dart
- mobile/lib/features/auth/providers/auth_provider.dart
- docs/API.md (bagian Dashboard & Statistik, GET /api/v1/dashboard/stats)
- mobile/lib/core/theme.dart
- mobile/lib/core/api_client.dart

[INSTRUKSI]
Buat dashboard ringkasan statistik di atas card menu di HomeScreen untuk role petugas.

KOMPONEN 1 — DashboardProvider (baru)
Buat file: mobile/lib/features/home/providers/dashboard_provider.dart
- Fetch GET /api/v1/dashboard/stats?tahun=YYYY (default tahun berjalan)
- Simpan state: loading, error, hama stats, irigasi stats
- Expose method: loadStats(), refresh()
- Data yang diambil:
  * hama: total_aktif (submitted+diverifikasi), total_draf, total_ditolak
  * irigasi: total_aktif, total_draf, total_ditolak

KOMPONEN 2 — StatsSummaryCard Widget
Buat file: mobile/lib/core/widgets/stats_summary_card.dart
- Widget Card berisi grid 3 kolom: Aktif | Draf | Ditolak
- Tiap kolom: angka besar (bold) + label kecil di bawahnya
- Header card: judul (contoh: "Laporan Hama Saya") + ikon refresh kecil
- Warna angka: aktif=hijau, draf=abu, ditolak=merah
- Saat loading: tampilkan Shimmer atau CircularProgressIndicator kecil
- Saat error: tampilkan teks "Gagal memuat" + tombol retry
- Saat tap angka "Ditolak": navigasi ke list laporan dengan filter status=Ditolak

KOMPONEN 3 — Integrasi ke HomeScreen
- Tampilkan dua StatsSummaryCard (hama + irigasi) di atas card menu
- Hanya tampil jika auth.isAdmin == false (khusus petugas)
- Admin tetap melihat "Antrian Verifikasi" seperti sebelumnya
- DashboardProvider diinisiasi dengan loadStats() di initState HomeScreen
- Register DashboardProvider di app.dart (MultiProvider)

[ATURAN IMPLEMENTASI]
- Ikuti pola ChangeNotifier yang sudah digunakan di project
- Register provider di app.dart/main.dart sesuai pola existing
- Tidak ada dependency baru; tidak ada perubahan backend
- Semua label dalam Bahasa Indonesia
- Responsif: card menyesuaikan lebar layar

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/lib/features/home/providers/dashboard_provider.dart
- mobile/lib/core/widgets/stats_summary_card.dart
File diubah:
- mobile/lib/features/home/screens/home_screen.dart
- mobile/lib/app.dart (tambah DashboardProvider ke MultiProvider)

[KRITERIA SELESAI]
- Dashboard hanya tampil untuk role petugas, admin tidak terpengaruh
- Data statistik ter-load dari API saat buka HomeScreen
- Tap angka "Ditolak" menavigasi ke list laporan filter ditolak
- Tombol refresh memuat ulang data
- Loading state dan error state ditangani dengan baik
- Tidak ada perubahan pada tampilan dan fungsi untuk role admin
```

---

## FASE 2 — OFFLINE-FIRST DRAFTING (2 Prompt)

---

### PROMPT-04 — Implementasi Offline-First: Local Draft Queue dengan sqflite

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Implementasi offline-first untuk pembuatan draf laporan
Prinsip bisnis krusial: "Draf wajib tersimpan di DB server saat online"
Artinya: lokal hanya sebagai buffer sementara, bukan pengganti server
Constraint schema: tidak ada perubahan skema database backend

[FILE REFERENSI WAJIB DIBACA]
- mobile/pubspec.yaml (dependency yang sudah ada)
- mobile/lib/features/hama/providers/laporan_hama_provider.dart
- mobile/lib/features/hama/screens/hama_form_screen.dart
- mobile/lib/features/irigasi/providers/laporan_irigasi_provider.dart
- mobile/lib/core/api_client.dart
- docs/API.md (bagian POST /api/v1/laporan-hama, action=draft)
- docs/BLUEPRINT.md (bagian Kebijakan Draf)
- AGENTS.md (aturan bisnis inti tentang Draf)

[INSTRUKSI]
Implementasikan sistem offline-first drafting dengan langkah berikut:

LANGKAH 1 — Tambah Dependencies
Tambahkan ke pubspec.yaml:
- sqflite: ^2.3.0 (local database)
- connectivity_plus: ^6.0.3 (deteksi koneksi)
- path: ^1.9.0 (path helper untuk sqflite)

LANGKAH 2 — Local Draft Database
Buat file: mobile/lib/core/local_db.dart
- Singleton class LocalDb menggunakan sqflite
- Buat tabel local_drafts:
  * id INTEGER PRIMARY KEY AUTOINCREMENT
  * type TEXT NOT NULL ('hama' atau 'irigasi')
  * payload TEXT NOT NULL (JSON string data form)
  * server_id INTEGER NULL (null = belum sync, ada nilai = sudah ada di server)
  * foto_path TEXT NULL (path lokal foto yang belum diupload)
  * created_at TEXT NOT NULL
  * synced_at TEXT NULL
- Method: insertDraft(), updateDraft(), deleteDraft(), getUnsyncedDrafts(), getAll()

LANGKAH 3 — Connectivity Service
Buat file: mobile/lib/core/connectivity_service.dart
- Gunakan connectivity_plus untuk pantau status koneksi
- ChangeNotifier: expose isOnline (bool) + stream koneksi
- Register di MultiProvider di app.dart

LANGKAH 4 — Modifikasi Form Save Logic
Di hama_form_screen.dart dan irigasi_form_screen.dart, ubah flow _save():

SEBELUM (hanya online):
  → POST /api/v1/laporan-hama (action=draft)

SESUDAH (offline-first):
  1. Simpan ke LocalDb.insertDraft() dulu (selalu berhasil, offline pun oke)
  2. Jika online: langsung sync ke server POST /api/v1/laporan-hama
     - Jika berhasil: update local draft dengan server_id, tandai synced_at
     - Jika gagal: simpan lokal saja, tampilkan "Tersimpan lokal, akan sync otomatis"
  3. Jika offline: simpan lokal, tampilkan banner "Offline — draf tersimpan lokal"

LANGKAH 5 — Background Sync Service
Buat file: mobile/lib/core/sync_service.dart
- Method syncPendingDrafts(ApiClient api): loop getUnsyncedDrafts(), POST ke server
- Dipanggil: saat app foreground + connectivity berubah ke online
- Setelah sync berhasil: hapus dari local, arahkan ke draf yang ada di server
- Handle error per-item: jika 1 item gagal, lanjut item berikutnya (tidak stop semua)
- Simpan log: "Sync berhasil: 2 draf dikirim, 0 gagal"

LANGKAH 6 — Indikator Status Koneksi + Sync
Di HomeScreen (dan opsional di form screen):
- Banner kuning di bawah AppBar jika offline: "Tidak ada koneksi internet"
- Badge di card menu Laporan Hama/Irigasi jika ada pending local drafts
- Tampilkan jumlah: "3 draf menunggu sinkronisasi"

[ATURAN IMPLEMENTASI]
- Offline hanya untuk action=draft; submit laporan tetap harus online
  (tampilkan error informatif jika user coba submit saat offline)
- Foto: jika ada foto lokal, upload setelah sync draf berhasil (server_id sudah ada)
- Tidak ada perubahan pada backend/API
- Ikuti null safety Dart; semua field JSON payload nullable
- Gunakan try-catch untuk semua operasi database lokal

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/lib/core/local_db.dart
- mobile/lib/core/connectivity_service.dart
- mobile/lib/core/sync_service.dart
File diubah:
- mobile/pubspec.yaml (tambah sqflite, connectivity_plus, path)
- mobile/lib/features/hama/screens/hama_form_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_form_screen.dart
- mobile/lib/features/home/screens/home_screen.dart (banner offline + badge)
- mobile/lib/app.dart (register ConnectivityService di MultiProvider)

[KRITERIA SELESAI]
- Saat offline: simpan form → tersimpan lokal → banner "Offline" tampil
- Saat kembali online: sync otomatis berjalan → badge hilang
- Saat online: flow normal (langsung ke server), lokal hanya backup sementara
- Submit laporan tetap menolak jika offline dengan pesan yang jelas
- Foto lokal ikut diupload setelah sync draf berhasil
- Tidak ada data loss: jika sync gagal, data lokal tidak dihapus
- Jalankan flutter analyze — tidak ada error
```


---

### PROMPT-05 — Indikator Sinkronisasi & Manajemen Draf Lokal

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: UI manajemen draf lokal yang belum tersinkronisasi
Prasyarat: PROMPT-04 sudah selesai diimplementasi (LocalDb, ConnectivityService, SyncService)

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/core/local_db.dart (hasil PROMPT-04)
- mobile/lib/core/connectivity_service.dart (hasil PROMPT-04)
- mobile/lib/core/sync_service.dart (hasil PROMPT-04)
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_list_screen.dart
- mobile/lib/core/theme.dart

[INSTRUKSI]
Buat tampilan yang membantu petugas mengelola draf lokal yang belum tersinkronisasi.

KOMPONEN 1 — LocalDraftsBanner di List Screen
- Jika ada unsync local drafts untuk tipe yang sama (hama atau irigasi):
  tampilkan Card berwarna kuning/amber di bagian atas list screen
  Isi: "2 draf belum tersinkronisasi. [Sync Sekarang]"
- Tombol "Sync Sekarang": jalankan SyncService.syncPendingDrafts() dengan feedback
- Saat sync berjalan: tampilkan loading indicator, disable tombol
- Setelah sync: refresh list (draf lokal hilang, draf server muncul di list)

KOMPONEN 2 — Section Draf Lokal di List Screen
- Jika ada local drafts yang belum sync, tampilkan section terpisah:
  "Draf Lokal (belum tersinkronisasi)"
- Setiap item tampilkan:
  * Tanggal dibuat (lokal)
  * Ringkasan payload (nama OPT untuk hama, nama saluran untuk irigasi)
  * Badge "Belum sync" warna oranye
  * Tombol hapus draf lokal (dengan konfirmasi dialog)
- Section ini tampil di atas list laporan dari server

KOMPONEN 3 — Retry & Hapus Manual
- Swipe kiri pada item draf lokal: tampilkan tombol "Hapus"
  Dialog konfirmasi: "Hapus draf lokal ini? Data yang belum tersinkronisasi akan hilang."
- Long press atau swipe kanan: "Coba Sync Item Ini" (sync satu item)

KOMPONEN 4 — Notifikasi Sync Berhasil
- Setelah sync berhasil di background: tampilkan SnackBar singkat
  "2 draf berhasil tersinkronisasi ke server"
- Setelah sync gagal sebagian: "1 draf berhasil, 1 gagal. Coba lagi nanti."

[ATURAN IMPLEMENTASI]
- Gunakan ValueListenableBuilder atau Consumer untuk reaktif terhadap perubahan LocalDb
- Hapus draf lokal yang sudah sync dari LocalDb segera setelah server_id diterima
- Tidak boleh tampilkan data lokal yang sudah punya server_id di section "Belum sync"
- Teks error sync harus spesifik (misalnya: "Gagal: koneksi timeout" bukan "Error")

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/lib/core/widgets/local_drafts_banner.dart
File diubah:
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_list_screen.dart

[KRITERIA SELESAI]
- Banner muncul hanya jika ada unsync draft untuk tipe tersebut
- Hapus manual dengan konfirmasi berjalan
- Sync manual berhasil dan list ter-refresh otomatis
- Setelah semua sync: banner menghilang
- Tidak ada item lokal tersync yang masih tampil di section "Belum sync"
```

---

## FASE 3 — PENINGKATAN DETAIL LAPORAN & NOTIFIKASI (2 Prompt)

---

### PROMPT-06 — Perbaikan Detail Laporan: Timeline Status + Peta Lokasi

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Perkaya halaman detail laporan dengan timeline status dan peta mini
Platform: Android
Packages tersedia: geolocator sudah ada; perlu tambah flutter_map untuk peta tile

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/features/hama/screens/hama_detail_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_detail_screen.dart
- mobile/lib/features/hama/models/laporan_hama.dart
- mobile/lib/features/irigasi/models/laporan_irigasi.dart
- mobile/lib/core/theme.dart
- docs/API.md (field yang tersedia di response detail laporan)

[INSTRUKSI]
Tingkatkan halaman detail laporan dengan informasi tambahan:

PENINGKATAN 1 — Status Timeline Widget
Buat file: mobile/lib/core/widgets/status_timeline.dart
- Widget yang menampilkan alur status sebagai timeline vertikal:
  [●] Draf → [●] Dikirim → [●] Diverifikasi / [●] Ditolak → [●] Diarsipkan
- Status yang sudah dilalui: lingkaran hijau + garis solid
- Status saat ini: lingkaran biru berkedip (AnimatedContainer) atau warna primer solid
- Status belum dilalui: lingkaran abu-abu + garis putus-putus
- Di bawah tiap milestone yang relevan: tampilkan tanggal (jika ada)
  * Dikirim: tanggal created_at laporan
  * Diverifikasi/Ditolak: verified_at
- Khusus status "Ditolak": tampilkan alasan penolakan (catatan_verifikasi) di bawah milestone

PENINGKATAN 2 — Peta Mini Lokasi (jika koordinat tersedia)
- Tambah dependency ke pubspec.yaml: flutter_map: ^7.0.0 + latlong2: ^0.9.0
- Jika laporan memiliki latitude dan longitude:
  tampilkan FlutterMap dengan tile OpenStreetMap
  Ukuran: tinggi 200px, full width
  Marker: pin merah di lokasi laporan
  Zoom level: 14
  InteractionOptions: disabled (read-only, tidak bisa pan/zoom)
  Sertakan fallback "Koordinat tidak tersedia" jika latitude/longitude null
- Tap pada peta: buka Google Maps eksternal ke koordinat tersebut
  (gunakan url_launcher untuk open maps://... atau https://maps.google.com/?q=lat,lng)
  Tambah url_launcher: ^6.2.5 ke pubspec.yaml

PENINGKATAN 3 — Action Button yang Lebih Jelas (Petugas)
- Ganti PopupMenuButton dengan tombol eksplisit di bottom sheet atau area bawah screen
- Untuk status Draf:
  Dua tombol baris: [Edit Draf] [Kirim Sekarang]
- Untuk status Ditolak:
  Tiga tombol: [Lihat Alasan] [Edit & Perbaiki] [Kirim Ulang]
  "Lihat Alasan" membuka dialog yang menampilkan catatan_verifikasi lengkap
- Untuk status Submitted/Diverifikasi/Diarsipkan:
  Read-only, tidak ada action button petugas

[ATURAN IMPLEMENTASI]
- Tidak mengubah PopupMenu untuk role admin (admin tetap pakai existing)
- Gunakan !auth.isAdmin untuk guard semua perubahan button petugas
- Tambah dependency url_launcher dan flutter_map ke pubspec.yaml secara pinned version
- Peta menggunakan OpenStreetMap (gratis, no API key)
- Simpan reusable widget di mobile/lib/core/widgets/

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/lib/core/widgets/status_timeline.dart
File diubah:
- mobile/pubspec.yaml (tambah flutter_map, latlong2, url_launcher)
- mobile/lib/features/hama/screens/hama_detail_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_detail_screen.dart

[KRITERIA SELESAI]
- Timeline menampilkan status yang tepat berdasarkan status laporan saat ini
- Peta mini tampil jika ada koordinat, tidak crash jika tidak ada
- Tap peta membuka Google Maps di browser/app eksternal
- Tombol aksi petugas terlihat jelas dan kontekstual per status
- Tidak ada perubahan behavior untuk role admin
- flutter analyze tidak ada error/warning kritis
```


---

### PROMPT-07 — Deep Link Notifikasi + Mark-Read Otomatis

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Pastikan notifikasi FCM dan in-app bisa deep link ke detail laporan yang tepat
      + tandai notifikasi sebagai dibaca saat detail laporan dibuka dari notifikasi

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/features/notifications/screens/notification_screen.dart
- mobile/lib/features/notifications/providers/notification_provider.dart
- mobile/lib/features/notifications/models/notification_item.dart
- mobile/lib/core/fcm/fcm_service.dart
- mobile/lib/core/router.dart
- docs/API.md (bagian Notifications)

[INSTRUKSI]
Pastikan seluruh alur notifikasi → detail laporan bekerja dengan benar:

PERBAIKAN 1 — Validasi Deep Link di NotificationScreen
- Saat _onTap(NotificationItem n) dipanggil:
  * Tandai read (sudah ada: markRead)
  * Navigasi menggunakan go_router (context.go()) bukan Navigator.pushNamed()
    (karena app menggunakan go_router, bukan Navigator named routes)
  * Route: '/hama/:id' untuk entity='hama', '/irigasi/:id' untuk entity='irigasi'
  * Jika entity atau laporanId null: tampilkan SnackBar "Detail laporan tidak tersedia"

PERBAIKAN 2 — FCM Background Tap Handler
Di fcm_service.dart, pastikan:
- FirebaseMessaging.onMessageOpenedApp (background → foreground tap) 
  navigasi ke detail laporan yang tepat menggunakan AppRouter instance
- getInitialMessage() untuk cold start dari notifikasi
- Data payload FCM: entity (hama/irigasi), laporan_id, nomor_laporan
- Gunakan NavigatorKey global yang sama dengan AppRouter

PERBAIKAN 3 — In-App Notification saat Foreground
- Saat FCM datang di foreground (onMessage):
  * Jangan tampilkan system notification (sudah ada in-app polling)
  * Trigger refresh NotificationProvider.load() agar badge update
  * Tampilkan SnackBar singkat: "Laporan #LH-XXXX: Diverifikasi. [Lihat]"
  * Tombol [Lihat] navigasi ke detail laporan

PERBAIKAN 4 — Mark-All-Read yang Benar
- Tombol "Baca Semua" di NotificationScreen AppBar:
  * Panggil API PATCH /api/v1/notifications/mark-all-read (cek apakah endpoint ada)
  * Jika endpoint tidak ada: POST tiap notifikasi satu per satu (batched, max 20)
  * Update state lokal segera (optimistic update) sebelum tunggu API
  * Reset unreadCount ke 0 di NotificationProvider

[ATURAN IMPLEMENTASI]
- Gunakan context.go() dari go_router, bukan Navigator.pushNamed()
- NavigatorKey untuk FCM harus sama instance dengan AppRouter.navigatorKey
- FCM token hanya didaftarkan setelah login berhasil (sudah ada, jangan duplikat)
- Semua navigasi dari FCM harus dicek apakah widget sudah mounted

[OUTPUT YANG DIHARAPKAN]
File diubah:
- mobile/lib/features/notifications/screens/notification_screen.dart
- mobile/lib/features/notifications/providers/notification_provider.dart
- mobile/lib/core/fcm/fcm_service.dart
- mobile/lib/app.dart (jika perlu pass navigatorKey ke FCM service)

[KRITERIA SELESAI]
- Tap notifikasi in-app membuka detail laporan yang benar
- Tap FCM push (background dan cold start) membuka detail laporan yang benar
- FCM foreground menampilkan SnackBar dengan tombol "Lihat"
- Mark-all-read mengosongkan badge di AppBar
- Tidak ada crash jika laporan_id null atau entity tidak dikenal
```

---

## FASE 4 — KEAMANAN & POLISHING (2 Prompt)

---

### PROMPT-08 — Keamanan: Validasi Input Sisi Client + Error Handling

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Perkuat validasi form di client-side dan standarisasi error handling
Prinsip: Validasi client mengurangi round-trip API; backend tetap sumber kebenaran

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/features/hama/screens/hama_form_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_form_screen.dart
- mobile/lib/core/api_client.dart
- docs/API.md (bagian validation rules setiap endpoint)
- AGENTS.md (aturan keamanan)

[INSTRUKSI]
Implementasikan validasi client-side yang lengkap dan error handling yang konsisten:

VALIDASI 1 — Form Laporan Hama (sebelum API call)
Tambahkan validator di setiap FormField:
- tanggal: wajib, format YYYY-MM-DD, tidak boleh lebih dari hari ini + 1
- master_opt_id: wajib dipilih (tidak null)
- kabupaten_id, kecamatan_id, desa_id: semua wajib (cascade; kecamatan disabled sebelum kab dipilih)
- tingkat_keparahan: wajib dipilih saat submit (opsional saat draft)
- luas_serangan: jika diisi, harus antara 0.01 dan 9999.99
- populasi: jika diisi, harus positif dan <= 999999.99
- latitude: jika diisi, harus antara -90 dan 90
- longitude: jika diisi, harus antara -180 dan 180
- catatan: maksimal 2000 karakter

VALIDASI 2 — Form Laporan Irigasi
- tanggal: wajib, tidak boleh lebih dari hari ini + 1
- kabupaten_id, kecamatan_id, desa_id: wajib saat submit
- nama_saluran: wajib saat submit, maksimal 200 karakter
- kondisi_fisik: wajib dipilih saat submit
- debit_air: wajib dipilih saat submit
- latitude/longitude: sama dengan hama

VALIDASI 3 — Foto Upload
Sebelum upload ke API:
- Periksa ukuran file: jika > 10MB tampilkan error "Foto melebihi batas 10 MB"
- Periksa ekstensi: hanya jpg, jpeg, png, webp
- Tampilkan error di SnackBar merah jika validasi gagal

STANDARISASI ERROR HANDLING
Buat file: mobile/lib/core/error_handler.dart
- Function: handleApiError(BuildContext context, ApiResponse response)
  * statusCode 422: tampilkan error per field (field errors sudah ada, pastikan konsisten)
  * statusCode 429: tampilkan "Terlalu banyak permintaan. Coba lagi dalam beberapa menit."
  * statusCode 401: tidak perlu handle di sini (sudah di interceptor)
  * statusCode 403: "Aksi tidak diizinkan."
  * statusCode 404: "Data tidak ditemukan."
  * statusCode 409: tampilkan pesan conflict dari API (misalnya: status laporan tidak valid)
  * statusCode >= 500: "Terjadi kesalahan server. Coba lagi nanti."
  * error = 'NetworkError': "Tidak dapat terhubung ke server. Periksa koneksi internet."
- Gunakan SnackBar untuk error non-form; gunakan setState() untuk field errors
- Log error ke debugPrint() hanya di debug mode (kIsWeb/kDebugMode check)

[ATURAN IMPLEMENTASI]
- Validasi client-side adalah tambahan, TIDAK menggantikan validasi server
- Validasi "wajib saat submit" vs "opsional saat draft" harus dibedakan
  dengan parameter isSubmit boolean di validator
- Tidak boleh menyimpan data sensitif (token, password) ke debugPrint atau log file
- Gunakan const untuk validator functions yang tidak capture state

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/lib/core/error_handler.dart
File diubah:
- mobile/lib/features/hama/screens/hama_form_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_form_screen.dart

[KRITERIA SELESAI]
- Form tidak bisa submit jika field wajib kosong (client check sebelum API call)
- Pesan error per field tampil di bawah field yang bermasalah
- Koordinat di luar range menolak form sebelum kirim ke API
- Foto > 10MB ditolak dengan pesan yang jelas sebelum upload
- Error 429 tampil pesan yang informatif, bukan generic error
- flutter analyze tidak ada warning baru
```


---

### PROMPT-09 — UI Polish: Loading Skeleton + Aksesibilitas + Tema Konsisten

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Polish visual dan aksesibilitas UI untuk kenyamanan petugas lapangan
Target pengguna: Petugas lapangan yang mungkin menggunakan HP entry-level
                 di luar ruangan (brightness tinggi, koneksi lambat)

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/core/theme.dart
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/hama/screens/hama_detail_screen.dart
- mobile/lib/features/home/screens/home_screen.dart
- mobile/pubspec.yaml

[INSTRUKSI]
Lakukan polish UI dengan fokus pada pengalaman pengguna lapangan:

POLISH 1 — Loading Skeleton (mengganti spinner tunggal)
Buat file: mobile/lib/core/widgets/skeleton_card.dart
- Widget SkeletonCard yang menampilkan placeholder abu-abu beranimasi (shimmer effect)
  Implementasi shimmer menggunakan AnimatedContainer dengan warna berubah-ubah
  (TIDAK menambah package shimmer baru; implementasi manual agar hemat)
- SkeletonListItem: placeholder satu baris list (icon circle + 2 baris teks)
- SkeletonDetailCard: placeholder untuk halaman detail (header besar + beberapa baris)
- Ganti CircularProgressIndicator pada halaman list dan detail dengan Skeleton
  Tampilkan 5-6 skeleton item saat loading list
  Tampilkan skeleton detail saat loading detail

POLISH 2 — Status Badge Widget yang Konsisten
Buat file: mobile/lib/core/widgets/status_badge.dart
- Widget StatusBadge(String status) yang menampilkan Chip dengan warna semantik:
  * Draf: abu-abu (#9E9E9E)
  * Submitted/Dikirim: biru (#1565C0)
  * Diverifikasi: hijau (#2E7D32)
  * Ditolak: merah (#C62828)
  * Diarsipkan: ungu (#6A1B9A)
- Teks dalam Bahasa Indonesia (pakai label dari LaporanHama.statusLabel)
- Ukuran: compact, font 11sp, padding horizontal 8
- Gunakan StatusBadge di: list item dan detail screen

POLISH 3 — List Item Card yang Lebih Informatif
Update tampilan item di hama_list_screen dan irigasi_list_screen:
- Gunakan Card dengan elevation 1, border radius 8
- Layout setiap item:
  * Kiri: StatusBadge (vertikal di kiri atas)
  * Tengah: tanggal (bold, 14sp) + nama OPT/nama saluran (12sp, grey)
             kecamatan/desa (12sp, grey) + nomor laporan jika ada
  * Kanan: chevron + timestamp "2 hari lalu" (relative time dengan intl)
- Untuk laporan Ditolak: tambahkan border merah tipis di sisi kiri Card

POLISH 4 — Aksesibilitas
- Tambahkan Semantics/Tooltip pada semua IconButton (sudah ada tooltip di beberapa, lengkapi)
- Pastikan contrast ratio teks terhadap background minimal 4.5:1 (WCAG AA)
  Periksa AppColors.textSecondary (#888888) di background putih: adjust ke #767676 min
- Gunakan TextScaler aware di teks penting (wrap dengan FittedBox atau maxLines + overflow)
- Minimum touch target 48x48dp di semua tombol aksi

[ATURAN IMPLEMENTASI]
- Tidak menambah package baru selain yang sudah ada atau disetujui di prompt sebelumnya
- Shimmer implementasi manual dengan AnimatedBuilder + ColorTween
- Semua widget baru di mobile/lib/core/widgets/ (bisa dipakai ulang)
- Tidak ada perubahan pada logika bisnis atau provider

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/lib/core/widgets/skeleton_card.dart
- mobile/lib/core/widgets/status_badge.dart
File diubah:
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/hama/screens/hama_detail_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_list_screen.dart
- mobile/lib/features/irigasi/screens/irigasi_detail_screen.dart
- mobile/lib/core/theme.dart (adjust textSecondary untuk aksesibilitas)

[KRITERIA SELESAI]
- Skeleton tampil saat loading, bukan spinner tunggal
- Status badge warna konsisten di list dan detail
- Setiap item card memberikan informasi yang cukup tanpa buka detail
- Semua IconButton memiliki tooltip
- flutter analyze tidak ada error
- Tidak ada regression pada behavior atau navigasi
```

---

## FASE 5 — TESTING (2 Prompt)

---

### PROMPT-10 — Unit Test: Provider & Model

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Tulis unit test untuk Provider dan Model layer
Framework: flutter_test (sudah ada di pubspec.yaml)
Coverage target: Provider methods dan model parsing (tidak perlu widget test)

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/features/hama/models/laporan_hama.dart
- mobile/lib/features/hama/providers/laporan_hama_provider.dart
- mobile/lib/features/irigasi/models/laporan_irigasi.dart
- mobile/lib/features/irigasi/providers/laporan_irigasi_provider.dart
- mobile/lib/features/auth/providers/auth_provider.dart
- mobile/lib/core/api_client.dart
- mobile/test/widget_test.dart (lihat pola existing)
- mobile/pubspec.yaml

[INSTRUKSI]
Tulis unit test yang komprehensif untuk layer model dan provider:

TEST 1 — Model Parsing Tests
Buat file: mobile/test/models/laporan_hama_test.dart
- Test LaporanHama.fromJson() dengan data lengkap (semua field)
- Test LaporanHama.fromJson() dengan data minimal (hanya field wajib)
- Test LaporanHama.fromJson() dengan field null (toleransi null safety)
- Test computed properties: isEditable, isSubmittable, isDraf, isDitolak, statusLabel
  * status='Draf' → isEditable=true, isSubmittable=true, statusLabel='Draf'
  * status='Submitted' → isEditable=false, isSubmittable=false
  * status='Ditolak' → isEditable=true, isSubmittable=true, isDitolak=true
  * status='Diverifikasi' → isEditable=false
- Test parsing latitude/longitude dari String dan double di JSON
- Test OptOption.fromJson()

Buat file: mobile/test/models/laporan_irigasi_test.dart
- Pola sama dengan hama, sesuaikan field (kondisi_fisik, debit_air, nama_saluran)

TEST 2 — Provider Tests dengan Mock ApiClient
Tambah dependency mock ke pubspec.yaml: mockito: ^5.4.4, build_runner: ^2.4.8

Buat file: mobile/test/providers/laporan_hama_provider_test.dart
Gunakan mockito untuk mock ApiClient:

Skenario yang harus ditest:
- loadList(): success response → list terisi, loading=false, error=null
- loadList(): error response → list kosong, error=pesan error
- loadList(refresh=true) → reset page ke 1, list di-clear sebelum isi baru
- loadDetail(id): success → detail ter-set
- loadDetail(id): not found → error ter-set
- save() draft: success → kembalikan data, fieldErrors=null
- save() dengan validation error (422): fieldErrors ter-set dari response
- submit(id): success → kembalikan data
- delete(id): success → hapus dari _list
- resubmit(id): success → detail terupdate

TEST 3 — ApiResponse Parsing
Buat file: mobile/test/core/api_client_test.dart
- Test ApiResponse.fromJson() dengan success=true, data, message
- Test ApiResponse.fromJson() dengan success=false, error, errors
- Test ApiResponse.fromJson() dengan data null

[ATURAN IMPLEMENTASI]
- Gunakan Mockito @GenerateMocks([ApiClient]) dan jalankan build_runner untuk generate mock
- Setiap test case: arrange → act → assert (pola AAA)
- Test harus independent (tidak bergantung state test lain)
- Gunakan group() untuk mengelompokkan test per method
- Tidak perlu test widget UI (widget test di prompt terpisah)
- Nama test dalam Bahasa Inggris (konvensi test)

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/test/models/laporan_hama_test.dart
- mobile/test/models/laporan_irigasi_test.dart
- mobile/test/providers/laporan_hama_provider_test.dart
- mobile/test/core/api_client_test.dart
File diubah:
- mobile/pubspec.yaml (tambah mockito, build_runner sebagai dev_dependencies)

[KRITERIA SELESAI]
- flutter test berjalan: semua test pass (0 failures)
- Coverage model parsing: minimal 90% branch coverage
- Coverage provider: semua public method ter-test minimal happy path + 1 error path
- Tidak ada test yang depend pada network atau database nyata
- build_runner generate mock berhasil tanpa error
```


---

### PROMPT-11 — Integration Test: Alur Kerja Petugas End-to-End

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Integration test alur kerja petugas dari login hingga submit laporan
Framework: flutter_test + integration_test package
Prasyarat: Backend JAGAPADI berjalan di localhost:8080 dengan seed data
           Akun test: username=petugas01, password=TestPetugas!456

[FILE REFERENSI WAJIB DIBACA]
- mobile/lib/core/router.dart
- mobile/lib/features/auth/screens/login_screen.dart
- mobile/lib/features/hama/screens/hama_list_screen.dart
- mobile/lib/features/hama/screens/hama_form_screen.dart
- mobile/lib/features/hama/screens/hama_detail_screen.dart
- mobile/test/widget_test.dart
- docs/API.md

[INSTRUKSI]
Tulis integration test untuk skenario kritis alur petugas:

SETUP
Tambah ke pubspec.yaml (dev_dependencies):
- integration_test:
    sdk: flutter

Buat file: mobile/integration_test/app_test.dart

SKENARIO 1 — Login Sukses Petugas
test('petugas can login with valid credentials'):
  1. Pump app dengan API_BASE_URL=http://10.0.2.2:8080/api/v1
  2. Tunggu LoginScreen render
  3. Isi username 'petugas01', password 'TestPetugas!456'
  4. Tap tombol 'Masuk'
  5. Tunggu navigasi ke HomeScreen
  6. Expect: teks 'JAGAPADI' di AppBar
  7. Expect: card 'Laporan Hama' terlihat
  8. Expect: TIDAK ada card 'Antrian Verifikasi' (hanya admin)

SKENARIO 2 — Login Gagal
test('login shows error with wrong password'):
  1. Isi username 'petugas01', password 'salah'
  2. Tap 'Masuk'
  3. Expect: pesan error 'Username atau password salah' tampil

SKENARIO 3 — Buat dan Simpan Draf Laporan Hama
test('petugas can create draft laporan hama'):
  1. Login sebagai petugas01
  2. Tap card 'Laporan Hama'
  3. Tap tombol '+' (buat baru)
  4. Isi field tanggal (hari ini)
  5. Pilih OPT dari dropdown
  6. Pilih Kabupaten, Kecamatan, Desa
  7. Isi Lokasi
  8. Tap 'Simpan Draft'
  9. Expect: SnackBar 'Draf berhasil disimpan'
  10. Expect: navigasi kembali ke list
  11. Expect: item baru muncul di list dengan badge 'Draf'

SKENARIO 4 — Submit Laporan
test('petugas can submit a draft laporan'):
  1. Login dan pastikan ada draf dari skenario 3
  2. Tap draf tersebut
  3. Tap action PopupMenu → 'Kirim Laporan'
  4. Expect: dialog konfirmasi muncul
  5. Tap 'Kirim' di dialog
  6. Expect: SnackBar 'Laporan berhasil dikirim'
  7. Expect: status badge berubah dari 'Draf' ke 'Dikirim'
  8. Expect: nomor laporan tampil (LH-YYYYMMDD-XXXX)

SKENARIO 5 — Logout
test('petugas can logout'):
  1. Login dan buka ProfileScreen
  2. Tap 'Keluar'
  3. Expect: navigasi ke LoginScreen

[SETUP TEST AKUN]
Buat script: mobile/integration_test/fixtures/setup_test_data.dart
- Fungsi yang memanggil POST /api/v1/auth/login dengan akun petugas test
- Jika akun tidak ada: skip test dengan pesan "Test account not found, skip"
- Gunakan http package sederhana (bukan Dio) untuk avoid test dependency

[ATURAN IMPLEMENTASI]
- Gunakan find.text(), find.byKey(), find.byType() secara konsisten
- Tambahkan Key() pada widget kritis (tombol submit, card menu) untuk find.byKey()
- Setiap skenario harus bisa jalan sendiri (gunakan setUp/tearDown)
- Gunakan tester.pumpAndSettle(timeout: Duration(seconds: 10)) untuk animasi
- Jangan hardcode data yang bergantung urutan (nomor laporan)
- Test hanya untuk happy path; edge case di unit test

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/integration_test/app_test.dart
- mobile/integration_test/fixtures/setup_test_data.dart
File diubah:
- mobile/pubspec.yaml (tambah integration_test)
- Tambah Key() pada widget kritis di: login_screen.dart, home_screen.dart,
  hama_list_screen.dart, hama_form_screen.dart, hama_detail_screen.dart

[KRITERIA SELESAI]
- Semua 5 skenario pass saat dijalankan dengan emulator dan backend aktif
- flutter test integration_test/ tidak ada failure
- Test tidak meninggalkan data kotor di database (cleanup di tearDown)
- Setiap skenario selesai dalam < 30 detik
```

---

## FASE 6 — BUILD & DEPLOYMENT (1 Prompt)

---

### PROMPT-12 — Build APK Release + Konfigurasi Produksi

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Konfigurasi build release APK yang siap distribusi ke petugas lapangan
Target: APK signed yang bisa diinstall langsung (sideload) atau via Play Store
Backend produksi URL: dikonfigurasi via --dart-define saat build

[FILE REFERENSI WAJIB DIBACA]
- mobile/pubspec.yaml
- mobile/android/app/ (lihat struktur existing)
- mobile/README.md
- docs/BUILD_APK.md
- mobile/lib/core/config.dart (AppConfig.baseUrl)
- AGENTS.md (aturan keamanan: no secret commit)

[INSTRUKSI]
Siapkan konfigurasi build dan dokumentasi lengkap untuk distribusi APK:

LANGKAH 1 — Konfigurasi ProGuard / R8 untuk Release Build
Buat file: mobile/android/app/proguard-rules.pro
Isi dengan rules untuk menjaga kelas yang digunakan:
- Keep flutter_secure_storage classes
- Keep firebase_messaging classes
- Keep dio/retrofit related classes
- Keep model classes (LaporanHama, LaporanIrigasi, dll.)
- Keep go_router classes

Update mobile/android/app/build.gradle:
- Aktifkan minifyEnabled true dan shrinkResources true hanya untuk buildType release
- Set targetSdk ke API level terbaru stabil (API 35 per Agustus 2026)
- Set minSdk ke 24 (sudah ada)

LANGKAH 2 — Signing Configuration Template
Buat file: mobile/android/key.properties.example (BUKAN key.properties)
Isi:
  storeFile=keystore.jks
  storePassword=GANTI_INI
  keyAlias=jagapadi
  keyPassword=GANTI_INI
Tambahkan key.properties dan keystore.jks ke .gitignore (jangan commit secret)

Update build.gradle untuk membaca key.properties jika ada:
  def keystorePropertiesFile = rootProject.file("key.properties")
  if (keystorePropertiesFile.exists()) { ... }
  Fallback ke debug key jika file tidak ada (untuk CI tanpa signing)

LANGKAH 3 — Konfigurasi Flavor (opsional tapi direkomendasikan)
Definisikan dua product flavor di build.gradle:
- development: API URL ke localhost, applicationIdSuffix ".dev", versionName + "-dev"
- production: API URL dikonfigurasi via --dart-define, applicationId resmi

LANGKAH 4 — Build Script
Buat file: mobile/scripts/build_release.sh (Linux/macOS)
dan mobile/scripts/build_release.bat (Windows)
Script melakukan:
  1. flutter clean
  2. flutter pub get
  3. flutter build apk --release \
       --dart-define=API_BASE_URL=https://jagapadi.example.go.id/api/v1 \
       --split-per-abi  (hasilkan 3 APK: armeabi-v7a, arm64-v8a, x86_64)
  4. Copy hasil ke folder dist/ dengan nama: jagapadi-vX.Y.Z-arm64-v8a-release.apk
  5. Print checksum SHA256 tiap APK

LANGKAH 5 — Update Dokumentasi BUILD_APK.md
Update docs/BUILD_APK.md dengan:
- Prasyarat signing (cara buat keystore dengan keytool)
- Cara set API_BASE_URL via --dart-define
- Cara jalankan script build_release
- Tabel APK per ABI dengan ukuran estimasi
- Instruksi distribusi: arahkan ke Google Drive / WhatsApp / internal portal
- QR code generation hint (gunakan qrencode di bash) untuk URL download APK
- Checklist sebelum build produksi:
  * [ ] API_BASE_URL sudah benar
  * [ ] FCM google-services.json ada di android/app/
  * [ ] key.properties sudah dikonfigurasi
  * [ ] Version code di pubspec.yaml sudah dinaikkan
  * [ ] flutter analyze clean
  * [ ] flutter test pass

[ATURAN KEAMANAN]
- key.properties dan *.jks WAJIB ada di .gitignore — verifikasi sebelum selesai
- Tidak ada API key, secret, atau URL produksi hardcoded di kode
- AppConfig.baseUrl sudah menggunakan String.fromEnvironment — tidak perlu ubah

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/android/app/proguard-rules.pro
- mobile/android/key.properties.example
- mobile/scripts/build_release.sh
- mobile/scripts/build_release.bat
File diubah:
- mobile/android/app/build.gradle (ProGuard + signing config)
- mobile/.gitignore (tambah key.properties, *.jks, dist/)
- docs/BUILD_APK.md (dokumentasi lengkap)
- mobile/pubspec.yaml (pastikan version dan build number up-to-date)

[KRITERIA SELESAI]
- flutter build apk --release berhasil tanpa error
- APK terhasilkan di build/app/outputs/flutter-apk/
- key.properties dan *.jks TIDAK ter-commit ke git (ada di .gitignore)
- docs/BUILD_APK.md memiliki instruksi yang bisa diikuti tanpa bantuan tambahan
- SHA256 checksum diprint oleh script build
- APK bisa diinstall di emulator Android API 24+ tanpa error
```


---

## FASE 7 — FITUR LANJUTAN OPSIONAL (2 Prompt)

> Prompt berikut adalah pengembangan tahap kedua setelah semua fitur inti (Fase 0–6) selesai.
> Jalankan hanya jika ada kebutuhan bisnis yang mengonfirmasi fiturnya.

---

### PROMPT-13 — Peta Sebaran Laporan Milik Sendiri (Opsional)

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Tambahkan screen peta untuk petugas melihat sebaran laporan miliknya sendiri
Prasyarat: PROMPT-06 sudah selesai (flutter_map sudah ditambahkan)
API: GET /api/v1/dashboard/map/hama?tahun=YYYY dan /api/v1/dashboard/map/irigasi
     API mengembalikan GeoJSON FeatureCollection dengan titik laporan
     Untuk role petugas: hanya laporan milik sendiri

[FILE REFERENSI WAJIB DIBACA]
- docs/API.md (bagian GET /api/v1/dashboard/map/hama)
- mobile/lib/features/hama/screens/ (pola screen)
- mobile/lib/core/theme.dart
- mobile/pubspec.yaml (flutter_map sudah ada dari PROMPT-06)

[INSTRUKSI]
Tambahkan screen peta laporan untuk petugas:

KOMPONEN 1 — MapProvider
Buat: mobile/lib/features/map/providers/map_provider.dart
- Fetch GeoJSON hama dan irigasi dari API
- Parse LatLng dari features[].geometry.coordinates ([lng, lat] → LatLng(lat, lng))
- Parse properties: id, nomor_laporan, status, tingkat_keparahan/kondisi_fisik
- State: loading, error, hamaPoints (List<MapPoint>), irigasiPoints (List<MapPoint>)
- Filter: tahun (default current year), type (hama/irigasi/all), status (all/aktif)

KOMPONEN 2 — MapScreen
Buat: mobile/lib/features/map/screens/map_screen.dart
- FlutterMap dengan tile OpenStreetMap
- Center default: Jember (-8.1728, 113.7024), zoom 10
- Layer toggle (BottomNavigationBar atau Chip):
  "Hama" | "Irigasi" | "Semua"
- Marker warna berbeda per jenis dan status:
  * Hama + Draf: pin abu-abu
  * Hama + Submitted: pin biru
  * Hama + Diverifikasi: pin hijau
  * Hama + Ditolak: pin merah
  * Irigasi: warna berbeda (cyan/teal tones)
- Tap marker: tampilkan popup kecil dengan nomor laporan + status + tombol "Detail"
- Tombol "Detail" pada popup navigasi ke HamaDetailScreen atau IrigasiDetailScreen

KOMPONEN 3 — Integrasi ke Navigasi
- Tambahkan route '/map' di AppRouter
- Tambahkan card "Peta Laporan Saya" di HomeScreen (untuk petugas)
- Tambahkan MapProvider ke MultiProvider di app.dart

[OUTPUT YANG DIHARAPKAN]
File baru:
- mobile/lib/features/map/providers/map_provider.dart
- mobile/lib/features/map/screens/map_screen.dart
File diubah:
- mobile/lib/core/router.dart (tambah route /map)
- mobile/lib/features/home/screens/home_screen.dart (tambah card peta)
- mobile/lib/app.dart (register MapProvider)

[KRITERIA SELESAI]
- Peta terbuka dan menampilkan tile OSM
- Marker laporan tampil di posisi koordinat yang benar
- Tap marker menampilkan popup
- Tombol Detail dari popup berhasil navigasi ke detail laporan
- Filter type (hama/irigasi) mengubah marker yang tampil
- Tidak crash jika laporan tidak memiliki koordinat
```

---

### PROMPT-14 — Riwayat Aktivitas Petugas (Opsional)

```
[KONTEKS PROYEK]
Proyek: JAGAPADI Mobile Flutter
Task: Tampilkan riwayat aktivitas petugas dari activity_log backend
Catatan: Perlu cek dulu apakah ada API endpoint untuk activity_log
         Jika belum ada endpoint, prompt ini mencakup juga pembuatan API-nya di backend

[FILE REFERENSI WAJIB DIBACA]
- docs/API.md (cari endpoint activity_log; jika tidak ada, perlu tambah)
- docs/DATABASE.md (tabel activity_log)
- backend/app/controllers/Api/ (lihat pola controller existing)
- mobile/lib/features/notifications/ (pola model/provider/screen yang bisa diikuti)
- AGENTS.md (aturan no schema change tanpa migration; PSR-12; prepared statement)

[INSTRUKSI]
Implementasikan fitur riwayat aktivitas:

LANGKAH 1 — Backend API (jika endpoint belum ada)
Buat: backend/app/controllers/Api/ActivityLogController.php

Endpoint baru:
  GET /api/v1/activity-log
  Auth: JWT (petugas hanya lihat milik sendiri, admin lihat semua)
  Query params: page=1, limit=20, action (filter aksi tertentu)
  Response: paginated list action log
  Field per item: id, action, table_name, record_id, description, created_at

Aturan controller (ikuti PSR-12, declare strict_types=1):
  - Gunakan PDO prepared statement
  - Petugas: WHERE user_id = :user_id (dari JWT payload)
  - Admin: tanpa filter user_id
  - Daftarkan route di backend routes/api.php

LANGKAH 2 — Flutter Model & Provider
Buat: mobile/lib/features/activity/models/activity_item.dart
- Field: id, action, tableName, recordId, description, createdAt
- fromJson() dengan null safety

Buat: mobile/lib/features/activity/providers/activity_provider.dart
- loadActivities(): paginated fetch
- loadMore(): infinite scroll
- refresh(): reset ke halaman pertama

LANGKAH 3 — Screen Riwayat
Buat: mobile/lib/features/activity/screens/activity_screen.dart
- ListView dengan item: icon aksi + deskripsi + timestamp relatif
- Icon semantik per aksi:
  * laporan_created: Icons.add_circle_outline (hijau)
  * laporan_submitted: Icons.send (biru)
  * laporan_updated: Icons.edit (oranye)
  * laporan_deleted: Icons.delete_outline (merah)
  * login: Icons.login (abu-abu)
  * change_password: Icons.lock_reset (ungu)
- Pull-to-refresh dan infinite scroll
- Empty state jika belum ada aktivitas

KOMPONEN 4 — Integrasi ke Navigasi
- Route: '/activity'
- Akses dari ProfileScreen sebagai item menu "Riwayat Aktivitas"

[OUTPUT YANG DIHARAPKAN]
File baru (backend, jika endpoint belum ada):
- backend/app/controllers/Api/ActivityLogController.php
File baru (mobile):
- mobile/lib/features/activity/models/activity_item.dart
- mobile/lib/features/activity/providers/activity_provider.dart
- mobile/lib/features/activity/screens/activity_screen.dart
File diubah:
- mobile/lib/core/router.dart (tambah route /activity)
- mobile/lib/features/profile/screens/profile_screen.dart (tambah menu item)
- mobile/lib/app.dart (register ActivityProvider)
- backend/routes/api.php atau sesuai pola routing (jika ada endpoint baru)

[KRITERIA SELESAI]
- GET /api/v1/activity-log mengembalikan data petugas dengan benar (scope user_id)
- Screen menampilkan daftar aktivitas dengan icon dan timestamp
- Infinite scroll bekerja
- Akses dari ProfileScreen berhasil
```


---

## BAGIAN C — RINGKASAN EKSEKUSI & URUTAN PRIORITAS

---

### C.1 Peta Jalan Pengembangan

```
FASE 0 — AUDIT (wajib pertama kali)
  └── PROMPT-00: Audit kode mobile existing

FASE 1 — PERBAIKAN DASAR (minggu 1-2)
  ├── PROMPT-01: Form UX (DatePicker, konfirmasi submit, foto preview)
  ├── PROMPT-02: Filter & search list laporan
  └── PROMPT-03: Dashboard statistik petugas

FASE 2 — OFFLINE-FIRST (minggu 2-3)  ⬅ fitur paling kompleks
  ├── PROMPT-04: Offline draft queue (sqflite + connectivity + sync)
  └── PROMPT-05: UI manajemen draf lokal

FASE 3 — DETAIL & NOTIFIKASI (minggu 3-4)
  ├── PROMPT-06: Detail laporan (timeline status + peta mini)
  └── PROMPT-07: Deep link notifikasi + mark-read otomatis

FASE 4 — KEAMANAN & POLISH (minggu 4)
  ├── PROMPT-08: Validasi input + error handling standar
  └── PROMPT-09: Skeleton loading + status badge + aksesibilitas

FASE 5 — TESTING (minggu 5)
  ├── PROMPT-10: Unit test provider & model
  └── PROMPT-11: Integration test end-to-end

FASE 6 — DEPLOYMENT (minggu 5-6)
  └── PROMPT-12: Build APK release + konfigurasi produksi

FASE 7 — OPSIONAL (sesuai kebutuhan)
  ├── PROMPT-13: Peta sebaran laporan milik sendiri
  └── PROMPT-14: Riwayat aktivitas petugas
```

---

### C.2 Matriks Dependensi Antar Prompt

| Prompt | Prasyarat | Bisa Paralel Dengan |
|---|---|---|
| PROMPT-00 | — | — |
| PROMPT-01 | PROMPT-00 | PROMPT-02, PROMPT-03 |
| PROMPT-02 | PROMPT-00 | PROMPT-01, PROMPT-03 |
| PROMPT-03 | PROMPT-00 | PROMPT-01, PROMPT-02 |
| PROMPT-04 | PROMPT-01 selesai | — |
| PROMPT-05 | PROMPT-04 selesai | — |
| PROMPT-06 | PROMPT-01 selesai | PROMPT-07 |
| PROMPT-07 | PROMPT-00 | PROMPT-06 |
| PROMPT-08 | PROMPT-01 selesai | PROMPT-09 |
| PROMPT-09 | PROMPT-06, PROMPT-07 | PROMPT-08 |
| PROMPT-10 | PROMPT-04, PROMPT-05 | PROMPT-11 |
| PROMPT-11 | PROMPT-08, PROMPT-09 | PROMPT-10 |
| PROMPT-12 | Semua Fase 0-5 selesai | — |
| PROMPT-13 | PROMPT-06 selesai | PROMPT-14 |
| PROMPT-14 | PROMPT-12 selesai | PROMPT-13 |

---

### C.3 Estimasi Effort Per Prompt

| Prompt | Nama | Kompleksitas | Estimasi Waktu |
|---|---|---|---|
| PROMPT-00 | Audit kode | Rendah | 2–4 jam |
| PROMPT-01 | Form UX | Sedang | 4–6 jam |
| PROMPT-02 | Filter & search | Sedang | 3–5 jam |
| PROMPT-03 | Dashboard statistik | Sedang | 3–4 jam |
| PROMPT-04 | Offline-first core | **Tinggi** | 8–12 jam |
| PROMPT-05 | UI draf lokal | Sedang | 3–5 jam |
| PROMPT-06 | Detail + peta mini | Sedang | 5–7 jam |
| PROMPT-07 | Notifikasi deep link | Sedang | 3–5 jam |
| PROMPT-08 | Validasi + error handler | Rendah | 3–4 jam |
| PROMPT-09 | Skeleton + polish | Sedang | 4–6 jam |
| PROMPT-10 | Unit test | Sedang | 5–8 jam |
| PROMPT-11 | Integration test | **Tinggi** | 6–10 jam |
| PROMPT-12 | Build release | Rendah | 2–4 jam |
| PROMPT-13 | Peta sebaran (opsional) | Sedang | 4–6 jam |
| PROMPT-14 | Riwayat aktivitas (opsional) | Sedang | 5–8 jam |
| | **TOTAL (wajib, Fase 0–6)** | | **~60–86 jam** |

---

### C.4 Aturan Penggunaan Prompt untuk Agen AI

Setiap kali menjalankan satu prompt kepada agen AI, sertakan konteks berikut
sebagai preambul wajib:

```
PREAMBUL WAJIB (sertakan sebelum setiap prompt):

Kamu bekerja pada proyek JAGAPADI — sistem pelaporan pertanian Flutter Android
untuk Kabupaten Jember. Stack: Flutter 3.x, Dart ^3.0.0, Provider, go_router,
Dio, flutter_secure_storage, FCM. Backend: PHP 8.2 REST API di /api/v1 dengan JWT.

Aturan wajib sebelum menulis kode:
1. Baca semua file referensi yang disebutkan di [FILE REFERENSI WAJIB DIBACA]
2. Ikuti pola kode yang sudah ada: ChangeNotifier Provider, Dio ApiClient, go_router
3. Jangan tambahkan dependency baru kecuali disebutkan eksplisit di instruksi
4. Semua string UI dalam Bahasa Indonesia
5. Jalankan flutter analyze setelah selesai; laporkan hasilnya
6. Jangan ubah file di luar daftar [OUTPUT YANG DIHARAPKAN]
7. Laporan akhir: daftar file yang diubah + ringkasan perubahan + risiko

Kemudian jalankan prompt di bawah ini:
[--- TEMPEL PROMPT YANG DIPILIH DI SINI ---]
```

---

### C.5 Kriteria Kualitas Global

Setiap prompt dianggap **selesai sempurna** jika memenuhi semua kondisi berikut:

| Kriteria | Cara Verifikasi |
|---|---|
| ✅ Kode berjalan tanpa error | `flutter run` berhasil di emulator/device |
| ✅ Analisis statis bersih | `flutter analyze` — 0 error, 0 warning kritis |
| ✅ Tidak ada regression | Fitur existing (login, laporan hama/irigasi, notifikasi) masih berfungsi |
| ✅ Role isolation | Fitur petugas tidak mempengaruhi tampilan/behavior admin |
| ✅ String Bahasa Indonesia | Semua teks UI berbahasa Indonesia |
| ✅ No secret in code | Tidak ada URL produksi, token, atau password hardcoded |
| ✅ Ikuti pola existing | Tidak ada pattern baru yang inkonsisten dengan kode yang ada |
| ✅ Laporan perubahan | Agen menyertakan daftar file yang diubah dan ringkasan |

---

## BAGIAN D — REFERENSI TEKNIS CEPAT

---

### D.1 Endpoint API yang Digunakan Role Petugas

| Method | Endpoint | Kegunaan |
|---|---|---|
| POST | `/api/v1/auth/login` | Login |
| POST | `/api/v1/auth/refresh` | Refresh token |
| POST | `/api/v1/auth/logout` | Logout |
| POST | `/api/v1/auth/change-password` | Ganti password |
| GET | `/api/v1/me` | Profil saya |
| GET | `/api/v1/wilayah/kabupaten` | List kabupaten |
| GET | `/api/v1/wilayah/kecamatan?kabupaten_id=` | List kecamatan |
| GET | `/api/v1/wilayah/desa?kecamatan_id=` | List desa |
| GET | `/api/v1/opt?aktif=1` | List OPT aktif |
| GET | `/api/v1/laporan-hama?include_draft=true` | List laporan hama milik sendiri |
| POST | `/api/v1/laporan-hama` | Buat draf/submit laporan hama |
| GET | `/api/v1/laporan-hama/{id}` | Detail laporan hama |
| PUT | `/api/v1/laporan-hama/{id}` | Edit draf laporan hama |
| DELETE | `/api/v1/laporan-hama/{id}` | Hapus draf laporan hama |
| POST | `/api/v1/laporan-hama/{id}/submit` | Submit laporan hama |
| POST | `/api/v1/laporan-hama/{id}/resubmit` | Kirim ulang laporan ditolak |
| POST | `/api/v1/laporan-hama/{id}/foto` | Upload foto laporan hama |
| POST | `/api/v1/laporan-hama/{id}/foto/delete` | Hapus foto laporan hama |
| GET | `/api/v1/laporan-irigasi?include_draft=true` | List laporan irigasi milik sendiri |
| POST | `/api/v1/laporan-irigasi` | Buat draf/submit laporan irigasi |
| GET | `/api/v1/laporan-irigasi/{id}` | Detail laporan irigasi |
| PUT | `/api/v1/laporan-irigasi/{id}` | Edit draf laporan irigasi |
| DELETE | `/api/v1/laporan-irigasi/{id}` | Hapus draf laporan irigasi |
| POST | `/api/v1/laporan-irigasi/{id}/submit` | Submit laporan irigasi |
| POST | `/api/v1/laporan-irigasi/{id}/resubmit` | Kirim ulang laporan ditolak |
| POST | `/api/v1/laporan-irigasi/{id}/foto` | Upload foto laporan irigasi |
| GET | `/api/v1/dashboard/stats?tahun=YYYY` | Statistik laporan milik sendiri |
| GET | `/api/v1/dashboard/map/hama` | GeoJSON peta laporan hama |
| GET | `/api/v1/dashboard/map/irigasi` | GeoJSON peta laporan irigasi |
| GET | `/api/v1/notifications` | List notifikasi |
| POST | `/api/v1/notifications/{id}/read` | Tandai notifikasi dibaca |
| POST | `/api/v1/device-tokens` | Daftarkan FCM token |
| DELETE | `/api/v1/device-tokens` | Hapus FCM token saat logout |

---

### D.2 Status Laporan — Quick Reference

| Status | Label UI | Bisa Edit? | Bisa Submit? | Bisa Hapus? | Bisa Upload Foto? |
|---|---|---|---|---|---|
| Draf | "Draf" | ✅ | ✅ | ✅ | ✅ |
| Submitted | "Dikirim" | ❌ | ❌ | ❌ | ❌ |
| Diverifikasi | "Diverifikasi" | ❌ | ❌ | ❌ | ❌ |
| Ditolak | "Ditolak" | ✅ | ✅ (resubmit) | ❌ | ✅ |
| Diarsipkan | "Diarsipkan" | ❌ | ❌ | ❌ | ❌ |

---

> **Dokumen ini adalah panduan hidup.** Update setiap kali ada perubahan signifikan pada
> arsitektur, API contract, atau prioritas fitur proyek JAGAPADI.
>
> Dibuat: Agustus 2026 | Versi: 1.0

