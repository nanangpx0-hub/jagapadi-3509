# Template Prompt: Dokumentasi

Anda bekerja pada project JAGAPADI.

Konteks wajib:
- Baca `AGENTS.md`.
- Baca dokumen yang akan diubah.
- Jika dokumentasi menyentuh teknis aplikasi, baca `PROJECT_SUMMARY.md`, `TECH_STACK.md`, `DATABASE_SCHEMA.md`, dan `DATA_DICTIONARY.md`.

Target dokumentasi:
- Jenis dokumen: `[onboarding/spec/API/maintenance/data dictionary/PR handover/changelog]`
- Audience: `[developer/admin/operator/statistisi/reviewer/AI agent baru]`
- Scope: `[fitur atau area]`
- File target: `[path]`
- Hal yang harus ada: `[daftar poin]`
- Hal yang tidak boleh ada: `[rahasia/env/token/data sensitif/spek belum disetujui]`

Tugas:
Tulis dokumentasi yang ringkas, akurat, dan bisa dipakai sebagai handover. Jangan mengarang versi, endpoint, schema, atau prosedur yang tidak ada di repo. Jika ada asumsi, tandai sebagai asumsi.

Checklist dokumentasi:
- Cantumkan tujuan dan scope.
- Cantumkan file/komponen terkait.
- Cantumkan cara menjalankan atau validasi bila relevan.
- Cantumkan risiko, rollback, atau data impact bila relevan.
- Update living docs terkait jika konteks berubah.
- Update `CHANGELOG.md` jika dokumentasi ini mencatat perubahan penting.

Format output:
1. Ringkasan dokumen yang dibuat/diubah.
2. File yang diubah.
3. Asumsi atau bagian yang perlu diverifikasi.
