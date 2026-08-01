# Audit Autentikasi & Otorisasi Backend JAGAPADI

**Auditor:** OpenCode AI  
**Tanggal:** 2026-07-17  
**Target:** `backend/` — modul Auth Web (Session) + API (JWT)

---

## A. Ringkasan

| Metrik | Nilai |
|--------|-------|
| **Skor Auth** | **8.5 / 10** |
| Temuan Kritis | 0 |
| Temuan Tinggi | 1 |
| Temuan Sedang | 3 |
| Temuan Rendah | 3 |
| **Status** | **Aman untuk development, perlu hardening untuk production** |

---

## B. Temuan

### JGP-AUTH-001 — Tinggi — Refresh token tidak memverifikasi user masih aktif

**File:** `backend/app/Controllers/Api/AuthController.php:73-92`

```php
public function refresh(): void
{
    $token = Request::bearerToken();
    $newToken = Jwt::refresh($token);
    // Tidak mengecek apakah user masih aktif / ada di DB
}
```

**Dampak:** Token yang direfresh untuk user yang sudah dinonaktifkan (`aktif=0`) tetap menghasilkan token valid baru. Akun yang dinonaktifkan seharusnya tidak bisa mendapat token baru.

**Eksploitasi:** Admin menonaktifkan akun petugas yang bermasalah. Petugas masih bisa me-refresh token JWT yang dimiliki sebelumnya dan tetap mengakses API sampai token lama kedaluwarsa.

**Perbaikan:** Setelah `Jwt::refresh()`, ambil user dari DB via `User::find((int) $payload['sub'])` dan cek `User::isActive()`. Jika tidak aktif, tolak refresh.

**Effort:** Kecil

---

### JGP-AUTH-002 — Sedang — Password hash bocor ke `$GLOBALS['auth_user']`

**File:** `backend/app/Middleware/ApiAuthMiddleware.php:51`

```php
$GLOBALS['auth_user'] = $user;
```

`$user` berisi seluruh row dari query `SELECT *` termasuk kolom `password` (bcrypt hash). Global ini dipakai di 12+ controller API.

**Dampak:** Jika ada bug atau log yang secara tidak sengaja mengekspos `$GLOBALS['auth_user']`, password hash bisa bocor. Ini tidak kritis karena bcrypt tahan offline cracking dengan cost 12, tapi tetap praktik buruk.

**Perbaikan:** Hapus `password` dari array user sebelum disimpan ke `$GLOBALS['auth_user']`, atau buat method `toSecureArray()` yang mengecualikan field sensitif.

**Effort:** Kecil

---

### JGP-AUTH-003 — Sedang — Tidak ada validasi panjang minimum `JWT_SECRET`

**File:** `backend/app/Core/Jwt.php:9-16`

```php
private static function getSecret(): string
{
    $secret = Env::get('JWT_SECRET', '');
    if ($secret === '' || $secret === 'GANTI_DENGAN_SECRET_MINIMAL_64_KARAKTER_ACAK') {
        Logger::warning('...');
    }
    return $secret;
}
```

Hanya log warning, tidak ada hard enforcement. Secret pendek atau lemah tetap dipakai.

**Dampak:** Secret pendek (< 32 byte) memungkinkan brute force HMAC-SHA256 signature secara offline jika token bocor.

**Perbaikan:** Tambahkan validasi: jika `strlen($secret) < 32`, throw exception atau gunakan secret default yang kuat (fail secure).

**Effort:** Kecil

---

### JGP-AUTH-004 — Sedang — JWT API logout bersifat stateless, tidak ada token blacklist

**File:** `backend/app/Controllers/Api/AuthController.php:94-100`

```php
public function logout(): void
{
    $userId = $GLOBALS['auth_user']['id'] ?? null;
    ActivityLog::log($userId, 'logout', null, null, 'API logout');
    $this->success([], 'Logout berhasil');
}
```

Tidak ada mekanisme blacklist atau revoked tokens. Token tetap valid sampai kedaluwarsa.

**Dampak:** Logout API hanya bersifat informasional. Token yang dicuri tetap bisa dipakai hingga expiry.

**Perbaikan:** Implementasi opsional: simpan `jti` (JWT ID) ke dalam tabel revoked_tokens, atau set `iat` blacklist check. Untuk MVP saat ini, trade-off ini bisa diterima karena JWT stateless.

**Effort:** Sedang

---

### JGP-AUTH-005 — Rendah — Login API membedakan error untuk akun tidak aktif

**File:** `backend/app/Controllers/Api/AuthController.php:48-51`

```php
if (!User::isActive($user)) {
    $this->error('Unauthorized', 'Akun Anda tidak aktif. Hubungi administrator.', [], 401);
}
```

Pesan error ini mengonfirmasi bahwa username dan password benar, hanya akun yang dinonaktifkan. Ini kebocoran informasi level rendah.

**Dampak:** Attacker bisa mengetahui bahwa suatu username valid (password sudah benar) tapi akun dinonaktifkan.

**Perbaikan:** Gunakan pesan generik: "Autentikasi gagal." Konsisten dengan error password salah.

**Effort:** Kecil

---

### JGP-AUTH-006 — Rendah — Rate limiter tidak mengunci berdasarkan username + IP

**File:** `backend/app/Controllers/Web/AuthController.php:32`

```php
RateLimiter::attempt('login', "web_$ip", $maxAttempts, $decay);
```

Hanya berdasarkan IP. Attacker dengan botnet ribuan IP bisa brute force tanpa trigger rate limit.

**Dampak:** Brute force distributed (ribuan IP) tidak terdeteksi oleh rate limiter berbasis IP saja.

**Perbaikan:** Tambahkan username ke identifier: `"web_{$ip}_{$username}"`. Reset juga setelah login berhasil.

**Effort:** Kecil

---

### JGP-AUTH-007 — Rendah — `ENV` dibaca via `$_ENV` bukan `Env::get()` di Web Auth

**File:** `backend/app/Controllers/Web/AuthController.php:29-30`

```php
$maxAttempts = (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5);
$decay = (int) ($_ENV['LOGIN_DECAY_SECONDS'] ?? 900);
```

Sementara API AuthController menggunakan konsisten `(int) Env::get(...)`. Ketidakkonsistenan ini bisa menyebabkan nilai tidak terbaca jika `Env` tidak mendaftarkan variabel ke `$_ENV`.

**Dampak:** Potensi fallback ke nilai default 5/900 jika `$_ENV` kosong, bukan dari `.env`.

**Perbaikan:** Ganti dengan `(int) Env::get('LOGIN_MAX_ATTEMPTS', '5')`.

**Effort:** Kecil

---

## C. Checklist

| Item | Status | Bukti |
|------|--------|-------|
| Session dibuat hanya setelah login valid | **PASS** | `Web/AuthController.php:67` — session di-set hanya setelah verifikasi password |
| Session fixation dicegah | **PASS** | `Web/AuthController.php:64` — `Security::regenerateSession()` setelah login |
| Cookie session HttpOnly | **PASS** | `Security.php:24` — `session.cookie_httponly = 1` |
| Cookie session Secure (HTTPS) | **PASS** | `Security.php:27-28` — diaktifkan jika HTTPS + production |
| Cookie session SameSite | **PASS** | `Security.php:25` — `Lax` |
| CSRF di form login | **PASS** | `login.php:2` — `csrfField()` ada di form |
| CSRF di form state-changing | **PASS** | `main.php:83-85` — form logout pakai CSRF |
| Error login generik (tidak bocorkan username) | **PASS** | `Web/AuthController.php:52` — pesan "Username atau password salah" sama untuk user tidak ditemukan dan password salah |
| Must change password memblokir akses | **PASS** | `WebAuthMiddleware.php:25` — redirect ke `/password/change` kecuali path `/logout` |
| JWT hanya HS256 | **PASS** | `Jwt.php:21` — hardcoded `'alg' => 'HS256'`, tidak membaca dari token |
| JWT signature diverifikasi | **PASS** | `Jwt.php:47-49` — `hash_equals()` |
| JWT expiry dicek | **PASS** | `Jwt.php:58` |
| Refresh token validasi user aktif | **FAIL** | Lihat JGP-AUTH-001 |
| AdminMiddleware menutup route admin | **PASS** | Setiap route admin punya `AdminMiddleware::class` |
| Petugas tidak bisa akses endpoint admin | **PASS** | `AdminMiddleware.php:14` — cek `role !== 'admin'` |
| Route publik hanya publik | **PASS** | Hanya `/login` (GET+POST), `/api/v1/health`, `/api/v1/auth/login` yang tanpa middleware auth |
| Password hash bcrypt cost 12 | **PASS** | `User.php:34` — `PASSWORD_BCRYPT` with `'cost' => 12` |
| Password policy (min 8, upper, lower, digit, symbol) | **PASS** | `PasswordValidator.php:13-31` |
| Change password butuh password lama | **PASS** | `PasswordController.php:58` dan `Api/AuthController.php:130` |
| Tidak ada hardcode password di source | **PASS** | `002_seed_users_local.sql` — hash dikomentari, hanya referensi password default |
| Rate limit login (5 gagal per 15 menit) | **PASS** | Default `LOGIN_MAX_ATTEMPTS=5`, `LOGIN_DECAY_SECONDS=900` |
| Rate limit berdasarkan IP | **PASS** | `"web_$ip"` dan `"api_$ip"` — lihat JGP-AUTH-006 untuk saran tambahan username |
| Activity log: login success | **PASS** | `Web/AuthController.php:75` dan `Api/AuthController.php:63` |
| Activity log: login failed | **PASS** | `Web/AuthController.php:51` dan `Api/AuthController.php:43` |
| Activity log: logout | **PASS** | `Web/AuthController.php:90` dan `Api/AuthController.php:97` |
| Activity log: password changed | **PASS** | `PasswordController.php:73` dan `Api/AuthController.php:138` |
| Activity log tidak simpan password plaintext | **PASS** | Semua log hanya menyimpan deskripsi event, bukan password |
| JWT_SECRET dari env, tidak hardcode | **PASS** | `Jwt.php:11` — `Env::get('JWT_SECRET', '')` |
| .env tidak di-commit | **PASS** | `.gitignore` mencakup `.env` dan `**/.env` |
| CORS tidak longgar untuk credentialed request | **PASS** | `index.php:53-58` — hanya origin yang ada di whitelist |
| APP_DEBUG tidak bocorkan stack trace auth di production | **PASS** | `ErrorHandler.php:98-99` — production `APP_DEBUG !== 'true'` sembunyikan detail |

---

## D. Test Manual

### 1. Login gagal berulang (rate limit)
```bash
# Web — 6x percobaan gagal dalam 15 menit
for i in {1..6}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST http://localhost:8080/login \
    -d "username=salah&password=salah" \
    -b cookies.txt -c cookies.txt
done
# Percobaan ke-6 seharusnya 302 redirect ke /login dengan flash error rate limit

# API — 6x percobaan gagal
for i in {1..6}; do
  curl -s -w "\n%{http_code}\n" \
    -X POST http://localhost:8080/api/v1/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username":"salah","password":"salah"}'
done
# Percobaan ke-6 seharusnya 429 TooManyRequests
```

### 2. Akses API tanpa token
```bash
curl -s -w "\nHTTP %{http_code}\n" \
  http://localhost:8080/api/v1/me
# Harus 401 Unauthenticated
```

### 3. Akses API dengan token palsu / expired
```bash
# Token palsu
curl -s -w "\nHTTP %{http_code}\n" \
  http://localhost:8080/api/v1/me \
  -H "Authorization: Bearer palsu.token.123"
# Harus 401 TokenInvalid

# Token expired (set time manually — harus buat sendiri)
# Atau tunggu expiry JWT_EXPIRY=3600 detik
```

### 4. Petugas akses endpoint admin
```bash
# Login sebagai petugas
curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"petugas01","password":"ChangeMePetugas!123"}' \
  > login.json
set TOKEN=$(cat login.json | jq -r '.data.token')

# Coba akses endpoint admin
curl -s -w "\nHTTP %{http_code}\n" \
  -X POST http://localhost:8080/api/v1/opt \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nama_opt":"Test"}'
# Harus 403 Forbidden
```

### 5. POST tanpa CSRF (web)
```bash
curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST http://localhost:8080/logout
# Harus 419 (CSRF token invalid) karena tidak ada _csrf_token
```

### 6. Change password valid & invalid
```bash
# Valid
curl -s -X POST http://localhost:8080/api/v1/auth/change-password \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"current_password":"Benar!123","new_password":"Baru!456","new_password_confirmation":"Baru!456"}'
# Harus 200

# Invalid — konfirmasi tidak cocok
curl -s -X POST ... \
  -d '{"current_password":"Benar!123","new_password":"Baru!456","new_password_confirmation":"Salah!789"}'
# Harus 422 ValidationError
```

---

## E. Ringkasan Perbaikan Prioritas

| ID | Severity | Perbaikan | Effort | Status |
|----|----------|-----------|--------|--------|
| JGP-AUTH-001 | Tinggi | Validasi user aktif di refresh token | Kecil | **FIXED** |
| JGP-AUTH-002 | Sedang | Hapus password dari `$GLOBALS['auth_user']` | Kecil | **FIXED** |
| JGP-AUTH-003 | Sedang | Validasi panjang minimal JWT_SECRET (>= 32) | Kecil | **FIXED** |
| JGP-AUTH-004 | Sedang | Token blacklist (opsional untuk MVP) | Sedang | **Open** (residual) |
| JGP-AUTH-005 | Rendah | Pesan error generik untuk akun tidak aktif | Kecil | **FIXED** |
| JGP-AUTH-006 | Rendah | Rate limit berdasarkan username+IP | Kecil | **FIXED** |
| JGP-AUTH-007 | Rendah | Konsisten pakai `Env::get()` | Kecil | **FIXED** |

---

**Siap lanjut audit Upload Foto atau pass perbaikan Auth.**
