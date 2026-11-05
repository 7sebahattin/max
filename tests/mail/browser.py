import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080'
subprocess.run(['docker','compose','cp','web:/var/lib/dolibarr/documents/mail-accounts.json','.local/mail-accounts.json'],check=True)
reader=json.loads(Path('.local/mail-accounts.json').read_text())
owner=dict(login='maxadmin',password=Path('.local/p2-admin-secret').read_text().strip())
with sync_playwright() as p:
    browser=p.chromium.launch()
    for role, account in [('reader',reader),('owner',owner)]:
        context=browser.new_context()
        context.route('**/*',lambda r:r.continue_() if r.request.url.startswith(BASE+'/') else r.abort())
        page=context.new_page(); page.goto(BASE+'/')
        page.locator('[name=username]').fill(account['login']); page.locator('[name=password]').fill(account['password'])
        page.locator('[type=submit]').first.click(); page.wait_for_load_state('networkidle')
        assert not page.locator('[name=password]').count(), 'Native login failed'
        for path in ['emailcollector_list.php','emailcollector_card.php?action=create']:
            response=page.goto(BASE+'/admin/'+path)
            body=response.text()
            assert 'Fatal error' not in body
            if role=='reader':
                assert response.status in [200,403]  # Native accessforbidden commonly returns 200.
                assert 'Access denied' in body or 'Accès refusé' in body
                assert 'Fixture a' not in body and 'name="host"' not in body and 'name="password"' not in body
            else:
                assert response.status==200 and 'Access denied' not in body
                assert 'Fixture a' in body if 'list' in path else 'name="host"' in body
        context.close()
    browser.close()
print('PASS native admin-only collector list/card HTTP and protected content')
