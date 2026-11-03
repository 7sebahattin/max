# P2 native feature evaluation protocol — awaiting approval

Use a fresh synthetic database only. Record release/image IDs, module settings, role, screenshots and pass/fail for each scenario in the next PR. No customer data or live provider credentials.

| Native capability | Upstream entry point inspected in 24.0.1 | Required scenario |
| --- | --- | --- |
| CRM / suppliers | modSociete, modFournisseur | Create synthetic customer, supplier and contacts; test read/write roles |
| Products / warehouses / lots | modProduct, modStock, modProductBatch | SKU, two warehouses, lot, receipt, transfer and adjustment; reconcile native movements |
| Orders / shipments | modCommande, modExpedition, modReception | Partial order/dispatch, remaining quantity, returns and supported packing output |
| Invoices / payments / bank | modFacture, modBanque, modAccounting | Synthetic invoice/payment, partial settlement, currency and accounting export; never real issuance |
| Packaging | Native product/stock and shipment features | Represent packaging materials and check available packing units; record gaps without importing tare engine |
| Email | modEmailCollector, modMailing | Mock/capture transport; verify account isolation, collector matching, attachments and outgoing approval gaps |
| Documents / export | Native document generators and exports | PDF legal identity, order/shipment association, tabular exports and role restrictions |
| Extensions | modModuleBuilder, external module descriptors | Supported hooks, extrafields, object IDs, menus and fine-grained permissions |

Source presence is verified, behavior is not yet certified. For each scenario choose native configuration, supported extension for a demonstrated gap, or defer/reject. Do not infer a missing feature from a legacy screen name.

Mobile acceptance: at 360/390/768 CSS-pixel widths, login, lists, order detail, shipment and document upload must allow task completion without hidden controls; keyboard, touch targets, readable validation and horizontal table access tested. Document native limitations before designing mobile custom pages.

Before P3: owner approves the resulting native/custom matrix, core identity boundaries and role matrix. Before P4: design synthetic parsing fixtures for out-of-order PO/LL/PC, revision conflicts, manual locks, multiple shipments and ambiguous external outcomes.
