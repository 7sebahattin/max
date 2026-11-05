#!/usr/bin/env bash
# Fresh single shared synthetic installation only. No staging/deployment.
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock COMPOSE_FILE=compose.yaml:tests/mailintake/compose.yaml
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH COMPOSE_PROFILES COMPOSE_ENV_FILES
if [[ ! "${COMPOSE_PROJECT_NAME:-}" =~ ^max-coenabled-[a-z0-9-]+$ ]]; then echo 'Require unique max-coenabled- project' >&2; exit 2; fi
coenabled_selector="com.docker.compose.project=$COMPOSE_PROJECT_NAME"
for kind in container volume network; do
 case "$kind" in container) resources="$(docker ps -aq --filter "label=$coenabled_selector")";; volume) resources="$(docker volume ls -q --filter "label=$coenabled_selector")";; network) resources="$(docker network ls -q --filter "label=$coenabled_selector")";; esac
 if [[ -n "$resources" ]]; then echo 'Refusing pre-existing coenabled resources' >&2; exit 2; fi
done
if [[ -e .local/coenabled-owner || -e .local/p2-admin-secret || -e .local/documents/install.lock ]]; then echo 'Fresh checkout runtime required' >&2; exit 2; fi
mkdir -p .local/coenabled-evidence
printf '%s' "$COMPOSE_PROJECT_NAME" > .local/coenabled-owner
cleanup_coenabled() {
 local test_status=$? cleanup_status=0 resources=''
 trap - EXIT
 if ! docker compose down --volumes --remove-orphans; then cleanup_status=1; fi
 for kind in container volume network; do
  case "$kind" in
   container) if ! resources="$(docker ps -aq --filter "label=$coenabled_selector")"; then cleanup_status=1; fi;;
   volume) if ! resources="$(docker volume ls -q --filter "label=$coenabled_selector")"; then cleanup_status=1; fi;;
   network) if ! resources="$(docker network ls -q --filter "label=$coenabled_selector")"; then cleanup_status=1; fi;;
  esac
  if [[ -n "$resources" ]]; then cleanup_status=1; fi
 done
 python3 tests/coenabled/cleanup.py "$test_status" "$cleanup_status"
}

trap cleanup_coenabled EXIT
python3 tests/mailintake/seed.py
python3 tests/coenabled/identity.py before
bash scripts/p2_install.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web chown -R www-data:www-data /var/lib/dolibarr/documents
docker compose exec -T web sh -c 'find /checks/coenabled -name "*.php" -print0 | xargs -0 -n1 php -l'
python3 tests/coenabled/identity.py installed
python3 tests/coenabled/run.py
python3 tests/coenabled/identity.py final
