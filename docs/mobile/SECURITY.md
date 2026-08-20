# Keamanan Mobile JAGAPADI

Dokumen ini menjelaskan keputusan keamanan aplikasi mobile dan batasannya.

## 1. Prinsip

1. **UI bukan security boundary** — matriks permission hanya menyembunyikan
   aksi; otorisasi final dilakukan backend.
2. **Fail-closed** — role tidak dikenal / verifier versi tidak dikenal /
   data tidak valid = ditolak.
3. **Tanpa secret di repository** — kredensial hanya di
   `flutter_secure_storage`; `.env`/token/key tidak pernah masuk repo.
4. **Validasi berlapis** — validasi mobile adalah lapisan UX; backend
   tetap memvalidasi magic bytes/MIME/ukuran (lihat `docs/AUDIT_UPLOAD_OPENCODE.md`).

## 2. Kredensial & Login Offline

- Verifier offline diturunkan dengan PBKDF2 (iterasi tinggi) dan disimpan
  di `flutter_secure_storage` (lihat `core/offline_credentials.dart`).
- **Lockout**: `OfflineLockPolicy` — 5 percobaan gagal → akun terkunci
  5 menit (`offline_lock_until`). Percobaan saat terkunci tidak menambah
  hitungan; keberhasilan login mereset hitungan.
- **Versi verifier**: `OfflineVerifierPolicy.currentVersion = 1`.
  Verifier lama tanpa versi diterima sementara (migration bertahap);
  bila versi penyimpanan ≠ versi saat ini, login offline ditolak
  (fail-closed) sampai user login online sekali untuk menulis verifier baru.
- Login offline menolak user non-aktif dan user yang wajib ganti password.
- `offline_last_online_at` dicatat untuk audit dan peringatan data basi.

## 3. Penyimpanan Lokal

- Token & verifier: `flutter_secure_storage` (Keystore/Keychain).
- Draf laporan: SQLite biasa (`local_db.dart`, versi 3).
- **Batasan jujur**: data SQLite TIDAK dienkripsi. Menambahkan enkripsi
  membutuhkan dependency baru (`sqflite` + SQLCipher atau `drift` dengan
  enkripsi) di luar ruang lingkup tahap ini. Risiko: backup penuh Android
  (ADB backup) dapat berisi draf dalam bentuk teks. Mitigasi:
  - Draf hanya berisi data laporan petugas (bukan kredensial).
  - Token/verifier tidak pernah disimpan di SQLite.
  - Direkomendasikan: aktifkan `allowBackup="false"` atau
    `fullBackupContent` yang mengecualikan DB, dan pertimbangkan enkripsi
    pada tahap berikutnya.

## 4. Upload Foto

- `PhotoValidator` memeriksa: berkas ada & dapat dibaca, ukuran ≤ 10 MB,
  ekstensi diizinkan (jpg/jpeg/png/webp), magic bytes cocok dengan ekstensi.
- Nama berkas di server tetap di-generate acak oleh backend (tidak memakai
  nama dari perangkat).
- Validasi ini BUKAN dekompresi penuh; berkas "zip bomb"/polyglot lanjutan
  ditangani backend.

## 5. Permintaan (Request)

- Semua request mutasi memakai POST/PUT dengan token JWT dari
  `flutter_secure_storage` (dio interceptor).
- Request yang berpotensi di-retry membawa `Idempotency-Key` (dari
  `client_operation_id` draf) agar duplikasi dapat dicegah server.
- Retry otomatis 5xx hanya terjadi untuk request idempoten (membawa header
  tersebut); request lain tidak di-retry otomatis.
- Header `Idempotency-Key` diproses backend (`IdempotencyMiddleware`):
  replay respons tersimpan untuk key sama, 409 untuk key sama dengan
  payload berbeda, kunci unik mencegah duplikasi bersamaan. Tanpa header
  (request lama/klien lain), duplikasi tetap mungkin terjadi — perilaku
  yang sama seperti sebelum fitur ini.

## 6. Permintaan Izin (Permissions)

- Izin lokasi diminta hanya saat user menekan "Ambil Lokasi GPS"
  (permission_handler + geolocator), dengan pesan kontekstual:
  - Service mati → arahkan ke pengaturan lokasi.
  - Denied → pesan izin presisi.
  - DeniedForever → arahkan ke pengaturan aplikasi.
- Tidak ada izin yang diminta saat aplikasi pertama dibuka tanpa konteks.

## 7. Validasi Input

Semua input user melewati validator terpusat (`LaporanValidators`):
- Tanggal `YYYY-MM-DD` dalam rentang 2020–hari ini+1, komponen dicek
  ketat (2026-02-30 ditolak).
- Angka: format desimal, non-negatif/positif sesuai konteks, batas maks.
- Koordinat: lat ∈ [-90, 90], lng ∈ [-180, 180].
- Catatan: maksimal 2000 karakter (konsisten semua modul).
- Enum: hanya nilai yang diizinkan backend (persis daftar valid).
- HTML escape di sisi web tetap berlaku untuk output (aturan backend).