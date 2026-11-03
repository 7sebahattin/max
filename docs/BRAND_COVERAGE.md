# Central product branding — Issue #14

This independent main-based lane follows the latest Active Codex B assignment in [Issue #14](https://github.com/7sebahattin/max/issues/14). Only modules/maxcore, new tests/branding, scripts/branding_*, this document and the new branding workflow are owned. No maxmobile or PR #16 integration files are changed. Upstream source stays pinned to Dolibarr 24.0.1 and unmodified.

## Supported surfaces and truthful coverage

| Surface | Supported mechanism | Implemented coverage / limitation |
| --- | --- | --- |
| MAX settings | Existing native configure/admin rights, forced native CSRF | One display name, bundled/uploaded logo and favicon selector, no PHP renames |
| Authenticated HTML browser title | llxHeader hook + request-local MAIN_APPLICATION_TITLE/MAIN_HTML_TITLE | Native standard llxHeader pages; preserves page title/context; pages bypassing standard header need review |
| Owned MAX navigation label | Own MAXMenu translation key in public native Translate cache | Changes our menu label without rewriting native commercial menu labels |
| Native header | Append-only printTopRightMenu hook | Product logo/name; native user tools, menus and notices preserved |
| HTML favicon | Request-local native MAIN_FAVICON_URL | Standard header and login; bundled assets or current sanitized uploaded PNG; none keeps native fallback |
| Native login | getLoginPageOptions + getLoginPageExtraContent | Title/favicon and appended separate product brand. Native login, true Dolibarr version marker, legal/company logo and notices remain |
| Login primary/legal logo replacement | Native code selects company/theme logo | NOT replaced: no safe product-logo setter identified; separate appended product block is the supported alternative |
| Email header/signature/Mail Center | Requires explicit template/transport integration | NOT implemented or tested as outgoing branding; no real SMTP/IMAP/provider configured |
| Commercial PDF/invoice letterhead | Native legal issuer/templates | NOT replaced by product artwork. Legal issuer, tax, IBAN and financial records remain authoritative; no global product PDF claim |
| Other themes/custom public pages/mobile app manifest/apple-touch icon | Separate surface-specific hooks/templates | NOT certified; native defaults remain. No core/theme/COPYRIGHT removal |

The implementation uses verified 24.0.1 main.inc.php llxHeader reference parameters and native renderer configuration, main/topright/login hook contexts through the native HookManager. Overrides are request-local only; no native application/title/favicon or legal constants are persisted. All saved business-independent product settings remain MAXCORE_DISPLAY_NAME/LOGO/FAVICON. Changes are separate from the legal company editor. Customer installation remains one independent DB/storage/config; no paid MultiCompany or cross-customer data store.

## Upload and storage contract

Only authenticated configure/admin users can save via the existing guarded settings form. Native token validation must succeed before inspecting uploads. Raster PNG/JPEG only, checked by extension, actual fileinfo MIME, decoded signature/type and image dimensions. Limit 1 MiB input, 2048px per side and 4,194,304 pixels. No SVG, GIF, arbitrary URL, PHP/file path or upload-name storage. GD decodes and writes a fresh PNG, discarding metadata/appended active bytes; content hash names the output. Files are mode 0600 in an entity-specific 0700 directory under DOL_DATA_ROOT/maxcore, outside the public web root.

The product artwork endpoint intentionally serves the *current non-sensitive application artwork* without login, so login and favicon work. It uses master.inc.php for native config only, accepts GET with a fixed logo/favicon enum, ignores arbitrary file/entity requests, validates the stored hash path and never reads business/user data. nosniff, image/png, no-store and restrictive CSP headers apply. Only the current configured pointer is exposed, not directory listings or old asset names. Old unreferenced sanitized files remain private for rollback; deliberate retention cleanup is a separate administrative task. There is no antivirus/malware-scanner claim.

Choose a bundled asset or upload a raster; a successful upload selects the new logo. Select logo in the favicon selector to use the new upload as favicon. Invalid uploads/token/roles must leave saved settings and legal/financial state unchanged. Database/filesystem atomicity is limited to pointer commit and atomic file rename: a rejected DB write may leave a private unreferenced raster, never an active pointer to a partial file.

## Acceptance and evidence

The workflow uses two separate fresh runners/projects/DB/storage contexts for North Demo + Synthetic Legal A and South Demo + Synthetic Legal B. Each starts with the original MAX Core actual installer/lifecycle/roles/CSRF/nine-mobile suite unchanged, then creates synthetic legal/tax/bank/IBAN fixtures before branding. HTTP tests change name/upload, verify claimed native home/third-party/MAX/menu/title/favicon/header/login surfaces, reject active/oversized/malformed/overdimension uploads, test configure/denied/read roles and invalid CSRF, and compare native issuer/tax/IBAN/finance snapshots. Nine own-page mobile widths are checked again after upload. This is browser/code coverage, not commercial production certification.

Pinned actions and Playwright, read-only contents token, no production environment/secrets or OIDC; same-origin browser routes, private db/web network and disabled real mail. Artifacts contain only explicit synthetic PNGs/measurement/report JSON, not .env/config/accounts/installer HTML or private asset storage.

Local static checks can run with python3 tests/maxcore/static.py and scripts/check.py. Full runtime is bash scripts/branding_test.sh with a fresh disposable .env, unique COMPOSE_PROJECT_NAME and BRANDING_VARIANT=a or b. Local Docker build is BLOCKED by managed 8GB/vfs storage; no local runtime PASS is claimed. Actual exact-head CI evidence follows on the draft PR and issue; implementation acceptance remains pending until those tests execute.

## Review, rollback and manual limits

Review public-artwork scope, input decode/re-encode, native hook behavior, pointer/storage isolation, CSRF/roles and exact surface table. Physical devices/software keyboard, screen-reader/contrast, other browsers/themes, post-merge coexistence with maxmobile, upload ZIP UI and broader templates remain manual or future acceptance. No staging/live deployment, notifications, customer migration or merge is authorized.

Disable MAX Core to stop hook rendering/public artwork and restore native title/favicon behavior; product constants/files remain for re-enable. Restore prior MAX Core source/settings/private files from a backup to roll back. No legal/financial migration or cleanup occurs. Preserve Dolibarr license, true version and per-component notices. Distribution licensing and prototype module registry allocation still need review.

First real CI passed the original MAX Core suite, then the new synthetic bank fixture failed before upload execution. Pinned Account::create explicitly requires date_solde; the fixture now supplies a demo opening date. No native API guard was bypassed. The isolation baseline also contains a native unissued draft invoice/line and synthetic recipient, and the authorized configure test uses an actual JPEG which must normalize to PNG. Both legal/entity fixtures and all failures remain part of runtime CI; no passing upload claim is made before execution.

A subsequent real CI passed upload sanitation/header/title/favicon, then caught the owned menu label still showing its old value. In pinned native bootstrap, menu labels are resolved before llxHeader. The same request-local product settings/own translation key now apply through supported updateSession/afterLogin hooks before menu construction, retaining llxHeader/login hooks for rendering. No native menu rows or legal constants are rewritten; the menu assertion stays mandatory.

The next runtime run reached the login marker assertion after claimed native surfaces, upload negatives, role/CSRF and mobile checks. That assertion incorrectly expected the word Dolibarr in the native login title; pinned security2.lib.php defines the true marker as the version alone. Tests now require the exact native "@ 24.0.1" marker, and the appended product block explicitly displays Dolibarr 24.0.1 as visible upstream attribution. The original native logo/template/notice files remain unmodified. Login screenshots are saved before assertions. The branding workflow also runs on main pushes after an owner merge, using the exact push SHA for post-merge regression.
