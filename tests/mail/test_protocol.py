import imaplib
import tempfile
import threading
import unittest
from pathlib import Path
from fake_imap import Server, state_read, state_write
from fixtures import initial, message


class Protocol(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.path = Path(self.temp.name)/'state.json'
        self.state = initial()
        state_write(self.path, self.state)
        self.server = Server(('127.0.0.1', 0), self.path)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.temp.cleanup()

    def connect(self, alias):
        conn = imaplib.IMAP4(*self.server.server_address)
        acc = self.state['accounts'][alias]
        conn.login(acc['login'], acc['password'])
        self.assertEqual(conn.select('Orders')[0], 'OK')
        self.assertEqual(conn.response('UIDVALIDITY')[1], [str(acc['epoch']).encode()])
        return conn

    def test_two_accounts_literals_duplicates_and_flags(self):
        for alias, uid in [('a', 101), ('b', 201)]:
            with self.connect(alias) as conn:
                self.assertIn(str(uid).encode(), conn.uid('SEARCH', None, 'UNDELETED')[1][0])
                status, data = conn.uid('FETCH', str(uid), '(RFC822.HEADER)')
                self.assertEqual(status, 'OK')
                self.assertIn(b'<shared@fixture.invalid>', data[0][1])
                self.assertEqual(conn.uid('STORE', str(uid), '+FLAGS', '(\\Seen)')[0], 'OK')
                self.assertIn(b'\\Seen', conn.uid('FETCH', str(uid), '(FLAGS)')[1][0])
        transcript = state_read(self.path)['commands']
        self.assertTrue(all(set(c) == {'command', 'uid', 'account'} for c in transcript))

    def test_epoch_restart_and_temporary_failure(self):
        with self.connect('a') as conn:
            self.assertEqual(conn.uid('FETCH', '102', '(RFC822.TEXT)')[0], 'OK')
        self.state['accounts']['a'].update(epoch=3001, messages=[message(101, 'new-epoch')], fail_fetch=True)
        state_write(self.path, self.state)
        with self.connect('a') as conn:
            self.assertEqual(conn.uid('FETCH', '101', '(RFC822.TEXT)')[0], 'NO')
            self.assertEqual(state_read(self.path)['faults'][-1], {'account':'a','fault':'fetch_no','uid':True})
        self.state['accounts']['a']['fail_fetch'] = False
        state_write(self.path, self.state)
        with self.connect('a') as conn:
            self.assertEqual(conn.uid('FETCH', '101', '(RFC822.HEADER)')[0], 'OK')

    def test_auth_failure_is_closed(self):
        self.state['accounts']['a']['fail_auth'] = True
        state_write(self.path, self.state)
        with imaplib.IMAP4(*self.server.server_address) as conn:
            with self.assertRaises(imaplib.IMAP4.error):
                conn.login(self.state['accounts']['a']['login'], self.state['accounts']['a']['password'])


if __name__ == '__main__':
    unittest.main()
