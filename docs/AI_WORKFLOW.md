# Model-Agnostic AI Workflow

Dokumen ini membuat workflow JAGAPADI mudah dipindah antar AI. Targetnya bukan bergantung pada satu model, tetapi membuat konteks, prompt, dan handover cukup jelas untuk dipakai oleh AI apa pun.

## Paket Konteks Minimal

Untuk AI baru, berikan file ini secara bertahap:

1. `AGENTS.md`
2. `CURRENT_TASK.md`
3. `PROJECT_SUMMARY.md`
4. `TECH_STACK.md`
5. `DATABASE_SCHEMA.md` jika menyentuh database
6. `DATA_DICTIONARY.md` jika menyentuh wilayah, MFD, laporan, role, atau data lokal
7. Prompt template dari `prompts/`

Jika context window kecil, mulai dari `AGENTS.md`, `CURRENT_TASK.md`, dan satu prompt template.

## Prompt Terstandar

Template prompt disimpan di `prompts/`:

- `code-review.md`: review PR/diff dengan temuan berbasis file dan severity.
- `new-feature.md`: implementasi fitur berbasis spec.
- `debug-error.md`: diagnosis error dan fix minimal.
- `documentation.md`: pembuatan atau update dokumentasi.

Setiap prompt sengaja tidak menyebut nama vendor AI agar bisa dipakai di Codex, ChatGPT, Gemini, Claude, Cursor, Kiro, Perplexity, atau tool lain.

## Spec-Driven Development

Sebelum coding, tulis spec singkat. Contoh:

```text
# Fitur: Laporan Hama
- Input: jenis hama, lokasi, foto
- Output: data tersimpan dan muncul di dashboard admin
- Validasi: semua field wajib, koordinat harus di area Jember
- Role: operator membuat laporan, admin melihat semua laporan
- Data impact: insert ke laporan_hama, tidak ada migration
- Out of scope: export dan dashboard peta
```

Spec kecil lebih mudah dipindahkan antar AI daripada instruksi panjang yang tersebar di chat.

## Git Discipline

Aturan ringkas:

- Jangan kerja langsung di `main`.
- Satu branch untuk satu scope.
- Jangan campur fitur, maintenance data, dan dokumentasi besar dalam satu branch.
- Jangan commit runtime file, upload, cache, dump SQL, log, atau secret.
- PR harus menjadi handover document: ringkasan, scope, risiko, test manual, data impact, rollback.

## Critical Review Skill

Setiap output AI harus dicek manusia atau reviewer lain untuk:

- SQL injection dan query tanpa binding.
- XSS dari output HTML yang tidak di-escape.
- CSRF pada form state-changing.
- Auth/role check.
- Upload file dan path traversal.
- Soft-delete dan relasi foreign key.
- Data wilayah: kode BPS, parent kecamatan/desa, dan namespace BPS vs Dagri.
- Test gap dan edge case.

## Living Documentation

Update dokumentasi setiap selesai scope yang mengubah perilaku:

- `CURRENT_TASK.md`: task aktif/handover.
- `PROJECT_SUMMARY.md`: modul besar atau arsitektur berubah.
- `TECH_STACK.md`: dependency, versi, CI, runtime berubah.
- `DATABASE_SCHEMA.md`: migration, tabel, kolom, relasi penting berubah.
- `DATA_DICTIONARY.md`: istilah domain/data lokal berubah.
- `CHANGELOG.md`: ringkasan perubahan penting per scope.
- Dokumen fitur di `docs/` jika workflow user berubah.

Komentar kode dan PHPDoc dipakai untuk fungsi penting atau alur non-obvious, bukan untuk menjelaskan baris yang sudah jelas.

## Rekomendasi Peran AI

Gunakan AI sesuai kekuatan peran, bukan sebagai pemilik branch paralel tanpa koordinasi:

- Coding utama: Codex, Copilot, Cursor, Codeium, atau tool coding lain.
- Requirement dan flow bisnis: Kiro atau reviewer manual.
- Riset dan referensi: Perplexity atau search tool dengan sumber primer.
- Dokumentasi dan handover: ChatGPT, Gemini, atau model teks lain.
- Second opinion/security pass: Claude, Blackbox, Antigravity, atau reviewer lain.

Catatan: ketersediaan free tier dan limit tool berubah dari waktu ke waktu. Verifikasi kuota/tool sebelum menetapkan workflow tim.

## Skill Teknis Pendukung

Skill yang membuat AI lebih efektif:

- Baca diff dan history git.
- Tulis commit message deskriptif.
- Buat spec singkat sebelum coding.
- Jalankan test/lint/validasi manual.
- Paham SQL dasar dan regex untuk audit data.
- Paham dasar security PHP: SQL injection, XSS, CSRF, session, upload.
- Paham CI/CD dasar GitHub Actions.
- Pakai script repeatable untuk task berulang.

Intinya: buat konteks portable, ringkas, dan selalu update.
