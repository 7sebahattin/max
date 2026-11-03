"""CI-only adapter for unchanged native pilots; disposable local Docker only."""
import argparse
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
PILOTS = {'p2': 'scripts/p2_install.sh', 'maxcore': 'scripts/maxcore_test.sh'}
PROXIES = ('HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy')
P2_SCREENS = ('societe/list.php', 'product/list.php', 'commande/list.php',
              'expedition/list.php', 'compta/facture/list.php')


def require(condition, message):
    if not condition:
        raise ValueError(message)


def isolated_env(inherited, pilot):
    require(pilot in PILOTS, 'Unknown pilot')
    require(inherited.get('GITHUB_ACTIONS') == 'true', 'Disposable GitHub runner required')
    for key in ('GITHUB_RUN_ID', 'GITHUB_RUN_ATTEMPT'):
        require(re.fullmatch(r'[0-9]+', inherited.get(key, '')) is not None, 'Invalid CI identity')
    env = dict(inherited)
    for key in ('DOCKER_CONTEXT', 'DOCKER_TLS', 'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH',
                'COMPOSE_FILE', 'COMPOSE_PROFILES', 'COMPOSE_ENV_FILES', 'COMPOSE_PATH_SEPARATOR',
                'MARIADB_DATABASE', 'MARIADB_USER', 'MARIADB_PASSWORD', 'MARIADB_ROOT_PASSWORD'):
        env.pop(key, None)
    env.update(DOCKER_HOST='unix:///var/run/docker.sock',
               COMPOSE_FILE=str(ROOT / 'compose.yaml'),
               COMPOSE_PROJECT_NAME='max-integration-' + inherited['GITHUB_RUN_ID'] + '-' +
                                    inherited['GITHUB_RUN_ATTEMPT'] + '-' + pilot,
               P2_BROWSER='1')
    return env


def validate_config(config, project):
    """Inspect in memory only: resolved Compose contains disposable DB credentials."""
    require(config.get('name') == project, 'Compose project mismatch')
    services = config.get('services', {})
    require(set(services) == {'db', 'web', 'gateway'}, 'Unexpected runtime service')
    networks = config.get('networks', {})
    require(set(networks) == {'sandbox', 'frontend'}, 'Unexpected runtime network')
    require(networks['sandbox'].get('internal') is True, 'Runtime network must be internal')
    for name, value in networks.items():
        require(not value.get('external') and value.get('name') == project + '_' + name,
                'Network must belong to disposable project')
    volumes = config.get('volumes', {})
    require(set(volumes) == {'database'}, 'Unexpected data volume')
    require(not volumes['database'].get('external') and
            volumes['database'].get('name') == project + '_database',
            'Database volume must belong to disposable project')
    for name, service in services.items():
        expected = {'sandbox', 'frontend'} if name == 'gateway' else {'sandbox'}
        require(set(service.get('networks', {})) == expected, 'Runtime network attachment mismatch')
        require(not service.get('network_mode') and not service.get('privileged'),
                'Unsafe runtime isolation override')
        require(not any(service.get('environment', {}).get(k) for k in PROXIES),
                'Runtime proxy must be disabled')
        if name != 'gateway':
            require(not service.get('ports'), 'Database/application port must not be published')
    ports = services['gateway'].get('ports', [])
    require(len(ports) == 1 and ports[0].get('host_ip') == '127.0.0.1' and
            str(ports[0].get('published')) == '8080' and str(ports[0].get('target')) == '80',
            'Only loopback gateway port is permitted')
    require(services['db']['environment'].get('MARIADB_DATABASE') == 'max_demo' and
            services['web']['environment'].get('MARIADB_DATABASE') == 'max_demo',
            'Only disposable demo database is permitted')


def evidence(pilot, local):
    if pilot == 'maxcore':
        rows = json.loads((local / 'maxcore-mobile.json').read_text())
        expected = {(w, s) for w in (360, 390, 768) for s in ('home', 'settings', 'administration')}
        require(len(rows) == 9 and {(r['width'], r['screen']) for r in rows} == expected,
                'Incomplete MAX Core mobile evidence')
        require(all(r['viewport'] == r['width'] and r['body_scroll_width'] <= r['width'] and
                    r['document_scroll_width'] <= r['width'] for r in rows),
                'MAX Core horizontal overflow')
        for width, screen in expected:
            suffix = '' if screen == 'home' else '-' + screen
            require((local / ('maxcore' + suffix + '-' + str(width) + '.png')).is_file(),
                    'Missing MAX Core screenshot')
        return {'mobile_checks': len(rows), 'strict_overflow_checks': len(rows)}
    rows = json.loads((local / 'p2-browser.json').read_text())
    expected = {(w, s) for w in (360, 390, 768) for s in P2_SCREENS}
    require(len(rows) == 15 and {(r['width'], r['path']) for r in rows} == expected and
            all(r['status'] == 200 for r in rows), 'Incomplete P2 authenticated render evidence')
    overflow = []
    for row in rows:
        require((local / ('p2-' + str(row['width']) + '-' + row['path'].replace('/', '-') + '.png')).is_file(),
                'Missing P2 screenshot')
        if row['body_scroll_width'] > row['width']:
            overflow.append({k: row[k] for k in ('width', 'path', 'body_scroll_width')})
    # Preserve approved P2 render semantics. Native P2 task/overflow acceptance remains manual.
    return {'mobile_checks': len(rows), 'strict_overflow_checks': 0, 'native_p2_overflow_samples': overflow}


def execute(pilot):
    env = isolated_env(os.environ, pilot)
    project = env['COMPOSE_PROJECT_NAME']
    local = ROOT / '.local'
    require(not local.exists() and not (ROOT / '.env').exists(), 'Fresh checkout required')
    def call(args, **kwargs):
        return subprocess.run(args, cwd=ROOT, env=env, check=True, **kwargs)
    def resources():
        for kind, args in [('container', ['docker', 'ps', '-aq']),
                           ('volume', ['docker', 'volume', 'ls', '-q']),
                           ('network', ['docker', 'network', 'ls', '-q'])]:
            result = call(args + ['--filter', 'label=com.docker.compose.project=' + project],
                          capture_output=True, text=True)
            require(not result.stdout.strip(), 'Disposable project still has ' + kind + ' resources')
    resources()  # Do not adopt/delete a pre-existing installation.
    commit = call(['git', 'rev-parse', 'HEAD'], capture_output=True, text=True).stdout.strip()
    require(re.fullmatch(r'[0-9a-f]{40}', commit) is not None, 'Invalid checkout identity')
    lock = json.loads((ROOT / 'upstream.lock.json').read_text())
    require(lock['version'] == '24.0.1', 'Unexpected Dolibarr version')
    local.mkdir()
    report = dict(pilot=pilot, commit=commit, dolibarr=lock['version'],
                  upstream_commit=lock['commit'], project=project,
                  run_id=env['GITHUB_RUN_ID'], run_attempt=env['GITHUB_RUN_ATTEMPT'],
                  status='fail', pilot_exit_code=None, cleanup='not-started')
    # Only newly generated demo values; never consume repository/production secrets.
    config = ROOT / '.env'
    fd = os.open(config, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as output:
        output.write('MARIADB_DATABASE=max_demo\nMARIADB_USER=max_demo\nMARIADB_PASSWORD=' +
                     secrets.token_hex(24) + '\nMARIADB_ROOT_PASSWORD=' + secrets.token_hex(24) + '\n')
    failed = False
    owned = False
    try:
        call([sys.executable, 'scripts/prepare.py'])
        resolved = call(['docker', 'compose', 'config', '--format', 'json'],
                        capture_output=True, text=True)
        validate_config(json.loads(resolved.stdout), project)
        owned = True  # Only the validated, fresh project may be removed below.
        print('PASS fresh local project and internal runtime network: ' + project, flush=True)
        result = subprocess.run(['bash', PILOTS[pilot]], cwd=ROOT, env=env, check=False)
        report['pilot_exit_code'] = result.returncode
        require(result.returncode == 0, 'Native pilot failed; inspect preceding test output')
        report.update(evidence(pilot, local))
        call(['git', 'diff', '--exit-code', 'HEAD', '--'])
        report['status'] = 'pass'
    except (ValueError, KeyError, TypeError, OSError, subprocess.SubprocessError):
        failed = True
        print('FAIL integration acceptance; inspect native output and safe report', flush=True)
    finally:
        if owned:
            try:
                call(['docker', 'compose', 'down', '--volumes', '--remove-orphans'])
                resources()
                report['cleanup'] = 'pass'
            except (ValueError, OSError, subprocess.SubprocessError):
                failed = True
                report['cleanup'] = 'fail'
                print('FAIL disposable project cleanup', flush=True)
        if failed:
            report['status'] = 'fail'
        # Deliberate allowlist: never serialize environment, config, account JSON or HTML.
        (local / ('integration-' + pilot + '.json')).write_text(json.dumps(report, indent=2))
        print(json.dumps(report, indent=2))
    return int(failed)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--pilot', choices=sorted(PILOTS), required=True)
    try:
        sys.exit(execute(parser.parse_args().pilot))
    except (ValueError, OSError, subprocess.SubprocessError):
        raise SystemExit('FAIL integration preflight (no existing installation adopted)') from None
