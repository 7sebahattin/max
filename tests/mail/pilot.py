"""Drive actual native polling; publish numeric/hash evidence only."""
import json
import subprocess
from pathlib import Path
from fake_imap import state_read, state_write
from fixtures import message

STATE=Path('.local/mail-state/state.json')
REPORT=Path('.local/mail-report.json')
steps=[]

def poll(alias, expected, failure=False):
    run=subprocess.run(['docker','compose','exec','-T','web','php','/checks/mail/native.php','poll',alias],capture_output=True,text=True,check=True)
    # Never echo native diagnostics/credentials if JSON parsing fails.
    try:
        result=json.loads(run.stdout.strip())
    except ValueError:
        raise AssertionError('Native poll did not return safe JSON') from None
    steps.append(dict(account=alias, **result))
    REPORT.write_text(json.dumps(steps,indent=2))
    Path('.local/mail-protocol.json').write_text(json.dumps(state_read(STATE).get('commands',[]),indent=2))
    assert result['events']==expected, f'Native event count mismatch: {result["events"]}, expected {expected}'
    assert (result['result']<0) if failure else (result['result']>0), 'Unexpected native polling status'
    assert result['sender_attribution']==expected, 'Sender attribution mismatch'
    return result

r=poll('a',2)
assert r['attachments'] and r['attachments'][0]['name']=='unknown.pdf', 'Native attachment metadata missing'
assert r['attachments'][0]['size']==len(b'%PDF-broken')
r=poll('b',3)  # Shared Message-ID suppresses a DIFFERENT account message: proven gap.
assert r['attachments'][0]['name']=='facts.txt'
poll('a',3); poll('b',3)
subprocess.run(['docker','compose','restart','fakeimap'],check=True,stdout=subprocess.DEVNULL)
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
subprocess.run(['docker','compose','exec','-T','web','php','/checks/mail/native.php','cron'],check=True)
poll('a',5); poll('b',5)
s=state_read(STATE)
assert {c['account'] for c in s['commands']} >= {'a','b'}
assert {'LOGIN','SELECT','SEARCH','FETCH'} <= {c['command'] for c in s['commands']}
Path('.local/mail-protocol.json').write_text(json.dumps(s['commands'],indent=2))
print('PASS native wire polling, cross-account Message-ID gap, epochs, replay, restart, auth retry, attachments')
