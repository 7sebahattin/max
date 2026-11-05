"""One installation orchestrator; all upstream/merged helpers are executed read-only."""
import json
import os
from pathlib import Path
import re
import subprocess
import sys

OUT=Path('.local/coenabled-evidence'); OUT.mkdir(exist_ok=True)
STAGES=[]
def command(label,args):
    row={'stage':label,'passed':False}; STAGES.append(row)
    try:
        r=subprocess.run(args,capture_output=True,text=True,timeout=600)
        safe=[x for x in (r.stdout+'\n'+r.stderr).splitlines() if re.fullmatch(r'(PASS|FAIL|ASSERT|AssertionError:) [A-Za-z0-9 /_+.,:()=-]{1,180}',x)]
        row.update(exit_code=r.returncode,log=safe[-18:]); print(('PASS' if r.returncode==0 else 'FAIL')+' '+label,flush=True)
        if r.returncode: raise RuntimeError('Stage failed: '+label)
        row['passed']=True
    finally: (OUT/'stages.json').write_text(json.dumps(STAGES,indent=2))
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
    checkpoint('freeze'); copy('coenabled-identity.json','coenabled-evidence/identity.json')
    checkpoint('chain'); copy('coenabled-chain.json','coenabled-evidence/chain.json')
    for source,target in [('coenabled-logo.jpg','coenabled-logo.jpg'),('maxcore-accounts.json','maxcore-accounts.json'),('coenabled-core-admin','maxcore-admin'),('orders-accounts.json','orders-accounts.json'),('customs-session.json','customs-session.json'),('mobile-accounts.json','mobile-accounts.json')]: copy(source,target)
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
