# MAX Core 0.1.0 prototype — Issue #12

This independent main-based branch adds only modules/maxcore/**, tests/maxcore/**, scripts/maxcore_* and the new maxcore workflow. It uses the main branch's pinned Dolibarr 24.0.1 and Compose preparation. PR #4 and #8 were read as guidance only: no cherry-pick, hidden cross-branch source copy or dependency. No other PR is changed.

## Install and use

Install the module directory as htdocs/custom/maxcore (the main local Compose already mounts modules under custom). An administrator enables MAX Core in Dolibarr Modules/Applications. MAX appears in the existing native menu; there is no separate login. Open /custom/maxcore/index.php, then Brand settings. The module uses the actual DolibarrModules _init/_remove lifecycle, native permissions, native session bootstrap and forced native CSRF token checking.

Prototype ID 610120 and permission IDs 61012001–61012003 were checked against pinned upstream; no global registry reservation is claimed. Activation refuses a conflicting permission owner. Registry allocation and third-party collision review are release gates before installation alongside arbitrary customer modules.

- read: MAX landing page.
- configure: landing/settings read and POST branding updates.
- admin: all MAX pages plus the read-only administration/status page.
- Native administrators follow Dolibarr's native all-rights semantics. External third-party users are rejected. Menus are convenience only; every server entrypoint checks enablement and permissions.

The MAX menu label is stable MAX. The configurable application display name is used on MAX page headings/titles. Edition/version and optional module slots are shown honestly: Orders/Documents, Mail, Customs, HKS and Reports are planned slots, not installed integrations. Turkish and English strings use native language files. Responsive CSS targets 360/390/768px; render checks do not certify accessibility or complete task usability.

## Brand versus legal identity

Only MAXCORE_DISPLAY_NAME, MAXCORE_LOGO and MAXCORE_FAVICON can be written. The input allowlist cannot write native company, tax, invoice, bank or validated-record fields. Settings are entity-scoped native Dolibarr constants, with no MAX business tables or ERP shadow objects. Display names are bounded plain text and HTML-escaped at render. Asset values are limited to bundled max.svg, max-icon.svg or none; no uploads, remote URLs or arbitrary file paths.

Defaults are MAX and bundled icons. Administrators can select these assets centrally without PHP edits. Customer-uploaded logos and full global/login-screen branding are NOT implemented. Favicon is a request-local native MAIN_FAVICON_URL override on MAX pages only, never a persisted change to native settings. Standard ERP menus, login, native pages and generated commercial documents retain native behavior and notices. This module cannot safely promise a completely white-labeled Dolibarr application; no core classes, native themes or legal attribution are removed.

Disabling removes native MAX menus/rights but preserves MAXCORE display constants for re-enable. No legal or financial record is deleted. To uninstall, disable through native UI, back up the display configuration, then remove only the module directory. Any later cleanup of MAXCORE constants is a separate deliberate operation. Roll back source to a compatible version and re-enable; this prototype performs no commercial schema migrations.

## Reproducible independent tests

Use a fresh disposable max_demo database and main's .env.example with random local credentials, Python 3.12+, curl, Docker/Compose v2 and enough disk. This is local/CI only; never run on customer or production installations.

1. Run python3 tests/maxcore/static.py.
2. Install test-only Playwright 1.56.0 and its Chromium browser.
3. Run bash scripts/maxcore_test.sh with the documented local-only Docker endpoint and disposable environment.
4. The script uses the official step2/step5 CLI installer from the pinned source, not P2 code, and refuses existing configured/nonempty databases.
5. Actual native tests activate, deactivate and reactivate the module, inspect menus/rights, create synthetic role accounts through native APIs, save brand settings and snapshot native legal/finance state.
6. Browser tests use native sessions, direct allowed/denied GET/POST requests, invalid-CSRF requests, brand changes, Turkish views, three mobile widths, menu rendering and disabled endpoint denial.
7. Verify after HTTP changes that issuer, native favicon setting and finance records remain untouched. The script stops containers on exit and preserves only ignored disposable data; resetting volumes requires the separate reviewed local procedure.

Only synthetic PNG/measurement JSON artifacts are uploaded for 7 days. Admin passwords, account credential JSON, config and installer HTML remain private ignored ephemeral test material, never artifacts. Native mail remains disabled by the private app network/PHP transport baseline; no SMTP/HKS or financial operations exist in this module.

The managed local execution environment became unavailable during implementation. Therefore local runtime claims are NOT RUN; actual evidence must come from GitHub Actions for the exact PR head. Test run links/results and any compatibility fixes are recorded on the issue and draft PR. No passing claim may be based on lint/static checks alone.

## Review and licensing

Review the supported bootstrap/layout, right IDs, CSRF/auth guards, asset/name validation and strict constant-write allowlist. Validate module registry and original MAX distribution licensing before commercial packaging; this prototype does not invent a license for all future MAX code. Preserve Dolibarr COPYING/COPYRIGHT and per-component notices in any distribution. Original MAX icons contain only the product brand; no customer assets/data were imported.

Scope remains a functional prototype awaiting review, not a commercially certified release. No merge, public/staging deployment, paid add-on, historical migration or real notification is authorized by this work.

CI also builds an installable prototype ZIP rooted at maxcore/, containing module source only (no installer/config/accounts). The artifact includes this ZIP alongside synthetic screenshots. Directory installation is what runtime CI exercises; Dolibarr's ZIP-upload UI is not separately automated. In pinned 24.0.1, accessforbidden() renders a native denial but does not itself set HTTP 403; MAX guards explicitly set 403 before invoking that native helper. No core helper is replaced.

Native CSRF compatibility detail: invalid tokens in 24.0.1 clear POST fields and expose errorcode=InvalidToken rather than terminating every request. The MAX bootstrap honors that native result with an explicit 403 before mutation; it does not replace native token generation/comparison. Browser tests also assert the brand is unchanged after this rejected POST.

## Targeted PR #13 review acceptance

The browser suite now asserts both body.scrollWidth and documentElement.scrollWidth against window.innerWidth at exactly 360, 390 and 768 CSS pixels, with zero tolerance. Home, brand settings and administration are checked at each width (nine checks), after fonts settle. Each record includes requested width, viewport, both scroll widths, both client widths and module bounding width. Measurement JSON and synthetic screenshots are saved before assertions, including on failure. A failure is not hidden with overflow-x suppression or scoped away from the native page: native Dolibarr layout overflow must be reported/fixed transparently.

At each width the real settings form is edited with keyboard input, Tab order and Enter submission, then checked for persisted values. This desktop Chromium keyboard flow does not emulate a physical phone's software keyboard or certify full accessibility.

Denied GET/POST and invalid-CSRF responses must have HTTP 403 AND exclude protected MAX containers, exact settings field identifiers (including hidden/script/comment payloads), saved brand values and the unique administration notice. Common native menu links alone are not treated as disclosure. Authorized responses are positive controls requiring the real form/body/administration content, so a universally empty page cannot pass. Pure assertion-regression tests deliberately feed leaking 403 pages and overflow at each width to prove the assertions fail.

Bundled asset enum restrictions remain unchanged; uploads/arbitrary URLs are unsupported and the existing remote-asset rejection stays in the native suite. Global supported-surface branding remains an open product requirement tracked in Issue #14, not delivered or expanded here. Native roles, forced CSRF, enable/disable and legal-identity/finance preservation tests remain enabled.
