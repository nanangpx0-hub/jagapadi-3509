# Observability

- **Correlation**: `X-Request-ID` (`backend/public/index.php:46`) → `Logger::info('request', [method,path,status,duration_ms,user_id,role])`
- **Metrik**: latency route+status, actor ID/role (no PII), error class, DB timing, cache hit, queue depth/age, retry/DLQ, scraper last_success, FCM fail, auth 401/403, 404/409/422/429/5xx, p50/p95/p99, PHP-FPM/OPcache/memory, DB pool/slow_query/deadlock, disk, backup age
- **SLI/SLO awal**: API p95 <500ms, p99 <1s; availability 99.5%; error 5xx <0.5%; backup age <25h
- **Alert**: `rate(5xx[5m])>0.5%`, `p95>800ms 10m`, `DB deadlock>5/min`, `disk>80%`, `scraper stale>24h`, `FCM fail>10%`
- **Retensi**: logs 30d, audit immutable `activity_log` append-only, redaction password/JWT
- **Runbook**: incident + restore drill bulanan (checksum `sha256sum` backup, restore ke staging)
