# Template Prompt: Review Kode

Anda bekerja pada project JAGAPADI.

Konteks wajib:
- Baca `AGENTS.md`.
- Baca `PROJECT_SUMMARY.md`, `TECH_STACK.md`, `DATABASE_SCHEMA.md`, dan `DATA_DICTIONARY.md` jika review menyentuh data atau database.
- Branch aktif: `[nama_branch]`
- Scope review: `[file/PR/commit/range]`

Tugas:
Review perubahan berikut sebagai reviewer teknis. Fokus pada bug nyata, regresi perilaku, risiko security, risiko data, dan test gap. Jangan melakukan perubahan kode kecuali diminta eksplisit.

Perubahan yang direview:
`[paste diff atau daftar file]`

Checklist review:
- Validasi requirement dan scope PR.
- Cek SQL injection, XSS, CSRF, upload file, auth/role check, dan data exposure.
- Cek query database, relasi wilayah, soft-delete, dan rollback jika ada maintenance data.
- Cek error handling, edge case, dan backward compatibility.
- Cek apakah test/manual validation cukup untuk risiko perubahan.
- Pastikan tidak ada runtime file, backup, dump SQL lokal, cache, atau file upload ikut commit.

Format output:
1. Findings dulu, urut dari severity tertinggi.
2. Setiap finding pakai format: `Severity - file:line - masalah - dampak - rekomendasi`.
3. Open questions atau asumsi.
4. Ringkasan singkat perubahan yang direview.
5. Test gap atau validasi manual yang masih perlu dilakukan.

Jika tidak menemukan masalah, katakan jelas "Tidak ada finding utama", lalu sebutkan sisa risiko atau test gap.
