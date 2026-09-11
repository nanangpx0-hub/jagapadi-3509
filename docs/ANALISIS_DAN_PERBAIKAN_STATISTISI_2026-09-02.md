# Analisis dan Perbaikan Fitur Statistisi

Tanggal audit: 2 September 2026. Audit mencakup runtime root/integrated dan Backend v1. Kontrak efektif statistisi adalah pengguna analitik baca-saja dengan cakupan global atas data resmi (`Submitted` dan `Diverifikasi`), tanpa akses Draf atau mutasi administratif.

## Pemetaan fungsi dan alur

| Area | Masukan dan proses | Keluaran | Dependensi utama |
|---|---|---|---|
| Dashboard root | Session, tahun/wilayah → controller → service/model agregasi | KPI, grafik, peta, cuaca/peringatan | `DashboardController`, `DashboardPadiController`, model laporan/BPS/KSA/cuaca |
| Dashboard Backend v1 | JWT/session → filter tahun dan `include_draft` → `DashboardService` | statistik, grafik bulanan, GeoJSON | controller API/web, cache, laporan hama/irigasi |
| Evaluasi akurasi | Tahun/bulan → pembacaan rilis dan snapshot → perhitungan deviasi | tabel evaluasi, statistik, grafik, log | `EvaluasiController`, `EvaluasiService`, data KSA/BPS |
| Storytelling | Dataset terpilih → analisis statistik → simpan/publikasi | narasi, insight, grafik cerita | `StorytellingController`, `DataStoryService`, `StorytellingAnalysisService` |
| Laporan | JWT/session → scope role → query terfilter status | daftar dan detail enam jenis laporan | service/model hama, irigasi, pupuk, panen, cuaca, alat/sarana |
| Ekspor | Role + filter tervalidasi → query ter-scope → CSV/XLSX | berkas data resmi | `ExportService`, `CsvWriter`, `XlsxWriter`, activity log |

Alur otorisasi yang ditetapkan ulang:

```text
Statistisi terautentikasi
  → dashboard/list/detail/evaluasi/storytelling/ekspor
  → scope global
  → status dipaksa Submitted + Diverifikasi
  → output baca-saja

Mutasi evaluasi/verifikasi/arsip/draf
  → policy Admin/Petugas sesuai route
  → Statistisi ditolak
```

## Temuan dan perbaikan

| Risiko | Temuan | Dampak | Perbaikan |
|---|---|---|---|
| Tinggi | Layanan daftar laporan memperlakukan semua non-admin sebagai Petugas. | Statistisi hanya melihat data milik ID sendiri atau hasil kosong, bukan data global. | Enam layanan laporan kini memilih query milik sendiri hanya untuk `petugas`; statistisi memakai query global resmi. |
| Tinggi | Detail laporan memberi seluruh status kepada semua role non-Petugas. | Statistisi dapat membuka Draf/Ditolak/Diarsipkan lewat ID langsung. | Enam model detail membatasi role baca-saja ke `Submitted` dan `Diverifikasi`. |
| Tinggi | Dashboard dan ekspor menerima `include_draft=true` dari Statistisi. | Data belum resmi dapat mencemari KPI, grafik, peta, dan berkas analisis. | `DashboardService` dan `ExportService` mengabaikan aktivasi Draf untuk role baca-saja; ekspor status nonresmi menghasilkan data kosong. |
| Sedang | Evaluasi Akurasi seluruhnya memakai policy Admin. | Halaman analitik utama gagal/ditolak untuk Statistisi. | Endpoint baca mengizinkan Admin/Statistisi; generate, tambah, ubah, hapus, dan impor tetap Admin-only. |
| Sedang | Menu Evaluasi dan Storytelling berada di blok navigasi Admin. | Fitur yang sah tidak dapat ditemukan oleh Statistisi. | Navigasi dipindahkan ke guard `admin/statistisi`. |
| Sedang | UI Evaluasi menampilkan kontrol mutasi tanpa membedakan role. | Menyesatkan pengguna dan membuka percobaan aksi terlarang. | Mode baca Statistisi ditampilkan; tombol tambah/impor/generate/edit/hapus disembunyikan. Server tetap menjadi kontrol utama. |
| Rendah | Input tahun/bulan dan limit log Evaluasi belum dinormalisasi konsisten. | Query tak perlu atau keluaran tidak stabil pada input batas. | Validasi tahun/bulan terpusat dan limit log dibatasi 1–100. |
| Rendah | Audit log dashboard menandai Statistisi sebagai `own_data`. | Jejak audit tidak sesuai cakupan query sebenarnya. | Scope log memakai pemetaan role yang sama dengan service (`all_data` untuk Statistisi). |

## Skenario batas yang dikunci

- `include_draft=true` sebagai Statistisi tetap tidak menyertakan Draf.
- `status=Draf` pada ekspor Statistisi menghasilkan nol baris.
- Statistisi dapat melihat laporan resmi milik pengguna lain.
- Statistisi tidak dapat membuka detail Draf dengan menebak ID.
- Tahun/bulan Evaluasi yang tidak valid kembali ke nilai aman.
- Limit log negatif, nol, atau berlebihan dinormalisasi ke rentang 1–100.
- Tombol mutasi tidak tersedia pada UI Statistisi dan endpoint mutasi tetap Admin-only.

## Verifikasi

Kontrak dilindungi oleh unit/integration test root dan Backend v1, termasuk kontrak navigasi/evaluasi, scope dashboard, keenam modul laporan, akses detail, dan ekspor. E2E role Statistisi mencakup dashboard tanpa Draf, pembatasan mutasi, navigasi analitik, serta mode baca Evaluasi.

## Rekomendasi lanjutan

1. Pusatkan matriks role/status dalam satu policy class agar enam service/model tidak menduplikasi aturan.
2. Tambahkan fixture database deterministik untuk keenam jenis laporan dan uji HTTP 200/403/404 per role.
3. Jalankan E2E Statistisi pada CI dengan server dan database terisolasi.
4. Tambahkan indeks komposit `(status, tanggal)` dan `(status, user_id, tanggal)` berdasarkan hasil `EXPLAIN` pada volume produksi.
5. Pantau cache hit ratio dan waktu query dashboard; tetapkan anggaran performa per endpoint.
