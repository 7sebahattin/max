import imaplib
import tempfile
import threading
import unittest
from pathlib import Path
from lab import MailboxLab,read_seed,save_seed
from seed import seed

class IndependentWire(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory(); self.path=Path(self.temp.name)/'seed'
        self.data=seed(); save_seed(self.path,self.data)
        self.server=MailboxLab(('127.0.0.1',0),self.path)
        threading.Thread(target=self.server.serve_forever,daemon=True).start()
    def tearDown(self):
        self.server.shutdown(); self.server.server_close(); self.temp.cleanup()
    def client(self,alias):
        box=next(b for b in self.data['mailboxes'] if b['alias']==alias)
        conn=imaplib.IMAP4(*self.server.server_address); conn.login(box['login'],box['secret']); conn.select('INBOX',readonly=True)
        self.assertEqual(conn.response('UIDVALIDITY')[1],[str(box['epoch']).encode()])
        return conn
    def test_two_scoped_identities_and_reused_epoch(self):
        for alias,uid in [('alpha',101),('beta',201)]:
            with self.client(alias) as conn:
                self.assertIn(str(uid).encode(),conn.uid('SEARCH',None,'UNDELETED')[1][0])
                result=conn.uid('FETCH',str(uid),'(UID RFC822.SIZE BODY.PEEK[])')
                self.assertEqual(result[0],'OK'); self.assertIn(b'<shared@intake.invalid>',result[1][0][1])
        self.data['mailboxes'][0]['epoch']=9001; save_seed(self.path,self.data)
        with self.client('alpha') as conn: self.assertEqual(conn.uid('SEARCH',None,'UNDELETED')[0],'OK')
    def test_final_no_after_partial_data_is_a_real_failure(self):
        self.data['mailboxes'][0]['faults']['101']='partial_no'; save_seed(self.path,self.data)
        with self.client('alpha') as conn:
            status,data=conn.uid('FETCH','101','(UID RFC822.SIZE BODY.PEEK[])')
            self.assertEqual(status,'NO'); self.assertTrue(data[0][1])
        trace=read_seed(self.path)['trace']
        self.assertTrue(any(t['command']=='UID FETCH' and t['status']=='NO' for t in trace))
        self.assertNotIn(self.data['mailboxes'][0]['secret'],str(trace))
    def test_deleted_uid_and_missing_header_remain_controlled(self):
        self.data['mailboxes'][0]['items'][0]['deleted']=True; save_seed(self.path,self.data)
        with self.client('alpha') as conn:
            self.assertNotIn(b'101',conn.uid('SEARCH',None,'UNDELETED')[1][0].split())
            status,data=conn.uid('FETCH','102','(UID RFC822.SIZE BODY.PEEK[])')
            self.assertEqual(status,'OK'); self.assertNotIn(b'Message-ID:',data[0][1])
