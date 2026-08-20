# Dokumentasi Operasional: Skrip Pengambil Data Kecepatan Angin NASA POWER (`fetch_nasa_wind_jember.php`)

Skrip ini bertugas mengambil data harian kecepatan angin pada ketinggian 10m (`WS10M`) dan 2m (`WS2M`) dalam satuan m/s dari **NASA POWER Daily Point API** untuk Kabupaten Jember dan menyimpannya ke dalam database MySQL secara otomatis & konsisten (*idempotent*).

---

## 1. Spesifikasi Teknis
- **Lokasi Skrip**: `scripts/fetch_nasa_wind_jember.php`
- **Database Target**: `cuaca_angin_jember`
- **Koneksi Database**: Menggunakan Singleton [`app/core/Database.php`](file:///C:/laragon/www/jagapadi-3509/app/core/Database.php)
- **File Log**: `storage/logs/fetch_nasa_wind_jember.log`

---

## 2. Cara Menjalankan Manual (CLI)

### A. Pengambilan Data Harian Otomatis (Default Kemarin WIB)
Menjalankan skrip tanpa argumen tanggal akan mengambil data kemarin WIB untuk titik pusat kabupaten:
```bash
php scripts/fetch_nasa_wind_jember.php
```

### B. Mode Single (Pusat Kabupaten Jember) Periode Historis
```bash
php scripts/fetch_nasa_wind_jember.php --mode=single --start=20240101 --end=20240131
```

### C. Mode Multi-Titik (Seluruh 31 Kecamatan dari `master_kecamatan`)
```bash
php scripts/fetch_nasa_wind_jember.php --mode=multi --start=20250101 --end=20250131
```

---

## 3. Konfigurasi Cron Job (Jadwal Otomatis)

Untuk menjalankan otomatis setiap hari pukul **06:00 WIB**:
```cron
0 6 * * * php /var/www/html/jagapadi-3509/scripts/fetch_nasa_wind_jember.php --mode=multi >> /var/www/html/jagapadi-3509/storage/logs/fetch_nasa_wind_jember.log 2>&1
```

---

## 4. Cara Cek Data di Database

### Query SQL via MySQL CLI / phpMyAdmin:
```sql
-- Cek 10 record terbaru data kecepatan angin
SELECT 
    w.id,
    k.nama_kecamatan,
    w.latitude,
    w.longitude,
    w.tanggal,
    w.ws10m_ms,
    w.ws2m_ms,
    w.sumber,
    w.created_at
FROM cuaca_angin_jember w
LEFT JOIN master_kecamatan k ON w.id_kecamatan = k.id
ORDER BY w.tanggal DESC, w.id ASC
LIMIT 10;
```

---

## 5. Struktur DDL Tabel Database (`cuaca_angin_jember`)

```sql
CREATE TABLE IF NOT EXISTS `cuaca_angin_jember` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `id_kecamatan` INT NULL,
    `latitude` DECIMAL(8,5) NOT NULL,
    `longitude` DECIMAL(8,5) NOT NULL,
    `tanggal` DATE NOT NULL,
    `ws10m_ms` DECIMAL(5,2) NOT NULL,
    `ws2m_ms` DECIMAL(5,2) NULL,
    `sumber` VARCHAR(50) DEFAULT 'NASA_POWER',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wind_loc` (`id_kecamatan`, `tanggal`, `latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
