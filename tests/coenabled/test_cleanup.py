import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
class CleanupReporting(unittest.TestCase):
    def test_failure_and_missing_evidence_never_become_pass(self):
        script=Path(__file__).with_name('cleanup.py').resolve()
        for test_status,cleanup_status in [(0,0),(1,0),(0,1)]:
            with self.subTest(test=test_status,cleanup=cleanup_status),tempfile.TemporaryDirectory() as directory:
                r=subprocess.run([sys.executable,str(script),str(test_status),str(cleanup_status)],cwd=directory,capture_output=True)
                self.assertNotEqual(r.returncode,0)
                data=json.loads((Path(directory)/'.local/coenabled-evidence/cleanup.json').read_text())
                self.assertFalse(data['passed']);self.assertEqual(data['owned_resources_removed'],cleanup_status==0)
                partial=json.loads((Path(directory)/'.local/coenabled-evidence/incomplete.json').read_text())
                self.assertEqual(sum(x['required'] for x in partial['counts'].values()),63)
                self.assertTrue(all(x['status']=='NOT_RUN' for x in partial['counts'].values()))
