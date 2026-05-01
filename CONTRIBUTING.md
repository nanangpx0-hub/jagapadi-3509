# Kontribusi ke JAGAPADI

Terima kasih mau ikut ngoprek JAGAPADI.  
File ini menjelaskan cara berkontribusi tanpa bikin branch kacau atau data berantakan.

Kalau kamu bingung mulai dari mana, baca ini dulu pelan-pelan.

## 1. Aturan Dasar

- Jangan pernah commit langsung ke `main`.
- Satu fitur / perbaikan = satu branch.
- Satu branch punya satu eksekutor utama.
- Semua perubahan lewat Pull Request (PR) + CI.
- Jangan commit file runtime, upload, backup SQL, atau file lokal.
- Jangan sentuh data/database destruktif tanpa backup dan review.

Detail aturan agent dan kolaborasi ada di `AGENTS.md`.

## 2. Cara Mulai Bekerja

1. Fork atau clone repo ini.
2. Pastikan kamu ada di branch `main` lokal, lalu:
   ```bash
   git checkout -b feature/nama-fitur-kamu
   ```
3. Kerjakan perubahan di branch tersebut.
4. Jalankan pengecekan manual sesuai area yang disentuh.
5. Commit dengan pesan yang jelas.
6. Push branch dan buat Pull Request ke repo utama.

Contoh nama branch:

- `feature/laporan-hama-archive`
- `fix/dashboard-status-filter`
- `docs/laporan-hama`
- `refactor/dashboard-aggregator`

## 3. Role Agent (jika memakai AI Agent)

Ringkasannya:

- **Codex**: implementasi utama (coding, refactor, bugfix).
- **Kiro**: review requirement dan alur bisnis.
- **Perplexity**: riset dan referensi.
- **Cursor / Trae / OpenCode**: patch kecil / eksperimen.
- **Blackbox / Antigravity**: review / second opinion.
- **ChatGPT**: orkestrasi dan dokumentasi.

Satu branch = satu eksekutor utama.  
Agent lain hanya review, mengusulkan perubahan, atau membuat branch/patch terpisah.

## 4. Gaya Commit

Usahakan commit singkat, tapi jelas:

- Hindari: `fix`, `update`, `test`
- Lebih baik: `feat: tambah status Diarsipkan di laporan hama`
- Lebih baik: `fix: perbaiki filter status peta dashboard`

Kalau perubahan besar, pecah jadi beberapa commit logis.

## 5. File yang Jangan Di-commit

Secara umum, jangan sertakan:

- File upload runtime (misal `storage/laporan`, `public/uploads/...`).
- Backup database, dump SQL, atau file hasil script maintenance.
- File cache, log, atau artefak environment lokal.
- `composer.lock` jika project tidak mengandalkan Composer sebagai dependency manager utama.
- File eksperimen pribadi dan catatan lokal.

Kalau ragu: cek `git diff` dan `git status` sebelum commit.

## 6. Perubahan Database

Kalau kamu perlu mengubah schema atau data:

- Buat migration atau script yang **aman** dan **reversible**.
- Jangan langsung modifikasi schema di `jagapadi.sql` tanpa migration.
- Jika menyentuh data produksi, wajib ada diskusi dan approval eksplisit.

Di PR, jelaskan:

- Nama migration / script.
- Apa yang diubah.
- Dampaknya ke data.
- Cara rollback.

## 7. Membuat Pull Request

Setelah push branch:

1. Buka PR ke repo utama (target **bukan** `main` lokal kamu, tapi branch tujuan di repo asli).
2. Template PR akan muncul otomatis (`PULL_REQUEST_TEMPLATE.md`).
3. Isi ringkasan perubahan, area terdampak, dan checklist pengujian.
4. Tunggu review. Jangan merge sendiri tanpa persetujuan (kecuali memang aturan repo mengizinkan).

Sebelum buka PR, pastikan:

- [ ] Branch bukan `main`
- [ ] Scope jelas dan tidak nyampur banyak hal
- [ ] Tidak ada file runtime/upload/backup di diff
- [ ] Pengujian manual sudah dilakukan
- [ ] Kamu siap menjawab pertanyaan reviewer

## 8. Review dan CI

- Reviewer boleh (dan dianjurkan) kritis ke alur bisnis dan implementasi.
- CI harus hijau sebelum merge.
- Kalau ada komentar review, tanggapi dan revisi secukupnya.
- Kalau perubahan makin besar, pertimbangkan split PR baru daripada satu PR gemuk.

## 9. Kontak & Diskusi

Jika kamu:

- Bingung mau mulai dari mana
- Tidak yakin dengan desain solusi
- Menemukan area yang inconsistent dengan dokumen (misalnya alur approval lama di laporan hama)

Buat issue atau tuliskan di bagian komentar PR: jelaskan konteks, dugaan masalah, dan saran perbaikan kalau ada.

Lebih baik banyak tanya di awal daripada memadamkan kebakaran belakangan.