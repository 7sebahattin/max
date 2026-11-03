#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
test -n "$COMPOSE_PROJECT_NAME"
test "$BRANDING_VARIANT" = a || test "$BRANDING_VARIANT" = b
python3 - "$COMPOSE_PROJECT_NAME" <<'PYGUARD'
import re, subprocess, sys
project=sys.argv[1]
if not re.fullmatch('max-branding-[a-z0-9][a-z0-9_-]*',project):
    raise SystemExit('Refusing project outside the disposable branding prefix')
for args in (['docker','ps','-aq'],['docker','volume','ls','-q'],['docker','network','ls','-q']):
    result=subprocess.run(args+['--filter','label=com.docker.compose.project='+project],check=True,capture_output=True,text=True)
    if result.stdout.strip():
        raise SystemExit('Refusing existing project resources')
PYGUARD
mkdir -p .local
printf '%s' "$COMPOSE_PROJECT_NAME" > .local/branding-owned
trap 'docker compose down --volumes --remove-orphans' EXIT
bash scripts/maxcore_test.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web php /checks/branding/fixture.php prepare "$BRANDING_VARIANT"
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxcore /checks/branding -name "*.php" -exec php -l {} \;'
docker compose cp web:/var/lib/dolibarr/documents/branding-fixture.jpg .local/branding-fixture.jpg
python3 tests/branding/browser.py
