# Deploy Atomik — Backend v1

- **Release dir**: `/var/www/jagapadi/releases/{timestamp}` + `ln -sfn releases/{ts} current` (`backend/public` docroot → `current/backend/public`)
- **Artifact**: `tar --exclude=.git --exclude=tests/e2e/backups/.env -czf release.tar.gz .` hanya backend v1 + `composer --no-dev --classmap-authoritative`
- **Known hosts**: `ssh-keyscan -p $PORT $HOST >> ~/.ssh/known_hosts` dipin, `RELEASE_DIR` divalidasi `realpath` + `[[ $RELEASE_DIR == /var/www/* ]]`
- **Permission**: `storage/` 775 `www-data`, `.env` 640 tidak ikut artifact
- **Backup**: `mysqldump jagapadi_prod | gzip > backups/db_pre_{ts}.sql.gz` + `tar uploads`
- **Migrate fail-closed**: `php backend/scripts/migrate.php || exit 1` (tanpa `|| echo`), `php -l backend/public/index.php`
- **Health**: `curl -fsS $APP_URL/api/v1/health` + `smoke_test` (login, dashboard, export) gagal → rollback `ln -sfn previous`
- **Staging promotion** via tag `deploy-*` manual, concurrency `production-deploy` lock, audit `activity_log` deployment.
