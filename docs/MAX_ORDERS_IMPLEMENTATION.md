# MAX Orders 0.3 — traceable source line proposals

Version 0.3 preserves the 0.1 manual packet and 0.2 A/B intake workflows and adds bounded multi-line XLSX proposals. The optional PDF text pilot is disabled by default and fails closed unless reviewed offline sandbox capabilities exist. See **Orders 0.3** below for current behavior and evidence. Earlier version-specific PDF BLOCKED statements describe the default pinned runtime, not a claim that the optional local pilot cannot work. External GPL-3.0-or-later module for Dolibarr 24.0.1; no upstream or native commercial mutations. Issue #21 established the original packet slice.

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
| Content PDF text extraction | Default pinned runtime **BLOCKED**; optional explicit text-layout pilot in 0.3, described below |
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

Second attempt `677dc02fd166f6d89e5c7b095957201b02136066`: [Orders 42046152488](https://github.com/7sebahattin/max/actions/runs/42046152488) passed the original suite, 32 parser checks, installed 0.2 service/lifecycle, native HTTP/concurrency, nine new strict viewports, unchanged commercial/financial snapshots and owned teardown. [Co-enabled 42046152498](https://github.com/7sebahattin/max/actions/runs/42046152498), [integration 42046152827](https://github.com/7sebahattin/max/actions/runs/42046152827), [foundation 42046152556](https://github.com/7sebahattin/max/actions/runs/42046152556) and [audit 42046152918](https://github.com/7sebahattin/max/actions/runs/42046152918) also passed. **Artifact privacy audit FAILED:** the broad `orders-intake-*.json` pattern also selected the generated private session fixture. Artifact `13423974333` therefore contains expired synthetic credentials and must be removed; the disposable DB/accounts were already destroyed, no real provider/customer credential was involved, and values are not reproduced here. Connector tools cannot delete artifacts and a CLI DELETE was denied `Forbidden`; removal remains an explicit owner action unless later verified in PR #33. The fix replaces the glob with four exact report filenames and adds early negative-selection tests for session/account/credential/raw-input paths. Do not treat green runtime CI alone as a successful artifact-privacy audit. New exact-HEAD CI and archive inspection are required after this fix.


## Orders 0.3: explicit source tables and human decisions

Base: `1d473fe8bea6de3035fcdf02b5a27d0dcccf437c`. PR #32 and #33 were verified merged before branching. PR #33's old private artifact was deleted by the owner (comment 6662160913); that historical privacy gate is resolved. No changes to PR #23, other module sources, upstream, dependency pins, or the original 63 shared acceptance cases.

### Supported layouts

The existing `MAX_ORDERS_V02` A/B layout retains its original semantics. The new layout has exactly one visible sheet:

| Cells | Meaning |
|---|---|
| A1 | `MAX_ORDERS_TABLE_V03` |
| A2:B8 | Keys/values: document_kind, order_reference, document_reference, document_revision, customer_reference, customer_name, document_date |
| Row 9 | Exact configured column headers |
| Rows 10–59 | Up to 50 source rows; interior blank rows remain identifiable |

Default columns, in order: Product → description, Variety → variety, Size → size, Quantity → quantity, Unit → unit, Price → unit_price, Currency → currency. Required fields: description, quantity, unit, unit_price, currency. Administrators with native Orders access can configure **MAX Orders → module setup** (`admin/setup.php`), using the entity-scoped `MAXORDERS_V03_COLUMNS` JSON map. Allowed optional fields include product_reference and packaging, at most nine columns A–I. Labels are unique, bounded strings, not code. Header mismatch yields needs_review; no fuzzy header or locale inference. A new configuration applies to new hashes only: replay always retains the original stored analysis.

Raw cell literals and proposed values remain separate. Decimal normalization uses string operations only; commas, exponents, negative quantities and excessive precision remain uncertain. No formulas or cached formula results are evaluated. Mixed currencies produce a visible warning and no total. Blank, duplicate and contradictory rows are retained and marked. Product description is never treated as a native product code. Catalog suggestions require an **explicit product_reference**, the same entity, exact reference and native product-read permission. Suggestions do not write native associations.

### Storage, association and history

Two additive, MAX-owned `_init` tables extend the existing schema without altering the original tables: `maxorders_source_line` and `maxorders_document_source`. Re-enable the module through native administration after copying the upgrade. Disable/re-enable preserves all data. Existing module/right IDs and menus stay stable.

Each immutable source row records upload SHA-256, declared kind/revision, sheet/row, cell raw/proposed values, uncertainty and pending review. The upload transaction locks the unique entity/hash ledger before inserting unique entity/upload/source-row records. Two simultaneous workers cannot create duplicate lines. Original files stay private and immutable.

Both intake and review rights, native customer/order scope, CSRF and an explicit checkbox are required to link a source file to an existing packet. Customer and normalized order reference must match. For multi-line tables the facts object **must be `{}`**: flattening a table into scalar quantity/price is rejected. Human association records confirmed document kind/revision and actor/time; `linked` is not approval of every proposal. No effective quantity, source precedence, automatic product assignment or native ERP mutation is implemented.

Document contexts retain PO/LL/PC independently. Lower late revisions are archived; conflicting same revisions are collisions. No source row, manual lock or earlier scalar fact is overwritten. Packet history shows original file links, all versions and separate source quantities/prices/currencies. Cross-source differences remain warnings with no selected winner. Comparisons fail closed above 500 rows; individual files remain accessible. Original 0.1/0.2 manual edits and conflict resolution remain available independently.

### Resource and privacy boundaries

Uploads are analyzed in a fixed PHP CLI worker with no DB/session/bootstrap or secret environment. Wall time 5 seconds; CPU 3 seconds; PHP heap 128 MiB; address space 384 MiB; 64 file descriptors; file output 3 MiB; reply 256 KiB. Missing CLI/POSIX limits or failed/over-budget workers yield needs_review with no accepted values. Private random workspaces are mode 0700, files 0600, and cleaned on normal success, rejection, timeout and PHP shutdown. A SIGKILL/host crash cannot run PHP cleanup: operators must handle orphaned private temporary directories before reuse. No shell commands or user paths are interpolated.

XLSX retains 2 MiB input, 64 ZIP entries, 1 MiB/entry, 8 MiB expansion, ratio 50, 20,000 XML nodes, one sheet, 128 rows, 1,152 cells and columns A–I bounds. DTD/entities, external relationships, traversal, macros, merges and formulas are rejected before the bundled reader. Hidden sheets/rows/columns and hidden protection layouts are not accepted. XML uses NONET, no entity substitution; only fixed internal parts are allowed. No parser makes external HTTP requests or follows document links. Storage/download retain native authorization, entity, hash verification, GET-only attachments, no-store/nosniff/sandbox headers and mode 0600 outside webroot.

### Optional offline PDF pilot and license review

Dolibarr's bundled PDF creation/merge libraries are not used as an untrusted general text extractor. The existing environment supplies **Poppler pdftotext/pdfinfo 25.03.0**, Bubblewrap and util-linux prlimit. Poppler's installed notices cover GPL-2/GPL-3/GPL-2+ components; Bubblewrap is LGPL-2/LGPL-2.1+; prlimit is a util-linux system tool. These are external executables, not vendored or linked PHP libraries. Preserve distribution copyright/source obligations when packaging a runtime containing them. MAX remains GPL-3.0-or-later. No OCR, paid API, extra package installation, runtime image or dependency pin change is introduced here.

The administrator may explicitly enable `MAXORDERS_PDF_SAFE_ENABLE`. Fixed `/usr/bin` tools must already be present and a real unprivileged user/network/PID/IPC/UTS namespace sandbox must work. Every invocation uses an argument array, cleared environment, no capabilities, read-only public binaries/libraries/fonts and one private input. App files, credentials and home are not mounted. Network is unshared, temp is a 4 MiB tmpfs, core dumps disabled, child memory 256 MiB, CPU 2 seconds, wall 2 seconds per command, output bounded. Ten pages and 64 KiB extracted UTF-8 text maximum. No rendering, OCR, URL fetch, embedded action or shell execution.

Only text beginning `MAX_ORDERS_PDF_V03` followed by exact `field: value` lines for the existing allowlist is understood. Unknown layout/fields, invalid values, duplicate fields, scanned/encrypted/malformed/over-limit documents stay needs_review. Supported fields include page, line and byte-offset provenance; original bytes are unchanged. This is a deterministic **extracted text** parser, not a PDF-byte regex parser.

The pinned native Docker runtime lacks these optional tools; safe sandbox support inside its container is not certified. Its default and CI PDF extraction are therefore **BLOCKED**, with no unconfined fallback. Do not add privileged mode, SYS_ADMIN, relaxed seccomp, paid OCR or an external provider to bypass this gate. Review/package licensed tools and validate unprivileged namespaces in a dedicated future deployment change before enabling on a customer installation.

### Tests and reproduction

`bash scripts/orders_test.sh` retains the entire original 0.1/0.2 suites, then runs the 0.3 real-file parser, optional capability probe, installed DB fixtures, authenticated browser checks and separate concurrent PHP workers in the same disposable native installation. Synthetic product creation precedes the immutable 17-table plus legal-issuer snapshot. Subsequent parser, branding-independent UI and intake operations may not change those native records. Existing shared 63 strict cases remain unchanged and run through the selective co-enabled workflow.

Local PHP 8.3.36 + checksum-verified Dolibarr 24.0.1 reader results: **32/32** original file/security checks, **32/32** new table/resource checks, **14/14** real sandboxed PDF checks, repository checks, PHP lint, Python compilation, four Orders artifact/Docker refusal/locale tests and **21/21** shared lightweight tests PASS. Full local MariaDB/browser acceptance is **NOT RUN** in the constrained managed filesystem; new automatic PR CI is authoritative for native installation, source HEAD, shared cases, artifact inventory and teardown. Do not infer a native PDF PASS from the separate capable-host probe. Native CI results are recorded on the draft PR at exact HEAD, including failed attempts.

New browser matrix: source table review, packet source history, administrator template settings × TR/EN × 360/390/768 = **18 strict document-overflow cases**, preserving the previous Orders and intake matrices. Tests cover pointer confirmation, keyboard-focusable table-local scrolling, native roles, server-side denial/content, template and association CSRF, source provenance, original bytes and missing assets/JS errors. Manual physical-device, WSL2, real-provider/TLS, general PDF/OCR, HKS, SMTP and full accessibility gates remain BLOCKED/NOT RUN; none is certified by desktop Chromium.

Only explicitly named public JSON reports, synthetic screenshots and the source-only module ZIP enter the artifact. New allowed names: orders-table-parser/native/http/mobile.json and orders-pdf-probe.json. Credentials, sessions, source inputs, workspace files and unlisted future JSON files must never match upload patterns. Inspect the actual downloaded archive against this closed inventory and source HEAD before review; a failed/absent archive inspection is NOT RUN, not PASS. Owned Compose containers, networks and volumes must be absent after teardown; cleanup failure fails acceptance. Keep the existing $0 Actions hard stop unchanged; no manual dispatch/rerun.

Reproduce the pure parser on a compatible PHP CLI with zip/DOM/mbstring/intl/POSIX: `php tests/orders/table_unit.php /path/to/dolibarr/htdocs`; optional sandbox capability: `php tests/orders/pdf_probe.php /path/to/dolibarr/htdocs`. These do not install tools or contact providers. For native acceptance use the existing unique `max-orders-*` Compose wrapper with synthetic `.env` and Playwright 1.56.0.

Rollback: disable the optional PDF setting, then disable MAX Orders through native administration. Restore the approved source package and preserve all private files and MAX-owned tables; do not drop or migrate commercial data. Version 0.2 ignores additive 0.3 source tables, so source-line review requires 0.3 again. Back up/restore the installation's approved DB/documents together before any future customer deployment. No deployment or merge is authorized by this PR.
