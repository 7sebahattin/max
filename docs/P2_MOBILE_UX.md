# Native mobile adapter — Issue #17

Baseline: main 059315f42a858510d507c1a78c9b8ee3f129698c. PR #16 remains unmerged; none of its integration files are changed or copied. [Issue #17](https://github.com/7sebahattin/max/issues/17) records the observed 13/15 native list overflows and its exact CI/artifact evidence.

## Supported extension and scope

Pinned Dolibarr 24.0.1's HookManager supports declared module hook contexts; main.inc.php exposes llxHeader with reference parameters and addHtmlHeader for additional assets. The external Maxmobile descriptor registers these hooks. Native list entrypoints check their own authentication/permissions before headers. The adapter adds a body class and same-origin stylesheet/script only for the five exact native list paths, enabled module, authenticated internal user. No core, theme, SQL schema, endpoint, authorization or CSRF replacement is installed. Prototype module ID 610130 needs registry review before commercial distribution.

Enable MAX Mobile through native Modules/Applications (administrator). At <=768px it adapts the existing navigation/content; tables retain their native rows, forms, columns and actions in a labeled keyboard-focusable scroll region. Arrow keys/Home/End operate on the region itself and do not intercept input editing. Native links/form submissions and single login are preserved. CSS uses the maxmobile prefix, distinct from MAX Core/global branding. Disabling removes asset injection immediately; native presentation returns. No company/legal/stock/financial state is changed.

Whole-document overflow assertions use zero tolerance on body and documentElement at all 15 page/width combinations (third parties/products/orders/shipments/invoices x 360/390/768). No overflow-x:hidden, clipped columns, hidden actions or assertion relaxation is allowed. Table-local scrolling remains accessible.

## Reproduce and evidence

On a capable isolated Linux/CI runner, generate fresh demo credentials as in the workflow, set a unique COMPOSE_PROJECT_NAME and run bash scripts/mobile_test.sh. The wrapper calls the existing P2 installer/commercial/fake-mail/browser pilot unchanged, restarts that same disposable context, creates native read-only/denied accounts, records 15 before screenshots/measurements, enables the native module, and requires all 15 after assertions. It also exercises keyboard table ends, real native filters, a keyboard product search/card task, role denial and disable/re-enable. Native legal/business snapshot hashes must remain equal. The separate CI matrix entry reruns unchanged MAX Core native security/lifecycle/Turkish/nine-mobile checks in another fresh DB/runner.

Only synthetic screenshots and numeric geometry are artifacts; credentials, account JSON, native config, installer HTML and whole .local are excluded. Isolated runtime has disabled mail/private network; no real SMTP/IMAP/HKS/customer data. Cleanup removes only the chosen disposable project/volume. No deployment or merge.

Implementation acceptance is pending until actual CI completes; do not treat source presence as PASS. The managed local runtime build failed because its 8GB/vfs storage filled during the approved Docker build. Only task-created cache records were reclaimed; no global prune or storage-driver/core change. Local runtime is BLOCKED, not PASS; local syntax/policy and actual isolated CI results are recorded on the draft PR.

Manual acceptance remains physical phones/software keyboard/touch, screen readers/contrast, other themes/browsers/menu combinations and complete business tasks. Chromium is not certification. A fresh combined-main integration run is required after PR #16 and this lane are merged by an owner. Review opt-in path scoping, responsive layout, keyboard semantics, native rights and artifact safety. Rollback: disable the module and remove its external directory. Preserve all upstream attribution/license notices; no distribution license claim.

First runtime CI correctly failed at 360px: the adapter contained the data table, but the separate native table-fiche-title layout remained ~424px wide, spilling pagination to x=433. The title/filter/pagination layout table is now stacked with wrapping controls on these five pages. Data tables and columns are preserved in their own scroll region; no assertion or overflow suppression changed. Existing MAX Core runtime matrix passed in that run. Exact rerun evidence is recorded on the draft PR.
