# MAX — Cross-module acceptance test matrix (draft)

Tracking: [Issue #7](https://github.com/7sebahattin/max/issues/7) · [Architecture PR #8](https://github.com/7sebahattin/max/pull/8).

This is a **specification of tests to implement**, not a report that those tests have passed. No real business data, government submission, SMTP delivery or staging deployment is authorized.

## Native ERP baseline, proven by Codex P2
The following behavior is **reported and tested** in P2's `tests/p2_business.php` and [native gap matrix in PR #4](https://github.com/7sebahattin/max/pull/4):
- synthetic customers/suppliers, contacts, user rights, stock SKUs, batch/lot and warehouse transfers;
- two-line order, partial shipment 4/10 with remaining 6, single native stock movement;
- draft EUR invoice, synthetic bank reconciliation (not invoice settlement), fixed USD FX draft;
- document PDF and CSV/XLSX output, native Turkish invoice label, render-only mobile smoke;
- disabled mail collector persistence and blocked real sending, with **no tested IMAP protocol/SMTP negotiation**.
- shipment did not implicitly consume a separately stocked carton item without configuration.

**Boundary:** P2 smoke does *not* establish production accounting compliance, actual mobile task usability, full permissions matrix or real e-document integrations. Tests below remain pending until actual MAX module implementations exist.

## Proposed scenarios and ownership

| ID | Module | Priority | Synthetic scenario / setup | Acceptance assertion | Primary owner / phase |
| --- | --- | --- | --- | --- | --- |
| MAX-CORE-01 | Core/branding | P0 | Install MAX in two disposable Dolibarr instances with different brand and legal issuer settings | UI brand changes separately from invoice issuer; no source renames | Codex A / Core |
| MAX-CORE-02 | Core/security | P0 | Read-only role invokes create/approve/export endpoints | Server rejects unauthorized action; sensitive response fields absent | Codex A / Core |
| MAX-ORD-01 | Orders | P0 | Receive LL before PO | One provisional packet; later PO links without duplicate native order | Codex A / Orders |
| MAX-ORD-02 | Orders | P0 | Receive revision 2, then older revision 1 | Revision 1 archived but never overrides revision 2 | Codex A / Orders |
| MAX-ORD-03 | Orders | P0 | User manually locks quantity at 100; document says 120 | Quantity remains 100; conflict event points to source revision | Codex A / Orders |
| MAX-ORD-04 | Orders | P0 | PO=100, LL=98, PC=99 | All three source values persist; effective quantity follows explicitly approved precedence | Codex A / Orders |
| MAX-ORD-05 | Orders | P0 | Same PDF processed twice; attachment arrives from same mailbox in retries | Exactly one logical document/revision and no duplicate native order | Codex A / Orders |
| MAX-ORD-06 | Orders | P1 | 10 units ordered, ship 4 then 6 in two native dispatches | Shipment links correct; no duplicate stock decrement or unrelated carton consumption | Codex A / Orders |
| MAX-MAIL-01 | Mail | P0 | Fake IMAP mailbox with duplicate UID poll events | No duplicated message/attachment business effect | Codex B / Mail |
| MAX-MAIL-02 | Mail | P0 | Filter labeled Orders returns unreadable/unknown PDF | Document type determined from validated content; quarantine, no fabricated order | Codex B / Mail |
| MAX-MAIL-03 | Mail | P0 | User requests SMTP draft then send with fake SMTP timeout after acceptance | `unknown_outcome`; reconciliation required, no automatic retry | Codex B / Mail |
| MAX-MAIL-04 | Mail | P0 | Read-only user invokes approve/send | Rejected at server; no outbound transport call | Codex B / Mail |
| MAX-MAIL-05 | Mail | P1 | Opt-in translation provider returns malformed output | Original preserved, error visible, no auto-send | Codex B / Mail |
| MAX-CUS-01 | Customs | P0 | Create declaration draft linked to one native order + partial shipment | Native records reused, declaration history preserved | Codex B / Customs |
| MAX-CUS-02 | Customs | P0 | API/export accessed by role without sensitive-field rights | Contact/analysis/source fields redacted consistently in API, UI and export | Codex B / Customs |
| MAX-CUS-03 | Customs | P0 | Parse invalid document or unknown source text | Unmatched text/parse warning retained; no invented goods data | Codex B / Customs |
| MAX-HKS-01 | HKS | P0 | Customs creates draft from reviewed declaration | Draft only; SOAP transport call count zero | HKS workstream / Phase 3 |
| MAX-HKS-02 | HKS | P0 | UI and Customs create the same invalid draft input | Same shared validator rejects both paths | HKS workstream / Phase 3 |
| MAX-HKS-03 | HKS | P0 | Two simultaneous send attempts with mocked SOAP | Only one authorized operation recorded; duplicate prevented | HKS workstream / Phase 3 |
| MAX-HKS-04 | HKS | P0 | Mock SOAP timeout with uncertain remote result | `unknown_outcome`, no retry without manual reconciliation | HKS workstream / Phase 3 |
| MAX-RPT-01 | Reports | P1 | Export order document-conflict list as XLSX | Correct rows, no cross-entity leakage or side effects | Reports workstream / Phase 3 |
| MAX-RPT-02 | Reports | P0 | Cell starts with an Excel formula marker | Export neutralizes formula injection in untrusted text | Reports workstream / Phase 3 |
| MAX-INT-01 | Integration | P0 | Fake Mail attachment → Orders PO/LL/PC → Customs → HKS | Correct reference chain; last stage is HKS draft only | Integration / Phase 4 |
| MAX-INT-02 | Integration | P0 | Two separately branded disposable installations run same fixture | No cross-installation records, mailboxes or credentials | Integration / Phase 4 |
| MAX-INT-03 | Integration | P0 | Approval pending when document revision changes | Previously approved snapshot not silently replaced for SMTP/HKS | Integration / Phase 4 |

## Acceptance proof format

For each executed case, record:
- GitHub PR and exact tested commit SHA;
- test ID, positive/negative result and synthetic fixture reference;
- which official Dolibarr class/API was exercised or mocked;
- authorization and external-effect boundary verified;
- runtime version and CI job URL;
- untested/manual coverage distinctly labeled **NOT RUN**.

A PASS without these fields is insufficient for a merge gate.

## Independent merge gates

1. Core identity/config/permissions are reviewed and merged before Orders/Mail/Customs/HKS depend on its approved interfaces.
2. Orders and Mail may implement independently with fake message/document contracts; integration waits for both.
3. Customs may implement native entity linkage independently; its HKS bridge initially only creates drafts, never sends.
4. HKS actual provider integration requires a separately reviewed adapter and explicit live authorization, outside CI.
5. Mobile render screenshots are **not** proof of task usability; record manual phone acceptance before staging.
6. Staging deployment and commercial sale/licensing have separate approval gates.

## Decision backlog for product owner
- How field/quantity source precedence is configured per customer, and what manual edit lock does.
- Which advanced Orders document variants require non-standard customer templates.
- Whether outbound email always needs human approval or is role/template-policy dependent.
- Which customs declaration sections are visible by role.
- Which HKS notification operations are enabled in each industry installation.
- Which reports are contractually required in first release rather than 'nice to have'.

These decisions must be resolved before their corresponding modules are coded; other independent tasks may proceed.
