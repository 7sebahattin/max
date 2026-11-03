# P2 native workflow evidence and disposition

Owner authorized P2; [Issue #3](https://github.com/7sebahattin/max/issues/3), existing draft [PR #4](https://github.com/7sebahattin/max/pull/4). No deployment, merge or bespoke MAX modules. Original installation checkpoint: [run 41617811791](https://github.com/7sebahattin/max/actions/runs/41617811791), official 24.0.1 installer, 275 tables, demo administrator and install.lock.

Reproduce with a fresh isolated stack using `bash scripts/p2_install.sh`. P2 CI also installs Playwright 1.56.0/Chromium and runs `P2_BROWSER=1`. Native business mutations are through Dolibarr classes; SQL in the pilot is read-only verification. Object IDs are printed as `P2 OBJECT IDS`, and synthetic screen captures/width measurements stay under ignored `.local/`. Do not publish installer HTML/config/password files.

## Executable scenarios

The following are implemented in `tests/p2_business.php`; the final PR validation comment links the tested SHA/run. A scenario is verified only if its PASS line exists in that run, not merely because its code is present.

| Area | Assertions / evidence | Disposition |
| --- | --- | --- |
| CRM | Native customer, supplier, contact creation and contact reload/association | native/configure |
| Permissions | Native unprivileged user and denied order validation; administrator succeeds | native/configure; full endpoint/warehouse role matrix remains manual |
| Products / packaging | Three native SKUs: goods, carton and tracked lot | native/configure; no duplicate catalog |
| Warehouses / stock | Receipt 20; transfer 5 between two warehouses; count adjustment -1; shipment -4; total native movements 15 | native/configure; count-adjustment API tested, full inventory-session UI not certified |
| Batch | Native receipt 8 in P2-BATCH; transfer 3; balances 5/3 | native/configure |
| Order / shipment | Two-line order; native validation; dispatch 4 of 10 goods; 6 remain outstanding | native/configure; no custom loading/tare logic |
| Draft finance | EUR 40 draft remains unissued/unsettled; separate fictional bank receipt 40 and statement reconciliation | native/configure; bank receipt is NOT an invoice payment |
| Currency | Native USD currency/rate 1.2; EUR 20 yields USD 24 on a separate draft; no online rate provider | native/configure; live exchange rates deferred |
| Documents | Native sponge invoice and rouget shipment generators | native/configure; customer-specific approved order templates may extend |
| Reports | Native CSV fixture and genuine XLSX workbook | native/configure; role-scoped full dataset/report equivalence not certified |
| Turkish | Native tr_TR Invoice translation resolves | native/configure; complete translation quality remains human review |
| Mobile | Real browser login and 15 authenticated renders: partner/product/order/shipment/invoice lists at 360/390/768px; scroll widths and screenshots | native first; this is render smoke, NOT proof of task usability or accessibility |
| Mail | Two inactive native collector configs persist; MAIN_DISABLE_ALL_MAILS rejects outgoing transport; separate CLI native CMailFile test captures MIME through a local-only sendmail replacement | native collector baseline; fake IMAP ingestion/SMTP protocol delivery, attachment matching and concurrency remain BLOCKED/not exercised |
| Carton consumption | Stock product persists at 50 despite goods shipment when no consumption rule is configured | extend only after approved packaging needs; advanced consumption deferred |

## Remaining acceptance limits (not PASS)

- No validated/issued invoice, invoice settlement or regulated accounting submission: draft-only guard is intentional. Fictional bank reconciliation does not prove customer receivable settlement.
- IMAP synchronization and SMTP protocol behavior are not demonstrated by disabled collector persistence. The tested outgoing capture path uses no network and does not certify SMTP negotiation. No collector polling/cron or provider credentials are enabled. Assess native collector attachment/matching behavior against a fake server before selecting a Mail Center replacement.
- Mobile screen rendering cannot prove touch interaction, creation/upload tasks, overflow usability, screen-reader behavior or Turkish wording quality. Review generated screenshots and complete the phone task protocol in NATIVE_PILOT.md manually.
- WSL2/Docker Desktop steps are documented in LOCAL_DEVELOPMENT.md; Windows execution is NOT RUN.
- No packaging consumption engine is authorized. A configured product/BOM/kit pilot would be needed before concluding every native option is insufficient.

## First-release scope decisions

| Proposed capability | Classification | Reason / gate |
| --- | --- | --- |
| CRM, catalog, stock, warehouses, lots, orders, shipments, invoices, payments/bank | native/configure | Keep one commercial authority; partial evidence above, remaining finance acceptance explicit |
| MAX brand vs legal identity, unified navigation | extend | Supported config/theme/hooks and native permissions; implementation requires later phase approval |
| PO/LL/PC provenance, conflicts and protected manual overrides | extend | Link specialized document history to native order/shipment IDs; never a second order ledger |
| Multi-account Mail Center and translation | extend (provisional) | Collector configuration exists; ingestion/approval gap still requires fake-transport evaluation; translation opt-in |
| Customs declarations | new module | Supplementary declaration lifecycle linked to native entities; later approved phase |
| Optional HKS | new module | Industry-specific mocked adapter; no notifications in P2 |
| Approved report/Excel gaps | extend | Native formats work first; define exact datasets/permission rules before replacement |
| Vehicle loading, general export packages, detailed material consumption | defer | Outside current release approval |
| Old pallet/tare, weighbridge, personnel/NFC/foreman, custom export cost/profit, historical migration | excluded | Master scope, regardless of legacy availability |

No evidence here approves a staging deployment, merge or custom-module implementation. Those are separate owner decisions; P2 authorization itself is already granted.
