"""Portable tests; all engine probes are fakes except a read-only CLI integration."""
import contextlib
import importlib.util
import io
import json
import os
from pathlib import Path
from types import SimpleNamespace
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/dev_doctor.py'
spec = importlib.util.spec_from_file_location('dev_doctor', SCRIPT)
doctor = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = doctor
spec.loader.exec_module(doctor)


class DoctorTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        for name in ['AGENTS.md', 'upstream.lock.json', 'compose.yaml', '.env.example', 'scripts/smoke.sh']:
            path = self.root / name
            path.parent.mkdir(exist_ok=True)
            path.touch()
        # A linked worktree uses a .git file, not a directory.
        (self.root / '.git').write_text('gitdir: elsewhere')
        self.calls = []

    def fake(self, args, cwd, env):
        self.calls.append((args, cwd, env))
        if 'rev-parse' in args:
            return 0, str(self.root)
        if args[0] == 'git':
            return 0, 'git version 2.47.1.windows.1'
        if args[-1] == '--short':
            return 0, 'v2.40.3'
        if 'info' in args:
            return 0, '28.4.0'
        return 0, 'Docker version 28.4.0, build example'

    def inspect(self, **kw):
        options = dict(run=self.fake, system='Linux', release='6.6',
                       python_version=(3, 12, 0), environ={},
                       disk=lambda root: SimpleNamespace(free=16 * 1024**3))
        options.update(kw)
        return doctor.diagnose(self.root, **options)

    def status(self, result, code):
        return next(c['status'] for c in result['checks'] if c['code'] == code)

    def test_supported_linux_and_linked_worktree(self):
        result = self.inspect()
        self.assertFalse(result['failed'])
        self.assertEqual(self.status(result, 'repo'), 'pass')
        self.assertEqual(self.status(result, 'coverage'), 'warn')

    def test_only_read_only_local_commands_and_selectors_removed(self):
        secret = 'DO_NOT_PRINT_TEST_SENTINEL'
        inherited = {name: secret for name in doctor.DOCKER_SELECTORS + doctor.GIT_SELECTORS}
        inherited['SAFE_PASSTHROUGH'] = 'kept'
        result = self.inspect(environ=inherited)
        self.assertNotIn(secret, json.dumps(result))
        for args, cwd, env in self.calls:
            self.assertEqual(cwd, self.root)
            self.assertEqual(env['SAFE_PASSTHROUGH'], 'kept')
            self.assertTrue(all(k not in env for k in inherited if k != 'SAFE_PASSTHROUGH'))
            if args[0] == 'docker' and '--version' not in args:
                self.assertEqual(args[1], '--host=unix:///var/run/docker.sock')
            self.assertNotIn('prune', args)
            self.assertNotIn('up', args)
            self.assertNotIn('config', args)  # Compose config can expose secrets.
        self.assertEqual(len(self.calls), 5)

    def test_missing_tool_and_untrusted_output_never_echoed(self):
        result = self.inspect(run=lambda *args: (1, 'password=DO_NOT_PRINT'))
        self.assertTrue(result['failed'])
        self.assertNotIn('DO_NOT_PRINT', json.dumps(result))
        self.assertEqual(self.status(result, 'docker_cli'), 'fail')

    def test_successful_commands_cannot_echo_embedded_secret(self):
        def noisy(args, cwd, env):
            code, output = self.fake(args, cwd, env)
            return code, output if 'rev-parse' in args else output + ' SECRET_SENTINEL'
        self.assertNotIn('SECRET_SENTINEL', json.dumps(self.inspect(run=noisy)))

    def test_wrong_root_and_missing_markers(self):
        self.assertEqual(self.status(self.inspect(run=lambda *a: (0, str(self.root.parent))), 'repo'), 'fail')
        (self.root / 'compose.yaml').unlink()
        self.assertEqual(self.status(self.inspect(), 'repo'), 'fail')

    def test_missing_root_does_not_execute_commands(self):
        result = doctor.diagnose(self.root / 'missing', run=self.fake)
        self.assertTrue(result['failed'])
        self.assertEqual(self.calls, [])

    def test_disk_boundary_and_failure(self):
        for free, expected in [(doctor.MIN_FREE_BYTES, 'pass'), (doctor.MIN_FREE_BYTES - 1, 'fail')]:
            self.assertEqual(self.status(self.inspect(disk=lambda p: SimpleNamespace(free=free)), 'disk'), expected)
        def denied(_):
            raise PermissionError('secret path')
        result = self.inspect(disk=denied)
        self.assertEqual(self.status(result, 'disk'), 'fail')
        self.assertNotIn('secret path', json.dumps(result))

    def test_old_python_and_compose_v1_are_failures(self):
        self.assertEqual(self.status(self.inspect(python_version=(3, 11, 9)), 'python'), 'fail')
        def old(args, cwd, env):
            return (0, '1.29.2') if '--short' in args else self.fake(args, cwd, env)
        self.assertEqual(self.status(self.inspect(run=old), 'compose'), 'fail')

    def test_windows_uses_local_named_pipe_and_warns_about_wsl(self):
        result = self.inspect(system='Windows', release='11')
        self.assertEqual(self.status(result, 'os'), 'warn')
        info = next(args for args, _, _ in self.calls if 'info' in args)
        self.assertEqual(info[1], '--host=npipe:////./pipe/docker_engine')

    def test_wsl_and_other_os_detection(self):
        self.assertIn('WSL', self.inspect(release='6.6-microsoft-standard-WSL2')['checks'][0]['message'])
        self.assertEqual(self.status(self.inspect(system='Darwin'), 'os'), 'warn')

    def test_timeout_missing_binary_and_no_shell(self):
        for error in [FileNotFoundError(), subprocess.TimeoutExpired(['docker'], 5), PermissionError()]:
            with patch.object(doctor.subprocess, 'run', side_effect=error) as run:
                self.assertEqual(doctor.run_command(['docker', '--version'], self.root, {}), (-1, ''))
                self.assertFalse(run.call_args.kwargs['shell'])
                self.assertEqual(run.call_args.kwargs['timeout'], 5)

    def test_json_and_exit_code_contract(self):
        for failed in [False, True]:
            result = {'schema_version': 1, 'failed': failed, 'checks': []}
            output = io.StringIO()
            with patch.object(doctor, 'diagnose', return_value=result), contextlib.redirect_stdout(output):
                self.assertEqual(doctor.main(['--json', '--repo', str(self.root)]), int(failed))
            self.assertEqual(json.loads(output.getvalue()), result)

    def test_secret_file_untouched(self):
        secret = self.root / '.env'
        secret.write_text('DO_NOT_READ_SECRET')
        before = {p.relative_to(self.root): p.read_bytes() for p in self.root.rglob('*') if p.is_file()}
        with patch.object(Path, 'read_text', side_effect=AssertionError('No contents may be read')):
            result = self.inspect()
        after = {p.relative_to(self.root): p.read_bytes() for p in self.root.rglob('*') if p.is_file()}
        self.assertEqual(before, after)
        self.assertNotIn('DO_NOT_READ_SECRET', json.dumps(result))

    def test_actual_cli_outside_repository_is_valid_json(self):
        result = subprocess.run([sys.executable, str(SCRIPT), '--json', '--repo', str(self.root / 'missing')],
                                capture_output=True, text=True, timeout=15, check=False)
        self.assertEqual(result.returncode, 1)
        self.assertTrue(json.loads(result.stdout)['failed'])
        self.assertEqual(result.stderr, '')


if __name__ == '__main__':
    unittest.main()
