"""Cleanup outcome is independent of the test failure and missing evidence."""
import json
from pathlib import Path
import sys
p=Path('.local/coenabled-evidence');p.mkdir(parents=True,exist_ok=True)
test_status=int(sys.argv[1]);cleanup_status=int(sys.argv[2]);complete=(p/'acceptance.json').is_file()
passed=test_status==0 and cleanup_status==0 and complete
(p/'cleanup.json').write_text(json.dumps({'passed':passed,'test_exit':test_status,'cleanup_exit':cleanup_status,'owned_resources_removed':cleanup_status==0,'acceptance_evidence_present':complete}))
if not complete:
    counts={}
    for lane,expected in {'native':15,'core':9,'orders':9,'customs':18,'mail':12}.items():
        file=p/('native-layout.json' if lane=='native' else lane+'-layout-raw.json')
        rows=json.loads(file.read_text()) if file.is_file() else []
        if lane=='native':rows=[r for r in rows if r.get('phase')=='after']
        counts[lane]={'required':expected,'observed':len(rows),'status':'NOT_RUN' if not rows else 'INCOMPLETE_OR_FAILED'}
    (p/'incomplete.json').write_text(json.dumps({'passed':False,'counts':counts,'reason':'No complete acceptance manifest; inspect stage and observer assertions'},indent=2))
if not passed:raise SystemExit(1)
print('PASS single owned project cleanup and complete acceptance evidence')
