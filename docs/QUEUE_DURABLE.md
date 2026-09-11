# Queue Durable — Scraper, FCM, Export

> Implementasi: `backend/app/Core/QueueInterface.php` + `FileQueue.php` (dev, flock) + Redis (prod, atomic LPUSH/BRPOP)

- **Outbox pattern**: mutasi DB dalam transaksi `INSERT INTO outbox (aggregate, payload, idempotency_key)` → worker `SELECT ... FOR UPDATE SKIP LOCKED` → `push()` → `ACK` hanya setelah sukses. Jika worker crash, outbox replay.
- **Retry**: exponential backoff `2^attempt * 1s` + jitter 0-500ms, max 5, timeout 30s per job, circuit breaker buka bila 5 gagal beruntun dalam 60s (skip 120s).
- **Idempotency**: `Idempotency-Key` header (API) & `idempotency_key` kolom outbox — duplikat `409 Conflict` bila payload beda, `200 replay` bila sama.
- **DLQ**: `queue_dlq` file/Redis list, `last-good` cache 5 menit + `stale` marker bila sumber eksternal gagal >3x.
- **FCM**: `FileQueue('fcm')` best-effort, `notifications` DB source of truth — push gagal hanya `Logger::warning`, tidak rollback transaksi laporan.
- **Scraper**: `FileQueue('scraper')` + cron `*/15 * * * * php backend/scripts/worker.php scraper` — `last_success` di `cache/scraper_last_success.json` + dashboard ops `GET /admin/health`.

## Queue Worker Backend v1 (`backend/scripts/queue-worker.php`)

Runner CLI canonical (ADR-011 Fase 3) — memproses antrean di latar belakang
agar thread request HTTP tidak membeku:

```powershell
# Enqueue tanpa blokir (controller hanya push + 202 Accepted)
php backend/scripts/queue-worker.php --enqueue-nasa-wind --year=2026 --month=9
php backend/scripts/queue-worker.php --enqueue-bps --year=2025 --force-simulation

# Proses terjadwal / daemon (cron tiap 15 menit disarankan --once)
php backend/scripts/queue-worker.php --queue=scraper --once --timeout=30 --max-jobs=50
```

- Handler: `backend/app/Services/ScraperJobService.php`
  (`nasa_wind`, `bps_sync`).
- Timeout per-job (`--timeout`, default 30 dtk), retry max 5 via
  `FileQueue::retry()` (exponential backoff + jitter), DLQ otomatis
  `storage/queue/<name>_dlq/`, error logging via `Logger` tanpa secret/PII.
- Fallback simulasi deterministik bila sumber eksternal kosong/gagal;
  status tersimpan di `storage/cache/scraper_<type>_last_success.json`
  (`fallback_used`, `stale`, `records_count`) untuk dibaca `/admin/health`.
- Input tidak valid (periode/tipe) bersifat fail-closed: dilempar ulang
  agar masuk retry/DLQ, bukan fallback sunyi.
