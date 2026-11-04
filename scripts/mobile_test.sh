#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export DOCKER_HOST=unix:///var/run/docker.sock
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
test -n "$COMPOSE_PROJECT_NAME"
python3 - "$COMPOSE_PROJECT_NAME" <<'PYGUARD'
import re, subprocess, sys
project=sys.argv[1]
if not re.fullmatch('max-mobile-[a-z0-9][a-z0-9_-]*',project):
    raise SystemExit('Refusing project outside the disposable mobile prefix')
for args in (['docker','ps','-aq'],['docker','volume','ls','-q'],['docker','network','ls','-q']):
    result=subprocess.run(args+['--filter','label=com.docker.compose.project='+project],check=True,capture_output=True,text=True)
    if result.stdout.strip():
        raise SystemExit('Refusing existing project resources')
PYGUARD
mkdir -p .local
printf '%s' "$COMPOSE_PROJECT_NAME" > .local/mobile-owned
trap 'docker compose down --volumes --remove-orphans' EXIT
P2_BROWSER=1 bash scripts/p2_install.sh
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxmobile /checks/mobile -name "*.php" -exec php -l {} \;'
docker compose exec -T web php /checks/mobile/runtime.php prepare
docker compose cp web:/var/lib/dolibarr/documents/mobile-accounts.json .local/mobile-accounts.json
chmod 600 .local/mobile-accounts.json
python3 tests/mobile/browser.py
docker compose exec -T web php /checks/mobile/runtime.php verify
