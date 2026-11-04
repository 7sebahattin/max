# MAX Orders 0.1 — document packets

Issue #21. External GPL-3.0-or-later module for pinned Dolibarr 24.0.1. No core changes or shadow ERP records. Version 0.1 is an optional, manual structured-metadata vertical slice, not a PDF parser or mail importer.

## Install and use

Copy `modules/maxorders` to the installation's `htdocs/custom/maxorders` (or install the source-only ZIP produced by CI). Enable MAX Orders in native module administration. It depends on native Third parties and Orders. Assign native MAX Orders read, intake or review permission to internal users, plus native customer read, **view all customers**, and order read permissions. Administrators use native administrator semantics. External users are denied. This version deliberately requires all-customer read scope; assigned-customer-only visibility is **not certified** and not silently broadened by the module. No permissions are automatically granted.

Open the MAX Orders menu under the existing Dolibarr login. Intake users enter an existing customer ID, exact customer order reference, explicit PO/LL/PC (or unknown) type, document reference, positive numeric revision and JSON source fields, for example `{"quantity":"100","sku":"DEMO","currency":"EUR"}`. Values must be strings. Allowed fields are quantity, unit_price, sku, variant, packaging and currency. Numeric fields are bounded nonnegative decimals; empty values retain earlier source facts. Nothing is classified from a filename. No file upload is exposed; unknown/empty metadata is retained as needs_review.

LL can arrive first: the packet remains provisional and the later PO with the same normalized customer/order reference joins it. References are case-normalized Unicode text with collapsed spaces; customer and installation remain part of the unique match key. Ambiguous references require human correction before intake; the module never guesses customer/order identity. The packet page shows separate PO/LL/PC current facts, immutable received facts/revisions, manual values, conflicts and audit actors.

Review users can lock/unlock a manual field. Incoming source facts are retained independently; a differing value opens an old/new/source conflict without changing the manual value. Explicit review keeps the manual value or accepts incoming source as a new manual decision. Repeated resolution is idempotent; a changed manual decision causes a stale-review rejection. A same-version/different-content collision preserves the original immutable revision and requires acknowledgement/new revision. It is never silently rewritten.

To link a packet, a reviewer enters an existing native order ID and explicitly confirms. Installation, native customer and customer reference (or native order reference if no customer reference) must match. The link is MAX-owned metadata only; changing to another already-linked order is rejected. It never updates native order lines, validates an order, decrements stock, or creates an invoice. There is no effective-quantity priority or automatic summation.

## Storage and lifecycle

Seven namespaced tables hold packet, immutable revision, immutable source facts, per-document current facts, manual locks, conflicts and value-free audit events. Module SQL creation runs through Dolibarr's supported `_init` SQL-array lifecycle with namespaced CREATE IF NOT EXISTS statements in PHP; repository policy forbids standalone `.sql` files. Disabling removes menus/rights through the native lifecycle, preserves records, and denies module services/screens; enabling restores access. Module ID 610140 and rights 61014001–61014003 are prototype allocations with collision checks, not a claim of global registry reservation.

Every read/write service and HTTP boundary checks module state, native user rights and entity. All POST screens use native session CSRF tokens. Input has bounded typed allowlists, output is escaped, no filesystem paths are accepted, and storage errors omit SQL/payload. Packet row locks and database uniqueness serialize replay/revision changes. MariaDB 11.4 is the tested DB; PostgreSQL compatibility is **NOT CERTIFIED** because the atomic upserts use MariaDB syntax. No sensitive payload is written to application logs. Authorized reviewers can see source values in module records; audit events store actor/action/provenance, not raw metadata.

## Scope matrix

| Capability | Status |
|---|---|
| Native unified menu/login, internal permissioned inbox/detail/review, Turkish/English labels | Implemented |
| Manual typed PO/LL/PC/unknown metadata, LL-first packet, immutable revisions, duplicate retry, late-old archive | Implemented |
| Source-specific quantities, manual lock and visible resolution actor, explicit matching native order link | Implemented; no precedence policy chosen |
| Native ERP/legal identity preservation and module on/off | Automated runtime acceptance |
| 360/390/768 pages, focusable table-local scrolling, accessible labels/buttons | Automated browser acceptance; no global native/mobile stylesheet changes |
| Binary file uploads, private downloads, content PDF parsing | **NOT IMPLEMENTED**; no fake parser claims |
| Mail automation, shipment mutation, external sending, automatic order creation/approval, stock writes, issued commercial invoice, customer PKL | **NOT IMPLEMENTED** |
| Field priority/effective quantity, approved extraction policy | Owner product decisions deferred |

## Reproduce and acceptance

In a fresh clone with Docker and synthetic `.env`, install Playwright 1.56.0 and Chromium, then:

```sh
python3 -m unittest discover -s tests/orders -v
COMPOSE_PROJECT_NAME=max-orders-unique bash scripts/orders_test.sh
```

The wrapper refuses pre-existing project containers/volumes/networks before installing or arming cleanup, runs the unchanged P2 native installer in its own fresh project, then the installed module tests. A real Docker negative test verifies an existing network survives refusal. Cleanup deletes only the owned project and checks no labeled resources remain. Never reuse a customer database or production endpoint.

Acceptance includes actual module SQL/install/lifecycle, native role grants, LL-first, revision 2 before late 1, canonical duplicate replay, collision retry, empty preservation, source quantities, lock conflicts, resolution actor/idempotency, unknown review, typed/XSS/path rejection, native order matching and explicit confirmation, entity isolation and full native ERP/legal snapshots. Browser checks actual native login, role-denied status/content, invalid CSRF, valid Turkish intake/review forms, on/off access and nine strict 360/390/768 renders. Table overflow stays within keyboard-focusable labeled regions, not the page.

Only synthetic screenshots and viewport reports/source-only module ZIP are artifacts. Private credentials, installer output and `.env` are excluded. No deployment or merge step exists. Local full runtime is **BLOCKED** by managed 8 GB/vfs Docker storage; actual native integration runs in GitHub Actions. Exact-SHA results and failed attempts will be recorded below.

Rollback: disable MAX Orders via native module administration; retain its records for audit. Deleting a disposable CI project removes its synthetic database. No native records/core files need rollback.

Initial `166e561741d56e7cd5658bbaa15ee72c1f5cf2ed` policy failure: standalone module SQL was rejected by existing `scripts/check.py`. Replaced within owned module files using the supported native `_init` lifecycle; shared policy stays unchanged.

CI `8d05832aaae8ddceca6d597dd04414900f464dea`: [41693951557](https://github.com/7sebahattin/max/actions/runs/41693951557) passed installed lifecycle, runtime, HTTP and nine strict viewport tests. Artifact `orders-module-evidence` contains source ZIP, nine synthetic PNGs and measured page widths exactly 360/390/768. Screenshots inspected. Follow-up improves collision review to show actual changed field values instead of hashes, localizes source field/status labels, and records the conflict intake actor; latest SHA is retested separately.

## Recorded CI evidence

| Exact tested commit | Run | Outcome |
|---|---|---|
| `166e561741d56e7cd5658bbaa15ee72c1f5cf2ed` | [41693735286](https://github.com/7sebahattin/max/actions/runs/41693735286) | FAIL: repository policy forbids standalone SQL; fixed with native `_init` SQL array |
| `903afb15b95a1cc2ff1c3c7edb745d10589ffcb4` | [41693837291](https://github.com/7sebahattin/max/actions/runs/41693837291) | FAIL: empty fixture facts encoded as JSON array; fixed to explicit object without relaxing validation |
| `dca18e70edadb0a214c3b028c99fc747cb324bca` | [41695001192](https://github.com/7sebahattin/max/actions/runs/41695001192) | PASS: field-level collision review and translated values |
| `4afdb4086db9ee88b13b551aebca318e953eef6e` | [41695347661](https://github.com/7sebahattin/max/actions/runs/41695347661) | PASS: full native/P2 + installed module + HTTP/CSRF/Turkish + nine viewport cases + teardown |
| `4afdb4086db9ee88b13b551aebca318e953eef6e` | [Foundation 41695347671](https://github.com/7sebahattin/max/actions/runs/41695347671), [source audit 41695348203](https://github.com/7sebahattin/max/actions/runs/41695348203) | PASS |

Local verification: one real Docker ownership/refusal test, 40 existing doctor/source-audit tests, four MAX Core assertion tests, repository policy/index-source audit, shell/Python compilation and PHP 8.3 syntax checks passed. Full local native integration remains BLOCKED by storage; the green native results above are actual GitHub Actions runs. The PR conversation records the final documentation commit's exact-head CI separately, avoiding a self-referential commit claim.

Owner review: native all-customer scope is required for this MVP; assigned-customer-only access, cross-database certification, commercial license/registry review and all deferred business-policy choices remain outside this acceptance. Review draft PR #24 before enabling this optional module anywhere beyond disposable test data. No production access, merge or deployment performed.

Review correction: source collision comparison distinguishes absent keys, explicitly empty strings and literal text such as `[missing]`. Candidate strings are quoted and absent fields use ∅, so user data cannot collide with a missing-field display marker. Installed regressions cover both absent-vs-literal and absent-vs-empty cases without changing current facts or native records.
