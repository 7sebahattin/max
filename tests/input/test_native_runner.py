"""Teardown must never delete a foreign tree/configuration or a live runtime."""
import importlib.util
import json
from pathlib import Path
import socket
import stat
import tempfile
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('native_local',Path(__file__).resolve().parents[2]/'scripts/input_native_local.py')
runner=importlib.util.module_from_spec(spec);spec.loader.exec_module(runner)


class OwnedCleanup(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory()
        self.root=Path(self.temp.name).resolve()
        (self.root/'modules').mkdir()
        self.run=self.root/'.local/input-native-synthetic';self.run.mkdir(parents=True)
        self.native=self.root/'.local/upstream/htdocs';(self.native/'conf').mkdir(parents=True)
        self.marker=self.run/'owner.json'
        self.config=self.native/'conf/conf.php'
        self.marker.write_text(json.dumps(dict(root=str(self.native),database='max_input_synthetic',port=1)))
        self.config.write_text("<?php\n$dolibarr_main_db_name = 'max_input_synthetic';\n")

    def tearDown(self): self.temp.cleanup()

    def test_parent_and_sibling_removals_are_rejected(self):
        foreign=self.root/'foreign';foreign.mkdir();sentinel=foreign/'keep';sentinel.write_text('keep')
        with self.assertRaises(RuntimeError):runner.remove_owned(self.run,self.run)
        with self.assertRaises(ValueError):runner.remove_owned(foreign,self.run)
        self.assertTrue(sentinel.exists())

    def test_readonly_owned_installer_lock_is_removed(self):
        docs=self.run/'documents';docs.mkdir();lock=docs/'install.lock';lock.write_text('synthetic');lock.chmod(stat.S_IREAD)
        runner.remove_owned(docs,self.run)
        self.assertFalse(docs.exists())

    def test_foreign_config_stops_recovery_before_deletion(self):
        self.config.write_text("<?php\n$dolibarr_main_db_name = 'foreign';\n")
        with patch.object(runner,'ROOT',self.root),patch.object(runner.socket,'create_connection',side_effect=ConnectionRefusedError):
            with self.assertRaisesRegex(RuntimeError,'foreign native configuration'):runner.recover(self.run,self.native)
        self.assertTrue(self.marker.exists());self.assertIn('foreign',self.config.read_text())

    def test_live_database_port_stops_recovery(self):
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0));sock.listen()
            self.marker.write_text(json.dumps(dict(root=str(self.native),database='max_input_synthetic',port=sock.getsockname()[1])))
            with patch.object(runner,'ROOT',self.root):
                with self.assertRaisesRegex(RuntimeError,'database port is live'):runner.recover(self.run,self.native)
        self.assertTrue(self.config.exists());self.assertTrue(self.marker.exists())

    def test_stopped_owned_recovery_retains_evidence_and_other_files(self):
        for name in ('database','documents','sessions'):
            child=self.run/name;child.mkdir();(child/'fixture').write_text('synthetic')
        (self.run/'client.ini').write_text('synthetic');(self.run/'accounts.json').write_text('{}')
        evidence=self.run/'before.json';evidence.write_text('{"sha256":"synthetic"}')
        with patch.object(runner,'ROOT',self.root),patch.object(runner.socket,'create_connection',side_effect=ConnectionRefusedError):
            runner.recover(self.run,self.native)
        self.assertTrue(evidence.exists());self.assertFalse(self.config.exists());self.assertFalse(self.marker.exists())
        self.assertFalse((self.run/'database').exists());self.assertFalse((self.run/'client.ini').exists())

    def test_native_zero_exit_without_complete_assertions_cannot_pass(self):
        with self.assertRaises(FileNotFoundError):runner.verify_stage_report(self.run,'native_http')
        evidence=self.run/'http-checks.json'
        evidence.write_text('[{"check":"native browser login","status":"PASS"}]')
        with self.assertRaises(RuntimeError):runner.verify_stage_report(self.run,'native_http')
        evidence.write_text('[{"check":"native browser no external requests","status":"FAIL"}]')
        with self.assertRaises(RuntimeError):runner.verify_stage_report(self.run,'native_http')
        evidence.write_text('[{"check":"native browser no external requests","status":"PASS"}]')
        runner.verify_stage_report(self.run,'native_http')

    def test_wizard_requires_separate_complete_evidence(self):
        (self.run/'http-checks.json').write_text('[{"check":"native browser no external requests","status":"PASS"}]')
        with self.assertRaises(FileNotFoundError):runner.verify_stage_report(self.run,'native_wizard_http')
        (self.run/'wizard-checks.json').write_text('[{"check":"wizard native login admin","status":"PASS"}]')
        with self.assertRaises(RuntimeError):runner.verify_stage_report(self.run,'native_wizard_http')


if __name__=='__main__':unittest.main()
