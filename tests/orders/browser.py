"""Installed module HTTP, native-session/CSRF and strict viewport acceptance."""
import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080'
fixture=json.loads(Path('.local/orders-accounts.json').read_text())
accounts=fixture['accounts']
accounts['owner']=dict(login='maxadmin',password=Path('.local/p2-admin-secret').read_text().strip())
metrics=[]


def denied(response):
    assert response.status==403, f'Expected 403, got {response.status}'
    body=response.text()
    assert 'data-maxorders=' not in body and 'PACK-100' not in body
    assert 'Access denied' in body or 'Erişim' in body or 'erişim' in body


def allowed(response, marker):
    assert response.status==200, f'Expected 200, got {response.status}'
    assert f'data-maxorders="{marker}"' in response.text()
    assert 'Fatal error' not in response.text()


def token(page):
    return page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')


def measure(page,width,screen):
    page.evaluate('document.fonts.ready')
    data=page.evaluate('''() => ({viewport:innerWidth,document:document.documentElement.scrollWidth,body:document.body.scrollWidth,
      client:document.documentElement.clientWidth, scroll_regions:[...document.querySelectorAll('.maxorders-scroll')].map(n=>({scroll:n.scrollWidth,client:n.clientWidth,tabindex:n.tabIndex,role:n.getAttribute('role')}))})''')
    data.update(width=width,screen=screen)
    metrics.append(data)
    Path('.local/orders-mobile.json').write_text(json.dumps(metrics,indent=2))
    page.screenshot(path=f'.local/orders-{screen}-{width}.png',full_page=True)
    assert data['viewport']==width and max(data['document'],data['body'])<=width+1, f'Horizontal page overflow: {data}'
    for region in data['scroll_regions']:
        assert region['tabindex']==0 and region['role']=='region'
    # Real keyboard focus on locally scrollable source tables.
    if page.locator('.maxorders-scroll').count():
        region=page.locator('.maxorders-scroll').first
        region.focus(); page.keyboard.press('ArrowRight')
        assert region.evaluate('(n)=>document.activeElement===n')


with sync_playwright() as p:
    browser=p.chromium.launch()
    for role, account in accounts.items():
        context=browser.new_context()
        context.route('**/*',lambda r:r.continue_() if r.request.url.startswith(BASE+'/') else r.abort())
        page=context.new_page(); page.goto(BASE+'/')
        page.locator('[name=username]').fill(account['login']); page.locator('[name=password]').fill(account['password'])
        page.locator('[type=submit]').first.click(); page.wait_for_load_state('networkidle')
        assert not page.locator('[name=password]').count(), 'Native authentication failed'
        paths=[('index.php','inbox'),(f'packet.php?id={fixture["packet"]}','packet'),(f'review.php?id={fixture["packet"]}','review')]
        for path, marker in paths:
            response=page.goto(BASE+'/custom/maxorders/'+path+'?lang=tr_TR' if '?' not in path else BASE+'/custom/maxorders/'+path+'&lang=tr_TR')
            can_read=role not in ['denied','no-native']
            if not can_read or (marker=='review' and role not in ['reviewer','owner']): denied(response)
            else: allowed(response,marker)
        if role in ['denied','reader','intake','no-native']:
            page.goto(BASE+'/')
            for action,extra in [('lock',dict(field='quantity',value='999',locked='yes')),('link',dict(order=str(fixture['order']),confirmed='yes')),('resolve',dict(conflict='1',decision='acknowledge'))]:
                reply=context.request.post(BASE+'/custom/maxorders/review.php',form=dict(token=token(page),id=str(fixture['packet']),action=action,**extra))
                denied(reply)
        if role in ['denied','reader','reviewer','no-native']:
            page.goto(BASE+'/')
            reply=context.request.post(BASE+'/custom/maxorders/index.php',form=dict(token=token(page),action='intake',customer=str(fixture['customer']),reference='FORBIDDEN',kind='PO',document='D',revision='1',facts='{}'))
            denied(reply)
        if role in ['intake','owner']:
            page.goto(BASE+'/custom/maxorders/index.php')
            reply=context.request.post(BASE+'/custom/maxorders/index.php',form=dict(token='invalid',action='intake',customer=str(fixture['customer']),reference='CSRF-FORBIDDEN',kind='PO',document='D',revision='1',facts='{}'))
            denied(reply)
        if role=='intake':
            page.goto(BASE+'/custom/maxorders/index.php?lang=tr_TR')
            assert 'Paket gelen kutusu' in page.locator('body').inner_text()
            page.locator('[name=customer]').fill(str(fixture['customer']))
            page.locator('[name=reference]').fill('BROWSER-LL')
            page.locator('[name=kind]').select_option('LL')
            page.locator('[name=document]').fill('LL-BROWSER')
            page.locator('[name=facts]').fill('{"quantity":"15","sku":"DEMO"}')
            page.locator('form button').click(); page.wait_for_load_state('networkidle')
            assert 'BROWSER-LL' in page.locator('h1').inner_text()
        if role=='reviewer':
            page.goto(BASE+f'/custom/maxorders/review.php?id={fixture["packet"]}&lang=tr_TR')
            reply=context.request.post(BASE+'/custom/maxorders/review.php',form=dict(token='invalid',action='lock',id=str(fixture['packet']),field='quantity',value='999',locked='yes'))
            denied(reply)
            # Valid form writes a manual lock; no native order/stock write.
            page.locator('[name=value]').fill('100')
            page.locator('form').first.locator('button').click(); page.wait_for_load_state('networkidle')
            assert 'Kaydedildi' in page.locator('[role=status]').inner_text()
        if role=='owner':
            for width in [360,390,768]:
                page.set_viewport_size(dict(width=width,height=900))
                for path,marker in paths:
                    response=page.goto(BASE+'/custom/maxorders/'+path+('&' if '?' in path else '?')+'lang=tr_TR')
                    allowed(response,marker); measure(page,width,marker)
            subprocess.run(['docker','compose','exec','-T','web','php','/checks/orders/runtime.php','off'],check=True)
            denied(page.goto(BASE+'/custom/maxorders/index.php'))
            subprocess.run(['docker','compose','exec','-T','web','php','/checks/orders/runtime.php','on'],check=True)
            allowed(page.goto(BASE+'/custom/maxorders/index.php'),'inbox')
        context.close()
    browser.close()
print('PASS native HTTP roles, CSRF, Turkish forms, lifecycle and 9 strict responsive screens')
