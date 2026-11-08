# MAX Roadmap — approved development sequence

## Current execution order — owner updates through 8 November 2026

1. **Mandatory input integrity:** seven-profile CSV/XLSX insert/strict_line HTTP acceptance is verified; see [INPUT_INVOICE_HTTP.md](INPUT_INVOICE_HTTP.md). CRM notes/town and request-bound email preservation are recorded in [INPUT_CRM_HTTP.md](INPUT_CRM_HTTP.md); native Contacts entity isolation remains BLOCKED. Warehouse web/API metadata acceptance is recorded in [INPUT_WAREHOUSE_HTTP.md](INPUT_WAREHOUSE_HTTP.md). The native order notes and Incoterms location stage is [draft PR #38](https://github.com/7sebahattin/max/pull/38), stacked on input-policy PR #37; its real native HTTP/browser acceptance is wired into the shared-installation workflow. Next: invoice/shipment write surfaces and order web-line/rich editor/special-action gaps; update/bulk/ORM/cron and configuration limits remain separate.
2. **Orders and native commercial flow:** when quantities conflict, show the discrepancy and require the user to choose before finalizing; preserve the source values and choice history. Verify manual document/revision/conflict handling and the 4+6 partial-shipment scenario. Reconcile PR #34 separately, without copying unmerged code. Keep manual intake usable without Mail.
3. **Customs for the initial release:** strengthen manual/native order-shipment links, typed field contracts, document validation and privacy/release gates. HKS draft/validator/mock work is deferred to a later phase, outside the first release. No live submission.
4. **Eligible operational outputs:** inventory reports from screens outside legacy Reports, record their provenance and exact acceptance fields, assess native filtered lists/exports first, implement only proven gaps. Do not reproduce the faulty legacy Reports page.
5. **Release acceptance:** same-HEAD shared 63 regressions on a Docker-capable environment, two-install isolation, mobile/accessibility, permission/security review, backup/restore rehearsal and staging preparation. Standard hosted Actions and scoped draft PR publication are authorized; deployment remains gated on verified host readiness and separate final owner approval.
6. **Final phase, possibly after launch:** Mail UI, translation, SMTP/outbox/approval, provider acceptance, Mail bridges and related reports; legacy Reports-page redesign. Deferred work is neither complete nor cancelled. It must not block independent earlier phases. Reopen priority only with a demonstrated critical dependency, impact and minimum required change recorded for owner review.

Initial-release dependencies: native object integrity → Orders → Customs. HKS is a later-phase dependency; Mail is an optional future intake channel for this sequence. Reports consume authoritative native/module data and do not create a second ledger. Candidate outputs from other legacy screens require a read-only inventory before implementation; current repository evidence does not prove their exact source or correctness.

Progress uses the unchanged 25-case denominator in [PROJECT_STATUS.md](PROJECT_STATUS.md): 28% evidenced development sub-scope, 72% open/partial. Moving items later does not improve completion percentage. The old matrix includes Mail-dependent integration cases; their final-phase dependency is recorded in [PROJECT_STATUS.json](PROJECT_STATUS.json), while independent subflows proceed above. This roadmap sets priority, not a launch date or a claim that every old target is a mandatory launch prerequisite.

## Historical baseline

Current owner-approved work is recorded in AGENTS.md. The original PR-01-only state below is historical, not the current authorization boundary; Mail/legacy Reports ordering below is superseded by the sequence above.

| Milestone | Goal | Evidence required | Gate |
| --- | --- | --- | --- |
| PR-01 | P0/P1 docs, release/runtime pins, safe local preparation and CI | Source audit, reproducible preparation checks, no secrets/data import | Draft PR architecture/code review; stop before P2 |
| PR-02 | P2 clean native Dolibarr pilot using PR-01 pins/scaffolding | Full installation, demo fixtures, native/custom disposition and mobile findings | Begin only after PR-01 approval |
| PR-03 | Close native pilot gaps and approve extension contracts | Role matrix, object relationships, document hooks and any unresolved native scenarios | Gap assessment approved |
| PR-04 | MAX Core module | One Dolibarr login, branded menu and configuration, separate legal entity fields, RBAC, localization | Automated and manual validation |
| PR-05 | Mail integration foundation | Multi-account read tests, safe send approval, fake SMTP, mock translations | No live email |
| PR-06 | Advanced Orders/Documents | PO/LL/PC parsing, version source priority, exception handling, ERP order relationship, custom outputs | Representative synthetic document fixtures |
| PR-07 | Customs declarations | Parsing, statuses, documents, ERP links, permissions | Regression and security tests |
| PR-08 | Optional HKS integration | Catalog, drafts, reference records, approval, SOAP mock, ambiguous outcomes | No live notification; explicit go-live approval |
| PR-09 | Module bridges | Email -> orders; email -> declarations; orders -> declarations; declarations -> HKS draft | Cross-module end-to-end fake tests |
| PR-10 | Custom reports / Excel / mobile hardening | Exact requested exports, role restrictions, smartphone tests | Acceptance review |
| PR-11 | Staging deployment | DNS/TLS, security, backups, secrets, no real notifications, safe install | Owner approval and rollback rehearsal |

## Delivery policy
- Keep code changes focused and reversible.
- No speculative implementation before the prior functional test.
- Native ERP objects are authoritative for stock and money.
- Standard Dolibarr packing/shipping must be evaluated before requesting custom pallet UI.
- General load-sheet and packaging consumption remain deferred.
- Staffing/NFC, weighbridge, old tare and custom export costing remain out of scope.

## Handoff if cloud tool is unavailable
A GitHub-connected reviewer may prepare documentation/PR structure without cloud shell. Mark executable checks as **NOT RUN** and require an actual reproducible environment before approving runtime claims.

## Owner-only prerequisites
- Establish DNS for `max.sacai.tr`, using a host-selected A/AAAA/CNAME; do not invent IP addresses.
- Provision compatible hosting, HTTPS and an isolated database.
- Decide who can access restricted staging.
- Provide deployment credentials via secure secret management, not in GitHub issues or source code.
