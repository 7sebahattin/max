"""Disposable accounts and messages; private state must never be an artifact."""
import base64
import secrets
from email.message import EmailMessage
from email.policy import SMTP
from fake_imap import state_write


def message(uid, message_id, body='Synthetic document', malformed=False):
    msg = EmailMessage(policy=SMTP)
    msg['Message-ID'] = f'<{message_id}@fixture.invalid>'
    msg['Date'] = 'Tue, 03 Nov 2026 12:00:00 +0000'
    msg['From'] = 'Buyer <buyer@partner.invalid>'
    msg['Reply-To'] = 'untrusted@partner-lookalike.invalid'
    msg['To'] = 'intake@fixture.invalid'
    msg['Subject'] = 'Orders fixture'
    msg.set_content(body)
    msg.add_attachment(b'%PDF-broken' if malformed else b'Synthetic attachment\n',
                       maintype='application' if malformed else 'text', subtype='pdf' if malformed else 'plain',
                       filename='unknown.pdf' if malformed else 'facts.txt')
    return dict(uid=uid, raw=base64.b64encode(msg.as_bytes()).decode(), flags=[])


def initial():
    return {'accounts': {alias: dict(login=f'{alias}@fixture.invalid', password=secrets.token_hex(24), epoch=epoch,
            messages=messages) for alias, epoch, messages in [
                ('a', 1001, [message(101, 'shared'), message(102, 'a-only', malformed=True)]),
                ('b', 2001, [message(201, 'shared', 'Different account content'), message(202, 'b-only')])]}}


if __name__ == '__main__':
    from pathlib import Path
    path = Path('.local/mail-state/state.json')
    path.parent.mkdir(parents=True, exist_ok=True)
    state_write(path, initial())
    path.chmod(0o600)
