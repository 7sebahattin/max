#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock
unset DOCKER_CONTEXT DOCKER_TLS_VERIFY DOCKER_CERT_PATH
export COMPOSE_FILE=compose.yaml:tests/mail/compose.yaml
case "${COMPOSE_PROJECT_NAME:-}" in max-mail-*) ;; *) echo 'Require unique max-mail- project' >&2; exit 2;; esac
for kind in container volume network; do
 case "$kind" in container) found="$(docker ps -aq --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 volume) found="$(docker volume ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 network) found="$(docker network ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";; esac
 if [[ -n "$found" ]]; then echo 'Refusing pre-existing Compose project' >&2; exit 2; fi
done
mkdir -p .local
printf '%s' "$COMPOSE_PROJECT_NAME" > .local/mail-owned
trap 'docker compose down --volumes --remove-orphans' EXIT
python3 tests/mail/fixtures.py
bash scripts/p2_install.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web php /checks/mail/native.php setup
python3 tests/mail/pilot.py
python3 tests/mail/browser.py
docker compose exec -T web php /checks/mail/native.php verify
docker compose down --volumes --remove-orphans
for kind in container volume network; do
 case "$kind" in container) count="$(docker ps -aq --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 volume) count="$(docker volume ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";;
 network) count="$(docker network ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")";; esac
 test -z "$count"
done
echo 'PASS owned mail project teardown'
# Intentionally strict: native product gaps must stay visible, never xfail/skip/continue-on-error.
python3 tests/mail/product_contracts.py
