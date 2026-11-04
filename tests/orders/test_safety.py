"""Real Docker negative test: a rejected existing project must survive."""
import os
import secrets
import subprocess
import unittest


class Safety(unittest.TestCase):
    def test_existing_project_is_not_deleted(self):
        project='max-orders-preflight-'+secrets.token_hex(6)
        env=dict(os.environ,DOCKER_HOST='unix:///var/run/docker.sock',COMPOSE_PROJECT_NAME=project)
        for key in ['DOCKER_CONTEXT','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH']:
            env.pop(key,None)
        network=subprocess.check_output(['docker','network','create','--internal','--label',f'com.docker.compose.project={project}',project],env=env,text=True).strip()
        try:
            result=subprocess.run(['bash','scripts/orders_test.sh'],env=env,text=True,capture_output=True)
            self.assertNotEqual(result.returncode,0)
            self.assertIn('Refusing pre-existing Compose project',result.stderr)
            subprocess.run(['docker','network','inspect',network],env=env,stdout=subprocess.DEVNULL,check=True)
        finally:
            subprocess.run(['docker','network','rm',network],env=env,stdout=subprocess.DEVNULL,check=True)
