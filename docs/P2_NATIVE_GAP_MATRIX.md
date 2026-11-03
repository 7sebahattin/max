# P2 — Native Dolibarr feature pilot (in progress)

**Owner authorized local P2 testing.** No live deployment or customer data is authorized.
Track: [Issue #3](https://github.com/7sebahattin/max/issues/3).

| Area | Planned evidence | Status |
| --- | --- | --- |
| Actual Dolibarr installation | Official installer step2 (schema and seed) and step5 (synthetic admin), not just installer prerequisites | NOT YET VERIFIED ON P2 CI |
| CRM and suppliers | Create synthetic customer/supplier, read/write permissions | PENDING |
| Stock and lots | Product, batch, two warehouses, transfer and stock reconciliation | PENDING |
| Orders and dispatch | Sales order, partial shipment, stock transaction timing | PENDING |
| Finance | Invoice, synthetic payment and different currencies | PENDING |
| Document and reporting | Invoice and shipment PDF, standard Excel and custom gaps | PENDING |
| Native mail collector | Assess supported behavior with fake/capture email only | PENDING |
| Mobile user experience | Standard pages at 360/390/768 CSS px | PENDING |
| Pallet/packaging | Existing standard features before any proposed custom code | PENDING |

No MAX-specific order, mail, customs or HKS functional code has been added in this phase. Source and demo records are isolated. This table must be updated with actual evidence; CI passing installer alone **does not** justify claims about business operations.
