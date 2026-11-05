"""Offline Issue #31 selection/provenance. Git only: no API, Docker or provider call."""
import json
import os
from pathlib import Path
import re
import subprocess
import sys

# Conservative module families plus actual shared installation/build dependencies.
# Documentation alone is not an executable input (including compatibility prose).
PATHS = (
    'modules/maxcore/**', 'modules/maxmobile/**', 'modules/maxorders/**',
    'modules/maxcustoms/**', 'modules/maxmail/**',
    'tests/coenabled/**', 'tests/maxcore/**', 'tests/mobile/**',
    'tests/orders/**', 'tests/customs/**', 'tests/mailintake/**',
    'tests/branding/**', 'tests/p2_*',
    'scripts/coenabled_*', 'scripts/maxcore_*', 'scripts/mobile_*',
    'scripts/orders_*', 'scripts/customs_*', 'scripts/mailintake_*', 'scripts/branding_*',
    'scripts/p2_install.sh', 'scripts/prepare.py', 'scripts/check.py',
    'scripts/max_source_audit.py', 'deployment/local/**', 'compose.yaml', '.dockerignore',
    'upstream.lock.json', '.github/workflows/max-coenabled.yml',
)


def matches(path, pattern):
    if pattern.endswith('/**'):
        return path.startswith(pattern[:-2])
    if pattern.endswith('*'):
        prefix = pattern[:-1]
        return path.startswith(prefix) and '/' not in path[len(prefix):]
    return path == pattern


def required(paths, manual=False):
    return manual or any(matches(path, pattern) for path in paths for pattern in PATHS)


def git(root, *args):
    result = subprocess.run(['git', *args], cwd=root, capture_output=True, timeout=30)
    if result.returncode:
        raise ValueError('Git history or tracked source validation failed')
    decoded = result.stdout.decode('utf-8', errors='strict')
    return decoded if '-z' in args else decoded.strip()


def commit(root, value):
    if not re.fullmatch('[a-f0-9]{40}', value):
        raise ValueError('Full commit SHA required')
    resolved = git(root, 'rev-parse', '--verify', value+'^{commit}')
    if resolved != value:
        raise ValueError('Commit mismatch')
    return resolved


def provenance(root, base=None, expected_head=None):
    head = commit(root, git(root, 'rev-parse', 'HEAD'))
    if expected_head is not None and commit(root, expected_head) != head:
        raise ValueError('Checkout is not the exact requested HEAD')
    # Local reproduction compares the first parent unless a base is explicit.
    target = commit(root, base or git(root, 'rev-parse', 'HEAD^'))
    comparison = git(root, 'merge-base', target, head)
    commit(root, comparison)
    git(root, 'diff', '--quiet', 'HEAD', '--')
    # --no-renames includes BOTH removed and added paths; -z handles all names.
    raw = git(root, 'diff', '--name-only', '--no-renames', '-z', comparison, head, '--')
    paths = raw.rstrip('\0').split('\0') if raw else []
    return {'sha': head, 'base': target, 'comparison_base': comparison, 'changed_files': paths}


def select(root, event_name, event, expected_sha):
    if event_name == 'pull_request':
        request = event['pull_request']
        state = provenance(root, request['base']['sha'], request['head']['sha'])
        manual = False
    elif event_name == 'workflow_dispatch':
        state = provenance(root, expected_sha, expected_sha)
        manual = True
    else:
        raise ValueError('Unsupported workflow event')
    state.update(required=required(state['changed_files'], manual), manual=manual)
    state['native_acceptance'] = 'NOT RUN'  # Selection never claims native PASS.
    state['reason'] = 'manual request' if manual else ('runtime dependency changed' if state['required'] else 'no runtime dependency changed')
    return state


def main():
    try:
        root = Path(__file__).resolve().parents[2]
        event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        result = select(root, os.environ['GITHUB_EVENT_NAME'], event, os.environ['GITHUB_SHA'])
        # Only trusted booleans/verified SHA go into Actions control outputs.
        with open(os.environ['GITHUB_OUTPUT'], 'a') as output:
            output.write('required='+str(result['required']).lower()+'\n')
            output.write('base='+result['base']+'\n')
            output.write('comparison_base='+result['comparison_base']+'\n')
        with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as summary:
            summary.write('Selection: PASS. Native acceptance: NOT RUN ('+result['reason']+').\n')
            summary.write('Source SHA: `'+result['sha']+'`. Native job below supplies acceptance evidence if selected.\n')
        print(json.dumps({k: result[k] for k in ('sha','comparison_base','required','manual','native_acceptance','reason')}))
    except (KeyError, ValueError, OSError, subprocess.SubprocessError):
        print('FAIL regression selection: invalid event, history or source; no skip output is valid', file=sys.stderr)
        raise SystemExit(1) from None


if __name__ == '__main__':
    main()
