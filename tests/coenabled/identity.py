"""Safe Docker identity projection only; never dump inspect/config environments."""
import json
import os
from pathlib import Path
import subprocess
import sys
OUT=Path('.local/coenabled-evidence');OUT.mkdir(exist_ok=True)
phase=sys.argv[1]; sha=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
project=os.environ['COMPOSE_PROJECT_NAME']
if phase=='before':
    (OUT/'source.json').write_text(json.dumps({'sha':sha,'base':'a3344b18be64e99c8e08df83d5888699f4e3d89d','project':project,'docker_version':subprocess.check_output(['docker','version','--format','{{.Server.Version}}'],text=True).strip(),'compose_version':subprocess.check_output(['docker','compose','version','--short'],text=True).strip()},indent=2))
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
