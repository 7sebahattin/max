# MAX Customs Declaration — product-neutral workflow (draft)

## Native relationship
Customs declarations are specialized business objects. Connect them to native Dolibarr customers, sales orders and shipments; do not duplicate invoice/stock/payment tables.

## Data categories
- Declaration reference, customs office, movement/state, shipment and document dates, goods summary, origin/destination, transport mode and reference numbers as applicable.
- Counterparty/contact and driver fields are personal/regulated operational data; fields must be configured and protected per deployment.
- Optional product analysis/laboratory evidence, raw source text, unmatched text and regulated filing evidence are restricted sections.
- Link imported PDF/text to a hash and document revision. Parsing is a draft suggestion, never regulatory truth without review.

## Workflow
`draft` → `reviewed` → `approved` → `closed` or `cancelled`, with allowed guarded corrections and append-only status history. Actual customs declarations externally filed by third-party channels must not be falsely represented as electronically submitted by MAX unless a verified connector exists.

## Permission design
Separate permissions for `view`, `create`, `edit`, `change_status`, `match_order_shipment`, `view_sensitive_contact`, `view_analysis`, `view_source_text`, `prepare_hks_draft` and `admin`.
- Apply denial/redaction **server-side to API, page, export and audit views**, not CSS hiding.
- A general view grant does not imply sensitive fields permission.
- Restricted fields are excluded from update payloads sent by unauthorized roles; reject attempts to change them.
- Audit change actor, time, field name and status; never dump identity numbers or complete document content into logs.

## Relationship with HKS
A declaration may propose a new HKS draft with validated mapped data. Preparing a draft is never approval to send a real HKS SOAP request. Revalidate and explicitly confirm all required external fields before submission.

## Synthetic acceptance tests
- Create draft linked to one native sales order and one partial shipment; same declaration can be reviewed after another shipment is created.
- Upload an unreadable text/PDF: retain original with parse warning, do not invent goods quantities.
- Restricted user's view/export omits sensitive contact and source text, and write API rejects unauthorized modifications.
- Allowed user changes a declaration status: append auditable event.
- Customs → HKS creates a draft only; sending remains explicitly blocked until a separate permitted confirmation.
- Duplicate source parsing does not create duplicate declaration/linked shipment entries.

## Explicit non-goals
No presumed customs-government EDI or e-document integration; no real electronic filing unless formally specified and tested; no historical record import.
