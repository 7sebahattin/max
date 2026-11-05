"""UI labels must be available in both supported native locales."""
from pathlib import Path
import re
import unittest

ROOT=Path(__file__).resolve().parents[2]/'modules/maxorders'


class Locales(unittest.TestCase):
    def test_parity_and_literal_screen_labels(self):
        locales={}
        for lang in ['en_US','tr_TR']:
            entries={}
            for row in (ROOT/'langs'/lang/'maxorders.lang').read_text().splitlines():
                if row and not row.startswith('#') and '=' in row:
                    key,value=row.split('=',1)
                    self.assertNotIn(key,entries)
                    self.assertTrue(value.strip())
                    entries[key]=value
            locales[lang]=entries
        self.assertEqual(set(locales['en_US']),set(locales['tr_TR']))
        for source in ROOT.rglob('*.php'):
            labels=re.findall(r"mo(?:Label|Button)\('([A-Za-z0-9_]+)'\)",source.read_text())
            labels+=re.findall(r"moInput\('[^']+','([A-Za-z0-9_]+)'",source.read_text())
            for key in labels:
                for lang,entries in locales.items():
                    with self.subTest(file=source.name,key=key,locale=lang):
                        self.assertIn(key,entries)
