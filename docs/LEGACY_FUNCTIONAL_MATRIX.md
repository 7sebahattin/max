# Functional gap assessment — preliminary, source-informed

This is a **requirements comparison, not a code migration**. Current behavior was identified in a read-only reference repository. Its actual operating mode and live business data have not been verified on staging.

| Capability | Observed reference behavior | MAX plan |
| --- | --- | --- |
| CRM and vendors | Some generic views are placeholders | Native Dolibarr |
| Sales and purchase orders | Generic ERP functions plus specialized customer-document workflow | Native orders + MAX Orders extension |
| Stock, warehouses, lots | ERP-style data plus materials/physical workflow | Native first; custom material consumption deferred |
| Invoices and payments | Specialized invoice/price comparison and placeholder generic pages | Native invoices/payments, specialized adjustment analysis only as approved |
| Shipment / packing | Legacy pallet/tare, load records and separate customer packing documents | Do **not** port old pallet/tare engine; test native shipment; retain specialized order document requirements |
| Advanced order processing | PO, loading list and price confirmation detection, source priority, revision/conflict handling, manual-lock rules | MAX Orders extension; behavior to be specified by cases/tests |
| Incoming and outgoing mail | Multiple accounts, sync, translate, preview/approve/send, PDF attachment intake | Native mail collector evaluation + MAX Mail for proven gaps |
| Customs declaration | Structured fields, free-text parsing, statuses, approval, document links, HKS bridge | MAX Customs extension |
| HKS | Firm profiles, catalog, reference tags, draft/sent tracking, regulated SOAP submissions | Optional MAX HKS module, mocks first |
| Reports/Excel | Multiple operational exports | Native reports; incremental MAX Reports for approved gaps |
| Personnel attendance and NFC | Specialized workforce subsystem | EXCLUDED |
| Weighbridge | Specialized vehicle weighing screens | EXCLUDED |
| Custom export costing/profit | Specialized costing screens | EXCLUDED |
| Advanced packaging consumption | Business-specific material movements | DEFERRED |

## Constraints discovered
1. Specialized order processing may require keeping document sources separately from native order line records. No duplicated authoritative ERP order/invoice/stock records.
2. Manual edits must not be silently overwritten when a later incoming document conflicts.
3. Duplicate document processing, repeated mail polling or retry must not create duplicated records or sends.
4. Mail-to-customs and customs-to-HKS draft paths depend on central permissions and shared validation rules.
5. External HKS sends are irreversible or financially material; no automatic retry on uncertain outcomes.
6. A long-lived generic customer-configurable product must remove hardcoded assumptions (company/supplier IDs, packing house identity, document prefixes, bank and country data) **from the redesigned behavior**, not blindly copy them.
7. Some reference pages are redirects or placeholders. Do not equate a page filename to a finished feature.

## What still requires a working Dolibarr sandbox
- Exact core overlap with packing/shipments, bank documents, multi-currency, lot tracking and email.
- Installed version's supported extension hooks and specific object APIs.
- Mobile workflows and PDF/document extensibility.
- Licensing/distribution viability for future commercial MAX customer deployments.

## No-data-migration rule
All implementation demos use generated test entities in an empty database. Historical records and documents remain in the legacy system unchanged.
