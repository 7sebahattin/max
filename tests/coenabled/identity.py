"""Safe Docker identity projection only; never dump inspect/config environments."""
import json
import os
from pathlib import Path
import subprocess
import sys
OUT=Path('.local/coenabled-evidence');OUT.mkdir(exist_ok=True)
phase=sys.argv[1]; sha=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
project=os.environ['COMPOSE_PROJECT_NAME']
base='a3344b18be64e99c8e08df83d5888699f4e3d89d'
subprocess.run(['git','merge-base','--is-ancestor',base,'HEAD'],check=True)
changes=subprocess.check_output(['git','diff','--name-status','--no-renames','-z',base,'HEAD']).decode().split('\0')
owned=[]
for index in range(0,len(changes)-1,2):
    status,name=changes[index:index+2]
    assert status=='A' and (name.startswith('tests/coenabled/') or name.startswith('scripts/coenabled_') or name in ('docs/COENABLED_ACCEPTANCE.md','.github/workflows/max-coenabled.yml')),'Only approved new test files allowed'
    owned.append(name)
subprocess.run(['git','diff','--quiet','HEAD'],check=True)
if phase=='before':
    (OUT/'source.json').write_text(json.dumps({'sha':sha,'base':base,'owned_files':owned,'project':project,'docker_version':subprocess.check_output(['docker','version','--format','{{.Server.Version}}'],text=True).strip(),'compose_version':subprocess.check_output(['docker','compose','version','--short'],text=True).strip()},indent=2))
else:
    containers={}
    for service in ('db','web','gateway','fakeimap'):
        cid=subprocess.check_output(['docker','compose','ps','-q',service],text=True).strip(); assert cid,'Required service missing'
        raw=json.loads(subprocess.check_output(['docker','inspect',cid],text=True))[0]
        assert raw['Config']['Labels']['com.docker.compose.project']==project
        assert raw['State']['Running'] and not raw['HostConfig'].get('Privileged')
        containers[service]={'container_id':cid,'image_id':raw['Image'],'project':project,'networks':sorted(raw['NetworkSettings']['Networks'])}
    file=OUT/'docker.json'
    if phase=='final': assert json.loads(file.read_text())==containers,'Containers replaced during shared DB acceptance'
    else: file.write_text(json.dumps(containers,indent=2))
    assert json.loads((OUT/'source.json').read_text())['sha']==sha,'Source SHA changed during tests'
print('PASS single project identity '+phase)
