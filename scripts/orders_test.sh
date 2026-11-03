#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock
unset DOCKER_CONTEXT DOCKER_TLS_VERIFY DOCKER_CERT_PATH
case "${COMPOSE_PROJECT_NAME:-}" in max-orders-*) ;; *) echo 'Require unique max-orders- project' >&2; exit 2;; esac
for kind in container volume network; do
 case "$kind" in container) found="$(docker ps -aq --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 volume) found="$(docker volume ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 network) found="$(docker network ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";; esac
 if [[ -n "$found" ]]; then echo 'Refusing pre-existing Compose project' >&2; exit 2; fi
done
mkdir -p .local
printf '%s' "$COMPOSE_PROJECT_NAME" > .local/orders-owned
trap 'docker compose down --volumes --remove-orphans' EXIT
bash scripts/p2_install.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxorders /checks/orders -name "*.php" -exec php -l {} \;'
docker compose exec -T web php /checks/orders/runtime.php setup
docker compose exec -T web php /checks/orders/runtime.php toggle
docker compose cp web:/var/lib/dolibarr/documents/orders-accounts.json .local/orders-accounts.json
chmod 600 .local/orders-accounts.json
python3 tests/orders/browser.py
docker compose exec -T web php /checks/orders/runtime.php verify
docker compose down --volumes --remove-orphans
for kind in container volume network; do
 case "$kind" in container) count="$(docker ps -aq --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 volume) count="$(docker volume ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 network) count="$(docker network ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";; esac
 test -z "$count"
done
echo 'PASS owned Orders project teardown'
