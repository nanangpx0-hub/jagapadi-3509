# Database Canonical Runner — JAGAPADI

> Status: Accepted 2026-08-30 (ADR-011)
> Runner canonical: `backend/scripts/migrate.php` + tabel `backend.schema_migrations`
> Legacy: `migrations/` (historis) & `database/migrations/` (root) — FROZEN, jangan jalankan di prod baru

## Aturan
- **Append-only**: jangan ubah migration yang sudah tercatat di `schema_migrations`
- **Idempoten guard**: setiap `backend/database/migrations/*.sql` memakai `INFORMATION_SCHEMA` check (`DO 1` no-op) — aman rerun
- **Baseline terverifikasi**: `php scripts/verify-migrations.php` membandingkan filesystem vs DB
- **FK/type/collation**: `utf8mb4_unicode_ci`, PK `INT UNSIGNED` (master) vs `BIGINT UNSIGNED` (laporan), `TIMESTAMP/DATETIME` sesuai migration asal

## Lokasi
| Lokasi | Status | Runner | Catatan |
|--------|--------|--------|---------|
| `backend/database/migrations/001..024*.sql` | **canonical** | `php backend/scripts/migrate.php` | 24 file, termasuk `024_align_laporan_hama_media.sql` idempoten |
| `migrations/*.php` | legacy historis | jangan jalankan | 3 file, sudah superseded |
| `database/migrations/*.sql,*.php` | legacy root | jangan jalankan di prod baru | 31 file, termasuk `2026_08_24_*` usulan-opt/recycle-bin — dicatat terpisah di `schema_migrations` root DB |

## Verifikasi
```bash
cd backend
php scripts/migrate.php              # idempoten, BEGIN/COMMIT per file, batch++
php ../scripts/verify-migrations.php # bandingkan filesystem vs schema_migrations
mysql -e "SELECT migration,batch FROM schema_migrations ORDER BY migration"
```

`verify-migrations.php` output:
- `[OK] filesystem == schema_migrations`
- `[DRIFT] file di DB tidak di filesystem` → migrasi manual/hapus fisik → audit
- `[PENDING] file di filesystem belum di DB` → jalankan migrate
- Exit 1 bila drift → fail-closed CI

## Indeks & Constraint (pola query)
- `idx_lh_status`, `idx_lh_tanggal`, `idx_lh_user`, `idx_laporan_hama(status,tanggal)`, `uk_nomor_laporan`, `fk_lh_user`, `ck_lh_latitude` etc — sesuai `DATABASE.md`
- Pagination max 100, export chunked, N+1 dihilangkan via JOIN (`EXPLAIN` staging)

## Rollback & Backfill
- Rollback dokumentatif di `DATABASE.md` (restore backup `backups/db_backup_pre_*`), tidak otomatis di prod
- Backfill via migration baru `025_*.sql` dengan guard, bukan edit lama

## Seeds
- `php backend/scripts/seed.php` hanya `APP_ENV=local` — never prod
