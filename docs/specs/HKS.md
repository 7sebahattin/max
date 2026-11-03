# MAX HKS — optional regulated integration contract (draft)

This is an optional industry-specific extension. It is not a default dependency of MAX Core nor an ordinary invoice/stock ledger. **No real SOAP submissions are authorized by this document.**

## Conceptual entities
- Integration profile: one installation's configured authorized firm identity, endpoint settings, credentials stored securely.
- HKS reference catalogue and known code mappings; explicit refresh status and provenance.
- Producer/shipper/recipient party records with per-role field restrictions, validation and controlled editing.
- Künye/tag query and document/label mapping records.
- Notification draft with source reference (operator form or Customs declaration), payload validation warnings and review state.
- Remote attempt: immutable operation ID, request hash, confirmed/failed/unknown result, time and non-sensitive external reference.
- Incoming and sent notification views, reconciled from trusted service responses.

## Workflow and safety
1. Create/edit draft using **one shared validation service** for UI and Customs-sourced drafts; never maintain two drifting rule copies.
2. Validate required party identity, producer/warehouse role, action type, goods quantities and units using current verified HKS/legislative rules. Where verification is incomplete, block external send instead of inventing a rule.
3. Preview the exact payload after validation, without credentials.
4. Require an explicit, permissioned **Send notification** action and revalidate immediately before remote interaction.
5. Persist send intent and stable idempotency/correlation data *before* external effect; serialize competing sends of the same draft.
6. Treat timeout/connection drop as `unknown_outcome`, not a definitive failure. Reconcile through an authenticated status/query pathway before any reattempt.
7. Record success/remote reference only on confirmed service result. Never mark an unknown submission as sent or safe to re-send.
8. Use mock SOAP and synthetic records for development and CI; integration endpoint/config must remain disabled until separate owner approval and compliance checks.

## Roles
Reference viewer, draft creator/editor, approver, sender, regulator-integration administrator and audit reviewer. No global write role should automatically include remote send privilege.

## Dependency boundaries
- Dolibarr native object IDs may reference product/partner/order/shipment for context; no hidden parallel stock decrement.
- Customs → HKS produces *draft data only*.
- The HKS module must be independently installable/disableable for deployments that do not use the relevant sector workflow.

## Synthetic acceptance cases
- Valid draft under fake reference dictionaries → reviewable preview; no network call.
- Invalid required party identity → blocked at both UI and Customs bridge with identical validation result.
- Unapproved user tries to send → denied and audited.
- Two simultaneous send attempts for one draft → no duplicated remote operation.
- SOAP timeout after provider may have accepted request → `unknown_outcome`, no auto retry; review/status query needed.
- Confirmed remote response → result and reference stored; source draft immutable except allowed corrective follow-up.
- Credentials, identity numbers and remote payload secrets never appear in CI logs or GitHub.

## Pre-live regulatory gate
Verify the exact current HKS SOAP services, allowed notification types, legal role/party checks, electronic document scenario and data retention requirements through official sources in the release environment. P2/P3 prototype tests do not imply government certification.
