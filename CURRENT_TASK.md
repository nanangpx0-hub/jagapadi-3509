# Current Task

## Scope Aktif

Membuat dokumentasi dan prompt template agar workflow pengembangan JAGAPADI bisa dipakai lintas AI/model, terutama model dengan context window terbatas.

Branch kerja:

- `docs/model-agnostic-ai-context`

## Output yang Diharapkan

- Template prompt:
  - review kode
  - fitur baru
  - debug error
  - dokumentasi
- File onboarding ringkas:
  - `PROJECT_SUMMARY.md`
  - `TECH_STACK.md`
  - `CURRENT_TASK.md`
  - `DATABASE_SCHEMA.md`
  - `DATA_DICTIONARY.md`
- Changelog:
  - `CHANGELOG.md`
- Panduan workflow lintas AI:
  - `docs/AI_WORKFLOW.md`
- Link dari `README.md` dan `AGENTS.md` ke dokumen baru.

## Status

- Branch docs aktif berbasis `main`.
- Tidak ada query database yang perlu dijalankan untuk task ini.
- Perubahan bersifat dokumentasi.

## Catatan Handover

- Sebelum commit, jalankan `git status`.
- Pastikan file runtime/lokal seperti `composer.lock`, `reports/`, `data/mfd/`, `.env`, `vendor/`, `storage/`, dan dump SQL tidak ikut commit kecuali task eksplisit memintanya.
- Jika task aktif berubah, update file ini di awal pekerjaan berikutnya.
