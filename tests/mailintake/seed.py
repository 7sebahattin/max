"""Fresh synthetic message sources; not the PR #23 fixture suite."""
import base64
import secrets
from email.message import EmailMessage
from email.policy import SMTP
from pathlib import Path
from lab import save_seed

def wire(uid,identity='shared',payload=b'Synthetic text\n',filename='note.txt',pdf=False):
    email=EmailMessage(policy=SMTP)
    email['Date']='Tue, 03 Nov 2026 10:00:00 +0000'
    email['From']='Source <sender@source.invalid>'
    email['Reply-To']='untrusted@lookalike.invalid'
    email['To']='receiver@intake.invalid'
    email['Subject']='Synthetic intake '+str(uid)
    if identity is not None: email['Message-ID']=f'<{identity}@intake.invalid>'
    email.set_content('Synthetic body; no classification or authorization.')
    email.add_attachment(payload,maintype='application' if pdf else 'text',subtype='pdf' if pdf else 'plain',filename=filename)
    return {'uid':uid,'wire':base64.b64encode(email.as_bytes()).decode()}

def seed():
    alpha={'alias':'alpha','login':'alpha@intake.invalid','secret':secrets.token_hex(24),'epoch':1001,
           'items':[wire(101),wire(102,None),wire(103,'path',filename='../../escape.txt')], 'faults':{}}
    beta={'alias':'beta','login':'beta@intake.invalid','secret':secrets.token_hex(24),'epoch':2001,
          'items':[wire(201,payload=b'Other account same Message-ID\n'),wire(202,'unknown',b'%PDF-1.4\n%%EOF','unknown.pdf',True),
                   wire(203,'broken',b'%PDF-broken','broken.pdf',True),wire(204,'locked',b'%PDF-1.4\n/Encrypt\n%%EOF','locked.pdf',True),
                   wire(205,'large',b'X'*524320,'large.txt')], 'faults':{}}
    return {'mailboxes':[alpha,beta],'trace':[]}

if __name__=='__main__':
    root=Path('.local/mailintake-state'); root.mkdir(parents=True,exist_ok=True)
    p=root/'seed.json'; save_seed(p,seed()); p.chmod(0o600)
