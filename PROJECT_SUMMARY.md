# Project Summary

JAGAPADI adalah aplikasi web PHP untuk monitoring dan manajemen data pertanian, hama/OPT, irigasi, cuaca, harga komoditas, wilayah administrasi, dan analisis dashboard. Nama lengkap aplikasi: Jember Agrikultur Gapai Prestasi Digital.

## Modul Utama

- Auth dan user management: login, role, profil, pergantian password, administrasi user.
- Laporan hama/OPT: input laporan, lokasi wilayah, foto, status laporan, dashboard, peta, analytics, export.
- Master wilayah: kabupaten/kota, kecamatan, desa berbasis kode BPS, termasuk maintenance data MFD Jawa Timur.
- Master OPT: data organisme pengganggu tanaman untuk referensi laporan.
- Irigasi dan IoT: data irigasi, laporan irigasi, rules, monitoring, sensor/aktuator API.
- Cuaca dan angin: curah hujan, kecepatan angin, integrasi/scraping, dashboard.
- Harga dan gabah/beras: harga komoditas, produksi gabah, analytics.
- Feedback: pelaporan bug/usulan fitur dan tracking status.
- Storytelling/evaluasi: analisis produksi, cuaca, hama, dan evaluasi akurasi panen.

## Arsitektur Ringkas

- Entry point web dan API: `index.php`.
- Controller web: `app/controllers`.
- Controller API: `app/controllers/Api`.
- Model: `app/models`, memakai PDO dari `config/database.php`.
- Service/helper: `app/services`, `app/helpers`.
- View PHP: `app/views`.
- Routing API eksplisit: `app/core/Router.php`.
- Routing web konvensional: `/{controller}/{method}/{params}` dari `index.php`.
- Script operasional: `scripts`.
- Maintenance SQL: `database/maintenance`.

## Prinsip Kerja

- Jangan kerja langsung di `main`.
- Satu branch untuk satu scope.
- Perubahan database harus jelas, aman, dan punya rollback bila memungkinkan.
- Jangan commit file runtime, upload, cache, dump SQL lokal, log, atau file rahasia.
- Untuk AI agent baru, mulai dari `AGENTS.md`, lalu file ringkas ini dan dokumen konteks lain di root repo.
