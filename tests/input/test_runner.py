"""Evidence must not retain a previous PASS after a failed local command."""
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

spec=importlib.util.spec_from_file_location('input_runner',Path(__file__).resolve().parents[2]/'scripts/input_test_local.py')
runner=importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)


class LocalEvidence(unittest.TestCase):
    def exercise_failure(self,error):
        with tempfile.TemporaryDirectory() as directory:
            out=Path(directory)
            (out/'input-local-summary.json').write_text('{"checks":{"one":"PASS","two":"PASS"}}')
            with patch.object(runner.subprocess,'run',side_effect=error) as process:
                self.assertEqual(runner.run_checks([('one',['local']),('two',['local'])],out,{'source_sha':'synthetic'}),1)
            self.assertEqual(process.call_count,1)
            self.assertEqual(json.loads((out/'input-local-summary.json').read_text())['checks'],{'one':'FAIL','two':'NOT RUN'})

    def test_timeout_invalidates_stale_pass_and_stops_later_checks(self):
        self.exercise_failure(subprocess.TimeoutExpired(['local'],120))

    def test_missing_executable_invalidates_stale_pass_and_stops_later_checks(self):
        self.exercise_failure(FileNotFoundError())

    def test_nonzero_exit_is_failure_and_success_can_complete(self):
        with tempfile.TemporaryDirectory() as directory:
            out=Path(directory)
            for codes,expected,exit_code in [([0,1],{'one':'PASS','two':'FAIL'},1),([0,0],{'one':'PASS','two':'PASS'},0)]:
                with patch.object(runner.subprocess,'run',side_effect=[subprocess.CompletedProcess(['local'],code) for code in codes]):
                    self.assertEqual(runner.run_checks([('one',['local']),('two',['local'])],out,{}),exit_code)
                self.assertEqual(json.loads((out/'input-local-summary.json').read_text())['checks'],expected)


if __name__=='__main__':unittest.main()
