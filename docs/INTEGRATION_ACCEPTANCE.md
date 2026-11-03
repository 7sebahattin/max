# Combined MAX integration acceptance — Issue #15

This lane starts from merged main commit `059315f42a858510d507c1a78c9b8ee3f129698c` (PRs #9, #11, #8, #4 and #13). Only this document, the new integration workflow and tests/integration are owned by this task. Approved commercial/module/audit/doctor code and Dolibarr core are unchanged. [Task](https://github.com/7sebahattin/max/issues/15).

## Reproduction and isolation

The MAX integration regression workflow runs on every pull request and main push, and supports manual dispatch. All jobs explicitly check out the same immutable PR head or push SHA, rather than mixing old feature heads or a moving main ref. Pinning the PR head means later base changes require a fresh main-based revision/run before promotion.

Two native matrix entries run on different fresh Ubuntu VMs. Each invokes the existing pilot unchanged: `P2_BROWSER=1 bash scripts/p2_install.sh` or `bash scripts/maxcore_test.sh`. They use the same committed Compose/source/lock/module tree, but separate generated .env files, config/documents directories, database volumes, networks and localhost:8080 sockets. This proves both pilots on one combined source baseline; it does not test shared-database simultaneous P2/MAX Core operation.

The CI-only adapter requires a GitHub runner identity and a fresh checkout, pins Docker to the local Unix socket, discards inherited context/TLS/Compose/DB selectors, and assigns a project name containing run ID, attempt and pilot. It refuses existing labeled containers/volumes/networks. It generates distinct random demo DB credentials with mode 0600 and verifies resolved Compose in memory: demo database, project-owned volume/networks, internal db/web sandbox, no db/web published ports, loopback gateway only and disabled runtime proxies. Builds/downloads need official repositories; runtime db/web have no direct Internet network. No production secrets, GitHub environment, OIDC, registry login or deployment credentials are requested. Checkout credentials are not persisted; token permission is contents:read; actions are commit-pinned.

On completion or a normal test failure, the adapter removes only its validated project's containers/networks/volumes and verifies no labeled resources remain. Cleanup failure is a failed test. It does not globally prune Docker. Provider cancellation/VM loss may interrupt Python finally; GitHub's disposable VM teardown remains the final boundary. Run this wrapper only in disposable CI; for workstation instructions use the existing local development documents and independent fresh checkouts.

## Automated acceptance

| Area | Required evidence |
| --- | --- |
| Pinned install | Verified archive checksum; actual official Dolibarr 24.0.1 installer and native schema/admin checks in both independent contexts |
| P2 native commercial | Existing CRM/product/stock/lot/order/shipment assertions, unissued draft invoices and currency conversion, separate fictional bank receipt/reconciliation, native exports/documents, role negatives and captured fake-mail tests; 15 authenticated native page/width renders |
| MAX Core | Actual native install/enable/disable/re-enable, roles and external-user rejection, GET/POST 403 plus protected-body non-disclosure, native invalid-CSRF rejection, persistence and unchanged native legal/favicon/finance state |
| Turkish/mobile | MAX Turkish content and menu; home/settings/admin at 360/390/768px: nine strict full-document overflow checks, keyboard edit/Tab/Enter save, screenshot/measurement completeness |
| Source safety | Existing adversarial source-audit tests and strict real Git-index audit on the combined integration revision; any nonzero audit exit fails, including warnings |
| Developer tooling | Existing doctor regression tests on Ubuntu and Windows; portable tests are not proof of a ready Windows/WSL2/Docker Desktop workstation |
| Repository | Policy checks, Python compilation, module static invariants and shell syntax; native adapter also requires tracked files unchanged after pilots |
| Harness | Adversarial tests for remote selectors, public ports/egress, shared resources, missing/duplicate/overflow evidence, pilot/evidence/cleanup failures and refusal to adopt existing configuration |
| Aggregate | Both portable OS entries and both native pilots must succeed; failed/cancelled/skipped matrices cannot yield combined acceptance |

The new adapter requires complete evidence after a successful pilot, and writes an allowlisted JSON record with exact checkout/upstream SHAs, project/run identity, pilot exit, measurements summary and cleanup status. Failures stay failures; no continue-on-error or warning conversion. Existing P2 browser assertions validate render/authentication, not zero horizontal overflow: numeric overflows are retained as explicit native P2 findings for separate UX acceptance. MAX Core's stricter overflow assertions stay mandatory.

Artifacts retain only explicitly named synthetic PNGs, browser measurement JSON and integration report JSON for seven days. Never upload .env, raw resolved Compose, installer HTML, account JSON, native config, passwords, database dumps, generated PDFs or the whole .local directory. Test logs and workflow summaries remain the durable evidence after artifact expiry; rerun to regenerate screenshots.

## Evidence record

Implementation CI has not yet run at the time this initial document is committed. This is **NOT RUN**, not PASS. Exact implementation/run/result evidence will be added below after execution and recorded in the draft PR and Issue #15. The managed local execution environment failed to start; local runtime remains NOT RUN. No old feature-branch CI result is used as combined-main proof.

## Remaining manual/product acceptance

- Finance remains intentionally draft-only: no issued/validated invoice, invoice payment/settlement or regulated accounting submission. A fictional bank receipt is not invoice settlement.
- Native mail send testing uses a local fake capture transport; no real SMTP provider/account delivery, IMAP multi-account collection, translation provider, bounce/retry/idempotency or Mail Center end-to-end certification. HKS/SOAP and regulated submissions are not exercised.
- P2 native mobile screenshots/render checks require human review of task completion, tables and overflow. MAX Core Chromium keyboard flows do not emulate a physical phone software keyboard or certify accessibility, other browsers or all menu/module combinations.
- Windows CI covers Python tooling; owner/developer must validate WSL2, Desktop integration, CA/proxy, bind-mount permissions, Windows browser forwarding and physical-device usability manually.
- Separate fresh installations do not establish cross-module transactions in one populated database, production readiness, upgrade/backup/restore, load/security certification, commercial licensing or global branding (Issue #14).
- No owner production access, DNS/TLS, hosting or staging configuration is assumed.

Review the same-SHA jobs, safe evidence allowlist, cleanup and remaining acceptance before promotion. A passing integration run does not authorize deployment. Keep the PR draft; no merge or live/staging deployment. Rollback is a revert of these new files only; disposable test databases are removed, with no customer data to migrate or restore.
