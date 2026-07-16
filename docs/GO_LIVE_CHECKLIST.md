# Go-Live Checklist — JAGAPADI v1.0.0

> Pre-flight checklist sebelum production go-live. Centang semua item.

---

## Environment

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false` — no stack trace leakage
- [ ] `APP_BASE_URL` points to **HTTPS** URL
- [ ] `JWT_SECRET` is a **random 64+ hex characters** (not default)
- [ ] Database: least privilege user (no DROP/CREATE/ALTER for app user)
- [ ] `CORS_ALLOWED_ORIGINS` set to **specific HTTPS origins** (not empty in production)

## Database

- [ ] All migrations (001–010+) applied
- [ ] Admin user created with **strong password** (not seed default)
- [ ] Petugas user created if needed
- [ ] `nomor_laporan_counter` initialised

## File Permissions

- [ ] `storage/cache/` — writable by web user (775)
- [ ] `storage/logs/` — writable by web user (775)
- [ ] `storage/tmp/` — writable by web user (775)
- [ ] `public/assets/uploads/` — writable by web user (775)
- [ ] `.env` — chmod **640**, not world-readable
- [ ] Uploads `.htaccess` blocks PHP execution

## TLS / HTTPS

- [ ] Valid SSL certificate installed
- [ ] HTTP → HTTPS redirect active
- [ ] Mixed content audit (no HTTP assets on HTTPS page)
- [ ] `session.cookie_secure=1` verified

## Nginx / Web Server

- [ ] `server_tokens off`
- [ ] Deny access to `app/`, `config/`, `storage/`, `vendor/`, `.env`
- [ ] Deny PHP execution in `assets/uploads/`
- [ ] `client_max_body_size` ≥ 12M
- [ ] Security headers present (X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy)
- [ ] Gzip compression enabled

## Backup

- [ ] DB backup script tested
- [ ] Uploads backup script tested
- [ ] Backup cron installed (daily DB + weekly uploads)
- [ ] Backup destination not on same disk as application
- [ ] Log rotation configured

## Cron

- [ ] Notification prune (daily): deletes notifications > 90 days
- [ ] Backup scripts cron entries active

## FCM (Optional)

- [ ] `FCM_ENABLED=false` (or `true` with valid server key)
- [ ] `google-services.json` **NOT** in repo
- [ ] Firebase project created and configured
- [ ] FCM token registration works on login

## Security

- [ ] Rate limiting active (login 5/15min, API 1000/h, export 20/h)
- [ ] CSRF tokens functional on web forms
- [ ] Upload file validation active (magic bytes, MIME, size, name)
- [ ] Session: httponly, samesite=Lax, secure in production
- [ ] No `.env`, `.pem`, `.key` in repo

## Monitoring

- [ ] Health endpoint responsive
- [ ] Logs rotating (storage/logs/)
- [ ] Admin email/contact for incident reporting

## Mobile

- [ ] APK/AAB built with `--dart-define=API_BASE_URL=https://...`
- [ ] `FCM_ENABLED` matches backend setting
- [ ] Signing keys stored securely (not in repo)
- [ ] `google-services.json` placed in `android/app/` (not committed)

## Post-Deploy

- [ ] Smoke test passed (see [SMOKE_TEST.md](SMOKE_TEST.md))
- [ ] QA checklist critical subset ticked (see [QA_CHECKLIST.md](QA_CHECKLIST.md))
- [ ] Rollback procedure documented and tested

---

## Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Developer | | | |
| DevOps | | | |
| Product Owner | | | |
