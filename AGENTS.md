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

## Current milestone
PR-01 is merged and P2 local/CI native functional testing is owner-authorized (Issue #3, PR #4). Continue on feat/p2-native-dolibarr-pilot without asking for P2 approval again. No bespoke MAX modules, live deployment or merge is authorized.

## Foundation checks
Run `python3 scripts/check.py`, Python compilation, shell syntax and Compose validation. Use `scripts/smoke.sh` on a capable local/CI runner. Never mark a skipped or blocked test as passing. Read `upstream.lock.json` and `docs/COMPATIBILITY.md` before changing dependencies. Keep `.local/` ignored; never edit downloaded upstream. Paid extensions require explicit owner approval. PR-01 approval is required before P2; deployment always needs distinct approval.
