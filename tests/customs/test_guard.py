"""Real owned-resource refusal, not a mocked cleanup claim."""
import os
import secrets
import subprocess
import unittest
from pathlib import Path


class CustomsOwnership(unittest.TestCase):
    def test_existing_network_survives_refusal(self):
        project='max-customs-guard-'+secrets.token_hex(5)
        env={**os.environ,'DOCKER_HOST':'unix:///var/run/docker.sock','COMPOSE_PROJECT_NAME':project,'DOCKER_CONTEXT':'do-not-use-this-context'}
        clean={k:v for k,v in env.items() if k not in ['DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH']}
        probe=subprocess.check_output(['docker','network','create','--internal','--label','com.docker.compose.project='+project,project],env=clean,text=True).strip()
        marker=Path('.local/customs-owner')
        before=marker.read_bytes() if marker.exists() else None
        try:
            result=subprocess.run(['bash','scripts/customs_test.sh'],env=env,capture_output=True,text=True)
            self.assertEqual(result.returncode,2)
            self.assertIn('Refusing pre-existing customs project',result.stderr)
            subprocess.run(['docker','network','inspect',probe],env=clean,stdout=subprocess.DEVNULL,check=True)
            self.assertEqual(marker.read_bytes() if marker.exists() else None,before)
        finally:
            subprocess.run(['docker','network','rm',probe],env=clean,stdout=subprocess.DEVNULL,check=True)
