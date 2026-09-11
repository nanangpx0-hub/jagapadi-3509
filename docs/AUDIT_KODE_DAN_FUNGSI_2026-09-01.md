# Audit Kode dan Fungsi JAGAPADI

**Tanggal snapshot:** 1 September 2026  
**Cakupan:** runtime root/integrated, Backend v1, Flutter Android, migration,
kontrak API, dan pengujian terkait  
**Sifat audit:** read-only terhadap kode produksi; hanya dokumen ini yang dibuat

## 1. Ringkasan Eksekutif

JAGAPADI telah berkembang menjadi sistem monorepo besar dan fungsional, tetapi
risiko utamanya bukan kekurangan fitur. Risiko utamanya adalah dua runtime PHP
yang hidup berdampingan, kontrak dan schema yang tersebar, controller/view yang
monolitik, serta quality gate yang belum sepenuhnya konsisten. Backend v1 adalah
bagian paling terstruktur dan paling teruji. Runtime root memiliki cakupan fitur
lebih luas, tetapi membawa debt arsitektur, variasi konvensi, dan dua kegagalan
integration test yang dapat direproduksi.

Skor kesehatan keseluruhan: **66/100 (cukup, belum layak disebut sehat tanpa
catatan)**.

| Dimensi | Skor | Kesimpulan |
|---|---:|---|
| Arsitektur | 58/100 | MVC ringan bekerja, tetapi dua runtime dan fallback dinamis memperbesar drift |
| Keamanan | 70/100 | Guard penting tersedia; cache deserialization dan konsistensi policy perlu diperkeras |
| Kualitas/maintainability | 57/100 | Banyak file besar, strict typing tidak merata, dan tanggung jawab bercampur |
| Kebenaran fungsi | 72/100 | Cakupan test baik; dua regresi integritas data root masih gagal |
| Performa | 64/100 | Pagination/cache ada; agregasi sinkron dan file besar menyulitkan optimasi |
| Testing/operasi | 74/100 | 509 PHP tests tersedia; quality gate lint/static/mobile belum deterministik |
| Dokumentasi/kontrak | 66/100 | Dokumen kaya, tetapi beberapa klaim dan route matrix sudah drift |

Keputusan prioritas: jadikan Backend v1 satu-satunya runtime canonical secara
operasional, hentikan penambahan fitur baru di root kecuali compatibility fix,
perbaiki dua regresi KSA, lalu kunci boundary keamanan/cache dan kontrak route.

## 2. Metode, Bukti, dan Batasan

Audit dilakukan terhadap workspace yang sedang memiliki banyak perubahan milik
pengguna. Karena itu kesimpulan menggambarkan **working tree**, bukan commit HEAD
bersih. Tidak ada file produksi yang diubah.

Metode yang digunakan:

1. Membaca `AGENTS.md`, `README.md`, `docs/BLUEPRINT.md`, dan
   `docs/REFERENSI_TEKNIS_BACKEND_AI.md` sebagai dokumen wajib.
2. Menginventarisasi 1.183 file di luar `vendor`/`node_modules`, 539 file PHP,
   sekitar 319 kelas, dan sekitar 4.056 deklarasi fungsi/metode PHP.
3. Menelusuri front controller, route, middleware, controller, service/model,
   migration, Flutter client, serta kontrak/test yang relevan.
4. Memindai raw input, dynamic SQL, process execution, serialization, output
   escaping, empty catch, file besar, strict types, dan duplikasi nama modul.
5. Menjalankan PHPUnit root dan Backend v1 serta Flutter tests. Tidak dilakukan
   destructive test, exploit aktif, load test produksi, atau perubahan database.

Ukuran utama:

| Area | File | Perkiraan baris |
|---|---:|---:|
| `app/` root | 204 | 96.496 |
| `backend/app/` | 130 | 36.647 |
| `mobile/lib/` | 89 | 22.321 |
| root tests | 52 | 8.076 |
| backend tests | 28 | 2.922 |
| E2E tests | 27 | 5.270 |
| migration di tiga lokasi | 62 | 3.107 |

Batas keyakinan: audit statis dapat menemukan pola risiko, tetapi tidak dapat
membuktikan tidak adanya kerentanan. Validasi final memerlukan database staging
dengan schema produksi, HTTP security tests, EXPLAIN pada data representatif,
dan DAST/dependency scan di CI.

## 3. Arsitektur dan Alur Sistem

### 3.1 Topologi aktual

```text
Browser root -> index.php -> app/core/Router.php atau web route/fallback
             -> root controller -> service/model -> PDO -> database root

Browser v1  -> backend/public/index.php -> backend Router + global middleware
             -> Web controller -> service/model -> PDO -> database v1

Flutter     -> AppConfig/API client -> /api/v1 -> JWT middleware
             -> API controller -> model/service -> database v1
```

Pola yang digunakan adalah Front Controller + Router + MVC ringan, ditambah
Service Layer secara parsial, Repository/Active Record-like Model, middleware,
helper, event/notification, cache-aside, dan offline-first provider/local DB di
Flutter. Pola ini cukup untuk beban daerah berskala moderat, tetapi penerapannya
tidak seragam: root masih sering menempatkan validasi, SQL, orchestration,
rendering decision, logging, dan transaksi dalam controller yang sama.

### 3.2 Evaluasi skalabilitas

Kekuatan:

- Backend v1 mempunyai route eksplisit, middleware global, controller web/API
  terpisah, strict types, ownership tests, cache, JWT revocation, dan migration.
- Prepared statement dominan; pagination dan batas maksimum sudah muncul pada
  banyak endpoint.
- Workflow laporan, nomor laporan, ownership, idempotensi, upload validation,
  serta cache invalidation sudah memperoleh test khusus.
- Flutter mempunyai API abstraction, secure storage, local database, sync,
  provider, dan error mapping.

Kelemahan:

- Dua runtime menggandakan auth, router, controller, model, cache, security,
  schema, dan aturan status. Setiap perubahan harus dipelihara dua kali.
- Root fallback `/{controller}/{method}/{params}` membuat attack surface dan
  contract surface lebih besar daripada route map eksplisit.
- Cache file lokal dan queue file lokal tidak secara alami konsisten pada
  multi-instance deployment.
- Scraper/import/analitik sinkron berpotensi menahan PHP worker lama.
- Tiga lokasi migration menciptakan risiko schema drift dan rollback yang sulit.

Kesimpulan: arsitektur dapat dipelihara untuk satu instance dan tim kecil,
tetapi tidak akan berskala secara organisasi tanpa konsolidasi runtime,
boundary domain, dan quality gate otomatis.

## 4. Pemetaan Fungsi Utama

| Domain | Entry point dan fungsi utama | Dependensi | Output/side effect | Risiko alur |
|---|---|---|---|---|
| Auth web root | `AuthController@login/doLogin/logout/changePassword` | session, `Security`, `User`, brute-force limiter | session/cookie, redirect, audit | guard tersebar antara front controller dan controller |
| Auth web v1 | `Web\AuthController`, `PasswordController` | `Request`, `Security`, `User`, rate limiter | session regenerate, forced password change | paling terstruktur |
| Auth API v1 | `Api\AuthController@login/refresh/logout/changePassword` | `Jwt`, blacklist/token version, `ApiAuthMiddleware` | JWT, revocation, JSON | secret/issuer/audience wajib konsisten antar env |
| Hama | list/create/update/delete/submit/resubmit/verify/reject/archive, foto/video/history | user, OPT, wilayah, uploader, notification, cache | laporan + status history + nomor | ownership/status/transaksi adalah invariant utama |
| Irigasi | alur laporan setara hama + monitoring/rule | wilayah, uploader, notification, cache | laporan + agregat | duplikasi implementasi meningkatkan drift |
| Pupuk/Panen/Cuaca/Alat | CRUD + workflow generik API/mobile | model per jenis, base screen/provider | laporan dan foto | model/controller hampir paralel; kandidat generic domain service |
| Laporan lainnya | schema-driven form, JSON payload, workflow, export, recycle bin | master jenis, JSON validator, soft delete | laporan fleksibel | status root memakai variasi case pada sebagian jalur |
| Dashboard | KPI/chart/map/activity/lainnya | `DashboardService`/`DashboardDataAggregator`, cache, banyak tabel | agregat role-scoped | query agregat besar; dua test integritas sumber data gagal |
| Wilayah | hierarchy/search/admin CRUD/import/audit | kabupaten-kecamatan-desa, FK | master data | root controller sangat besar; operasi bulk mahal |
| OPT/usulan OPT | master CRUD, proposal, review, foto, import/export | proposal/review/master services | master dan workflow proposal | kompleksitas tinggi, banyak endpoint dan branching |
| Feedback | input pribadi, admin summary/report/status/vote | upload, history, notification | feedback dan audit | implementasi lintas runtime perlu matriks ownership |
| Notifikasi/device | list/unread/read/delete, register/delete token | authenticated user, FCM best effort | DB notification/device token | DB harus menjadi source of truth |
| Export | CSV/XLSX/PDF per domain | scoped query, writer | streaming file | memory/CSV injection perlu batas dan sanitasi |
| Scraper/BPS/KSA | run/background/status/import/sync | HTTP source, parser, DB, queue | data eksternal dan log | proses panjang, source drift, idempotensi |
| Cuaca/angin/harga | scraper, statistik, chart, alert | external source, cache, models | observasi dan alert | synchronous retry dan stale-data semantics |
| Storytelling/evaluasi | generate/save/publish/chart/snapshot | agregator, KSA/BPS/cuaca | analisis naratif/snapshot | tidak boleh dianggap bukti kausal; KSA regression aktif |
| Recycle bin | index/restore/bulk restore/bulk delete | module allowlist, soft-delete tables | restore/permanent delete | transaksi bulk dan cap 5.000 harus diuji beban |
| Flutter offline | local draft, sync queue, providers, screens | SQLite, connectivity, API client, secure storage | draft lokal/server | konflik/resume/idempotensi dan canonical base URL |

Alur fungsi laporan canonical:

```text
input -> autentikasi -> role/ownership -> normalisasi/validasi
      -> transaksi model -> status history/nomor atomik
      -> notification -> cache invalidation -> JSON/HTML response

Draf -> Submitted -> Diverifikasi -> Diarsipkan
                 \-> Ditolak -> Draf/Submitted oleh pemilik
```

Dependensi silang paling kritis adalah authenticated user -> query ownership,
status -> policy transition, mutation -> cache invalidation, dan source data ->
dashboard/evaluasi. Keempat relasi ini wajib memiliki integration test.

## 5. Temuan Terperinci

### F-01 — Dua runtime aktif menciptakan contract dan security drift

**Risiko: Tinggi.** Root dan Backend v1 memiliki router, auth, controller,
model, cache, middleware, migration, dan status representation berbeda.
Duplikasi nama kelas memperbesar kemungkinan developer memperbaiki runtime yang
salah. Mobile default saat ini juga menunjuk path root compatibility, sedangkan
dokumen menetapkan Backend v1 sebagai canonical.

**Dampak:** patch keamanan/bug dapat hanya masuk satu runtime; schema dan API
berbeda; hasil test lokal tidak mewakili production document root.

**Rekomendasi:** tegakkan ADR-011 sebagai strangler plan; bekukan fitur root,
tambahkan compatibility tests per endpoint, telemetry penggunaan route root,
dan hapus fallback bertahap setelah semua client pindah. CI harus menjalankan
matrix `runtime=root|backend-v1` dengan database terpisah.

### F-02 — Dua integration test root gagal pada integritas sumber data KSA

**Risiko: Tinggi untuk kebenaran analitik; Sedang untuk availability.** Suite
root menghasilkan 2 failure dari 274 test:

- `EnvironmentalAnalysisDatabaseTest::testEvaluationSnapshotUsesExactMonthlyKsaAndZeroReleaseIsUndefined`;
- `DashboardReportIntegrityDatabaseTest::testDashboardPadiChoosesKsaAndReturnsOnePointPerYear`.

Yang pertama gagal membuat snapshot sesuai data KSA bulanan; yang kedua
menghasilkan source `null`, bukan `ksa`. Ini dapat mengubah angka dashboard dan
evaluasi yang dipakai pengambilan keputusan.

**Rekomendasi:** telusuri precedence source pada `DashboardPadi` dan
`EvaluasiAkurasi::snapshotEstimasi`, verifikasi fixture/migration database target,
dan tambahkan assertion untuk exact month, zero-release, fallback source, serta
satu titik per tahun. Jangan menurunkan test agar hijau.

### F-03 — Deserialisasi cache PHP mengizinkan object instantiation

**Risiko: Tinggi bila cache/file/Redis dapat ditulis pihak lain; Sedang pada
deployment terisolasi.** Root menggunakan `unserialize(...,
['allowed_classes' => true])`; Backend v1 memanggil `unserialize()` tanpa
`allowed_classes => false`. Cache poisoning dapat berubah menjadi PHP object
injection jika ada gadget class dan boundary cache dilanggar.

**Rekomendasi actionable:** simpan payload JSON untuk array/scalar atau gunakan
`unserialize($payload, ['allowed_classes' => false])`; tambahkan HMAC/version
pada file cache; pastikan Redis private, authenticated, TLS/network ACL; hapus
cache lama saat format berubah. Tambahkan test malicious serialized object.

### F-04 — Route matrix machine-readable salah mengklasifikasikan middleware

**Risiko: Sedang.** `docs/ROUTE_MATRIX.json` menandai banyak endpoint JWT
sebagai `Public` walaupun `backend/config/routes.php` jelas memasang
`ApiAuthMiddleware`. Parser regex di `scripts/generate-route-matrix.php` gagal
menangkap middleware secara andal. Rate limit global juga tampak `none` karena
generator hanya membaca middleware per-route.

**Dampak:** security reviewer, developer, atau automation dapat membuat policy
berdasarkan data salah.

**Rekomendasi:** jangan parse PHP route dengan regex. Instrumentasikan Router
dalam mode metadata atau gunakan PHP AST; snapshot hasil registrasi aktual;
buat contract test yang membandingkan method/path/controller/middleware dengan
OpenAPI. Gagal CI jika endpoint protected terklasifikasi public.

### F-05 — Root dynamic fallback memperbesar attack/maintenance surface

**Risiko: Sedang-Tinggi.** Root dapat memanggil public method controller lewat
konvensi jika file dan method callable. Front controller memakai allowlist nama
method untuk menentukan mutasi/CSRF. Controller modern juga memanggil
`requireStateChangingRequest`, tetapi defense ini tidak dapat dijamin untuk
4.056 method dan fitur lama.

**Rekomendasi:** default-deny; route eksplisit menyimpan HTTP method, auth,
role, CSRF, dan handler. Non-action methods controller harus private/protected.
Tambahkan test yang mengiterasi public controller methods dan gagal jika tidak
terdaftar. Deprecate fallback dengan log dan removal deadline.

### F-06 — File monolitik dan separation of concerns lemah

**Risiko: Sedang-Tinggi.** Contoh: view curah hujan 2.995 baris, form laporan
2.347 baris, `BpsScraperController` 1.721 baris,
`KecepatanAnginController` 1.566 baris, `LaporanController` 1.328 baris,
`DashboardDataAggregator` 1.348 baris, dan form hama Flutter 1.216 baris.

**Dampak:** review sulit, regresi tinggi, test unit membutuhkan banyak setup,
dan perubahan UI/data saling mengganggu.

**Rekomendasi:** ekstrak use-case service per command/query, validator/policy
terpusat, partial/component view tanpa inline JS besar, dan Flutter widgets per
section. Target awal: controller <300 baris dan method cyclomatic complexity
<10; lakukan bertahap dengan characterization tests.

### F-07 — Strict typing dan standard kode tidak merata

**Risiko: Sedang.** Hanya 131 dari 319 file PHP aplikasi yang terdeteksi memakai
`declare(strict_types=1)`. Backend v1 lebih konsisten; root bercampur antara
camelCase/snake_case, tipe eksplisit/tanpa tipe, class namespace/non-namespace,
serta style brace berbeda.

**Rekomendasi:** PHP-CS-Fixer/PHP_CodeSniffer PSR-12, PHPStan pada baseline yang
terukur, larangan error baru di CI, dan strict types untuk file baru/yang
disentuh. Jangan mass rewrite; migrasikan domain per domain.

### F-08 — Tooling static analysis belum menjadi quality gate nyata

**Risiko: Sedang.** File `phpstan.neon` dan baseline tersedia/berubah, tetapi
binary PHPStan tidak tersedia pada dependency yang diaudit sehingga command
tidak menghasilkan analisis. Flutter analyzer juga tidak selesai dalam waktu
audit dan harus dihentikan, sehingga lint status belum diketahui.

**Rekomendasi:** deklarasikan PHPStan/PHP_CodeSniffer dalam `require-dev`, pin
versi, tambahkan Composer script tunggal, beri timeout CI, cache dependency, dan
unggah artifact hasil. Untuk Flutter gunakan `flutter analyze --fatal-infos`
dan batas waktu yang eksplisit.

### F-09 — Mobile default URL bertentangan dengan canonical runtime dan memakai HTTP

**Risiko: Sedang.** `AppConfig.baseUrl` default Android menunjuk
`http://10.0.2.2/jagapadi-3509/api/v1` (root compatibility), bukan Backend v1
di `:8080`. Production dapat memakai HTTPS melalui `dart-define`, tetapi build
tanpa define dapat salah runtime; token/data berjalan cleartext pada jaringan
non-emulator bila konfigurasi disalin.

**Rekomendasi:** tidak ada production fallback; release build harus gagal bila
`API_BASE_URL` kosong/non-HTTPS. Gunakan flavor dev/staging/prod dan compile-time
validation. Uji endpoint signature/health `runtime=backend-v1` sebelum login.

### F-10 — Error swallowing mengurangi observability

**Risiko: Sedang.** Backend cache/rate limiter memiliki beberapa `catch
(Throwable) {}` kosong; sejumlah JS view juga mengabaikan promise/catch. Fail-open
untuk cache dapat benar, tetapi tanpa metric/log tidak dapat dibedakan dari
operasi sehat. `UsulanOptController` juga memiliki catch kosong.

**Rekomendasi:** structured warning dengan request/correlation ID, tanpa secret;
counter `cache_error`, `rate_limiter_fallback`, `queue_retry`, dan UI error
boundary. Rate limiter harus memiliki keputusan fail-open/fail-closed yang
terdokumentasi per endpoint.

### F-11 — Cache invalidation dan cache lokal membatasi multi-instance

**Risiko: Sedang.** Dashboard mempunyai banyak key/filter/role, sementara
mutation tersebar pada banyak controller. File cache lokal tidak berbagi state
antar node. Root clear-prefix dan Backend v1 namespace membantu, tetapi tidak
membuktikan semua mutation menginvalidasi semua agregat.

**Rekomendasi:** event domain `ReportChanged` -> invalidator terpusat; shared
Redis di production; key harus memuat runtime, env, schema version, role, user,
dan normalized filter. Tambahkan test mutation->fresh aggregate untuk semua
jenis laporan dan dua user.

### F-12 — Query agregat, offset pagination, dan proses sinkron berpotensi bottleneck

**Risiko: Sedang.** Dashboard/scraper/import melakukan banyak agregasi dan SQL
dinamis yang aman melalui cast/allowlist pada sampel, tetapi mahal pada data
besar. Offset pagination melambat pada page tinggi. Scraper/import gambar/XLSX
dan export dapat memakai satu PHP worker serta memory besar.

**Rekomendasi:** capture slow query dan EXPLAIN ANALYZE pada dashboard/list;
buat composite index dari pola `user_id/status/tanggal/wilayah/deleted_at`;
gunakan keyset pagination; queue durable untuk scraper/import/export; stream
CSV/XLSX; beri row/file/time limits dan cancellation.

### F-13 — Duplikasi domain laporan meningkatkan bug drift

**Risiko: Sedang.** Hama, irigasi, pupuk, panen, cuaca, dan alat-sarana memiliki
controller/model/screen dengan alur CRUD/workflow yang hampir paralel. Ini
memudahkan satu modul tertinggal dalam ownership, archive transition, foto,
idempotensi, atau cache invalidation.

**Rekomendasi:** bukan satu mega-controller. Ekstrak shared policy/value object
untuk status transition, ownership scope, numbering, photo attachment, history,
dan idempotency; domain-specific validation tetap terpisah. Jalankan parameterized
contract test untuk seluruh jenis laporan.

### F-14 — Dokumentasi status proyek dan mobile sudah usang

**Risiko: Rendah-Sedang.** README menyebut `v1.0.0 Production Ready` dan mobile
“placeholder”, sedangkan mobile memiliki 22 ribu baris, versi 1.1.3+6, FCM,
offline database, dan banyak modul. Klaim production-ready juga tidak selaras
dengan dua failing integration tests dan dirty quality gates.

**Rekomendasi:** ubah status menjadi berbasis evidence: commit, migration
version, test counts, open incidents, dan last smoke date. Hapus narasi
placeholder dan tambahkan runtime support matrix.

### F-15 — Dynamic SQL perlu allowlist konsisten, walau tidak ditemukan exploit langsung

**Risiko: Sedang.** Ada 91 pemanggilan `query()` langsung dan banyak SQL yang
dirakit dinamis. Banyak fragmen adalah konstanta, integer cast, atau allowlist;
`backend/Core/Model` juga memvalidasi identifier. Namun pola ini mudah salah
pada perubahan berikutnya, terutama table/order/group fields.

**Rekomendasi:** identifier hanya dari enum/constant allowlist; values selalu
bind parameter; larang interpolasi request dalam query melalui custom static
rule. Tambahkan negative tests untuk sort/order/filter/table selector.

### F-16 — Output escaping masih bergantung disiplin per-view

**Risiko: Sedang.** Terdeteksi ratusan output variabel langsung dan ratusan
panggilan escaping. Angka tersebut bukan bukti XSS karena sebagian output
adalah angka/HTML trusted, tetapi template PHP tidak auto-escape sehingga satu
kelalaian cukup menghasilkan stored/reflected XSS.

**Rekomendasi:** helper `e()` wajib untuk text/attribute, helper khusus URL/JSON,
sanitizer allowlist untuk rich text, CSP tanpa inline script bertahap, dan test
payload XSS pada nama/deskripsi/catatan/feedback/filename/export.

### F-17 — Malware scan command adalah boundary konfigurasi berprivilege

**Risiko: Rendah-Sedang.** Path upload di-escape, tetapi keseluruhan
`MALWARE_SCAN_CMD` berasal dari environment dan dijalankan melalui `exec`.
Ini bukan injection dari nama file pada implementasi saat ini, namun kesalahan
konfigurasi mempunyai kemampuan command execution penuh.

**Rekomendasi:** ganti command template bebas dengan executable + fixed args
allowlist, verifikasi absolute binary, timeout proses, no-shell invocation bila
tersedia, dan fail-closed pada production sesuai policy. Jalankan service dengan
OS user berprivilege minimum.

### F-18 — Migration tersebar dan status eksekusi tidak terbukti dari filesystem

**Risiko: Tinggi operasional.** Migration berada di `database/migrations`,
`backend/database/migrations`, dan `migrations`. Audit ini tidak menganggap file
sebagai bukti migration telah dijalankan; tanpa snapshot `schema_migrations`
production, kebenaran schema belum dapat disimpulkan.

**Rekomendasi:** runner tunggal per runtime, checksum migration immutable,
pre-deploy verification, backup/restore rehearsal, dan laporan drift antara
filesystem, `schema_migrations`, `INFORMATION_SCHEMA`, serta `docs/DATABASE.md`.

## 6. Evaluasi Keamanan Positif

Kontrol yang terverifikasi ada dan perlu dipertahankan:

- session HttpOnly/SameSite, secure cookie saat HTTPS, regeneration, timeout;
- CSRF middleware Backend v1 dan guard request mutasi pada root/controller;
- JWT HS256 dengan secret minimum, `exp`, `sub`, `jti`, optional `iss/aud`,
  clock skew, blacklist/token version;
- role middleware dan ownership tests Petugas A/Petugas B/Admin;
- prepared statement dominan dan identifier validation pada model dasar;
- upload memeriksa error, ukuran, magic bytes, MIME, extension, dimensi, random
  filename, directory, malware hook, dan traversal saat delete;
- `.env`, key, certificate, keystore, upload, cache, dan session artifact masuk
  `.gitignore`; audit tracked filenames tidak menemukan secret nyata;
- status workflow, number assignment, draft exclusion, idempotency, dan archive
  mempunyai test di Backend v1.

Kontrol ini menurunkan risiko, tetapi tidak menggantikan DAST/dependency scan,
header verification, DB privilege audit, dan staging penetration test.

## 7. Error Handling dan Edge Case

| Skenario | Status | Catatan/rekomendasi |
|---|---|---|
| Input kosong/422 | Baik di v1 | Error map tersedia; root masih bervariasi redirect/session error |
| Auth token malformed/expired/future | Baik | JWT decode memeriksa struktur, signature, sub, exp, iat, nbf, jti |
| IDOR Petugas A -> B | Baik di test v1 | Pertahankan matrix untuk semua resource dan agregat |
| Draf diverifikasi | Ditest | Wajib tetap ditolak |
| Resubmit ditolak | Ditest | Nomor harus tetap |
| Duplicate mutation | Parsial baik | Idempotency middleware tersedia; perlu TTL/concurrency tests lintas node |
| Upload spoof/oversize/dimensi | Baik di helper | Tambah decompression bomb/polyglot/malware timeout tests |
| Database/cache unavailable | Parsial | Cache fail-open; error sebagian ditelan, observability kurang |
| External source timeout/schema berubah | Parsial | Harus mempertahankan last-good + stale marker, circuit breaker |
| Page/limit ekstrem | Umumnya di-cap | Gunakan keyset dan total count terkontrol |
| Bulk 0/5.000/>5.000 | Sebagian | Test transaksi, rollback, lock duration, dan authorization per item |
| Nilai nol vs null | Gagal pada test KSA | Zero release harus tetap undefined, bukan 0% |
| Offline sync conflict | Ada unit coverage | Tambah E2E reconnect, duplicate, token expiry, server rejection |
| HTML/CSV formula injection | Belum terbukti lengkap | Escape HTML dan prefix sel `=,+,-,@` pada export user data |

## 8. Dead Code dan Duplikasi

Audit tidak menghapus dead code karena runtime root memiliki fallback dinamis;
search reference biasa dapat salah menyebut method sebagai tidak terpakai.
Kandidat dead code harus ditentukan dengan gabungan route registry, template/JS
references, test coverage, dan production telemetry.

Kandidat konsolidasi paling jelas:

- pasangan `Security`, `CacheManager`, `RateLimiter`, `Model`, `Router`,
  `AuthController`, `DashboardController`, `Laporan*Controller/Model` lintas
  runtime;
- enam domain laporan Backend v1 dengan CRUD/workflow serupa;
- writer XLSX/root dan backend;
- JS/CSS inline berulang di view create/index/admin wilayah;
- route `usulan-opt/*` yang terdaftar duplikat dalam `config/web_routes.php`.

Jangan menghapus berdasarkan kemiripan nama saja. Buat usage manifest dan log
route selama minimal satu siklus operasional, lalu deprecate dengan test.

## 9. Hasil Pengujian

| Pemeriksaan | Hasil |
|---|---|
| PHP 8.2.32 | tersedia |
| Root PHPUnit | **Gagal:** 274 tests, 2.392 assertions, 2 failures, 2 deprecations |
| Backend v1 PHPUnit | **Lulus:** 235 tests, 594 assertions, 3 deprecations |
| PHPStan | Tidak dijalankan: binary tidak tersedia pada vendor yang diaudit |
| Flutter analyze | Tidak selesai dalam jendela audit; dihentikan, status tidak diketahui |
| Flutter test | **Lulus:** 254 tests, seluruhnya passed |
| E2E Playwright | Tidak dijalankan karena membutuhkan server/fixture aktif dan dapat mengubah DB |
| Migration verification | Tidak dijalankan terhadap production DB |
| Load/DAST | Di luar audit read-only ini |

Deprecation PHPUnit harus dianggap debt terjadwal: tampilkan detailnya di CI dan
tetapkan zero-new-deprecation policy.

## 10. Prioritas Perbaikan

### P0 — sebelum klaim release sehat (0–7 hari)

1. Perbaiki dua failing test KSA/dashboard dan validasi angka pada staging.
2. Tetapkan base URL mobile release ke HTTPS Backend v1; build gagal jika kosong.
3. Ganti unsafe cache deserialization dan rotasi/flush format cache.
4. Verifikasi `schema_migrations` dan schema production terhadap runtime target.
5. Tandai route matrix tidak authoritative sampai generator diperbaiki.

### P1 — hardening (1–4 minggu)

1. Runtime route registry eksplisit/default-deny; mulai matikan root fallback.
2. Contract test method/path/middleware/OpenAPI dan matrix ownership semua modul.
3. Jadikan PHPStan, PSR-12, Flutter analyze, secret/dependency scan sebagai CI gate.
4. Event-driven centralized cache invalidation dan shared cache production.
5. Structured logging/correlation ID serta metric untuk cache/rate/queue/source.

### P2 — maintainability/performance (1–3 bulan)

1. Ekstrak policy/workflow laporan bersama dan service per use case.
2. Pecah controller/view/screen >500 baris dengan characterization tests.
3. EXPLAIN/index review, keyset pagination, streaming export, durable queue.
4. Konsolidasikan migration runner, baseline DB, dan rollback rehearsal.
5. Telemetry route root lalu migrasi/hapus endpoint compatibility yang tidak dipakai.

### P3 — maturity (3–6 bulan)

1. Selesaikan strangler migration ke Backend v1.
2. SLO untuk latency/error/freshness scraper dan dashboard.
3. Staging DAST, restore drill, chaos test cache/DB/external source.
4. Security threat model dan review hak DB/OS/network per deployment.

## 11. Definition of Done yang Direkomendasikan

Sebuah perubahan domain laporan belum selesai sebelum:

- runtime dan route target eksplisit;
- auth, role, ownership, status transition, dan CSRF/JWT ditest negatif;
- Petugas A, Petugas B, dan Admin tercakup;
- query parameterized dan identifier allowlisted;
- mutation menginvalidasi cache yang tepat;
- migration append-only serta terverifikasi;
- API.md/OpenAPI/DATABASE.md sinkron jika kontrak berubah;
- PHPUnit/PHPStan/lint/Flutter/E2E relevan hijau;
- log tidak mengandung secret/PII berlebih;
- rollback dan observability tersedia.

## 12. Kesimpulan

JAGAPADI memiliki fondasi keamanan dan pengujian yang lebih kuat daripada
aplikasi PHP native rata-rata, terutama di Backend v1. Namun kesehatan sistem
ditahan oleh dua runtime, regresi analitik KSA, tooling kontrak yang salah baca,
cache deserialization, serta ukuran/duplikasi kode root. Fokus terbaik bukan
menambah abstraksi besar sekaligus, melainkan memperbaiki P0, mengunci contract
dan policy dengan test, kemudian memindahkan fungsi root ke Backend v1 secara
bertahap dan terukur.

Laporan ini tidak menyatakan “tidak ada kerentanan”; laporan menyatakan kontrol
mana yang terbukti ada, masalah mana yang dapat direproduksi, dan area mana yang
masih membutuhkan verifikasi staging/production.
