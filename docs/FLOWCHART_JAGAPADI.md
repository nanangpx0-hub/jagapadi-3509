# Flowchart Komprehensif Aplikasi JAGAPADI

> Dokumen ini berisi 10 flowchart komprehensif untuk aplikasi JAGAPADI (Jember Agrikultur Gapai Prestasi Digital) menggunakan sintaks Mermaid.
> Semua label menggunakan bahasa Indonesia dengan decision node, aktor/role, serta referensi controller/service/tabel.

---

## FLOWCHART 1: ARSITEKTUR SISTEM KESELURUHAN

```mermaid
graph TD
    subgraph Layer1_EntryPoint ["LAYER 1 — Entry Point"]
        A1["HTTP Request"] --> A2["index.php<br/>(Front Controller)"]
        A2 --> A3["Load .env + .env.local"]
        A3 --> A4["Session Config<br/>(httponly, samesite, secure)"]
        A4 --> A5["Autoloader<br/>(spl_autoload_register)"]
        A5 --> A6["CORS Headers"]
    end

    subgraph Layer2_Routing ["LAYER 2 — Routing"]
        B1{"API Request?<br/>(/api/*)"} -->|Ya| B2["Router.php<br/>(Middleware Chain)"]
        B1 -->|Tidak| B3["index.php<br/>(Convention-based Mapping)"]
        B2 --> B4["Middleware:<br/>auth, admin, operator, statistisi, rate_limit<br/>external_auth, mobile_auth, scraper_auth"]
        B3 --> B5["Explicit Routes<br/>(config/web_routes.php)"]
        B5 --> B6["Controller@Method"]
        B4 --> B7["Controller@Method"]
    end

    subgraph Layer3_Controller ["LAYER 3 — Controller (23 Total)"]
        C1["Auth: AuthController"]
        C2["Dashboard: DashboardController, DashboardPadiController"]
        C3["Laporan: LaporanController, LaporanHamaController, LaporanLainnyaController"]
        C4["Cuaca: CurahHujanController, KecepatanAnginController"]
        C5["Pertanian: BpsScraperController, GabahBerasController, EvaluasiController"]
        C6["Harga: HargaKomoditasController"]
        C7["Irigasi: IrigasiController, IrigasiScraperController"]
        C8["Admin: UserController, AdminWilayahController, OptController"]
        C9["Lainnya: FeedbackController, StorytellingController, ExportController"]
        C10["API: ApiController, ApiBpsController, WilayahController"]
    end

    subgraph Layer4_Service ["LAYER 4 — Service (25 Total)"]
        D1["Scraper: BpsScraper, CurahHujanScraper, KecepatanAnginScraper<br/>HargaKomoditasScraper, IrigasiScraper"]
        D2["API Client: BpsApiClient, BMKGApiClient, BMKGService, OpenMeteoService"]
        D3["Analytics: WindAnalyticsService, WindIntegrationService, GabahBerasAnalytics<br/>DashboardDataAggregator, DataStoryService"]
        D4["Import: ExcelImportService, KsaImportService, UserImportService"]
        D5["Engine: IrrigationRuleEngine, WeatherService, WeatherConditionMapper"]
        D6["Lainnya: BpsSimulationService, BpsDataService, CurahHujanMonitor<br/>CurahHujanHealthCheck, QwenEditorTokenManager"]
    end

    subgraph Layer5_ModelDB ["LAYER 5 — Model & Database"]
        E1["26 Model Classes<br/>(Active Record / QueryBuilder)"]
        E2["52 Tabel MySQL<br/>(InnoDB, utf8mb4)"]
        E3["Core ORM: Model.php + QueryBuilder.php"]
        E4["Cache: CacheManager.php<br/>(file/redis/memcached, fail-open)"]
    end

    subgraph Layer6_External ["LAYER 6 — Sumber Data Eksternal"]
        F1["NASA POWER API<br/>(curah hujan + kecepatan angin)"]
        F2["Open-Meteo API<br/>(prakiraan cuaca)"]
        F3["BMKG API<br/>(prakiraan cuaca Indonesia)"]
        F4["SISKAPERBAPO Jatim<br/>(harga komoditas)"]
        F5["BPS WebAPI<br/>(data pertanian resmi)"]
        F6["Qwen AI<br/>(editor token)"]
        F7["Simitra<br/>(integrasi mitra)"]
    end

    B7 --> C1 & C2 & C3 & C4 & C5 & C6 & C7 & C8 & C9 & C10
    C1 & C2 & C3 & C4 & C5 & C6 & C7 & C8 & C9 & C10 --> D1 & D2 & D3 & D4 & D5 & D6
    D1 & D2 & D3 & D4 & D5 & D6 --> E1
    E1 --> E2
    E1 --> E3
    E1 --> E4
    D1 & D2 --> F1 & F2 & F3 & F4 & F5 & F6 & F7

    style A2 fill:#007bff,color:#fff
    style B2 fill:#007bff,color:#fff
    style B3 fill:#007bff,color:#fff
    style E1 fill:#28a745,color:#fff
    style E2 fill:#28a745,color:#fff
```

---

## FLOWCHART 2: ALUR AUTENTIKASI & OTORISASI

```mermaid
graph TD
    S1["User Mengakses Halaman"] --> S2{"Cek Session:<br/>isset($_SESSION['user_id'])?"}
    S2 -->|Tidak| S3["Redirect ke /auth/login"]
    S2 -->|Ya| S4["Load Controller & Method"]

    subgraph LoginFlow ["ALUR LOGIN"]
        L1["Render Form Login<br/>(CSRF Token dari Security::generateCsrfToken)"] --> L2["User Input Username + Password"]
        L2 --> L3["POST Submit"]
        L3 --> L4{"Validasi CSRF Token?"}
        L4 -->|Gagal| L5["Log Security Event<br/>(CSRF_VIOLATION)"]
        L5 --> L6["Tampilkan Error"]
        L4 -->|Berhasil| L7{"Cek Brute Force<br/>(Security::checkBruteForce)"}
        L7 -->|Terdeteksi| L8["Blokir 15 Menit"]
        L8 --> L6
        L7 -->|Aman| L9["Query User dari Tabel `users`<br/>(password_verify)"]
        L9 --> L10{"Login Berhasil?"}
        L10 -->|Gagal| L11["Log ke activity_log"]
        L11 --> L12["Tampilkan Error:<br/>Username/Password Salah"]
        L12 --> L1
        L10 -->|Berhasil| L13["Set Session:<br/>user_id, role, nama_lengkap"]
        L13 --> L14{"Password Changed At<br/>=== NULL?"}
        L14 -->|Ya| L15["Redirect ke<br/>/auth/change_password"]
        L14 -->|Tidak| L16["Redirect ke /dashboard"]
    end

    subgraph RBAC ["SISTEM RBAC — 4 Level Role"]
        R1["admin"] --> R1A["Akses Penuh ke 23 Controller"]
        R2["operator"] --> R2A["Laporan + OPT + Export + Verifikasi"]
        R3["statistisi"] --> R3A["Storytelling + Data Analysis"]
        R4["petugas"] --> R4A["Laporan Hama + Irigasi + Feedback<br/>(Hanya Data Sendiri)"]
        R5["viewer"] --> R5A["Hanya Baca Dashboard"]
    end

    subgraph MiddlewareCheck ["CEK OTORISASI DI SETIAP CONTROLLER"]
        M1["checkAuth()"] --> M2{"Session Aktif?"}
        M2 -->|Tidak| M3["401 Unauthorized"]
        M2 -->|Ya| M4["checkAdmin() / checkRole()"]
        M4 --> M5{"Role === admin?"}
        M5 -->|Tidak| M6["403 Forbidden"]
        M5 -->|Ya| M7["Akses Diberikan"]
        M4 --> M8{"Role in ['admin','operator']?"}
        M8 -->|Tidak| M6
        M8 -->|Ya| M7
    end

    S3 --> L1
    L15 --> S4
    L16 --> S4
    S4 --> M1

    style L10 fill:#ffc107,color:#000
    style L14 fill:#ffc107,color:#000
    style M2 fill:#ffc107,color:#000
    style M5 fill:#ffc107,color:#000
    style M8 fill:#ffc107,color:#000
    style L8 fill:#dc3545,color:#fff
    style L12 fill:#dc3545,color:#fff
    style M3 fill:#dc3545,color:#fff
    style M6 fill:#dc3545,color:#fff
    style L16 fill:#28a745,color:#fff
    style M7 fill:#28a745,color:#fff
```

---

## FLOWCHART 3: SIKLUS HIDUP LAPORAN HAMA (OPT)

```mermaid
stateDiagram-v2
    [*] --> Draft: Petugas buka /laporan/create

    state Draft {
        [*] --> PilihWilayah: Pilih Kecamatan -> Desa<br/>(WilayahController)
        PilihWilayah --> PilihOPT: Pilih OPT dari master_opt
        PilihOPT --> InputData: Input luas serangan, intensitas, kondisi
        InputData --> UploadFoto: Upload foto bukti<br/>(JPEG/PNG, auto-compress)
        UploadFoto --> AutoTag: generateAutoTags() dari deskripsi
        AutoTag --> Honeypot: Honeypot + rate limiting
        Honeypot --> [*]: Form siap
    }

    Draft --> Submitted: Submit → status = 'submitted'<br/>→ log activity_log

    state Submitted {
        [*] --> MenungguVerifikasi: Operator/Admin melihat daftar pending
    }

    Submitted --> Diverifikasi: Admin klik Verify<br/>→ status = 'verified', verified_by = admin_id
    Submitted --> Ditolak: Admin klik Reject<br/>→ status = 'rejected', alasan wajib

    state Ditolak {
        [*] --> PerbaikiLaporan: Petugas melihat alasan penolakan
        PerbaikiLaporan --> Resubmit: Edit & submit kembali
        Resubmit --> [*]: status kembali ke 'submitted'
    }

    Ditolak --> Submitted: Resubmit

    Diverifikasi --> Diarsipkan: Admin klik Archive<br/>→ status = 'archived'

    state Diarsipkan {
        [*] --> Arsip: Data tetap tersimpan untuk statistik
    }

    Diarsipkan --> [*]

    note right of Draft
        Tabel: laporan_hama, master_opt,
        master_kecamatan, master_desa,
        users, laporan_hama_tags, tags,
        activity_log
    end note

    note right of Diverifikasi
        Controller: LaporanHamaController
        Model: LaporanHama
    end note
```

---

## FLOWCHART 4: PIPELINE DATA SCRAPING (CUACA & PERTANIAN)

```mermaid
graph TD
    subgraph A_CurahHujan ["A. PIPELINE CURAH HUJAN<br/>(CurahHujanScraper → CurahHujanController)"]
        A1["Admin Klik<br/>'Jalankan Scraper'"] --> A2{"Pilih Sumber:<br/>NASA | Open-Meteo | BMKG | Simulasi"}
        A2 --> A3["Fallback Chain Dimulai"]
        A3 --> A4{"NASA POWER API<br/>(Prioritas 1)<br/>cURL Multi, 45s timeout"}
        A4 -->|Berhasil| A5["Parse Data<br/>(31 kecamatan × 30 hari)"]
        A4 -->|Gagal| A6{"Open-Meteo API<br/>(Prioritas 2)<br/>200ms delay, 30s timeout"}
        A6 -->|Berhasil| A5
        A6 -->|Gagal| A7{"BMKG API<br/>(Prioritas 3)<br/>3x retry + exponential backoff"}
        A7 -->|Berhasil| A5
        A7 -->|Gagal| A8["Simulasi<br/>(Prioritas 99)"]
        A8 --> A5
        A5 --> A9["Validasi: range 0-500mm<br/>Deteksi duplikat (tanggal + lokasi)"]
        A9 --> A10["Simpan ke tabel:<br/>curah_hujan + curah_hujan_logs"]
    end

    subgraph B_KecepatanAngin ["B. PIPELINE KECEPATAN ANGIN<br/>(KecepatanAnginScraper → KecepatanAnginController)"]
        B1["Admin Klik<br/>'Jalankan Scraper'"] --> B2["Fallback Chain"]
        B2 --> B3{"NASA POWER API<br/>(WS10M, WS2M)"}
        B3 -->|Berhasil| B4["Transformasi:<br/>m/s × 3.6 = km/h<br/>degrees → cardinal direction"]
        B3 -->|Gagal| B5{"Open-Meteo API"}
        B5 -->|Berhasil| B4
        B5 -->|Gagal| B6["Simulasi"]
        B6 --> B4
        B4 --> B7["Analitik:<br/>Beaufort scale, spray recommendation<br/>pest risk scoring"]
        B7 --> B8["Simpan ke tabel:<br/>kecepatan_angin + kecepatan_angin_logs"]
    end

    subgraph C_HargaKomoditas ["C. PIPELINE HARGA KOMODITAS<br/>(HargaKomoditasScraper → HargaKomoditasController)"]
        C1["Admin Klik<br/>'Jalankan Scraper'"] --> C2{"SISKAPERBAPO Jatim API"}
        C2 -->|Berhasil| C3["Filter: Region Jember<br/>Validasi harga Rp5.000-30.000"]
        C2 -->|Gagal| C4["Simulasi"]
        C4 --> C3
        C3 --> C5["Estimasi:<br/>GKP = 52% × Medium<br/>GKG = 60% × Medium"]
        C5 --> C6["Simpan ke tabel:<br/>harga_komoditas + harga_komoditas_logs + harga_alerts"]
    end

    subgraph D_BPS ["D. PIPELINE DATA BPS<br/>(BpsScraper → BpsScraperController)"]
        D1["Admin Klik<br/>'Jalankan Scraper'"] --> D2["Orchestrator: BpsScraper.php"]
        D2 --> D3{"BpsApiClient<br/>(WebAPI Resmi)"}
        D3 -->|Berhasil| D4["BpsDataService.processRecords()"]
        D3 -->|Gagal| D5["BpsSimulationService<br/>(Fallback)"]
        D5 --> D4
        D4 --> D6["applyConversions:<br/>GKG×0.577=Beras<br/>produktivitas=(gabah/luas)×10"]
        D6 --> D7["validateRecord:<br/>range check produktivitas 30-80 ku/ha"]
        D7 --> D8{"Valid?"}
        D8 -->|Tidak| D9["logAnomaly<br/>(selisih > 5 ku/ha)"]
        D9 --> D10["Upsert ke data_pertanian_bps<br/>(UNIQUE KEY tahun+kabupaten)"]
        D8 -->|Ya| D10
        D10 --> D11["updateYearlySummary"]
        D11 --> D12["Simpan log:<br/>bps_scraping_logs + bps_data_anomalies"]
    end

    style A4 fill:#ffc107,color:#000
    style A6 fill:#ffc107,color:#000
    style A7 fill:#ffc107,color:#000
    style B3 fill:#ffc107,color:#000
    style B5 fill:#ffc107,color:#000
    style C2 fill:#ffc107,color:#000
    style D3 fill:#ffc107,color:#000
    style D8 fill:#ffc107,color:#000
    style A10 fill:#28a745,color:#fff
    style B8 fill:#28a745,color:#fff
    style C6 fill:#28a745,color:#fff
    style D12 fill:#28a745,color:#fff
```

---

## FLOWCHART 5: ALUR IMPORT DATA (EXCEL & KSA)

```mermaid
graph TD
    subgraph A_ImportExcel ["A. IMPORT EXCEL UMUM<br/>(ExcelImportService.php)"]
        A1["Admin Upload File<br/>(.xlsx/.xls/.csv, max 5MB)"] --> A2["Validasi MIME via finfo<br/>+ extension whitelist"]
        A2 --> A3{"File Valid?"}
        A3 -->|Tidak| A4["Tampilkan Error"]
        A3 -->|Ya| A5["Preview: Parse 10 baris pertama"]
        A5 --> A6["Tampilkan di Modal<br/>(column mapping preview)"]
        A6 --> A7{"Admin Konfirmasi?"}
        A7 -->|Tidak| A8["Batal Import"]
        A7 -->|Ya| A9["Column Mapping Otomatis<br/>(header Indonesia/Inggris → DB column)"]
        A9 --> A10["Per-baris: validasi tipe data + range<br/>+ normalizeNumber()"]
        A10 --> A11{"Semua Baris Valid?"}
        A11 -->|Tidak| A12["Catat errors[] + warnings[]"]
        A12 --> A13["Upsert ke tabel target:<br/>data_pertanian_bps / harga_komoditas<br/>kecepatan_angin / evaluasi_akurasi"]
        A11 -->|Ya| A13
        A13 --> A14["Hasil: {successCount, failedCount,<br/>errors[], warnings[]}"]
    end

    subgraph B_ImportKSA ["B. IMPORT KSA<br/>(KsaImportService.php)"]
        B1["Admin Upload File KSA BPS<br/>(.xlsx)"] --> B2{"Format File?"}
        B2 -->|Fixed Annual<br/>(2018-2025)| B3["3 Sheet: luas panen,<br/>prod gabah, prod beras"]
        B2 -->|Monthly 2026| B4["Sheet 'Level KABKOT'<br/>dengan blok per-variabel"]
        B3 --> B5["Parser: ZipArchive +<br/>SimpleXMLElement"]
        B4 --> B5
        B5 --> B6["Header Parser:<br/>teks tanggal ('Jan 18', 'Feb-26*')<br/>atau serial Excel"]
        B6 --> B7["Mapping nama kabupaten:<br/>'[3501] PACITAN' → 'Pacitan'"]
        B7 --> B8["Status data:<br/>tetap / sementara / potensi<br/>(dari tanda *)"]
        B8 --> B9["Simpan ke tabel:<br/>data_ksa_bulanan"]
        B9 --> B10{"Sync to Annual?"}
        B10 -->|Ya| B11["Agregasi data 'tetap'<br/>→ upsert ke data_pertanian_bps"]
        B10 -->|Tidak| B12["Selesai"]
        B11 --> B13{"Perlindungan:<br/>TIDAK timpa data manual<br/>yang bukan dari KSA?"}
        B13 -->|Ya| B12
        B13 -->|Tidak| B14["Skip overwrite<br/>log warning"]
        B14 --> B12
    end

    A1 --> A2
    B1 --> B2

    style A3 fill:#ffc107,color:#000
    style A7 fill:#ffc107,color:#000
    style A11 fill:#ffc107,color:#000
    style B2 fill:#ffc107,color:#000
    style B10 fill:#ffc107,color:#000
    style B13 fill:#ffc107,color:#000
    style A4 fill:#dc3545,color:#fff
    style A8 fill:#dc3545,color:#fff
    style A14 fill:#28a745,color:#fff
    style B12 fill:#28a745,color:#fff
```

---

## FLOWCHART 6: DASHBOARD & VISUALISASI DATA

```mermaid
graph TD
    D1["User Login"] --> D2["Redirect ke /dashboard"]
    D2 --> D3["DashboardController::index()"]

    D3 --> D4["Muam Data Statistik"]
    D4 --> D5["Widget Stat Box:<br/>Total Laporan, Terverifikasi,<br/>Jumlah OPT, Jumlah Petugas"]

    D3 --> D6["Mini Map (Leaflet)"]
    D6 --> D7["AJAX ke /api/dashboard/map/hama"]
    D7 --> D8["MarkerCluster grouping<br/>color-coded severity<br/>(merah/kuning/hijau)"]
    D8 --> D9["OpenStreetMap Tiles"]

    D3 --> D10["Chart.js Visualisasi"]
    D10 --> D11["Line Chart: Tren Laporan Bulanan<br/>(12 bulan)"]
    D10 --> D12["Bar Chart: Top 5 OPT<br/>(hama terbanyak)"]

    D3 --> D13["Tabel Laporan Terkini<br/>(10 terakhir, terverifikasi)"]

    subgraph DashboardPadi ["DASHBOARD PADI (/dashboardPadi)"]
        DP1["DashboardPadiController"] --> DP2["Data produksi per-kecamatan<br/>dari tabel produksi_gabah"]
    end

    subgraph PetaSebaran ["PETA SEBARAN (/dashboard/map)"]
        PS1["Leaflet Map Fullscreen"] --> PS2["Layer Toggle:<br/>Hama | Irigasi | Cuaca"]
        PS2 --> PS3["Filter: status, kecamatan, tanggal"]
    end

    subgraph GrafikStatistik ["GRAFIK & STATISTIK (/dashboard/charts)"]
        GS1["Multi-chart Analytics"] --> GS2["Filter Periode"]
    end

    D3 --> DashboardPadi
    D3 --> PetaSebaran
    D3 --> GrafikStatistik

    style D2 fill:#28a745,color:#fff
    style D9 fill:#28a745,color:#fff
    style DP2 fill:#28a745,color:#fff
    style PS3 fill:#28a745,color:#fff
    style GS2 fill:#28a745,color:#fff
```

---

## FLOWCHART 7: SISTEM IRIGASI & RULE ENGINE

```mermaid
graph TD
    subgraph A_LaporanIrigasi ["A. LAPORAN IRIGASI<br/>(IrigasiController)"]
        A1["Petugas → /irigasi/create"] --> A2["Pilih: kecamatan, desa,<br/>nama saluran/bendung"]
        A2 --> A3["Input: debit air (L/det),<br/>tinggi muka air, kondisi pintu air"]
        A3 --> A4["Upload Foto"]
        A4 --> A5["Submit → status 'submitted'"]
        A5 --> A6["Operator/Admin Verify<br/>→ status 'verified'"]
    end

    subgraph B_MonitoringIrigasi ["B. MONITORING IRIGASI<br/>(IrigasiScraperController)"]
        B1["Admin → /irigasiScraper/runScraper"] --> B2["IrigasiScraper.php:<br/>simulasi 20 bendung Jember"]
        B2 --> B3["Per-bendung:<br/>debit = norm_debit × seasonal_coeff × fluctuation"]
        B3 --> B4["Kategorisasi:"]
        B4 --> B5["Aman (≥60%)"]
        B4 --> B6["Waspada (30-60%)"]
        B4 --> B7["Kritis (<30%)"]
        B5 & B6 & B7 --> B8["Simpan ke tabel:<br/>data_irigasi"]
    end

    subgraph C_RuleEngine ["C. RULE ENGINE<br/>(IrrigationRuleEngine.php)"]
        C1["Admin → /irigasi/rules"] --> C2["Buat Rule JSON"]
        C2 --> C3["Kondisi:<br/>sensor (soil_moisture, water_ph,<br/>water_flow, temperature, humidity,<br/>water_level) + cuaca (rain, temp)<br/>+ waktu (jam, hari)"]
        C3 --> C4["Operator logika:<br/>AND, OR, perbandingan<br/>(=, !=, <, >, BETWEEN, IN)"]
        C4 --> C5["Aksi:<br/>irrigation_start, irrigation_stop,<br/>alert, log, notification"]
        C5 --> C6["Cooldown Check<br/>(mencegah re-trigger)"]
        C6 --> C7["Eksekusi Rule"]
        C7 --> C8["Log ke:<br/>irrigation_logs + irrigation_rule_logs"]
    end

    A1 --> A2
    B1 --> B2
    C1 --> C2

    style B5 fill:#28a745,color:#fff
    style B6 fill:#ffc107,color:#000
    style B7 fill:#dc3545,color:#fff
    style C7 fill:#007bff,color:#fff
    style C8 fill:#007bff,color:#fff
```

---

## FLOWCHART 8: FEEDBACK & MASUKAN PENGGUNA

```mermaid
graph LR
    F1["User (Semua Role)<br/>→ /feedback/create"] --> F2["Input: Kategori<br/>(Bug/Saran/Pertanyaan)"]
    F2 --> F3["Input: Prioritas<br/>(low/medium/high/critical)"]
    F3 --> F4["Upload: Lampiran<br/>(JPG/PNG/GIF/WEBP/PDF, max 5MB)"]
    F4 --> F5["Submit → status 'pending'"]
    F5 --> F6["Admin Review"]
    F6 --> F7{"updateStatus()"}
    F7 --> F8["in_progress<br/>(sedang ditangani)"]
    F7 --> F9["resolved<br/>(selesai)"]
    F7 --> F10["closed<br/>(ditutup)"]
    F8 & F9 & F10 --> F11["Status History Tercatat<br/>di tabel feedback_status_history"]

    F12["User Lain"] --> F13["Upvote /feedback/vote/{id}"]
    F13 --> F14["Tabel feedback_votes<br/>(unique per user+feedback)"]

    F15["Admin"] --> F16["/feedback/report<br/>(admin only)"]
    F16 --> F17["Ringkasan:<br/>per-kategori, per-prioritas, per-status"]

    F5 --> F11
    F8 --> F11
    F9 --> F11
    F10 --> F11

    style F5 fill:#007bff,color:#fff
    style F8 fill:#ffc107,color:#000
    style F9 fill:#28a745,color:#fff
    style F10 fill:#6c757d,color:#fff
    style F11 fill:#28a745,color:#fff
```

---

## FLOWCHART 9: INTEGRASI API EKSTERNAL

```mermaid
graph LR
    subgraph A_ApiController ["A. ApiController (/api/*)"]
        A1["Auth: X-API-Key header<br/>→ ApiAuthMiddleware"] --> A2["Rate Limit:<br/>Security::checkRateLimit()"]
        A2 --> A3["GET /api/getPestDistribution"]
        A2 --> A4["GET /api/getStats"]
        A2 --> A5["GET /api/getTopPests"]
        A2 --> A6["POST /api/submitReport"]
        A2 --> A7["GET /api/getMitra"]
        A2 --> A8["GET /api/getKegiatan"]
        A2 --> A9["POST /api/addHonorPoptPelaporan"]
        A2 --> A10["GET /api/validateSBML"]
    end

    subgraph B_ApiBpsController ["B. ApiBpsController (/api/v1/bps/*)"]
        B1["Auth: X-API-Key + RateLimiter<br/>(100 req/menit)"] --> B2["GET /api/v1/bps/data"]
        B1 --> B3["GET /api/v1/bps/statistics"]
        B1 --> B4["GET /api/v1/bps/trend"]
        B1 --> B5["POST /api/v1/bps/scrape<br/>(background queue)"]
        B1 --> B6["GET /api/v1/bps/status/{jobId}"]
        B1 --> B7["GET /api/v1/bps/provinsi"]
        B1 --> B8["GET /api/v1/bps/kabupaten-list"]
    end

    A1 --> A2
    B1 --> B2 & B3 & B4 & B5 & B6 & B7 & B8

    style A1 fill:#007bff,color:#fff
    style B1 fill:#007bff,color:#fff
    style A2 fill:#ffc107,color:#000
    style B5 fill:#007bff,color:#fff
```

---

## FLOWCHART 10: ERD (ENTITY RELATIONSHIP DIAGRAM)

```mermaid
erDiagram
    users ||--o{ laporan_hama : "membuat"
    users ||--o{ laporan_irigasi : "membuat"
    users ||--o{ laporan_lainnya : "membuat"
    users ||--o{ feedback : "mengirim"
    users ||--o{ activity_log : "tercatat"

    master_kabupaten ||--o{ master_kecamatan : "memiliki"
    master_kecamatan ||--o{ master_desa : "memiliki"
    master_kecamatan ||--o{ produksi_gabah : "lokasi"
    master_desa ||--o{ laporan_hama : "lokasi"
    master_opt ||--o{ laporan_hama : "jenis hama"

    feedback ||--o{ feedback_votes : "divote"
    feedback ||--o{ feedback_status_history : "riwayat status"
    laporan_hama ||--o{ laporan_hama_tags : "memiliki tag"
    tags ||--o{ laporan_hama_tags : "diterapkan"

    users {
        bigint id PK
        varchar username
        varchar password
        varchar email
        varchar nama_lengkap
        enum role "admin|operator|statistisi|petugas|viewer"
        tinyint aktif
        timestamp created_at
        timestamp updated_at
    }

    laporan_hama {
        bigint id PK
        bigint user_id FK
        bigint master_opt_id FK
        bigint kabupaten_id FK
        bigint kecamatan_id FK
        bigint desa_id FK
        date tanggal
        text lokasi
        decimal latitude
        decimal longitude
        varchar tingkat_keparahan
        decimal populasi
        decimal luas_serangan
        varchar foto_url
        enum status "Draft|Submitted|Diverifikasi|Ditolak|Diarsipkan"
        text catatan
        text catatan_verifikasi
        bigint verified_by FK
        timestamp verified_at
        varchar nomor_laporan
    }

    master_opt {
        bigint id PK
        varchar nama_opt
        varchar nama_lokal
        varchar nama_ilmiah
        varchar golongan
        varchar jenis
        text etl_acuan
    }

    master_kecamatan {
        bigint id PK
        bigint kabupaten_id FK
        varchar kode_kecamatan
        varchar nama_kecamatan
    }

    master_desa {
        bigint id PK
        bigint kecamatan_id FK
        varchar kode_desa
        varchar nama_desa
        varchar kode_pos
    }

    feedback {
        bigint id PK
        bigint user_id FK
        enum jenis_feedback "Bug|Saran|Pertanyaan"
        varchar judul
        text deskripsi
        enum prioritas "low|medium|high|critical"
        enum status "pending|in_progress|resolved|closed"
        varchar attachment_url
        text admin_notes
        timestamp created_at
    }

    feedback_votes {
        bigint id PK
        bigint feedback_id FK
        bigint user_id FK
        timestamp created_at
    }

    feedback_status_history {
        bigint id PK
        bigint feedback_id FK
        enum status_lama
        enum status_baru
        bigint changed_by FK
        text catatan
        timestamp created_at
    }

    laporan_hama_tags {
        bigint id PK
        bigint laporan_hama_id FK
        bigint tag_id FK
    }

    tags {
        bigint id PK
        varchar nama_tag
        varchar jenis_tag
    }

    curah_hujan {
        bigint id PK
        date tanggal
        varchar lokasi
        decimal curah_hujan_mm
        varchar kategori
        varchar sumber_data
        timestamp created_at
    }

    kecepatan_angin {
        bigint id PK
        date tanggal
        varchar lokasi
        decimal kecepatan_avg
        decimal kecepatan_max
        varchar arah_angin
        varchar cardinal_direction
        varchar kategori_angin
        varchar sumber_data
        timestamp created_at
    }

    harga_komoditas {
        bigint id PK
        date tanggal
        enum jenis_komoditas "gabah_kering_panen|gabah_kering_giling|beras_medium|beras_premium"
        decimal harga
        varchar satuan
        varchar lokasi
        varchar sumber_data
        timestamp created_at
    }

    data_pertanian_bps {
        bigint id PK
        int tahun
        varchar kabupaten_kota
        decimal luas_panen
        decimal produksi_gabah
        decimal produksi_beras
        decimal produktivitas
        varchar sumber_data
        timestamp created_at
        timestamp updated_at
    }

    data_ksa_bulanan {
        bigint id PK
        int tahun
        int bulan
        varchar kabupaten
        decimal luas_panen
        decimal produksi_gabah
        decimal produksi_beras
        enum status_data "tetap|sementara|potensi"
        timestamp created_at
    }

    data_irigasi {
        bigint id PK
        date tanggal
        varchar nama_bendung
        varchar daerah_irigasi
        varchar kecamatan
        decimal luas_sawah
        decimal debit_air
        varchar status_pintu
        text keterangan
        timestamp created_at
    }

    produksi_gabah {
        bigint id PK
        bigint kecamatan_id FK
        int tahun
        int bulan
        decimal produksi_gabah
        decimal produksi_beras
        decimal luas_panen
        decimal produktivitas
        timestamp created_at
    }

    activity_log {
        bigint id PK
        bigint user_id FK
        varchar aksi
        varchar tabel
        bigint record_id
        text deskripsi
        varchar ip_address
        timestamp created_at
    }
```

---

## Catatan Implementasi

1. **Sintaks Mermaid**: Semua diagram menggunakan sintaks Mermaid yang valid untuk rendering di GitHub, GitLab, dan Markdown viewers yang mendukung Mermaid.
2. **Warna Konsisten**:
   - Hijau (`#28a745`): Status sukses/completed
   - Merah (`#dc3545`): Error/gagal
   - Kuning (`#ffc107`): Warning/pending/percabangan
   - Biru (`#007bff`): Proses/action
3. **Referensi Kode**: Semua controller, service, model, dan tabel telah diverifikasi against codebase aktual di `C:\laragon\www\jagapadi-3509`.
4. **Bahasa Indonesia**: Semua label node menggunakan bahasa Indonesia sesuai spesifikasi.
