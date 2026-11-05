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

Issue #20 added only `tests/coenabled/**`, `scripts/coenabled_*`, this document and its workflow. Application modules, previously merged test helpers and upstream remain unchanged. Issue #31 adjusts CI routing/source metadata inside that harness, as documented below. The exact branch HEAD is checked out explicitly. No skip/xfail/continue-on-error is permitted within native acceptance. Missing viewport sets, observer evidence, snapshot mismatch, test failure or cleanup failure prevents acceptance. Test exit and cleanup outcome are recorded separately, including on failure.

Artifacts: numeric geometry/state/identity/phase summaries, fixed assertion locations, source SHA, and public or source-redacted synthetic screenshots. No raw HTML, credential/session/seed/config files, raw MIME/attachments, browser trace or Mail screenshots are published. Observers suppress exception arguments and retain safe labels/file locations; private values are not debugging artifacts.

## Reproduction and review gates

Use a fresh checkout, disposable `.env`, Docker/Compose, Python and Playwright 1.56.0/Chromium:

```sh
python3 -m unittest discover -s tests/coenabled -v
COMPOSE_PROJECT_NAME=max-coenabled-unique bash scripts/coenabled_test.sh
```

During Issue #20, local full native execution was **BLOCKED** by the managed environment's 8 GB/vfs capacity; real isolated execution was performed in GitHub Actions, not substituted with mocks. Six local evidence/cleanup/real-Docker guard tests passed then. Recorded cross-module results are listed below; final exact-head proof is maintained in merged PR #29. Issue #31's prepublication cost gate and NOT RUN snapshot are preserved below, followed by its published exact-SHA evidence; historical results do not validate a later HEAD.

Physical phones, real WSL2/Docker Desktop workstation, full accessibility audit, provider/TLS/OAuth, PDF extraction/unlocking/automatic release, HKS SOAP and commercial distribution remain manual/BLOCKED gates. This test-only task authorizes no staging/live deployment, validated invoice or merge. Rollback discards only the owned synthetic project; module/data rollback is outside this test-only PR.

## Execution history

Initial exact head `5ef348cbd8657050d00e46cd3575aed86b613c84` [41984618161](https://github.com/7sebahattin/max/actions/runs/41984618161) FAILED at the new bootstrap helper, after the unchanged native P2/commercial installation passed. Docker identities were captured; cleanup succeeded and no acceptance manifest was emitted. The first bounded log projection omitted a generic native exception, so argument-free exception type/file/line/stack diagnostics and partial NOT_RUN viewport evidence were added. This is not a cross-module PASS or yet a diagnosed application defect.

Bootstrap root cause: the harness passed the HTML selector value `logo` directly to `maxCoreSave`, whose supported service contract accepts concrete bundled/uploaded asset tokens. The UI resolves `logo` before invoking that service. Bootstrap now supplies `max-icon.svg`; the real HTTP upload test still uses the UI `logo` option and validates normalized raster/favicon bytes. This is a new-test API misuse, not an application defect or a reason to modify Core.

`b90ae5d7936779b3f453df75142b5323fe01e8ce` [41985650987](https://github.com/7sebahattin/max/actions/runs/41985650987): all five module bootstrap and original Orders/Customs/Mail setups PASS; freeze rejected an incomplete commercial fixture. P2 proves batch stock quantities but does not create a native `product_lot` catalog record. The new bootstrap now uses `Productlot::create` for the existing synthetic P2-BATCH before any baseline, without changing P2 or an application module. The strict populated-lot assertion is retained. Cleanup succeeded; all viewport cases remain NOT_RUN for this failed attempt.

`f4dcc514f824dd93cc5a9ccf178f63b97453c20f` [41986437274](https://github.com/7sebahattin/max/actions/runs/41986437274): full bootstrap, all merged module fixtures, immutable populated freeze and customer/order/partial shipment→packet→Customs chain PASS. Core browser stopped before its first viewport with `Expected HTTP 200`, plus console error digests (including `net::ERR_FAILED`). No viewport PASS is claimed. Added response-status/path and payload-free failed-request provenance plus Core model-capability projection to distinguish fixture permissions, blocked resources and real application defects without changing modules or weakening assertions.

`aa0fd2ebed30fa75f6b76aee516fe577c3925641` [41987662042](https://github.com/7sebahattin/max/actions/runs/41987662042) diagnosed the Core HTTP failure: `maxcore-test` had native `admin=1` but `read/configure/module_admin=false`; regular Core reader/editor/manager permissions and HTTP responses were correct. A newly created post-activation admin needs explicit native rights, unlike the pre-activation installer admin. The bootstrap now grants that intended synthetic owner's current native rights via `User::addrights` and verifies Core capabilities before HTTP tests; unprivileged roles are unchanged. No application ACL bypass was added.

Blocked external XHRs were native anonymous telemetry attempted on the home page. Tests now opt out through the official installer cookie `DOLINSTALLNOPING_<getHashUniqueIdOfRegistration(sha256)>=1` (upstream `install/step2.php` and `main.inc.php`), added to each isolated browser context before navigation. This opts out, does not forge telemetry success, and does not alter session/CSRF credentials, responses or application source. Network guards remain enabled; any remaining failed request/JavaScript/missing-asset error fails acceptance. No remote request succeeded. The opt-out cookie file is private and never uploaded.

## Recorded successful shared-installation proof

`2bf29c5cb61a61fabcd7300ad4f664164081ad12`: [coenabled native 41988646779](https://github.com/7sebahattin/max/actions/runs/41988646779), [existing integration 41988646636](https://github.com/7sebahattin/max/actions/runs/41988646636), [foundation 41988646689](https://github.com/7sebahattin/max/actions/runs/41988646689), [source audit 41988646701](https://github.com/7sebahattin/max/actions/runs/41988646701) all **PASS**. The evidence archive was inspected: 63 strict checks (15/9/9/18/12), 15 diagnostic baseline measurements, 66 permitted public/redacted PNGs, all four browser observers with empty JS/asset/failed-request lists, 14 cross-module HTTP 403 denials, successful native chain, and test exit=0/cleanup exit=0/no owned resources remaining.

Runtime: Docker 28.0.4, Compose 2.38.2, Dolibarr 24.0.1, PHP 8.3.36, MariaDB `11.4.13-MariaDB-ubu2404`, one `max_demo` DB/entity1. Passing project `max-coenabled-41988646779-1`; DB hostname `856730d7154f` matches its Docker container ID prefix. These are expired disposable identities, never selectors for reuse. Full image/container IDs and installation fingerprint are in sanitized `docker.json`/`identity.json`; all four container identities remain identical from installed→final.

Native Dolibarr's legacy denied list pages return HTTP 200 with an access-denied body; tests record the actual code, require the denial message and absence of the protected table. MAX module/field/private-file denials require HTTP 403. No native core status behavior was patched or mislabeled.

The final evidence follow-up records explicit native and Orders/Customs before/after hashes plus module versions and applies the same local-network guard to the unauthenticated login context. Its exact SHA/CI result is recorded in [merged PR #29](https://github.com/7sebahattin/max/pull/29): `697f5efac81d0347617e61cb2524eda83d09181d`, [native CI 41989908295](https://github.com/7sebahattin/max/actions/runs/41989908295), 63/63. No unresolved application defect was found in this synthetic pilot; manual/BLOCKED gates above remain outside this acceptance.

## Issue #31: persistent selective regression

Starting main was verified on GitHub on 2026-11-05 as `83df46d2646f8004c72ec2cbaf4ad4892159f9fd`, the merge commit of PR #29 (already merged; no merge requested or repeated). Open parallel work was only draft PR #23; neither that PR nor its branch is modified. The new local branch is `ci/max-selective-regression`. This section and [Issue #31](https://github.com/7sebahattin/max/issues/31) record the implementation and publication gate.

### Selection and concurrency contract

`regression_policy.py` is the single path policy. A five-minute lightweight job reads the **complete local Git diff** from the PR target/head merge base after a full-history exact-head checkout. It does not use GitHub's truncated 300-file path-filter list or call a changed-files API. Deletions and both sides of renames are included; NUL-separated paths and subprocess argument arrays preserve unusual filenames. Missing history, incorrect checkout SHA, dirty tracked sources or invalid input fail selection rather than emitting a valid skip. The only control outputs are a boolean and verified full SHA.

| Changed paths | Native job selected? | Reason |
|---|---|---|
| `modules/maxcore/**`, `modules/maxmobile/**`, `modules/maxorders/**`, `modules/maxcustoms/**`, `modules/maxmail/**` | Yes | All five deployed module trees, including assets/locales/SQL |
| `tests/coenabled/**`, `tests/maxcore/**`, `tests/mobile/**`, `tests/orders/**`, `tests/customs/**`, `tests/mailintake/**`, `tests/branding/**`, `tests/p2_*` | Yes | Shared native fixtures, browser/security helpers, fake IMAP overlay and related module suites |
| `scripts/coenabled_*`, `scripts/maxcore_*`, `scripts/mobile_*`, `scripts/orders_*`, `scripts/customs_*`, `scripts/mailintake_*`, `scripts/branding_*` | Yes | Related orchestration/helper families |
| `compose.yaml`, `.dockerignore`, `deployment/local/**`, `upstream.lock.json`, `scripts/p2_install.sh`, `scripts/prepare.py`, `scripts/check.py`, `scripts/max_source_audit.py` | Yes | Runtime/build context, pinned compatibility, native installer and policy dependencies |
| `.github/workflows/max-coenabled.yml` | Yes | Changes to the acceptance gate itself |
| Documentation only, including this file and `docs/COMPATIBILITY.md`; unrelated lanes such as `tests/mail/**` | No | Prose does not change executable compatibility; actual locks/build/Compose changes above do |
| `workflow_dispatch` | Always | Explicit full native acceptance on the selected ref, even without a diff |

PR events are only `opened`, `synchronize`, `reopened`; there is no push, schedule, automatic retry, matrix or extra-service dependency. Cumulative PR changes are used: adding a doc commit to a PR that already changes a module still requires exact-head native proof. An older SHA's success is never reused as current acceptance.

Concurrency is keyed by source repository plus branch, shared with manual runs on that branch. Only one workflow in that group runs at once; GitHub replaces older pending runs with the newest pending run. `cancel-in-progress: false` deliberately preserves a running suite's owned-resource teardown. There is no second push-triggered copy. Manual cancellation/timeouts cannot be claimed as verified cleanup or native PASS; the ephemeral hosted VM disposal is a fallback, not acceptance evidence. A manual dispatch on a different branch is a different group.

An irrelevant doc-only PR runs the lightweight selector and leaves `single-populated-installation` **NOT RUN**. The selector's PASS means routing/provenance passed, never 63-case native PASS. For selected runs the existing 40-minute native job still requires complete evidence, unchanged snapshots and successful teardown. Missing selector output/job failure cannot silently select a successful native skip.

If enforcing required status checks, require the selector as well as the conditional native job: a skipped native job alone is not acceptance evidence when selection fails. Branch-protection settings are not changed by this task.

### Preserved native guarantees and source metadata fix

The original source guard hardcoded the Issue #20 creation base and permitted only added harness files. A future legitimate module modification would therefore abort before the native regression began. Source metadata now records the actual target/merge-base, changed paths and exact HEAD with a clean tracked tree; the native job receives the selector's verified target SHA and computes the same merge-base. Local reproduction defaults to the first parent, or accepts an explicit `COENABLED_BASE_SHA`. Moving main ahead of a PR is supported via merge-base, without testing a synthetic merge instead of the PR HEAD. This adjusts provenance, not the commercial fixture/snapshot boundary.

The strict engine, runtime PHP, observers, role/CSRF/private-file checks, IMAP scenarios, native snapshots, run orchestration and cleanup script are unchanged. The same five Cartesian sets remain **15 native + 9 Core + 9 Orders + 18 Customs + 12 Mail = 63**, at exactly 360/390/768px with zero overflow tolerance, one installation/database and no SMTP/HKS/provider use. New offline negative tests cover missing/duplicate/one-pixel-overflow evidence in every surface and test/cleanup failures with a manifest present. Missing-manifest tests remain in place. These synthetic unit fixtures are not native execution evidence.

### Historical prepublication snapshot: zero-cost gate and local evidence

The following BLOCKED / NOT RUN statements and evidence table record the local-only state at `b7769ee8ea346b9f1b29cc67c13df3d224e7e1fa` **before publication**. They remain unchanged as history, not the current PR status. The later owner-confirmed spending block and completed automatic CI are recorded separately below.

**BLOCKED: push, draft PR creation and any new remote native run.** The connected GitHub tools do not expose account-level remaining private-repository Actions allowance, artifact-storage allowance or hard spending limits. A standard `ubuntu-latest` runner is not proof of zero charges. No billing setting was changed or inferred from successful historical runs. No new runner, subscription, API key, external service, schedule or retry was created.

Before publication the owner must verify sufficient included Actions execution/storage allowance and a hard stop preventing additional charges for this private repository. Existing foundation/integration workflows can also run on publication; this change does not alter their independent policies. Thus even pushing/opening a draft PR is held until that exposure is verified. This is a user-required cost gate, not a requirement for another architectural/P2 approval. Local commits are not published by an issue status comment.

| Evidence for Issue #31 | Status |
|---|---|
| Main SHA, PR #29 merged, parallel PR/workflow inventory | PASS (read-only GitHub verification) |
| New offline trigger/provenance/63-case contract/cleanup tests | PASS: 15 unit methods; no Docker/network/provider |
| Existing strict evidence tests and missing-manifest cleanup test | PASS: 4 + 1 unit methods |
| Python compilation, shell syntax, workflow YAML syntax/structure (preinstalled PyYAML), repository policy and whitespace check | PASS locally |
| Staged source audit | PASS: 183 blobs scanned, zero findings |
| Protected-source comparison against verified main | PASS: exactly the five intended CI/test/docs files changed; modules, strict engine, native helpers and cleanup unchanged |
| Full Docker/DB/63-browser execution at this new HEAD | NOT RUN; no native PASS or runtime identity claimed |
| New remote CI run, push and draft PR | NOT RUN / BLOCKED by unverifiable no-charge allowance |

### Published PR #32 evidence: implementation HEAD verified 2026-11-05

After the historical snapshot, the owner verified the account Billing display as **$0.00 / $0.00, Usage blocked** and authorized publication. The branch was published and [DRAFT PR #32](https://github.com/7sebahattin/max/pull/32) opened against main `83df46d2646f8004c72ec2cbaf4ad4892159f9fd`. Budget/spending controls were not changed; no paid runner/service/API, manual dispatch or rerun was used. This supersedes the earlier publication/native-execution block for the evidence below, without claiming independent API access to account billing settings.

All four automatically PR-triggered workflows completed **PASS on attempt 1** at exact implementation HEAD `b7769ee8ea346b9f1b29cc67c13df3d224e7e1fa`:

| Workflow | Result | Exact-SHA run |
|---|---|---|
| MAX co-enabled acceptance | PASS | [42027772689](https://github.com/7sebahattin/max/actions/runs/42027772689) |
| MAX integration regression (P2 and MAX Core native pilots) | PASS | [42027772266](https://github.com/7sebahattin/max/actions/runs/42027772266) |
| Foundation checks | PASS | [42027772511](https://github.com/7sebahattin/max/actions/runs/42027772511) |
| MAX source audit (Linux/Windows) | PASS | [42027772543](https://github.com/7sebahattin/max/actions/runs/42027772543) |

The completed native job log confirms the unchanged strict evidence engine passed **63/63** (15 native + 9 Core + 9 Orders + 18 Customs + 12 Mail), project identity checks passed before/installed/final, and `PASS single owned project cleanup and complete acceptance evidence` was recorded. These are actual native execution and cleanup results, not the earlier offline tests or artifact-upload success alone. CI also passed 21 harness unit tests including the real-Docker ownership guard; **20/20 local PASS** remains the offline subset recorded above. The [independent source review](https://github.com/7sebahattin/max/pull/32#pullrequestreview-5785167136) verified the native log and requested this documentation reconciliation; it is not owner merge authorization.

The sanitized `max-coenabled-evidence` artifact (ID `13414698732`, SHA-256 `66b2e3f7d3f5395609694c6790ece53032fc30c993d73f37fa67001cc005f2d3`) is temporary, with recorded expiry **2026-11-12**. Its archive contents were not independently downloaded/inspected for this PR reconciliation; the native log and workflow outcomes were verified. Do not treat temporary artifact retention as permanent evidence availability.

These run links prove only the implementation SHA above. This documentation follow-up creates a later HEAD; its automatic CI results must be verified separately and recorded with that exact SHA in PR #32, without attributing an older PASS to it or creating recursive documentation-only CI updates. The PR's cumulative executable changes still select native acceptance even though this follow-up modifies only this document.

**Still manual / BLOCKED:** physical-device checks, a real WSL2/Docker Desktop workstation, real provider/TLS/OAuth, PDF extraction/unlocking/automatic release, HKS SOAP and full accessibility/commercial review. Synthetic CI does not satisfy those gates. Code review and explicit owner merge authorization remain separate requirements; selector code from the PR HEAD is not an independent tamper-proof policy. No module/test mechanism, PR #23, merge or staging/live deployment is changed or authorized by this documentation update.

### Reproduction and rollback

The commands below are reproduction guidance, not authorization to dispatch or rerun workflows during this documentation task. The earlier offline/native NOT RUN distinction remains part of the historical record; actual published native/cleanup PASS is recorded above.

Lightweight reproduction (Python stdlib and Git; no Docker or API):

```sh
python3 -m unittest discover -s tests/coenabled -p test_ci_policy.py -v
python3 -m unittest discover -s tests/coenabled -p test_evidence.py -v
python3 -m unittest discover -s tests/coenabled -p test_cleanup.py -v
python3 -m compileall -q tests/coenabled
bash -n scripts/coenabled_test.sh
python3 scripts/check.py
python3 scripts/max_source_audit.py --json
```

The source audit reads the Git index; include the reviewed new files before auditing. A preinstalled YAML parser can validate workflow syntax without downloading tools. Full `unittest discover -s tests/coenabled` also includes the real-Docker ownership guard and is **not** part of this offline subset. After the no-charge gate is verified, use manual dispatch on the exact published branch HEAD or the existing native reproduction command in a fresh disposable checkout; record that SHA, actual runtime identity, all counts and independent test/cleanup exits. No historical run substitutes for it.

Rollback: discard this local feature branch before publication, or later revert its isolated commit through a reviewed PR. That restores the former harness-only routing/source guard without changing modules or business data; it also removes automatic coverage of module edits, so record that loss of protection. Do not roll back application/database state for this CI-only change. No owned Docker project is created by the offline tests, so native cleanup is NOT RUN rather than a fabricated cleanup PASS. Physical devices, WSL2, provider/TLS/OAuth, PDF release, HKS and full accessibility/commercial review remain manual/BLOCKED.
