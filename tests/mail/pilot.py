"""Drive actual native polling; publish numeric/hash evidence only."""
import hashlib
import json
import subprocess
from pathlib import Path
from fake_imap import state_read, state_write
from fixtures import message

STATE=Path('.local/mail-state/state.json')
REPORT=Path('.local/mail-report.json')
steps=[]

def poll(alias, expected, failure=False, observed_fetch_gap=False):
    run=subprocess.run(['docker','compose','exec','-T','web','php','/checks/mail/native.php','poll',alias],capture_output=True,text=True,check=False)
    Path('.local/mail-protocol.json').write_text(json.dumps(state_read(STATE).get('commands',[]),indent=2))
    # Never echo native diagnostics/credentials if JSON parsing fails.
    try:
        result=json.loads(run.stdout.strip())
    except ValueError:
        raise AssertionError(f'Native poll did not return safe JSON (exit {run.returncode})') from None
    steps.append(dict(account=alias, phase='injected_fetch_failure' if observed_fetch_gap else 'poll', **result))
    REPORT.write_text(json.dumps(steps,indent=2))
    Path('.local/mail-protocol.json').write_text(json.dumps(state_read(STATE).get('commands',[]),indent=2))
    assert run.returncode==0, f'Native fixture failure: {result}'
    assert result['events']==expected, f'Native event count mismatch: {result}, expected {expected}'
    if observed_fetch_gap:
        # Exact native characterization; the strict MAX contract remains failing below.
        assert result['result']==1 and result['attachments']==[], 'Known native failure semantics changed; review required'
        assert any(f['account']==alias and f['fault']=='fetch_no' for f in state_read(STATE).get('faults',[])), 'FETCH NO was not actually injected'
        print('BLOCKED native fetch failure was reported as success; strict contract will fail')
    else:
        assert (result['result']<0) if failure else (result['result']>0), 'Unexpected native polling status'
    assert result['sender_attribution']==expected, 'Sender attribution mismatch'
    return result

r=poll('a',2)
assert r['attachments'] and r['attachments'][0]['name']=='unknown.pdf', 'Native attachment metadata missing'
assert r['attachments'][0]['size']==len(b'%PDF-broken')
assert r['attachments'][0]['sha256']==hashlib.sha256(b'%PDF-broken').hexdigest()
assert r['attachments'][0]['mime'] in ['application/pdf','text/plain']
r=poll('b',3)  # Shared Message-ID suppresses a DIFFERENT account message: proven gap.
assert r['attachments'][0]['name']=='facts.txt'
assert r['attachments'][0]['mime']=='text/plain' and r['attachments'][0]['sha256']==hashlib.sha256(b'Synthetic attachment\n').hexdigest()
poll('a',3); poll('b',3)
subprocess.run(['docker','compose','restart','fakeimap'],check=True,stdout=subprocess.DEVNULL)
subprocess.run(['docker','compose','up','-d','--wait','--wait-timeout','30','fakeimap'],check=True,stdout=subprocess.DEVNULL)
poll('a',3)
s=state_read(STATE)
s['accounts']['a'].update(epoch=3001,messages=[message(101,'new-epoch'),message(102,'shared')])
state_write(STATE,s)
poll('a',4)
s=state_read(STATE); s['accounts']['a']['fail_auth']=True
s['accounts']['a']['messages'].append(message(103,'retry'))
state_write(STATE,s)
poll('a',4,failure=True)
s=state_read(STATE); s['accounts']['a']['fail_auth']=False; state_write(STATE,s)
poll('a',5); poll('a',5)
s=state_read(STATE); s['accounts']['b']['fail_fetch']=True
s['accounts']['b']['messages'].append(message(203,'body-retry'))
state_write(STATE,s)
poll('b',5,observed_fetch_gap=True)
s=state_read(STATE); s['accounts']['b']['fail_fetch']=False; state_write(STATE,s)
poll('b',6); poll('b',6)
subprocess.run(['docker','compose','exec','-T','web','php','/checks/mail/native.php','cron'],check=True)
poll('a',6); poll('b',6)
s=state_read(STATE)
assert {c['account'] for c in s['commands']} >= {'a','b'}
assert {'LOGIN','SELECT','SEARCH','FETCH'} <= {c['command'] for c in s['commands']}
Path('.local/mail-protocol.json').write_text(json.dumps({'commands':s['commands'],'faults':s.get('faults',[])},indent=2))
print('PASS native wire characterization, epochs, replay, restart, auth/fetch retry and attachments; MAX contracts remain BLOCKED')
