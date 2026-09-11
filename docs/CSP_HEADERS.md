# CSP & Security Headers — Backend v1

- **CSP**: `default-src 'self'; script-src 'self' 'nonce-{random}'; style-src 'self' 'nonce-{random}'; img-src 'self' data: https://*.tile.openstreetmap.org; font-src 'self'; connect-src 'self'` — nonce per-request `bin2hex(random_bytes(16))` inject ke `header.php`, inline `unsafe-inline` dihapus bertahap setelah `scripts/generate-csp-hashes.php` whitelist.
- **Hapus**: `X-XSS-Protection` (usang)
- **Pertahankan**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN` (atau `frame-ancestors 'self'`), `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: geolocation=(self), camera=(self), microphone=()`, `HSTS` hanya HTTPS `includeSubDomains` (31536000)
- **CORS**: `CORS_ALLOWED_ORIGINS` exact allowlist per env, `Vary: Origin`, `Access-Control-Allow-Credentials: true` bila origin terpercaya
- **Output encoding**: `Security::e()` `htmlspecialchars(ENT_QUOTES)` untuk HTML, `rawurlencode` URL, `json_encode(JSON_HEX_TAG|HEX_APOS|HEX_QUOT|HEX_AMP)` untuk JS/JSON, CSV/Excel `sanitizeCell()` prefix `'` untuk `=+-@`
