"""Real temporary Git indexes; no external repositories, services or credentials."""
import contextlib
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/max_source_audit.py'
spec = importlib.util.spec_from_file_location('source_audit', SCRIPT)
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)


class AuditTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        self.command('init', '-q')
        self.command('config', 'user.name', 'Synthetic Tester')
        self.command('config', 'user.email', 'tester@example.invalid')

    def command(self, *args, data=None):
        result = subprocess.run(['git', '-C', str(self.root), *args], input=data,
                                capture_output=True, check=True)
        return result.stdout

    def stage(self, path, text='', mode='100644'):
        raw = text.encode() if isinstance(text, str) else text
        oid = self.command('hash-object', '-w', '--stdin', data=raw).decode().strip()
        self.command('update-index', '--add', '--cacheinfo', f'{mode},{oid},{path}')
        return oid

    def rules(self, result):
        return {f['rule'] for f in result['findings']}

    def test_neutral_config_and_product_brand_are_allowed(self):
        self.stage('modules/maxcore/main.php', "<?php\n$app_name = 'MAX';\n$company_name = getenv('TENANT_NAME');\ndefine('APP_NAME', 'MAX');")
        self.assertEqual(audit.audit(self.root)['exit_code'], 0)

    def test_assignment_define_and_native_config_sink(self):
        self.stage('modules/demo.php', "<?php\n$company_name = 'Synthetic Firm';\ndefine('TAX_ID', '123456789');\ndolibarr_set_const($db, 'MAIN_INFO_SOCIETE_NOM', 'Synthetic Firm');")
        result = audit.audit(self.root)
        self.assertEqual(result['exit_code'], 1)
        self.assertEqual([f['line'] for f in result['findings']], [2, 3, 4])
        self.assertEqual(self.rules(result), {'IDENTITY_LITERAL'})
        self.assertNotIn('Synthetic Firm', json.dumps(result))

    def test_json_identity_and_placeholder(self):
        self.stage('config/brand.json', json.dumps({'tenant_domain': 'tenant.example.invalid'}))
        self.stage('config/env.yml', 'tenant_name: "${TENANT_NAME}"')
        self.assertEqual(len(audit.audit(self.root)['findings']), 1)

    def test_synthetic_docs_and_fixture_identity_not_flagged(self):
        for path in ['docs/archive/notes.md', 'tests/fixtures/demo.php']:
            self.stage(path, "$company_name = 'Synthetic Firm';")
        self.assertEqual(audit.audit(self.root)['exit_code'], 0)

    def test_secret_markers_still_apply_to_fixtures(self):
        secret = 'gh' + 'p_' + 'A' * 36
        self.stage('tests/fixtures/token.txt', secret)
        result = audit.audit(self.root)
        self.assertEqual(result['exit_code'], 2)
        self.assertIn('SECRET_MARKER', self.rules(result))
        self.assertNotIn(secret, json.dumps(result))
        with patch.object(audit, 'audit', return_value=result), contextlib.redirect_stdout(io.StringIO()) as out:
            self.assertEqual(audit.main(['--repo', str(self.root)]), 2)
        self.assertNotIn(secret, out.getvalue())

    def test_private_key_and_literal_password(self):
        marker = '-----BEGIN ' + 'PRIVATE KEY-----'
        self.stage('config/secrets.txt', marker + '\n' + 'password' + ' = "synthetic-secret-value"')
        result = audit.audit(self.root)
        self.assertEqual(self.rules(result), {'SECRET_MARKER'})
        self.assertEqual(len(result['findings']), 2)
        self.assertNotIn('synthetic-secret-value', json.dumps(result))

    def test_credential_names_and_prohibited_paths_never_read(self):
        for path in ['.env', 'config/id_rsa', 'credentials.json', '.local/generated.php', 'htdocs/main.inc.php', 'vendor/pkg/file.php', 'modules/pkg/vendor/file.php']:
            self.stage(path, 'MUST_NOT_OPEN')
        original = audit.git
        def guarded(root, *args):
            self.assertNotIn('cat-file', args)
            return original(root, *args)
        with patch.object(audit, 'git', side_effect=guarded):
            result = audit.audit(self.root)
        self.assertEqual(result['exit_code'], 2)
        self.assertEqual(self.rules(result), {'CREDENTIAL_NAME', 'UPSTREAM_PATH'})

    def test_env_example_and_archived_doc_reference_allowed(self):
        self.stage('.env.example', 'DB_PASSWORD=\n')
        self.stage('docs/archive/readme.md', 'Upstream reference: vendor/pkg/file.php')
        self.assertEqual(audit.audit(self.root)['exit_code'], 0)
        self.stage('exports/archive.zip', b'PK\x00binary')
        self.assertIn('ARCHIVE_REVIEW', self.rules(audit.audit(self.root)))

    def test_symlink_never_followed(self):
        outside = self.root.parent / 'outside-secret'
        # No filesystem symlink privilege is needed on Windows: real index mode.
        self.stage('link.php', str(outside), '120000')
        with patch.object(audit, 'git', wraps=audit.git) as run:
            result = audit.audit(self.root)
        self.assertEqual(self.rules(result), {'SYMLINK_SKIPPED'})
        self.assertFalse(any('cat-file' in c.args for c in run.call_args_list))

    def test_submodule_not_traversed(self):
        self.command('commit', '--allow-empty', '-qm', 'synthetic base')
        oid = self.command('rev-parse', 'HEAD').decode().strip()
        self.command('update-index', '--add', '--cacheinfo', f'160000,{oid},modules/child')
        self.assertEqual(self.rules(audit.audit(self.root)), {'SUBMODULE_SKIPPED'})

    def test_malicious_filename_is_hashed_and_not_interpreted(self):
        name = '--option\tSENSITIVE_FILENAME\nfile.txt'
        self.stage(name, 'safe')
        # Introduce a content warning so output includes the opaque path identifier.
        self.stage(name, '\x00')
        result = audit.audit(self.root)
        self.assertEqual(self.rules(result), {'BINARY_SKIPPED'})
        self.assertEqual(result['findings'][0]['path_id'], audit.path_id(name.encode()))
        self.assertNotIn('SENSITIVE_FILENAME', json.dumps(result))

    def test_index_snapshot_not_working_tree_or_untracked_files(self):
        self.stage('config.py', 'safe')
        (self.root / 'config.py').write_text('gh' + 'p_' + 'A' * 36)
        (self.root / '.env').write_text('untracked secret')
        self.assertEqual(audit.audit(self.root)['exit_code'], 0)

    def test_no_mutation_of_index_or_working_files(self):
        self.stage('app.php', 'safe')
        (self.root / 'app.php').write_text('unstaged changes')
        before_index = (self.root / '.git/index').read_bytes()
        before_files = {p.relative_to(self.root): p.read_bytes() for p in self.root.iterdir() if p.is_file()}
        audit.audit(self.root)
        self.assertEqual(before_index, (self.root / '.git/index').read_bytes())
        self.assertEqual(before_files, {p.relative_to(self.root): p.read_bytes() for p in self.root.iterdir() if p.is_file()})

    def test_linked_worktree(self):
        self.stage('app.py', 'safe')
        self.command('commit', '-qm', 'synthetic base')
        linked = self.root / 'linked'
        self.command('worktree', 'add', '-qb', 'audit-test', str(linked))
        self.assertTrue((linked / '.git').is_file())
        self.assertEqual(audit.audit(linked)['exit_code'], 0)

    def test_oversized_and_binary_report_incomplete_coverage(self):
        self.stage('large.py', b'x' * (audit.MAX_BYTES + 1))
        self.stage('asset.bin', b'\0secret')
        self.assertEqual(self.rules(audit.audit(self.root)), {'SCAN_LIMIT', 'BINARY_SKIPPED'})

    def test_exceptions_are_exact_visible_and_stale_fail_closed(self):
        oid = self.stage('app.php', "$legal_name = 'Synthetic Firm';")
        entry = dict(rule='IDENTITY_LITERAL', path_id=audit.path_id(b'app.php'), line=1, blob=oid,
                     reason='Synthetic explicit regression case under review.', review='https://github.com/example/project/issues/1')
        policy = lambda value: '<!-- MAX_SOURCE_AUDIT_EXCEPTIONS\n'+json.dumps(value)+'\n-->'
        self.stage('docs/SOURCE_AUDIT.md', policy([entry]))
        result = audit.audit(self.root)
        self.assertEqual(result['exit_code'], 1)
        self.assertTrue(result['findings'][0]['excepted'])
        self.stage('app.php', "$legal_name = 'Changed Synthetic Firm';")
        result = audit.audit(self.root)
        self.assertEqual(result['exit_code'], 2)
        self.assertIn('EXCEPTION_INVALID', self.rules(result))
        self.assertFalse(any(f['excepted'] for f in result['findings']))

    def test_duplicate_or_unjustified_exception_rejected(self):
        self.stage('docs/SOURCE_AUDIT.md', '<!-- MAX_SOURCE_AUDIT_EXCEPTIONS\n[{}]\n-->')
        self.assertIn('EXCEPTION_INVALID', self.rules(audit.audit(self.root)))

    def test_malformed_exception_block_is_not_silently_ignored(self):
        self.stage('docs/SOURCE_AUDIT.md', '<!-- MAX_SOURCE_AUDIT_EXCEPTIONS\nnot closed')
        self.assertIn('EXCEPTION_INVALID', self.rules(audit.audit(self.root)))

    def test_unmerged_index_is_not_scanned(self):
        oid = self.command('hash-object', '-w', '--stdin', data=b'conflict').decode().strip()
        self.command('update-index', '--index-info', data=f'100644 {oid} 1\tconflict.php\n100644 {oid} 2\tconflict.php\n'.encode())
        result = audit.audit(self.root)
        self.assertEqual(self.rules(result), {'UNMERGED_INDEX'})
        self.assertEqual(result['scanned_blobs'], 0)

    def test_invalid_utf8_and_nested_root_fail_safely(self):
        self.stage('source.txt', b'\xff')
        self.assertEqual(self.rules(audit.audit(self.root)), {'BINARY_SKIPPED'})
        nested = self.root / 'nested'
        nested.mkdir()
        self.assertEqual(audit.audit(nested)['exit_code'], 3)

    def test_file_limit_reports_error(self):
        self.stage('one.py', 'safe')
        with patch.object(audit, 'MAX_FILES', 0):
            self.assertEqual(audit.audit(self.root)['exit_code'], 3)

    def test_git_failure_suppresses_details_and_json_exit_is_stable(self):
        with patch.object(audit, 'git', side_effect=audit.AuditError('SECRET_DETAIL')):
            result = audit.audit(self.root)
        self.assertEqual(result['exit_code'], 3)
        self.assertNotIn('SECRET_DETAIL', json.dumps(result))
        with patch.object(audit, 'audit', return_value=result), contextlib.redirect_stdout(io.StringIO()) as output:
            self.assertEqual(audit.main(['--json']), 3)
        self.assertEqual(json.loads(output.getvalue()), result)

    def test_all_cli_json_exit_codes_and_invalid_root_are_sanitized(self):
        for status, code in [('pass', 0), ('warn', 1), ('fail', 2), ('error', 3)]:
            result = dict(schema_version=1, status=status, exit_code=code, scanned_blobs=0, findings=[])
            with patch.object(audit, 'audit', return_value=result), contextlib.redirect_stdout(io.StringIO()) as out:
                self.assertEqual(audit.main(['--json']), code)
            self.assertEqual(json.loads(out.getvalue()), result)
        result = audit.audit('\0SENSITIVE_ROOT')
        self.assertEqual(result['exit_code'], 3)
        self.assertNotIn('SENSITIVE_ROOT', json.dumps(result))

    def test_subprocess_timeout_and_missing_git_fail_without_details(self):
        for error in [FileNotFoundError('SENSITIVE_DETAIL'), subprocess.TimeoutExpired(['git'], 15)]:
            with patch.object(audit.subprocess, 'run', side_effect=error):
                self.assertEqual(audit.audit(self.root)['exit_code'], 3)

    def test_subprocess_no_shell_timeout_and_git_environment_isolated(self):
        with patch.dict(os.environ, {'GIT_DIR': 'SECRET_DETAIL'}), patch.object(audit.subprocess, 'run', return_value=type('R', (), {'returncode': 0, 'stdout': b''})()) as run:
            audit.git(self.root, 'ls-files', '--stage', '-z')
        self.assertFalse(run.call_args.kwargs['shell'])
        self.assertEqual(run.call_args.kwargs['timeout'], 15)
        self.assertNotIn('GIT_DIR', run.call_args.kwargs['env'])
        self.assertEqual(run.call_args.kwargs['env']['GIT_OPTIONAL_LOCKS'], '0')


if __name__ == '__main__':
    unittest.main()
