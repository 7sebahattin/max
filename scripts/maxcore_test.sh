#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/prepare.py
trap 'docker compose down' EXIT
docker compose build
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web sh -c 'find /opt/dolibarr/htdocs/custom/maxcore /checks/maxcore -name "*.php" -exec php -l {} \;'
docker compose exec -T web php /checks/maxcore/configure.php
docker compose exec -T -w /opt/dolibarr/htdocs/install web php step2.php set en_US > .local/maxcore-schema.html
module_admin_pass="$(python3 -c 'import secrets; print(secrets.token_hex(24))')"
printf '%s' "$module_admin_pass" > .local/maxcore-admin
chmod 600 .local/maxcore-admin
docker compose exec -T -w /opt/dolibarr/htdocs/install web php step5.php '' '' en_US set maxcore-test "$module_admin_pass" "$module_admin_pass" 1 > .local/maxcore-admin.html
unset module_admin_pass
docker compose exec -T web php /checks/maxcore/runtime.php
docker compose cp web:/var/lib/dolibarr/documents/maxcore-accounts.json .local/maxcore-accounts.json
chmod 600 .local/maxcore-accounts.json
python3 tests/maxcore/browser.py
docker compose exec -T web php /checks/maxcore/verify.php
