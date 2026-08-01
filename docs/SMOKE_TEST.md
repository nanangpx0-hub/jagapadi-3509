# Smoke Test — JAGAPADI Post-Deploy

> Jalankan setelah deploy untuk memastikan sistem berfungsi.

---

## Prerequisites

- `curl` atau `httpie`
- `jq` untuk format JSON (optional)
- Browser modern

---

## 1. Health Check

```bash
curl -sS https://jagapadi.example.go.id/api/v1/health | jq .
```

**Expected:**
```json
{
  "status": "ok",
  "timestamp": "...",
  "database": "connected",
  "app_env": "production"
}
```

---

## 2. API Authentication

```bash
# Login
curl -sS -X POST https://jagapadi.example.go.id/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"<password>"}' | jq .
```

**Expected:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ...",
    "user": { "username": "admin", "role": "admin", ... }
  }
}
```

**Save token:**
```bash
TOKEN="eyJ..."
```

---

## 3. Wilayah (Master Data)

```bash
# List kabupaten
curl -sS https://jagapadi.example.go.id/api/v1/wilayah/kabupaten \
  -H "Authorization: Bearer $TOKEN" | jq '.success'
```

**Expected:** `true`

---

## 4. Laporan Hama — Create Draft

```bash
curl -sS -X POST https://jagapadi.example.go.id/api/v1/laporan-hama \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tanggal_kejadian": "2026-07-16",
    "kabupaten_id": 1,
    "kecamatan_id": 1,
    "desa_id": 1,
    "latitude": "-8.1845",
    "longitude": "113.6682",
    "action": "draft"
  }' | jq '.success'
```

**Expected:** `true` (returns draft laporan with status `Draf`, nomor_laporan null)

---

## 5. Notifikasi — Unread Count

```bash
curl -sS https://jagapadi.example.go.id/api/v1/notifications/unread-count \
  -H "Authorization: Bearer $TOKEN" | jq '.data.unread'
```

**Expected:** Integer (0 or more)

---

## 6. Export — CSV

```bash
curl -sS -o /dev/null -w "%{http_code}" \
  "https://jagapadi.example.go.id/api/v1/export/hama?format=csv&include_draft=false" \
  -H "Authorization: Bearer $TOKEN"
```

**Expected:** `200`

---

## 7. Browser Tests

| Item | How to Test | Expected |
|------|------------|----------|
| Login page | Open `https://jagapadi.example.go.id/login` | Form login muncul |
| Dashboard | Login as admin | Charts, map, KPI render |
| Assets | Check browser console | No 404 for Chart.js, Leaflet CSS/JS |
| Laporan Hama list | Navigate to `/laporan-hama` | List renders with filters |
| Export page | `/export` | Form with filter fields |
| Bell notification | Click bell icon | Dropdown shows recent notif |

---

## 8. Mobile Smoke

> Run on emulator or device pointed to production URL.

- [ ] Login with petugas credentials
- [ ] Create laporan hama draft
- [ ] Submit laporan → status `Submitted`
- [ ] Logout → re-login → draft still present
- [ ] (Admin) Login → antrian verifikasi
- [ ] Verify the submitted laporan

---

## 9. Security Headers

```bash
curl -sI https://jagapadi.example.go.id/api/v1/health | grep -iE '(x-frame-options|x-content-type|x-xss|csp|referrer)'
```

**Expected:** All headers present.

| Header | Expected Value |
|--------|---------------|
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | Present (may include `unsafe-inline`) |

---

## 10. Error Mode (APP_DEBUG=false)

```bash
# Hit a non-existent endpoint
curl -sS https://jagapadi.example.go.id/api/v1/nonexistent | jq .
```

**Expected:** Generic error JSON — no stack trace, no path leakage.

---

## Results

- [ ] Health check OK
- [ ] Auth login API OK
- [ ] Wilayah list OK
- [ ] Laporan draft OK
- [ ] Notifications OK
- [ ] Export OK
- [ ] Web dashboard renders
- [ ] Assets load without 404
- [ ] Mobile login + submit
- [ ] Security headers present
- [ ] No stack trace on error
