"""Real native sessions and server projections. Never publish restricted content."""
import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080'
fixture=json.loads(Path('.local/customs-session.json').read_text())
accounts=fixture['accounts']
restricted_values=['CONTACT_RESTRICTED_SENTINEL','ANALYSIS_RESTRICTED_SENTINEL','SOURCE_RESTRICTED_SENTINEL','PRIVATE_EVIDENCE_SENTINEL']
measurements=[]

def private_absent(body):
    assert not any(value in body for value in restricted_values),'Restricted payload disclosed'

def denied(response, status=403):
    assert response.status==status,f'Unexpected denial status {response.status}'
    private_absent(response.text())
    assert 'data-maxcustoms=' not in response.text()

def csrf(page):
    return page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')

def api(context, ident=None):
    response=context.request.get(BASE+'/custom/maxcustoms/api.php?id='+str(ident or fixture['id']))
    assert response.status==200,'Authorized API failed'
    return response.json()

def measure(page, width, screen):
    page.evaluate('document.fonts.ready')
    data=page.evaluate('''() => ({width:innerWidth,document:document.documentElement.scrollWidth,body:document.body.scrollWidth,
    regions:[...document.querySelectorAll('.maxcustoms-scroll')].map(n=>({width:n.clientWidth,scroll:n.scrollWidth,tab:n.tabIndex,role:n.getAttribute('role')}))})''')
    data['screen']=screen
    measurements.append(data)
    Path('.local/customs-layout.json').write_text(json.dumps(measurements,indent=2))
    private_absent(page.content())  # No privileged screenshots, even with synthetic values.
    page.screenshot(path=f'.local/customs-view-{screen}-{width}.png',full_page=True)
    assert data['width']==width and max(data['document'],data['body'])<=width+1,'Page overflow'
    for region in data['regions']:
        assert region['tab']==0 and region['role']=='region','Table region not keyboard accessible'
    if page.locator('.maxcustoms-scroll').count():
        node=page.locator('.maxcustoms-scroll').first
        node.focus(); page.keyboard.press('ArrowRight')
        assert node.evaluate('(n)=>document.activeElement===n')

with sync_playwright() as p:
    browser=p.chromium.launch()
    manager_context=None
    for role in ['denied','viewer','editor','matcher','source','reviewer','contact','analysis','manager','no-native']:
        account=accounts[role]
        context=browser.new_context()
        context.route('**/*',lambda route:route.continue_() if route.request.url.startswith(BASE+'/') else route.abort())
        page=context.new_page(); page.goto(BASE+'/')
        page.locator('[name=username]').fill(account['login']); page.locator('[name=password]').fill(account['password'])
        page.locator('[type=submit]').first.click(); page.wait_for_load_state('networkidle')
        assert not page.locator('[name=password]').count(),'Native login failed'
        response=page.goto(BASE+f'/custom/maxcustoms/card.php?id={fixture["id"]}&lang=tr_TR')
        if role in ['denied','no-native']:
            denied(response)
            for path in [f'api.php?id={fixture["id"]}',f'export.php?id={fixture["id"]}',f'download.php?id={fixture["id"]}&evidence_id={fixture["evidence"]}']:
                denied(context.request.get(BASE+'/custom/maxcustoms/'+path))
            page.goto(BASE+'/')
            denied(context.request.post(BASE+'/custom/maxcustoms/api.php',form={'token':csrf(page),'action':'edit','id':str(fixture['id']),'version':'1','fields':'{}','restricted':'{"contact":""}'}))
            context.close(); continue
        assert response.status==200 and 'data-maxcustoms="detail"' in response.text()
        result=api(context)
        expected={'contact':{'contact'},'analysis':{'analysis'},'source':{'source'},'manager':{'contact','analysis','source'}}.get(role,set())
        assert set(result.get('restricted',{}))==expected,'Unexpected field projection'
        if role=='viewer':
            english=page.goto(BASE+f'/custom/maxcustoms/card.php?id={fixture["id"]}&lang=en_US')
            assert english.status==200 and 'Local unsubmitted draft' in page.locator('body').inner_text(),'English surface failed'
            private_absent(english.text())
            page.goto(BASE+f'/custom/maxcustoms/card.php?id={fixture["id"]}&lang=tr_TR')
        if not expected:
            private_absent(response.text()); private_absent(json.dumps(result))
            assert not page.locator('.maxcustoms-shell [name=contact],.maxcustoms-shell [name=analysis],.maxcustoms-shell [name=source]').count()
        exported=context.request.get(BASE+f'/custom/maxcustoms/export.php?id={fixture["id"]}')
        assert exported.status==200 and 'text/csv' in exported.headers['content-type']
        if not expected: private_absent(exported.text())
        download=context.request.get(BASE+f'/custom/maxcustoms/download.php?id={fixture["id"]}&evidence_id={fixture["evidence"]}')
        if role not in ['source','manager']:
            denied(download)
        else:
            assert download.status==200 and download.body()==b'PRIVATE_EVIDENCE_SENTINEL'
            assert download.headers['content-type']=='application/octet-stream' and 'attachment' in download.headers['content-disposition']
            assert download.headers.get('x-content-type-options')=='nosniff'
        if role!='manager':
            denied(context.request.post(BASE+'/custom/maxcustoms/api.php',form={'token':csrf(page),'action':'edit','id':str(fixture['id']),'version':str(result['version']),'fields':'{"office":"FORBIDDEN-UPDATE"}','restricted':'{"contact":""}'}))
        if role=='editor':
            before=api(context)
            denied(context.request.post(BASE+'/custom/maxcustoms/api.php',form={'token':'invalid','action':'edit','id':str(fixture['id']),'version':str(before['version']),'fields':'{"office":"FORBIDDEN-UPDATE"}'}))
            assert api(context)==before,'CSRF altered draft'
            denied(context.request.post(BASE+'/custom/maxcustoms/index.php',form={'token':'invalid','action':'create','ref':'CSRF-FORBIDDEN','office':'NO'}))
            page.reload(); page.wait_for_load_state('networkidle')
            page.locator('section[data-maxcustoms=edit] [name=office]').fill('Browser synthetic office')
            page.locator('section[data-maxcustoms=edit] [name=goods_summary]').fill('Synthetic <script>window.customsLeak=1</script>')
            page.locator('section[data-maxcustoms=edit] button').click(); page.wait_for_load_state('networkidle')
            assert api(context)['fields']['office']=='Browser synthetic office','Valid public edit failed'
            assert page.evaluate('typeof window.customsLeak')=='undefined','Source text executed script'
            assert '<script>window.customsLeak=1</script>' in page.locator('main.maxcustoms-shell').inner_text(),'Source text was not safely rendered'
        if role=='matcher':
            page.reload(); page.wait_for_load_state('networkidle')
            section=page.locator('[data-maxcustoms=match]')
            section.locator('[name=customer]').fill(str(fixture['customer']))
            section.locator('[name=order]').fill(str(fixture['order']))
            section.locator('[name=order_reference]').fill(fixture['reference'])
            section.locator('[name=shipments]').fill(','.join(map(str,fixture['shipments'])))
            section.locator('[name=confirm]').check(); section.locator('button').click(); page.wait_for_load_state('networkidle')
            assert len(api(context)['shipments'])==2,'Valid native link form failed'
        if role=='reviewer':
            page.reload(); page.wait_for_load_state('networkidle')
            section=page.locator('[data-maxcustoms=status]')
            section.locator('[name=target]').select_option('reviewed')
            section.locator('[name=confirm]').check(); section.locator('button').click(); page.wait_for_load_state('networkidle')
            assert api(context)['status']=='reviewed','Valid status form failed'
        if role in ['viewer','editor','matcher','reviewer']:
            screens=[('card.php?id='+str(fixture['id']),{'viewer':'detail','editor':'edit','matcher':'match','reviewer':'status'}[role])]
            if role in ['viewer','editor']: screens.append(('index.php','list' if role=='viewer' else 'create'))
            for width in [360,390,768]:
                page.set_viewport_size({'width':width,'height':900})
                for path,screen in screens:
                    reply=page.goto(BASE+'/custom/maxcustoms/'+path+('&' if '?' in path else '?')+'lang=tr_TR')
                    assert reply.status==200
                    assert 'Gönderilmemiş yerel taslak' in page.locator('body').inner_text()
                    measure(page,width,screen)
        if role=='source':
            current=api(context)
            # Browser evidence is read-only after review; no implicit correction.
            if current['status']=='draft':
                page.reload(); page.wait_for_load_state('networkidle')
                page.locator('[name=evidence]').set_input_files({'name':'web.txt','mimeType':'text/plain','buffer':b'opaque synthetic evidence'})
                page.locator('form[enctype] button').click(); page.wait_for_load_state('networkidle')
        if role=='manager':
            manager_context=context
            result=api(context)
            assert result['restricted']==dict(contact=restricted_values[0],analysis=restricted_values[1],source=restricted_values[2]),'Denied updates altered private fields'
            subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','/checks/customs/runtime.php','off'],check=True)
            denied(context.request.get(BASE+f'/custom/maxcustoms/api.php?id={fixture["id"]}'))
            subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','/checks/customs/runtime.php','on'],check=True)
            assert api(context)['id']==fixture['id']
        context.close()
    browser.close()
Path('.local/customs-summary.json').write_text(json.dumps({'role_cases':len(accounts),'mobile_cases':len(measurements),'restricted_payloads_in_artifacts':False,'outbound_transport':False},indent=2))
print('PASS native HTTP role matrix, field redaction, CSRF, links, local review and 18 mobile renders')
