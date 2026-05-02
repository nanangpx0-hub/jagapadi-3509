# Template Prompt: Buat Fitur Baru

Anda bekerja pada project JAGAPADI.

Konteks wajib:
- Baca `AGENTS.md`.
- Baca `PROJECT_SUMMARY.md`, `TECH_STACK.md`, `CURRENT_TASK.md`, `DATABASE_SCHEMA.md`, dan `DATA_DICTIONARY.md`.
- Branch aktif harus bukan `main`.
- Jangan mengubah file runtime, upload, dump SQL, backup lokal, atau artefak eksperimen.

Spec fitur:
- Nama fitur: `[nama_fitur]`
- Masalah yang diselesaikan: `[masalah]`
- User/role terkait: `[admin/operator/statistisi/petugas/publik/API eksternal]`
- Input: `[field/request/data]`
- Output: `[halaman/API/data/notifikasi]`
- Validasi: `[aturan validasi]`
- Data/database impact: `[tidak ada / ada migration / ada maintenance script]`
- Risiko: `[risiko utama]`
- Out of scope: `[hal yang tidak boleh disentuh]`

Tugas:
Implementasikan fitur sesuai pola repo yang sudah ada. Pilih solusi paling kecil yang memenuhi spec. Jangan refactor area lain jika tidak diperlukan.

Aturan implementasi:
- Ikuti pola MVC lokal: `app/controllers`, `app/models`, `app/views`, `app/services`, `app/core/Router.php` untuk API.
- Gunakan prepared statement/PDO binding untuk input user.
- Untuk form state-changing, pastikan method dan CSRF sesuai pola controller.
- Untuk akses data, hormati role dan ownership data yang sudah ada.
- Jika menyentuh database, buat migration atau maintenance script yang aman dan jelaskan rollback.
- Update dokumentasi terkait jika perilaku user, API, data, atau workflow berubah.
- Update `CHANGELOG.md` untuk fitur, bugfix, security fix, atau maintenance data yang penting.

Format output:
1. Ringkasan perubahan.
2. File yang diubah.
3. Cara validasi manual.
4. Dampak database dan rollback jika ada.
5. Risiko tersisa.
