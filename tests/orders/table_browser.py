"""Native sessions and private HTTP files, 18 strict bilingual viewport cases."""
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
fixture=json.loads(Path('.local/orders-table-session.json').read_text())
inputs=Path('.local/orders-table-inputs')
checks=[]; metrics=[]


def fail(kind,error,tb):
    locations=[dict(file=Path(x.filename).name,line=x.lineno) for x in traceback.extract_tb(tb)]
    Path('.local/orders-table-http.json').write_text(json.dumps(dict(passed=False,checks=checks,error_type=kind.__name__,locations=locations,observed_viewports=len(metrics),required_viewports=18)))
    print('FAIL Orders table browser '+kind.__name__+'; sanitized locations recorded',file=sys.stderr)


sys.excepthook=fail


def check(ok,label):
    if not ok:
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
    account=fixture['accounts'][role] if role!='admin' else dict(login='maxadmin',password=Path('.local/p2-admin-secret').read_text().strip())
    page.locator('[name=username]').fill(account['login']);page.locator('[name=password]').fill(account['password'])
    page.locator('[type=submit]').first.click();page.wait_for_load_state('networkidle')
    check(not page.locator('[name=password]').count(),'native table login '+role)
    return context,page


def deny(response,label):
    body=response.text()
    check(response.status==403 and 'data-maxorders=' not in body and fixture['table_hash'] not in body and not response.body().startswith((b'PK',b'%PDF-')),label)


with sync_playwright() as p:
    browser=p.chromium.launch()
    anonymous=browser.new_context()
    deny(anonymous.request.get(URL+f'download.php?id={fixture["table_upload"]}'),'anonymous table file denied')
    anonymous.close()
    for role in ['denied','no-native','reader','document-review']:
        context,page=login(browser,role)
        if role in ['denied','no-native']:
            deny(context.request.get(URL+f'document.php?id={fixture["table_upload"]}'),'table content denied '+role)
            deny(context.request.get(URL+f'download.php?id={fixture["table_upload"]}'),'table bytes denied '+role)
        else:
            response=page.goto(URL+f'document.php?id={fixture["table_upload"]}')
            check(response.status==200 and page.locator('[data-orders-source-lines] article').count()==3,'authorized persisted source lines '+role)
            check(context.request.get(URL+f'download.php?id={fixture["table_upload"]}').status==200,'authorized table download '+role)
        deny(context.request.get(URL+'admin/setup.php'),'admin-only template settings '+role)
        context.close()

    context,page=login(browser,'document-review')
    page.goto(URL+'documents.php?lang=tr_TR')
    page.locator('[name=document_file]').set_input_files(inputs/'browser.xlsx')
    page.locator('[data-orders-upload] button').click();page.wait_for_load_state('networkidle')
    upload_id=int(page.url.split('id=')[1]);path=f'document.php?id={upload_id}'
    # Native redirects do not persist a one-request lang query parameter.
    page.goto(URL+path+'&lang=tr_TR')
    check(page.locator('[data-orders-source-lines] article').count()==3 and 'Synthetic!D10' in page.locator('body').inner_text(),'multipart upload persists exact source cells')
    check('Birden çok para birimi' in page.locator('body').inner_text(),'visible mixed-currency warning')
    deny(context.request.post(URL+'document.php',form=dict(token='invalid',action='associate',id=str(upload_id))),'table association CSRF denied')
    page.goto(URL+path)
    page.locator('[name=packet]').fill(str(fixture['table_packet']));page.locator('[name=customer]').fill(str(fixture['customer']))
    check(page.locator('[name=facts]').input_value()=='{}' and page.locator('[name=revision]').input_value()=='4','no automatic line aggregation; declared revision proposed')
    page.locator('[name=confirmed]').check();page.locator('form button').click();page.wait_for_load_state('networkidle')
    check(not page.locator('[name=packet]').count(),'pointer human confirmation links table once')
    response=context.request.get(URL+f'download.php?id={upload_id}')
    check(response.status==200 and hashlib.sha256(response.body()).digest()==hashlib.sha256((inputs/'browser.xlsx').read_bytes()).digest(),'linked table private original immutable')
    context.close()

    context,page=login(browser,'admin')
    page.goto(URL+'admin/setup.php')
    deny(context.request.post(URL+'admin/setup.php',form=dict(token='invalid',action='template',columns='{}')),'template CSRF denied')
    page.goto(URL+'admin/setup.php')
    original=page.locator('[name=columns]').input_value()
    response=context.request.post(URL+'admin/setup.php',form=dict(token=csrf(page),action='template',columns='{}'))
    check(response.status==400,'invalid template rejected server-side')
    page.goto(URL+'admin/setup.php')
    mapping=json.loads(original);mapping['description']='Item'
    response=context.request.post(URL+'admin/setup.php',form=dict(token=csrf(page),action='template',columns=json.dumps(mapping)),max_redirects=0)
    check(response.status==303,'admin explicit template configuration saved')
    page.goto(URL+'admin/setup.php');check(json.loads(page.locator('[name=columns]').input_value())==mapping,'configured template read back')
    page.locator('[name=columns]').fill(original);page.locator('form button').click();page.wait_for_load_state('networkidle')
    check(json.loads(page.locator('[name=columns]').input_value())==json.loads(original),'default configuration restored')
    errors=[];assets=[]
    page.on('pageerror',lambda error:errors.append(type(error).__name__))
    page.on('console',lambda message:errors.append('console-error') if message.type=='error' else None)
    page.on('response',lambda response:assets.append(response.request.resource_type) if response.status>=400 and response.request.resource_type in ['script','stylesheet','image','font'] else None)
    for language in ['tr_TR','en_US']:
        for width in [360,390,768]:
            page.set_viewport_size(dict(width=width,height=900))
            for surface,route in [('table-review',path),('source-history',f'packet.php?id={fixture["table_packet"]}'),('table-settings','admin/setup.php')]:
                response=page.goto(URL+route+('&' if '?' in route else '?')+'lang='+language)
                check(response.status==200,'native bilingual surface '+surface)
                page.evaluate('document.fonts.ready')
                row=page.evaluate('''() => ({viewport:innerWidth,body:document.body.scrollWidth,document:document.documentElement.scrollWidth,
                  regions:[...document.querySelectorAll('.maxorders-scroll')].map(n=>({client:n.clientWidth,scroll:n.scrollWidth,tab:n.tabIndex,role:n.getAttribute('role')}))})''')
                row.update(width=width,language=language,surface=surface);metrics.append(row)
                Path('.local/orders-table-mobile.json').write_text(json.dumps(metrics,indent=2))
                check(row['viewport']==width and max(row['body'],row['document'])<=width,'strict '+surface+' '+language+' '+str(width))
                for region in row['regions']:check(region['tab']==0 and region['role']=='region','accessible local source table')
                if page.locator('.maxorders-scroll').count():
                    region=page.locator('.maxorders-scroll').first;region.focus();page.keyboard.press('End');page.keyboard.press('Home')
                    check(region.evaluate('(n)=>document.activeElement===n'),'keyboard source table')
                body=page.locator('body').inner_text()
                check('OrdersSourceLines' not in body and 'OrdersTemplateSettings' not in body and 'OrdersSourceDifference' not in body,'localized source labels')
                page.screenshot(path=f'.local/orders-table-{surface}-{language}-{width}.png',full_page=True)
    check(len(metrics)==18 and not errors and not assets,'18 strict bilingual cases; no JS errors or missing assets')
    context.close();browser.close()

def concurrent_receive(_):
    subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','/checks/orders/table_runtime.php','concurrent'],check=True,stdout=subprocess.DEVNULL)


with ThreadPoolExecutor(max_workers=2) as executor:list(executor.map(concurrent_receive,range(2)))
Path('.local/orders-table-http.json').write_text(json.dumps(dict(passed=True,checks=checks,observed_viewports=len(metrics),required_viewports=18),indent=2))
print('PASS Orders 0.3 table HTTP and 18 strict bilingual mobile cases')
