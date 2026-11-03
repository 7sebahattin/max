"""Real Docker ownership refusal, before any setup or teardown."""
import os
import secrets
import subprocess
import unittest
from pathlib import Path

class ProjectOwnership(unittest.TestCase):
    def test_existing_network_survives_refused_runner(self):
        project='max-mailintake-probe-'+secrets.token_hex(6)
        env=dict(os.environ,DOCKER_HOST='unix:///var/run/docker.sock',COMPOSE_PROJECT_NAME=project)
        for key in ('DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH'): env.pop(key,None)
        marker=Path('.local/mailintake-owner'); before=marker.read_bytes() if marker.exists() else None
        network=subprocess.check_output(['docker','network','create','--internal','--label','com.docker.compose.project='+project,project],env=env,text=True).strip()
        try:
            result=subprocess.run(['bash','scripts/mailintake_test.sh'],env=env,capture_output=True,text=True)
            self.assertEqual(result.returncode,2)
            self.assertIn('Refusing pre-existing intake project',result.stderr)
            self.assertEqual(subprocess.run(['docker','network','inspect',network],env=env,capture_output=True).returncode,0)
            self.assertEqual(marker.read_bytes() if marker.exists() else None,before)
        finally: subprocess.run(['docker','network','rm',network],env=env,check=True,capture_output=True)
