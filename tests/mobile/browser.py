"""Installed native lists before/after opt-in adapter. All data is synthetic."""
import json
from pathlib import Path
import subprocess
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
PAGES = ['societe/list.php', 'product/list.php', 'commande/list.php',
         'expedition/list.php', 'compta/facture/list.php']
measurements = []
owner = dict(login='maxadmin', password=Path('.local/p2-admin-secret').read_text().strip())
accounts = json.loads(Path('.local/mobile-accounts.json').read_text())
secrets = [owner['password']] + [v['password'] for v in accounts.values()]

def toggle(mode):
    subprocess.run(['docker','compose','exec','-T','web','php','/checks/mobile/runtime.php',mode],check=True)

def login(browser, account):
    context = browser.new_context()
    context.route('**/*', lambda r: r.continue_() if r.request.url.startswith(BASE+'/') else r.abort())
    page = context.new_page()
    page.goto(BASE+'/')
    page.locator('[name=username]').fill(account['login'])
    page.locator('[name=password]').fill(account['password'])
    page.locator('input[type=submit],button[type=submit]').first.click()
    page.wait_for_load_state('networkidle')
    assert page.locator('[name=password]').count()==0, 'Native login failed'
    return context, page

def measure(page, width, path, phase):
    page.evaluate('document.fonts.ready')
    row = page.evaluate("""() => ({
        viewport:innerWidth,body:document.body.scrollWidth,document:document.documentElement.scrollWidth,
        wide:[...document.querySelectorAll('div.fiche *')].filter(n=>!n.closest('.maxmobile-table-scroll') && n.getBoundingClientRect().right>innerWidth && getComputedStyle(n).display!=='none').slice(0,20).map(n=>({tag:n.tagName,classes:[...n.classList].slice(0,4),width:n.getBoundingClientRect().width,right:n.getBoundingClientRect().right,white_space:getComputedStyle(n).whiteSpace})),
        boxes:Object.fromEntries(['#id-left','#id-right','div.fiche','.div-table-responsive','table.titre','div.pagination'].map(s => [
            s,[...document.querySelectorAll(s)].slice(0,3).map(n => {
                const r=n.getBoundingClientRect();
                return {left:r.left,right:r.right,width:r.width,scroll:n.scrollWidth,client:n.clientWidth};
            })
        ]))
    })""")
    row.update(width=width,path=path,phase=phase)
    measurements.append(row)
    Path('.local/mobile-measurements.json').write_text(json.dumps(measurements,indent=2))
    page.screenshot(path=f'.local/mobile-{phase}-{width}-{path.replace("/","-")}.png',full_page=True)
    print('MOBILE_LAYOUT '+json.dumps(row),flush=True)
    if phase=='after':
        assert row['viewport']==width and row['body']<=width and row['document']<=width, 'Page overflow: '+json.dumps(row)

def run():
    with sync_playwright() as p:
        browser=p.chromium.launch()
        context,page=login(browser,owner)
        for phase in ['before','after']:
            if phase=='after':
                toggle('on')
            for width in [360,390,768]:
                page.set_viewport_size(dict(width=width,height=900))
                for path in PAGES:
                    response=page.goto(BASE+'/'+path+'?lang=tr_TR')
                    assert response.status==200 and page.locator('table.liste').count()>=1, 'Native list absent'
                    if phase=='after':
                        page.locator('body.maxmobile-page').wait_for()
                        region=page.locator('.maxmobile-table-scroll').first
                        region.wait_for()
                    measure(page,width,path,phase)
                    if phase=='after':
                        assert region.get_attribute('role')=='region' and region.get_attribute('aria-label')
                        region.focus()
                        assert region.evaluate('(n)=>n===document.activeElement'), 'Table not keyboard focusable'
                        region.press('End')
                        assert region.evaluate('(n)=>n.scrollLeft+n.clientWidth>=n.scrollWidth-1'), 'Last columns unreachable'
                        region.press('Home')
                        assert region.evaluate('(n)=>n.scrollLeft')==0, 'First columns unreachable'
                        field=region.locator('input[name^=search_]:visible').first
                        assert field.count(), 'Native filters missing'
                        field.focus()
                        assert field.evaluate('(n)=>n===document.activeElement'), 'Filter cannot receive keyboard focus'
                        search=page.locator('button.button_search[name=button_search_x]').first
                        assert search.count()>0, 'Native filter actions missing'
                        search.focus()
                        assert search.evaluate('(n)=>n===document.activeElement'), 'Search action cannot receive keyboard focus'
                        selection=region.locator('input[type=checkbox][name="toselect[]"]')
                        if selection.count():
                            selection.first.check()
                            assert selection.first.is_checked(), 'Row selection failed'
                            selection.first.uncheck()
                        assert page.locator('.pagination, .liste_titre').count()>0, 'Native list navigation absent'
            if phase=='before':
                assert any(r['body']>r['width'] for r in measurements), 'Baseline defect not reproduced'
        page.set_viewport_size(dict(width=360,height=900))
        page.goto(BASE+'/product/list.php')
        field=page.locator('[name=search_ref]')
        field.fill('P2-SKU'); field.press('Enter')
        page.wait_for_load_state('networkidle')
        link=page.locator('table.liste a').filter(has_text='P2-SKU').first
        assert link.count()>0, 'Native filter lost fixture'
        assert page.locator('table.liste a').filter(has_text='P2-CARTON').count()==0, 'Native filter not applied'
        link.focus(); link.press('Enter'); page.wait_for_load_state('networkidle')
        assert '/product/card.php' in page.url, 'Native card navigation failed'
        assert not page.locator('body.maxmobile-page').count(), 'Adapter leaked to non-list endpoint'
        context.close()
        for role,account in accounts.items():
            context,page=login(browser,account)
            page.set_viewport_size(dict(width=360,height=900))
            for path in PAGES:
                response=page.goto(BASE+'/'+path)
                if role=='reader':
                    assert page.locator('table.liste').count()>=1, 'Read rights lost'
                    assert page.locator('body.maxmobile-page').count()==1, 'Reader adapter missing'
                else:
                    assert page.locator('table.liste').count()==0, 'Native permissions bypassed'
                    assert not page.locator('.maxmobile-table-scroll').count(), 'Denied page contains table'
                print('PASS native mobile role '+role+'/'+path+' HTTP '+str(response.status),flush=True)
            context.close()
        context,page=login(browser,owner)
        toggle('off')
        page.goto(BASE+'/product/list.php')
        assert not page.locator('body.maxmobile-page').count(), 'Disable left adapter active'
        toggle('on')
        page.goto(BASE+'/product/list.php')
        assert page.locator('body.maxmobile-page').count(), 'Re-enable failed'
        browser.close()
    print('PASS all 15 strict mobile cases, table keyboard/filter/card flow, native roles and opt-in lifecycle')

try:
    run()
except Exception as error:
    message=str(error)
    for secret in secrets:
        message=message.replace(secret,'[redacted]')
    raise SystemExit(message) from None
