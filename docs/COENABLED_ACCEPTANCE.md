# MAX single-installation coexistence acceptance

Issue [#20](https://github.com/7sebahattin/max/issues/20). Source base independently verified on GitHub: `a3344b18be64e99c8e08df83d5888699f4e3d89d`. PRs #16/#18/#19/#24/#27/#28 are merged in the approved order and every merged main push has successful foundation/integration workflows. Latest base main additionally has successful native-mobile/global-branding push checks. PR #23 stays unmodified and unmerged; its native-only failures are not reclassified by this suite.

## One installation, one database

`scripts/coenabled_test.sh` creates exactly one uniquely labeled local Compose project using unchanged main Compose plus the merged fake IMAP overlay. The unchanged official P2 installer runs once and populates native partners, products, stock/lots, orders, partial shipments, unissued draft invoices and harmless bank records, including an explicitly nonbank fictional IBAN set through the native Account API. Core, Mobile, Orders, Customs and Mail are installed together through native activation APIs. Existing Orders/Customs/Mail fixture helpers and browser suites are executed read-only, without installing another ERP or creating another database.

All commercial fixtures finish before the immutable snapshot boundary. The original helper snapshot files are explicitly initialized **once** to that shared populated baseline, using the exact merged hash contracts; standalone empty-database Core verification is not misrepresented as appropriate for a populated ERP. No baseline is refreshed after UI/intake begins. A broader independent hash protects native legal/tax/render constants, bank/account/IBAN, stock/lots/movements, orders/lines, shipments/lines, invoice totals/lines, payment/finance, agenda and native collector records. Database hostname/version, installation fingerprint and Docker container/image IDs establish identity across the whole run.

The chain test uses the same native customer/order/partial shipments for LL-before-PO packet ingest, explicit human native-order linking and a separate Customs draft/native association. Packet replay and repeated explicit link preserve packet/revision/audit state. No source priority or document approval is inferred. A second immutable MAX Orders/Customs table snapshot surrounds actual fake-IMAP protocol scenarios and the Mail browser suite, proving that mail intake/acknowledgement creates no implicit packet, declaration or downstream write.

## Mandatory acceptance matrix

| Surface | Widths | Strict checks |
|---|---|---:|
| Native customer/product/order/shipment/invoice lists | 360/390/768 | 15 |
| MAX Core home/settings/administration | 360/390/768 | 9 |
| MAX Orders inbox/packet/review | 360/390/768 | 9 |
| MAX Customs list/detail/create/edit/match/status | 360/390/768 | 18 |
| MAX Mail accounts/inbox/message/settings | 360/390/768 | 12 |
| **Total** | exact Cartesian sets, no omitted/duplicate cases | **63** |

Both document and body scroll widths must fit the requested CSS viewport **without tolerance**, including when inherited suites permit one pixel. Fifteen additional native baseline measurements with Mobile disabled are diagnostic only. During native acceptance both shared hooks must inject their own assets exactly once; the top/mobile brand positions must each contain one brand bar, all MAX menu links remain, uploaded artwork/title/favicon follow two synthetic brands while the legal identity stays fixed. Keyboard table End/Home, filter focus, pointer selection/search and product filter→card navigation are exercised. Existing Core keyboard edits and all module lifecycle/role/CSRF/redaction/mailbox-private-download tests remain active. JavaScript page/console errors and missing script/style/image/font assets fail the observer. Expected negative HTTP responses are not misreported as JavaScript application errors.

## Isolation and evidence

Fresh-project guard refuses any pre-existing labeled container, network or volume before arming teardown; it also refuses previous local installer/ownership files. Runtime services have no Internet path/proxy, fake IMAP has no published port, gateway is loopback only, and no real provider/account/customer/SMTP/HKS is used. The unchanged P2 mail test is local capture-only, never SMTP. Private Mail/Customs storage stays separate and outside webroot. Cross-module native users are denied access to other modules and existing private evidence; isolated-run passes are not counted as shared-run proof.

Only `tests/coenabled/**`, `scripts/coenabled_*`, this document and the new workflow are changed. Application modules, existing test files and upstream are unchanged. The exact branch HEAD is checked out explicitly. No skip/xfail/continue-on-error is permitted. Missing viewport sets, observer evidence, snapshot mismatch, test failure or cleanup failure prevents acceptance. Test exit and cleanup outcome are recorded separately, including on failure.

Artifacts: numeric geometry/state/identity/phase summaries, fixed assertion locations, source SHA, and public or source-redacted synthetic screenshots. No raw HTML, credential/session/seed/config files, raw MIME/attachments, browser trace or Mail screenshots are published. Observers suppress exception arguments and retain safe labels/file locations; private values are not debugging artifacts.

## Reproduction and review gates

Use a fresh checkout, disposable `.env`, Docker/Compose, Python and Playwright 1.56.0/Chromium:

```sh
python3 -m unittest discover -s tests/coenabled -v
COMPOSE_PROJECT_NAME=max-coenabled-unique bash scripts/coenabled_test.sh
```

Local full native execution is **BLOCKED** by this managed environment's 8 GB/vfs capacity; real isolated execution is performed in GitHub Actions, not substituted with mocks. Exact SHA/run links, failed assertions, root causes, per-surface counts and cleanup outcome are recorded in the draft PR as execution completes. Current status: IMPLEMENTED, CI PENDING; six local evidence/cleanup/real-Docker guard tests PASS; no cross-module PASS claim yet.

Physical phones, real WSL2/Docker Desktop workstation, full accessibility audit, provider/TLS/OAuth, PDF extraction/unlocking/automatic release, HKS SOAP and commercial distribution remain manual/BLOCKED gates. This test-only task authorizes no staging/live deployment, validated invoice or merge. Rollback discards only the owned synthetic project; module/data rollback is outside this test-only PR.

## Execution history

Initial exact head `5ef348cbd8657050d00e46cd3575aed86b613c84` [41984618161](https://github.com/7sebahattin/max/actions/runs/41984618161) FAILED at the new bootstrap helper, after the unchanged native P2/commercial installation passed. Docker identities were captured; cleanup succeeded and no acceptance manifest was emitted. The first bounded log projection omitted a generic native exception, so argument-free exception type/file/line/stack diagnostics and partial NOT_RUN viewport evidence were added. This is not a cross-module PASS or yet a diagnosed application defect.

Bootstrap root cause: the harness passed the HTML selector value `logo` directly to `maxCoreSave`, whose supported service contract accepts concrete bundled/uploaded asset tokens. The UI resolves `logo` before invoking that service. Bootstrap now supplies `max-icon.svg`; the real HTTP upload test still uses the UI `logo` option and validates normalized raster/favicon bytes. This is a new-test API misuse, not an application defect or a reason to modify Core.

`b90ae5d7936779b3f453df75142b5323fe01e8ce` [41985650987](https://github.com/7sebahattin/max/actions/runs/41985650987): all five module bootstrap and original Orders/Customs/Mail setups PASS; freeze rejected an incomplete commercial fixture. P2 proves batch stock quantities but does not create a native `product_lot` catalog record. The new bootstrap now uses `Productlot::create` for the existing synthetic P2-BATCH before any baseline, without changing P2 or an application module. The strict populated-lot assertion is retained. Cleanup succeeded; all viewport cases remain NOT_RUN for this failed attempt.

`f4dcc514f824dd93cc5a9ccf178f63b97453c20f` [41986437274](https://github.com/7sebahattin/max/actions/runs/41986437274): full bootstrap, all merged module fixtures, immutable populated freeze and customer/order/partial shipment→packet→Customs chain PASS. Core browser stopped before its first viewport with `Expected HTTP 200`, plus console error digests (including `net::ERR_FAILED`). No viewport PASS is claimed. Added response-status/path and payload-free failed-request provenance plus Core model-capability projection to distinguish fixture permissions, blocked resources and real application defects without changing modules or weakening assertions.

`aa0fd2ebed30fa75f6b76aee516fe577c3925641` [41987662042](https://github.com/7sebahattin/max/actions/runs/41987662042) diagnosed the Core HTTP failure: `maxcore-test` had native `admin=1` but `read/configure/module_admin=false`; regular Core reader/editor/manager permissions and HTTP responses were correct. A newly created post-activation admin needs explicit native rights, unlike the pre-activation installer admin. The bootstrap now grants that intended synthetic owner's current native rights via `User::addrights` and verifies Core capabilities before HTTP tests; unprivileged roles are unchanged. No application ACL bypass was added.

Blocked external XHRs were native anonymous telemetry attempted on the home page. Tests now opt out through the official installer cookie `DOLINSTALLNOPING_<getHashUniqueIdOfRegistration(sha256)>=1` (upstream `install/step2.php` and `main.inc.php`), added to each isolated browser context before navigation. This opts out, does not forge telemetry success, and does not alter session/CSRF credentials, responses or application source. Network guards remain enabled; any remaining failed request/JavaScript/missing-asset error fails acceptance. No remote request succeeded. The opt-out cookie file is private and never uploaded.
