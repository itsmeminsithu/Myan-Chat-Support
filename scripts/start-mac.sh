#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if ! command -v docker >/dev/null; then echo 'Docker Desktop is required: https://www.docker.com/products/docker-desktop/'; exit 1; fi
if ! docker info >/dev/null 2>&1; then echo 'Docker Engine is not running. Open Docker Desktop and rerun.'; exit 1; fi
if [ ! -f .env ]; then
  if ! command -v openssl >/dev/null; then echo 'OpenSSL command required'; exit 1; fi
  { echo "MCS_DB_PASSWORD=$(openssl rand -hex 24)"; echo "MCS_DB_ROOT_PASSWORD=$(openssl rand -hex 24)"; echo "MCS_SETUP_KEY=$(openssl rand -hex 24)"; echo 'MCS_PORT=8088'; } > .env
  chmod 600 .env
  echo 'Created private .env — do not commit to GitHub.'
fi
docker compose up -d --build
printf '\nMyan Chat Support v1.0.2\n'
printf 'Installer: http://localhost:8088/install.php\n'
printf 'Widget demo (after installation): http://localhost:8088/demo.php\n'
printf 'Admin: http://localhost:8088/admin.php\n'
printf 'Installer setup key (keep secret): '
grep '^MCS_SETUP_KEY=' .env | cut -d= -f2-
printf '\nOn the installer, use the prefilled MySQL settings from Docker.\n'
