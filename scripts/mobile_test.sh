#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
test -n "$COMPOSE_PROJECT_NAME"
trap 'docker compose down --volumes --remove-orphans' EXIT
P2_BROWSER=1 bash scripts/p2_install.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxmobile /checks/mobile -name "*.php" -exec php -l {} \;'
docker compose exec -T web php /checks/mobile/runtime.php prepare
docker compose cp web:/var/lib/dolibarr/documents/mobile-accounts.json .local/mobile-accounts.json
chmod 600 .local/mobile-accounts.json
python3 tests/mobile/browser.py
docker compose exec -T web php /checks/mobile/runtime.php verify
