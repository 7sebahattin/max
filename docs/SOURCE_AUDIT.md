# MAX source audit — scope, rules and review

Delivery lane: [Issue #10](https://github.com/7sebahattin/max/issues/10), coordinator [Issue #5](https://github.com/7sebahattin/max/issues/5). This independent `main`-based branch owns only this document, `scripts/max_source_audit.py`, `tests/test_max_source_audit.py` and `.github/workflows/max-source-audit.yml`. It does not change other lanes or implement MAX functional modules.

## Run and interpret

```bash
python3 scripts/max_source_audit.py
python3 scripts/max_source_audit.py --json
python3 -m unittest discover -s tests -p test_max_source_audit.py -v
```

Native Windows can use `python` instead of `python3`. Python 3.12 and Git are used in CI; no third-party Python dependencies, Docker, credentials or network access are required. `--repo /path/to/max` must select the actual repository/worktree root. The default root is the script's own checkout, not the current shell directory.

The scanner inspects the **Git index** (`git ls-files --stage -z`, `git cat-file` by validated object ID). Stage an intended source change yourself before auditing it. The tool never stages, commits, refreshes the index, resets files, opens working `.env` data, executes application code, invokes a shell, fetches repositories or deploys anything. Untracked files and unstaged modifications are intentionally outside the scan. CI checkout populates the index for the PR revision; the tested commit and run are recorded in the draft PR.

It does not walk the filesystem or directly traverse Git's object directories. Git provides selected indexed blob content. Linked worktree `.git` files are supported. Gitlinks (submodules) and symlinks are reported but never followed/read. Generated/upstream/credential/archive paths are identified from index metadata and their blobs are not opened. A warning means coverage is incomplete or review is needed, not a clean bill of health.

JSON schema 1 contains `status`, `exit_code`, `scanned_blobs` and `findings`. Every finding has `rule`, `severity`, `path_id`, `line`, `blob` and `excepted`. `path_id` is the full SHA-256 of the repository-relative Git filename bytes; `blob` is the indexed Git object ID, **not a raw source match**. Names, matches, lines of source, exception text, subprocess errors and credential values are never logged. Even filenames containing controls or token-like strings remain opaque. Line 0 denotes a file/structure finding.

| CLI exit | Meaning | CI policy |
| --- | --- | --- |
| 0 | pass: no findings in the scanned index | Pass |
| 1 | warn: review findings and/or exact exceptions remain | Pass only if **every** finding has an exact documented exception; no blanket allow-warnings switch |
| 2 | fail: at least one unsuppressed failure | Fail |
| 3 | error: Git/root/parse/resource inspection failed | Fail |

Argument syntax errors use argparse exit 2. Git subprocesses have 15-second timeouts and no optional locks or fsmonitor hooks; inherited Git selectors are removed to avoid switching repositories/indexes. Global/system Git configuration is excluded. No Git authentication or remotes are queried. Reports use fixed rule identifiers and metadata only.

## Rules

| Rule | Default | Interpretation |
| --- | --- | --- |
| CREDENTIAL_NAME | fail | Tracked `.env`/`.env.*` except `.env.example`, private-key/container names, credential JSON/YAML, conf.php or database dump names need removal/review even in fixtures |
| SECRET_MARKER | fail | Recognizable private-key headers, common GitHub/AWS access-key markers and long quoted password/API/access-token assignments; applies to docs and tests too |
| IDENTITY_LITERAL | warn | Literal assignment/JSON mapping, define(), or native dolibarr_set_const() legal/application/tenant identity sink |
| UPSTREAM_PATH | fail | Root htdocs/vendor/upstream/dolibarr/node_modules, any .local/.git segment, or PHP under nested vendor/upstream/dolibarr; no upstream/ERP vendoring |
| ARCHIVE_REVIEW | fail | Tracked ZIP/TAR/GZ/PHAR/7z requires review; compressed source/data is not silently treated as scanned |
| SYMLINK_SKIPPED / SUBMODULE_SKIPPED | warn | Target/dependency content not inspected |
| BINARY_SKIPPED | warn | NUL-containing or non-UTF-8 content not scanned |
| SCAN_LIMIT | fail | Blob exceeds 1 MiB; no silent partial-content scan |
| UNMERGED_INDEX | fail | Resolve unmerged index entries before review |
| EXCEPTION_INVALID | fail | Invalid, duplicate, stale or unmatched exception; all exceptions fail closed |

The overall index is capped at 5,000 records; exceeding it reports an audit error. These bounds prevent an unlimited scan of large vendored/generated content; change limits through reviewed code, not an unlogged bypass.

### Product neutrality conventions

Identity rules recognize key/sink conventions such as app_name/application_name, tenant_name/domain, company_name/domain, legal_name, tax_id, VKN, bank_iban and native MAIN_INFO_SOCIETE identity constants. They report literal values flowing **directly** into those assignments or native configuration calls. They do not implement full cross-function data-flow analysis.

The product label MAX is allowed for application-name keys, including define(). Using that same string as a legal company identity still needs review. There is no denylist of historical companies, real customers or customer domains. Ordinary upstream attribution, repository URLs and archived documentation references are not banned.

Production code should obtain legal identity from native legal-company configuration and display identity from central brand configuration. Environment/config access and interpolation placeholders are preferable to literal tenant data. Synthetic identity values under `tests/`, `docs/` and Markdown/RST documentation are allowed. Secret-marker checks **still apply** there: build synthetic regression tokens from fragments in test code rather than committing a full recognizable credential marker. Test fixtures are never authorization to store real customer data.

This convention can miss aliases, computed/encoded values, other keys, dynamic PHP, template strings or cross-file flows. It can also flag a deliberate configuration/setup literal. Review such cases using the exact exception process below, not by renaming a key to evade the rule. It does not prove a rendered UI, email, document or branch is company-neutral.

## Audited false positives and exceptions

First fix the source or remove the tracked credential/upstream material when it is unsafe. A real committed credential requires rotation and separate history review; suppressing the finding is not remediation.

For a justified false positive, obtain an issue/PR review record and add an entry to the single policy block at the end of **this document**. Required fields are `rule`, `path_id`, integer `line`, exact indexed `blob` ID, a reason of at least 20 characters and a GitHub issue/PR URL in `review`. No wildcard, filename-only, broad directory exclusion or silent ignore is supported. The exception is bound to the exact path, line, rule and blob; changing the blob invalidates it. Duplicate, stale, malformed or unmatched entries invalidate the exception set and fail CI. GitHub review approval itself is **not** fetched/verified by this offline tool; a human reviewer must confirm the linked authorization and reasoning.

Find the local path using the path ID, without publishing filenames or content to CI logs:

```bash
# Local lookup only; prints opaque path IDs followed by Git names for your review.
# Do not paste this private mapping into CI/issues if names could contain secrets.
git ls-files -z | python3 -c 'import sys,hashlib; [print(hashlib.sha256(p).hexdigest(), repr(p)) for p in sys.stdin.buffer.read().split(b"\0") if p]'
# Get the indexed blob ID locally via git ls-files --stage; never paste file contents.
```

Exception entries are review artifacts, not a place for credential values or actual customer identity. The report never prints reason/review text. Excepted findings stay visible with `excepted=true` and status warn. CI permits warnings only when all findings match these explicit records; review the policy diff as carefully as the source diff. Current policy is empty.

## What is and is not proven

Portable tests use real temporary Git indexes and synthetic blobs. They cover identity-positive/negative cases, credential names/markers, synthetic fixture rules, secret/filename suppression, generated/upstream paths, symlinks, submodules, linked worktrees, malicious names, binary/size limits, unmerged stages, exceptions, JSON/exit behavior and unchanged index/working files. The pinned CI runs tests and the real audit on Ubuntu and Windows. This is static tooling, not a Windows/WSL/Desktop or Dolibarr runtime certification.

**This smoke audit is additional defense, not a substitute for vetted secret scanning, CodeQL or GitHub security tooling.** It does not scan committed history, untracked secrets, binary/archive payloads, dependencies/submodules, generated `.local` data, Docker image layers, external checkouts, upstream licenses, copyright/trademarks, runtime execution, complete data flows, API permissions, databases, SMTP/HKS or production infrastructure. Licensing/distribution obligations and actual company-neutral UI require separate review. A pass means only that the enabled heuristics found no issue in the indexed text they covered.

Review the four-file scope, index-only command allowlist, rule coverage, exception diff and CI evidence. Rollback is a revert of these four files; the audit creates no service/database/filesystem mutation to undo. Keep the PR draft and unmerged. No deployment, financial action, email/HKS send, real-data migration or legacy/upstream edits are part of this work.

<!-- MAX_SOURCE_AUDIT_EXCEPTIONS
[]
-->
