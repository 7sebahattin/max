# MAX shared interface contracts (draft)

These requirements guide independent implementation branches. They are **not** a claim that the corresponding Dolibarr APIs or MAX modules are implemented. Exact Dolibarr 24.0.1 class/hook availability must be verified against the pinned source and P2 pilot.

## Record ownership
- Dolibarr owns third parties, contacts, products, warehouses, lots, stock movements, sales/purchase orders, shipments, invoices, payments and banks.
- MAX Orders owns specialized incoming document records, parsed facts, source priority, field locks, conflicts and business-workflow history.
- MAX Mail owns only additional mailbox/message/attachment processing not already supported by the native collector.
- MAX Customs owns declaration-specific facts, review and status history.
- MAX HKS owns optional regulated draft/submission records and remote-result evidence.
- MAX Reports reads authorized data; no parallel stock or finance ledger.

## Proposed integration references
Use opaque module-owned IDs for MAX-specific entities and native Dolibarr **integer row IDs** as foreign references only once resolved in the selected customer installation. Do not assume imported order IDs or customer numbers from the old product.

| Link | Cardinality / meaning |
| --- | --- |
| MAX document → native order | many-to-one or unresolved; may originate before the order exists |
| MAX order packet → native shipment | zero-to-many; partial shipments are valid |
| MAX mail attachment → MAX document | zero-to-many links; content-hash and mailbox/message keys prevent duplicate processing |
| MAX customs declaration → native shipment/order | explicit relation with reviewable evidence |
| MAX customs declaration → HKS draft | optional, zero-to-many, **never direct send** |
| HKS send attempt → HKS draft | append-only attempt/history; unknown outcome remains uncertain until reconciled |

Implementations must use a stable namespaced API/model and per-installation scope. Do not invent Dolibarr core columns; use supported object APIs/hook paths and separate custom tables/extra fields.

## Generic state and event contract
Proposed common event envelope (conceptual; actual implementation contract requires code review):

```json
{
  "event_type": "max.orders.document.processed",
  "event_version": 1,
  "resource_type": "document",
  "resource_id": "opaque-local-id",
  "erp_order_id": null,
  "actor_id": "erp-user-or-system",
  "occurred_at_utc": "ISO-8601",
  "correlation_id": "stable-operation-id",
  "idempotency_key": "stable-message-or-document-key"
}
```

- Events must be idempotent and carry **no credentials, file contents, tax IDs, personal data, or sensitive mail text**.
- Persist command outcome and dedupe metadata; do not assume exactly-once delivery.
- Human-confirmed actions record actor, scope, approval and result without sensitive payload logging.
- Use versioned event names and allow older consumers to ignore unknown optional fields.

## User and company scope
- One isolated MAX installation per customer initially.
- Dolibarr native session/authentication and permissions are authoritative; no second login or password table.
- Different roles for module view, create/edit, approve, send and admin. Permissions must be checked server-side on every API/export.
- Brand display name/logo are separate configuration from invoice issuer/legal entity; changing one must not silently mutate the other.
- No prior customer names or fixed company identities in source.

## Document security
- Files stored outside public web root via supported Dolibarr document mechanisms.
- Validate MIME, signature/extension, max size, type-specific parse limits and filename; never execute uploaded files.
- Store immutable revision/content hash and restricted download rules, with provenance to source mail/user.
- Parser returns **untrusted extracted facts**. It cannot automatically overwrite protected values or authorize a regulated external submission.

## External effect boundary
- Incoming IMAP polling: idempotent intake.
- Outgoing SMTP: draft → review → explicit approved send; uncertain transport result is not a normal failure eligible for blind retry.
- HKS remote SOAP: validated draft → preview → explicit authorized send; persist submission intent and handle uncertain outcomes before retry.
- Finance/stock actions: operate through native Dolibarr object methods; no unreviewed direct SQL updates.

## Versioning/testing gate
For each interface write a schema/example fixture, success and conflict tests, role/tenant isolation test and safe retries. Proposed field names in these documents are descriptive; not frozen SQL or REST API names until the MAX Core contract PR is approved.
