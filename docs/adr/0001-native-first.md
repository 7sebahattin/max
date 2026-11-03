# ADR-0001: Native ERP with external MAX modules

Status: proposed for owner architectural approval in PR-01. Source: [Issue #1](https://github.com/7sebahattin/max/issues/1).

Dolibarr 24.0.1 owns all commercial entities and accounting/stock state. MAX owns only supplementary workflows and references native IDs. Upstream is downloaded separately with a reviewed checksum and mounted read-only. No fork or vendored core is permitted.

Use external module descriptors, ModuleBuilder conventions, hooks, triggers, extrafields and document generators. Native object APIs perform commercial changes; no direct stock/ledger writes. Module classes and SQL prefixes remain stable when a display brand changes. Each module registers scoped permissions and menus within the same Dolibarr session. Every custom endpoint loads the Dolibarr bootstrap, checks module enablement and permission, checks CSRF for mutation, and escapes output. Module IDs must be allocated/checked before implementation; none are invented here.

One customer receives one installation, database, document root, secrets, cron identity and backups. Shared SaaS tenancy and paid MultiCompany are outside this baseline. Product settings belong to maxcore; legal identity remains in native company, bank and document configuration.

Native modules must pass the P2 demo before any proposed replacement. Specialized parsing, provenance and approval history may extend native orders/shipments; they never become another order or financial authority. The initial alternatives rejected are a core fork, standalone mail login, a second stock ledger and wholesale legacy port.

Consequences: upstream upgrades remain reviewable; hooks and native overlap must be rechecked on upgrades. Generic branding may require supported theme/assets/hooks, and mandatory upstream notices remain visible where required. Packaging consumption and general export/vehicle forms remain deferred.

Review evidence: [compatibility](../COMPATIBILITY.md), [source audit](../LEGACY_FUNCTIONAL_MATRIX.md), [pilot protocol](../NATIVE_PILOT.md). No functional MAX module is implemented by this decision.
