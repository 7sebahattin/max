# MAX Orders 0.2 — private document intake and document packets

Issue #21 established the optional manual 0.1 packet slice. Version 0.2 adds private PDF/XLSX intake, bounded XLSX proposals and explicit association with an existing packet. External GPL-3.0-or-later module for pinned Dolibarr 24.0.1; no core changes, shadow ERP records or mail importer. **General PDF text extraction is BLOCKED**, not implemented by a guessed/regex parser. The original manual workflow below remains available; the 0.2 extension and limits are documented separately at the end.

## Install and use

Copy `modules/maxorders` to the installation's `htdocs/custom/maxorders` (or install the source-only ZIP produced by CI). Enable MAX Orders in native module administration. It depends on native Third parties and Orders. Assign native MAX Orders read, intake or review permission to internal users, plus native customer read, **view all customers**, and order read permissions. Administrators use native administrator semantics. External users are denied. This version deliberately requires all-customer read scope; assigned-customer-only visibility is **not certified** and not silently broadened by the module. No permissions are automatically granted.

Open the MAX Orders menu under the existing Dolibarr login. Intake users enter an existing customer ID, exact customer order reference, explicit PO/LL/PC (or unknown) type, document reference, positive numeric revision and JSON source fields, for example `{"quantity":"100","sku":"DEMO","currency":"EUR"}`. Values must be strings. Allowed fields are quantity, unit_price, sku, variant, packaging and currency. Numeric fields are bounded nonnegative decimals; empty values retain earlier source facts. Nothing is classified from a filename. This manual form accepts metadata only; a separate 0.2 screen handles file uploads. Unknown/empty metadata is retained as needs_review.

LL can arrive first: the packet remains provisional and the later PO with the same normalized customer/order reference joins it. References are case-normalized Unicode text with collapsed spaces; customer and installation remain part of the unique match key. Ambiguous references require human correction before intake; the module never guesses customer/order identity. The packet page shows separate PO/LL/PC current facts, immutable received facts/revisions, manual values, conflicts and audit actors.

Review users can lock/unlock a manual field. Incoming source facts are retained independently; a differing value opens an old/new/source conflict without changing the manual value. Explicit review keeps the manual value or accepts incoming source as a new manual decision. Repeated resolution is idempotent; a changed manual decision causes a stale-review rejection. A same-version/different-content collision preserves the original immutable revision and requires acknowledgement/new revision. It is never silently rewritten.

To link a packet, a reviewer enters an existing native order ID and explicitly confirms. Installation, native customer and customer reference (or native order reference if no customer reference) must match. The link is MAX-owned metadata only; changing to another already-linked order is rejected. It never updates native order lines, validates an order, decrements stock, or creates an invoice. There is no effective-quantity priority or automatic summation.

## Storage and lifecycle

The original seven namespaced tables hold packet, immutable revision, immutable source facts, per-document current facts, manual locks, conflicts and value-free audit events. Version 0.2 adds `maxorders_upload` without altering those tables. Module SQL creation runs through Dolibarr's supported `_init` SQL-array lifecycle with namespaced CREATE IF NOT EXISTS statements in PHP; repository policy forbids standalone `.sql` files. Disabling removes menus/rights through the native lifecycle, preserves records, and denies module services/screens; enabling restores access. Module ID 610140 and rights 61014001–61014003 are prototype allocations with collision checks, not a claim of global registry reservation.

Every read/write service and HTTP boundary checks module state, native user rights and entity. All POST screens use native session CSRF tokens. Input has bounded typed allowlists, output is escaped, no filesystem paths are accepted, and storage errors omit SQL/payload. Packet row locks and database uniqueness serialize replay/revision changes. MariaDB 11.4 is the tested DB; PostgreSQL compatibility is **NOT CERTIFIED** because the atomic upserts use MariaDB syntax. No sensitive payload is written to application logs. Authorized reviewers can see source values in module records; audit events store actor/action/provenance, not raw metadata.

## Scope matrix

| Capability | Status |
|---|---|
| Native unified menu/login, internal permissioned inbox/detail/review, Turkish/English labels | Implemented |
| Manual typed PO/LL/PC/unknown metadata, LL-first packet, immutable revisions, duplicate retry, late-old archive | Implemented |
| Source-specific quantities, manual lock and visible resolution actor, explicit matching native order link | Implemented; no precedence policy chosen |
| Native ERP/legal identity preservation and module on/off | Automated runtime acceptance |
| 360/390/768 pages, focusable table-local scrolling, accessible labels/buttons | Automated browser acceptance; no global native/mobile stylesheet changes |
| Binary file uploads and private downloads | Implemented in 0.2 with explicit review; see limits below |
| Bounded single-sheet XLSX cell proposals | Implemented in 0.2; not a general customer-layout importer |
| Content PDF text extraction | **BLOCKED**; all PDFs stay needs_review with no inferred fields |
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

## Orders 0.2: native-tool audit and approved first slice

Base main `d034f729ac5c41ce0693dc00babb1f6a3c4f32a4` was verified with PR #32 merged and its selective shared-regression gate present. Its Foundation, integration, mobile and branding push workflows passed. Only parallel draft PR #23 was open; it is untouched. Work is isolated on `feat/maxorders-document-intake-v02` and preserves Core/Mobile/Customs/Mail and all 63 shared strict acceptance conditions.

Dolibarr 24.0.1 already bundles PhpSpreadsheet (MIT; upstream `includes/phpoffice/phpspreadsheet/LICENSE`), used by its native XLSX import driver. The module loads that installed reader after a bounded OOXML preflight; no dependency is downloaded or vendored. ZIP, DOM and finfo are PHP tools already present in the pinned runtime. The bundled TCPDF/TCPDI PDF parsers (LGPLv3) support object/merge operations rather than a safe, bounded general text-extraction service. They are **not invoked on uploaded PDFs**. Adding another parser, OCR, AI/API, translation service or subscription is outside this PR.

| Format / operation | Implemented outcome | Explicit limit |
|---|---|---|
| PDF with matching extension/MIME/header | Private immutable source, `needs_review`, authorized attachment download and manual association | **PDF field extraction BLOCKED**, including text-layer files; no page/text provenance is invented |
| Scanned, encrypted or structurally damaged PDF with recognizable PDF header | Same visible `needs_review` with parser blocker, no fields | Magic recognition is not full PDF structural validation, malware certification or a safe-rendering claim |
| Invalid PDF magic, disguised extension or invalid upload | Rejected | Not stored as a trusted document |
| Bounded single-sheet XLSX template | Cell-level unverified proposals and source locations | One product/quantity source, no multi-line aggregation or arbitrary customer template inference |
| Formula/cached result or hidden supported worksheet | Private `needs_review`, no proposals from that workbook | No formula evaluation or cached-formula trust |
| Macro, external relationship, malformed XML/ZIP, ZIP bomb, traversal, unsupported archive parts/ranges | Rejected before the native reader | No macro support or external fetch |
| Explicit reviewed association | Existing packet only; independent source facts, actor/time and immutable link decision | No native order creation/update, approval, stock, invoice or source-priority decision |

### XLSX contract and provenance

Use one worksheet, cells A/B only, at most 128 rows. A1 must contain `MAX_ORDERS_V02`; A2=`field`, B2=`value`. From row 3 each A cell is one exact field key and its B cell is the source value. Supported keys: `document_kind`, `order_reference`, `document_reference`, `document_date`, `customer_reference`, `customer_name`, `product_reference`, `description`, `quantity`, `unit`, `unit_price`, `currency`, `packaging`. Synthetic fixture generation is in `tests/orders/fixtures.php`; the parser is also tested against the actual bundled XLSX writer.

`document_kind` is explicit content PO/LL/PC, never inferred from the filename. Date is ISO `YYYY-MM-DD`; quantities are bounded decimals with at most three places, prices four, and currency three uppercase letters. Raw numeric XML literals preserve decimal precision; formatted values and calculated values are never requested. Locale-dependent/invalid values are uncertain. Required reference/type fields and customer reference or name must be present for an issue-free proposal; other absent fields are listed. Repeated keys preserve all candidates/cells as ambiguous instead of choosing a value. Raw cell text, worksheet/cell/label-cell position, state and truncation flag are retained. Long previews are bounded to 255 characters; the original private file remains the complete source.

Upload validation enforces 2 MiB, UTF-8 basename-only filenames, extension/MIME/magic and a ZIP member allowlist. Preflight caps 64 entries, 1 MiB per entry, 8 MiB total inflated bytes, 50:1 compression ratio, 20,000 XML nodes and 256 A/B cells; it rejects duplicate/case-colliding names, symlinks, encryption, DTD/entities, external targets, macros, named/merged or unbounded ranges. XML uses NONET without entity expansion. No ZIP member is extracted to a filesystem path. Unsupported workbook structures are rejected or remain visible needs_review rather than being partially guessed.

### Private ledger, access and human association

`maxorders_upload` is unique by entity + SHA-256 of original bytes. A row lock and uniqueness serialize duplicate/concurrent intake; hashed private paths use exclusive creation. A rename/retry does not create another file, upload record, packet revision or audit action. Different bytes remain separate evidence, even with the same document reference. Analysis and the first reviewed association are immutable; a conflicting revision retains both the new file and the original packet revision/conflict instead of overwriting facts. The original older-revision, source-specific quantity and manual-lock services perform association inside the same native transaction.

Sources are stored outside webroot at `DOL_DATA_ROOT/maxorders-private/entity-N/<hash>.blob`, with 0700 directories / 0600 files, separate from Mail/Customs storage. No path comes from a request. Symlinked/missing/tampered content is denied. Downloads are GET-only, scoped native-session reads, attachment/octet-stream with nosniff, sandbox CSP and no-store; the app never renders PDF/Office content inline. These controls do not certify that opening an arbitrary original file in a desktop application is safe.

Existing three MAX Orders rights and native all-customer/order-read prerequisites remain authoritative. Intake rights allow upload; read rights allow the scoped list/detail/download; association requires **both intake and review**, not a new role or hidden permission grant. Anonymous/external/unauthorized users, disabled module access and another entity's IDs are denied server-side. POSTs retain native CSRF checks. No public API or anonymous file route is added.

Open the incoming documents link from the original Orders inbox. Review untrusted proposals, issues/missing fields and source cells; download the original if needed. Exact normalized customer reference/name and order reference can suggest existing authorized packets; suggestions never select a target. Matching fails closed beyond a 500-customer search bound. Enter an existing packet ID, native customer ID, reviewed matching reference, explicit type/document revision and reviewed source-field JSON, then confirm. This permits manual transcription for PDF/unsupported extraction without calling it automatic extraction. Unit/date/description and all other proposals remain in immutable analysis; only the original 0.1 field allowlist is submitted to packet facts. No source precedence, automatic approval or native business write occurs.

### Upgrade, test evidence and rollback

Replace only the MAX Orders module files, then disable/re-enable through native module administration to create the additional table with the supported `_init` lifecycle. Existing seven tables, IDs, assigned rights, menu count and manual form are preserved. Re-enabling preserves stored uploads; disabling denies services/downloads. No destructive migration or automatic purge is provided. Back up the upload table, linked packet records and private files together. Roll back to prior module files via review and retain the added table/files for audit; do not drop native or source records.

Local PHP 8.3.36 tests with the pinned Dolibarr bundled reader: **32 real-file parser/security checks PASS**, including writer interoperability. PDF checks prove quarantine/no-fabrication, **not PDF extraction**. The first minimal synthetic XLSX lacked the styles part required by the bundled reader and failed; the fixture was corrected to a native-compatible workbook. A first native-writer test exposed its standard empty worksheet relationships part; that bounded part is now allowed while external targets remain rejected. No upstream change or relaxed extraction assertion was used. The old bundled library emits PHP 8.3 deprecations; the local CLI command excludes deprecation notices (`error_reporting=8191`) and is not a claim of a warning-free upstream library.

The existing 20 offline shared-regression contract tests and one real-Docker existing-project refusal test also PASS; PHP syntax, Python compilation, shell syntax and repository policy are checked locally. Full local Docker image build **FAIL / runtime BLOCKED** at the managed 8 GB/vfs limit; only this attempt's cache records were removed, and its compiled PHP runtime was used outside Docker for real-file tests. Full local DB/browser acceptance is NOT RUN, not replaced with a mock DB.

`scripts/orders_test.sh` retains the original installed 0.1 suite and then runs the real-file parser, native 0.2 transaction/privacy/lifecycle checks, native HTTP multipart/CSRF/private download tests, two independent PHP-process concurrent uploads, unchanged commercial snapshots and nine additional strict 360/390/768 cases. Only sanitized parser/native/HTTP/viewport JSON and synthetic screen PNGs are CI artifacts; sessions, uploaded bytes, installer output and private fixture files are excluded. Existing five-module co-enabled acceptance remains unchanged and is automatically selected by these module edits. Exact-HEAD CI outcomes and failed assertions belong in the new draft PR, not a self-referential documentation commit.

The previously owner-confirmed **$0.00 / $0.00, Usage blocked** control is preserved. No budget change, paid runner/API, schedule, manual dispatch or rerun is authorized. If automatic CI is prevented by quota, record BLOCKED. Remaining gates: **general PDF extraction/OCR**, arbitrary/multi-product workbook templates, assigned-customer-only visibility, a real WSL2 workstation/physical phones, full accessibility/desktop-file safety review, provider/PDF/HKS integrations and owner architectural/code approval. No merge or staging/live deployment is authorized.

First CI attempt at `c832ce766343707919a3378b1103eee0524784f0`: [Orders 42043164847](https://github.com/7sebahattin/max/actions/runs/42043164847) **FAIL** in the new fixture helper: `getHashUniqueIdOfRegistration()` was called without its native `blockedlog.lib.php` include. The original 0.1 suite, 32 real-file parser checks and new service checks through lifecycle preservation passed; new HTTP/concurrency/final snapshot checks were **NOT RUN**. Docker teardown commands removed the owned containers/networks/volume, but the wrapper's final post-check was not reached. The fix explicitly loads that supported native library, as the existing shared fixture already does; it changes no application code or assertion. At that same SHA [co-enabled 42043160962](https://github.com/7sebahattin/max/actions/runs/42043160962), [integration 42043160498](https://github.com/7sebahattin/max/actions/runs/42043160498), [foundation 42043160811](https://github.com/7sebahattin/max/actions/runs/42043160811) and [source audit 42043160483](https://github.com/7sebahattin/max/actions/runs/42043160483) passed. These do not certify a later fix commit; exact-HEAD follow-up evidence is recorded in PR #33.
