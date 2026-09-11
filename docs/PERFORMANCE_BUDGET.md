# Performance Budget & Baseline

- **Web**: TTFB <300ms, LCP <2.5s, INP <200ms, CLS <0.1
- **API**: p95 <500ms, p99 <1s, query count <10 per request, memory <128 MB
- **APK**: size <30 MB, startup <2s, jank <5%
- **Dataset representatif**: 10k laporan_hama, 5k irigasi, 500 desa — skenario dashboard/peta/list/filter/detail/create/submit/upload/export/login/poll/offline-sync
- **Method**: `EXPLAIN ANALYZE` untuk daftar/dashboard/peta/ekspor, hilangkan N+1, pagination max 100, export chunked (cursor), `wrk -c 50 -t 4 -d 60s` staging
- **Budget enforcement**: CI `php scripts/bench.php` gagal bila p95>budget atau query >10, artifact `bench.json` upload
