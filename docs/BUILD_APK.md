# Build APK JAGAPADI

Panduan singkat untuk membangun APK Flutter JAGAPADI dari folder `mobile/`.

## 1. Ringkasan

- Aplikasi Flutter berada di folder `mobile/`
- Backend API harus dapat diakses dari emulator/perangkat
- FCM adalah fitur opsional
- Jangan commit file rahasia seperti `google-services.json`, `*.jks`, atau `key.properties`

## 2. Prasyarat tools

- Flutter SDK
- Android Studio atau Android SDK command-line tools
- JDK 17
- Android SDK API level yang sesuai
- `flutter doctor` harus lolos bagian Android

## 3. Siapkan project Android (jika belum ada platform)

Folder `mobile/android/app` sudah ada di repo, tetapi struktur Android harus lengkap sebelum build.
Jika folder Android tidak lengkap, jalankan:

```powershell
cd c:\laragon\www\jagapadi-3509\mobile
flutter create . --platforms android
```

> Jangan menimpa folder `lib/` secara buta. Jalankan hanya jika Android platform belum ada atau tidak lengkap.

## 4. Konfigurasi API base URL

Aplikasi membaca base URL API dari `--dart-define=API_BASE_URL=...`.
Nilai default di `mobile/lib/core/config.dart` adalah:

- Emulator Android: `http://10.0.2.2:8080/api/v1`
- Device fisik / desktop: `http://localhost:8080/api/v1`

### Contoh dev (emulator)

```powershell
cd c:\laragon\www\jagapadi-3509\mobile
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1
```

### Contoh production

```powershell
flutter run --dart-define=API_BASE_URL=https://domain-anda.com/api/v1
```

> Untuk dev, `10.0.2.2` adalah host machine dari emulator Android.
> Untuk production, gunakan HTTPS tanpa trailing slash di akhir.

## 5. Firebase / FCM (opsional)

Jika ingin push notification:

- Siapkan project Firebase
- Download `google-services.json`
- Letakkan di `mobile/android/app/google-services.json`
- Backend harus memiliki `FCM_ENABLED=true` dan `FCM_SERVER_KEY` di `backend/.env`
- Repo sudah mengabaikan `mobile/android/app/google-services.json` dan `*.service_account.json`
- Template file tersedia di `mobile/android/app/google-services.json.example`

## 6. Build APK debug

```powershell
cd c:\laragon\www\jagapadi-3509\mobile
flutter pub get
flutter build apk --debug
```

Output biasanya di:

```text
mobile/build/app/outputs/flutter-apk/app-debug.apk
```

Install ke emulator/perangkat:

```powershell
adb install -r .\build\app\outputs\flutter-apk\app-debug.apk
```

## 7. Build APK release

### 7.1 Keystore

Buat keystore secara lokal dan simpan di luar repo:

```powershell
keytool -genkeypair -v -keystore C:\keystore\jagapadi-release.jks -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 -alias jagapadi
```

Buat file `android/key.properties` di luar kontrol versi dan isi:

```properties
storePassword=<keystore-password>
keyPassword=<key-password>
keyAlias=jagapadi
storeFile=C:\keystore\jagapadi-release.jks
```

Jika `android/app/build.gradle` belum dikonfigurasi, tambahkan signing config minimal untuk `release`.

### 7.2 Build release

```powershell
cd c:\laragon\www\jagapadi-3509\mobile
flutter build apk --release
```

Split per ABI:

```powershell
flutter build apk --release --split-per-abi
```

### 7.3 App Bundle (opsional)

```powershell
flutter build appbundle --release
```

## 8. Install & uji di perangkat

- Aktifkan USB debugging atau install dari sumber tidak dikenal
- Pastikan perangkat dan backend berada di jaringan yang sama jika menggunakan IP lokal
- Login petugas/admin
- Uji minimal:
  - login
  - daftar laporan
  - buat draft laporan
  - submit laporan
  - verifikasi jika role admin tersedia

## 9. Troubleshooting APK

- `flutter doctor` issue: perbaiki SDK/JDK/Android licenses
- `minSdk` / `compileSdk` error: cek Android SDK dan Gradle plugin
- Cleartext HTTP blocked: gunakan `10.0.2.2` untuk emulator atau HTTPS untuk device/production
- SSL handshake: cek sertifikat backend
- Connection refused: pastikan backend berjalan di port yang benar
- Firebase error: periksa `google-services.json` dan package name
- Signing gagal: cek `key.properties`, keystore, alias, dan password

## 10. Checklist rilis APK internal

- [ ] API URL production HTTPS
- [ ] Backend `APP_DEBUG=false`
- [ ] Keystore aman di luar git
- [ ] `versionName` / `versionCode` sesuai kebutuhan
- [ ] Login + 1 laporan hama + 1 laporan irigasi diuji
