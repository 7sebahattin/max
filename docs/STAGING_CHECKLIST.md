# Staging Readiness — max.sacai.tr

**State:** NOT DEPLOYED; DNS, hosting and service readiness not verified by the repo author.

## Infrastructure prerequisites
- [ ] Confirm intended hostname: `max.sacai.tr`.
- [ ] Confirm the authoritative DNS zone and host target; set an A/AAAA/CNAME record using verified server details.
- [ ] Confirm DNS resolution from multiple networks after propagation.
- [ ] Provision a web server or controlled Docker host that can run the chosen Dolibarr release.
- [ ] Select and **verify** official tagged Dolibarr release, PHP/extensions and supported MySQL/MariaDB range.
- [ ] Enable HTTPS with valid server certificate, secure redirects, HSTS as appropriate.
- [ ] Protect staging with explicit access control (VPN or authentication allowlist).
- [ ] Provision a **dedicated empty** database and least-privilege DB user.
- [ ] Place document storage and local secrets outside public web root; avoid publicly accessible setup directories.
- [ ] Configure separate writable app paths, restrictive permissions, uploads and log retention.
- [ ] After installer completes, apply Dolibarr's recommended setup locking controls.
- [ ] Configure cron/scheduled jobs only for approved test tasks; disable outbound integrations by default.
- [ ] Configure automatic backups, encrypted storage, retention and a restore test.
- [ ] Make exception/error reporting private; ensure logs and PRs cannot expose PII/secrets.
- [ ] Pin dependency versions and record upgrade/rollback procedures.
- [ ] Verify monitoring, disk usage and certificate-renewal alerts.

## Functional smoke test with *synthetic* data
- [ ] Login/logout, permissions, Turkish language and mobile screens.
- [ ] Create test customer and supplier.
- [ ] Create product, lot, warehouse, inventory change.
- [ ] Create order, shipment, invoice and demo payment.
- [ ] Check baseline document rendering and account balances.
- [ ] Test native packing, packaging and stock-consumption behavior; record missing requirements.
- [ ] Verify no direct HKS submission, real email sending or unapproved financial action is possible in staging.

## Sign-off
Owner approval before any staging deployment; store evidence in the relevant PR. Staging must never share the legacy application's database or production credentials.

## Factual readiness report — 2026-11-03

No deployment attempted. `max.sacai.tr` is a development/staging target, never an implied production destination. No hosting, DNS-zone, database or SSH credentials were supplied. The managed workspace has restricted outbound networking and does not establish access to this domain; DNS/TLS have not been certified here.

| Requirement | Observed status | Owner action / required verification |
| --- | --- | --- |
| DNS A/AAAA/CNAME | UNVERIFIED; no intended server address known | Identify zone administrator and intended host; inspect authoritative and public resolution; remove stale AAAA if IPv6 unavailable |
| HTTPS | UNVERIFIED; no certificate or server inspected | Issue certificate for exact hostname, verify full chain/renewal and redirect; secure cookies; enable HSTS after HTTPS validation |
| Host / web server | UNPROVISIONED from this project's evidence | Provide isolated staging host, access method and OS; serve only upstream htdocs; deny conf, private storage, dotfiles and installer after setup |
| PHP | Local CLI 8.3.36 verified; staging unknown | Install reviewed PHP 8.3 security patch, required extensions from COMPATIBILITY.md, private error logging, upload/time/memory limits |
| Database | Staging unknown; selected local MariaDB 11.4.13 | Dedicated empty DB and least-privilege runtime user; no public port; separate migration identity, utf8mb4, private transport |
| Storage | No host paths confirmed | Suggested `/srv/max/releases/<id>` read-only code, `/srv/max/shared/documents`, `/srv/max/shared/conf`; secrets/documents outside htdocs; narrowly scoped web-user write permissions |
| Cron | No jobs installed | Enable native scheduled jobs only after review; CLI as restricted service user, locking and logs; no public cron URL/key; all external-effect jobs disabled |
| Backups | None configured by this PR | Encrypted off-host database + documents + config backups with agreed retention; restore rehearsal and recovery objectives before staging sign-off |
| Access / monitoring | Unknown | VPN or access gateway before application login, least-privilege accounts, audit/health/disk/certificate alerts; no default admin credentials |

## Proposed approved-deployment procedure

1. Obtain distinct owner deployment approval after host, access and licensing prerequisites are recorded. Confirm no shared legacy paths/database. Complete P2 pilot and review exact built image digest and dependency security findings.
2. Back up any existing staging DB/documents/config; prove restore into an isolated target. Store secrets in protected host configuration or a secret manager, never in issues or shell logs.
3. Install the locked release separately; mount reviewed MAX external modules only. Configure native company identity, locale and central product branding when implemented. Application runtime cannot modify core or install updates.
4. Use installer only behind restricted access; after setup create `documents/install.lock`, make conf.php read-only, deny `/install` at web server and verify direct requests fail. No production information or real integrations.
5. Verify HTTPS/cookie flags, RBAC, CSRF, private document denial, native synthetic workflows, storage permissions and disabled outgoing transports. Native cron remains off until specific jobs are approved.
6. Record owner sign-off, deployed SHA/image, backup IDs and rollback result in the deployment PR. No automatic deployments are configured by PR-01.

Rollback: stop traffic and scheduled jobs, restore the prior code/image **together with its compatible database, documents and configuration backup**, verify restricted access and synthetic smoke, then reopen. Never run old code against an upgraded schema. For a new empty failed staging install, retain diagnostic evidence and discard only that isolated install after confirming there is no data to keep.
