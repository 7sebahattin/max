"""Regressions for isolation, complete evidence and fail-closed execution."""
import copy
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest import mock

spec = importlib.util.spec_from_file_location('integration_runner', Path(__file__).with_name('run.py'))
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)
ENV = {'GITHUB_ACTIONS': 'true', 'GITHUB_RUN_ID': '1234', 'GITHUB_RUN_ATTEMPT': '2'}
PROJECT = 'max-integration-1234-2-p2'


def config(project=PROJECT):
    return {'name': project,
            'services': {
                'db': {'networks': {'sandbox': {}}, 'environment': {'MARIADB_DATABASE': 'max_demo'}},
                'web': {'networks': {'sandbox': {}}, 'environment': {'MARIADB_DATABASE': 'max_demo'}},
                'gateway': {'networks': {'sandbox': {}, 'frontend': {}},
                            'ports': [{'host_ip': '127.0.0.1', 'published': '8080', 'target': 80}]}},
            'networks': {'sandbox': {'internal': True, 'name': project + '_sandbox'},
                         'frontend': {'name': project + '_frontend'}},
            'volumes': {'database': {'name': project + '_database'}}}


class IsolationTests(unittest.TestCase):
    def test_remote_selectors_and_db_environment_cannot_redirect_pilots(self):
        inherited = dict(ENV, DOCKER_HOST='tcp://invalid.example:2376',
                         DOCKER_CONTEXT='production', DOCKER_TLS_VERIFY='1',
                         DOCKER_CERT_PATH='/private', COMPOSE_FILE='other.yaml',
                         COMPOSE_PROJECT_NAME='production', COMPOSE_ENV_FILES='other.env',
                         MARIADB_DATABASE='production', MARIADB_PASSWORD='fixture')
        env = runner.isolated_env(inherited, 'p2')
        self.assertEqual(env['DOCKER_HOST'], 'unix:///var/run/docker.sock')
        self.assertEqual(env['COMPOSE_PROJECT_NAME'], PROJECT)
        self.assertEqual(env['COMPOSE_FILE'], str(runner.ROOT / 'compose.yaml'))
        for key in ('DOCKER_CONTEXT', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH',
                    'COMPOSE_ENV_FILES', 'MARIADB_DATABASE', 'MARIADB_PASSWORD'):
            self.assertNotIn(key, env)
        self.assertNotEqual(env['COMPOSE_PROJECT_NAME'],
                            runner.isolated_env(ENV, 'maxcore')['COMPOSE_PROJECT_NAME'])
        self.assertEqual(inherited['DOCKER_CONTEXT'], 'production')

    def test_non_ci_or_invalid_project_identity_is_refused(self):
        for env in ({}, dict(ENV, GITHUB_ACTIONS='false'),
                    dict(ENV, GITHUB_RUN_ID='../escape'), dict(ENV, GITHUB_RUN_ATTEMPT='')):
            with self.subTest(env=env), self.assertRaises(ValueError):
                runner.isolated_env(env, 'p2')

    def test_resolved_compose_isolation_is_enforced(self):
        runner.validate_config(config(), PROJECT)
        mutations = [
            lambda c: c.update(name='other'),
            lambda c: c['networks']['sandbox'].update(internal=False),
            lambda c: c['networks']['sandbox'].update(external=True),
            lambda c: c['volumes']['database'].update(name='shared-database'),
            lambda c: c['volumes']['database'].update(external=True),
            lambda c: c['services']['db'].update(ports=[{'published': '3306'}]),
            lambda c: c['services']['web'].update(ports=[{'published': '80'}]),
            lambda c: c['services']['web'].update(network_mode='host'),
            lambda c: c['services']['web'].update(privileged=True),
            lambda c: c['services']['web']['networks'].update(frontend={}),
            lambda c: c['services']['web']['environment'].update(HTTPS_PROXY='http://invalid.example'),
            lambda c: c['services']['gateway']['ports'][0].update(host_ip='0.0.0.0'),
            lambda c: c['services']['gateway']['ports'][0].update(published='443'),
            lambda c: c['services']['db']['environment'].update(MARIADB_DATABASE='production'),
        ]
        for mutate in mutations:
            value = copy.deepcopy(config())
            mutate(value)
            with self.subTest(mutate=mutate), self.assertRaises(ValueError):
                runner.validate_config(value, PROJECT)

    def test_maxcore_evidence_rejects_missing_duplicate_and_overflow_measurements(self):
        with tempfile.TemporaryDirectory() as temp:
            local = Path(temp)
            rows = []
            for width in (360, 390, 768):
                for screen in ('home', 'settings', 'administration'):
                    rows.append(dict(width=width, viewport=width, screen=screen,
                                     body_scroll_width=width, document_scroll_width=width))
                    suffix = '' if screen == 'home' else '-' + screen
                    (local / ('maxcore' + suffix + '-' + str(width) + '.png')).touch()
            path = local / 'maxcore-mobile.json'
            path.write_text(json.dumps(rows))
            self.assertEqual(runner.evidence('maxcore', local)['strict_overflow_checks'], 9)
            for bad in (rows[:-1], rows[:-1] + rows[:1],
                        [dict(rows[0], document_scroll_width=361)] + rows[1:]):
                path.write_text(json.dumps(bad))
                with self.assertRaises(ValueError):
                    runner.evidence('maxcore', local)
            path.write_text(json.dumps(rows))
            (local / 'maxcore-360.png').unlink()
            with self.assertRaises(ValueError):
                runner.evidence('maxcore', local)

    def test_native_failure_evidence_failure_and_cleanup_failure_stay_failed(self):
        for scenario in ('pass', 'pilot-fails', 'evidence-fails', 'diagnostic-fails', 'cleanup-fails', 'unsafe-config'):
            with self.subTest(scenario=scenario), tempfile.TemporaryDirectory() as temp:
                root = Path(temp)
                (root / 'upstream.lock.json').write_text(json.dumps({'version': '24.0.1', 'commit': 'b' * 40}))
                calls = []
                def command(args, **kwargs):
                    calls.append(args)
                    if args[:2] == ['git', 'rev-parse']:
                        return subprocess.CompletedProcess(args, 0, stdout='a' * 40 + '\n')
                    if args[:3] == ['docker', 'compose', 'config']:
                        value = config()
                        if scenario == 'unsafe-config':
                            value['networks']['sandbox']['internal'] = False
                        return subprocess.CompletedProcess(args, 0, stdout=json.dumps(value))
                    if args[-1] == 'tests/integration/p2_layout.py' and scenario == 'diagnostic-fails':
                        raise subprocess.CalledProcessError(8, args)
                    if args[:3] == ['docker', 'compose', 'down'] and scenario == 'cleanup-fails':
                        raise subprocess.CalledProcessError(9, args)
                    code = 17 if args[0] == 'bash' and scenario == 'pilot-fails' else 0
                    return subprocess.CompletedProcess(args, code, stdout='')
                with mock.patch.object(runner, 'ROOT', root), mock.patch.dict(os.environ, ENV, clear=True), \
                     mock.patch.object(runner.subprocess, 'run', side_effect=command), \
                     mock.patch.object(runner, 'evidence', side_effect=ValueError('fixture') if scenario == 'evidence-fails' else None,
                                       return_value={'mobile_checks': 15}):
                    result = runner.execute('p2')
                report = json.loads((root / '.local/integration-p2.json').read_text())
                self.assertEqual(result, 0 if scenario == 'pass' else 1)
                self.assertEqual(report['status'], 'pass' if scenario == 'pass' else 'fail')
                self.assertNotIn('MARIADB_PASSWORD', json.dumps(report))
                pilot_calls = [c for c in calls if c[0] == 'bash']
                self.assertEqual(pilot_calls, [] if scenario == 'unsafe-config' else [['bash', 'scripts/p2_install.sh']])
                cleanup = [c for c in calls if c[:3] == ['docker', 'compose', 'down']]
                self.assertEqual(bool(cleanup), scenario != 'unsafe-config')
                if cleanup:
                    self.assertIn('--volumes', cleanup[0])

    def test_existing_configuration_is_never_adopted(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root / '.env').write_text('private fixture')
            with mock.patch.object(runner, 'ROOT', root), mock.patch.dict(os.environ, ENV, clear=True), \
                 mock.patch.object(runner.subprocess, 'run') as command, self.assertRaises(ValueError):
                runner.execute('p2')
            command.assert_not_called()


if __name__ == '__main__':
    unittest.main()
