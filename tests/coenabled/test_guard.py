import os
from pathlib import Path
import secrets
import subprocess
import unittest
class Ownership(unittest.TestCase):
    def test_real_docker_existing_network_survives(self):
        project='max-coenabled-probe-'+secrets.token_hex(6);env=dict(os.environ,COMPOSE_PROJECT_NAME=project,DOCKER_HOST='unix:///var/run/docker.sock')
        for k in ('DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH'):env.pop(k,None)
        marker=Path('.local/coenabled-owner');before=marker.read_bytes() if marker.exists() else None
        network=subprocess.check_output(['docker','network','create','--internal','--label','com.docker.compose.project='+project,project],env=env,text=True).strip()
        try:
            r=subprocess.run(['bash','scripts/coenabled_test.sh'],env=env,capture_output=True,text=True)
            self.assertEqual(r.returncode,2);self.assertIn('Refusing pre-existing coenabled resources',r.stderr)
            self.assertEqual(subprocess.run(['docker','network','inspect',network],env=env,capture_output=True).returncode,0)
            self.assertEqual(marker.read_bytes() if marker.exists() else None,before)
        finally:subprocess.run(['docker','network','rm',network],env=env,check=True,capture_output=True)
