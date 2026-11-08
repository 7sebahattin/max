# MAX Reports & Excel — native-first rule set (draft)

## Principles
- Prefer standard Dolibarr dashboards, filtered lists and exports before authoring custom reporting.
- Every view and Excel export enforces the same user/legal-entity permissions as its source rows.
- Exports are read-only: generating a report must not create, modify or validate invoices, payments, shipments or HKS notifications.
- No hardcoded customer logo, company name, bank or address; use centralized brand and legal issuer configuration and preserve source-of-truth information.

## Initial gap candidates
Owner priority (2026-11-06): these are candidates, not an accepted implementation queue. Legacy ASYA Reports-page outputs are last-phase redesign work. Other screens' exports remain eligible after source-screen provenance and native assessment; the candidates below have not been matched to those origins. Mail-related reporting follows the deferred Mail phase. Deferral never waives export authorization, source immutability or formula-injection protection for any export implemented earlier.

1. Advanced Orders: document processing backlog, source conflicts, manually locked fields, PO/LL/PC value comparison and shipment reconciliation.
2. Mail: message intake counts, classified documents, duplicate/skip causes, send approvals/outcome status with no exposed content.
3. Customs: declaration statuses, document completeness, shipment associations and HKS draft readiness.
4. HKS: draft vs confirmed sent vs unknown-result status and restricted audit.
5. Operational consolidated view: authenticated dashboard linking native ERP records to optional MAX modules.

Actual output fields, filters and Excel formats require owner acceptance after P2 standard export assessment.

## Tests
- Export respects per-module restrictions and redacts sensitive fields.
- Native ERP payment and stock totals are read through native supported interfaces, not parallel totals.
- CSV formula injection strings are escaped safely in exported spreadsheets.
- Repeated read/export produces identical totals without changing business records.
- Generated templates use installation-specific legal issuer and separate app branding correctly.
