import json, subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
from browser_assertions import assert_denied, assert_allowed, assert_no_overflow

BASE='http://127.0.0.1:8080'
accounts=json.loads(Path('.local/maxcore-accounts.json').read_text())
accounts['owner']=dict(login='maxcore-test',password=Path('.local/maxcore-admin').read_text())
secrets=[a['password'] for a in accounts.values()]
measurements=[]

def measure(page, width, screen):
    page.evaluate('document.fonts.ready')
    metrics=page.evaluate("""() => ({
        viewport: window.innerWidth,
        body_scroll_width: document.body.scrollWidth,
        document_scroll_width: document.documentElement.scrollWidth,
        body_client_width: document.body.clientWidth,
        document_client_width: document.documentElement.clientWidth,
        module_width: document.querySelector('.maxcore-shell').getBoundingClientRect().width
    })""")
    metrics.update(width=width, screen=screen)
    measurements.append(metrics)
    # Persist diagnostics BEFORE asserting so failed native/module layouts stay visible.
    Path('.local/maxcore-mobile.json').write_text(json.dumps(measurements,indent=2))
    suffix='' if screen=='home' else '-'+screen
    page.screenshot(path=f'.local/maxcore{suffix}-{width}.png',full_page=True)
    print('MAXCORE_LAYOUT '+json.dumps(metrics,sort_keys=True))
    assert_no_overflow(metrics)

def run():
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
            for path,kind,allowed in [('index.php','home',role!='denied'),('admin/setup.php','settings',role in ['editor','manager','owner']),('admin/status.php','administration',role in ['manager','owner'])]:
                response=page.goto(BASE+'/custom/maxcore/'+path+'?mainmenu=maxcore')
                assert 'Fatal error' not in page.locator('body').inner_text()
                if allowed:
                    assert_allowed(response.status,response.text(),kind)
                else:
                    assert_denied(response.status,response.text(),['Synthetic Display'])
                    print('PASS denied GET body '+role+'/'+kind)
            if role in ['denied','reader','editor']:
                # Obtain a real session token even for the no-MAX-rights native user.
                targets=['admin/status.php']
                if role in ['denied','reader']:
                    targets.append('admin/setup.php')
                for target in targets:
                    page.goto(BASE+'/')
                    token=page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')
                    response=context.request.post(BASE+'/custom/maxcore/'+target,form={'token':token,'MAXCORE_DISPLAY_NAME':'Forbidden'})
                    assert_denied(response.status,response.text(),['Synthetic Display'])
                    print('PASS denied POST body '+role+'/'+target)
            if role in ['editor','owner']:
                page.goto(BASE+'/custom/maxcore/admin/setup.php')
                before=page.locator('[name=MAXCORE_DISPLAY_NAME]').input_value()
                reply=context.request.post(BASE+'/custom/maxcore/admin/setup.php',form={'token':'invalid','MAXCORE_DISPLAY_NAME':'Forbidden'})
                assert_denied(reply.status,reply.text(),[before])
                page.goto(BASE+'/custom/maxcore/admin/setup.php')
                assert page.locator('[name=MAXCORE_DISPLAY_NAME]').input_value()==before,'Invalid CSRF mutated brand'
                page.locator('[name=MAXCORE_DISPLAY_NAME]').fill('MAX Demo '+role)
                page.locator('button[type=submit]').click(); page.wait_for_load_state('networkidle')
                assert page.locator('[name=MAXCORE_DISPLAY_NAME]').input_value()=='MAX Demo '+role,'Brand save failed'
            if role=='owner':
                current_brand='MAX Demo owner'
                for width in [360,390,768]:
                    page.set_viewport_size(dict(width=width,height=900))
                    response=page.goto(BASE+'/custom/maxcore/index.php?lang=tr_TR&mainmenu=maxcore')
                    assert_allowed(response.status,response.text(),'home')
                    assert current_brand in page.locator('h1').inner_text()
                    assert 'İsteğe bağlı' in page.locator('body').inner_text()
                    assert page.locator('a[href*="/maxcore/index.php"]').count()>=1,'MAX menu absent'
                    measure(page,width,'home')
                    response=page.goto(BASE+'/custom/maxcore/admin/setup.php?lang=tr_TR&mainmenu=maxcore')
                    assert_allowed(response.status,response.text(),'settings')
                    measure(page,width,'settings')
                    field=page.locator('[name=MAXCORE_DISPLAY_NAME]')
                    field.focus(); field.press('ControlOrMeta+A'); field.press('Backspace')
                    current_brand=f'MAX Mobile {width}'
                    field.press_sequentially(current_brand)
                    field.press('Tab')
                    assert page.evaluate('document.activeElement.id')=='MAXCORE_LOGO','Form keyboard order'
                    page.locator('button[type=submit]').focus()
                    page.locator('button[type=submit]').press('Enter')
                    page.wait_for_load_state('networkidle')
                    assert page.locator('[name=MAXCORE_DISPLAY_NAME]').input_value()==current_brand,'Mobile keyboard save failed'
                    print('PASS mobile keyboard brand save '+str(width))
                    response=page.goto(BASE+'/custom/maxcore/admin/status.php?lang=tr_TR&mainmenu=maxcore')
                    assert_allowed(response.status,response.text(),'administration')
                    measure(page,width,'administration')
                subprocess.run(['docker','compose','exec','-T','web','php','/checks/maxcore/toggle.php','off'],check=True)
                response=page.goto(BASE+'/custom/maxcore/index.php')
                assert_denied(response.status,response.text(),[current_brand])
                subprocess.run(['docker','compose','exec','-T','web','php','/checks/maxcore/toggle.php','on'],check=True)
                response=page.goto(BASE+'/custom/maxcore/index.php')
                assert_allowed(response.status,response.text(),'home')
            context.close()
        browser.close()
    print('PASS native roles/CSRF/lifecycle plus denied-content, nine strict overflow checks and mobile keyboard saves')

try:
    run()
except Exception as error:
    message=str(error)
    for secret in secrets: message=message.replace(secret,'[redacted]')
    raise SystemExit(message) from None
