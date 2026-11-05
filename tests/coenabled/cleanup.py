"""Cleanup outcome is independent of the test failure and missing evidence."""
import json
from pathlib import Path
import sys
p=Path('.local/coenabled-evidence');p.mkdir(parents=True,exist_ok=True)
test_status=int(sys.argv[1]);cleanup_status=int(sys.argv[2]);complete=(p/'acceptance.json').is_file()
passed=test_status==0 and cleanup_status==0 and complete
(p/'cleanup.json').write_text(json.dumps({'passed':passed,'test_exit':test_status,'cleanup_exit':cleanup_status,'owned_resources_removed':cleanup_status==0,'acceptance_evidence_present':complete}))
if not passed:raise SystemExit(1)
print('PASS single owned project cleanup and complete acceptance evidence')
