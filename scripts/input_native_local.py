#!/usr/bin/env python3
"""Disposable Windows native Dolibarr input regression; existing free tools only.

No downloads, service registration, Docker, provider, GitHub or upstream patches.
Requires a fresh checksum-verified upstream extraction and portable MariaDB.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import sys
import time
import stat
from concurrent.futures import ThreadPoolExecutor

ROOT = Path(__file__).resolve().parents[1]


def port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


def digest_tree(root):
    def entry(p):
        return str(p.relative_to(root)),hashlib.sha256(p.read_bytes()).hexdigest()
    with ThreadPoolExecutor(max_workers=12) as pool:
        return dict(pool.map(entry,(p for p in root.rglob('*') if p.is_file())))


def unlink_owned(path):
    if path.exists():
        os.chmod(path,stat.S_IWRITE)
        path.unlink()


def remove_owned(target,parent):
    relative=target.resolve().relative_to(parent.resolve())
    if not relative.parts or target.is_symlink():
        raise RuntimeError('Refusing unsafe cleanup target')
    def writable_retry(function,path,exc):
        if not isinstance(exc,PermissionError): raise exc
        os.chmod(path,stat.S_IWRITE); function(path)
    if target.exists(): shutil.rmtree(target,onexc=writable_retry)


def verify_stage_report(run,label):
    required={
        'native_fixture':('fixture-checks.json','baseline written once'),
        'native_http':('http-checks.json','native browser no external requests'),
        'native_wizard_http':('wizard-checks.json','wizard final source integrity and network checks'),
        'native_people_http':('people-checks.json','people final source integrity and network checks'),
        'native_order_http':('order-checks.json','orders final source integrity network and owned draft cleanup'),
        'native_import_lifecycle_snapshot':('verify-checks.json','commercial bank IBAN stock order invoice payment and legal snapshot unchanged'),
    }
    if label not in required:return
    name,terminal=required[label]
    rows=json.loads((run/name).read_text(encoding='utf-8'))
    if not rows or any(row['status']!='PASS' for row in rows) or terminal not in {row['check'] for row in rows}:
        raise RuntimeError('Incomplete native stage evidence')
    if label=='native_import_lifecycle_snapshot':
        if json.loads((run/'before.json').read_text())!=json.loads((run/'after.json').read_text()):
            raise RuntimeError('Native snapshot mismatch')


def recover(run,native):
    """Recover only a stopped, manifest-owned local run after interrupted teardown."""
    run=run.resolve();native=native.resolve()
    if run.parent!=ROOT/'.local' or not run.name.startswith('input-native-'):
        raise RuntimeError('Recovery path outside owned local runtime')
    owner=json.loads((run/'owner.json').read_text(encoding='utf-8'))
    if Path(owner['root']).resolve()!=native or not owner['database'].startswith('max_input_'):
        raise RuntimeError('Recovery ownership mismatch')
    try:
        with socket.create_connection(('127.0.0.1',owner['port']),timeout=.2):
            raise RuntimeError('Refusing recovery while database port is live')
    except OSError: pass
    config=native/'conf/conf.php'
    if config.exists():
        if ("$dolibarr_main_db_name = '"+owner['database']+"';") not in config.read_text(encoding='utf-8'):
            raise RuntimeError('Refusing foreign native configuration')
        unlink_owned(config)
    # Modules may be removed only when their contents equal this checkout.
    for module in (ROOT/'modules').iterdir():
        target=native/'custom'/module.name
        if module.is_dir() and target.exists():
            if digest_tree(target)!=digest_tree(module): raise RuntimeError('Foreign module contents')
            remove_owned(target,native/'custom')
    for name in ('database','documents','sessions'): remove_owned(run/name,run)
    for name in ('client.ini','accounts.json','owner.json'): unlink_owned(run/name)
    print('PASS stopped owned runtime recovered',flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', required=True, type=Path)
    parser.add_argument('--php-ini', required=True, type=Path)
    parser.add_argument('--dolibarr', required=True, type=Path)
    parser.add_argument('--mariadb', required=True, type=Path, help='Portable bin directory')
    parser.add_argument('--recover-run', type=Path, help='Only clean a stopped manifest-owned interrupted run, then exit')
    args = parser.parse_args()
    if os.name != 'nt':
        parser.error('This runner is explicitly for the portable Windows runtime')
    native = args.dolibarr.resolve()
    dbbin = args.mariadb.resolve()
    if args.recover_run:
        recover(args.recover_run,native)
        return 0
    summary=ROOT/'.local/input-native-summary.json'
    summary.parent.mkdir(exist_ok=True)
    identity=dict(source_sha=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip(),
                  worktree_clean=not subprocess.check_output(['git','status','--porcelain'],cwd=ROOT,text=True).strip())
    # A failed preflight must invalidate evidence from any earlier invocation.
    summary.write_text(json.dumps(dict(**identity,result='BLOCKED',preflight='NOT RUN',
                                      checks={},cleanup='NOT RUN'),indent=2),encoding='utf-8')
    config = native/'conf/conf.php'
    if config.exists() or any((native/'custom'/p.name).exists() for p in (ROOT/'modules').iterdir() if p.is_dir()):
        parser.error('Refusing an existing configuration or MAX module installation')
    lock = json.loads((ROOT/'upstream.lock.json').read_text(encoding='utf-8'))
    archive = ROOT/'.local/dolibarr-24.0.1.tar.gz'
    if hashlib.sha256(archive.read_bytes()).hexdigest() != lock['sha256']:
        parser.error('Pinned upstream archive checksum mismatch')
    # Compare every supplied htdocs byte with the verified archive, not just version.inc.php.
    import tarfile
    print('Checking every native source byte against pinned archive',flush=True)
    before = digest_tree(native)
    with tarfile.open(archive) as source:
        expected = {}
        for member in source:
            if member.isfile() and '/htdocs/' in member.name:
                name = member.name.split('/htdocs/', 1)[1].replace('/', os.sep)
                expected[name] = hashlib.sha256(source.extractfile(member).read()).hexdigest()
    if before != expected:
        parser.error('Native source differs from the checksum-verified archive')
    run = ROOT/'.local'/('input-native-'+secrets.token_hex(6))
    run.mkdir(parents=True)
    docs = run/'documents'; docs.mkdir()
    sessions = run/'sessions'; sessions.mkdir()
    datadir = run/'database'
    dbport, httpport = port(), port()
    dbpass, adminpass = secrets.token_hex(24), secrets.token_hex(24)
    dbname = 'max_input_'+secrets.token_hex(5)
    env = os.environ.copy()
    env.update(MAX_INPUT_NATIVE_RUN=str(run), MAX_INPUT_NATIVE_ROOT=str(native),
               MAX_INPUT_NATIVE_DB=dbname, MAX_INPUT_NATIVE_PORT=str(dbport),
               MAX_INPUT_NATIVE_HTTP=str(httpport), MAX_INPUT_ADMIN_PASS=adminpass)
    php = [str(args.php.resolve()), '-c', str(args.php_ini.resolve()),
           '-d', 'session.save_path='+sessions.as_posix(), '-d', 'sendmail_path=disabled-max-local',
           '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'error_log='+(run/'php.log').as_posix()]
    processes, handles, installed = [], [], []
    report = dict(**identity,
                  scope='portable_windows_installed_native', run_id=run.name,
                  upstream_sha256=lock['sha256'], preflight='PASS',
                  checks={name:'NOT RUN' for name in ('database_init','database_create','installer_schema','installer_admin','native_fixture','native_http','native_wizard_http','native_people_http','native_order_http','native_import_lifecycle_snapshot')},
                  original_shared_63='NOT RUN (Docker identity/network contract unavailable)',
                  github_ci='NOT RUN (owner hold)', cleanup='NOT RUN')

    def save():
        summary.write_text(json.dumps(report, indent=2), encoding='utf-8')

    def command(label, argv, cwd=ROOT, data=None, timeout=300):
        report['checks'][label] = 'FAIL'; save()
        with (run/(label+'.log')).open('w', encoding='utf-8') as log:
            result = subprocess.run(argv, cwd=cwd, env=env, input=data, text=True,
                                    stdout=log, stderr=subprocess.STDOUT, timeout=timeout,
                                    creationflags=subprocess.CREATE_NO_WINDOW)
        if result.returncode:
            raise RuntimeError(label)
        # Dolibarr's WAF/access-denied paths can exit(0). Exit status alone is
        # never proof that a fixture or acceptance assertion actually executed.
        verify_stage_report(run,label)
        report['checks'][label] = 'PASS'; save(); print('PASS '+label, flush=True)

    def start(argv, label, cwd=ROOT):
        handle = (run/(label+'.log')).open('w', encoding='utf-8'); handles.append(handle)
        proc = subprocess.Popen(argv,cwd=cwd,env=env,stdout=handle,stderr=subprocess.STDOUT,
                                creationflags=subprocess.CREATE_NO_WINDOW)
        processes.append(proc)
        return proc

    def ready(proc, number):
        for _ in range(100):
            if proc.poll() is not None: raise RuntimeError('owned process exited')
            try:
                with socket.create_connection(('127.0.0.1',number), timeout=.2): return
            except OSError: time.sleep(.1)
        raise RuntimeError('owned listener timeout')

    save()
    try:
        command('database_init',[str(dbbin/'mariadb-install-db.exe'),'--datadir='+str(datadir),
                                '--password='+dbpass,'--port='+str(dbport),'--silent'])
        server = start([str(dbbin/'mariadbd.exe'),'--no-defaults','--basedir='+str(dbbin.parent),
                        '--datadir='+str(datadir),'--bind-address=127.0.0.1','--port='+str(dbport),
                        '--skip-name-resolve','--character-set-server=utf8mb4',
                        '--collation-server=utf8mb4_unicode_ci','--max-allowed-packet=16M'], 'database')
        ready(server, dbport)
        client = run/'client.ini'
        client.write_text('[client]\nuser=root\npassword='+dbpass+'\nhost=127.0.0.1\nport='+str(dbport)+'\n',encoding='utf-8')
        command('database_create',[str(dbbin/'mariadb.exe'),'--defaults-extra-file='+str(client)],
                data=f"CREATE DATABASE {dbname} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
                     f"CREATE USER 'max_input'@'127.0.0.1' IDENTIFIED BY '{dbpass}';\n"
                     f"GRANT ALL ON {dbname}.* TO 'max_input'@'127.0.0.1';\n")
        values = dict(dolibarr_main_url_root=f'http://127.0.0.1:{httpport}',
                      dolibarr_main_document_root=native.as_posix(),dolibarr_main_url_root_alt='/custom',
                      dolibarr_main_document_root_alt=(native/'custom').as_posix(),
                      dolibarr_main_data_root=docs.as_posix(),dolibarr_main_db_host='127.0.0.1',
                      dolibarr_main_db_port=str(dbport),dolibarr_main_db_name=dbname,
                      dolibarr_main_db_prefix='llx_',dolibarr_main_db_user='max_input',
                      dolibarr_main_db_pass=dbpass,dolibarr_main_db_type='mysqli',
                      dolibarr_main_db_character_set='utf8mb4',dolibarr_main_db_collation='utf8mb4_unicode_ci',
                      dolibarr_main_authentication='dolibarr',dolibarr_main_prod='0',
                      dolibarr_main_instance_unique_id=secrets.token_hex(24),dolibarr_main_distrib='standard')
        config.write_text('<?php\n'+''.join('$'+k+" = '"+v+"';\n" for k,v in values.items()),encoding='utf-8')
        (run/'owner.json').write_text(json.dumps(dict(root=str(native),database=dbname,port=dbport)),encoding='utf-8')
        command('installer_schema',php+['step2.php','set','en_US'],native/'install')
        command('installer_admin',php+['step5.php','','','en_US','set','maxadmin',adminpass,adminpass,'1'],native/'install')
        for module in (ROOT/'modules').iterdir():
            if module.is_dir():
                target = native/'custom'/module.name
                shutil.copytree(module,target); installed.append(target)
        command('native_fixture',php+[str(ROOT/'tests/input/installed.php'),'fixture'])
        web = start(php+['-S',f'127.0.0.1:{httpport}','-t',str(native),str(ROOT/'tests/input/router.php')], 'http',native)
        ready(web,httpport)
        http_passed=True
        try: command('native_order_http', [sys.executable,str(ROOT/'tests/input/order_http.py')],timeout=600)
        except RuntimeError: http_passed=False
        try: command('native_http', [sys.executable,str(ROOT/'tests/input/installed_http.py')])
        except RuntimeError: http_passed=False
        try: command('native_wizard_http', [sys.executable,str(ROOT/'tests/input/wizard_http.py')])
        except RuntimeError: http_passed=False
        try: command('native_people_http', [sys.executable,str(ROOT/'tests/input/people_http.py')],timeout=600)
        except RuntimeError: http_passed=False
        command('native_import_lifecycle_snapshot',php+[str(ROOT/'tests/input/installed.php'),'verify'])
        report['result']='PASS' if http_passed else 'FAIL'
    except Exception as exc:
        report['result']='FAIL'
        report['failure']=str(exc) if isinstance(exc,RuntimeError) else type(exc).__name__
        print('FAIL native run; see private local stage logs',flush=True)
    finally:
        # Only terminate PIDs this invocation created; never stop global services.
        for proc in reversed(processes):
            if proc.poll() is None:
                proc.terminate()
                try: proc.wait(timeout=15)
                except subprocess.TimeoutExpired: proc.kill(); proc.wait(timeout=10)
        for handle in handles: handle.close()
        cleanup_errors=[]
        targets=[(target,native/'custom') for target in installed]+[(target,run) for target in (datadir,docs,sessions)]
        for target,parent in targets:
            try: remove_owned(target,parent)
            except OSError as exc: cleanup_errors.append(type(exc).__name__)
        try: unlink_owned(config)
        except OSError as exc: cleanup_errors.append(type(exc).__name__)
        # Delete owned runtime secrets and commercial DB, retain only local evidence/logs.
        for name in ('client.ini','accounts.json'):
            try: unlink_owned(run/name)
            except OSError as exc: cleanup_errors.append(type(exc).__name__)
        if not cleanup_errors: unlink_owned(run/'owner.json')
        try: unchanged = digest_tree(native)==before
        except OSError as exc:
            unchanged=False;cleanup_errors.append(type(exc).__name__)
        closed = True
        for number in (dbport,httpport):
            try:
                with socket.create_connection(('127.0.0.1',number),timeout=.2): closed=False
            except OSError: pass
        report['cleanup']='PASS' if not cleanup_errors and unchanged and closed and all(p.poll() is not None for p in processes) else 'FAIL'
        report['cleanup_errors']=cleanup_errors
        report['upstream_bytes_unchanged']=unchanged
        report['owned_ports_closed']=closed
        save()
    return 0 if report.get('result')=='PASS' and report['cleanup']=='PASS' else 1


if __name__=='__main__': raise SystemExit(main())
