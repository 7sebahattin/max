# Local preparation (PR-01)

Requires Python 3.12+, curl, Docker Engine with BuildKit and Compose v2, network access to official GitHub, Docker Hub and Debian repositories, and sufficient free disk (recommend 15 GB, especially with the vfs driver). No host PHP or database is required. This stack is disposable local scaffolding, not a production deployment.

From the repository root:

```sh
cp .env.example .env
chmod 600 .env
# Set MARIADB_PASSWORD and MARIADB_ROOT_PASSWORD to distinct random local values.
# Example generation: python3 -c 'import secrets; print(secrets.token_hex(24))'
python3 scripts/prepare.py
python3 scripts/check.py
docker compose config --quiet
docker compose build
docker compose up -d --wait
docker compose exec -T web php /checks/runtime.php
curl --fail http://127.0.0.1:8080/install/check.php
```

Export `MAX_CA_BUNDLE` before running prepare.py to select a trusted CA bundle (default `/etc/ssl/certs/ca-certificates.crt`). The script stages this public trust material under ignored `.local/build-ca.pem` for a BuildKit secret mount, avoiding Compose/Bake external-filesystem restrictions. It is never copied into image layers. In a managed proxy environment keep injected proxy settings, use its trusted bundle, and follow its Docker CA guidance. Never disable TLS checks. Compose clears runtime proxy variables because the internal sandbox must not reach external providers. Build network access is separate from the runtime network.

The verified archive is extracted under ignored `.local/upstream`; it is never committed. Do not edit that tree. To verify a fresh source tree, stop the stack, remove only `.local/upstream` and rerun prepare. A checksum match covers the downloaded archive, not subsequent manual edits. The app mounts upstream read-only, overlays isolated writable configuration and document directories, and mounts MAX module source read-only under `htdocs/custom`. There are no executable modules yet.

Open [local installer](http://127.0.0.1:8080/install/check.php). PR-01 stops at preparation and the prerequisites page; complete the native installation only in the approved P2 pilot. Then use database host `db`, database/user from `.env`, document directory `/var/lib/dolibarr/documents`, and local URL `http://localhost:8080`. Installer needs the writable local configuration overlay. After installation, lock setup with `/var/lib/dolibarr/documents/install.lock` and make `conf.php` read-only. Do not reuse the local broad directory permissions on staging.

The database and application are not published directly. A fixed-target reverse-proxy gateway binds 127.0.0.1:8080 and reaches only the application on the private network. The gateway has no application source or credentials and forward-proxy mode is disabled. The internal network and empty proxy environment block SMTP, SOAP and translation egress; PHP `mail()` is also disabled. No cron, email capture service, real credentials or live integrations are started. `MAX_MAIL_REAL_SEND`/`MAX_HKS_REAL_SEND` are future application policy, not security controls provided by unimplemented code.

Stop without deleting data: `docker compose down`. Reset only disposable local data: after confirming there is nothing to keep, run `docker compose down --volumes`, remove `.local/conf` and `.local/documents`, then rerun prepare. These commands never target any external database. Do not use the reset procedure on a customer/staging host.

A full run is `scripts/smoke.sh`; it starts only this local Compose stack and always stops its containers afterward. Database volume survives. See [acceptance](ACCEPTANCE.md) and [progress](PROGRESS.md) for actual results and limitations.

Docker internal networks do not publish ports. The local gateway joins a frontend bridge and the private sandbox to expose the UI while keeping PHP business logic/database without direct Internet egress. Only the gateway has frontend connectivity; it is not a general outbound proxy.
