# BPS Data Export Documentation

## Overview

Script ini mengambil seluruh data pertanian BPS (luas panen, produksi gabah, produksi beras, produktivitas) per kabupaten/kota dari database JAGAPADI dan mengekspornya ke format CSV di direktori `data/ksa/`.

## Proses Pengambilan Data

### Alur Eksekusi

1. **Verifikasi Konektivitas Database** — Script terhubung ke MariaDB/MySQL melalui PDO menggunakan kelas `Database` singleton
2. **Query Data** — Menggunakan model `DataPertanianBps` untuk mengambil semua records dari tabel `data_pertanian_bps`
3. **Export ke CSV** — Data ditulis ke file CSV dengan format UTF-8 + BOM (kompatibel Excel)
4. **Validasi** — Memverifikasi kelengkapan kabupaten (38/38), integritas field, dan struktur CSV

### Cara Menjalankan

```bash
php scripts/export_bps_data.php
```

### Prasyarat

- PHP 8.2+ dengan ekstensi PDO, MySQL, JSON
- Database `jagapadi_local` dengan tabel `data_pertanian_bps`
- File `.env.local` di root proyek

## Struktur File Output

Direktori output: `C:\laragon\www\jagapadi-3509\data\ksa\`

### File CSV

1. **`data_pertanian_bps_export_<timestamp>.csv`** — Full export semua data (semua tahun)
   - 304 records (2018-2025, 38 per tahun)
   - Header: Tahun, Kabupaten/Kota, Kode Wilayah, Luas Panen (Ha), Produksi Gabah (Ton), Produksi Beras (Ton), Produktivitas (Ku/Ha), Sumber Data, Tipe Sumber, Skenario, Validated, Validation Notes, Keterangan, Dibuat Pada, Diupdate Pada

2. **`data_pertanian_bps_<tahun>.csv`** — Export data untuk tahun terbaru (2025)
   - 38 records (38 kabupaten/kota Jawa Timur)
   - Format identik dengan full export

### Validation Report

3. **`export_validation_<timestamp>.json`** — Laporan validasi lengkap
   - Total records diekspor
   - Daftar kabupaten ditemukan/hilang
   - Data completeness per kabupaten
   - Validation errors (jika ada)

## Field Data

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| tahun | INT | Tahun data (2018-2025) |
| kabupaten_kota | VARCHAR(100) | Nama kabupaten/kota (38 wilayah Jawa Timur) |
| kode_wilayah | VARCHAR(20) | Kode BPS (misal: 3509 untuk Jember) |
| luas_panen | DECIMAL(15,2) | Luas panen dalam hektar |
| produksi_gabah | DECIMAL(15,2) | Produksi gabah dalam ton |
| produksi_beras | DECIMAL(15,2) | Produksi beras dalam ton (konversi GKG 57.7%) |
| produktivitas | DECIMAL(10,2) | Produktivitas dalam ku/ha |
| sumber_data | VARCHAR(100) | Sumber data (Simulasi/BPS WebAPI/Manual) |
| sumber_data_type | ENUM | simulasi/resmi_webapi/manual |
| tipe_skenario | ENUM | baseline/optimis/pesimis |
| is_validated | TINYINT(1) | Status validasi (1=valid, 0=invalid) |
| validation_notes | TEXT | Catatan validasi (anomali jika ada) |
| keterangan | TEXT | Keterangan tambahan (variasi tahun, random factor) |

## Validasi Data

### Kelengkapan Kabupaten
- Total expected: 38 kabupaten/kota
- Total found: 38 (100%)
- Total missing: 0

### Validation Thresholds (BpsDataService)
- Produktivitas: 30-80 ku/ha
- Luas panen: 100-200,000 ha
- Produksi gabah: 500-1,200,000 ton

### Konversi
- Beras = Gabah × 57.7% (BPS standard conversion rate)
- Produktivitas = (Produksi gabah / Luas panen) × 10 (ku/ha)

## Monitoring

### Log Files
- `logs/bps_scraper.log` — Log scraping process (start, complete, errors)
- `logs/bps_api_client.log` — Log WebAPI requests (hanya jika source = resmi_webapi)
- `logs/bps_data_service.log` — Log validasi dan penyimpanan data

### Database Logs
- Tabel `bps_scraping_logs` — Log aktivitas scraper (aksi, status, pesan, detail JSON)
