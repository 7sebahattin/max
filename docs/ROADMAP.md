# MAX Roadmap — approved development sequence

Status: planned. Only PR-01 repository scaffolding is authorized at this time.

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
