# MAX Advanced Orders & Documents — product-neutral functional specification (draft)

**Scope:** reusable advanced order-document processing, not a copy of any customer's order page, logos, recipient identifiers or historical files. Based on read-only legacy behavioral analysis; implementation must be a fresh supported Dolibarr extension. This module complements, not replaces, native sales orders and shipments.

## Native boundary
Dolibarr owns customer, product catalog, order and order-line, shipment, invoice and financial records. The specialized MAX document packet may exist before a native sales order, and must link to one only after deterministic matching or human confirmation. Zero-to-many shipments are allowed.

## Documents
- PO: buyer's purchase order.
- LL: loading/listing document (shipment quantities/status).
- PC: price confirmation/cross-check document.
- PKL: customer-specific packing list where actually needed by the approved advanced order workflow.
- Commercial invoice: prefer native validated financial records and document templates; custom export presentation must not silently alter accounting totals.

Classify a document from validated **content**, not just filename or mailbox/filter button. Unknown/invalid attachments remain in a visible quarantine/needs-review state.

## Core behavior to preserve as a generalized requirement
1. Arrival order must not dictate truth: LL or PC may arrive before PO; store an unmatched or provisional packet pending correct linking, and support explicit confirmation.
2. A newer revision of a type supersedes its older version for field application, while preserving all revisions and audit history. An older late-arriving version is archived and **must not overwrite** newer facts.
3. Manual edits create field-level lock/provenance. Later extracted values **never overwrite locked fields**; raise a conflict for human review.
4. Empty source fields do not clear existing data. Deleting a document does not cascade-delete a real Dolibarr order/shipment.
5. Quantity facts stay separate by source (PO quantity, LL quantity, PC quantity). An *effective* quantity may follow an approved precedence rule; source facts must not disappear.
6. Differences in product variant/caliber/packaging or line splitting create a reviewable discrepancy, not a silent merge.
7. Idempotent reprocessing: same document version/content may be retried without duplicating order lines, events, approvals or files.
8. Reference-based matching must use normalized customer/supplier + order reference and contextual evidence; ambiguous matches require human confirmation.
9. Conflicts record old/new facts, source document version, timestamp and resolution actor; no payload or sensitive identifying information in audit logs.
10. Customer-specific templates/configuration must be data-driven and opt-in per deployment; never embed customer brand or address in source.

## Proposed document states
`received` → `classified` → `matched` → `parsed` → `applied` / `needs_review` / `rejected`. Distinguish document processing status from native ERP order fulfillment status.

## Pilot acceptance cases (all synthetic)
- LL first → provisional packet; PO later → one native order link with no duplicate packet.
- Rev-2 PO then late Rev-1 PO → Rev-2 wins; Rev-1 retained.
- Field manually locked at 100 units; new source says 120 → remains 100, visible conflict.
- PO 100 / LL 98 / PC 99 → three source values retained; effective quantity and any warning follow documented user-approved rule.
- Same attachment processed twice by mail polling → one logical document revision and one set of extracted facts.
- Incomplete scanned PDF → no fabricated fields and no automatic downstream workflow approval.
- One order with two partial Dolibarr shipments → correct linked status and quantity sum, no duplicate inventory decrement.
- Customer-specific PKL required for one deployment; all other customers use native shipment output by default.

## Decisions before implementation
- Approve effective-source priority by field/quantity; legacy `manual > PC > LL > PO` is **observed reference behavior**, not automatically a universal commercial policy.
- Decide when specialized packet becomes a native Dolibarr order and whether user approval is mandatory.
- Define how external commercial invoice presentation relates to official posted invoice and numbering.
- Confirm standard Dolibarr shipment/pallet capability in P2 before custom packaging changes.
