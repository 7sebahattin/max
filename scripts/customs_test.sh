#!/usr/bin/env bash
# Disposable project only. No staging/live endpoint.
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock COMPOSE_FILE=compose.yaml
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
if [[ ! "${COMPOSE_PROJECT_NAME:-}" =~ ^max-customs-[a-z0-9-]+$ ]]; then echo 'Require a unique max-customs- project' >&2; exit 2; fi
customs_selector="com.docker.compose.project=$COMPOSE_PROJECT_NAME"
if [[ -n "$(docker ps -aq --filter "label=$customs_selector")" || -n "$(docker volume ls -q --filter "label=$customs_selector")" || -n "$(docker network ls -q --filter "label=$customs_selector")" ]]; then
 echo 'Refusing pre-existing customs project' >&2; exit 2
fi
mkdir -p .local
printf '%s' "$COMPOSE_PROJECT_NAME" > .local/customs-owner
trap 'docker compose down --volumes --remove-orphans' EXIT
bash scripts/p2_install.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web chown -R www-data:www-data /var/lib/dolibarr/documents
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxcustoms /checks/customs -name "*.php" -print0 | xargs -0 -n1 php -l'
docker compose exec -T --user www-data web php /checks/customs/runtime.php setup
docker compose cp web:/var/lib/dolibarr/documents/customs-session.json .local/customs-session.json
chmod 600 .local/customs-session.json
python3 tests/customs/browser.py
docker compose exec -T --user www-data web php /checks/customs/runtime.php verify
docker compose down --volumes --remove-orphans
test -z "$(docker ps -aq --filter "label=$customs_selector")"
test -z "$(docker volume ls -q --filter "label=$customs_selector")"
test -z "$(docker network ls -q --filter "label=$customs_selector")"
echo 'PASS owned customs project completely removed'
