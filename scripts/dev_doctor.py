#!/usr/bin/env python3
"""Read-only MAX workstation checks. No config/secret reads or Docker mutations."""
from __future__ import annotations

import argparse
from dataclasses import asdict, dataclass
import json
import os
from pathlib import Path
import platform
import re
import shutil
import subprocess
import sys

MIN_FREE_BYTES = 15 * 1024**3
DOCKER_SELECTORS = ('DOCKER_HOST', 'DOCKER_CONTEXT', 'DOCKER_TLS',
                    'DOCKER_TLS_VERIFY', 'DOCKER_CERT_PATH')
GIT_SELECTORS = ('GIT_DIR', 'GIT_WORK_TREE', 'GIT_COMMON_DIR', 'GIT_INDEX_FILE')


@dataclass(frozen=True)
class Check:
    code: str
    status: str
    message: str


def run_command(args, cwd, env):
    """Capture bounded diagnostic commands; never surface their raw output/errors."""
    try:
        result = subprocess.run(args, cwd=cwd, env=env, capture_output=True,
                                text=True, timeout=5, shell=False, check=False)
        return result.returncode, result.stdout
    except (OSError, subprocess.TimeoutExpired, UnicodeError):
        return -1, ''


def version(text):
    match = re.search(r'(?<![\w.])v?(\d+)\.(\d+)(?:\.(\d+))?', text)
    return tuple(map(int, match.groups(default='0'))) if match else None


def diagnose(root, *, run=run_command, system=None, release=None,
             python_version=None, disk=shutil.disk_usage, environ=None):
    root = Path(root).resolve()
    system = system if system is not None else platform.system()
    release = release if release is not None else platform.release()
    py = python_version if python_version is not None else sys.version_info[:3]
    inherited = dict(os.environ if environ is None else environ)
    env = {k: v for k, v in inherited.items()
           if k not in DOCKER_SELECTORS + GIT_SELECTORS}
    checks = []

    def add(code, status, message):
        checks.append(Check(code, status, message))

    if system == 'Linux':
        add('os', 'pass', 'WSL/Linux shell detected; verify WSL version 2 with wsl -l -v.' if 'microsoft' in release.lower()
            else 'Linux shell detected; WSL2/Desktop integration is not certified here.')
    elif system == 'Windows':
        add('os', 'warn', 'Doctor supports Windows; run MAX Bash/Compose setup inside WSL2 Linux.')
    else:
        add('os', 'warn', 'This OS is outside the documented Windows/WSL2/Linux setup.')
    add('python', 'pass' if py >= (3, 12, 0) else 'fail',
        'Python 3.12+ is available.' if py >= (3, 12, 0) else 'Install Python 3.12+ in this shell.')

    # Fixed local endpoints: never inherit a potentially remote/production context.
    endpoint = 'npipe:////./pipe/docker_engine' if system == 'Windows' else 'unix:///var/run/docker.sock'
    if any(inherited.get(k) for k in DOCKER_SELECTORS):
        add('docker_selectors', 'warn', 'Docker endpoint/context/TLS selectors ignored; only the local socket is checked.')

    if not root.is_dir():
        add('repo', 'fail', 'Repository directory does not exist.')
        return report(checks)
    required = ['AGENTS.md', 'upstream.lock.json', 'compose.yaml', '.env.example', 'scripts/smoke.sh']
    code, top = run(['git', '-C', str(root), 'rev-parse', '--show-toplevel'], root, env)
    try:
        at_root = code == 0 and Path(top.strip()).resolve() == root
    except (OSError, ValueError):
        at_root = False
    add('repo', 'pass' if at_root and all((root / f).is_file() for f in required) else 'fail',
        'MAX repository root identified.' if at_root and all((root / f).is_file() for f in required)
        else 'Select the MAX repository root (linked Git worktrees are supported).')

    for name, args, minimum in [
        ('git', ['git', '--version'], (2, 0, 0)),
        ('docker_cli', ['docker', '--version'], (20, 10, 0)),
        ('compose', ['docker', '--host=' + endpoint, 'compose', 'version', '--short'], (2, 0, 0)),
    ]:
        code, output = run(args, root, env)
        found = version(output) if code == 0 else None
        add(name, 'pass' if found and found >= minimum else 'fail',
            f'{name}: compatible version {".".join(map(str, found))} detected.' if found and found >= minimum
            else f'{name}: missing, failed, or older than the supported minimum; see DEVELOPER_WINDOWS.md.')

    code, output = run(['docker', '--host=' + endpoint, 'info', '--format', '{{.ServerVersion}}'], root, env)
    add('docker_engine', 'pass' if code == 0 and version(output) else 'fail',
        'Local Docker engine responded (no containers enumerated).'
        if code == 0 and version(output) else 'Local Docker engine unavailable; start Desktop/Engine and check WSL integration/socket access.')
    try:
        free = disk(root).free
        add('disk', 'pass' if free >= MIN_FREE_BYTES else 'fail',
            'At least 15 GiB free on the repository filesystem.' if free >= MIN_FREE_BYTES
            else 'Less than 15 GiB free on the repository filesystem; free space without global Docker pruning.')
    except OSError:
        add('disk', 'fail', 'Cannot inspect repository filesystem free space.')
    if system == 'Linux' and 'microsoft' in release.lower() and root.as_posix().startswith('/mnt/'):
        add('wsl_path', 'warn', 'Repository is under /mnt; prefer the WSL Linux filesystem for permissions and bind mounts.')
    add('coverage', 'warn', 'Docker VM disk, CA/network access, private GitHub authorization and Windows browser forwarding are not tested.')
    return report(checks)


def report(checks):
    failed = any(c.status == 'fail' for c in checks)
    return {'schema_version': 1, 'failed': failed,
            'checks': [asdict(c) for c in checks]}


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, default=Path(__file__).resolve().parents[1],
                        help='MAX repository root; defaults to the script repository')
    parser.add_argument('--json', action='store_true', help='Emit a machine-readable report without raw command output')
    args = parser.parse_args(argv)
    result = diagnose(args.repo)
    if args.json:
        print(json.dumps(result, indent=2))
    else:
        for check in result['checks']:
            print(f"{check['status'].upper():4} {check['code']}: {check['message']}")
        print('Read-only checks complete; warnings are not a full environment certification.')
    return 1 if result['failed'] else 0


if __name__ == '__main__':
    sys.exit(main())
