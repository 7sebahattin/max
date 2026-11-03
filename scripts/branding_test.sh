#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
test -n "$COMPOSE_PROJECT_NAME"
test "$BRANDING_VARIANT" = a || test "$BRANDING_VARIANT" = b
trap 'docker compose down --volumes --remove-orphans' EXIT
bash scripts/maxcore_test.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web php /checks/branding/fixture.php prepare "$BRANDING_VARIANT"
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxcore /checks/branding -name "*.php" -exec php -l {} \;'
docker compose cp web:/var/lib/dolibarr/documents/branding-fixture.jpg .local/branding-fixture.jpg
python3 tests/branding/browser.py
