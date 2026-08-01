# Tech Stack

## Runtime

- PHP: `>=8.2` sesuai `composer.json`.
- Database: MySQL/MariaDB via PDO MySQL.
- Local default database: `bpsjembe_jagapadi`.
- Local environment umum: Laragon di Windows, path repo `c:\laragon\www\jagapadi`.
- Timezone aplikasi: `Asia/Jakarta`.

## Backend

- Framework: custom PHP MVC ringan, bukan Laravel.
- Entry point: `index.php`.
- Config: `config/config.php`, `config/database.php`, `config/env.php`.
- Dependency injection sederhana: `app/core/Container.php`.
- Routing API: `app/core/Router.php`.
- Base controller/model: `app/core/Controller.php`, `app/core/Model.php`.
- Database access: PDO prepared statements.

## Frontend

- View server-rendered PHP di `app/views`.
- Vite: `^5.4.0`.
- Chart.js: `^4.4.0`.
- Asset build scripts:
  - `npm run dev`
  - `npm run build`
  - `npm run preview`

## Testing dan CI

- PHPUnit: `^11.0`.
- Konfigurasi test: `phpunit.xml`.
- CI: `.github/workflows/ci.yml`.
- CI melakukan PHP 8.2 setup, Composer install, PHP lint, dan PHPUnit jika tersedia.

## File dan Folder Penting

- `AGENTS.md`: aturan kerja agent dan branch.
- `prompts/`: prompt template model-agnostic.
- `PROJECT_SUMMARY.md`: gambaran singkat aplikasi.
- `CURRENT_TASK.md`: task aktif dan status handover.
- `DATABASE_SCHEMA.md`: ringkasan schema.
- `DATA_DICTIONARY.md`: istilah data lokal, terutama wilayah dan MFD.
- `database/maintenance`: script SQL maintenance.
- `data/mfd`: data pembanding MFD lokal; perlakukan sebagai data lokal/generated kecuali task eksplisit meminta file itu masuk repo.

## Catatan Security Dasar

- Gunakan prepared statement/PDO binding untuk input user.
- Validasi CSRF untuk state-changing request.
- Cek role di controller/API sebelum membuka data atau aksi sensitif.
- Jangan commit `.env`, secret, token, dump SQL, upload, atau file cache.
