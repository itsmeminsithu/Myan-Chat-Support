#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose down
printf 'Containers stopped. Your MySQL and config volumes were NOT deleted.\n'
