# PR-01 acceptance and review gate

| Criterion | Automated evidence | Review requirement |
| --- | --- | --- |
| Scope, exclusions, branding, security, roadmap and ADR documented | scripts/check.py required docs and local links | Owner architecture review |
| No vendored core, local config, dumps or obvious credential patterns | scripts/check.py and git diff review | Basic pattern check is not a full secret scanner; inspect diff |
| Stable official release pinned | prepare.py verifies SHA-256 | Review release URL, commit and unsigned-tag limitation |
| Reproducible local preparation | Compose validation, image build, PHP extension/SQL temporary-table test, installer HTTP check | P2 full install intentionally deferred |
| Safe defaults | Internal network, loopback port, no DB publication, disabled sendmail, no cron/providers | Review Compose and PHP config |
| License/attribution inventory | docs/LICENSE_REVIEW.md | Owner resolves MAX licensing before redistribution |
| Deployment prerequisites and rollback | docs/STAGING_CHECKLIST.md | Owner supplies infrastructure and separate deploy approval |

Run `python3 scripts/check.py` and `scripts/smoke.sh` after setup. GitHub Actions runs the same checks. An installer HTTP response is not proof of installed/functional ERP. Role/mobile/branding/module/backup tests are not claimed by PR-01.

Review must examine immutable dependency references, source integrity, safe networking, writable paths, commercial entity ownership and roadmap gates. Do not merge automatically or advance to P2 without explicit owner approval. PR-01 rollback is to revert its commit(s); no remote runtime or database migration exists. For the disposable local stack stop containers; retain or explicitly reset local data as documented.
