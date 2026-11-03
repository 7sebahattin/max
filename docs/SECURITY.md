# MAX Security Baseline

## Source control
No passwords, tokens, OAuth credentials, API keys, tax identities, bank details, real mails, real PDF attachments or production dumps in this repo. Use `.env.example` with blanks and a secret manager for actual values. PR/issue comments are **not** a secure channel.

## Access model
Use Dolibarr's supported authenticated user and permission system, including per-module read/write/admin roles. No parallel password tables. Ensure legal entity scope and warehouse scope are enforced on every endpoint, including exports and generated documents.

## External integrations
- Mail: fake SMTP/IMAP fixtures for tests, no live send by default; explicit approve-and-send, atomic idempotency, unknown delivery status handled without automatic resubmission.
- HKS: dry-run/mock SOAP and separate privileged send gate; prevent duplicate regulated filings when timeout outcomes are unknown.
- Translation: opt-in; minimize personal data sent to third-party providers, prevent logging of message content.
- Financial records: single Dolibarr ledger, no duplicate inventory decrement, avoid raw database mutation to standard ERP entities.

## Application controls
CSRF tokens, authorization checks server-side, strict content/filename validation, secure HTML escaping, role-bound access to exports, upload limits, private attachment storage, audit logs that exclude secrets/PII, rate limits for sensitive endpoints.

## Staging
Require HTTPS, staging access restriction, isolated database and file storage, backups and tested rollback. No live external side effects without owner approval. Never assume `max.sacai.tr` is active just because the name was selected.

## Compliance
Upstream code licenses and attribution remain intact; review the actual licensing terms of MAX additions and dependencies before commercial redistribution. Determine controller/processor responsibilities and retention policy for customer data before a live customer install.

## Reporting
Never put real credentials in issue text or test output. Record security findings in private issues with redacted reproduction details.

## PR-01 enforceable local controls

Compose publishes only loopback HTTP through a fixed-target reverse proxy with no source/credentials, publishes no application/database port directly, uses an internal business-runtime network, clears runtime proxy variables and disables PHP sendmail. No mail/HKS/translation credentials or cron services exist. Upstream and MAX modules mount read-only; only local installer configuration and documents are writable. Local permissive directory modes support a disposable installer and must not be used on staging. Build downloads use TLS and a trusted CA secret mount.

Future enablement flags are not sufficient safety barriers: tests must use fake providers and deployment must restrict egress. Staging requires HTTPS-only secure cookies (`session.cookie_secure=1`), native CSRF/authentication checks, explicit module permissions, read-only conf.php and `documents/install.lock` plus web-server installer denial. Source parsing requires size/resource limits, content validation and private storage; generated exports require formula-injection protection and row-level authorization.

Docker internal networks do not publish ports. The local gateway joins a frontend bridge and the private sandbox to expose the UI while keeping PHP business logic/database without direct Internet egress. Only the gateway has frontend connectivity; it is not a general outbound proxy.
