"""Fail before credentials are created if artifact routing can include them."""
import fnmatch
from pathlib import Path
import unittest


class ArtifactPolicy(unittest.TestCase):
    def paths(self):
        workflow=(Path(__file__).resolve().parents[2]/'.github/workflows/maxorders.yml').read_text()
        block=workflow.split('          path: |\n',1)[1]
        paths=[]
        for line in block.splitlines():
            if not line.startswith('            '):break
            paths.append(line.strip())
        self.assertTrue(paths)
        return paths

    def test_public_result_inventory_is_explicit(self):
        expected={'.local/orders-mobile.json','.local/orders-intake-parser.json',
                  '.local/orders-intake-native.json','.local/orders-intake-http.json',
                  '.local/orders-intake-mobile.json','.local/orders-*.png',
                  '.local/orders-table-parser.json','.local/orders-table-native.json',
                  '.local/orders-table-http.json','.local/orders-table-mobile.json','.local/orders-pdf-probe.json',
                  '.local/maxorders-module.zip'}
        self.assertEqual(set(self.paths()),expected)

    def test_private_runtime_files_never_match_artifact_patterns(self):
        patterns=self.paths()
        private=['.local/orders-table-session.json','.local/orders-table-inputs/browser.xlsx',
                 '.local/orders-table-inputs/concurrent.xlsx','.local/orders-table-before',
                 '.local/orders-intake-session.json','.local/orders-accounts.json',
                 '.local/orders-intake-inputs/manual.pdf','.local/orders-intake-inputs/browser.xlsx',
                 '.local/p2-admin-secret','.env','.local/conf/conf.php',
                 '.local/orders-intake-credentials.json','.local/orders-intake-future-private.json']
        for name in private:
            with self.subTest(path=name):
                self.assertFalse(any(fnmatch.fnmatchcase(name,pattern) for pattern in patterns))
