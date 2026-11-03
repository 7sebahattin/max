#!/usr/bin/env python3
"""Read-only audit of Git-index blobs, not working files, history or dependencies."""
from __future__ import annotations
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys

MAX_BYTES = 1024 * 1024
MAX_FILES = 5000
RULES = {
    'CREDENTIAL_NAME': 'fail', 'SECRET_MARKER': 'fail', 'UPSTREAM_PATH': 'fail',
    'ARCHIVE_REVIEW': 'fail', 'IDENTITY_LITERAL': 'warn', 'SYMLINK_SKIPPED': 'warn',
    'SUBMODULE_SKIPPED': 'warn', 'BINARY_SKIPPED': 'warn', 'SCAN_LIMIT': 'fail',
    'UNMERGED_INDEX': 'fail', 'EXCEPTION_INVALID': 'fail',
}
SECRET = re.compile(
    r'-----BEGIN (?:RSA |EC |OPENSSH |DSA |ENCRYPTED )?PRIVATE KEY-----'
    r'|\bgh[pousr]_[A-Za-z0-9]{30,}\b|\bgithub_pat_[A-Za-z0-9_]{40,}\b'
    r'|\bAKIA[A-Z0-9]{16}\b'
    r'|(?i:\b(?:password|passwd|api_key|api_secret|access_token|smtp_password)\b[\"\']?\s*[:=]\s*[\"\'][^\"\'\r\n]{12,}[\"\'])'
)
KEY = r'(?:app_name|application_name|tenant_name|company_name|legal_name|tax_id|vkn|bank_iban|tenant_domain|company_domain|MAIN_INFO_SOCIETE_(?:NOM|NAME|ADDRESS|TVA|IDPROF\d))'
ASSIGN = re.compile(r'(?i)\b(' + KEY + r')[\"\']?\s*(?:=>|=|:)\s*([\"\'])([^\"\'\r\n]*)\2')
DEFINE = re.compile(r'(?i)\bdefine\s*\(\s*[\"\'](' + KEY + r')[\"\']\s*,\s*([\"\'])([^\"\'\r\n]*)\2')
NATIVE = re.compile(r'(?i)\bdolibarr_set_const\s*\([^,]+,\s*[\"\'](' + KEY + r')[\"\']\s*,\s*([\"\'])([^\"\'\r\n]*)\2')


def path_id(raw):
    return hashlib.sha256(raw).hexdigest()


class AuditError(Exception):
    pass


def git(root, *args):
    env = {k: v for k, v in os.environ.items() if not k.startswith('GIT_')}
    env.update(GIT_OPTIONAL_LOCKS='0', GIT_CONFIG_NOSYSTEM='1', GIT_CONFIG_GLOBAL=os.devnull)
    try:
        result = subprocess.run(['git', '--no-optional-locks', '-c', 'core.fsmonitor=false',
                                 '-C', str(root), *args], env=env, capture_output=True,
                                timeout=15, check=False, shell=False)
    except (OSError, subprocess.TimeoutExpired):
        raise AuditError() from None
    if result.returncode:
        raise AuditError()
    return result.stdout


def structure(path):
    p = PurePosixPath(path)
    parts = [s.lower() for s in p.parts]
    name = p.name.lower()
    rules = []
    if (name == '.env' or name.startswith('.env.') and name != '.env.example'
            or name in {'id_rsa', 'id_ed25519', 'id_dsa', 'id_ecdsa', 'credentials.json', 'credentials.yml', 'credentials.yaml', 'conf.php'}
            or p.suffix.lower() in {'.key', '.pem', '.p12', '.pfx', '.sql'}):
        rules.append('CREDENTIAL_NAME')
    if (any(s in {'.local', '.git'} for s in parts)
            or parts[0] in {'htdocs', 'vendor', 'upstream', 'dolibarr', 'node_modules'}
            or any(s in {'vendor', 'upstream', 'dolibarr'} for s in parts[:-1]) and p.suffix.lower() in {'.php', '.phtml', '.inc'}):
        rules.append('UPSTREAM_PATH')
    if p.suffix.lower() in {'.zip', '.gz', '.tar', '.phar', '.7z'}:
        rules.append('ARCHIVE_REVIEW')
    return rules


def findings_for_text(path, text):
    result = []
    fixture = path.startswith(('tests/', 'docs/')) or PurePosixPath(path).suffix.lower() in {'.md', '.rst'}
    for number, line in enumerate(text.splitlines(), 1):
        if SECRET.search(line):
            result.append(('SECRET_MARKER', number))
        if not fixture:
            for pattern in (ASSIGN, DEFINE, NATIVE):
                for match in pattern.finditer(line):
                    key, _, value = match.groups()
                    if not value.strip() or '${' in value or '{{' in value:
                        continue
                    if key.lower() in {'app_name', 'application_name'} and value == 'MAX':
                        continue
                    result.append(('IDENTITY_LITERAL', number))
    return sorted(set(result))


def audit(root):
    try:
        root = Path(root).resolve()
        top = git(root, 'rev-parse', '--show-toplevel').decode().strip()
        if Path(top).resolve() != root:
            raise AuditError()
        listing = git(root, 'ls-files', '--stage', '-z')
        records = [r for r in listing.split(b'\0') if r]
        if len(records) > MAX_FILES:
            raise AuditError()
        findings, blobs, scanned = [], {}, 0
        policy_text = ''
        def add(rule, raw=b'', line=0, blob=''):
            findings.append(dict(rule=rule, severity=RULES[rule], path_id=path_id(raw),
                                 line=line, blob=blob, excepted=False))
        for record in records:
            header, raw = record.split(b'\t', 1)
            mode, oid, stage = header.decode('ascii').split()
            if not re.fullmatch(r'[0-9a-f]{40,64}', oid):
                raise AuditError()
            path = raw.decode('utf-8', 'surrogateescape')
            if stage != '0':
                add('UNMERGED_INDEX', raw)
                continue
            problems = structure(path)
            for rule in problems:
                add(rule, raw, blob=oid)
            if mode == '160000':
                add('SUBMODULE_SKIPPED', raw, blob=oid)
                continue
            if mode == '120000':
                add('SYMLINK_SKIPPED', raw, blob=oid)
                continue
            if mode not in {'100644', '100755'}:
                raise AuditError()
            # Generated/upstream content and credential files are never opened.
            if 'UPSTREAM_PATH' in problems or 'CREDENTIAL_NAME' in problems or 'ARCHIVE_REVIEW' in problems:
                continue
            if oid not in blobs:
                size = int(git(root, 'cat-file', '-s', oid).strip())
                if size > MAX_BYTES:
                    blobs[oid] = None
                else:
                    blobs[oid] = git(root, 'cat-file', 'blob', oid)
            data = blobs[oid]
            if data is None:
                add('SCAN_LIMIT', raw, blob=oid)
                continue
            try:
                if b'\0' in data:
                    raise UnicodeError()
                text = data.decode('utf-8')
            except UnicodeError:
                add('BINARY_SKIPPED', raw, blob=oid)
                continue
            scanned += 1
            if path == 'docs/SOURCE_AUDIT.md':
                policy_text = text
            for rule, line in findings_for_text(path, text):
                add(rule, raw, line, oid)
        apply_exceptions(findings, policy_text, add)
        findings.sort(key=lambda f: (f['path_id'], f['line'], f['rule']))
        status = 'fail' if any(f['severity'] == 'fail' and not f['excepted'] for f in findings) else 'warn' if findings else 'pass'
        return dict(schema_version=1, status=status, exit_code={'pass': 0, 'warn': 1, 'fail': 2}[status],
                    scanned_blobs=scanned, findings=findings)
    except (AuditError, ValueError, OSError, UnicodeError):
        return dict(schema_version=1, status='error', exit_code=3, scanned_blobs=0,
                    findings=[dict(rule='AUDIT_ERROR', severity='error', path_id='', line=0, blob='', excepted=False)])


def apply_exceptions(findings, text, add):
    blocks = re.findall(r'<!-- MAX_SOURCE_AUDIT_EXCEPTIONS\s*\n(.*?)\n-->', text, re.S)
    if not blocks:
        if '<!-- MAX_SOURCE_AUDIT_EXCEPTIONS' in text:
            add('EXCEPTION_INVALID')
        return
    try:
        if len(blocks) != 1:
            raise ValueError()
        entries = json.loads(blocks[0])
        if not isinstance(entries, list):
            raise ValueError()
        used = set()
        for entry in entries:
            if set(entry) != {'rule', 'path_id', 'line', 'blob', 'reason', 'review'}:
                raise ValueError()
            if not all(isinstance(entry[k], str) for k in ['rule', 'path_id', 'blob', 'reason', 'review']):
                raise ValueError()
            if len(entry['reason'].strip()) < 20 or not re.fullmatch(r'https://github\.com/[^/]+/[^/]+/(?:pull|issues)/\d+', entry['review']):
                raise ValueError()
            key = (entry['rule'], entry['path_id'], entry['line'], entry['blob'])
            if key in used or entry['rule'] not in RULES or type(entry['line']) is not int:
                raise ValueError()
            matched = [f for f in findings if (f['rule'], f['path_id'], f['line'], f['blob']) == key]
            if len(matched) != 1:
                raise ValueError()
            matched[0]['excepted'] = True
            used.add(key)
    except (ValueError, TypeError, KeyError):
        # Invalid/stale policy never turns a failed scan into success.
        for f in findings:
            f['excepted'] = False
        add('EXCEPTION_INVALID')


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--json', action='store_true')
    args = parser.parse_args(argv)
    result = audit(args.repo)
    if args.json:
        print(json.dumps(result, indent=2))
    else:
        print('MAX source audit: '+result['status'].upper())
        for finding in result['findings']:
            print(f"{finding['severity'].upper()} {finding['rule']} path_id={finding['path_id']} line={finding['line']} excepted={finding['excepted']}")
        print('Git index only; no runtime, history, license or vetted secret-scanner certification.')
    return result['exit_code']


if __name__ == '__main__':
    sys.exit(main())
