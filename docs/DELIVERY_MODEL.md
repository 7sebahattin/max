# MAX — Parallel Development and Integration Model

Status: proposed coordination method for the approved MAX scope. Tracking: [GitHub Issue #5](https://github.com/7sebahattin/max/issues/5).

## Product constraints
- Official pinned Dolibarr base, no changes to upstream core.
- Customer-neutral MAX branding with independently configurable legal issuer/company identity.
- Separate isolated installation per customer for now; no shared multi-tenant ledger.
- Existing reference code is **read-only evidence**; no code, historical data or identifying brand/logo migration.
- Native ERP commercial records are authoritative. Specialized workflows add linked metadata, not duplicate accounting/stock.
- No live HKS/mail/invoice effects or `max.sacai.tr` deployment without explicit approval.

## Concurrency model

| Lane | Owner | Current scope | Branch | Owned paths |
| --- | --- | --- | --- | --- |
| A | Codex session A | P2 native installation, ERP functional CI, gap analysis | `feat/p2-native-dolibarr-pilot` | `tests/p2_*`, `scripts/p2_*`, `docs/P2_NATIVE_GAP_MATRIX.md` |
| B | Codex session B | Independent local developer tooling, Windows/WSL2 onboarding and repository safety | `feat/dev-onboarding-and-ci` from `main` | NEW `docs/DEVELOPER_WINDOWS.md`, `scripts/dev_doctor.py`, `tests/test_dev_doctor.py` |
| C | ChatGPT/GitHub | Requirements contracts for orders, mail, customs, HKS and cross-module design | `docs/max-functional-blueprints` from `main` | `docs/specs/*`, `docs/DELIVERY_MODEL.md` |
| D | Product owner | Feature priorities, business-rule decisions, merge and stage-deployment approval | None | Approvals; no direct SQL/PHP |

**No two lanes edit the same file.** If a shared file must change, identify its owning PR and request a reviewed follow-up. Do not copy another branch's unpublished commits without documenting the dependency.

## GitHub issue → PR lifecycle
1. One issue contains scope, owner/lane, exact branch and allowed paths, dependencies, acceptance tests and explicit exclusions.
2. Each lane opens a separate **draft PR against `main`**, unless a reviewed stacked-PR plan is explicitly needed.
3. Every PR has actual changed files, reproducible test evidence, security notes, dependencies and a rollback plan. A chat-only statement of completion does not count.
4. CI runs against PR commits; failures are repaired by the owning lane. Do not merge on unverified claims or after skipped tests disguised as PASS.
5. ChatGPT reviews code and contract alignment when invoked from the conversation; it can leave GitHub review comments, but **does not run or monitor continuously in the background** without a separately approved scheduled mechanism.
6. Owner approves merges and public/staging deployments separately. Passing local CI does not authorize a staging push.
7. After merge, branch owners rebase/recreate their next branch on updated `main`; a dependent PR must identify its baseline.

## Integration order and parallel waves

### Wave 1 — Current, in parallel
- Lane A: finish P2 synthetic ERP business workflow tests including warehouse, lot, partial shipment, draft invoice, mobile capability and documented actual gaps.
- Lane B: safe Windows/WSL2/Docker developer onboarding; independent read-only environment doctor.
- Lane C: generic workflow contracts, security invariants and synthetic acceptance fixtures for specialized modules.

**Merge order:** B and C can be reviewed/merged independently; A only after its runtime assertions and native gap matrix meet P2 acceptance. No staging deployment.

### Wave 2 — After P2 native overlap is documented and core contracts reviewed
- Codex A: MAX Core extension skeleton, configurable product branding, legal-entity separation, authenticated menus, permissions, module flags.
- Codex B: Mail Center fake-transport prototype and attachment intake, only where native Dolibarr collector does not meet documented requirements.
- ChatGPT: specialized Orders document state/field-priority refinements, Customs/HKS safe draft flows, code review and acceptance evidence.

**Integration gate:** MAX Core published interfaces, roles and company scope tested before writing modules that depend on them.

### Wave 3 — Function modules (parallel)
- Codex A: advanced orders/document workflow on native ERP order IDs.
- Codex B: Customs declarations + optionally independent reporting in its own file paths, depending on mail work.
- ChatGPT: integration test definitions; HKS SOAP approval/ambiguous outcome threat modeling and interface review. No real remote submissions.

### Wave 4 — Contracted integration and acceptance
- Integrate Mail → Orders, Mail → Customs, Orders ↔ Customs → HKS **draft**, plus reports.
- End-to-end synthetic scenarios, role isolation, mobile screens, upgrade safety and customer-neutral branding.
- Stage `max.sacai.tr` only after fresh owner approval plus DNS/TLS/host/backup checks.

## Control limits
Keep no more than **three active coding/documentation PRs** in the current wave. Blocked tasks may be split if their files and dependencies can be isolated. Do not set a time-based deadline unsupported by available developer capacity.

## Definition of done per PR
- [ ] No forbidden source, identity, data or production endpoints.
- [ ] Native-vs-custom decision and actual behavior documented.
- [ ] Automated test result linked to the exact commit, or untested behavior explicitly marked.
- [ ] All cross-module contract changes reviewed.
- [ ] No direct writes to Dolibarr core business tables or duplicate ledgers.
- [ ] Rollback and migration effects described.
- [ ] Product owner approved merge; live/stage approval considered separately.
