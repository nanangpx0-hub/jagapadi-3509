# Database Schema JAGAPADI

> **Status**: Implemented (Tahap 3)
> **Target DB**: MySQL 8.0+ / MariaDB 10.6+
> **Charset**: `utf8mb4` / `utf8mb4_unicode_ci`
> **Engine**: InnoDB

---

## Setup Database Lokal

```sql
CREATE DATABASE IF NOT EXISTS jagapadi_local
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Setel `.env`:

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jagapadi_local
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

Jalankan migrasi:

```bash
cd backend
php scripts/migrate.php
php scripts/seed.php
```

---

## Naming Convention

| Object | Convention | Example |
|--------|------------|---------|
| Table | `snake_case`, singular | `laporan_hama`, `users` |
| Column | `snake_case` | `nomor_laporan`, `created_at` |
| PK | `id` (INT UNSIGNED AI / BIGINT UNSIGNED AI) | `id` |
| FK | `fk_{table}_{column}` | `fk_lh_user` |
| Index | `idx_{table}_{column(s)}` | `idx_lh_status` |
| Unique | `uk_{table}_{column(s)}` | `uk_username` |
| CHECK | `ck_{table}_{column}` | `ck_lh_latitude` |

---

## Daftar Tabel

### 1. `schema_migrations`
Tracking migration yang sudah dijalankan.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI PK | |
| migration | VARCHAR(255) UNIQUE | Nama file migration |
| batch | INT UNSIGNED | Nomor batch |
| executed_at | TIMESTAMP | Waktu eksekusi |

### 2. `master_kabupaten`
Master data kabupaten/kota.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED AI PK | |
| kode | VARCHAR(10) UNIQUE | Kode BPS |
| nama_kabupaten | VARCHAR(100) | Nama kabupaten |

### 3. `master_kecamatan`
Master data kecamatan.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED AI PK | |
| kabupaten_id | INT UNSIGNED FK | FK → master_kabupaten(id) |
| kode | VARCHAR(10) UNIQUE | Kode BPS |
| nama_kecamatan | VARCHAR(100) | Nama kecamatan |

### 4. `master_desa`
Master data desa/kelurahan.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED AI PK | |
| kecamatan_id | INT UNSIGNED FK | FK → master_kecamatan(id) |
| kode | VARCHAR(10) UNIQUE | Kode BPS |
| nama_desa | VARCHAR(100) | Nama desa |

### 5. `users`
User aplikasi (admin & petugas).

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED AI PK | |
| username | VARCHAR(50) UNIQUE | Login username |
| password | VARCHAR(255) | Bcrypt hash (cost 12) |
| email | VARCHAR(150) UNIQUE | Email |
| nama_lengkap | VARCHAR(150) | Nama lengkap |
| role | ENUM('admin','petugas') | Default 'petugas' |
| aktif | TINYINT(1) | 1 = aktif, 0 = nonaktif |
| must_change_password | TINYINT(1) | 1 = harus ganti password |
| last_password_change_at | TIMESTAMP NULL | |

### 6. `master_opt`
Master Organisme Pengganggu Tanaman (OPT).

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED AI PK | |
| nama_opt | VARCHAR(150) UNIQUE | Nama OPT |
| jenis | ENUM('hama','penyakit','gulma') | Jenis |
| etl_acuan | DECIMAL(10,2) NULL | Ambang ETL |
| satuan_etl | VARCHAR(30) NULL | Satuan ETL |
| foto_url | VARCHAR(300) NULL | URL foto referensi |
| deskripsi | TEXT NULL | Deskripsi |
| aktif | TINYINT(1) | 1 = aktif |

### 7. `laporan_hama`
Laporan serangan hama/OPT.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI PK | |
| nomor_laporan | VARCHAR(20) UNIQUE NULL | **NULL saat Draf**, diisi saat Submitted |
| user_id | INT UNSIGNED FK | Pelapor |
| master_opt_id | INT UNSIGNED FK NULL | Jenis OPT (nullable saat Draf) |
| tanggal | DATE NULL | Tanggal observasi |
| kabupaten_id | INT UNSIGNED FK NULL | |
| kecamatan_id | INT UNSIGNED FK NULL | |
| desa_id | INT UNSIGNED FK NULL | |
| lokasi | VARCHAR(255) NULL | Nama lokasi |
| alamat_lengkap | VARCHAR(300) NULL | Alamat detail |
| latitude | DECIMAL(10,7) NULL | CHECK(-90..90) |
| longitude | DECIMAL(10,7) NULL | CHECK(-180..180) |
| tingkat_keparahan | ENUM('Ringan','Sedang','Berat') NULL | |
| luas_serangan | DECIMAL(8,2) NULL | CHECK(0..9999.99) |
| populasi | DECIMAL(10,2) NULL | Populasi hama |
| foto_url | VARCHAR(300) NULL | |
| catatan | TEXT NULL | |
| status | ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan') | Default 'Draf' |
| verified_by | INT UNSIGNED FK NULL | Admin verifikator |
| verified_at | TIMESTAMP NULL | |
| catatan_verifikasi | TEXT NULL | |
| ip_pengirim | VARCHAR(45) NULL | |

**Indexes**: user_id, master_opt_id, status, tanggal, kecamatan_id, tingkat_keparahan, (status+tanggal)

### 8. `laporan_irigasi`
Laporan kondisi irigasi.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI PK | |
| nomor_laporan | VARCHAR(20) UNIQUE NULL | **NULL saat Draf**, diisi saat Submitted |
| user_id | INT UNSIGNED FK | Pelapor |
| tanggal | DATE NULL | |
| kabupaten_id | INT UNSIGNED FK NULL | |
| kecamatan_id | INT UNSIGNED FK NULL | |
| desa_id | INT UNSIGNED FK NULL | |
| nama_saluran | VARCHAR(200) NULL | Nama saluran irigasi |
| daerah_irigasi | VARCHAR(200) NULL | Daerah irigasi |
| latitude | DECIMAL(10,7) NULL | CHECK(-90..90) |
| longitude | DECIMAL(10,7) NULL | CHECK(-180..180) |
| kondisi_fisik | ENUM('Bagus','Sedang','Tidak Bagus','Rusak') NULL | |
| debit_air | ENUM('Cukup','Kurang','Kering') NULL | |
| foto_url | VARCHAR(300) NULL | |
| catatan | TEXT NULL | |
| status | ENUM('Draf','Submitted','Diverifikasi','Ditolak','Diarsipkan') | Default 'Draf' |
| verified_by | INT UNSIGNED FK NULL | |
| verified_at | TIMESTAMP NULL | |
| catatan_verifikasi | TEXT NULL | |
| ip_pengirim | VARCHAR(45) NULL | |

### 9. `activity_log`
Log aktivitas aplikasi.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI PK | |
| user_id | INT UNSIGNED FK NULL | NULL jika user dihapus |
| action | VARCHAR(100) | Tindakan |
| table_name | VARCHAR(50) NULL | Tabel terkait |
| record_id | BIGINT UNSIGNED NULL | Record terkait |
| description | TEXT NULL | |
| ip_address | VARCHAR(45) NULL | |
| user_agent | VARCHAR(500) NULL | |

### 10. `audit_log_wilayah`
Audit perubahan data wilayah oleh admin.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI PK | |
| admin_id | INT UNSIGNED FK | Admin pelaku |
| tabel | VARCHAR(50) | Tabel yang diubah |
| record_id | INT UNSIGNED | Record yang diubah |
| aksi | ENUM('INSERT','UPDATE','DELETE') | |
| data_lama | JSON NULL | Data sebelum perubahan |
| data_baru | JSON NULL | Data setelah perubahan |

### 11. `notifications`
Notifikasi in-app untuk user.
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI PK | |
| user_id | INT UNSIGNED FK | FK → users(id) ON DELETE CASCADE |
| type | VARCHAR(50) | `laporan_submitted`, `laporan_verified`, `laporan_rejected`, `laporan_resubmitted`, `laporan_archived` |
| title | VARCHAR(200) | Judul notifikasi |
| body | VARCHAR(500) | Isi notifikasi |
| data_json | TEXT NULL | JSON payload: entity, laporan_id, nomor_laporan, status, web_path, api_path |
| read_at | TIMESTAMP NULL | Waktu dibaca (NULL = belum dibaca) |
| created_at | TIMESTAMP DEFAULT CURRENT_TIMESTAMP | |
**Indexes**: idx_user_created (user_id, created_at), idx_user_unread (user_id, read_at), idx_type (type)

### 12. `device_tokens`
Penyimpanan FCM token perangkat user untuk push notification.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT UNSIGNED AI PK | |
| user_id | INT UNSIGNED FK | FK → users(id) ON DELETE CASCADE |
| token | VARCHAR(512) | Token FCM — UNIQUE |
| platform | ENUM('android','ios','web') | Default 'android' |
| user_agent | VARCHAR(500) NULL | HTTP User-Agent |
| last_seen_at | TIMESTAMP NULL | Terakhir kali token digunakan |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | Auto-update |
**Unique**: uq_device_token (token). **Indexes**: idx_device_tokens_user (user_id)

### 13. `nomor_laporan_counter`
Counter atomik untuk generate nomor laporan. Bukan AUTO_INCREMENT — dikelola aplikasi.

| Column | Type | Description |
|--------|------|-------------|
| prefix | VARCHAR(10) | PK — 'LH' untuk hama, 'LI' untuk irigasi |
| tanggal | DATE | PK — Tanggal laporan |
| counter | INT UNSIGNED | Counter harian (default 0) |

---

## Status Laporan & Arti Bisnis

| Status | Arti | Bisa Diverifikasi? | Masuk Statistik Default? |
|--------|------|-------------------|--------------------------|
| `Draf` | Disimpan sementara, belum dikirim | **Tidak** | **Tidak** |
| `Submitted` | Dikirim petugas, menunggu verifikasi | Ya | Ya |
| `Diverifikasi` | Disetujui admin | N/A | Ya |
| `Ditolak` | Ditolak admin | N/A | Tidak |
| `Diarsipkan` | Diarsipkan (read-only) | N/A | Tidak |

---

## Migration Order

```sql
-- 1–8: existing tables
-- 9: notifications
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `body` VARCHAR(500) NOT NULL,
    `data_json` TEXT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_created` (`user_id`, `created_at`),
    INDEX `idx_user_unread` (`user_id`, `read_at`),
    INDEX `idx_type` (`type`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Aturan `nomor_laporan`

- **NULL** saat laporan masih `Draf`
- **Diisi** saat status berubah menjadi `Submitted`
- Format: `{prefix}{YYYYMMDD}-{XXXX}`
  - `LH` = Laporan Hama
  - `LI` = Laporan Irigasi
- Counter menggunakan tabel `nomor_laporan_counter` (harian, per prefix)

---

## Foreign Key Relasi

```
master_kabupaten 1──N master_kecamatan 1──N master_desa
                                                    │
users ──N laporan_hama ──N master_opt               │
users ──N laporan_irigasi                            │
users ──N notifications                              │
users ──N activity_log                               │
users ──N audit_log_wilayah                          │
users ──N verified_by (laporan_hama/irigasi)         │
                                       └─────────────┘
```

---

## Catatan Penting

1. **Statistik default tidak termasuk Draf** → semua query agregat wajib `WHERE status != 'Draf'` kecuali ada parameter `include_draft=true`
2. **Draf tidak boleh diverifikasi** → validasi di aplikasi/server
3. **Constraint CHECK** untuk latitude, longitude, luas_serangan (MySQL 8.0+ / MariaDB 10.2+)
4. **FULLTEXT** index pada `master_opt.nama_opt` untuk pencarian
5. **Seed hanya untuk local/development** — jangan jalankan `php scripts/seed.php` di production

---

## Referensi

- Migration SQL: `backend/database/migrations/`
- Seed SQL: `backend/database/seeds/`
- Schema lengkap: `backend/database/schema.sql`
- Migration runner: `backend/scripts/migrate.php`
- Seed runner: `backend/scripts/seed.php`
