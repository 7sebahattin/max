# P2 — Native Dolibarr feature pilot (in progress)

**Owner authorized local P2 testing.** No live deployment or customer data is authorized.
Track: [Issue #3](https://github.com/7sebahattin/max/issues/3).

| Area | Planned evidence | Status |
| --- | --- | --- |
| Actual Dolibarr installation | Official installer step2 (schema and seed) and step5 (synthetic admin), not just installer prerequisites | PASS — GitHub Actions run 41617811791: official step2/step5, 275 tables, synthetic admin, install.lock |
| CRM and suppliers | Create synthetic customer/supplier, read/write permissions | PENDING |
| Stock and lots | Product, batch, two warehouses, transfer and stock reconciliation | PENDING |
| Orders and dispatch | Sales order, partial shipment, stock transaction timing | PENDING |
| Finance | Invoice, synthetic payment and different currencies | PENDING |
| Document and reporting | Invoice and shipment PDF, standard Excel and custom gaps | PENDING |
| Native mail collector | Assess supported behavior with fake/capture email only | PENDING |
| Mobile user experience | Standard pages at 360/390/768 CSS px | PENDING |
| Pallet/packaging | Existing standard features before any proposed custom code | PENDING |

No MAX-specific order, mail, customs or HKS functional code has been added in this phase. Source and demo records are isolated. This table must be updated with actual evidence; CI passing installer alone **does not** justify claims about business operations.

## Verified P2 installation checkpoint — 2026-11-03

- [GitHub Actions run 41617811791](https://github.com/7sebahattin/max/actions/runs/41617811791), commit `01dd08c9f8f00e508884c891cbf101a68f4ba443`: **SUCCESS**.
- Dolibarr 24.0.1 official CLI installer, 275 MariaDB tables, synthetic administrator, standard commercial tables and `install.lock`: verified in the CI log.
- First failed attempt refused an unchanged installer example and was corrected with an exact-example guard; second failed attempt ran the official CLI from the incorrect working directory and was corrected. Both were isolated CI failures with no live effects.
- This success proves a **disposable CI/local sandbox install**, **not** that a publicly accessible MAX or a complete ERP/business workflow has been deployed.
- Pending before the P2 pilot may be signed off: synthetic native object creation and operations, permissions, stock/lot transfer, partial shipping, invoice/payment, document generation, mobile UI, native mail assessment, and a native/custom scope decision matrix.
- **No deployment to `max.sacai.tr`** and no approval to merge this P2 draft PR.
