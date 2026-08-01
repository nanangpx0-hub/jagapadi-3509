# Day 7 - Week 1 Summary & Phase 2 Backlog (2026-04-22)

Dokumen ini merangkum hasil minggu pertama (hardening + stabilisasi) dan arah kerja fase refactor berikutnya.

## 1) Daftar patch yang sudah dilakukan

- Hardening repository:
  - pembersihan file sensitif/runtime dari tracking Git
  - penguatan aturan `.gitignore` (dump, log, uploads, cookies, config sensitif)
- Hardening credential:
  - rotasi `SCRAPER_API_KEY`, `MOBILE_API_KEY`, `EXTERNAL_API_KEY` di environment runtime
  - dokumentasi rotasi di `docs/DAY2_SECURITY_ROTATION_2026-04-22.md`
- Stabilitas API:
  - audit route API terhadap controller/method nyata
  - fallback `501 Not Implemented` untuk endpoint yang belum siap
  - perbaikan kontrak model agar call site API inti tidak memanggil method yang hilang
  - penambahan `Wilayah` model untuk endpoint wilayah
- Kualitas testing:
  - `TESTING_GUIDE.md` dijadikan checklist regresi resmi
  - smoke test route API: `scripts/smoke_test_api_routes.php`
  - pipeline lokal pre-deploy: `scripts/run_local_pipeline.php` (`php -l` -> unit test dasar -> smoke test route)

## 2) Endpoint yang sudah stabil

- `GET/POST/PUT/DELETE /api/laporan-hama` (+ detail by id)
- `GET/POST/PUT/DELETE /api/irigasi` (+ monitoring/rules/analytics/dashboard-summary)
- `GET /api/wilayah/*` (kabupaten/kecamatan/desa/hierarchy/search/stats/by-coordinates)
- `GET /api/dashboard/*` (stats/charts/activities/alerts)
- `GET /api/dashboard/map/*`
- `GET /api/dashboard/charts/*`
- `GET/POST/PUT/DELETE /api/users/*` (profile/password/toggle/force-password-change)
- `GET/POST/PUT/DELETE /api/opt/*` (+ search/by-category/by-type/stats)
- `POST/GET /api/external/*`

## 3) Credential yang sudah dirotasi

- `SCRAPER_API_KEY` -> rotated
- `MOBILE_API_KEY` -> rotated
- `EXTERNAL_API_KEY` -> rotated

Catatan: detail status dan tindak lanjut operasional tersedia di `docs/DAY2_SECURITY_ROTATION_2026-04-22.md`.

## 4) Area yang masih ditunda

- Implementasi penuh endpoint:
  - `/api/pengairan/*` (IoT)
  - `/api/storytelling/*`
  - sementara ditutup terkontrol dengan respons `501`
- Penyempurnaan coverage test otomatis (unit/integration lebih luas)
- CI/CD pipeline terpusat (saat ini masih pipeline lokal)

## 5) Backlog Fase 2 (Refactor Bertahap)

Prioritas fase ini penting untuk maintainability, namun tetap di bawah prioritas hardening/stabilisasi yang sudah selesai.

### Track A - Pecah Controller Besar

1. Identifikasi controller >400 baris / >8 aksi utama.
2. Ekstrak logic bisnis ke service layer (mis. `UserService`, `LaporanService`, `IrigasiService`).
3. Kurangi controller menjadi orchestration (request validation + response mapping).
4. Tambahkan test per service sebelum memindahkan logic berikutnya.

### Track B - Pusatkan Logging

1. Konsolidasikan semua `error_log()` ke helper terpusat (`Logger`).
2. Standarkan format log lintas modul (request_id, user_id, endpoint, duration, level).
3. Terapkan log policy:
   - security event
   - business-critical event
   - error event
4. Tambahkan guard agar log tidak membocorkan data sensitif.

### Track C - Pindah Inline JS/CSS ke Aset Modular

1. Inventarisasi view dengan inline `<script>` dan `<style>`.
2. Pindahkan ke `public/js/*` dan `public/css/*` per fitur.
3. Buat struktur modular (shared + feature-specific bundle).
4. Rapikan inisialisasi per halaman agar tidak ada side effect global.

### Track D - Mulai Autoload/Composer/PSR-4

1. Introduce `composer.json` minimal + autoload PSR-4 bertahap.
2. Migrasi namespace untuk `app/core`, `app/controllers`, `app/models`, `app/services`.
3. Pertahankan backward compatibility saat transisi (bridge autoload sementara).
4. Setelah stabil, hapus autoload legacy secara bertahap.

## 6) Rencana Eksekusi Fase 2 (usulan)

- Sprint 1: Track A (controller split pilot) + Track B baseline logging.
- Sprint 2: Track C (inline asset migration untuk modul prioritas tinggi).
- Sprint 3: Track D tahap awal (composer + PSR-4 untuk modul baru dulu).

## 7) Definisi Selesai Fase 2 (target)

- Controller inti lebih kecil dan terpisah per concern.
- Logging seragam dan dapat diaudit lintas endpoint.
- Inline JS/CSS di modul prioritas sudah dipindah ke aset modular.
- Composer/autoload PSR-4 sudah aktif minimal untuk modul baru/refactor.
