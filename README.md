## Kontribusi & Agent

Repo JAGAPADI pakai pola branch-per-fitur dan multi-agent.

**Aturan singkat:**

- Jangan pernah commit langsung ke `main`.
- Satu fitur / perbaikan = satu branch terpisah.
- Satu branch hanya punya satu eksekutor utama.
- Semua perubahan harus lewat Pull Request (PR) + CI.
- Jangan commit file runtime (upload, backup SQL, log, cache, dll).
- Perubahan database harus lewat migration/script yang aman dan bisa di-rollback.

**Alur kontribusi:**

1. Buat branch baru dari `main` (misal: `feature/laporan-hama-archive`).
2. Kerjakan perubahan di branch tersebut.
3. Jalankan pengecekan manual sesuai area yang disentuh.
4. Commit dengan pesan yang jelas.
5. Push branch dan buka PR, isi template `PULL_REQUEST_TEMPLATE.md`.
6. Tunggu review dan pastikan CI hijau sebelum merge.

**Peran agent (jika memakai AI agent):**

- **Codex** – implementasi utama (coding, refactor, bugfix).
- **Kiro** – review requirement & alur bisnis.
- **Perplexity** – riset & referensi.
- **Cursor / Trae / OpenCode** – patch kecil & eksperimen.
- **Blackbox / Antigravity** – review / second opinion.
- **ChatGPT** – orkestrasi & dokumentasi.

Detail aturan kolaborasi agent: lihat [`AGENTS.md`](./AGENTS.md)
Panduan kontribusi lengkap: lihat [`CONTRIBUTING.md`](./CONTRIBUTING.md)

**Konteks cepat untuk AI / handover:**

- [`PROJECT_SUMMARY.md`](./PROJECT_SUMMARY.md)
- [`TECH_STACK.md`](./TECH_STACK.md)
- [`CURRENT_TASK.md`](./CURRENT_TASK.md)
- [`DATABASE_SCHEMA.md`](./DATABASE_SCHEMA.md)
- [`DATA_DICTIONARY.md`](./DATA_DICTIONARY.md)
- [`CHANGELOG.md`](./CHANGELOG.md)
- [`prompts/`](./prompts/)
- [`docs/AI_WORKFLOW.md`](./docs/AI_WORKFLOW.md)
