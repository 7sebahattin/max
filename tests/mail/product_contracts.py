"""Strict MAX requirements against actual native observations. No xfail or skip.

These currently fail for verified upstream 24.0.1. The research deliverable is
BLOCKED for native-only MAX intake, not a claim that the Mail Center is ready.
"""
import json
import unittest
from pathlib import Path


class NativeProductContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.report=json.loads(Path('.local/mail-report.json').read_text())
        cls.protocol=json.loads(Path('.local/mail-protocol.json').read_text())

    def test_native_agenda_alone_preserves_account_scoped_intake(self):
        first_b=next(row for row in self.report if row['account']=='b')
        self.assertEqual(first_b['events'],4,
            'BLOCKED: four distinct account messages produced only three agenda events; Message-ID collides across accounts')

    def test_injected_fetch_no_is_reported_as_failure(self):
        row=next(row for row in self.report if row['phase']=='injected_fetch_failure')
        self.assertTrue(any(f['account']=='b' and f['fault']=='fetch_no' for f in self.protocol['faults']),
            'Fixture must prove real FETCH NO, not infer failure from an empty inbox')
        self.assertLess(row['result'],0,
            'BLOCKED: native collector returns success after real tagged FETCH NO; retry monitoring cannot rely on this status')


if __name__=='__main__':
    unittest.main(verbosity=2)
