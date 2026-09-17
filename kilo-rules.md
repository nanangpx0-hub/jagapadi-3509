# kilo-rules.md — Instruksi Kerja Kilo & Kiro untuk Repositori JAGAPADI

Instruksi ini berlaku untuk AI coding assistant Kilo dan Kiro saat beroperasi di repositori JAGAPADI (Sistem Pelaporan & Pemantauan Pertanian Jember).

---

## 1. Peta Runtime & Larangan Kritis
- **Root Runtime (`index.php`)**: Web server-rendered aplikasi & dashboard.
  - File `config/web_routes.php` **DIBEKUKAN pada 136 rute** oleh pengujian `tests/Compatibility/RootCompatibilityTest.php`.
  - **DILARANG** menambahkan array rute baru di `config/web_routes.php`.
  - Gunakan auto-dispatch fallback di `index.php` untuk aksi controller baru (contoh: `/storytelling/exportCsv` langsung memanggil `StorytellingController::exportCsv()`).
- **Backend v1 Runtime (`backend/public/index.php`)**: Target canonical REST API `/api/v1` dengan autentikasi JWT.
  - Rute dikelola terpisah di `backend/config/routes.php`.

---

## 2. Aturan Bisnis & Status Laporan
```
Draf → Submitted → Diverifikasi
              └→ Ditolak → Draf
                         └→ Submitted (resubmit pemilik)
```
- Status resmi di database: `'Draf'`, `'Submitted'`, `'Diverifikasi'`, `'Ditolak'` (status `'Diarsipkan'` telah dihapus).
- Label visual UI: `'Dikirim'` (tetapi nilai database dan query wajib tetap `'Submitted'`).
- Query agregat, grafik, peta sebaran, dan ekspor data default **TIDAK BOLEH** memasukkan data berstatus `'Draf'`.
- Status `'Draf'` tidak boleh diverifikasi.
- Role `admin` adalah satu-satunya yang berhak memverifikasi atau menolak laporan (`'Diverifikasi'`, `'Ditolak'`). Laporan berstatus `'Diverifikasi'` adalah status final.
- Role `petugas` hanya boleh melihat dan mengedit data miliknya sendiri (Ownership checking wajib dari sesi/JWT authenticated user).

---

## 3. Standar Coding & Security
- **Strict Typing**: Setiap file PHP diawali `declare(strict_types=1);`.
- **SQL Injection**: Wajib memakai prepared statements PDO (`$stmt->prepare()` dan binding). Dilarang menyusun SQL mentah dari variabel input.
- **XSS Escaping**: Semua cetakan variabel dinamis pada template HTML wajib dibungkus `htmlspecialchars((string)$var, ENT_QUOTES, 'UTF-8')` atau helper `e()`.
- **CSRF Token**: Form mutasi (POST/PUT/DELETE) wajib memvalidasi CSRF token.
- **PSR-12**: Format rapi, 4 spasi indent, LF line endings, UTF-8.

---

## 4. Validasi Mandiri
Sebelum mengakhiri task atau commit, jalankan verifikasi test:
```bash
php vendor/bin/phpunit
```
Pastikan seluruh test (354+ assertions) berstatus hijau (100% Pass).
