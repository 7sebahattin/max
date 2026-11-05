import copy
import unittest
from evidence import strict,SURFACES,WIDTHS
class Completeness(unittest.TestCase):
    def native(self):return [{'width':w,'surface':s,'phase':'after','body':w,'document':w} for w in WIDTHS for s in SURFACES['native']]
    def test_complete_native_cartesian_set(self):self.assertEqual(len(strict(self.native(),'native')),15)
    def test_missing_or_duplicate_check_fails(self):
        rows=self.native()
        for invalid in (rows[:-1],rows[:-1]+[rows[0]]):
            with self.assertRaises(AssertionError):strict(invalid,'native')
    def test_body_document_overflow_and_wrong_viewport_fail(self):
        for field in ('body','document','width'):
            rows=self.native();rows[0][field]+=1
            with self.assertRaises(AssertionError):strict(rows,'native')
    def test_baseline_is_not_acceptance(self):
        rows=self.native();rows[0]['phase']='before'
        with self.assertRaises(AssertionError):strict(rows,'native')
