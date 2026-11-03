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
