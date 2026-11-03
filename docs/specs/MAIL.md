# MAX Mail Center — reusable contract and tests (draft)

## Purpose
Provide optional multi-mailbox correspondence and attachment workflows integrated with native Dolibarr accounts and MAX Orders/Customs. Native Dolibarr mail collector MUST be evaluated first; create new components only for proven gaps.

## Incoming process
1. Configure mailbox identities and IMAP endpoint through private per-installation secrets and role-based settings.
2. Poll via scheduled jobs with bounded time-window and rate limits. Preserve UID/UIDVALIDITY or equivalent unique identities, mailbox account and immutable Message-ID as dedupe inputs; Message-ID alone is not reliable across all mailbox situations.
3. Record message/attachment metadata and policy-limited storage of original files. Restrict any display of email content to authorized users.
4. Exact sender-address allowlists can be used for *selected automatic document workflows*; never infer exact trust from a matching domain or Reply-To alone. Allowlist is not a replacement for authentication and content validation.
5. On-demand attachment extraction may access IMAP, but a fetched binary must pass type/size/content checks before processing.
6. A mailbox button such as 'Orders' is only a search/filter. It does **not** prove that an attachment is a PO/LL/PC; document classification comes from PDF content and validation.
7. Unknown document type, parse failures, duplicate attachments and ambiguous ERP matches must generate explicit review/skip reasons.
8. An intake retry must not recreate a deleted business document without approved user action.

## Outgoing process
- Compose → preview → authorized approval → send using the configured SMTP provider; separate `draft`, `approved`, `sending`, `sent`, `failed`, `unknown_outcome` states.
- Keep idempotency record and a durable outbox. A timeout or uncertain response **must not trigger blind automatic retry**.
- For document-driven replies, generate preview from the *approved data snapshot* to prevent race conditions between approval and send.
- No real SMTP delivery during MAX development; fake transport/capture service only.

## Translation and summarization
- Optional provider-agnostic translation with source/target language and human correction.
- Do not send personal/business sensitive text to an external AI provider before explicit deployment configuration and data-processing review.
- Preserve original message; translations/summaries are derivative, editable and never replace original evidence.
- Avoid auto-sending machine translated text.

## Integration boundaries
- Message/attachment → MAX Orders document intake by explicit source and content classification.
- Message/attachment → MAX Customs declaration draft only after validation.
- Sending a mail must not automatically validate a Dolibarr invoice/order or send HKS notifications.

## Synthetic tests
- Two mailbox accounts with same Message-ID but different UIDs: distinguish according to documented cross-mailbox policy.
- Identical PDF downloaded twice: no duplicate order document from same logical source.
- Sender from lookalike domain or altered Reply-To: not trusted by exact-address allowlist.
- Malformed, password-protected or oversized PDF: quarantined and visible review reason.
- Translation returns malformed content: original still displayed and no automatic send.
- SMTP timeout after server acceptance: `unknown_outcome` requiring reconciliation.
- Role with read-only mail access cannot approve outgoing messages.
- Files and logs never expose passwords, raw credentials or unredacted third-party content.

## Review gate
Confirm whether native Dolibarr collectors and messaging can implement each requirement before introducing new mail tables or background workers.
