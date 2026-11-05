"""Real native sessions, multipart HTTP, private files and strict mobile evidence."""
from concurrent.futures import ThreadPoolExecutor
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import traceback
from playwright.sync_api import sync_playwright

BASE='http://127.0.0.1:8080'
URL=BASE+'/custom/maxorders/'
fixture=json.loads(Path('.local/orders-intake-session.json').read_text())
inputs=Path('.local/orders-intake-inputs')
metrics=[]; checks=[]


def safe_failure(kind,error,tb):
    locations=[dict(file=Path(row.filename).name,line=row.lineno) for row in traceback.extract_tb(tb)]
    Path('.local/orders-intake-http.json').write_text(json.dumps(dict(passed=False,checks=checks,error_type=kind.__name__,locations=locations,observed_viewports=len(metrics),required_viewports=9)))
    print('FAIL Orders intake browser '+kind.__name__+'; sanitized locations in orders-intake-http.json',file=sys.stderr)


sys.excepthook=safe_failure


def check(value,label):
    if not value:
        print('FAIL '+label,file=sys.stderr)
        raise AssertionError()
    checks.append(dict(check=label,status='PASS'))


def csrf(page):
    return page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')


def login(browser,role):
    context=browser.new_context()
    context.route('**/*',lambda route:route.continue_() if route.request.url.startswith(BASE+'/') else route.abort())
    context.add_cookies([dict(name=fixture['cookie_name'],value='1',url=BASE)])
    page=context.new_page();page.goto(BASE+'/')
    account=fixture['accounts'][role]
    page.locator('[name=username]').fill(account['login']);page.locator('[name=password]').fill(account['password'])
    page.locator('[type=submit]').first.click();page.wait_for_load_state('networkidle')
    check(not page.locator('[name=password]').count(),'native login '+role)
    return context,page


def denied(response,label):
    text=response.text()
    check(response.status==403 and 'data-maxorders=' not in text and 'misleading-PO.xlsx' not in text and not response.body().startswith((b'PK',b'%PDF-')),label)


def upload(context,page,name,token=None,bytes_=None):
    payload=dict(token=token if token is not None else csrf(page),action='upload',
                 document_file=dict(name=name,mimeType='application/octet-stream',buffer=bytes_ if bytes_ is not None else (inputs/name).read_bytes()))
    return context.request.post(URL+'documents.php',multipart=payload,max_redirects=0)


def measure(page,width,screen):
    page.evaluate('document.fonts.ready')
    row=page.evaluate('''() => ({viewport:innerWidth,body:document.body.scrollWidth,document:document.documentElement.scrollWidth,
      regions:[...document.querySelectorAll('.maxorders-scroll')].map(n=>({client:n.clientWidth,scroll:n.scrollWidth,tab:n.tabIndex,role:n.getAttribute('role')}))})''')
    row.update(width=width,screen=screen);metrics.append(row)
    Path('.local/orders-intake-mobile.json').write_text(json.dumps(metrics,indent=2))
    check(row['viewport']==width and max(row['body'],row['document'])<=width,'strict '+screen+' '+str(width))
    for region in row['regions']:check(region['tab']==0 and region['role']=='region','labeled table scrolling')
    if page.locator('.maxorders-scroll').count():
        area=page.locator('.maxorders-scroll').first;area.focus();page.keyboard.press('End');page.keyboard.press('Home')
        check(area.evaluate('(n)=>document.activeElement===n'),'keyboard table region')
    page.screenshot(path=f'.local/orders-intake-{screen}-{width}.png',full_page=True)


with sync_playwright() as p:
    browser=p.chromium.launch()
    anonymous=browser.new_context()
    response=anonymous.request.get(URL+f'download.php?id={fixture["uploaded"]}')
    denied(response,'anonymous file access denied');anonymous.close()
    for role in fixture['accounts']:
        context,page=login(browser,role)
        allowed=role not in ['denied','no-native']
        for path in ['documents.php',f'document.php?id={fixture["uploaded"]}']:
            response=page.goto(URL+path)
            if allowed:check(response.status==200 and 'data-maxorders=' in response.text(),'permitted document screen '+role)
            else:denied(response,'denied document screen '+role)
        response=context.request.get(URL+f'download.php?id={fixture["uploaded"]}')
        if allowed:
            check(response.status==200 and hashlib.sha256(response.body()).hexdigest()==fixture['uploaded_hash'],'private bytes authenticated '+role)
            headers=response.headers
            check(headers['content-type']=='application/octet-stream' and headers['content-disposition'].startswith('attachment;') and headers['x-content-type-options']=='nosniff' and 'no-store' in headers['cache-control'] and 'sandbox' in headers['content-security-policy'],'safe download headers')
            check(context.request.post(URL+f'download.php?id={fixture["uploaded"]}').status==405,'download is GET only')
            check(context.request.get(URL+'download.php?id=../../outside').status==404,'download accepts scoped numeric ID only')
        else:denied(response,'private file denied '+role)
        page.goto(BASE+'/')
        if role not in ['intake','document-review']:
            denied(upload(context,page,'manual.pdf'),'HTTP upload role denied '+role)
        if role!='document-review':
            response=context.request.post(URL+'document.php',form=dict(token=csrf(page),id=str(fixture['uploaded']),action='associate',packet=str(fixture['packet']),customer=str(fixture['customer']),reference='PACK-100',kind='PO',document='FORBIDDEN',revision='1',facts='{}',confirmed='yes'))
            denied(response,'HTTP association role denied '+role)
        if role in ['intake','document-review']:
            page.goto(URL+'documents.php')
            denied(upload(context,page,'manual.pdf',token='invalid'),'HTTP upload CSRF denied '+role)
        context.close()

    context,page=login(browser,'document-review')
    errors=[];assets=[]
    page.on('pageerror',lambda error:errors.append(type(error).__name__))
    page.on('console',lambda message:errors.append('console-error') if message.type=='error' else None)
    page.on('response',lambda response:assets.append(response.request.resource_type) if response.status>=400 and response.request.resource_type in ['script','stylesheet','image','font'] else None)
    page.goto(URL+'documents.php?lang=tr_TR')
    check('Gelen PDF / XLSX belgeleri' in page.locator('h1').inner_text(),'Turkish upload screen')
    page.locator('[name=document_file]').focus()
    check(page.locator('[name=document_file]').evaluate('(n)=>document.activeElement===n'),'keyboard file input')
    page.locator('[name=document_file]').set_input_files(inputs/'browser.xlsx')
    page.locator('[data-orders-upload] button').click();page.wait_for_load_state('networkidle')
    xlsx_id=int(page.url.split('id=')[1]);xlsx_path=f'document.php?id={xlsx_id}'
    check('Synthetic!B11' in page.locator('body').inner_text(),'cell provenance visible in review')
    page.goto(URL+'documents.php?lang=en_US')
    check('Incoming PDF / XLSX documents' in page.locator('h1').inner_text(),'English upload screen')
    response=upload(context,page,'manual.pdf')
    check(response.status==302,'PDF accepted into private review')
    pdf_id=int(response.headers['location'].split('id=')[1]);pdf_path=f'document.php?id={pdf_id}'
    again=upload(context,page,'manual.pdf');check(again.headers.get('location')==response.headers['location'],'HTTP duplicate returns same private record')
    for width in [360,390,768]:
        page.set_viewport_size(dict(width=width,height=900))
        for path,screen in [('documents.php','documents'),(xlsx_path,'xlsx-review'),(pdf_path,'pdf-review')]:
            reply=page.goto(URL+path+('&' if '?' in path else '?')+'lang=tr_TR')
            check(reply.status==200,'mobile authenticated screen');measure(page,width,screen)
    check(not errors and not assets,'authorized screens have no JS errors or missing assets')
    page.goto(URL+xlsx_path)
    denied(context.request.post(URL+'document.php',form=dict(token='invalid',id=str(xlsx_id),action='associate')),'HTTP association CSRF denied')
    page.goto(URL+xlsx_path)
    page.locator('[name=packet]').fill(str(fixture['packet']));page.locator('[name=customer]').fill(str(fixture['customer']))
    page.locator('[name=confirmed]').check();page.locator('form button').click();page.wait_for_load_state('networkidle')
    check('Saved' in page.locator('[role=status]').inner_text() or 'Kaydedildi' in page.locator('[role=status]').inner_text(),'pointer-confirmed XLSX association')
    check(not page.locator('[name=packet]').count(),'associated file cannot silently reassign')
    page.goto(URL+pdf_path)
    check('BLOCKED' in page.locator('body').inner_text(),'PDF parser limitation visible')
    page.locator('[name=packet]').fill(str(fixture['packet']));page.locator('[name=customer]').fill(str(fixture['customer']))
    page.locator('[name=reference]').fill('PACK-100');page.locator('[name=kind]').select_option('PO');page.locator('[name=document]').fill('MANUAL-PDF')
    page.locator('[name=facts]').fill('{"quantity":"17"}');page.locator('[name=confirmed]').check()
    page.locator('form button').click();page.wait_for_load_state('networkidle')
    check(not page.locator('[name=packet]').count() and 'BLOCKED' in page.locator('body').inner_text(),'PDF manual association preserves extraction BLOCKED')
    response=context.request.get(URL+f'download.php?id={pdf_id}')
    check(response.status==200 and response.body()==(inputs/'manual.pdf').read_bytes(),'PDF private original bytes retained')
    check(not errors and not assets,'authorized association and download have no JS errors or missing assets')
    context.close();browser.close()

# Distinct PHP processes test real DB concurrency (not PHP session serialization).
def concurrent_upload(_):
    result=subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','/checks/orders/intake_runtime.php','concurrent'],capture_output=True,text=True)
    assert result.returncode==0,'Concurrent native intake failed'
with ThreadPoolExecutor(max_workers=2) as pool:list(pool.map(concurrent_upload,range(2)))
expected={(width,screen) for width in [360,390,768] for screen in ['documents','xlsx-review','pdf-review']}
check(len(metrics)==9 and {(row['width'],row['screen']) for row in metrics}==expected,'all nine strict viewport cases present exactly once')
Path('.local/orders-intake-http.json').write_text(json.dumps(dict(passed=True,checks=checks,strict_viewports=len(metrics),pdf_extraction='BLOCKED',js_errors=errors,missing_assets=assets),indent=2))
print('PASS Orders intake native HTTP/CSRF/private files, real DB concurrency and 9 strict mobile cases')
