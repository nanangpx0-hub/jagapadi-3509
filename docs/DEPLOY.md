# Deployment Guide — JAGAPADI v1.0.0

> Panduan instalasi server production. Target: **Ubuntu 22.04/24.04 LTS** + **Nginx** + **MySQL 8** + **PHP 8.2**.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Clone & Composer](#2-clone--composer)
3. [Environment Configuration](#3-environment-configuration)
4. [Database Setup](#4-database-setup)
5. [Directory Permissions](#5-directory-permissions)
6. [Nginx Configuration](#6-nginx-configuration)
7. [PHP-FPM Tuning](#7-php-fpm-tuning)
8. [TLS (HTTPS)](#8-tls-https)
9. [Cron Jobs](#9-cron-jobs)
10. [Post-Deploy Smoke Test](#10-post-deploy-smoke-test)
11. [Mobile App Build](#11-mobile-app-build)
12. [FCM Push Notification](#12-fcm-push-notification)
13. [Backup Strategy](#13-backup-strategy)
14. [Rollback Procedure](#14-rollback-procedure)

---

## 1. Prerequisites

```bash
# System packages
sudo apt update && sudo apt install -y \
    nginx \
    mysql-server-8.0 \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-gd \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-xml \
    php8.2-zip \
    php8.2-fileinfo \
    composer \
    certbot \
    python3-certbot-nginx \
    unzip

# Verify
php -v                    # Must be 8.2+
mysql --version           # Must be 8.0+
composer --version
nginx -v
```

### PHP Extensions Checklist

| Extension | Required For |
|-----------|-------------|
| `pdo_mysql` | Database connection |
| `gd` | Image compression (upload foto) |
| `mbstring` | UTF-8 string handling |
| `fileinfo` | MIME type validation (upload) |
| `curl` | FCM push HTTP calls |
| `zip` | XLSX export (PclZip) |
| `json` | API responses |

---

## 2. Clone & Composer

```bash
# Clone repository
cd /var/www
git clone <repository-url> jagapadi
cd jagapadi/backend

# Install production dependencies only
composer install --no-dev --optimize-autoloader

# Verify autoloader
php -r "require 'vendor/autoload.php'; echo 'OK';"
# Output: OK
```

> **Note**: The document root is `backend/public/`. The monorepo structure is kept intact; only `backend/public/` is served by Nginx.

---

## 3. Environment Configuration

```bash
cd /var/www/jagapadi/backend
cp .env.example .env
chmod 640 .env
chown www-data:www-data .env   # or :root depending on setup
```

Edit `.env` with production values:

```ini
APP_ENV=production
APP_DEBUG=false
APP_BASE_URL=https://jagapadi.example.go.id

DB_NAME=jagapadi_prod
DB_USER=jagapadi_user
DB_PASS=<strong-random-password>

JWT_SECRET=<64+ random hex characters>
JWT_EXPIRY=3600

CORS_ALLOWED_ORIGINS=https://admin.jagapadi.example.go.id
```

### Generate Secrets

```bash
# JWT secret (64+ hex chars)
php -r "echo bin2hex(random_bytes(32));"

# DB password — use a password manager
```

### Critical Env Vars Reference

| Variable | Production Value | Notes |
|----------|-----------------|-------|
| `APP_ENV` | `production` | Disables debug output |
| `APP_DEBUG` | `false` | Prevents stack trace leakage |
| `JWT_SECRET` | Random ≥64 hex chars | Never share or commit |
| `CORS_ALLOWED_ORIGINS` | One or more HTTPS origins | Never `*` in production |
| `FCM_ENABLED` | `true`/`false` | Only `true` when Firebase is ready |
| `SESSION_NAME` | `jagapadi_session` | Change if needed, no special chars |
| `LOGIN_MAX_ATTEMPTS` | `5` | Brute-force protection |
| `APP_LOG_LEVEL` | `warning` | Reduce log noise in production |

---

## 4. Database Setup

```bash
# Create database
mysql -u root -p -e "
CREATE DATABASE IF NOT EXISTS jagapadi_prod
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
"

# Create user with least privilege
mysql -u root -p -e "
CREATE USER IF NOT EXISTS 'jagapadi_user'@'localhost'
  IDENTIFIED BY '<strong-password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON jagapadi_prod.*
  TO 'jagapadi_user'@'localhost';
FLUSH PRIVILEGES;
"
```

### Run Migrations

```bash
cd /var/www/jagapadi/backend
php scripts/migrate.php
```

This will execute all migration files in `database/migrations/` (001–010+) sequentially.

### Initial Admin User

**Do NOT use default seed passwords in production.**

Manually create the admin user via SQL:

```sql
INSERT INTO `users` (`username`, `password`, `nama_lengkap`, `role`, `aktif`, `must_change_password`)
VALUES (
  'admin',
  '$2y$12$...',  <!-- generate with: php -r "echo password_hash('StrongAdminPass!123', PASSWORD_BCRYPT, ['cost'=>12]);" -->
  'Administrator',
  'admin',
  1,
  1
);
```

> Alternatively, run `php scripts/seed.php` on a fresh DB, then immediately change passwords.

---

## 5. Directory Permissions

```bash
cd /var/www/jagapadi

# Web-writable directories
chown -R www-data:www-data backend/storage/cache
chown -R www-data:www-data backend/storage/logs
chown -R www-data:www-data backend/storage/tmp
chown -R www-data:www-data backend/public/assets/uploads

# Set permissions
find backend/storage -type d -exec chmod 775 {} \;
find backend/public/assets/uploads -type d -exec chmod 775 {} \;

# Protect .env
chmod 640 backend/.env

# Ensure .htaccess in uploads dir blocks PHP
cat backend/public/assets/uploads/.htaccess
# Expected output (should exist):
#   php_flag engine off
#   ...
```

### Permission Summary

| Path | Owner | Mode | Notes |
|------|-------|------|-------|
| `backend/.env` | `www-data:www-data` | `640` | Credentials |
| `backend/storage/cache/` | `www-data:www-data` | `775` | Cache files |
| `backend/storage/logs/` | `www-data:www-data` | `775` | Log files |
| `backend/storage/tmp/` | `www-data:www-data` | `775` | Export temp files |
| `backend/public/assets/uploads/` | `www-data:www-data` | `775` | Uploaded images |
| `backend/vendor/` | `root:root` | `755` | Read-only (Composer) |

---

## 6. Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name jagapadi.example.go.id;

    root /var/www/jagapadi/backend/public;
    index index.php;

    # SSL — managed by certbot (see section 8)
    ssl_certificate /etc/letsencrypt/live/jagapadi.example.go.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/jagapadi.example.go.id/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # Security headers (complement PHP headers in index.php)
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header Referrer-Policy strict-origin-when-cross-origin;
    add_header X-XSS-Protection "1; mode=block";
    # Server tokens off
    server_tokens off;

    # Upload size limit (matches PHP upload_max_filesize)
    client_max_body_size 12M;

    # Deny access to sensitive directories
    location ~* /(app|config|database|storage|tests|vendor)/ {
        deny all;
        return 404;
    }

    # Deny access to .env, .git, etc.
    location ~* \.(env|git|svn|hg|log|lock)$ {
        deny all;
        return 404;
    }

    # Deny PHP execution in uploads
    location ~* /assets/uploads/.*\.php$ {
        deny all;
        return 404;
    }

    # Deny execution of .ht* files
    location ~ /\.(ht|git) {
        deny all;
        return 404;
    }

    # Static assets — cache aggressively
    location /assets/ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # PHP-FPM
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny all other file extensions
    location ~* \.(htaccess|htpasswd|ini|conf|psd|log|sh)$ {
        deny all;
        return 404;
    }

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript image/svg+xml;
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name jagapadi.example.go.id;
    return 301 https://$server_name$request_uri;
}
```

### Nginx Hardening Summary

| Measure | Implementation |
|---------|---------------|
| `server_tokens off` | Hides nginx version |
| Restrict methods | Optional: `limit_except GET POST PUT DELETE` |
| Rate limiting | Application-level (RateLimitMiddleware); Nginx `limit_req` optional |
| Deny access to sensitive dirs | `location ~* /(app|config|...)/` |
| No PHP in uploads | `location ~* /assets/uploads/.*\.php$` |
| Security headers | `add_header` directives |

---

## 7. PHP-FPM Tuning

Edit `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
; Production values (adjust based on server RAM)
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Security
security.limit_extensions = .php
; Run as www-data (default)
; user = www-data
; group = www-data
```

Edit `/etc/php/8.2/fpm/php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 120
memory_limit = 256M
date.timezone = Asia/Jakarta

; Disable dangerous functions (production)
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,show_source
```

Restart:

```bash
sudo systemctl restart php8.2-fpm
```

---

## 8. TLS (HTTPS)

```bash
# Obtain certificate (replace domain)
sudo certbot --nginx -d jagapadi.example.go.id

# Auto-renewal is enabled by default; verify:
sudo certbot renew --dry-run

# The Nginx config in section 6 assumes certbot-managed SSL.
```

> **Always enforce HTTPS.** Session cookies have `cookie_secure=1` in production; mixed content will break.

---

## 9. Cron Jobs

### Notification Prune (Daily)

Deletes notifications older than 90 days.

```bash
# Option A: Via CLI script
0 3 * * * cd /var/www/jagapadi/backend && php scripts/prune-notifications.php >> storage/logs/prune.log 2>&1
```

If the script does not exist yet, create it (see `scripts/prune-notifications.php` example).

### Backup DB (Daily)

```bash
0 2 * * * /var/www/jagapadi/scripts/backup-db.sh >> /var/www/jagapadi/backups/backup.log 2>&1
```

### Backup Uploads (Weekly)

```bash
0 3 * * 0 /var/www/jagapadi/scripts/backup-uploads.sh >> /var/www/jagapadi/backups/backup.log 2>&1
```

### Log Rotation

Use `logrotate`:

```bash
# /etc/logrotate.d/jagapadi
/var/www/jagapadi/backend/storage/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data www-data
}
```

---

## 10. Post-Deploy Smoke Test

See [SMOKE_TEST.md](SMOKE_TEST.md) for a full walkthrough.

Quick check:

```bash
# Health endpoint
curl -sS https://jagapadi.example.go.id/api/v1/health | jq .

# Login API
curl -sS -X POST https://jagapadi.example.go.id/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"<password>"}' | jq .

# Web admin
# Open https://jagapadi.example.go.id/login in a browser
```

---

## 11. Mobile App Build

```bash
cd mobile

# Build release APK with production API URL
flutter build apk --release \
  --dart-define=API_BASE_URL=https://jagapadi.example.go.id/api/v1 \
  --dart-define=FCM_ENABLED=true

# Or AAB (Play Store)
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://jagapadi.example.go.id/api/v1 \
  --dart-define=FCM_ENABLED=true
```

### Mobile Build Checklist

- [ ] `API_BASE_URL` points to **HTTPS** production backend
- [ ] `FCM_ENABLED=true` only if Firebase project is configured
- [ ] `google-services.json` from Firebase Console placed in `android/app/` (do NOT commit)
- [ ] Signing keys (`*.jks`, `key.properties`) stored securely outside repo
- [ ] ProGuard/R8: keep model classes used by `json_serializable`

---

## 12. FCM Push Notification

### Backend

1. Set `FCM_ENABLED=true` and `FCM_SERVER_KEY` in `.env`
2. The server key is obtained from Firebase Console → Project Settings → Cloud Messaging
3. Legacy HTTP protocol is used: `POST https://fcm.googleapis.com/fcm/send`

### Mobile

1. Ensure `google-services.json` is present in `android/app/` (never committed)
2. Build with `--dart-define=FCM_ENABLED=true`
3. Test: login triggers token registration; submit laporan triggers push to admin

### FCM Architecture Notes

| Component | Implementation |
|-----------|---------------|
| Protocol | Legacy HTTP (FCM Legacy) |
| Backend fallback | `NullPushNotifier` when `FCM_ENABLED=false` |
| Token lifecycle | Register on login, delete on logout, auto-refresh listener |
| In-app DB | Source of truth; push is best-effort (try-catch) |
| Flutter degrade | `Firebase.initializeApp()` wrapped in try-catch |

---

## 13. Backup Strategy

| Data | Frequency | Retention | Method |
|------|-----------|-----------|--------|
| Database | Daily | 30 days | `mysqldump` → compressed `.sql.gz` |
| Uploads | Weekly | 90 days | `rsync` or `tar.gz` |
| `.env` | Manual (secret) | Permanent | Password manager |

> See `scripts/backup-db.sh.example` and `scripts/backup-uploads.sh.example` for reference scripts.

All backups go to `backups/` (gitignored). The backup directory should be on a separate volume or external mount.

---

## 14. Rollback Procedure

```bash
# 1. Restore database
mysql -u root -p jagapadi_prod < backups/jagapadi_YYYYMMDD.sql

# 2. Restore previous release
cd /var/www
mv jagapadi jagapadi_rollback
git clone <repository-url> jagapadi
cd jagapadi/backend
git checkout tags/v0.9.0  # or previous stable tag
composer install --no-dev --optimize-autoloader

# 3. Restore uploads
tar -xzf backups/uploads_YYYYMMDD.tar.gz -C backend/public/assets/uploads/

# 4. Restore .env
cp /root/backups/.env.backup backend/.env
chmod 640 backend/.env
chown www-data:www-data backend/.env

# 5. Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# 6. Run smoke test (SMOKE_TEST.md)
```

> Rollback should be tested in a staging environment before production go-live.

---

## References

- [GO_LIVE_CHECKLIST.md](GO_LIVE_CHECKLIST.md) — Pre-flight checklist
- [SMOKE_TEST.md](SMOKE_TEST.md) — Post-deploy validation
- [QA_CHECKLIST.md](QA_CHECKLIST.md) — Full regression checklist
- [BLUEPRINT.md](BLUEPRINT.md) — Architecture & business rules
- [API.md](API.md) — API contract
