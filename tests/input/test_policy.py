"""Local-only policy/registry checks; no Docker/provider/Actions invocation."""
import json
from pathlib import Path
import subprocess
import unittest

ROOT=Path(__file__).resolve().parents[2]


def unique(pairs):
    data={}
    for key,value in pairs:
        if key in data:raise ValueError('duplicate key')
        data[key]=value
    return data


class InputPolicy(unittest.TestCase):
    def setUp(self):self.registry=json.loads((ROOT/'modules/maxcore/input-fields.json').read_text(),object_pairs_hook=unique)

    def test_registry_has_fixed_locale_and_bounded_explicit_business_roles(self):
        self.assertEqual(self.registry['locale'],'tr_TR')
        protected={'password','token','secret','username','host','folder','port','email','url','ref','reference','sku','quantity','price','mode','action','id','api_key'}
        for family in ['web','api','imports']:
            self.assertTrue(self.registry[family])
            for path,fields in self.registry[family].items():
                for key,rule in fields.items():
                    with self.subTest(path=path,field=key):
                        self.assertNotIn(key.rsplit('.',1)[-1].lower(),protected)
                        self.assertIn(rule[0],['text','html'])
                        self.assertIs(type(rule[1]),int)
                        self.assertGreater(rule[1],0);self.assertLessEqual(rule[1],65535)

    def test_new_function_contract_is_recorded_and_ci_hold_is_explicit(self):
        text=(ROOT/'AGENTS.md').read_text()
        for term in ['Issue #36','MaxInput','input-fields.json','AFTER conversion','CSS-only','Do not push','BLOCKED/NOT RUN']:
            self.assertIn(term,text)

    def test_core_native_all_hook_and_native_id_rights_remain(self):
        source=(ROOT/'modules/maxcore/core/modules/modMaxcore.class.php').read_text()
        self.assertIn("'hooks'=>array('all')",source)
        self.assertIn('$this->numero=610120',source)
        self.assertIn('61012001+$i',source)

    def test_all_writing_max_modules_declare_core_dependency(self):
        for module in ['maxorders','maxcustoms','maxmail']:
            text=next((ROOT/'modules'/module/'core/modules').glob('mod*.class.php')).read_text()
            self.assertIn("'modMaxcore'",text)

    def test_no_workflow_or_other_draft_source_was_changed(self):
        changes=subprocess.check_output(['git','diff','origin/main','--name-only','--','.github/workflows','modules/maxmobile'],cwd=ROOT,text=True)
        self.assertEqual(changes,'')
        self.assertFalse((ROOT/'modules/maxorders/class/table.class.php').exists(),'Unmerged PR #34 source must not be copied')


if __name__=='__main__':unittest.main()
