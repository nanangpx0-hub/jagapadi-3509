# Analisis Dataset KSA Ubinan & Rencana Import ke BPS Scraper
**Tanggal Analisis:** 7 Agustus 2026  
**Dataset:** pada-2025.pdf (5.6 MB)  
**Target Sistem:** http://localhost/jagapadi-3509/bpsScraper

---

## 1. AUDIT DATASET

### 1.1 Informasi File
| Atribut | Detail |
|---------|--------|
| Nama File | pada-2025.pdf |
| Ukuran | 5,631,151 bytes (~5.6 MB) |
| Format | PDF (biner) |
| Tanggal Modifikasi | 7 Agustus 2026, 15:23:42 |
| Lokasi | C:\laragon\www\jagapadi-3509\data\ksa-ubinan\ |

### 1.2 Status Ekstraksi Data
❌ **MASALAH KRITIKAL:** File dalam format PDF biner yang tidak dapat dibaca langsung tanpa tool ekstraksi PDF-to-text atau PDF-to-Excel.

**Solusi yang Dibutuhkan:**
1. Ekstrak teks dari PDF menggunakan tool seperti:
   - Adobe Acrobat Reader (Export to Excel)
   - Online converter (PDF2XLS, Smallpdf, ILovePDF)
   - Command-line tools (pdftotext, Tabula)
   - Python libraries (pdfplumber, camelot, tabula-py)

2. Alternatif manual: Salin data dari PDF ke Excel template

---

## 2. STRUKTUR DATABASE TARGET

### 2.1 Tabel: `data_pertanian_bps`

```sql
CREATE TABLE data_pertanian_bps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun INT NOT NULL,                          -- WAJIB: Tahun data (2019-2030)
    kabupaten_kota VARCHAR(100) NOT NULL,        -- WAJIB: Nama kabupaten/kota
    kode_wilayah VARCHAR(20),                    -- Opsional: Kode BPS
    luas_panen DECIMAL(15,2),                    -- WAJIB: dalam hektar
    produksi_gabah DECIMAL(15,2),                -- WAJIB: dalam ton
    produksi_beras DECIMAL(15,2),                -- Auto-calc: 57.7% dari gabah
    produktivitas DECIMAL(10,2),                 -- Auto-calc: (gabah/luas)*10 ku/ha
    sumber_data VARCHAR(100),                    -- Auto: 'Import Excel'
    sumber_data_type ENUM(...) DEFAULT 'manual', -- Auto: 'manual'
    tipe_skenario ENUM(...) DEFAULT 'baseline',  -- Auto: 'baseline'
    is_validated TINYINT(1) DEFAULT 1,           -- Auto: 1
    validation_notes TEXT,                       -- Opsional
    keterangan TEXT,                             -- Opsional
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_data (tahun, kabupaten_kota)
)
```

### 2.2 Field Wajib vs Opsional

| Field | Status | Validasi |
|-------|--------|----------|
| `tahun` | ✅ WAJIB | 2019 ≤ tahun ≤ 2030 |
| `kabupaten_kota` | ✅ WAJIB | Min 3 karakter |
| `luas_panen` | ✅ WAJIB | ≥ 0 (hektar) |
| `produksi_gabah` | ✅ WAJIB | ≥ 0 (ton) |
| `kode_wilayah` | ⚠️ OPSIONAL | Format: XX.XX |
| `produksi_beras` | ⚠️ AUTO-CALC | gabah × 0.577 jika kosong |
| `produktivitas` | ⚠️ AUTO-CALC | (gabah/luas)×10 jika kosong |
| `keterangan` | ⚠️ OPSIONAL | Text bebas |

### 2.3 Daftar Kabupaten/Kota Jawa Timur (38 Total)

```
Bangkalan, Banyuwangi, Blitar, Bojonegoro, Bondowoso, Gresik, Jember, 
Jombang, Kediri, Kota Batu, Kota Blitar, Kota Kediri, Kota Madiun, 
Kota Malang, Kota Mojokerto, Kota Pasuruan, Kota Probolinggo, 
Kota Surabaya, Lamongan, Lumajang, Madiun, Magetan, Malang, Mojokerto, 
Nganjuk, Ngawi, Pacitan, Pamekasan, Pasuruan, Ponorogo, Probolinggo, 
Sampang, Sidoarjo, Situbondo, Sumenep, Trenggalek, Tuban, Tulungagung
```

---

## 3. FORMAT EXCEL YANG DIBUTUHKAN

### 3.1 Template CSV/Excel

```csv
tahun,kabupaten_kota,kode_wilayah,luas_panen,produksi_gabah,produksi_beras,produktivitas,keterangan
2025,Jember,3509,125000.50,750000.25,432750.14,60.00,Data KSA Ubinan
2025,Banyuwangi,3510,130000.00,780000.00,450060.00,60.00,
```

### 3.2 Mapping Nama Kolom yang Diterima

System menerima berbagai variasi nama kolom (case-insensitive):

| Kolom Target | Variasi yang Diterima |
|--------------|----------------------|
| `tahun` | tahun, year |
| `kabupaten_kota` | kabupaten_kota, kabupaten, kota, regency |
| `luas_panen` | luas_panen, luas, harvest_area |
| `produksi_gabah` | produksi_gabah, gabah |
| `produksi_beras` | produksi_beras, beras |
| `produktivitas` | produktivitas, productivity |
| `keterangan` | keterangan, notes |

### 3.3 Contoh Data Valid

```
Tahun: 2025
Kabupaten: Jember
Luas Panen: 125,000.50 Ha
Produksi Gabah: 750,000.25 Ton
Produksi Beras: (otomatis = 750000.25 × 0.577 = 432,750.14 Ton)
Produktivitas: (otomatis = (750000.25 / 125000.50) × 10 = 60.00 Ku/Ha)
```

---

## 4. IDENTIFIKASI MASALAH POTENSIAL

### 4.1 Masalah Format PDF
❌ **P1-KRITIKAL:** PDF tidak dapat dibaca langsung oleh sistem import
- **Dampak:** Import tidak bisa dilakukan tanpa konversi
- **Solusi:** Konversi PDF → Excel/CSV terlebih dahulu

### 4.2 Potensi Masalah Data

| Masalah | Deteksi | Solusi |
|---------|---------|--------|
| **Duplikasi** | Tahun + Kabupaten sama | UNIQUE constraint akan reject, gunakan UPSERT |
| **Format angka** | Titik ribuan (1.000,50) | Hapus titik ribuan, ganti koma jadi titik |
| **Nama kabupaten inkonsisten** | "Kab. Jember" vs "Jember" | Normalisasi sebelum import |
| **Tahun di luar range** | Tahun < 2019 atau > 2030 | Validasi akan reject |
| **Data negatif** | Luas/produksi < 0 | Validasi akan reject |
| **Missing required fields** | tahun/kabupaten/luas/gabah kosong | Validasi akan reject baris |

### 4.3 Warning yang Mungkin Muncul

| Kondisi | Warning |
|---------|---------|
| Produktivitas > 100 Ku/Ha | "Produktivitas sangat tinggi" |
| Luas panen = 0 tapi produksi > 0 | Logic error |
| Produksi beras > produksi gabah | Logic error |

---

## 5. PROSEDUR IMPORT KE SISTEM

### 5.1 Persiapan File

**Langkah 1: Ekstrak Data dari PDF**
```bash
# Opsi A: Manual (Rekomendasi untuk PDF kompleks)
1. Buka pada-2025.pdf dengan Adobe Reader
2. Pilih tabel data
3. Copy ke Excel
4. Sesuaikan header kolom dengan template
5. Save as .xlsx atau .csv

# Opsi B: Tool Online
- Upload ke https://www.ilovepdf.com/pdf_to_excel
- Download hasil konversi
- Periksa dan bersihkan data

# Opsi C: Python (jika tersedia)
pip install tabula-py
python -c "import tabula; tabula.convert_into('pada-2025.pdf', 'output.csv', output_format='csv', pages='all')"
```

**Langkah 2: Normalisasi Data di Excel**

1. **Hapus baris kosong** dan footer tabel
2. **Pastikan header baris pertama** sesuai template:
   ```
   tahun | kabupaten_kota | luas_panen | produksi_gabah | produksi_beras | produktivitas
   ```
3. **Normalisasi nama kabupaten:**
   - Hapus prefix "Kab." atau "Kabupaten"
   - Gunakan kapitalisasi konsisten (Title Case)
   - Contoh: "KAB. JEMBER" → "Jember"
4. **Format angka:**
   - Pastikan tidak ada titik ribuan (1.000 → 1000)
   - Gunakan titik untuk desimal (1000,5 → 1000.5)
5. **Tambah kolom tahun** jika belum ada (isi semua dengan 2025)
6. **Save as CSV UTF-8** untuk kompatibilitas terbaik

### 5.2 Import via Web Interface

**URL:** http://localhost/jagapadi-3509/bpsScraper

**Akses:**
- Login sebagai **Admin** (hanya admin yang bisa import)
- Navigasi ke halaman BPS Scraper

**Prosedur:**

1. **Klik tombol "Import Excel"** di toolbar
2. **Upload file** (.xlsx, .xls, atau .csv maks 5MB)
3. **Preview data** akan muncul (10 baris pertama)
4. **Periksa mapping kolom** otomatis
5. **Klik "Import"** untuk mulai proses
6. **Monitor progress:**
   - Success Count: Jumlah baris berhasil
   - Failed Count: Jumlah baris gagal
   - Errors: Daftar error per baris

### 5.3 Import via API (Alternatif)

```bash
# Endpoint
POST http://localhost/jagapadi-3509/bpsScraper/importExcel

# Headers
Content-Type: multipart/form-data
Cookie: PHPSESSID=... (dari session admin)

# Body
csrf_token: [token dari session]
excel_file: [file upload]

# Response
{
  "success": true,
  "successCount": 35,
  "failedCount": 3,
  "totalProcessed": 38,
  "errors": [
    "Baris 5: Luas panen tidak boleh negatif",
    "Baris 12: Nama kabupaten/kota terlalu pendek",
    "Baris 25: Tahun harus antara 2019-2030"
  ],
  "warnings": [
    "Baris 8: Produktivitas sangat tinggi (105.5 ku/ha)"
  ]
}
```

---

## 6. BATCH UJI COBA (TESTING)

### 6.1 Skenario Test Batch Kecil

**File Test:** `test_ksa_ubinan_sample.csv`

```csv
tahun,kabupaten_kota,luas_panen,produksi_gabah,keterangan
2025,Jember,125000,750000,Test batch 1
2025,Banyuwangi,130000,780000,Test batch 2
2025,Situbondo,45000,270000,Test batch 3
```

**Expected Result:**
- 3 records success
- 0 failed
- All auto-calculated: produksi_beras, produktivitas

### 6.2 Test Case Coverage

| Test Case | Input | Expected |
|-----------|-------|----------|
| **TC-001: Normal** | Valid semua field | ✅ Success |
| **TC-002: Duplikat** | Tahun + Kab sama 2x | ⚠️ Row kedua UPDATE |
| **TC-003: Tahun invalid** | tahun=2018 | ❌ Error: "Tahun harus antara 2019-2030" |
| **TC-004: Luas negatif** | luas_panen=-100 | ❌ Error: "Luas panen tidak boleh negatif" |
| **TC-005: Missing field** | kabupaten_kota kosong | ❌ Error: "Field 'kabupaten_kota' wajib diisi" |
| **TC-006: Auto-calc** | produksi_beras kosong | ✅ Auto = gabah × 0.577 |
| **TC-007: Format angka** | "1.250,50" | ✅ Parsed ke 1250.50 |

### 6.3 Validasi Post-Import

```sql
-- Cek jumlah record per tahun
SELECT tahun, COUNT(*) as jumlah_kabupaten 
FROM data_pertanian_bps 
WHERE tahun = 2025 
GROUP BY tahun;

-- Expected: 38 kabupaten untuk Jawa Timur
-- Jika < 38: ada yang gagal import

-- Cek total produksi
SELECT 
    tahun,
    SUM(luas_panen) as total_luas_panen,
    SUM(produksi_gabah) as total_produksi_gabah,
    SUM(produksi_beras) as total_produksi_beras,
    ROUND(AVG(produktivitas), 2) as rata_produktivitas
FROM data_pertanian_bps 
WHERE tahun = 2025;

-- Cek anomali
SELECT * FROM data_pertanian_bps 
WHERE tahun = 2025 
AND (
    produktivitas > 100 
    OR produktivitas < 30 
    OR luas_panen = 0 
    OR produksi_gabah = 0
);

-- Cek duplikat
SELECT tahun, kabupaten_kota, COUNT(*) 
FROM data_pertanian_bps 
GROUP BY tahun, kabupaten_kota 
HAVING COUNT(*) > 1;
```

---

## 7. CHECKLIST EKSEKUSI

### 7.1 Persiapan (Pre-Import)

- [ ] Backup database sebelum import
  ```sql
  mysqldump -u root jagapadi_db data_pertanian_bps > backup_before_import.sql
  ```
- [ ] Download template Excel dari sistem
  - URL: http://localhost/jagapadi-3509/bpsScraper/downloadTemplate
- [ ] Konversi PDF → Excel/CSV
- [ ] Normalisasi data di Excel (nama kabupaten, format angka)
- [ ] Validasi manual 5 baris sampel
- [ ] Simpan file sebagai CSV UTF-8

### 7.2 Import (Execution)

- [ ] Login sebagai admin di http://localhost/jagapadi-3509
- [ ] Navigasi ke /bpsScraper
- [ ] Klik "Import Excel"
- [ ] Upload file CSV/Excel
- [ ] Review preview data (10 baris pertama)
- [ ] Confirm dan klik "Import"
- [ ] Catat hasil: success_count, failed_count, errors

### 7.3 Validasi (Post-Import)

- [ ] Jalankan query validasi SQL (lihat section 6.3)
- [ ] Cek jumlah total: harus 38 kabupaten untuk tahun 2025
- [ ] Verifikasi data sampel di web interface
- [ ] Test filter per kabupaten
- [ ] Test export CSV untuk memastikan data dapat diekspor
- [ ] Cek activity log: tabel `bps_scraping_logs`
  ```sql
  SELECT * FROM bps_scraping_logs 
  ORDER BY created_at DESC LIMIT 5;
  ```

### 7.4 Reporting

- [ ] Dokumentasi hasil import:
  - Total baris diproses
  - Jumlah success
  - Jumlah failed + daftar error
  - Anomali yang ditemukan
  - Screenshot dashboard dengan data baru
- [ ] Update status di task tracker

---

## 8. TROUBLESHOOTING

### 8.1 Error Umum dan Solusi

| Error | Penyebab | Solusi |
|-------|----------|--------|
| "Token keamanan tidak valid" | Session expired atau CSRF error | Refresh halaman dan login ulang |
| "File terlalu besar" | File > 5MB | Kompres atau split file |
| "Format file tidak didukung" | Extension bukan xlsx/xls/csv | Convert ke format yang valid |
| "Field 'tahun' wajib diisi" | Kolom tahun kosong/tidak ada | Tambah kolom tahun di Excel |
| "Nama kabupaten/kota terlalu pendek" | Kabupaten < 3 karakter | Periksa data, mungkin ada cell kosong |
| "Tahun harus antara 2019-2030" | Data historis/futuristik | Filter data ke range yang valid |
| "Duplicate entry" | Tahun + Kabupaten sudah ada | Normal, sistem akan UPDATE (upsert) |

### 8.2 Performance Issues

| Issue | Solusi |
|-------|--------|
| Import lambat (>1000 rows) | Split file jadi beberapa batch 500 rows |
| Timeout saat upload | Tingkatkan `upload_max_filesize` di php.ini |
| Memory limit | Tingkatkan `memory_limit` di php.ini |

---

## 9. REKOMENDASI AKHIR

### 9.1 Prioritas Tinggi

1. **Ekstrak PDF ke Excel ASAP** — tanpa ini import tidak bisa jalan
2. **Normalisasi nama kabupaten** — gunakan list standar 38 kabupaten
3. **Test dengan 5 baris sampel** sebelum full import
4. **Backup database** sebelum import produksi

### 9.2 Best Practices

- Import di **jam non-peak** untuk menghindari beban server
- Gunakan **CSV UTF-8** untuk encoding yang konsisten
- **Split file besar** (>1000 rows) menjadi batch kecil
- **Dokumentasi setiap import** untuk audit trail
- **Validasi statistik** setelah import selesai

### 9.3 Estimasi Waktu

| Aktivitas | Estimasi |
|-----------|----------|
| Ekstrak PDF → Excel | 15-30 menit (manual) |
| Normalisasi data | 10-15 menit |
| Test batch kecil (5 rows) | 5 menit |
| Full import (38 rows) | <1 menit |
| Validasi post-import | 10 menit |
| **TOTAL** | **~45-60 menit** |

---

## 10. KONTAK & SUPPORT

Jika ada masalah saat import:
1. Cek error di `bps_scraping_logs` table
2. Review file Excel untuk data yang bermasalah
3. Test ulang dengan batch lebih kecil
4. Periksa format kolom sesuai template

**System Endpoint:**
- Import: http://localhost/jagapadi-3509/bpsScraper/importExcel
- Preview: http://localhost/jagapadi-3509/bpsScraper/previewImport
- Template: http://localhost/jagapadi-3509/bpsScraper/downloadTemplate

---

**Status Dokumen:** ✅ READY FOR EXECUTION  
**Next Action:** Ekstrak PDF → Excel dan jalankan checklist section 7
