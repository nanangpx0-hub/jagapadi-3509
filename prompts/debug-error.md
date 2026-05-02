# Template Prompt: Debug Error

Anda bekerja pada project JAGAPADI.

Konteks wajib:
- Baca `AGENTS.md`.
- Baca `PROJECT_SUMMARY.md`, `TECH_STACK.md`, `DATABASE_SCHEMA.md`, dan `DATA_DICTIONARY.md` jika error menyentuh data.
- Branch aktif: `[nama_branch]`

Error:
- Halaman/API/command: `[route atau command]`
- Role user: `[admin/operator/statistisi/petugas/publik/API eksternal]`
- Input yang dipakai: `[input]`
- Expected result: `[hasil seharusnya]`
- Actual result: `[hasil sekarang]`
- Log/error message: `[paste error lengkap]`
- Sejak kapan muncul: `[commit/tanggal/perubahan terkait jika ada]`

Tugas:
Cari root cause dan buat fix minimal. Jangan menebak jika bisa dibuktikan dari kode/log. Jangan menjalankan query destructive atau mengubah data produksi.

Checklist debug:
- Reproduce dari route/command yang disebutkan.
- Lacak dari controller ke model/service/view.
- Cek nama kolom, enum, status, role, session, CSRF, file upload, dan path.
- Cek query SQL dengan parameter binding.
- Cek apakah error disebabkan data lokal, schema drift, atau file ignored.
- Jika fix menyentuh database, buat script aman dengan backup/rollback.

Format output:
1. Root cause.
2. Perubahan yang dibuat.
3. File yang diubah.
4. Cara validasi.
5. Risiko atau kasus yang belum tertutup.
