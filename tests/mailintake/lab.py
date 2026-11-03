"""Independent tiny IMAP laboratory, purpose-built for Issue #25.

No imports or copied fixtures from PR #23. Subset: LOGIN, EXAMINE/SELECT,
UID SEARCH UNDELETED, single-UID metadata/full-message FETCH, read-only sequence UID mapping and LOGOUT.
Only synthetic private seed bytes; trace contains command/status/alias/UID.
"""
import argparse
import base64
import hmac
import json
import re
import shlex
import socketserver
import threading
import time
from pathlib import Path

mutex=threading.RLock()

def read_seed(path):
    with mutex:
        return json.loads(Path(path).read_text())

def save_seed(path,seed):
    with mutex:
        temporary=Path(str(path)+'.replace')
        temporary.write_text(json.dumps(seed))
        temporary.replace(path)

class MailboxLab(socketserver.ThreadingTCPServer):
    allow_reuse_address=True
    daemon_threads=True
    def __init__(self,address,seed):
        self.seed=seed
        super().__init__(address,Session)
    def handle_error(self,request,client_address):
        # Do not let socketserver serialize untrusted command arguments.
        pass

class Session(socketserver.StreamRequestHandler):
    def handle(self):
        self.alias=None
        self.selected=False
        self.line('* OK isolated intake laboratory')
        for raw in iter(lambda:self.rfile.readline(8192),b''):
            try:
                text=raw.decode('ascii').strip()
                tag,command=text.split(' ',1)
                with mutex:
                    seed=read_seed(self.server.seed)
                    verb=command.split(' ',1)[0].upper()
                    uid=0
                    if verb=='UID':
                        fields=command.split(' ',3)
                        verb='UID '+fields[1].upper()
                        if verb=='UID FETCH': uid=int(fields[2])
                    trace={'command':verb,'alias':self.alias,'uid':uid,'status':'OK'}
                    result=self.dispatch(seed,tag,command,trace)
                    seed.setdefault('trace',[]).append(trace)
                    save_seed(self.server.seed,seed)
                if result=='logout': break
            except (BrokenPipeError,ConnectionResetError):
                break
            except Exception:
                try:self.line('* BAD laboratory request rejected')
                except OSError:break
    def line(self,line):
        self.wfile.write(line.encode('ascii')+b'\r\n')
    def dispatch(self,seed,tag,command,trace):
        def done(code='OK'):
            trace['status']=code
            self.line(tag+' '+code+' laboratory')
        verb=trace['command']
        if verb=='CAPABILITY':
            self.line('* CAPABILITY IMAP4rev1')
            done(); return
        if verb=='LOGOUT':
            self.line('* BYE closed'); done(); return 'logout'
        if verb=='LOGIN':
            parts=shlex.split(command)[1:]
            for box in seed['mailboxes']:
                if len(parts)==2 and parts[0]==box['login'] and hmac.compare_digest(parts[1],box['secret']) and not box.get('reject_login'):
                    self.alias=box['alias']; trace['alias']=self.alias; done(); return
            done('NO'); return
        if self.alias is None:
            done('NO'); return
        box=next(b for b in seed['mailboxes'] if b['alias']==self.alias)
        if verb in ['SELECT','EXAMINE']:
            folder=shlex.split(command)[1]
            if folder!='INBOX': done('NO'); return
            self.selected=True
            self.line('* FLAGS (\\Seen \\Deleted)')
            self.line(f'* {len(box["items"])} EXISTS')
            self.line('* 0 RECENT')
            self.line(f'* OK [UIDVALIDITY {box["epoch"]}] stable')
            self.line(f'* OK [UIDNEXT {max([i["uid"] for i in box["items"]]+[0])+1}] next')
            done(); return
        if not self.selected:
            done('NO'); return
        if verb=='UID SEARCH':
            ids=[str(i['uid']) for i in box['items'] if not i.get('deleted')]
            self.line('* SEARCH'+(' '+' '.join(ids) if ids else ''))
            done(); return
        if verb=='NOOP': done(); return
        if verb=='FETCH':
            # Bundled Message::make -> setUid -> getMessageNumber requests this
            # read-only mapping. It does not fetch body bytes or alter flags.
            if command.upper() not in ('FETCH 1:* (UID)','FETCH 1:* UID'):
                done('BAD'); return
            for n,item in enumerate(box['items'],1):
                self.line(f'* {n} FETCH (UID {item["uid"]})')
            done(); return
        if verb!='UID FETCH':
            done('BAD'); return
        candidates=[(n+1,item) for n,item in enumerate(box['items']) if item['uid']==trace['uid']]
        if not candidates:
            done('NO'); return
        sequence,item=candidates[0]
        body=base64.b64decode(item['wire'])
        mode=box.get('faults',{}).get(str(item['uid']),'')
        full='BODY.PEEK[]' in command.upper()
        trace['full']=full
        size=len(body)+(2097153 if mode=='oversize' else 0)
        if not full:
            self.line(f'* {sequence} FETCH (UID {item["uid"]} RFC822.SIZE {size})')
            done(); return
        if box.get('delay'): time.sleep(min(float(box['delay']),1))
        if mode=='no': done('NO'); return
        if mode=='omit':
            self.line(f'* {sequence} FETCH (UID {item["uid"]} RFC822.SIZE {size})')
            done(); return
        if mode=='short': body=body[:-7]
        self.wfile.write(f'* {sequence} FETCH (UID {item["uid"]} RFC822.SIZE {size} BODY[] {{{len(body)}}}\r\n'.encode('ascii')+body+b')\r\n')
        done('NO' if mode=='partial_no' else 'OK')

if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('seed')
    parser.add_argument('--port',type=int,default=1143)
    args=parser.parse_args()
    with MailboxLab(('0.0.0.0',args.port),args.seed) as server:
        server.serve_forever()
