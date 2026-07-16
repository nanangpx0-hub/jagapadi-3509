#!/usr/bin/env bash
set -Eeuo pipefail

info() {
  printf '[security-cleanup] %s\n' "$*"
}

warn() {
  printf '[security-cleanup][warn] %s\n' "$*" >&2
}

require_git_repo() {
  if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    warn "Script must be run from inside the JAGAPADI git repository."
    exit 1
  fi
}

random_hex() {
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -hex 32
    return
  fi

  php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
}

sha256() {
  php -r 'echo hash("sha256", stream_get_contents(STDIN)), PHP_EOL;'
}

remove_sensitive_files_from_git_cache() {
  local -a pathspecs=(
    '*.sql'
    '*.dump'
    '*.bak'
    '*.tmp'
    '*.log'
    'cookies.txt'
    '**/cookies.txt'
    'error_log'
    '**/error_log'
    'logs/**'
    'storage/logs/**'
    'storage/cache/**'
    '.env'
    '.env.*'
  )

  local -a tracked=()
  while IFS= read -r -d '' file; do
    case "$file" in
      .env.example)
        continue
        ;;
    esac
    tracked+=("$file")
  done < <(git ls-files -z -- "${pathspecs[@]}")

  if ((${#tracked[@]} == 0)); then
    info "No sensitive tracked files found in git index."
    return
  fi

  info "Removing sensitive files from git index while keeping local copies:"
  printf '  - %s\n' "${tracked[@]}"
  git rm --cached -- "${tracked[@]}"
}

write_production_env_example() {
  cat > .env.example <<'EOF'
# ==================================
# JAGAPADI Production Environment Template
# ==================================
# Copy this file to .env on the target server.
# Never commit real credentials, API keys, cookies, SQL dumps, or logs.

# Application
APP_NAME=JAGAPADI
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com/jagapadi

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=jagapadi
DB_USER=jagapadi_app
DB_PASS=__SET_IN_DEPLOYMENT__
DB_CHARSET=utf8mb4

# Cache
CACHE_ENABLED=true
CACHE_DRIVER=redis
CACHE_PREFIX=jagapadi
CACHE_DEFAULT_TTL=60
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=__SET_IN_DEPLOYMENT__
REDIS_DATABASE=0
REDIS_TIMEOUT=1.0
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211

# API Authentication
# Prefer *_HASH values in production. Generate raw keys outside Git:
# php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
# php -r "echo hash('sha256', getenv('TOKEN')), PHP_EOL;"
SCRAPER_API_KEY=
SCRAPER_API_KEY_HASH=__SET_IN_DEPLOYMENT__
SCRAPER_API_KEY_BACKUP=
SCRAPER_API_KEY_BACKUP_HASH=
SCRAPER_ALLOWED_IPS=

MOBILE_API_KEY=
MOBILE_API_KEY_HASH=__SET_IN_DEPLOYMENT__

EXTERNAL_API_KEY=
EXTERNAL_API_KEY_HASH=__SET_IN_DEPLOYMENT__

# Integrations
SIMITRA_API_URL=https://simitra.example.invalid/api/
SIMITRA_API_TOKEN=__SET_IN_DEPLOYMENT__

# Email
ADMIN_EMAIL=admin@example.com
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=__SET_IN_DEPLOYMENT__
SMTP_PASS=__SET_IN_DEPLOYMENT__
SMTP_FROM=no-reply@example.com
SMTP_FROM_NAME=JAGAPADI System

# Features
AUTO_APPROVE_ENABLED=false
EOF

  info "Rewrote .env.example with production-safe placeholders."
}

rotate_dummy_api_tokens() {
  local dummy_api_key
  local dummy_api_hash

  dummy_api_key="dummy_$(random_hex)"
  dummy_api_hash="$(printf '%s' "$dummy_api_key" | sha256)"

  mkdir -p docs/security
  cat > docs/security/dummy_api_tokens.example <<EOF
# Generated dummy token rotation example.
# These values are fake and are safe only as documentation.
# Do not use them in production.
DUMMY_API_KEY=${dummy_api_key}
DUMMY_API_KEY_HASH=${dummy_api_hash}
EOF

  info "Generated docs/security/dummy_api_tokens.example with fake rotated API token values."
}

main() {
  require_git_repo
  remove_sensitive_files_from_git_cache
  rotate_dummy_api_tokens
  write_production_env_example

  info "Cleanup complete. Review changes with: git status --short"
  warn "If real secrets were committed, also rotate them in the real systems and rewrite git history with git-filter-repo or BFG."
}

main "$@"
