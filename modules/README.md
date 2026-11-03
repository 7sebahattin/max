# MAX External Modules

This folder will hold **MAX-owned extension source code** once the installed Dolibarr version and APIs have been verified.

Proposed modules:
- `maxcore`: brand, authorized navigation, shared configuration and permissions.
- `maxorders`: advanced order documents and workflow.
- `maxmail`: email/translation workflow after native collector evaluation.
- `maxcustoms`: customs declarations and related documents.
- `maxhks`: optional regulated HKS integration.
- `maxreports`: approved reports/Excel exports not covered by Dolibarr.

During local build/install, package each valid external module using the pinned version's supported `htdocs/custom` extension layout. Do not mirror upstream Dolibarr or write directly into its core tree.

**No executable module has been installed or tested in PR-01.**
