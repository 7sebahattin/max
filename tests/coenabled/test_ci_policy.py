"""Portable selection/source tests. No Docker, browser, network, billing or provider."""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

from evidence import strict, SURFACES, WIDTHS
from regression_policy import PATHS, matches, provenance, required, select

ROOT = Path(__file__).resolve().parents[2]


class TriggerCoverage(unittest.TestCase):
    def test_five_modules_and_all_shared_helpers(self):
        for module in ('maxcore','maxmobile','maxorders','maxcustoms','maxmail'):
            for name in ('class/service.class.php','core/modules/descriptor.php','css/mobile.css','langs/tr_TR/module.lang'):
                with self.subTest(module=module,name=name):
                    self.assertTrue(required(['modules/'+module+'/'+name]))
        for folder in ('coenabled','maxcore','mobile','orders','customs','mailintake','branding'):
            self.assertTrue(required(['tests/'+folder+'/nested/fixture.py']))
        for prefix in ('coenabled','maxcore','mobile','orders','customs','mailintake','branding'):
            self.assertTrue(required(['scripts/'+prefix+'_test.sh']))
        for path in ('tests/input/order_write_http.py','scripts/input_native_local.py'):
            with self.subTest(path=path): self.assertTrue(required([path]))

    def test_order_write_native_suite_is_required_in_shared_installation(self):
        self.assertIn('tests/input/order_write_http.py', (ROOT/'tests/coenabled/run.py').read_text())
        self.assertIn('order-write-checks.json', (ROOT/'tests/coenabled/evidence.py').read_text())
        self.assertIn('COENABLED_NATIVE_DIFF', (ROOT/'tests/coenabled/run.py').read_text())
        self.assertIn('nativePartsE()', (ROOT/'tests/coenabled/runtime.php').read_text())

    def test_real_install_build_and_compatibility_inputs(self):
        for name in ('compose.yaml','.dockerignore','upstream.lock.json','scripts/p2_install.sh','scripts/prepare.py',
                     'scripts/check.py','scripts/max_source_audit.py','tests/p2_business.php',
                     'tests/p2_config.php','deployment/local/Dockerfile','deployment/local/php.ini',
                     'deployment/local/gateway.conf','.github/workflows/max-coenabled.yml'):
            with self.subTest(path=name): self.assertTrue(required([name]))
        self.assertTrue(required(['tests/mailintake/compose.yaml']))

    def test_unrelated_docs_and_other_lanes_do_not_select_native(self):
        paths=['README.md','AGENTS.md','docs/PRODUCT_SCOPE.md','docs/COMPATIBILITY.md',
               'docs/COENABLED_ACCEPTANCE.md','docs/specs/CUSTOMS.md','tests/test_dev_doctor.py',
               'scripts/dev_doctor.py','tests/mail/diagnostic.py','.github/workflows/unrelated.yml']
        for path in paths:
            with self.subTest(path=path): self.assertFalse(required([path]))
        self.assertFalse(required(paths))
        self.assertFalse(required([]))
        self.assertTrue(required(paths+['modules/maxmail/api.php']))

    def test_segment_boundaries_and_manual_override(self):
        self.assertFalse(required(['modules/maxcore-other/a.php','archive/modules/maxcore/a.php',
                                   'scripts/coenabled_other/nested.txt','tests/p2_nested/file.php']))
        self.assertTrue(required([],manual=True))
        self.assertTrue(all('!' not in p and '?' not in p for p in PATHS))

    def test_workflow_keeps_exact_head_and_single_native_job(self):
        text=(ROOT/'.github/workflows/max-coenabled.yml').read_text()
        events=text.split('on:\n',1)[1].split('permissions:',1)[0]
        self.assertIn('types: [opened, synchronize, reopened]',events)
        self.assertIn('  workflow_dispatch:',events)
        for event in ('push:','schedule:','pull_request_target:','paths:'):
            self.assertNotIn(event,events)  # Full Git diff, no 300-file native filter.
        self.assertIn('cancel-in-progress: false',text)
        group=text.split('  group:',1)[1].splitlines()[0]
        self.assertIn('head.repo.full_name || github.repository',group)
        self.assertIn('head.ref || github.ref_name',group)
        self.assertNotIn('run_id',group)
        self.assertIn('needs: select-regression',text)
        self.assertIn("if: needs.select-regression.outputs.required == 'true'",text)
        self.assertEqual(text.count('  single-populated-installation:'),1)
        self.assertEqual(text.count('runs-on: ubuntu-latest'),2)
        self.assertNotIn('matrix:',text)
        self.assertNotIn('continue-on-error',text)
        self.assertIn('timeout-minutes: 40',text)
        self.assertIn('ref: ${{ github.event.pull_request.head.sha || github.sha }}',text)
        self.assertIn('COENABLED_BASE_SHA: ${{ needs.select-regression.outputs.base }}',text)
        for command in ('bash scripts/coenabled_test.sh','if-no-files-found: error','if: always()'):
            self.assertIn(command,text)
        light=text.split('  select-regression:',1)[1].split('  single-populated-installation:',1)[0]
        self.assertIn('-p test_ci_policy.py',light)
        self.assertNotIn('docker ',light)
        self.assertNotIn('pip install',light)


class RealGitProvenance(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory(); self.root=Path(self.temp.name)
        self.git('init','-b','main');self.git('config','user.name','Synthetic CI Test')
        self.git('config','user.email','ci@example.invalid')
        self.write('README.md','base\n'); self.base=self.save('base')

    def tearDown(self): self.temp.cleanup()

    def git(self,*args):
        return subprocess.check_output(['git',*args],cwd=self.root,stderr=subprocess.DEVNULL,text=True).strip()

    def write(self,name,body):
        p=self.root/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(body)

    def save(self,label):
        self.git('add','-A');self.git('commit','-m',label);return self.git('rev-parse','HEAD')

    def test_legitimate_module_edit_is_provenance_not_original_pr_allowlist(self):
        self.write('modules/maxcore/class/fixture.php','synthetic v1'); self.base=self.save('fixture')
        self.write('modules/maxcore/class/fixture.php','synthetic v2');head=self.save('change')
        state=provenance(self.root,self.base,head)
        self.assertEqual(state['sha'],head);self.assertTrue(required(state['changed_files']))
        self.assertEqual(state['changed_files'],['modules/maxcore/class/fixture.php'])

    def test_large_pr_and_filename_metacharacters_are_not_truncated(self):
        for n in range(350): self.write(f'docs/doc{n:03d}.md','prose')
        self.write('modules/maxmail/nested/a b\n$(inert).php','synthetic')
        head=self.save('large')
        state=provenance(self.root,self.base,head)
        self.assertEqual(len(state['changed_files']),351)
        self.assertTrue(required(state['changed_files']))
        self.assertIn('modules/maxmail/nested/a b\n$(inert).php',state['changed_files'])
        self.assertFalse((self.root/'inert').exists())

    def test_rename_out_of_watched_module_and_deletion_are_relevant(self):
        self.write('modules/maxorders/fixture.php','synthetic');self.base=self.save('fixture')
        self.git('mv','modules/maxorders/fixture.php','moved.txt');head=self.save('rename')
        state=provenance(self.root,self.base,head)
        self.assertIn('modules/maxorders/fixture.php',state['changed_files'])
        self.assertIn('moved.txt',state['changed_files']);self.assertTrue(required(state['changed_files']))

    def test_advanced_target_branch_uses_merge_base(self):
        self.git('checkout','-b','feature');self.write('modules/maxcustoms/fixture.php','synthetic');head=self.save('feature')
        self.git('checkout','main');self.write('CHANGELOG.md','new main');target=self.save('target')
        self.git('checkout','feature');state=provenance(self.root,target,head)
        self.assertEqual(state['base'],target);self.assertEqual(state['comparison_base'],self.base)
        self.assertEqual(state['changed_files'],['modules/maxcustoms/fixture.php'])

    def test_docs_only_pr_and_manual_same_head(self):
        self.write('docs/guide.md','prose');head=self.save('docs')
        event={'pull_request':{'base':{'sha':self.base},'head':{'sha':head}}}
        state=select(self.root,'pull_request',event,'ignored merge ref')
        self.assertFalse(state['required']);self.assertEqual(state['native_acceptance'],'NOT RUN')
        manual=select(self.root,'workflow_dispatch',{},head)
        self.assertTrue(manual['required']);self.assertEqual(manual['changed_files'],[])
        self.assertEqual(manual['native_acceptance'],'NOT RUN')

    def test_missing_history_wrong_head_dirty_tree_and_invalid_event_fail(self):
        for base in ('not-a-sha','0'*40):
            with self.assertRaises(ValueError): provenance(self.root,base)
        self.write('docs/change.md','committed');head=self.save('second')
        with self.assertRaises(ValueError): provenance(self.root,self.base,self.base)
        with self.assertRaises(ValueError): select(self.root,'push',{},head)
        self.write('README.md','dirty')
        with self.assertRaises(ValueError): provenance(self.root,self.base,head)

    def test_cli_control_outputs_and_fail_closed_without_skip_output(self):
        # Execute the real entry point in a real repository; never invoke Actions.
        self.write('tests/coenabled/regression_policy.py',(ROOT/'tests/coenabled/regression_policy.py').read_text())
        head=self.save('selector')
        event=self.root/'.git/event.json';output=self.root/'.git/output';summary=self.root/'.git/summary'
        env=dict(os.environ,GITHUB_EVENT_NAME='pull_request',GITHUB_EVENT_PATH=str(event),
                 GITHUB_SHA=head,GITHUB_OUTPUT=str(output),GITHUB_STEP_SUMMARY=str(summary))
        event.write_text(json.dumps({'pull_request':{'base':{'sha':self.base},'head':{'sha':head}}}))
        script=self.root/'tests/coenabled/regression_policy.py'
        result=subprocess.run([sys.executable,str(script)],env=env,capture_output=True,text=True)
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(output.read_text(),'required=true\nbase='+self.base+'\ncomparison_base='+self.base+'\n')
        self.assertIn('Native acceptance: NOT RUN',summary.read_text())
        event.write_text('{}');output.unlink();summary.unlink()
        result=subprocess.run([sys.executable,str(script)],env=env,capture_output=True,text=True)
        self.assertNotEqual(result.returncode,0)
        self.assertIn('FAIL regression selection',result.stderr)
        self.assertFalse(output.exists());self.assertFalse(summary.exists())


class AcceptancePreservation(unittest.TestCase):
    def rows(self,lane):
        rows=[]
        for width in WIDTHS:
            for screen in SURFACES[lane]:
                rows.append(dict(width=width,viewport=width,screen=screen,surface=screen,phase='after',
                                 body=width,document=width,doc=width,
                                 body_scroll_width=width,document_scroll_width=width))
        return rows

    def test_original_all_five_cartesian_sets_total_exactly_63(self):
        expected={'native':15,'core':9,'orders':9,'customs':18,'mail':12}
        self.assertEqual(set(SURFACES),set(expected));self.assertEqual(WIDTHS,(360,390,768))
        counts={lane:len(strict(self.rows(lane),lane)) for lane in SURFACES}
        self.assertEqual(counts,expected);self.assertEqual(sum(counts.values()),63)

    def test_each_surface_rejects_missing_duplicate_and_one_pixel_overflow(self):
        for lane in SURFACES:
            rows=self.rows(lane)
            bad_geometry=[dict(r) for r in rows]
            for field in ('body','document','doc','body_scroll_width','document_scroll_width'):
                bad_geometry[0][field]+=1
            for invalid in (rows[:-1],rows[:-1]+[rows[0]],bad_geometry):
                with self.subTest(lane=lane),self.assertRaises(AssertionError): strict(invalid,lane)

    def test_complete_manifest_does_not_hide_test_or_cleanup_failure(self):
        for test_exit,cleanup_exit in ((0,0),(1,0),(0,1),(1,1)):
            with self.subTest(test=test_exit,cleanup=cleanup_exit),tempfile.TemporaryDirectory() as directory:
                evidence=Path(directory)/'.local/coenabled-evidence';evidence.mkdir(parents=True)
                # Only cleanup's status contract is tested, never native PASS.
                (evidence/'acceptance.json').write_text('{}')
                run=subprocess.run([sys.executable,str(ROOT/'tests/coenabled/cleanup.py'),
                                    str(test_exit),str(cleanup_exit)],cwd=directory,capture_output=True)
                passed=test_exit==0 and cleanup_exit==0
                self.assertEqual(run.returncode,0 if passed else 1)
                report=json.loads((evidence/'cleanup.json').read_text())
                self.assertEqual(report['passed'],passed)
                self.assertEqual(report['owned_resources_removed'],cleanup_exit==0)
