# JAGAPADI Mobile App

Flutter Android app for JAGAPADI — Laporan pertanian Kab. Jember.

## Prerequisites

- Flutter 3.x (Dart ^3.0.0)
- Android SDK (API 24+)
- Backend API running (default: `http://10.0.2.2:8080/api/v1` for emulator)

## Getting Started

```bash
cd mobile
flutter pub get
flutter run
```

Override API base URL:

```bash
flutter run --dart-define=API_BASE_URL=http://your-domain.com/api/v1
```

## Project Structure

```
lib/
├── core/              # Shared infrastructure
│   ├── config.dart    # API URL, timeouts, polling interval
│   ├── api_client.dart # Dio + JWT interceptor + refresh token
│   ├── secure_storage.dart
│   ├── fcm/fcm_service.dart  # Firebase Cloud Messaging
│   ├── theme.dart     # Material 3 theme
│   ├── router.dart    # go_router routes
│   ├── app.dart       # MaterialApp.router + providers
│   └── main.dart
├── features/          # Feature modules
│   ├── auth/          # Login, User model, AuthProvider
│   ├── hama/          # Laporan Hama (OPT)
│   ├── irigasi/       # Laporan Irigasi
│   ├── notifications/ # Notifikasi
│   ├── profile/       # Profil & Ubah Password
│   └── wilayah/       # Wilayah cascading picker
```

## Features

| Feature | Status |
|---------|--------|
| Login (JWT) | ✅ |
| Auth refresh token | ✅ |
| Must-change-password flow | ✅ |
| Laporan Hama (CRUD + Draft + Submit) | ✅ |
| Laporan Irigasi (CRUD + Draft + Submit) | ✅ |
| Admin: Verifikasi/Tolak/Arsip laporan | ✅ |
| Petugas: Kirim ulang (resubmit) | ✅ |
| Wilayah cascading picker | ✅ |
| GPS location | ✅ |
| Camera photo | ✅ |
| Foto existing tampil di detail | ✅ |
| 422 field errors mapped to form | ✅ |
| 429 handling (Too Many Requests) | ✅ |
| Notifications list (in-app) | ✅ |
| Notifikasi tap → detail laporan | ✅ |
| Notifikasi poll unread (60s) | ✅ |
| Profile & Change Password | ✅ |
| FCM Push Notification | ✅ (backend + Flutter) |

## FCM Push Notification Setup

Backend must have `FCM_ENABLED=true` and `FCM_SERVER_KEY` set in `.env`.

### Android setup

1. Create Firebase project at https://console.firebase.google.com
2. Add Android app with package name (default: `com.jagapadi.mobile`)
3. Download `google-services.json` → place at `android/app/google-services.json`
4. Run `flutter pub get` to sync Firebase dependencies

### Run with FCM

```bash
flutter run --dart-define=FCM_ENABLED=true
```

Without FCM (default for development):

```bash
flutter run
```

The app degrades gracefully — if Firebase is not configured, push is silently disabled and all other features work normally.

### Token lifecycle

- **Login**: FCM token fetched and POST to `/api/v1/device-tokens`
- **Token refresh**: Auto-registered via `onTokenRefresh` listener
- **Logout**: DELETE `/api/v1/device-tokens`
- **Foreground**: In-app notif polling still active (60s interval)
- **Background**: Data-only FCM message handled by top-level handler

## Dependencies

- `dio` — HTTP client with refresh token interceptor
- `flutter_secure_storage` — JWT token + user data storage
- `provider` — State management
- `go_router` — Navigation
- `geolocator` — GPS location
- `image_picker` — Camera capture
- `firebase_core` + `firebase_messaging` — FCM push
- `intl` — Date formatting
- `google_fonts` — Typography

## Testing

```bash
flutter test
```

## Admin vs Petugas

- **Admin**: Antrian verifikasi di home, tombol verifikasi/tolak/arsip di detail, melihat semua laporan.
- **Petugas**: Hanya laporan sendiri, tombol edit/submit/resubmit, tidak ada aksi admin.
