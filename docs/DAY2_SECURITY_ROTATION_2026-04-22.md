# Day 2 - Credential Rotation Log (2026-04-22)

Dokumen ini mencatat credential yang telah dirotasi setelah pembersihan file sensitif dari repository.

## Credential yang sudah dirotasi

| Credential | Lokasi | Status | Catatan |
| --- | --- | --- | --- |
| `SCRAPER_API_KEY` | `.env` (runtime lokal, tidak ter-track git) | Rotated | Nilai lama dinonaktifkan, diganti key 64-hex baru pada 2026-04-22. |
| `MOBILE_API_KEY` | `.env` (runtime lokal, tidak ter-track git) | Rotated | Ditambahkan key baru 64-hex untuk memutus kemungkinan reuse credential lama. |
| `EXTERNAL_API_KEY` | `.env` (runtime lokal, tidak ter-track git) | Rotated | Ditambahkan key baru 64-hex untuk integrasi eksternal. |

## Wajib tindak lanjut operasional (di luar repo)

1. Ganti password database produksi dan update secret di environment server.
2. Revoke token/API key lama di provider pihak ketiga (jika pernah dipakai di dump/backup).
3. Restart service yang melakukan cache env agar semua key lama tidak lagi aktif.
4. Simpan credential baru hanya di secret manager/environment server, bukan di file yang ter-track Git.

## Verifikasi pembersihan repo

- File sensitif yang dihapus dari workspace: `cookies.txt`, `error_log`, `scripts/jawa_timur_kabupaten.sql`, `scripts/sync_kabupaten_master.sql`.
- Rule `.gitignore` diperketat untuk dump, log, runtime artifacts, dan config sensitif.
