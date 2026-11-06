#!/usr/bin/env python3
"""Explicit local-only input checks. No install, Docker, Actions or provider calls."""
import argparse
import json
from pathlib import Path
import subprocess
import sys

ROOT=Path(__file__).resolve().parents[1]


def run_checks(commands,out,report):
    """Replace stale results first; a crash/timeout cannot leave an earlier PASS."""
    report['checks']={name:'NOT RUN' for name,_ in commands}

    def save():
        temporary=out/'input-local-summary.json.tmp'
        temporary.write_text(json.dumps(report,indent=2))
        temporary.replace(out/'input-local-summary.json')

    save()
    for name,command in commands:
        report['checks'][name]='FAIL'
        save()  # Fail closed even if interrupted while the child is running.
        with (out/('input-local-'+name+'.log')).open('w') as log:
            try:
                result=subprocess.run(command,cwd=ROOT,stdout=log,stderr=subprocess.STDOUT,timeout=120)
                passed=result.returncode==0
            except (subprocess.TimeoutExpired,OSError):
                passed=False
                log.write('\nFAIL local command timed out or could not start\n')
        report['checks'][name]='PASS' if passed else 'FAIL'
        save()
        print(report['checks'][name]+' '+name)
        if not passed:break
    return 1 if 'FAIL' in report['checks'].values() else 0


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php',required=True,type=Path,help='Existing compatible PHP CLI, no installation performed')
    parser.add_argument('--dolibarr',required=True,type=Path,help='Existing checksum-verified pinned htdocs, read only')
    parser.add_argument('--php-ini',type=Path,help='Optional existing local PHP configuration; system configuration stays unchanged')
    parser.add_argument('--browser',action='store_true',help='Use already installed Playwright and sandboxed /usr/bin/chromium')
    args=parser.parse_args()
    php=[str(args.php.resolve())];native=str(args.dolibarr.resolve());out=ROOT/'.local';out.mkdir(exist_ok=True)
    if args.php_ini:php+=['-c',str(args.php_ini.resolve())]
    commands=[('normalizer',php+['tests/input/unit.php',str(out/'input-unit.json')]),
              ('native_library',php+['tests/input/native_adapter_unit.php',native,str(out/'input-native-library.json')]),
              ('source_preservation',php+['tests/input/source_unit.php',native,str(out/'input-source.json')]),
              ('original_extraction',php+['tests/orders/extraction_unit.php',native,str(out/'input-orders-baseline.json')]),
              ('policy',[sys.executable,'-m','unittest','discover','-s','tests/input','-v'])]
    if args.browser:commands.append(('synthetic_browser',[sys.executable,'tests/input/browser.py']))
    report={'source_sha':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),
            'worktree_clean':not subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True).strip(),
            'scope':'local_unit_library_synthetic_only','github_ci':'NOT RUN (owner paused)',
            'installed_native_db_http_roles_csrf':'NOT RUN','original_shared_63':'NOT RUN'}
    result=run_checks(commands,out,report)
    if not args.browser:
        report['checks']['synthetic_browser']='NOT RUN'
        (out/'input-local-summary.json').write_text(json.dumps(report,indent=2))
    return result


if __name__=='__main__':raise SystemExit(main())
