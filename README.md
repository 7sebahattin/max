# MAX

**MAX** is a modular, brand-configurable business operations and ERP platform.

## Status

Pre-development bootstrap. No Dolibarr deployment, running application or production service is implied by this repository.

- Repository: `7sebahattin/max` (private)
- Target staging hostname: `max.sacai.tr` (requires DNS, TLS and hosting verification)
- ERP foundation: official Dolibarr release, installed separately and pinned after compatibility verification
- MAX extensions: installable Dolibarr external modules under `htdocs/custom`
- First milestone: [Issue #1](https://github.com/7sebahattin/max/issues/1)

## Principles

- Brand identity, company identity, document settings and business configuration must be editable without source changes.
- Prefer native ERP functionality before designing custom modules.
- Do not migrate old business data or copy entire legacy implementations.
- Keep each customer deployment logically and physically isolated.
- Never commit secrets, private messages, real customer data or production credentials.
- Do not perform real external messages, regulated submissions, inventory mutations or deployment without approval.
- Preserve upstream Dolibarr copyright, trademark notices and license obligations.

## Workflow

All changes go through focused feature branches and **draft pull requests**. Do not merge or deploy without explicit owner approval.

See `docs/` once PR-01 is reviewed and merged.

## Licensing

The upstream Dolibarr framework has its own open-source license terms. Licensing of original MAX code and bundled dependencies must be decided and documented before redistribution.
