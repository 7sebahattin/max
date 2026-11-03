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

## Read-only audit evidence, 2026-11-03

Reference snapshot: commit `ac1d55f400a2ef106ff239e1242c83ab63db76c0` in the owner-designated legacy repository. Only selected source, tests and documentation were read through GitHub. No legacy code was executed, downloaded into MAX, modified or reused. The behaviors below are discovery inputs; future implementations need approved synthetic acceptance cases.

| Evidence path in reference snapshot | Behavior extracted | Disposition and MAX acceptance case |
| --- | --- | --- |
| `config/siparis_eslestir.php`, `scripts/siparis_core_smoke.php` | Document type detected from content; LL/PC can arrive before PO; older revisions stay linked but inactive; blank values do not erase data; manual edits lock fields; repeated processing is idempotent | Rebuild provenance/conflict extension around native orders/shipments. Reprocess a file twice, import older/newer revisions and preserve a manual value with an explicit conflict |
| `config/siparis_eslestir.php` | Source priority is field-specific: loading date/place LL over PO, unloading date PC over LL, price from PC; quantity precedence manual then PC then LL then PO with separate source evidence | Make mappings configurable and tested; never generalize one priority order to every field or copy the legacy quantity engine |
| `scripts/siparis_pdf_smoke.php`, `config/siparis_beyan.php` | Multi-page/multi-shipment parsing, payment-advice totals/currencies, declaration association and removal | Extend only parsing/matching gaps; native financial settlement remains authoritative. Test ambiguous matching and reject inconsistent totals |
| `config/mail_sync.php` | Per-account/folder cursors, UIDVALIDITY rollover preserves history, duplicate handling must distinguish legitimate repeated Message-IDs | Native EmailCollector evaluation first; custom sync only for gaps. Test rollover, concurrent polls and two distinct messages sharing Message-ID |
| `config/mail_translate.php` | Provider interface and disabled provider; provider-specific size limits | Opt-in translation abstraction with fake providers; disclose/minimize sent content, no paid/live provider in development |
| `config/mail_outbox.php`, `scripts/mail_outbox_review_smoke.php` | Preview-content hash, approval, atomic send ownership, account identity changes invalidate approval; dedupe survives unknown/sent results; confirmed rejection differs from uncertain delivery | Rebuild missing controlled-outbox behavior; concurrent approval sends once; edited content requires new preview; timeout remains blocked pending reconciliation |
| `beyan_parse.php`, `config/siparis_beyan.php` | POST/CSRF/permission gate; Turkish numbers and labeled/free-text extraction; unmatched lines returned; order/shipment declaration links | MAX Customs object with explicit validation and approval, source evidence and unresolved fields; never silently approve parsed text |
| `scripts/hks_belirsiz_gonderim_smoke.php` | Firm-isolated uncertainty marker; normalized reference matching; audit redaction; human reconciliation before retry | Optional HKS only. Persist operation identity and unresolved outcome until reconciled. Do not copy legacy time-based marker expiry as permission to resend |
| `config/xlsx_export.php`, `docs/EXCEL_EXPORT_ANALIZ.md` | Genuine XLSX, explicit text cells to prevent formulas, numeric/date types, currency separation, cell-count limits, filtered exports and export permission/audit | Native export first; custom gap reports must test formula injection, scoped rows, totals, types and memory limit. No import of old templates or reports for excluded modules |

Source reference root: [read-only snapshot](https://github.com/7sebahattin/Yukleme_plani/tree/ac1d55f400a2ef106ff239e1242c83ab63db76c0). Native source presence is recorded in [pilot protocol](NATIVE_PILOT.md); actual behavior overlap remains a P2 gate. Pallet/tare, personnel, weighbridge and custom costing behavior is rejected regardless of legacy availability. Vehicle loading, general export packages and advanced packaging consumption remain deferred.

Additional inspected evidence: `config/beyan_permissions.php` and `scripts/beyan_data_smoke.php` separate create/edit/status/match/notify permissions, protect restricted fields and ensure a status update does not overwrite concurrently edited vehicle/quantity fields. MAX must use scoped native permissions, validate transitions and record approval history rather than copy permissive legacy transition rules. `config/siparis_cari.php`, `config/siparis_invoice.php` and `config/siparis_pkl.php` show unit/currency-sensitive price comparison and validated document drafts; rebuild only approved reconciliation/template gaps using native invoice calculations and legal identity, without old defaults, numbering or templates. `halkayit/hks_soap.php` confirms separate service contracts, XML escaping/redaction and a distinction between an envelope rejection and a potentially accepted notification. Verify current official HKS contracts with mocks in its later phase; source discovery is no authorization to call those endpoints.
