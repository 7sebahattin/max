"""Synthetic RFC3501 subset, never a production server. No command arguments logged."""
import argparse
import base64
import json
import re
import shlex
import socketserver
import threading
from pathlib import Path

LOCK = threading.RLock()


def state_read(path):
    with LOCK:
        return json.loads(Path(path).read_text())


def state_write(path, value):
    with LOCK:
        tmp = Path(str(path)+'.tmp')
        tmp.write_text(json.dumps(value))
        tmp.replace(path)


class Handler(socketserver.StreamRequestHandler):
    def handle(self):
        self.account = None
        self.selected = False
        self.wfile.write(b'* OK synthetic IMAP ready\r\n')
        while line := self.rfile.readline(16384):
            try:
                parts = shlex.split(line.decode().strip(), posix=True)
                tag, cmd, *args = parts
                cmd = cmd.upper()
                uid = cmd == 'UID'
                if uid:
                    cmd = args.pop(0).upper()
                with LOCK:
                    state = state_read(self.server.state_path)
                    state.setdefault('commands', []).append({'command': cmd, 'uid': uid, 'account': self.account})
                    state_write(self.server.state_path, state)
                    self.command(state, tag, cmd, args, uid)
                if cmd == 'LOGOUT':
                    break
            except (ValueError, IndexError, UnicodeError):
                self.wfile.write(b'* BAD invalid fixture command\r\n')
            except (BrokenPipeError, ConnectionResetError):
                break

    def send(self, value):
        self.wfile.write(value.encode()+b'\r\n')

    def command(self, state, tag, cmd, args, uid):
        finish = lambda status='OK', msg='completed': self.send(f'{tag} {status} {msg}')
        if cmd == 'CAPABILITY':
            self.send('* CAPABILITY IMAP4rev1 UIDPLUS')
        elif cmd == 'LOGIN':
            for alias, acc in state['accounts'].items():
                if args == [acc['login'], acc['password']] and not acc.get('fail_auth'):
                    self.account = alias
                    break
            else:
                finish('NO', 'authentication unavailable')
                return
        elif cmd == 'LOGOUT':
            self.send('* BYE synthetic logout')
        elif not self.account:
            finish('NO', 'authenticate first')
            return
        else:
            acc = state['accounts'][self.account]
            messages = acc['messages']
            if cmd in ('LIST', 'LSUB'):
                self.send('* LIST (\\HasNoChildren) "/" "Orders"')
            elif cmd in ('SELECT', 'EXAMINE'):
                if not args or args[0] != 'Orders':
                    finish('NO', 'no such mailbox')
                    return
                self.selected = True
                self.send('* FLAGS (\\Seen \\Deleted)')
                self.send(f'* {len(messages)} EXISTS')
                self.send('* 0 RECENT')
                self.send(f'* OK [UIDVALIDITY {acc["epoch"]}] valid')
                self.send(f'* OK [UIDNEXT {max([m["uid"] for m in messages]+[0])+1}] next')
                finish('OK', '[READ-WRITE] selected')
                return
            elif cmd == 'STATUS':
                self.send(f'* STATUS Orders (MESSAGES {len(messages)} UNSEEN {len(messages)} UIDVALIDITY {acc["epoch"]} UIDNEXT {max([m["uid"] for m in messages]+[0])+1})')
            elif cmd in ('NOOP', 'CLOSE', 'EXPUNGE'):
                pass
            elif not self.selected:
                finish('NO', 'select first')
                return
            elif cmd == 'SEARCH':
                selected = [str(m['uid'] if uid else i) for i, m in enumerate(messages, 1)
                            if '\\Deleted' not in m.get('flags', [])]
                self.send('* SEARCH' + (' '+' '.join(selected) if selected else ''))
            elif cmd in ('FETCH', 'STORE'):
                ids = set()
                for item in args[0].split(','):
                    if ':' in item:
                        a, b = item.split(':')
                        ceiling = max([m['uid'] for m in messages]+[0]) if uid else len(messages)
                        a, b = (ceiling if n == '*' else int(n) for n in (a, b))
                        ids.update(range(min(a, b), max(a, b)+1))
                    else:
                        ids.add(int(item))
                request = ' '.join(args[1:]).upper()
                for seq, msg in enumerate(messages, 1):
                    if (msg['uid'] if uid else seq) not in ids:
                        continue
                    if cmd == 'STORE':
                        msg['flags'] = ['\\Seen'] if 'SEEN' in request else msg.get('flags', [])
                        state_write(self.server.state_path, state)
                        self.send(f'* {seq} FETCH (UID {msg["uid"]} FLAGS ({" ".join(msg.get("flags", []))}))')
                        continue
                    if acc.get('fail_fetch'):
                        state.setdefault('faults', []).append({'account':self.account,'fault':'fetch_no','uid':uid})
                        state_write(self.server.state_path, state)
                        finish('NO', 'temporary fixture fetch failure')
                        return
                    raw = base64.b64decode(msg['raw'])
                    header, sep, body = raw.partition(b'\r\n\r\n')
                    if 'RFC822.HEADER' in request:
                        key, value = 'RFC822.HEADER', header+sep
                    elif 'RFC822.TEXT' in request:
                        key, value = 'RFC822.TEXT', body
                    elif 'RFC822' in request or 'BODY' in request:
                        key, value = 'RFC822', raw
                    else:
                        self.send(f'* {seq} FETCH (UID {msg["uid"]} FLAGS ({" ".join(msg.get("flags", []))}))')
                        continue
                    self.wfile.write(f'* {seq} FETCH (UID {msg["uid"]} {key} {{{len(value)}}}\r\n'.encode()+value+b')\r\n')
            else:
                finish('BAD', 'unsupported fixture command')
                return
        finish()


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True

    def __init__(self, address, path):
        self.state_path = path
        super().__init__(address, Handler)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--state', required=True)
    parser.add_argument('--port', type=int, default=1143)
    options = parser.parse_args()
    with Server(('0.0.0.0', options.port), options.state) as server:
        server.serve_forever()
