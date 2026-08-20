# Desain Offline-First & Sinkronisasi Mobile JAGAPADI

## 1. Alur Simpan (Draf)

1. User mengisi form & menekan **Simpan Draf** (online maupun offline).
2. Form memvalidasi (angka, tanggal, koordinat, catatan, foto bila ada).
3. Draf disimpan ke SQLite lokal:
   - Pertama kali: `insertDraft` → membuat `client_operation_id`
     (`OperationId.generate()`, format `op-<32hex>`).
   - Berikutnya: `updateDraft` pada id yang sama (tidak ada duplikat).
4. Bila online, form mengirim payload + header `Idempotency-Key` ke server.
   - Sukses → `markSynced(serverId)`, foto diunggah lalu
     `markPhotoSynced`.
   - Gagal → draf tetap tersimpan; snackbar memberitahu bahwa draf akan
     disinkronkan saat server siap.

## 2. Alur Submit

1. Submit **hanya** saat online; foto wajib ada (baru/URL lama).
2. Payload `action: submit` + `Idempotency-Key` dikirim ke server.
3. Sukses → draf lokal dihapus.
4. Gagal validasi (422) → error field ditampilkan, draf dipertahankan.

## 3. Sinkronisasi Otomatis (`SyncService`)

- Draf diambil dari `getSyncableDrafts()`:
  - **Termasuk**: `pending` dan `pending_photo`.
  - **Dikecualikan** (terminal, tidak di-auto-retry): `failed_validation`,
    `conflict`.
- Untuk draf `pending_photo` yang sudah `photoSynced`, upload foto
  dilewati (payload tetap dikirim).
- POST/PUT membawa `Idempotency-Key` dari `client_operation_id`.
- Respons:
  - 201/200 → `markSynced`.
  - 409 → `conflict` (duplikat/konflik) — tidak di-auto-retry.
  - 422 → `failed_validation` — tidak di-auto-retry.
- UI daftar draf memakai `getUnsyncedDrafts` (semua non-synced) agar
  user tetap melihat draf bermasalah dan dapat memperbaikinya.

## 4. Status Draf Lokal

| Status | Arti | Auto-sync? |
|--------|------|------------|
| `pending` | Belum dikirim | Ya |
| `pending_photo` | Payload terkirim, foto belum | Ya (payload; foto hanya bila `photoSynced=false`) |
| `synced` | Tersinkron penuh | Tidak |
| `failed_validation` | Ditolak server (422) | Tidak |
| `conflict` | Konflik/duplikat (409) | Tidak |

## 5. Idempotensi Anti-Duplikasi

- `client_operation_id` dibuat SEKALI per draf dan tidak pernah berubah.
- Dikirim sebagai header `Idempotency-Key` pada setiap retry.
- Kontrak untuk backend: jika server pernah menerima key yang sama untuk
  endpoint + user yang sama, kembalikan respons asli (tanpa membuat
  record baru). Lihat `API_COMPATIBILITY.md`.

## 6. Konektivitas

- `ConnectivityService` memantau koneksi (connectivity_plus).
- Draf selalu tersimpan lokal dulu; koneksi hanya menentukan apakah
  request langsung dikirim atau menunggu sinkronisasi.
- Dashboard: saat offline dengan cache → tampilkan data lama + penanda
  "offline" + tombol retry (bukan angka nol).

## 7. Migration DB (v2 → v3)

- Kolom baru: `client_operation_id TEXT` (nullable), `photoSynced INTEGER
  DEFAULT 0`.
- Draf lama bernilai null: `Idempotency-Key` tidak dikirim (kompatibel
  dengan backend lama); kolom diisi saat draf berikutnya disimpan ulang.
- `PRAGMA user_version` dinaikkan ke 3; migration idempoten (aman
  dijalankan berulang).