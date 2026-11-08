# MAX — Agent Development Contract

Read [GitHub Issue #1](https://github.com/7sebahattin/max/issues/1) and `docs/PRODUCT_SCOPE.md` before any implementation.

## Hard boundaries
- The customer-facing product is **MAX**; the application must not contain past brand names, hardcoded customer or legal entity identifiers, or legacy logos.
- MAX is a **configurable product**, not a single-company customization. Application brand and the operating legal entity are independent configuration domains.
- Keep the Dolibarr upstream framework unmodified. Implement MAX-specific behavior in external Dolibarr modules under `htdocs/custom`; install a tagged upstream release separately.
- Use native Dolibarr commercial objects as the authority for partners, products, warehouses, inventory, orders, invoices, payments and bank records.
- Legacy source is READ-ONLY requirements evidence. No historical data migration; no wholesale copying of source, templates, credentials or customer documents.
- Exclude weighbridge, staff attendance/NFC/foreman, legacy pallet/tare calculation, and bespoke export costing.
- Defer custom vehicle-loading workflows, general non-order export document suites and detailed packaging consumption. First validate native shipment/stock capabilities.
- Stage with synthetic data only. No real HKS notifications, real SMTP sends, live invoicing, deployments, or external side effects without explicit authorization.
- Use one isolated installation per customer in the initial product. Do not assume paid MultiCompany or implement shared multi-tenant data.
- Enforce per-user authorization, CSRF prevention, strict file upload validation, audit without secrets, escaping, staging access control and safe idempotent external calls.
- Preserve Dolibarr/third-party license and trademark attribution. Never claim upstream code is proprietary MAX code.
- Never commit secrets, PII, customer files, API keys, .env, or private configuration.

## Workflow
1. Audit native functionality before proposing any replacement.
2. One incremental feature branch and **draft pull request** per stage.
3. Include acceptance criteria, automated tests, manual validation steps and rollback notes on each PR.
4. Never merge or deploy without explicit owner approval.
5. If command execution or staging access is unavailable, say so and mark tests as **NOT RUN**; do not pretend verification.
6. Record decisions and deviations in `docs/`; keep a traceable GitHub issue/PR link.
7. Distinguish completed work from proposals. Avoid speculative estimates and invented API behavior.

## Current owner-approved work
Issue #36 authorizes the mandatory automatic uppercase input policy and local implementation. Earlier PR-01/P2-only restrictions are historical and superseded by the owner's later module approvals. PR #34 remains a separate draft; do not copy its unmerged Orders 0.3 code into this branch.

## Mandatory business input rule (Issue #36)
- Every new or changed business-input function must classify each field as business text, rich text, technical/opaque, or immutable source before implementation. Add the route/field rule to `modules/maxcore/input-fields.json`, use `MaxInput` at the authoritative server boundary and add bypass/normalization regressions.
- Actual editable business values must uppercase automatically in the browser AND on the server; CSS-only casing does not comply. Use the shared fixed Turkish/Unicode/NFC contract, independent of the user's display language. No per-screen casing code or end-user off switch.
- Passwords, API/session/CSRF tokens, signatures/hashes, email/URI/path values, case-sensitive identifiers/SKUs, machine/schema/enum keys, typed numbers/dates and immutable raw documents/history must remain exact. Protect technical spans inside prose and markup attributes. These integrity exceptions are not discretionary waivers for business names/notes.
- Validate lengths and native uniqueness AFTER conversion; reject overflow/collisions without truncation or silent merging. Normalize imported proposals separately from byte-preserved source evidence.
- Use supported Dolibarr hooks/APIs only. Declare unclassified, unsupported, disabled-Core and unverified native surfaces BLOCKED/NOT RUN; never claim global coverage from pure unit or synthetic-form tests.
- See `docs/INPUT_NORMALIZATION.md`. New function review must include its input classification, server adapter, protected values, role/CSRF/entity behavior and local test evidence.

## Owner priority update — 2026-11-06
Mail UI, translation and all Mail development/bridges are last-phase work, potentially after launch. Revisit only a demonstrated critical dependency; independent Orders/Customs/native flows must not require Mail. Legacy ASYA's Reports-page outputs are also last-phase redesign candidates. Outputs originating in other screens remain requirements candidates, subject to native-first assessment and explicit provenance. Deferral is not completion, cancellation, or waiver of security/release gates. See `docs/ROADMAP.md`.

## Owner publication and CI update — 2026-11-08
At the owner's request, `7sebahattin/max` was changed from private to public to resume standard GitHub-hosted Actions. Standard hosted-runner checks and scoped draft PR publication are authorized. This authorization does not include paid/larger runners, billing changes, merge, deployment, external integrations, historical-data import, real HKS/SMTP or live financial actions. Publish only a reviewed, incremental stage; do not push the separate 65-commit fast-pilot branch wholesale. PR #34 remains separate. The owner requests one isolated pilot only after its readiness gates pass; host plan/access and working DNS are not yet verified. See `docs/FAST_PILOT_RELEASE.md`.

## Foundation checks
Run `python3 scripts/check.py`, Python compilation, shell syntax and Compose validation. Use `scripts/smoke.sh` on a capable local/CI runner. Never mark a skipped or blocked test as passing. Read `upstream.lock.json` and `docs/COMPATIBILITY.md` before changing dependencies. Keep `.local/` ignored; never edit downloaded upstream. Paid extensions require explicit owner approval. PR-01 approval is required before P2; deployment always needs distinct approval.
