# Actual local Docker negative: refusal must leave the pre-existing probe intact.
import os
from pathlib import Path
import subprocess

root=Path(__file__).resolve().parents[2]
env=dict(os.environ)
env['DOCKER_HOST']='unix:///var/run/docker.sock'
for key in ('DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH'):
    env.pop(key,None)
project=env['COMPOSE_PROJECT_NAME']+'-preflight'
env['COMPOSE_PROJECT_NAME']=project
network=subprocess.check_output(['docker','network','create','--internal','--label',
    'com.docker.compose.project='+project,project+'-probe'],env=env,text=True).strip()
try:
    result=subprocess.run(['bash','scripts/branding_test.sh'],cwd=root,env=env,capture_output=True,text=True,timeout=30)
    assert result.returncode!=0 and 'Refusing existing project resources' in result.stderr, 'Unsafe pre-existing-project handling'
    subprocess.run(['docker','network','inspect',network],env=env,check=True,stdout=subprocess.DEVNULL)
    print('PASS existing Docker project refused before installation or cleanup')
finally:
    subprocess.run(['docker','network','rm',network],env=env,check=True,stdout=subprocess.DEVNULL)
