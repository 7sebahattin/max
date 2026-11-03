# MAX Mail native protocol pilot — Issue #22

Dolibarr **24.0.1**, upstream commit `b7958385f00a92219f76a4dfea67dae93df97a25`, pinned by `upstream.lock.json`. No core changes. No MAX Mail module or replacement transport is introduced.

## Run

Create a disposable `.env` using `.env.example` (random database passwords), install Playwright 1.56.0 and Chromium, then run:

```sh
python3 -m unittest discover -s tests/mail -v
COMPOSE_PROJECT_NAME=max-mail-unique bash scripts/mail_test.sh
```

Use a new checkout/project each run. The wrapper refuses pre-existing containers, volumes **and networks before arming cleanup**; only its fresh project is removed, including its database volume. A real Docker negative test preserves an intentionally existing network. The fake server has no published port, lives on Compose's internal sandbox, and has no proxy environment. PHP sends remain disabled; the unchanged P2 local capture uses a local process only. Never use real credentials/providers.

`tests/mail/compose.yaml` extends the existing Compose file without editing it. Its official Python image is digest-pinned. The fake server is an explicitly limited RFC3501 fixture, not a production IMAP implementation or TLS certificate test. It supports LOGIN, exact Orders mailbox selection, UID SEARCH/FETCH/STORE, literals, flags, epochs, reconnect, authentication/fetch failure. Unsupported commands fail closed. Commands are logged as names and account aliases only.

## Native source findings and executable coverage

References are in the verified upstream archive under `htdocs/`:

| Capability | Native surface / evidence | Status and product implication |
|---|---|---|
| Collector configuration, filters/actions, active scheduled polling | `emailcollector/class/emailcollector.class.php`: `create`, `doCollect`, `doCollectOneCollector(0)` | Actual native configuration, real mode-0 polling and cron invoked in CI; no inactive-config-only claim |
| IMAP backend | `MAIN_IMAP_USE_PHPIMAP=1`, bundled `includes/webklex/php-imap/vendor/autoload.php` | Supported pure-PHP backend avoids absent PHP IMAP extension; baseline default backend requires that extension. No new Composer dependency |
| Two accounts and mailbox selection | Actual collectors A/B against isolated fake server, Orders folder | A folder is not a document type; only agenda `recordevent` enabled, no ticket/project/order creation |
| Duplicate semantics | `recordevent` calls `ActionComm::fetch` by Message-ID | Entity-wide Message-ID dedupe suppresses a distinct message with the same Message-ID in another account. **GAP**, not account+UIDVALIDITY+UID identity |
| Restart, replay, changed epoch, retry | `tests/mail/pilot.py` changes server state between actual polls | Unique Message-ID after epoch change creates one agenda action; old Message-ID remains suppressed; failed authentication followed by retry does not duplicate actions |
| Attachments | Native Webklex attachment objects | Name, size, content hash and detected MIME asserted. Bad PDF is opaque synthetic bytes; no PDF interpretation or guessed PO/LL/PC |
| Private attachment intake/quarantine | Generic agenda `recordevent` does not provide a Mail Center quarantine repository | **NOT IMPLEMENTED**. Native ticket/project/recordjoinpiece actions can write attachments but are deliberately not configured. No false role-checked download claim |
| Sender attribution | Native `email_from`, From/Reply-To synthetic lookalike fixture | Header attribution is recorded, not authenticated trust. No provider/DKIM verification or permission based on sender. Future exact sender policy requires review |
| Native administration ACL | `admin/emailcollector_list.php` and `emailcollector_card.php` admin guards | Browser checks native login, denied response status and protected-body absence for unprivileged reader; admin sees configuration. Native denial may be HTTP 200 and must include actual denial content |
| No financial effects | Full native partner/order/line/shipment/invoice/stock/bank/payment and legal-constant snapshot | Unchanged across polling/browser checks; only agenda/collector bookkeeping changes |
| TLS/provider interoperability | Fake internal plaintext only | **NOT TESTED**; do not use plaintext fixture configuration in production. Native extension connect string includes `novalidate-cert`; pure backend config validates certificates. Production certificates/provider auth require separate verification |

## MAX Mail functional requirements (proposal, not implementation)

Persist a bounded, private incoming ledger keyed by installation/account/UIDVALIDITY/UID with content digest and source metadata. Message-ID is supplementary evidence, never a global mailbox identity. UIDVALIDITY reset must reconcile hashes/references without silently resurrecting deleted business documents. Two accounts require distinct authorization and identity scope. Failed intake needs explicit retry state and bounded backoff; parsing failures need review status, not guessed classification. Preserve immutable message/document provenance and exact From/Reply-To distinctions without treating either as trusted authorization.

Provide role-checked attachment retrieval outside webroot, size/MIME/content validation, untrusted-name handling, quarantine for malformed/unknown documents, explicit customer/order matching and human review before any Orders intake. The present native agenda collector alone does not satisfy those requirements. No automatic precedence, order approval, stock decrement or invoice creation is proposed here.

### No-send and uncertain-outcome proposal

Future outgoing flow: draft → human approval → durable outbox → sending → sent, with a separate **unknown_outcome** state if a fake SMTP server accepts DATA but the final 250 response is lost. Reconcile using durable outbox identity, Message-ID and transport evidence before any explicit retry; never blind-resend an uncertain accepted message. A future fake SMTP test should simulate this boundary. **No SMTP sender, translation API, outgoing queue or provider integration is implemented in this PR.**

## Evidence and acceptance

Local: three real socket IMAP unittest cases passed. Full native runtime local execution is **BLOCKED** by the managed runner's 8 GB/vfs Docker storage; it is executed on disposable GitHub Actions instead. CI results and exact tested commits are appended below, including failed attempts. Safe artifacts are numeric/hash reports and command-name transcripts only; no state/password/raw mail/native debug output or installer HTML. New evidence does not imply production certification.

Rollback: disable/delete the disposable native collectors or discard the isolated project. No core/database migration or live deployment exists.
