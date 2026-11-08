# PR-01 progress and verification

Historical PR-01 record. Current whole-project status, remaining acceptance scope and evidence limitations: [PROJECT_STATUS.md](PROJECT_STATUS.md).

2026-11-03 — resumed existing draft [PR #2](https://github.com/7sebahattin/max/pull/2), branch `chore/pr-01-bootstrap`; [Issue #1](https://github.com/7sebahattin/max/issues/1) read in full including comments. Existing scaffold preserved and expanded; no duplicate PR.

Completed: P0/P1 architecture, ADR, native-first scope, read-only source audit, product/legal identity boundaries, isolated-customer model, safe local preparation, source/image pins, CI, acceptance criteria and staging runbook. No functional MAX module or upstream core modification.

| Check | Local result |
| --- | --- |
| Official release and annotated tag resolution | PASS: 24.0.1, stable, commit recorded in lock; tag unsigned |
| Download archive SHA-256 and prepare script | PASS; official archive verified and local tree prepared |
| PHP CLI | PASS: pinned PHP base executes 8.3.36 |
| PHP syntax | PASS: tests/runtime.php |
| Repository policy, Python compilation, shell syntax, Compose config | See final PR validation comment for tested revision |
| Full image build / SQL / installer smoke | BLOCKED locally: MariaDB image registration failed with no space left on device on 7.9 GB workspace using vfs; CI runs the full smoke |
| Native ERP installation, role/mobile pilot | NOT RUN: P2 after approval |
| Branding implementation, mail/HKS integration | NOT IMPLEMENTED: later phases |
| DNS / HTTPS / host / database readiness | UNVERIFIED; no host access provided or deployment attempted |

Follow the PR checks for CI results on the exact head SHA. A failed or pending workflow is not acceptance. Full-runtime compatibility remains provisional until the smoke passes; functional compatibility requires P2 regardless.

Owner actions: architectural and code review; approve next phase separately; identify staging host/zone owner, intended DNS target and access policy; arrange secret management and backups; resolve original MAX code licensing before redistribution. No credentials belong in review comments. Merge, staging deployment and real external effects remain separate deliberate approvals.
