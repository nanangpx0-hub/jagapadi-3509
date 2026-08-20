# Build & Distribusi APK JAGAPADI

> **Sistem Pelaporan Pertanian Kab. Jember — Role Petugas Lapangan**  
> Panduan resmi konfigurasi, signing, dan otomatisasi build release APK Flutter JAGAPADI.

---

## 1. Prasyarat Tools & SDK

- **Flutter SDK**: 3.x (Dart ^3.0.0)
- **JDK**: 17
- **Android SDK**: API 35 (minSdk 24)
- **Gradle**: 8.x
- `flutter doctor` harus hijau pada bagian Android toolchain.

---

## 2. Struktur Konfigurasi Signing & ProGuard

Konfigurasi build release menggunakan ProGuard / R8 minification dan signing otomatis via `key.properties`.

File terdaftar di repo:
- `mobile/android/app/proguard-rules.pro` — aturan ProGuard/R8
- `mobile/android/key.properties.example` — template konfigurasi keystore
- `mobile/scripts/build_release.sh` — script build otomatis (Linux/macOS)
- `mobile/scripts/build_release.bat` — script build otomatis (Windows)

---

## 3. Menyiapkan Keystore Production

Jalankan perintah berikut untuk membuat file keystore release:

```powershell
keytool -genkeypair -v -keystore C:\keystore\jagapadi-release.jks -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 -alias jagapadi
```

Salin template `mobile/android/key.properties.example` ke `mobile/android/key.properties`:

```properties
storeFile=C:/keystore/jagapadi-release.jks
storePassword=PASSWORD_KEYSTORE_ANDA
keyAlias=jagapadi
keyPassword=PASSWORD_KEY_ANDA
```

> **PERINGATAN SEKURITAS**: File `key.properties` dan `*.jks` **TIDAK BOLEH** di-commit ke Git repo! (sudah ada di `.gitignore`).

---

## 4. Menjalankan Build Release Otomatis

Gunakan script yang tersedia di `mobile/scripts/`:

### Windows

```cmd
cd c:\laragon\www\jagapadi-3509\mobile
.\scripts\build_release.bat https://jagapadi.jemberkab.go.id/api/v1
```

### Linux / macOS

```bash
cd mobile
chmod +x scripts/build_release.sh
./scripts/build_release.sh https://jagapadi.jemberkab.go.id/api/v1
```

Hasil build APK akan ditempatkan secara otomatis di folder `mobile/dist/`:
- `jagapadi-arm64-v8a-release.apk` (Perangkat modern 64-bit — disarankan)
- `jagapadi-armeabi-v7a-release.apk` (Perangkat Android lama 32-bit)
- `jagapadi-x86_64-release.apk` (Emulator 64-bit)

---

## 5. Parameter --dart-define

Parameter wajib yang harus disertakan saat build:

| Parameter | Contoh | Kegunaan |
|---|---|---|
| `--dart-define=API_BASE_URL` | `https://jagapadi.jemberkab.go.id/api/v1` | URL Backend API Production |

---

## 6. Checklist Sebelum Rilis Produksi

- [ ] `API_BASE_URL` menggunakan protocol HTTPS resmi
- [ ] Backend `APP_DEBUG=false` di `backend/.env`
- [ ] `key.properties` dan `*.jks` tersimpan aman & tidak ter-commit ke git
- [ ] `google-services.json` ada di `mobile/android/app/` (jika menggunakan FCM)
- [ ] `flutter analyze` 0 error / warning kritis
- [ ] Direct test login & offline draft queue di device fisik / emulator
