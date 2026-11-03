#!/usr/bin/env bash
# Actual Dolibarr installation in the isolated disposable local/CI sandbox only.
# Does NOT deploy to staging; NEVER run against a customer or production host.
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ ! -f .env ]]; then
  echo "Missing disposable .env; make one from .env.example and use random local DB credentials." >&2
  exit 2
fi
python3 scripts/check.py
python3 scripts/prepare.py
docker compose config --quiet
trap 'docker compose down' EXIT
docker compose build
docker compose up -d --wait --wait-timeout 180
docker compose exec -T web php /checks/p2_config.php
# step2 is the documented official installer schema/data creation script.
docker compose exec -T -w /opt/dolibarr/htdocs/install web php step2.php set en_US > .local/p2-install-schema.html
# Verify schema before proceeding. A plain exit code/HTTP 200 is not sufficient.
docker compose exec -T web php -r '
$db = new mysqli("db", getenv("MARIADB_USER"), getenv("MARIADB_PASSWORD"), getenv("MARIADB_DATABASE"));
$r = $db->query("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE()");
$n = (int)$r->fetch_assoc()["n"];
if ($n < 50) {fwrite(STDERR, "Schema not installed, tables=".$n."\n");exit(3);}
echo "Official installer schema created: ".$n." tables\n";
'
if [[ -f .local/p2-admin-secret ]]; then
  echo "Refusing to overwrite existing local admin credentials." >&2
  exit 2
fi
admin_pass="$(python3 -c 'import secrets; print(secrets.token_hex(24))')"
printf '%s\n' "$admin_pass" > .local/p2-admin-secret
chmod 600 .local/p2-admin-secret
# Official step5 arguments: versionfrom versionto selectlang action login pass pass_verif lockinstall
docker compose exec -T -w /opt/dolibarr/htdocs/install web php step5.php '' '' en_US set maxadmin "$admin_pass" "$admin_pass" 1 > .local/p2-install-admin.html
unset admin_pass
docker compose exec -T web php /checks/p2_verify.php
docker compose exec -T web php /checks/p2_business.php
# The installer must no longer be available after its lock was created.
curl --fail --silent --show-error http://127.0.0.1:8080/ > .local/p2-home.html
if grep -Eiq 'Fatal error|Uncaught .*Error|Parse error' .local/p2-home.html; then
  echo "Fatal error on application landing page" >&2
  exit 3
fi
echo "P2 installed sandbox PASS; no staging deployment, no real emails or HKS."
echo "Only local demo admin password is stored in ignored .local/p2-admin-secret"
