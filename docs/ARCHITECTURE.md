# MAX — Target Architecture (draft ADR-0001)

## Foundational decision
MAX uses a supported, pinned, official **Dolibarr upstream release** as the ERP kernel. Original MAX functionality, subject to applicable upstream licenses, is implemented as independent external modules inside Dolibarr's supported `htdocs/custom` mechanism. No core forks.

Dolibarr **24.0.1** is pinned in `upstream.lock.json`. PHP 8.3.36 and MariaDB 11.4.13 are selected with immutable base-image references. See [compatibility evidence](COMPATIBILITY.md) for verified prerequisites and remaining runtime/functional checks. Staging is not deployed.

## Product layers
1. **ERP kernel** — customers/suppliers, product catalog, lots, warehouse stock, orders, shipments, invoices, payments, bank.
2. **MAX Core** — product branding, menu hierarchy, extension registration, roles, feature flags, localization, audit conventions.
3. **MAX Orders** — specialized incoming business documents, revisions, source priority, manual field protection, document-driven process state; links to native orders and shipments.
4. **MAX Mail** — IMAP ingestion, approved SMTP outgoing, translation abstraction, mail/document association; native collector used when its tested behavior suffices.
5. **MAX Customs** — declaration records, source parsing, status, attachments, order/shipment relationships.
6. **MAX HKS** — optional domain-specific regulated integration; explicit draft/review/send, request idempotency and ambiguous-result state.
7. **MAX Reports** — native report configuration first; authorized custom Excel and operational exports only for documented gaps.

## Data ownership
| Owner | Authoritative record |
| --- | --- |
| Native ERP | Third parties, contacts, products, warehouses, lots, stock movements, orders, shipments, invoices and settlement |
| MAX Orders | Specialized source documents, parsed values, conflict history, manual overrides, processing stage |
| MAX Mail | Mail account configuration, messages, outbound approvals and translation outcomes not sufficiently supported natively |
| MAX Customs | Customs declaration and its workflow states |
| MAX HKS | Notification drafts, evidence, send attempts and outcomes |
| MAX Reports | Views/aggregations only, no parallel business ledger |

Use native Dolibarr object APIs and hooks/trigger mechanisms rather than raw writes to native ERP tables. Extension data should refer to native object IDs and selected legal entity scope.

## Deployment and tenancy
- Each client gets its own independently secured MAX installation and isolated database and document storage.
- Central brand configuration must determine display labels, logo, favicon and browser title; separate legal entity configuration determines invoice issuer, legal name, tax details and official documents.
- A rename of either must not require renaming PHP classes, table names, routes, source files or Git repository.
- Dedicated staging hostname: `max.sacai.tr`; DNS, TLS, host, database, cron and backups unverified.
- Development and production must have separate credentials and storage; staging must be access-restricted.

## Security and irreversible operations
- Integrations disabled by default until environment and owner approvals are supplied.
- HKS draft creation and actual SOAP submission are different privileged actions. Do not assume a timed-out submission failed; investigate before any retry.
- SMTP requires explicit content preview and approval. Deduplicate by durable idempotency key, handle unknown delivery status without unsafe retry.
- Auth/permissions come from Dolibarr; avoid a second independent user table/password system.
- User-generated docs are untrusted and must be validated, scanned when feasible, stored privately and logged without content/PII.
- Production migrations and schema updates require reviewed scripts, backups and rollback plans.

## Open architecture decisions
- Complete runtime smoke and native ERP pilot on the pinned PHP/DB selection.
- License compliance and redistribution terms for MAX extensions and packaged distributions.
- Which native mail collector capabilities to reuse.
- Shipment/packing, packaging-unit, stock-consumption behavior as confirmed by running demo.
- Concrete data/schema designs for advanced document processing and HKS after source behavior audit.

## References
- GitHub Issue #1 (approved scope).
- Dolibarr official developer documentation and distribution; do not infer API or module path availability without confirming against the pinned version.

## Decision record
See [ADR-0001](adr/0001-native-first.md) and [native pilot protocol](NATIVE_PILOT.md).
