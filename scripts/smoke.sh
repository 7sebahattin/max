#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/check.py
python3 scripts/prepare.py
docker compose config --quiet
docker compose build
trap 'docker compose down' EXIT
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web php -l /checks/runtime.php
docker compose exec -T web php /checks/runtime.php
curl --fail --silent --show-error http://127.0.0.1:8080/install/check.php > .local/installer-check.html
# HTTP success alone must not conceal a PHP fatal error.
python3 - <<'PYSMOKE'
from pathlib import Path
import re
body = Path('.local/installer-check.html').read_text()
if re.search(r'Fatal error|Uncaught .*Error|Parse error', body, re.I):
    raise SystemExit('Installer fatal error detected')
PYSMOKE
echo 'Local preparation smoke PASS (full ERP installation deferred to P2)'
