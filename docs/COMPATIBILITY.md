# Pinned upstream and runtime compatibility

Verified 2026-11-03 against primary upstream sources. [Official latest stable release](https://github.com/Dolibarr/dolibarr/releases/tag/24.0.1): **24.0.1**, published 2026-09-07, neither draft nor prerelease. `upstream.lock.json` records the annotated tag, resolved commit and SHA-256 of the actual downloaded archive. The tag is unsigned: origin/checksum verification is not a claim of cryptographic publisher signature verification.

| Component | Selection | Evidence / limit |
| --- | --- | --- |
| Dolibarr | 24.0.1 / b7958385f00a92219f76a4dfea67dae93df97a25 | Official release API and tag object; archive downloaded and checksum verified |
| PHP | 8.3.36 / Apache on Debian Bookworm | Official PHP image pinned by digest; CLI executed locally |
| PHP extensions | mysqli, mbstring, gd, curl, calendar, simplexml, intl, zip, soap | Build installs these; JSON, sessions, XML/DOM and OpenSSL supplied by PHP image; runtime check verifies selected extensions |
| Database | MariaDB 11.4.13, immutable image digest in Compose | Native mysqli adapter; full build/SQL roundtrip is a CI acceptance check, not yet a local compatibility claim |
| Mail transport | Disabled | PHP sendmail returns failure; private runtime network has no Internet path; no real accounts/providers |
| IMAP | Deferred | Choose extension or client after native EmailCollector pilot; no claim of Mail Center compatibility yet |

[Installer checks at the pinned commit](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/install/check.php) set PHP warning minimum 7.2 and maximum 8.5. PHP 8.3 is within those checks. These bounds are not a reason to deploy end-of-life PHP. `composer.json.disabled` has a broader minimum and is not used to resolve MAX dependencies. Native mysqli support is present under `htdocs/core/db/mysqli.class.php`.

Full installation, schema creation, enabled native modules and mobile workflows are explicitly P2 checks. Passing runtime preparation does not prove all Dolibarr functions compatible. Before staging, rerun the pilot and security review on the exact locked image; record PHP package revisions and image ID. Debian packages installed during a build come from current repositories, so the base/source pins do not alone provide a byte-identical application image. Promote an approved built image by digest; refresh it through reviewed security updates.

Upgrade procedure: check stable release and security advisories, resolve official tag, download and review archive, update lock/checksum, rebuild images, run preparation and full synthetic pilot, back up and rehearse rollback, then request deployment approval. Never use `latest` or silently follow a moving branch.
