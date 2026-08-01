# Prompt: Verifikasi Runtime Mobile Slice Auth (JAGAPADI)

Tempelkan prompt ini ke OpenCode di **PC yang sudah punya Flutter + device/emulator**.

---

Anda adalah engineer Flutter + backend JAGAPADI.

Tugas
Jalankan verifikasi runtime Mobile Slice Auth (opsi A): environment Flutter, koneksi API, lalu uji manual M1–M8.
Jangan implement fitur baru besar.
Jangan kerjakan offline/sync/foto kecuali blocker login.
Fokus: buktikan login JWT mobile benar-benar jalan di device/emulator.

Konteks
- Backend web sudah stabil (Auth, Verifikasi, Export lulus uji).
- Mobile codebase sudah ada di mobile/ dan relatif lengkap.
- 2 bug auth sudah diperbaiki sebelumnya:
  1) user.dart: key aktif (bool) bukan is_active
  2) api_client.dart: delete() support data body untuk FCM unregister
- Akun uji local:
  - petugas01 / ChangeMePetugas!123
  - petugas02 / ChangeMePetugas!123
  - petugas_demo / ChangeMePetugas!123  (must_change_password=1)
  - petugas_nonaktif / ChangeMePetugas!123 (aktif=0)
  - admin / ChangeMeAdmin!123
- API base: /api/v1
- Auth: JWT Bearer
- Jangan commit secret, google-services.json, keystore, .env production.

==================================================
FASE 0 — Inspeksi
==================================================
1. Baca file nyata:
   - mobile/pubspec.yaml
   - mobile/lib/main.dart
   - mobile/lib/core/* (api_client, config, env, storage)
   - mobile/lib/features/auth/*
   - docs/BUILD_APK.md jika ada
   - CURRENT_TASK.md
2. Catat:
   - Nama dart-define / env untuk base URL (API_BASE_URL atau lain)
   - State management (Riverpod/BLoC)
   - Secure storage package
   - Apakah cleartext HTTP sudah diizinkan untuk debug Android
3. Jangan rewrite arsitektur.

==================================================
FASE 1 — Environment
==================================================
1. Jalankan:
   flutter --version
   flutter doctor -v
2. Jika Flutter belum terpasang:
   - Beri instruksi install singkat untuk OS mesin ini
   - Jangan pura-pura analyze/run sukses
   - Stop dengan laporan blocker yang jelas
3. Jika Flutter ada:
   - Pastikan ada device: emulator Android atau HP USB debugging
   - flutter devices
4. Backend harus hidup:
   cd backend
   php -S 0.0.0.0:8080 -t public
5. Verifikasi backend:
   curl http://localhost:8080/api/v1/health
   curl login petugas01 harus dapat token

==================================================
FASE 2 — Base URL & cleartext
==================================================
Tentukan target:
- Android emulator → http://10.0.2.2:8080
- HP fisik → http://IP_LAN_PC:8080

Cek/implement minimal jika belum ada:
1. Config base URL via --dart-define atau file config existing
2. Android debug cleartext:
   - android:usesCleartextTraffic="true" pada debug/manifest dev
   - atau network security config dev
3. Jangan hardcode IP production
4. Jangan commit secret

==================================================
FASE 3 — Build & static check
==================================================
cd mobile
flutter pub get
flutter analyze
flutter test   # jika ada test; jika tidak, catat "no tests"

Perbaiki HANYA error yang memblokir compile/analyze untuk auth flow.
Jangan refactor besar.
Jika ada error di modul non-auth yang memblokir run, fix minimal atau skip dengan alasan.

==================================================
FASE 4 — Run app
==================================================
Contoh:
# emulator
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080

# device fisik (ganti IP)
flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8080

Jika project memakai key config berbeda, pakai key yang benar dari kode.
Pastikan app ter-install dan LoginPage tampil.

==================================================
FASE 5 — Uji manual M1–M8 (WAJIB)
==================================================
Isi PASS/FAIL + bukti singkat (screenshot path opsional / log).

M1 Login sukses
- Username: petugas01
- Password: ChangeMePetugas!123
PASS jika: masuk Home/Dashboard mobile, tidak crash, token tersimpan.

M2 Cold start session
- Kill app fully, buka lagi
PASS jika: tetap authenticated (jika token masih valid) tanpa login ulang.

M3 Logout
- Tekan logout
PASS jika: kembali LoginPage, token terhapus, buka ulang app tetap minta login.

M4 Password salah
- Login petugas01 + password salah
PASS jika: pesan error generik/user-friendly, tidak stacktrace, tidak crash.

M5 must_change_password
- Login petugas_demo / ChangeMePetugas!123
PASS jika: diarahkan ke ubah password, tidak masuk fitur utama dulu.

M6 akun nonaktif
- Login petugas_nonaktif / ChangeMePetugas!123
PASS jika: gagal login (aktif=false dihormati), pesan generik.

M7 change password
- Dari petugas_demo atau alur profile:
  current benar, new valid + confirmation match
PASS jika: sukses; login ulang dengan password baru berhasil
CATATAN: jika mengubah password seed, catat password baru di laporan local saja, jangan commit.

M8 profil /me
- Setelah login valid, buka profil atau trigger GET /me
PASS jika: username/role/nama tampil; field password tidak ada; aktif terbaca benar.

Opsional jika mudah:
M9 Network down: airplane mode saat login → pesan koneksi gagal
M10 Token invalid: hapus/rusak token storage → kembali login

==================================================
FASE 6 — Bugfix hanya jika gagal
==================================================
Prioritas fix:
1. Base URL / cleartext / network
2. Mapping response user (aktif, must_change_password, nama_lengkap)
3. Secure storage token
4. Header Authorization Bearer
5. Refresh token hanya jika memblokir cold start
6. Navigation guard must_change_password

Jangan kerjakan:
- Offline draft/sync
- Upload foto besar
- Redesign UI
- FCM production setup (google-services) kecuali error memblokir start app; jika FCM optional, disable graceful

==================================================
FASE 7 — Dokumentasi
==================================================
Update singkat CURRENT_TASK.md:
- Mobile runtime auth verification status
- Base URL cara jalankan emulator vs device
- Hasil M1–M8

Jangan dokumentasi panjang.

==================================================
OUTPUT LAPORAN AKHIR
==================================================
Format wajib:

A. Environment
- flutter doctor ringkas
- device yang dipakai
- API_BASE_URL
- backend health: OK/FAIL

B. Static
- flutter pub get: OK/FAIL
- flutter analyze: jumlah issue
- flutter test: hasil atau no tests

C. File diubah
- path + 1 baris alasan (jika ada)

D. Hasil M1–M8
| ID | Hasil | Bukti singkat |
|----|-------|---------------|
| M1 | PASS/FAIL | ... |
...

E. Bug ditemukan & fix
- ...

F. Residual risk
- FCM, offline, cleartext prod, dll

G. Next slice usulan
- List laporan hama online / draft submit / foto GPS

Aturan
- Baca kode nyata dulu
- Jangan klaim PASS tanpa menjalankan di device/emulator
- Jika Flutter/device tidak tersedia, hentikan dengan blocker jelas + perintah exact untuk user
- Jangan commit secret
- Setelah M1–M8 selesai (atau blocker), berhenti

Mulai sekarang dari Fase 0 inspeksi mobile config + flutter doctor.

Jika flutter command tidak ada, JANGAN simulasi hasil uji.
Berikan blocker + perintah install exact untuk Windows,
lalu hentikan. User akan jalankan ulang prompt setelah Flutter siap.
