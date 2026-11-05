"""Native installation-wizard telemetry opt-out, never auth/session manipulation."""
import json
from pathlib import Path
import re

def opt_out(context):
    cookies=json.loads(Path('.local/coenabled-privacy.json').read_text())
    assert len(cookies)==1
    cookie=cookies[0]
    assert re.fullmatch(r'DOLINSTALLNOPING_[a-f0-9]{64}',cookie['name'])
    assert cookie['value']=='1' and cookie['domain']=='127.0.0.1' and cookie['path']=='/'
    context.add_cookies(cookies)
