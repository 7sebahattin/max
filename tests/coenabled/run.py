"""One installation orchestrator; all upstream/merged helpers are executed read-only."""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys

OUT=Path('.local/coenabled-evidence'); OUT.mkdir(exist_ok=True)
STAGES=[]
def failure_details(output):
    details=[]
    kinds=re.findall(r'(?m)^(?:[A-Za-z0-9_.]+\.)?([A-Za-z][A-Za-z0-9]*(?:Error|Exception)):',output)
    if kinds: details.append('FAILTYPE '+kinds[-1])
    for filename,line in re.findall(r'File "([^"]+)", line ([0-9]+)',output)[-6:]:
        name=Path(filename).name
        if re.fullmatch(r'[A-Za-z0-9_.-]{1,80}',name): details.append('FAILTRACE '+name+':'+line)
    return details

def command(label,args,env=None):
    row={'stage':label,'passed':False}; STAGES.append(row)
    try:
        r=subprocess.run(args,capture_output=True,text=True,timeout=600,env=env)
        output=r.stdout+'\n'+r.stderr
        safe=[x for x in output.splitlines() if re.fullmatch(r'(PASS|FAIL|ASSERT|AssertionError:) [A-Za-z0-9 /_+.,:()=-]{1,180}',x)]
        if r.returncode: safe.extend(failure_details(output))
        for line in r.stderr.splitlines():
            if line.startswith('COENABLED_NATIVE_ERROR '): row['native_error']=json.loads(line.split(' ',1)[1])
        row.update(exit_code=r.returncode,log=safe[-18:]); print(('PASS' if r.returncode==0 else 'FAIL')+' '+label,flush=True)
        if r.returncode: raise RuntimeError('Stage failed: '+label)
        row['passed']=True
    finally:
        (OUT/'stages.json').write_text(json.dumps(STAGES,indent=2))
        for name in ('mailintake-report.json','mailintake-protocol.json','mailintake-summary.json'):
            file=Path('.local')/name
            if file.is_file(): shutil.copyfile(file,OUT/name)
def php(path,*args): command(path+' '+str(args[0] if args else ''),['docker','compose','exec','-T','--user','www-data','web','php','/checks/'+path,*args])
def copy(source,target):
    command('private fixture transfer',['docker','compose','cp','web:/var/lib/dolibarr/documents/'+source,'.local/'+target]); Path('.local/'+target).chmod(0o600)
def checkpoint(label): php('coenabled/runtime.php',label)
def python(path,*args): command(path,['python3',path,*args])

def main():
    php('coenabled/runtime.php','bootstrap')
    php('orders/runtime.php','setup')
    php('customs/runtime.php','setup')
    php('mobile/runtime.php','prepare')
    # Private one-shot account seed; fake IMAP service is internal, no published port.
    command('private IMAP seed transfer',['docker','compose','cp','.local/mailintake-state/seed.json','web:/var/lib/dolibarr/documents/mailintake-input.json'])
    command('private seed ownership',['docker','compose','exec','-T','web','chown','www-data:www-data','/var/lib/dolibarr/documents/mailintake-input.json'])
    php('mailintake/native.php','setup')
    checkpoint('freeze'); copy('coenabled-identity.json','coenabled-evidence/identity.json'); copy('coenabled-core-roles.json','coenabled-evidence/core-roles.json')
    checkpoint('chain'); copy('coenabled-chain.json','coenabled-evidence/chain.json')
    for source,target in [('coenabled-privacy.json','coenabled-privacy.json'),('coenabled-logo.jpg','coenabled-logo.jpg'),('maxcore-accounts.json','maxcore-accounts.json'),('coenabled-core-admin','maxcore-admin'),('orders-accounts.json','orders-accounts.json'),('customs-session.json','customs-session.json'),('mobile-accounts.json','mobile-accounts.json')]: copy(source,target)
    order_accounts=Path('.local/orders-accounts.json')
    private=json.loads(order_accounts.read_text())
    private['admin']['password']=Path('.local/p2-admin-secret').read_text().strip()
    order_accounts.write_text(json.dumps(private),encoding='utf-8');order_accounts.chmod(0o600)
    order_env=os.environ.copy()
    order_env.update(MAX_INPUT_NATIVE_RUN=str(OUT.resolve()),MAX_INPUT_NATIVE_HTTP='8080',
                     MAX_INPUT_NATIVE_ACCOUNTS=str(order_accounts.resolve()),
                     MAX_INPUT_NATIVE_CHECKS=str((OUT/'order-write-checks.json').resolve()))
    command('native order notes and Incoterms HTTP acceptance',
            [sys.executable,'tests/input/order_write_http.py'],env=order_env)
    for lane in ('core','orders','customs'):
        checkpoint('before-'+lane)
        python('tests/coenabled/observe.py',lane)
        checkpoint('after-'+lane)
    php('orders/runtime.php','verify'); php('customs/runtime.php','verify')
    # Global title/artwork, two brands and native 15 checks while all five enabled.
    python('tests/coenabled/browser.py')
    checkpoint('mail-before')
    python('tests/mailintake/scenarios.py')
    php('mailintake/native.php','publish-fixture')
    copy('mailintake-session.json','mailintake-session.json')
    python('tests/coenabled/observe.py','mail')
    checkpoint('mail-after')
    python('tests/coenabled/roles.py')
    php('mailintake/native.php','verify'); checkpoint('final')
    copy('coenabled-final.json','coenabled-evidence/final.json')
    python('tests/coenabled/evidence.py')

if __name__=='__main__':
    try: main()
    except Exception as e:
        print('FAIL coenabled stage; sanitized stages.json records the assertion boundary',file=sys.stderr)
        raise SystemExit(1) from None
