# MAX Deployment

Target **staging** hostname: `max.sacai.tr`. This does not indicate an active server.

See [staging readiness checklist](../docs/STAGING_CHECKLIST.md) and [security baseline](../docs/SECURITY.md).

- No production keys or .env files are stored here.
- Do not assume a reachable DNS record, public server, Docker runtime, SSL/TLS certificate, database or storage.
- Do not deploy automatically from `main` or from a pull request.
- Use the reviewed `upstream.lock.json` and runtime image digests; complete functional compatibility and license review before staging.
- Restrict access to the development host and keep test data synthetic.
- Require a separate owner-approved deployment procedure and rollback plan.

Local-only scaffolding is documented in [local development](../docs/LOCAL_DEVELOPMENT.md). It is not a production manifest. Host deployment remains a later separately approved phase.
