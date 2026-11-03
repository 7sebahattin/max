# MAX Validation Strategy

No executable checks have been run for this branch because the prior cloud workspace failed to start and no command runner is available from the GitHub-only workflow.

## Planned test layers
- Static repository policy (brand independence, no accidental secrets, no upstream core edits).
- Dolibarr version compatibility and module enable/disable test.
- Demo ERP workflow with synthetic partner, product, warehouse, lot, order, shipment, invoice and payment.
- Role isolation and access controls.
- Phone-width/mobile usability checks.
- Mail test doubles: fake IMAP and SMTP; no external outgoing messages.
- HKS service stubs: dry-run XML, approval boundary and ambiguous-outcome handling.
- Advanced Orders fixture tests: PO/LL/PC revisions, source precedence, idempotence and manual overrides.
- Document and report formatting checks.
- Backup/restore and safe deployment rollback rehearsal.

Do not claim **PASS** for an unexecuted test. Record tested commit, environment and evidence within the related PR.
