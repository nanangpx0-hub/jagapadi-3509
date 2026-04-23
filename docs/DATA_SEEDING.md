# Data Seeding for Jagapadi

Overview
- Automated data seeding to populate dummy data for development, QA, and performance testing.
- Data is generated to resemble realistic user profiles, reports, and irrigation data.
- Designed to be re-run repeatedly without affecting production data (seed data uses a dedicated database by default).

Prerequisites
- A seed database named jagapadi_seed (or provide --db override).
- Tables exist for: users, laporan_hama, master_opt, master_desa, data_irigasi, activity_log (as used by app).
- PHP CLI environment with composer (for PHP lint/tests via CI).

Configuration and Scenarios
- --db: Target database for seeding (default: jagapadi_seed).
- --count: Number of total seed records to generate (default: 500).
- --scenario: Seed mix. Values: all, users, reports, irigasi, mixed (default: all).
- --clean: Purge previously seeded data from the seed database.
- --log: Custom log file path (default: storage/logs/dummy_seed.log).

How data is generated
- Users: seed_user_XXX usernames with hashed passwords, emails, and roles (admin, petugas, operator, statistisi).
- Laporan Hama: generated from seed users with random master_opt_id and desa_id (if present in DB).
- Data Irigasi: generated with seed_users and random desa_id; includes status_kondisi, debit_air, luas_lahan, etc.

Batching and performance
- Batch inserts are used (multi-row INSERT) to improve throughput and reduce round-trips.
- Validation checks ensure referential integrity where possible (skip if master_opt or desa tables are missing).

Cleanup
- The script supports --clean to delete seeded data by username pattern (seed_user_%).
- Pruning cascades cleanup in dependent tables first, then users.

Operational Guide
- Run seed:
  php scripts/dummy_seed.php --count=1000 --db=jagapadi_seed --scenario=all
- Dry-run (no data persisted): not implemented in this draft; set --log path to monitor progress.
- Cleanup seed data:
  php scripts/dummy_seed.php --clean --db=jagapadi_seed
- Inspect logs at storage/logs/dummy_seed.log or the configured log path.

Notes for QA
- Ensure seed data adheres to validation rules and does not collide with production datasets.
- Validate performance improvements via exported metrics (SQL plan, timing, etc.).

Appendix: Data Model Assumptions
- Users: id, username, password, email, nama_lengkap, role, aktif
- Laporan Hama: user_id, master_opt_id, lokasi, tanggal, jenis_hama, tingkat_keparahan, luas_serangan, jenis_tanggulangan, hasil_tanggulangan, status, catatan
- Data Irigasi: user_id, desa_id, tanggal, status_kondisi, debit_air, luas_lahan, catatan
