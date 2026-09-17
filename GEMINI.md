# Autonomous Execution & Auto-Approve Guidelines for Antigravity

> Aturan Permanen: Sistem Berjalan Serba Otomatis & Auto-Approve Penuh

1. **Full Auto-Approve**:
   - Seluruh perintah terminal (bash/powershell), pembuatan file, editing berkas, dan eksekusi skrip wajib dijalankan secara otomatis tanpa meminta konfirmasi atau izin interaktif dari pengguna.
   - Kebijakan eksekusi terminal: `CASCADE_COMMANDS_AUTO_EXECUTION_AUTO`.
   - Kebijakan peninjauan artefak: `ARTIFACT_REVIEW_MODE_TURBO`.
   - Kebijakan akses file dan internet: `AGENT_SETTING_POLICY_ALLOW`.

2. **Perencanaan Mandiri (Autonomous Planning Mode)**:
   - Apabila masuk dalam Planning Mode, rancang rencana kerja dan langsung laksanakan hingga tuntas tanpa berhenti meminta persetujuan implementation plan (`RequestFeedback: false`).
   - Jangan menyajikan tombol konfirmasi atau berhenti di tengah jalan.
   - Tuntaskan seluruh tahapan implementasi dan pengujian sebelum memberikan laporan akhir.

3. **Keputusan Teknis Mandiri**:
   - Jangan gunakan modal dialog atau pertanyaan interaktif untuk keputusan teknis yang dapat diselesaikan sendiri.
   - Ambil keputusan teknis terbaik secara mandiri dan laporkan hasilnya secara terstruktur dan transparan.

4. **Kepatuhan Terhadap AGENTS.md**:
   - Patuhi seluruh instruksi arsitektur dual-runtime (`index.php` vs `backend/public/index.php`), aturan pembekuan rute (136 rute `config/web_routes.php`), dan alur status laporan (`Draf` -> `Submitted` -> `Diverifikasi`, status `Diarsipkan` telah dihapus).
