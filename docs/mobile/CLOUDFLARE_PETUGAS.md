# JAGAPADI Mobile — Versi Petugas via Cloudflare (`https://jagapadi.my.id`)

> Fokus: APK **khusus Petugas Lapangan** yang konek ke server `localhost:8080` yang di-expose via **Cloudflare Tunnel** ke `https://jagapadi.my.id/dashboard`.

## Arsitektur

```
[HP Android Petugas]
      |  https://jagapadi.my.id/api/v1  (TLS Cloudflare)
      v
[Cloudflare Edge] -- Tunnel (cloudflared) --> http://localhost:8080
                                              backend/public (Laragon/php -S)
                                              |-> /api/v1/*  (JWT, mobile)
                                              |-> /dashboard (web)
      + DB: jagapadi_local (MariaDB)
```

* Backend v1 (`backend/public/index.php`) adalah **single source of truth** untuk web & mobile.
* Mobile **tidak** hit `/jagapadi-3509/api/v1` (itu runtime root legacy). Semua mobile pakai `/api/v1`.

## 1. Backend config (sudah di-update)

`backend/.env` (baris 3-6):

```ini
APP_BASE_URL=https://jagapadi.my.id
CORS_ALLOWED_ORIGINS=https://jagapadi.my.id,https://www.jagapadi.my.id,http://localhost:8080,http://localhost,http://10.0.2.2:8080,http://192.168.10.5:8080
```

Restart server setelah edit:

```powershell
taskkill /F /IM php.exe
php -S localhost:8080 -t backend/public
# atau via Laragon: Stop -> Start
```

Verifikasi lokal (tanpa Cloudflare):

```powershell
Invoke-RestMethod http://localhost:8080/api/v1/health | ConvertTo-Json
# -> {"success":true,"message":"JAGAPADI is healthy"...}

$body=@{username="petugas01";password="Jember3509"} | ConvertTo-Json
Invoke-RestMethod -Uri http://localhost:8080/api/v1/auth/login -Method Post -Body $body -ContentType "application/json"
# -> {"success":true,"data":{"token":"eyJ...","user":{"role":"petugas"}}}
```

## 2. Cloudflare Tunnel (localhost -> https://jagapadi.my.id)

### Opsi A — Quick tunnel (testing, 5 detik)

```powershell
cloudflared tunnel --url http://localhost:8080
# Output: https://random-words-1234.trycloudflare.com
# Test: curl https://random-words-1234.trycloudflare.com/api/v1/health
# Untuk mobile, build dengan URL itu:
# flutter run --dart-define=API_BASE_URL=https://random-words-1234.trycloudflare.com/api/v1
```

### Opsi B — Named tunnel (produksi, domain permanen)

```powershell
cloudflared tunnel login                # buka browser, pilih domain jagapadi.my.id
cloudflared tunnel create jagapadi-3509
cloudflared tunnel route dns jagapadi-3509 jagapadi.my.id  # buat DNS CNAME
# copy file mobile/cloudflare-config.example.yml -> %USERPROFILE%\.cloudflared\config.yml
# sesuaikan credentials-file path
cloudflared tunnel run jagapadi-3509
```

Template config ada di `mobile/cloudflare-config.example.yml`.

**Cek tunnel hidup:**

```powershell
curl.exe -s https://jagapadi.my.id/api/v1/health | head
# harus {"success":true}
# Web dashboard:
start https://jagapadi.my.id/dashboard
start https://jagapadi.my.id/login
```

## 3. Build APK Petugas

### File build yang sudah disiapkan

* `mobile/build-petugas-cloudflare.ps1` — debug/release khusus petugas (API = `https://jagapadi.my.id/api/v1`)
* `mobile/build-petugas-cloudflare.bat` — versi .bat
* `mobile/build-apk.ps1` / `.bat` — `PROD_URL` sekarang default `https://jagapadi.my.id/api/v1`
* `mobile/lib/core/config.dart` — comment Cloudflare ditambahkan, `baseUrl` tetap via `--dart-define`

### Build debug (langsung install)

```powershell
cd mobile
.\build-petugas-cloudflare.ps1                  # debug APK
# atau
.\build-petugas-cloudflare.ps1 -BuildType release  # release split-per-abi
```

APK ada di `mobile/build/app/outputs/flutter-apk/` :

* `app-arm64-v8a-debug.apk` — untuk HP modern (paling umum)
* `app-arm64-v8a-release.apk` — distribusi

Install:

```powershell
adb devices
adb install -r build/app/outputs/flutter-apk/app-arm64-v8a-debug.apk
```

### Build manual (tanpa script)

```powershell
cd mobile
flutter pub get
flutter build apk --debug --dart-define=API_BASE_URL=https://jagapadi.my.id/api/v1
flutter build apk --release --split-per-abi --dart-define=API_BASE_URL=https://jagapadi.my.id/api/v1
```

## 4. Cara pakai di HP (Petugas)

1. Install APK petugas di HP Android (Android 7+ / API 24+).
2. Buka app — akan ke `/login`.
3. Login Petugas (contoh, sesuai `daftar-user.md`):

   | Username | Password | Nama |
   |---|---|---|
   | `petugas01` | `Jember3509` | Petugas Lapangan 01 (testing) |
   | `nurulhamzah44@gmail.com` | `Jagapadi1!` | Nurul Hamzah (real) |
   | `operator@gmail.com` | `Jagapadi1!` | Operator |

   *Semua petugas real password `Jagapadi1!` (wajib ganti saat pertama login online).*
   *Admin: `admin` / `Jember3509*` (khusus debugging, jangan pakai di petugas).*

4. Setelah login, Home akan tampil **"Petugas Lapangan"** — menu:
   * Semua Laporan, Hama/OPT, Irigasi, Pupuk, Panen, Cuaca, Alat & Sarana, Usulan OPT, Notifikasi, Profil.
   * **Tidak ada** "Antrian Verifikasi" (hanya Admin). Petugas hanya lihat laporan **milik sendiri**.
   * Bottom nav: Beranda | Laporan | Sinkron | Profil.

5. Buat laporan: Laporan → Hama/Irigasi → `+` → isi form → Simpan Draf → Submit. Foto akan di-upload ke `https://jagapadi.my.id/api/v1/laporan-hama/{id}/foto`.

6. Sinkron offline: jika tunnel mati / HP offline, draf disimpan lokal (sqflite) → tombol **Sinkron** akan push ke server saat online (`Idempotency-Key`).

### Verifikasi petugas terhubung ke server yang benar

* Di Home, tarik refresh — jika tunnel mati akan muncul banner merah `Tidak dapat terhubung ke server (https://jagapadi.my.id/api/v1)`.
* Cek `adb logcat` :

  ```
  [ApiClient →] POST /auth/login
  [ApiClient ←] 200 /auth/login
  baseUrl=https://jagapadi.my.id/api/v1
  ```

## 5. Dashboard Web vs Mobile

* **Web Petugas:** https://jagapadi.my.id/dashboard (session + CSRF) — login sama (`petugas01`).
* **Mobile Petugas:** https://jagapadi.my.id/api/v1/* (JWT Bearer) — data sama, filter ownership di query (`user_id` dari JWT, bukan dari client).
* Laporan yang dibuat di mobile langsung muncul di web dashboard (dan sebaliknya) karena **DB sama** (`jagapadi_local`).

## 6. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| APK `NetworkError` / timeout | Tunnel `cloudflared` belum jalan atau `localhost:8080` mati | `netstat -ano \| findstr :8080` → harus LISTENING; `cloudflared tunnel run` |
| `Username atau password salah` di mobile tapi web bisa | Password petugas `Jagapadi1!` vs `Jember3509` (testing) | Pakai `Jagapadi1!` untuk akun email real, `Jember3509` hanya `petugas01` testing |
| `401 Missing credential` di health | Coba GET `/api/v1/health` tanpa token harus 200 success — jika 401 berarti server belum restart setelah `.env` | Restart `php -S` |
| Foto gagal upload (413/422) | Melebihi 2 MB atau magic bytes tidak valid | Kompres foto <2MB, pakai kamera, bukan screenshot PNG |
| Notifikasi tidak masuk | FCM belum enable | Build dengan `--dart-define=FCM_ENABLED=true` + `google-services.json` |

## 7. Checklist Go-Live Petugas

- [ ] `backend/.env` → `APP_BASE_URL=https://jagapadi.my.id` & `CORS_ALLOWED_ORIGINS` include domain
- [ ] Tunnel `cloudflared` running & `https://jagapadi.my.id/api/v1/health` → `{"success":true}`
- [ ] `flutter pub get` lulus, `flutter analyze` no error
- [ ] Build APK petugas via `build-petugas-cloudflare.ps1` berhasil (3 APK split)
- [ ] Test login petugas01 di HP fisik via jaringan seluler (bukan WiFi LAN) → ke `https://...` (bukan `10.0.2.2`)
- [ ] Buat 1 laporan Hama sebagai petugas → muncul di https://jagapadi.my.id/laporan-hama?status=Submitted
- [ ] Admin verifikasi di web → status jadi Diverifikasi → petugas lihat di mobile

## Referensi

* `mobile/lib/core/config.dart:30` — `AppConfig.baseUrl` (dart-define)
* `mobile/lib/core/api_client.dart` — Dio + JWT refresh + Idempotency-Key
* `mobile/README.md` — struktur fitur
* `backend/config/routes.php` — API public `/api/v1/auth/login`
* `daftar-user.md` — 47 akun petugas + password default
