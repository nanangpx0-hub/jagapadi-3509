# Password Reset & Force Change — Implementasi & Dokumentasi

## Ringkasan

Password semua akun pengguna telah direset ke `Jember3509` (sementara) untuk semua
peran. Setiap pengguna **wajib mengganti password** setelah login pertama.

**Password sementara:** `Jember3509`
**Semua akun:** `must_change_password = 1` (paksa ganti setelah login)

---

## 1. Daftar Peran Pengguna

Berdasarkan skema database (`migration 012_add_user_roles_to_enum.sql`), peran yang
terdaftar di sistem:

| # | Role      | Keterangan                          | Jumlah |
|---|-----------|-------------------------------------|--------|
| 1 | `admin`   | Administrator sistem                | 1      |
| 2 | `petugas` | Petugas lapangan (hama & irigasi)     | 4      |
| 3 | `operator`| Operator irigasi                    | 1      |
| 4 | `statistisi` | Statistisi/data analyst          | 1      |
| 5 | `viewer`  | Read-only (didefinisikan di ENUM)   | 0      |

**Total pengguna di database: 7** (semua di-reset)

---

## 2. Perubahan Kode

### 2.1 `backend/app/Models/User.php`

**Method baru:**

```php
public static function resetPassword(int $userId, string $newHash): bool
```

Menimpa password dengan hash bcrypt baru, mengatur `must_change_password = 1`,
dan memperbarui `last_password_change_at = NOW()`. Berbeda dengan
`updatePassword()` yang mengatur `must_change_password = 0` (untuk flow ganti
password oleh pengguna), method ini khusus untuk skenario reset paksa oleh admin.

```php
public static function getAllByRole(): array
```

Mengambil semua pengguna diurutkan per peran dan ID, digunakan oleh script reset.

### 2.2 `backend/app/Middleware/ApiAuthMiddleware.php`

**Perubahan:** Setelah otentikasi JWT berhasil, middleware kini memeriksa flag
`must_change_password` pada akun pengguna. Jika bernilai `true`:

- Endpoint `/api/v1/auth/change-password` — **diizinkan** (200)
- Endpoint `/api/v1/auth/logout` — **diizinkan** (200)
- Endpoint lainnya — **diblokir** (403) dengan respons:
  ```json
  {"success":false,"error":"PasswordChangeRequired","message":"...","must_change_password":true}
  ```

Ini memastikan pengguna mobile/juga dipaksa mengganti password sebelum mengakses
fitur aplikasi.

### 2.3 `backend/app/Controllers/Api/AuthController.php`

**Perubahan:** JWT payload saat login kini termasuk klaim `must_change_password`,
sehingga aplikasi mobile dapat mengecek flag ini langsung dari token tanpa perlu
memanggil endpoint `/me`.

```json
{"sub":1,"role":"admin","username":"admin","must_change_password":true,"exp":...,"iat":...,"jti":"..."}
```

Respons login API tetap mengembalikan `must_change_password` di objek user melalui
`User::toPublicArray()`.

---

## 3. Script Reset Password

### `backend/scripts/reset_passwords.php`

Script maintenance CLI untuk me-reset semua password ke password sementara.

**Fitur:**
- Mengidentifikasi semua peran di database
- Mengambil semua pengguna (atau berdasarkan filter `--role=admin,petugas`)
- Hash `Jember3509` dengan bcrypt (PASSWORD_BCRYPT, cost=12)
- Update `password`, `must_change_password=1`, `last_password_change_at=NOW()`
- Logging audit per-user (`password_reset`) dan batch (`password_reset_batch`) ke tabel `activity_log`
- Dukungan `--dry-run` untuk simulasi tanpa mengubah DB
- Guard: tidak boleh dijalankan di lingkungan production (`APP_ENV=production`)

**Penggunaan:**

```bash
cd backend

# Reset semua user (local/dev)
php scripts/reset_passwords.php

# Simulasi tanpa mengubah DB
php scripts/reset_passwords.php --dry-run

# Reset hanya role tertentu
php scripts/reset_passwords.php --role=admin,petugas
```

---

## 4. Alur Paksa Ganti Password

### Web (Session-based)

1. Pengguna login ke `https://domain.tld/login` dengan `Jember3509`
2. `WebAuthController::login()` mendeteksi `must_change_password=1`
3. Otomatis redirect ke `/password/change`
4. Pengguna memasukkan password lama (`Jember3509`) dan password baru
5. Password baru divalidasi oleh `PasswordValidator` (min 8 kar, huruf besar, huruf
   kecil, angka, karakter khusus)
6. `PasswordController::change()` memperbarui password dan set `must_change_password=0`
7. Pengguna dialihkan ke dashboard

`WebAuthMiddleware` juga mencegah akses ke semua endpoint dilindungi (`/dashboard`,
`/laporan`, dll) ketika `must_change_password=1`, kecuali `/password/change` dan
`/logout`.

### API / Mobile (JWT)

1. Aplikasi mobile POST ke `/api/v1/auth/login` dengan `Jember3509`
2. Respons login mengembalikan `must_change_password: true` di objek user
3. JWT token juga mengandung klaim `must_change_password: true`
4. Akses ke semua endpoint API (kecuali `/api/v1/auth/change-password` dan
   `/api/v1/auth/logout`) dikembalikan dengan HTTP 403:
   ```json
   {"success":false,"error":"PasswordChangeRequired","message":"...","must_change_password":true}
   ```
5. Aplikasi mobile menampilkan screen ganti password
6. POST ke `/api/v1/auth/change-password` dengan `current_password` (Jember3509),
   `new_password`, dan `new_password_confirmation`
7. Password baru divalidasi; `must_change_password` di-set ke 0
8. Akses ke semua endpoint API kini diizinkan

---

## 5. Audit Log

Setiap perubahan password tercatat di tabel `activity_log` dengan format:

| Field       | Nilai                                                        |
|-------------|---------------------------------------------------------------|
| `user_id`   | ID pengguna yang password-nya direset                        |
| `action`    | `password_reset` (per-user) / `password_reset_batch` (batch) |
| `table_name`| `users`                                                      |
| `record_id` | ID pengguna / NULL (batch)                                   |
| `description` | Deskripsi lengkap termasuk alasan dan instruksi             |
| `ip_address`| IP request                                                   |
| `created_at`| Timestamp                                                    |

Query melihat log:
```sql
SELECT * FROM activity_log WHERE action IN ('password_reset','password_reset_batch')
ORDER BY created_at DESC;
```

---

## 6. Hasil Pengujian

| Pengujian                                    | Status |
|----------------------------------------------|--------|
| Semua 7 user (4 role) login API dengan `Jember3509` | ✅ |
| `must_change_password=true` di respons login | ✅ |
| `must_change_password` ada di JWT payload    | ✅ |
| API endpoint diblokir (403) ketika must_change | ✅ |
| `/api/v1/auth/change-password` bisa diakses  | ✅ |
| Akun bisa ganti password, lalu akses API     | ✅ |
| 180 unit test lulus (termasuk 13 baru)       | ✅ |
| PHP lint semua file lulus                    | ✅ |

---

## 7. File yang Berubah

| File | Perubahan |
|------|-----------|
| `backend/app/Models/User.php` | + `resetPassword()`, + `getAllByRole()` |
| `backend/app/Middleware/ApiAuthMiddleware.php` | + `must_change_password` enforcement (403 block) |
| `backend/app/Controllers/Api/AuthController.php` | + `must_change_password` di JWT payload |
| `backend/scripts/reset_passwords.php` | **Baru** — script reset password massal |
| `backend/tests/Unit/PasswordResetTest.php` | **Baru** — 13 unit test |
