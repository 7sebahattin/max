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

## P2 native business pilot and Windows onboarding

P2 local/CI testing is approved; PR-01 approval warnings describe history. Run `bash scripts/p2_install.sh` only with a fresh disposable `max_demo` database/configuration. It invokes official installer stages and native business APIs, not a customer migration. CI additionally runs `tests/p2_browser.py` using pinned Playwright 1.56.0/Chromium. To run that locally, install that Python package and Chromium in a virtual environment, then use `P2_BROWSER=1 bash scripts/p2_install.sh`. Screenshots and measurements remain in ignored `.local/`; no passwords or customer records are published.

Windows checklist (documented, not yet certified on a Windows machine):

1. Install WSL2 with an Ubuntu distribution; enable Docker Desktop's WSL2 integration for that distribution and Linux containers. Run all commands in the WSL Bash shell, not native PowerShell.
2. Clone into the WSL Linux filesystem (for example `~/src/max`) to avoid Windows bind-mount ACL/performance surprises. Check Python 3.12+, curl, Git and Docker Compose v2 from that same shell.
3. Follow the random `.env` credential and trusted-CA preparation instructions above. Ensure localhost port 8080 is free. `docker compose config --quiet` must pass before installation.
4. Verify localhost forwarding from Windows to WSL manually. A passing Linux CI run is not evidence that Docker Desktop sharing, Windows browser routing or ACLs work on that machine.

Reset only this disposable pilot: stop with `docker compose down`; after confirming the database contains synthetic records only, remove its volume using `docker compose down --volumes`. Delete only `.local/conf`, `.local/documents`, `.local/p2-admin-secret` and generated `.local/p2-*` evidence, then rerun prepare/install. Keep the verified upstream cache. Do not run reset on a customer/staging host or reuse its credentials. Installer-generated configuration/admin files are disposable secrets; never attach them or installer HTML to a PR. Local directory ACLs are not staging recommendations.
