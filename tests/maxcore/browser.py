import json, subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080'
accounts=json.loads(Path('.local/maxcore-accounts.json').read_text())
accounts['owner']=dict(login='maxcore-test',password=Path('.local/maxcore-admin').read_text())
secrets=[a['password'] for a in accounts.values()]
def run():
    measurements=[]
    with sync_playwright() as p:
        browser=p.chromium.launch()
        for role, account in accounts.items():
            context=browser.new_context()
            context.route('**/*',lambda r:r.continue_() if r.request.url.startswith(BASE+'/') else r.abort())
            page=context.new_page(); page.goto(BASE+'/')
            page.locator('input[name=username]').fill(account['login'])
            page.locator('input[name=password]').fill(account['password'])
            page.locator('input[type=submit],button[type=submit]').first.click()
            page.wait_for_load_state('networkidle')
            assert page.locator('input[name=password]').count()==0,'Native login failed'
            for path,allowed in [('index.php',role!='denied'),('admin/setup.php',role in ['editor','manager','owner']),('admin/status.php',role in ['manager','owner'])]:
                response=page.goto(BASE+'/custom/maxcore/'+path+'?mainmenu=maxcore')
                assert 'Fatal error' not in page.locator('body').inner_text()
                assert response.status==(200 if allowed else 403),f'Role endpoint: {role}/{path}'
            if role=='reader':
                page.goto(BASE+'/custom/maxcore/index.php')
                token=page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')
                response=context.request.post(BASE+'/custom/maxcore/admin/setup.php',form={'token':token,'MAXCORE_DISPLAY_NAME':'Forbidden'})
                assert response.status==403,'Reader POST denied server-side'
            if role in ['editor','owner']:
                page.goto(BASE+'/custom/maxcore/admin/setup.php')
                reply=context.request.post(BASE+'/custom/maxcore/admin/setup.php',form={'token':'invalid','MAXCORE_DISPLAY_NAME':'Forbidden'})
                assert 'refused' in reply.text().lower() or reply.status==403,'Native CSRF missing'
                page.goto(BASE+'/custom/maxcore/admin/setup.php')
                page.locator('[name=MAXCORE_DISPLAY_NAME]').fill('MAX Demo '+role)
                page.locator('button[type=submit]').click(); page.wait_for_load_state('networkidle')
                assert page.locator('[name=MAXCORE_DISPLAY_NAME]').input_value()=='MAX Demo '+role,'Brand save failed'
            if role=='owner':
                for width in [360,390,768]:
                    page.set_viewport_size(dict(width=width,height=900))
                    page.goto(BASE+'/custom/maxcore/index.php?lang=tr_TR&mainmenu=maxcore')
                    assert 'MAX Demo owner' in page.locator('h1').inner_text()
                    assert 'İsteğe bağlı' in page.locator('body').inner_text()
                    assert page.locator('a[href*="/maxcore/index.php"]').count()>=1,'MAX menu absent'
                    page.screenshot(path=f'.local/maxcore-{width}.png',full_page=True)
                    measurements.append(dict(width=width,scroll_width=page.evaluate('document.body.scrollWidth')))
                subprocess.run(['docker','compose','exec','-T','web','php','/checks/maxcore/toggle.php','off'],check=True)
                assert page.goto(BASE+'/custom/maxcore/index.php').status==403,'Disabled endpoint still accessible'
                subprocess.run(['docker','compose','exec','-T','web','php','/checks/maxcore/toggle.php','on'],check=True)
                assert page.goto(BASE+'/custom/maxcore/index.php').status==200
            context.close()
        browser.close()
    Path('.local/maxcore-mobile.json').write_text(json.dumps(measurements,indent=2))
    print('PASS native login/menu, direct role GET/POST denial, CSRF, HTTP branding, Turkish mobile and disabled endpoint')
try:
    run()
except Exception as error:
    message=str(error)
    for secret in secrets: message=message.replace(secret,'[redacted]')
    raise SystemExit(message) from None
