# Dokumentasi Akun Testing JAGAPADI

## Tujuan Dokumen

Dokumentasi ini bertujuan untuk memudahkan pengujian aplikasi JAGAPADI berdasarkan hak akses masing-masing role. Setiap role memiliki tingkat akses yang berbeda yang perlu diverifikasi selama proses development dan testing.

## Catatan Keamanan

**Password pada akun testing ini hanya untuk lingkungan lokal/development. JANGAN digunakan di environment production.**

Password default: `Jagapadi123!`

Jika aplikasi menggunakan PHP `password_hash()`, nilai password di SQL harus berupa hash bcrypt, bukan plain text. Jika belum yakin format hash aplikasi, gunakan salah satu opsi berikut:

1. Membuat hash menggunakan PHP
2. Menggunakan fitur reset password aplikasi jika tersedia

## Daftar Akun Testing

| No | Username | Role | Nama Lengkap | Email |
|----|----------|------|--------------|-------|
| 1 | admin_test | admin | Admin Testing JAGAPADI | admin_test@jagapadi.local |
| 2 | operator_test | operator | Operator Testing JAGAPADI | operator_test@jagapadi.local |
| 3 | viewer_test | viewer | Viewer Testing JAGAPADI | viewer_test@jagapadi.local |
| 4 | petugas_test | petugas | Petugas Testing JAGAPADI | petugas_test@jagapadi.local |

## Penjelasan Role

| Role | Fungsi | Hak Akses |
|------|--------|-----------|
| **admin** | Administrator sistem | Akses penuh ke semua fitur dan pengelolaan pengguna |
| **operator** | Operator lapangan | Pengelolaan data sesuai hak akses yang ditentukan |
| **viewer** | Viewer data | Hanya dapat melihat data dan dashboard |
| **petugas** | Petugas lapangan | Dapat membuat laporan hama dan mengakses fitur lapangan |

## Membuat Hash Password dengan PHP

Jalankan perintah berikut untuk membuat hash password:

```bash
php -r "echo password_hash('Jagapadi123!', PASSWORD_DEFAULT) . PHP_EOL;"
```

Salin hasil hash dan ganti placeholder `GANTI_DENGAN_HASH_PASSWORD` pada SQL di bawah.

## SQL Seed Akun Testing

```sql
INSERT INTO users 
(username, password, role, nama_lengkap, aktif, must_change_password, email, phone, created_at, updated_at)
VALUES
('admin_test', 'GANTI_DENGAN_HASH_PASSWORD', 'admin', 'Admin Testing JAGAPADI', 1, 0, 'admin_test@jagapadi.local', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('operator_test', 'GANTI_DENGAN_HASH_PASSWORD', 'operator', 'Operator Testing JAGAPADI', 1, 0, 'operator_test@jagapadi.local', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('viewer_test', 'GANTI_DENGAN_HASH_PASSWORD', 'viewer', 'Viewer Testing JAGAPADI', 1, 0, 'viewer_test@jagapadi.local', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
('petugas_test', 'GANTI_DENGAN_HASH_PASSWORD', 'petugas', 'Petugas Testing JAGAPADI', 1, 0, 'petugas_test@jagapadi.local', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
  password = VALUES(password),
  role = VALUES(role),
  nama_lengkap = VALUES(nama_lengkap),
  aktif = VALUES(aktif),
  must_change_password = VALUES(must_change_password),
  email = VALUES(email),
  phone = VALUES(phone),
  updated_at = CURRENT_TIMESTAMP;
```

## Cara Menjalankan SQL di MySQL Laragon

1. Buka **phpMyAdmin** melalui Laragon menu > MySQL > phpMyAdmin
2. Pilih database yang digunakan aplikasi JAGAPADI
3. Buka tab **SQL**
4. Paste query SQL di atas (setelah mengganti `GANTI_DENGAN_HASH_PASSWORD` dengan hash password yang sudah dibuat)
5. Klik **Go** untuk menjalankan query

Alternatif via command line:

```bash
mysql -u root -p nama_database < file_sql.sql
```

## Checklist Pengujian

### Login Testing
- [ ] Login sebagai admin_test
- [ ] Login sebagai operator_test
- [ ] Login sebagai viewer_test
- [ ] Login sebagai petugas_test

### Verifikasi Role & Hak Akses
- [ ] Cek menu yang muncul sesuai role
- [ ] Cek petugas bisa membuat laporan hama
- [ ] Cek viewer hanya dapat melihat data/dashboard
- [ ] Cek operator dapat mengelola data sesuai hak akses
- [ ] Cek admin memiliki akses penuh

---

*Dokumen ini dibuat untuk keperluan testing development. Password dan akun tidak boleh digunakan di production.*