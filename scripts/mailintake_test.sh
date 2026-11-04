#!/usr/bin/env bash
# Fresh isolated synthetic installation. No production provider or SMTP.
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock COMPOSE_FILE=compose.yaml:tests/mailintake/compose.yaml
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
if [[ ! "${COMPOSE_PROJECT_NAME:-}" =~ ^max-mailintake-[a-z0-9-]+$ ]]; then echo 'Require unique max-mailintake- project' >&2; exit 2; fi
intake_selector="com.docker.compose.project=$COMPOSE_PROJECT_NAME"
if [[ -n "$(docker ps -aq --filter "label=$intake_selector")" || -n "$(docker volume ls -q --filter "label=$intake_selector")" || -n "$(docker network ls -q --filter "label=$intake_selector")" ]]; then
 echo 'Refusing pre-existing intake project' >&2; exit 2
fi
mkdir -p .local
printf '%s' "$COMPOSE_PROJECT_NAME" > .local/mailintake-owner
trap 'docker compose down --volumes --remove-orphans' EXIT
python3 tests/mailintake/seed.py
bash scripts/p2_install.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web chown -R www-data:www-data /var/lib/dolibarr/documents
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxmail /checks/mailintake -name "*.php" -print0 | xargs -0 -n1 php -l'
docker compose cp .local/mailintake-state/seed.json web:/var/lib/dolibarr/documents/mailintake-input.json
docker compose exec -T web chown www-data:www-data /var/lib/dolibarr/documents/mailintake-input.json
docker compose exec -T web chmod 600 /var/lib/dolibarr/documents/mailintake-input.json
docker compose exec -T --user www-data web php /checks/mailintake/native.php setup
python3 tests/mailintake/scenarios.py
docker compose exec -T --user www-data web php /checks/mailintake/native.php publish-fixture
docker compose cp web:/var/lib/dolibarr/documents/mailintake-session.json .local/mailintake-session.json
chmod 600 .local/mailintake-session.json
python3 tests/mailintake/browser.py
docker compose exec -T --user www-data web php /checks/mailintake/native.php verify
docker compose down --volumes --remove-orphans
test -z "$(docker ps -aq --filter "label=$intake_selector")"
test -z "$(docker volume ls -q --filter "label=$intake_selector")"
test -z "$(docker network ls -q --filter "label=$intake_selector")"
echo 'PASS complete owned intake project teardown'
