"""Real native PHP service against independent, private socket IMAP sources."""
import atexit
import concurrent.futures
import json
import subprocess
from pathlib import Path
from lab import save_seed
from seed import wire

ROOT=Path('.local/mailintake-state/seed.json')
def run(*args, structured=False):
    result=subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','/checks/mailintake/native.php',*map(str,args)],capture_output=True,text=True)
    if result.returncode:
        # Native fixture emits only fixed assertion codes, never exception arguments.
        safe=[x for x in result.stderr.splitlines() if x.startswith('ASSERT ') or x.startswith('native intake fixture failure')]
        raise AssertionError('Native command failed: '+args[0]+' '+(' '.join(safe)[:180]))
    if structured:
        try: return json.loads(result.stdout)
        except ValueError: raise AssertionError('Native JSON response unavailable') from None
    return result.stdout

def stats(): return run('stats',structured=True)
def state(): return json.loads(ROOT.read_text())
def update(change):
    data=state(); change(data); save_seed(ROOT,data)
def box(data,alias): return next(x for x in data['mailboxes'] if x['alias']==alias)
def poll(alias='alpha',role='operator'): return run('poll',alias,role,structured=True)
def entry(uid,epoch=1001):
    matches=[x for x in stats()['entries'] if int(x['imap_uid'])==uid and int(x['uidvalidity'])==epoch]
    assert len(matches)==1,'Unique account epoch UID ledger identity'
    return matches[0]
def files(e): return [x for x in stats()['files'] if int(x['fk_entry'])==int(e['rowid'])]
def check(value,label):
    if not value: raise AssertionError(label)
    print('PASS '+label,flush=True)
def add(uid,fault=None):
    def change(data):
        b=box(data,'alpha'); b['items'].append(wire(uid,str(uid)))
        if fault: b['faults'][str(uid)]=fault
    update(change)

def diagnostic():
    # Fixed metadata projection only; never raw mail, login, secrets or hashes.
    try:
        Path('.local/mailintake-report.json').write_text(json.dumps(stats(),indent=2))
        Path('.local/mailintake-protocol.json').write_text(json.dumps({'trace':state()['trace']},indent=2))
    except Exception:
        print('Sanitized diagnostics unavailable',flush=True)
atexit.register(diagnostic)

# These are real TCPDF files generated in the native runtime, including encryption.
private=Path('.local/mailintake-pdfs'); private.mkdir(exist_ok=True)
for kind,uid in [('unknown',202),('locked',204)]:
    subprocess.run(['docker','compose','cp','web:/var/lib/dolibarr/documents/mailintake-fixtures/'+kind+'.pdf',str(private/(kind+'.pdf'))],check=True,capture_output=True)
    payload=(private/(kind+'.pdf')).read_bytes()
    assert payload.startswith(b'%PDF-')
    if kind=='locked': assert b'/Encrypt' in payload
    def replace(data,uid=uid,payload=payload,kind=kind):
        b=box(data,'beta'); b['items']=[wire(uid,kind,payload,kind+'.pdf',True) if x['uid']==uid else x for x in b['items']]
    update(replace)
poll(); poll('beta','manager')
s=stats(); print('Native initial metadata '+json.dumps(s),flush=True); check(len(s['entries'])==8 and s['shared_count']==2,'Same Message-ID in two accounts retained separately')
check(int(entry(102)['stored'])==1,'Message without Message-ID retained by source identity')
for uid,reason in [(202,'pdf_unknown_type'),(203,'pdf_invalid_signature'),(204,'pdf_encrypted'),(205,'attachment_oversized')]:
    e=entry(uid,2001); fs=files(e)
    check(e['state']=='review_required' and any(f['reason']==reason for f in fs),'Quarantine source '+str(uid))
base=(len(s['entries']),len(s['files']),s['disk_files'])
for unused in range(8): poll(); poll('beta','manager')
s=stats(); check(base==(len(s['entries']),len(s['files']),s['disk_files']),'Sixteen replays preserve rows and private files')
add(301); update(lambda d: box(d,'alpha').update(delay=0.4))
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    results=list(pool.map(lambda _:poll(),range(4)))
update(lambda d: box(d,'alpha').update(delay=0))
e=entry(301)
trace=state()['trace']
check(int(e['stored'])==1 and len(files(e))==2 and sum(x.get('uid')==301 and x.get('full',False) for x in trace)==1,'Concurrent workers create one identity and one file set')
check(any(x['state']=='busy' for x in results),'Concurrent account lock refuses overlapping worker')
add(401,'no'); result=poll(); e=entry(401)
check(result['state']=='retry_due' and int(e['attempts'])==1 and not int(e['stored']) and not files(e),'FETCH NO persists retry not success')
poll(); check(int(entry(401)['attempts'])==1,'Automatic retry respects backoff')
update(lambda d: box(d,'alpha')['faults'].pop('401'))
run('retry',e['rowid'],'reviewer'); poll(); e=entry(401)
check(int(e['attempts'])==2 and int(e['stored'])==1,'Explicit retry recovers verified source')
add(402,'partial_no'); poll(); e=entry(402)
for unused in range(2): run('retry',e['rowid'],'reviewer'); poll()
e=entry(402)
check(e['state']=='review_required' and int(e['attempts'])==3 and not int(e['stored']) and not files(e),'Partial bytes followed by NO never committed and retry ceiling enforced')
run('retry-denied',e['rowid'],'reviewer')
update(lambda d: box(d,'alpha')['faults'].pop('402')); poll(); check(int(entry(402)['attempts'])==3,'Fault clearance does not bypass manual review')
add(403,'omit'); poll(); e=entry(403)
check(e['state']=='review_required' and not int(e['stored']) and not files(e),'Tagged OK missing body blocked without file')
run('retry-denied',e['rowid'],'reviewer')
add(404,'oversize'); poll(); e=entry(404)
check(e['state']=='review_required' and not int(e['stored']) and not any(x.get('uid')==404 and x.get('full') for x in state()['trace']),'Oversize rejected before full FETCH')
add(405,'short'); poll(); e=entry(405)
check(e['state']=='review_required' and not int(e['stored']) and not files(e),'Incomplete literal cannot become success')
old_files=stats()['disk_files']
update(lambda d: box(d,'beta').update(epoch=9001,items=[wire(201,payload=b'New epoch source\n')]))
poll('beta','manager'); e=entry(201,9001)
check(e['state']=='review_required' and int(e['stored'])==1 and int(entry(201,2001)['remote_deleted'])==1,'UIDVALIDITY reset preserves old identities and flags new epoch')
check(stats()['disk_files']==old_files+2,'Epoch reset retains previous private evidence')
def remove_sources(data):
    a=box(data,'alpha'); a['items']=[x for x in a['items'] if x['uid']!=102]
    next(x for x in a['items'] if x['uid']==101)['deleted']=True
update(remove_sources); poll()
check(int(entry(101)['remote_deleted'])==1 and int(entry(102)['remote_deleted'])==1,'Deleted and expunged sources retained as tombstones')
update(lambda d: next(x for x in box(d,'alpha')['items'] if x['uid']==101).update(deleted=False)); poll()
check(int(entry(101)['remote_deleted'])==1,'Source reappearance does not resurrect retired identity')
# Authentication backoff and credential rotation preserve the ledger identity.
update(lambda d: box(d,'alpha').update(reject_login=True))
result=poll(); check(result['state']=='blocked' and result['code']=='connect_failed','Authentication failure has explicit provenance')
s=stats(); a=next(x for x in s['accounts'] if int(x['rowid'])==int(entry(101)['fk_account']))
check(a['state']=='retry_due' and int(a['connection_failures'])==1,'Connection failure persists bounded backoff')
update(lambda d: box(d,'alpha').update(reject_login=False))
rotation=Path('.local/mailintake-rotate.json'); rotation.write_text(json.dumps({'secret':box(state(),'alpha')['secret']})); rotation.chmod(0o600)
subprocess.run(['docker','compose','cp',str(rotation),'web:/var/lib/dolibarr/documents/mailintake-rotate.json'],check=True,capture_output=True)
subprocess.run(['docker','compose','exec','-T','web','chown','www-data:www-data','/var/lib/dolibarr/documents/mailintake-rotate.json'],check=True,capture_output=True)
run('rotate','alpha'); rotation.unlink(); poll()
check(int(entry(402)['attempts'])==3,'Credential rotation does not reset message retry ceiling')
trace=state()['trace']
check(not any(x['command'] in ['STORE','UID STORE','EXPUNGE','APPEND','COPY','UID COPY','MOVE','UID MOVE'] for x in trace),'Source mailbox remains read only')
s=stats(); check(s['disk_files']==sum(int(x['stored']) for x in s['files']),'No duplicate or orphan final private file')
run('verify')
Path('.local/mailintake-report.json').write_text(json.dumps({'checks':'passed','replays':16,'workers':4,'entries':s['entries'],'files':s['files'],'disk_files':s['disk_files']},indent=2))
Path('.local/mailintake-protocol.json').write_text(json.dumps({'trace':trace},indent=2))
print('PASS native protocol identity concurrency failure and preservation scenarios')
