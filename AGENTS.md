# AGENTS.md

Panduan ini menjadi aturan kerja agent untuk repository JAGAPADI. Tujuannya sederhana: perubahan tetap rapi, ownership jelas, branch tidak semrawut, dan tidak ada modifikasi berisiko yang lolos tanpa review.

## Prinsip Utama

- Jangan mengubah branch `main` secara langsung.
- Satu fitur dikerjakan di satu branch terpisah.
- Satu branch hanya boleh memiliki satu eksekutor utama.
- Agent lain hanya boleh memberi review, analisis, atau second opinion, kecuali diminta eksplisit untuk ikut mengubah kode.
- Semua perubahan harus melalui Pull Request (PR) dan CI.
- Jangan commit file runtime, file lokal, file backup, atau artefak yang tidak relevan dengan source code.
- Jangan melakukan perubahan destruktif pada data atau database tanpa backup dan review.

## Aturan Branch

- Selalu buat branch baru sebelum mulai kerja.
- Nama branch harus mewakili satu pekerjaan yang jelas.
- Satu branch hanya untuk satu scope kerja utama.
- Jangan menumpuk banyak fitur berbeda dalam satu branch.
- Jangan pakai `main` sebagai workspace eksperimen.

Contoh nama branch:

- `feature/laporan-hama-archive`
- `fix/dashboard-status-filter`
- `docs/laporan-hama-guide`
- `refactor/dashboard-aggregator`

## Ownership Branch

- Setiap branch punya satu eksekutor utama.
- Eksekutor utama bertanggung jawab atas implementasi, konsistensi perubahan, dan kesiapan PR.
- Agent lain tidak boleh push perubahan langsung ke branch yang sama tanpa instruksi eksplisit.
- Jika butuh masukan dari agent lain, perlakukan sebagai review, audit, atau usulan patch.

## Role Agent

### Codex
Eksekutor implementasi utama. Cocok untuk coding inti, refactor, perbaikan bug, dan perubahan file produksi.

### Kiro
Reviewer requirement dan alur bisnis. Fokus pada validasi apakah implementasi sesuai kebutuhan dan tidak melenceng dari flow aplikasi.

### Perplexity
Riset dan referensi. Dipakai untuk mencari informasi, cross-check, atau memperkaya dokumentasi dan keputusan teknis.

### Cursor / Trae / OpenCode
Patch kecil atau eksperimen terbatas. Jangan dijadikan pemilik utama branch kecuali memang ditetapkan begitu dari awal.

### Blackbox / Antigravity
Review tambahan atau second opinion. Berguna untuk menguji asumsi, mengecek blind spot, atau membandingkan solusi.

### ChatGPT
Orkestrasi, penyusunan arah kerja, perapihan dokumentasi, dan sinkronisasi hasil dari agent lain.

## Context dan Prompt Portable

Untuk onboarding agent baru atau AI dengan context window terbatas, gunakan file ringkas berikut:

- `PROJECT_SUMMARY.md`
- `TECH_STACK.md`
- `CURRENT_TASK.md`
- `DATABASE_SCHEMA.md`
- `DATA_DICTIONARY.md`
- `CHANGELOG.md`

Prompt model-agnostic disimpan di folder `prompts/`:

- `prompts/code-review.md`
- `prompts/new-feature.md`
- `prompts/debug-error.md`
- `prompts/documentation.md`

Panduan workflow lintas AI ada di `docs/AI_WORKFLOW.md`. Jika task aktif berubah, update `CURRENT_TASK.md` agar handover tetap akurat.
Jika fitur, bugfix, maintenance data, atau dokumentasi penting selesai, update `CHANGELOG.md`.

## Aturan Commit

Sebelum commit, wajib lakukan pengecekan berikut:

- Jalankan `git status`.
- Pastikan branch aktif **bukan** `main`.
- Pastikan file yang ikut commit memang relevan.
- Pastikan file upload, runtime, backup, dan file lokal tidak ikut terbawa.
- Lakukan pengecekan manual sesuai scope perubahan.
- Buat ringkasan perubahan yang singkat dan jelas.

## File yang Tidak Boleh Di-commit

Jangan commit file berikut kecuali memang ada alasan yang sudah disetujui dalam task:

- `composer.lock` jika project tidak memakai Composer sebagai dependency source utama.
- File upload runtime seperti `storage/laporan` atau direktori sejenis.
- Backup database, dump SQL lokal, atau file hasil maintenance sementara.
- File testing lokal, catatan pribadi, screenshot sementara, file cache, dan artefak eksperimen.
- File hasil generate yang tidak dibutuhkan repository.

## Aturan Data dan Database

- Jangan hapus data tanpa backup.
- Jangan jalankan script destructive tanpa review.
- Jangan ubah schema database secara sembarangan.
- Semua perubahan database harus aman, jelas tujuannya, dan sebisa mungkin reversible.
- Jika perubahan menyentuh data produksi, wajib ada approval eksplisit.

## Pull Request

Setiap perubahan harus diajukan melalui PR.

PR minimal harus berisi:

- Ringkasan perubahan.
- Scope file yang diubah.
- Risiko atau catatan dampak.
- Checklist pengujian manual.
- Catatan migration atau data impact jika ada.

## CI dan Review

- Jangan merge bila CI gagal.
- Jangan merge bila masih ada perubahan yang belum dipahami.
- Review bukan formalitas; reviewer harus cek scope, risiko, dan konsistensi terhadap requirement.
- Jika ada konflik antara implementasi dan requirement, requirement yang sudah disepakati jadi acuan.

## Aturan Praktis Kolaborasi Agent

- Kalau satu agent sedang pegang branch, agent lain jangan ikut nyopet perubahan diam-diam.
- Review boleh keras, branch jangan liar.
- Eksperimen pisahkan dari implementasi utama.
- Jika butuh pendekatan alternatif, buat branch atau patch terpisah.
- Semua keputusan final tetap harus tercermin jelas di PR.

## Checklist Singkat Sebelum Commit

- [ ] Sudah cek `git status`
- [ ] Branch aktif bukan `main`
- [ ] Scope branch hanya untuk satu fitur/perbaikan
- [ ] Tidak ada file runtime/upload/backup yang ikut commit
- [ ] Sudah lakukan pengecekan manual
- [ ] Sudah buat ringkasan perubahan
- [ ] Siap diajukan lewat PR

## Checklist Singkat Sebelum Merge

- [ ] PR sudah dibuat
- [ ] CI lolos
- [ ] Scope perubahan jelas
- [ ] Tidak ada file aneh atau artefak lokal
- [ ] Requirement sudah sesuai
- [ ] Risiko perubahan sudah dipahami
- [ ] Reviewer sudah menyetujui

