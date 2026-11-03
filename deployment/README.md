# MAX Deployment

Target **staging** hostname: `max.sacai.tr`. This does not indicate an active server.

See [staging readiness checklist](../docs/STAGING_CHECKLIST.md) and [security baseline](../docs/SECURITY.md).

- No production keys or .env files are stored here.
- Do not assume a reachable DNS record, public server, Docker runtime, SSL/TLS certificate, database or storage.
- Do not deploy automatically from `main` or from a pull request.
- Select a pinned compatible Dolibarr tag only after verifying upstream compatibility and licensing.
- Restrict access to the development host and keep test data synthetic.
- Require a separate owner-approved deployment procedure and rollback plan.

Reproducible deployment code will be added in a later reviewed PR when an executable development environment is available.
