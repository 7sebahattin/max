# MAX Licensing and Commercial Distribution — to be reviewed

The Dolibarr ERP framework is an upstream open-source project with its own license, copyright and trademarks. A new product brand **does not erase** license/notice obligations.

Before any MAX redistribution or commercial sale:
1. Verify exact Dolibarr version, corresponding LICENSE/COPYING content and author notices from the source release.
2. Identify all third-party extensions, assets and dependencies and their exact licenses.
3. Determine which MAX code components are derivative/combined works and any source redistribution obligations under the applicable licenses.
4. Preserve required attribution and license files; avoid presenting upstream source as independently written MAX code.
5. Review use of names and logos against Dolibarr trademarks.
6. Do not add a blanket restrictive/proprietary LICENSE file until legal review of the planned distribution model.
7. Document customer-facing terms, support responsibilities and security updates.

This is an engineering compliance checklist, not legal advice.

## Verified inventory for PR-01

| Component | Version / origin | License evidence and action |
| --- | --- | --- |
| Dolibarr (downloaded separately) | 24.0.1, pinned commit in lock | Root COPYING is GPLv3; main.inc.php header is GPL-3.0-or-later; preserve COPYING, COPYRIGHT and per-file notices |
| Bundled upstream libraries | Within unchanged official archive | Multiple component licenses under htdocs/includes; inventory exact redistributed package before sale; do not relabel all libraries GPL |
| PHP / Apache / Debian | Locked official PHP image | Preserve image/package license notices; PHP License, Apache-2.0 and individual Debian package terms need distribution inventory |
| MariaDB | Locked official 11.4.13 image | Server GPLv2 and bundled package notices; review exact distribution and offer required source where applicable |
| Original MAX docs/scripts/config | This repository | No new blanket license chosen; owner decision pending before external distribution |
| Future extension / translation / XLSX libraries | None added in PR-01 | Review license, security and commercial terms before adding; no paid modules authorized |

Primary evidence: [Dolibarr COPYING](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/COPYING), [Dolibarr COPYRIGHT](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/COPYRIGHT), [PHP image packaging](https://github.com/docker-library/php), [MariaDB image packaging](https://github.com/MariaDB/mariadb-docker). This is an inventory, not a licensing grant for original MAX code or a claim that all future modules have the same license.
