# MAX developer setup: Windows 11, WSL2 and Docker Desktop

Scope: [Issue #6](https://github.com/7sebahattin/max/issues/6), Codex B lane `feat/dev-onboarding-and-ci`, based on `main`. This guide prepares a disposable local development environment. It does not deploy MAX, migrate data or authorize sending email/HKS. The four files in this lane are independent of the P2 pilot and functional blueprints; do not check out or edit another team's branch to follow this guide.

## 1. Windows host — PowerShell

Use Windows 11 with virtualization enabled, enough RAM for Docker Desktop (8 GB minimum, 16 GB recommended), and at least 15 GiB free **both** in the repository filesystem and Docker Desktop's Linux disk. Image layering can require more. The doctor checks only repository free space, not the Desktop VM disk.

In an administrator **PowerShell** terminal:

```powershell
wsl --install -d Ubuntu-24.04
wsl --update
wsl --list --verbose
```

Restart Windows if requested. Confirm Ubuntu reports **VERSION 2**; if an existing distribution is version 1, select its actual name when running `wsl --set-version Ubuntu-24.04 2`. These are Windows commands, not Linux commands. Do not reset/unregister an existing WSL distribution containing work.

Install Git, VS Code and Docker Desktop from their official distributions. Check current Docker Desktop licensing eligibility before installation; this guide does not authorize paid software. Configure Desktop for Linux containers and enable Settings → Resources → WSL Integration for the intended Ubuntu distribution. Start Desktop. Do not install a second Docker daemon inside WSL unless intentionally using a separately managed Linux Engine setup.

Install VS Code's WSL extension. Open Ubuntu from Windows Terminal or use `wsl -d Ubuntu-24.04`. Follow the current approved Codex installation/sign-in flow for your organization; never paste tokens into source or issues. An account being signed into Codex does not prove Git can access the private MAX repository.

References: [Microsoft WSL installation](https://learn.microsoft.com/windows/wsl/install), [Docker Desktop WSL backend](https://docs.docker.com/desktop/features/wsl/), [VS Code WSL](https://code.visualstudio.com/docs/remote/wsl).

## 2. Repository tools — Ubuntu/WSL Bash

All remaining commands are **Linux Bash**, not native PowerShell. Check the prompt/terminal profile before copying them.

```bash
sudo apt update
sudo apt install git curl python3 python3-venv ca-certificates
python3 --version
git --version
docker --version
docker compose version
mkdir -p ~/src
cd ~/src
```

Ubuntu 24.04 supplies Python 3.12. Require Python 3.12+ and Compose **v2** (`docker compose`, not legacy `docker-compose`). No host PHP or MariaDB installation is required.

Keep the checkout under the Linux filesystem, such as `~/src/max`. `C:\Users\...` is a Windows path; `/mnt/c/Users/...` is the corresponding WSL mount, with different ACL, file-watching and performance behavior. Prefer Linux storage for MAX bind mounts. Run `code .` from WSL and confirm VS Code shows the intended WSL distribution. Configure the Codex session to use that same checkout/worktree. Separate checkouts/worktrees prevent concurrent teams from changing each other's files.

## 3. Clone the private repository with minimum access

Authenticate Git **inside the shell where Git will run**. Prefer your organization's approved credential manager or a passphrase-protected SSH key registered with GitHub; never disable host-key verification. Confirm GitHub's host key against its official published fingerprints before trusting a first connection. For HTTPS, use a fine-grained, expiring token limited to this repository: contents read for cloning; contents write only when pushing this lane. Updating a workflow may also need the provider's workflow permission. Do not grant organization/admin scopes merely to clone. Do not embed credentials in a clone URL, `.env`, command history, screenshots or review comments.

Choose one authenticated transport; examples contain **no token**:

```bash
# HTTPS using an already configured approved credential manager:
git clone https://github.com/7sebahattin/max.git ~/src/max
# OR SSH after configuring and verifying the registered SSH identity:
# git clone git@github.com:7sebahattin/max.git ~/src/max
cd ~/src/max
git status --short --branch
git fetch origin
git switch main
git pull --ff-only origin main
# Existing Codex B remote branch:
git switch --track origin/feat/dev-onboarding-and-ci
# If it already exists locally instead:
# git switch feat/dev-onboarding-and-ci
```

Do not switch branches with uncommitted work. If a task needs a new branch, create it from reviewed `main` with an agreed name; do not overwrite another team's branch or force-push. Codex A owns `feat/p2-native-dolibarr-pilot`/PR #4 and ChatGPT owns the functional-blueprints branch/PR #8. This lane never modifies their files.

Authentication failures are resolved through repository access/credential-manager settings, not by making the repository public. The doctor deliberately does **not** contact GitHub to verify authorization.

## 4. Read-only environment checks

From the repository root:

```bash
python3 scripts/dev_doctor.py
python3 scripts/dev_doctor.py --json
python3 -m unittest discover -s tests -p test_dev_doctor.py -v
```

You can pass `--repo /absolute/linux/path/to/max` from another directory. Linked worktrees with a `.git` file are supported. `--repo` must identify the actual root, not a nested folder or a different repository.

Exit 0 means no failed checks; warnings still require review. Exit 1 means missing/failed prerequisites (including less than 15 GiB free); argument errors use exit 2. JSON has `schema_version`, `failed`, and checks with `code`, `status` (`pass`/`warn`/`fail`) and a fixed/sanitized message. Versions shown are parsed numbers, never raw subprocess output.

The doctor checks Python, OS, Git, Docker CLI, Compose v2, repository markers/root, local Engine response and repository disk. It only runs version queries, Git root discovery and a formatted local engine-version query with a five-second timeout. It never opens `.env`, credentials, Docker config, customer files or API keys itself, and never prints their contents (Git/Docker may consult their own normal configuration); never lists containers; never builds, starts, stops, resets, logs into a service or scans the network. It ignores Docker context/host/TLS selectors and explicitly checks the local Unix socket (Windows: local named pipe). A remote context cannot redirect its engine query to a server.

Warnings are deliberate: native Windows can run the doctor but MAX Bash setup belongs in WSL2. WSL kernel detection is not proof of WSL version 2. Desktop VM free space, Docker disk allocation, rootless/nonstandard sockets, private GitHub access, package-download/CA access, TCP port availability and Windows-to-WSL browser forwarding are not certified by this tool. A local Docker socket can still belong to a shared machine; never infer authorization to mutate its containers from a successful read-only probe.

Troubleshooting:

- Engine unavailable: start Desktop and verify the selected distribution's WSL integration; confirm the local socket is accessible. Do not apply `chmod 666` to the Docker socket or disable TLS to solve a problem.
- Old Compose/Python: install the supported version in the **same shell** as this checkout.
- Low disk: inspect your own workspace/VM allocation; do not run global Docker prune or delete another project's volumes. A passing filesystem check does not prove the VM has space.
- Windows path/CRLF/executable problems: use the Linux filesystem and `bash scripts/smoke.sh`; do not convert the entire repository or change another lane's files.

## 5. Disposable credentials and local smoke

Use [.env.example](../.env.example) as the template. Generate new **local-only** credentials without printing them; this example refuses to overwrite an existing `.env`:

```bash
python3 - <<'PYENV'
import os
from pathlib import Path
import secrets
body = Path('.env.example').read_text()
for key in ('MARIADB_PASSWORD', 'MARIADB_ROOT_PASSWORD'):
    placeholder = key + '=\n'
    if placeholder not in body:
        raise SystemExit('Template changed; review it before creating local credentials')
    body = body.replace(placeholder, key + '=' + secrets.token_hex(24) + '\n', 1)
fd = os.open('.env', os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
with os.fdopen(fd, 'w') as handle:
    handle.write(body)
print('Disposable .env created; no credentials displayed')
PYENV
git check-ignore .env
```

Never copy existing customer credentials/dumps. Keep `.env` and `.local/` untracked. `.env` is not a GitHub authentication file. WSL permissions and local installer ACLs are not staging permissions.

The doctor does not change the active context for subsequent commands. Explicitly select the local socket and a unique Compose project **in this Bash shell** before executing the existing smoke script:

```bash
unset DOCKER_CONTEXT DOCKER_TLS DOCKER_TLS_VERIFY DOCKER_CERT_PATH
export DOCKER_HOST=unix:///var/run/docker.sock
export COMPOSE_PROJECT_NAME=max-b-local
python3 scripts/check.py
bash scripts/smoke.sh
```

The smoke performs downloads/builds and starts disposable services, unlike the read-only doctor. It is the existing preparation smoke documented in [LOCAL_DEVELOPMENT.md](LOCAL_DEVELOPMENT.md), not proof of P2 business workflows. Respect proxy/CA settings; never disable certificate verification. `prepare.py` stages a trusted CA under ignored `.local/`. No real outgoing SMTP/HKS credentials or cron should be configured.

Port 8080 and the local runtime image tag are shared by the current Compose manifest even with different project names: schedule smoke runs so another lane is not using them, or use a separate machine/daemon. Do not stop another lane's stack to claim the port. Open `http://127.0.0.1:8080` only while your local stack is running; browser forwarding from Windows needs a manual check. The existing smoke stops its own services on exit; a localhost page being absent afterward is expected.

## 6. Stop only your project; preserve data by default

Stay in this checkout and the local-socket environment above. This guard requires the dedicated project name and explicit local endpoint. It does not remove volumes/images:

```bash
if [ "${COMPOSE_PROJECT_NAME:-}" = max-b-local ] &&
   [ "${DOCKER_HOST:-}" = unix:///var/run/docker.sock ] &&
   [ -z "${DOCKER_CONTEXT:-}" ]; then
  docker --host=unix:///var/run/docker.sock compose \
    --project-name max-b-local --file "$PWD/compose.yaml" down
else
  echo 'Refusing: select the dedicated local MAX project/socket first' >&2
fi
```

Never substitute `docker system prune`, `docker volume prune`, `rm -rf`, `down --volumes` or an external engine endpoint. This lane provides **no destructive reset automation**. Stop leaves the named database volume and ignored local files intact. If a later test genuinely requires a fresh database, inspect that particular project's volume labels and ownership and use its reviewed disposable-reset procedure; never delete external volumes, shared/customer databases or another project's data.

## Verification, review and rollback

Reproduce the unit tests above and `python3 scripts/check.py`. The dedicated dev-doctor workflow executes tests and a sanitized diagnostic CLI snapshot on Ubuntu and native Windows with Python 3.12. Missing runner Docker prerequisites are diagnostic findings, not a reason to fake readiness. CI validates JSON/exit-code consistency even when a runner is not a complete workstation.

Windows runner tests verify this Python utility; they do **not** execute `wsl --install`, Docker Desktop setup, VS Code/Codex installation, credential-manager registration, private cloning, bind-mount ACLs or Windows browser forwarding. Those host setup steps remain **NOT RUN** until manually checked on a Windows 11 workstation. Linux CI does not certify them either. CI evidence and the tested commit are linked in the draft PR for Issue #6.

Review the exact four-file diff, command allowlist, local endpoint binding, secret-output suppression and tests. No dependency installation or external side effects occur in the doctor itself. Rollback is to revert this lane's files/commit; doctor runs create no infrastructure state to restore. Smoke services, if started manually, are stopped only with the scoped command above. No merge or deployment is performed by this workflow.
