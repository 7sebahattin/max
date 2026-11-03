# MAX Roadmap — approved development sequence

Status: planned. Only PR-01 repository scaffolding is authorized at this time.

| Milestone | Goal | Evidence required | Gate |
| --- | --- | --- | --- |
| PR-01 | Clean repository scaffold, architecture/security/branding/scope notes | Reviewed files, no secrets or imported customer data | Draft PR owner review |
| PR-02 | Verify Dolibarr tagged version and reproducible local sandbox | Test container logs, version/extension matrix, demo fixtures | No real staging deploy until approved |
| PR-03 | Standard ERP feature evaluation | Demo: partner, SKU, warehouse/lot, stock movement, sales order, shipment, invoice, bank payment, mobile usability | Gap assessment approved |
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
