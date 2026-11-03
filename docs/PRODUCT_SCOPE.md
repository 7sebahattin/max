# MAX Product Scope — initial approved baseline

MAX is a modular, brand-configurable ERP platform for independent customer deployments, built on an official tagged Dolibarr release and external modules. Source of product decisions: [Issue #1](https://github.com/7sebahattin/max/issues/1).

## Native first — initial release
- Customer, supplier and contact management.
- Sales and purchase orders.
- Products, warehouses, stock and lots.
- Invoicing, payments, bank accounts and financial records.
- Basic reports, exports and shipment/packing capabilities.

These must be tested in a clean Dolibarr environment *before* implementing any replacement or redundant ledger.

## Approved MAX custom business capabilities — initial release
| Capability | Functional target | Design rule |
| --- | --- | --- |
| Advanced order & document workflow | PO, loading list, price confirmation document intake, revisions, cross-checking, order status, customer-specific output | Use Dolibarr orders/shipments as authoritative commercial records; preserve source-priority and manual override rules where applicable |
| Mail Center | Multiple IMAP/SMTP accounts, translation, approval-before-send, document attachment processing and order associations | Evaluate native Dolibarr mail collector first; implement only gaps |
| Customs declarations | Declarations, input parsing, statuses, document history, shipment linkage and approval steps | Use a separate custom declaration object linked to ERP records |
| HKS integration | Firm settings, reference lists, producer/party data, tags, draft review, controlled submission and result tracking | Optional industry module; never auto-submit or auto-retry ambiguous notifications |
| Reports and Excel | Operational custom exports beyond native reports | Build only requested gaps; no redundant ERP reports |

## Deferred
- Custom vehicle loading forms.
- Broad export document packages not required by the advanced order process.
- Advanced packaging/carton consumption automation.
- Custom pallet UI: test native shipment packaging first and obtain explicit approval for any gap.

## Excluded
- Historical/production data migration.
- Legacy pallet/tare calculations.
- Weighbridge.
- Personnel attendance, NFC or foreman/crew payments.
- Bespoke export costing and profitability calculations.
- Hardcoded customer-specific branding or legal company data.

## Special interpretations
- The approved advanced order workflow includes its necessary customer-specific packing list, commercial invoice, document/email association and price-confirmation reconciliation. That does **not** authorize a broad generic document-suite rewrite or custom stock ledger.
- Financial adjustments must integrate with Dolibarr accounting mechanisms, never silently replace validated invoices or ledger entries.
- No actual HKS integration, email delivery, invoice submission or deployment until independently approved.

## Product constraints
- Mobile usable on common phone widths; large touch targets for custom operations.
- Turkish-ready localization plus structurally translatable UI strings.
- Branding configuration separate from legal company identification.
- One isolated customer installation per deployment initially.
- Transparent upstream Dolibarr licensing and third-party notices.
