# Panduan Penghapusan / Pengosongan Data Laporan JAGAPADI

> **Tujuan**: Menghapus seluruh data laporan di tabel `laporan_hama`, `laporan_irigasi`, `laporan_lainnya`, `laporan_alat_sarana`, `laporan_cuaca`, `laporan_panen`, dan `laporan_pupuk` tanpa merusak struktur tabel, foreign key, dan format dokumen yang ada.
> **Ruang Lingkup**: Hanya data laporan. Data master (users, wilayah, OPT, dll) **tidak** dihapus.
> **Penting**: Selalu backup database sebelum menjalankan perintah penghapusan.

---

## 1. Persiapan

### 1.1 Backup Database
```bash
# Dari direktori backend
cd C:\laragon\www\jagapadi-3509\backend

# Backup lengkap
mysqldump -u root -p jagapadi_local > backup_jagapadi_laporan_$(date +%Y%m%d_%H%M%S).sql

# Atau jika tanpa password
mysqldump -u root jagapadi_local > backup_jagapadi_laporan_$(date +%Y%m%d_%H%M%S).sql
```

### 1.2 Verifikasi Koneksi Database
```bash
# Test koneksi
mysql -u root -p -e "USE jagapadi_local; SHOW TABLES LIKE '%laporan%';"
```

---

## 2. Identifikasi Tabel Terkait

Sebelum menghapus, pahami relasi data yang ada:

| Tabel Utama | Tabel Terkait | Relasi | Keterangan |
|--------------|----------------|--------|------------|
| `laporan_hama` | `laporan_hama_tags` | 1:N | Tag per laporan hama |
| `laporan_hama` | `honor_pelaporan` | 1:N | Data honor eksternal per laporan |
| `laporan_hama` | `laporan_status_history` | 1:N | Riwayat status laporan hama |
| `laporan_irigasi` | — | — | Tidak memiliki child table khusus |
| `laporan_lainnya` | — | — | Tidak memiliki child table khusus |
| `laporan_alat_sarana` | — | — | Tidak memiliki child table khusus |
| `laporan_cuaca` | — | — | Tidak memiliki child table khusus |
| `laporan_panen` | — | — | Tidak memiliki child table khusus |
| `laporan_pupuk` | — | — | Tidak memiliki child table khusus |
| `nomor_laporan_counter` | Semua laporan | Counter | Nomor laporan harian per prefix |

> **Catatan**: `activity_log` dan `notifications` tidak memiliki FK langsung ke tabel laporan, tetapi menyimpan referensi. Bersihkan jika ingin total reset.

---

## 3. Metode Penghapusan

Ada 2 metode yang bisa dipilih:

### Metode A: TRUNCATE (Cepat, Reset AUTO_INCREMENT)
- Mengosongkan tabel dan reset `id` ke 1
- Lebih cepat untuk dataset besar
- Tidak men-trigger `ON DELETE CASCADE` di foreign key
- **Hanya aman jika tidak ada foreign key constraint yang aktif**

### Metode B: DELETE dengan ORDER (Aman, Bertahap)
- Menghapus data berurutan dari tabel child ke parent
- Men-trigger `ON DELETE CASCADE` jika dikonfigurasi
- Lebah aman untuk relasi kompleks
- AUTO_INCREMENT tetap melanjutkan dari nilai sebelumnya

---

## 4. Langkah-langkah Eksekusi

### Opsi 1: Menggunakan Script PHP (Recommended)

Buat file `backend/tmp_purge_laporan.php`:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use App\Core\Env;
use App\Core\Database;

Env::load('.env');

$start = microtime(true);
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== MULAI PENGHAPUSAN DATA LAPORAN ===\n\n";

// 1. Backup terlebih dahulu (opsional, bisa dilewati jika sudah backup manual)
$backupFile = __DIR__ . '/backup_laporan_sebelum_purge_' . date('Ymd_His') . '.sql';
exec("mysqldump -u " . escapeshellarg(Env::get('DB_USER')) . " " . escapeshellarg(Env::get('DB_NAME')) . " laporan_hama laporan_irigasi laporan_lainnya laporan_alat_sarana laporan_cuaca laporan_panen laporan_pupuk laporan_hama_tags honor_pelaporan laporan_status_history nomor_laporan_counter > " . escapeshellarg($backupFile));
echo "Backup tabel laporan: {$backupFile}\n";

// 2. Matikan foreign key check sementara
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

// 3. Hapus data berurutan dari tabel child ke parent

// 3a. Hapus tags laporan hama
$stmt = $pdo->exec('DELETE FROM laporan_hama_tags');
echo "laporan_hama_tags: {$stmt} baris dihapus\n";

// 3b. Hapus honor pelaporan
$stmt = $pdo->exec('DELETE FROM honor_pelaporan');
echo "honor_pelaporan: {$stmt} baris dihapus\n";

// 3c. Hapus riwayat status laporan hama
$stmt = $pdo->exec('DELETE FROM laporan_status_history');
echo "laporan_status_history: {$stmt} baris dihapus\n";

// 3d. Hapus data laporan hama
$stmt = $pdo->exec('DELETE FROM laporan_hama');
echo "laporan_hama: {$stmt} baris dihapus\n";

// 3e. Hapus data laporan irigasi
$stmt = $pdo->exec('DELETE FROM laporan_irigasi');
echo "laporan_irigasi: {$stmt} baris dihapus\n";

// 3f. Hapus data laporan lainnya
$stmt = $pdo->exec('DELETE FROM laporan_lainnya');
echo "laporan_lainnya: {$stmt} baris dihapus\n";

// 3g. Hapus data laporan alat sarana
$stmt = $pdo->exec('DELETE FROM laporan_alat_sarana');
echo "laporan_alat_sarana: {$stmt} baris dihapus\n";

// 3h. Hapus data laporan cuaca
$stmt = $pdo->exec('DELETE FROM laporan_cuaca');
echo "laporan_cuaca: {$stmt} baris dihapus\n";

// 3i. Hapus data laporan panen
$stmt = $pdo->exec('DELETE FROM laporan_panen');
echo "laporan_panen: {$stmt} baris dihapus\n";

// 3j. Hapus data laporan pupuk
$stmt = $pdo->exec('DELETE FROM laporan_pupuk');
echo "laporan_pupuk: {$stmt} baris dihapus\n";

// 3k. Reset counter nomor laporan (opsional)
$stmt = $pdo->exec('TRUNCATE TABLE nomor_laporan_counter');
echo "nomor_laporan_counter: counter direset\n";

// 3l. Bersihkan activity_log terkait laporan (opsional)
$stmt = $pdo->exec('DELETE FROM activity_log WHERE table_name IN (\'laporan_hama\', \'laporan_irigasi\', \'laporan_lainnya\', \'laporan_alat_sarana\', \'laporan_cuaca\', \'laporan_panen\', \'laporan_pupuk\')');
echo "activity_log (laporan): {$stmt} baris dihapus\n";

// 3m. Bersihkan notifications terkait laporan (opsional, jika ada kolom referensi)
// Uncomment jika tabel notifications memiliki kolom like laporan_id/table_name
// $stmt = $pdo->exec('DELETE FROM notifications WHERE table_name IN (\'laporan_hama\', \'laporan_irigasi\', \'laporan_lainnya\', \'laporan_alat_sarana\', \'laporan_cuaca\', \'laporan_panen\', \'laporan_pupuk\') OR data LIKE \'%laporan%\'');

// 4. Aktifkan kembali foreign key check
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

// 5. Verifikasi struktur tabel tetap intact
$tables = ['laporan_hama', 'laporan_irigasi', 'laporan_lainnya', 'laporan_alat_sarana', 'laporan_cuaca', 'laporan_panen', 'laporan_pupuk'];
foreach ($tables as $table) {
    $result = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nStruktur tabel `{$table}` (jumlah kolom: " . count($result) . "):\n";
    foreach ($result as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
}

$duration = round(microtime(true) - $start, 2);
echo "\n=== SELESAI ({$duration}s) ===\n";
```

Jalankan script:
```bash
cd C:\laragon\www\jagapadi-3509\backend
php tmp_purge_laporan.php
```

### Opsi 2: Langsung via MySQL CLI

```sql
-- 1. Matikan foreign key check
SET FOREIGN_KEY_CHECKS = 0;

-- 2. Hapus data berurutan
DELETE FROM laporan_hama_tags;
DELETE FROM honor_pelaporan;
DELETE FROM laporan_status_history;
DELETE FROM laporan_hama;
DELETE FROM laporan_irigasi;
DELETE FROM laporan_lainnya;
DELETE FROM laporan_alat_sarana;
DELETE FROM laporan_cuaca;
DELETE FROM laporan_panen;
DELETE FROM laporan_pupuk;
TRUNCATE TABLE nomor_laporan_counter;

-- Opsional: Bersihkan activity_log dan notifications
DELETE FROM activity_log WHERE table_name IN ('laporan_hama', 'laporan_irigasi', 'laporan_lainnya', 'laporan_alat_sarana', 'laporan_cuaca', 'laporan_panen', 'laporan_pupuk');
-- DELETE FROM notifications WHERE table_name IN ('laporan_hama', 'laporan_irigasi', 'laporan_lainnya', 'laporan_alat_sarana', 'laporan_cuaca', 'laporan_panen', 'laporan_pupuk') OR data LIKE '%laporan%';

-- 3. Aktifkan kembali
SET FOREIGN_KEY_CHECKS = 1;

-- 4. Verifikasi
SELECT COUNT(*) FROM laporan_hama;           -- Harus 0
SELECT COUNT(*) FROM laporan_irigasi;         -- Harus 0
SELECT COUNT(*) FROM laporan_lainnya;         -- Harus 0
SELECT COUNT(*) FROM laporan_alat_sarana;     -- Harus 0
SELECT COUNT(*) FROM laporan_cuaca;           -- Harus 0
SELECT COUNT(*) FROM laporan_panen;           -- Harus 0
SELECT COUNT(*) FROM laporan_pupuk;           -- Harus 0
```

---

## 5. Verifikasi Pasca-Penghapusan

### 5.1 Verifikasi Data
```sql
-- Semua tabel laporan harus kosong
SELECT COUNT(*) AS total_hama FROM laporan_hama;           -- 0
SELECT COUNT(*) AS total_irigasi FROM laporan_irigasi;     -- 0
SELECT COUNT(*) AS total_lainnya FROM laporan_lainnya;     -- 0
SELECT COUNT(*) AS total_alat_sarana FROM laporan_alat_sarana; -- 0
SELECT COUNT(*) AS total_cuaca FROM laporan_cuaca;         -- 0
SELECT COUNT(*) AS total_panen FROM laporan_panen;         -- 0
SELECT COUNT(*) AS total_pupuk FROM laporan_pupuk;         -- 0

-- Tabel terkait harus kosong
SELECT COUNT(*) FROM laporan_hama_tags;                    -- 0
SELECT COUNT(*) FROM honor_pelaporan;                      -- 0
SELECT COUNT(*) FROM laporan_status_history;               -- 0
```

### 5.2 Verifikasi Struktur Tabel
```sql
-- Pastikan struktur tabel tetap sama
DESCRIBE laporan_hama;
DESCRIBE laporan_irigasi;
DESCRIBE laporan_lainnya;
DESCRIBE laporan_alat_sarana;
DESCRIBE laporan_cuaca;
DESCRIBE laporan_panen;
DESCRIBE laporan_pupuk;

-- Pastikan foreign key masih ada
SELECT 
    TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('laporan_hama', 'laporan_irigasi', 'laporan_lainnya', 'laporan_alat_sarana', 'laporan_cuaca', 'laporan_panen', 'laporan_pupuk');
```

### 5.3 Verifikasi Aplikasi
```bash
# Login sebagai petugas, cek halaman laporan
# - Daftar laporan harus menampilkan "Tidak ada data laporan"
# - Filter, pencarian, pagination harus tetap berfungsi
# - Form create harus tetap bisa dibuka
```

---

## 6. Troubleshooting

### 6.1 Foreign Key Constraint Error
Jika muncul error `Cannot delete or update a parent row: a foreign key constraint fails`:

```sql
-- Cek constraint yang aktif
SELECT 
    TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IN ('laporan_hama', 'laporan_irigasi', 'laporan_lainnya', 'laporan_alat_sarana', 'laporan_cuaca', 'laporan_panen', 'laporan_pupuk');

-- Hapus data child terlebih dahulu sesuai urutan constraint
```

### 6.2 Data Masih Tampil di Aplikasi
- Clear cache aplikasi: `rm backend/storage/cache/*.lock`
- Clear session browser
- Hard refresh (Ctrl+F5)

### 6.3 Auto Increment Tidak Reset
```sql
-- Reset AUTO_INCREMENT manual jika diperlukan
ALTER TABLE laporan_hama AUTO_INCREMENT = 1;
ALTER TABLE laporan_irigasi AUTO_INCREMENT = 1;
ALTER TABLE laporan_lainnya AUTO_INCREMENT = 1;
ALTER TABLE laporan_alat_sarana AUTO_INCREMENT = 1;
ALTER TABLE laporan_cuaca AUTO_INCREMENT = 1;
ALTER TABLE laporan_panen AUTO_INCREMENT = 1;
ALTER TABLE laporan_pupuk AUTO_INCREMENT = 1;
```

---

## 7. Catatan Penting

1. **Jangan menghapus tabel**: Gunakan `DELETE` atau `TRUNCATE`, bukan `DROP TABLE`
2. **Jangan ubah struktur kolom**: Jangan menambah/hapus/mengubah tipe kolom
3. **Jangan menghapus data master**: Users, wilayah, OPT, jenis laporan harus tetap
4. **Backup adalah wajib**: Selalu backup sebelum menjalankan penghapusan
5. **Test di staging terlebih**: Jalankan di environment non-production terlebih dahulu
6. **Counter nomor laporan**: Direset bersamaan agar nomor baru dimulai dari awal
7. **Activity log & notifications**: Bersihkan jika ingin total reset, tetapi tidak wajib karena tidak ada FK
8. **Tambah jenis laporan**: Jika nanti ada jenis laporan baru, tambahkan query DELETE-nya juga

---

## 8. Checklist Eksekusi

- [ ] Database sudah di-backup
- [ ] Script penghapusan sudah dibuat dan di-review
- [ ] Foreign key constraints sudah diidentifikasi
- [ ] Menjalankan penghapusan di lingkungan staging
- [ ] Verifikasi data laporan = 0
- [ ] Verifikasi struktur tabel tetap sama
- [ ] Verifikasi aplikasi masih berjalan normal
- [ ] Informasikan tim jika diperlukan
- [ ] Jalankan di production
- [ ] Verifikasi ulang pasca-produksi

---

## 9. Alternatif: Reset ke State Awal (Seeded)

Jika ingin mengembalikan ke kondisi seperti baru dengan data sample:

```bash
# 1. Jalankan penghapusan seperti di atas
# 2. Jalankan seeder
cd backend
php scripts/seed.php
```

Pastikan `seed.php` tidak menimpa data master yang sudah ada.

---

Dibuat oleh: Kilo  
Tanggal: 2026-08-20  
Versi: 1.1