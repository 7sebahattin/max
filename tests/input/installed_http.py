"""Real installed HTTP/session/API checks; synthetic payloads and loopback only."""
import json
import os
from pathlib import Path
from urllib.parse import urlencode, urlparse
from playwright.sync_api import sync_playwright

RUN=Path(os.environ['MAX_INPUT_NATIVE_RUN'])
BASE='http://127.0.0.1:'+os.environ['MAX_INPUT_NATIVE_HTTP']
ACCOUNTS=json.loads((RUN/'accounts.json').read_text(encoding='utf-8'))
CHECKS=[]


def check(ok,label,continue_on_failure=False):
    CHECKS.append(dict(check=label,status='PASS' if ok else 'FAIL'))
    (RUN/'http-checks.json').write_text(json.dumps(CHECKS,indent=2),encoding='utf-8')
    print(('PASS ' if ok else 'FAIL ')+label,flush=True)
    if not ok and not continue_on_failure: raise AssertionError(label)


def main():
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True,chromium_sandbox=True)
        ctx=browser.new_context(base_url=BASE)
        external=[]; errors=[]
        def guard(route):
            if urlparse(route.request.url).netloc==urlparse(BASE).netloc: route.continue_()
            else: external.append(route.request.resource_type); route.abort()
        ctx.route('**/*',guard)
        ctx.add_cookies([dict(name=ACCOUNTS['privacy'],value='1',url=BASE)])
        page=ctx.new_page();page.on('pageerror',lambda err:errors.append(type(err).__name__))

        def api(method,path,data=None,key='admin',**kwargs):
            headers={'Content-Type':'application/json'}
            if key: headers['DOLAPIKEY']=ACCOUNTS[key]['key']
            if isinstance(data,dict): data=json.dumps(data,ensure_ascii=False)
            return ctx.request.fetch(BASE+'/api/index.php/'+path,method=method,data=data,headers=headers,**kwargs)

        check(api('POST','products',dict(ref='Unauth-Case',label='izinsiz',type=0),key=None).status in (401,403),'native API authentication required')
        check(api('POST','products',dict(ref='Denied-Case',label='izinsiz',type=0),key='denied').status==403,'native API role denies write')
        response=api('POST','products',dict(ref='Api-SkuCase',label='istanbul ıslak c\u0327ic\u0327ek straße',description='<p>şişli <a href="https://example.invalid/Case">çiçek</a></p>',type=0,price='12.340000',status=1,status_buy=1))
        check(response.ok,'native API create accepted')
        pid=int(response.json()); product=api('GET','products/'+str(pid)).json()
        check(product['label']=='İSTANBUL ISLAK ÇİÇEK STRASSE','native API persists Turkish NFC expansion')
        check(product['ref']=='Api-SkuCase' and 'https://example.invalid/Case' in product['description'],'native API technical SKU and rich link preserved')
        check('ŞİŞLİ' in product['description'] and 'ÇİÇEK' in product['description'],'native API rich business text persisted')
        response=api('PUT','products/'+str(pid),dict(label='ikinci çiçek'))
        check(response.ok and api('GET','products/'+str(pid)).json()['label']=='İKİNCİ ÇİÇEK','native API update normalizes')
        response=api('POST','products',dict(ref='Api-SkuCase',label='çakışma',type=0))
        check(not response.ok and api('GET','products/'+str(pid)).json()['label']=='İKİNCİ ÇİÇEK','native unique SKU rejects without merge')
        response=api('POST','products',dict(ref='Api-Overflow',label='ß'*128,type=0))
        check(response.status==400 and api('GET','products/ref/Api-Overflow').status==404,'API expansion overflow rejected without row')
        response=api('POST','products','{"ref":"Api-Duplicate","label":"ilk","label":"son","type":0}')
        check(response.status==400 and api('GET','products/ref/Api-Duplicate').status==404,'API duplicate JSON keys rejected without row')
        response=api('POST','products',dict(ref='Api-QueryCase',label='gövde çiçek',type=0),params={'label':'sorgu şişli'})
        check(response.ok,'API query bypass request accepted')
        query=api('GET','products/'+str(int(response.json()))).json()
        check(query['label'] in ('GÖVDE ÇİÇEK','SORGU ŞİŞLİ'),'API query and body cannot bypass uppercase')
        other=str(ACCOUNTS['other_product'])
        check(api('GET','products/'+other).status in (403,404),'native API entity one cannot read entity two')
        check(api('PUT','products/'+other,dict(label='sızma')).status in (403,404),'native API entity one cannot modify entity two')

        page.goto(BASE+'/index.php')
        page.locator('input[name=username]').fill('maxadmin')
        page.locator('input[name=password]').fill(ACCOUNTS['admin']['password'])
        page.locator('input[name=password]').press('Enter')
        page.wait_for_load_state('networkidle')
        check(page.locator('input[name=password]').count()==0,'native browser login')
        viewports=[]
        for width in (360,390,768):
            page.set_viewport_size(dict(width=width,height=850))
            for route,field in (('/product/card.php?action=create&type=0','label'),('/societe/card.php?action=create','name'),('/custom/maxcore/admin/setup.php','MAXCORE_DISPLAY_NAME')):
                response=page.goto(BASE+route);check(response.status==200,'native form HTTP '+str(width)+' '+field)
                target=page.locator('[name="'+field+'"]')
                target.fill('istanbul ıslak c\u0327ic\u0327ek straße')
                check(target.input_value()=='İSTANBUL ISLAK ÇİÇEK STRASSE','native actual input uppercase '+str(width)+' '+field)
                metrics=page.evaluate('({viewport:innerWidth,body:document.body.scrollWidth,document:document.documentElement.scrollWidth})')
                viewports.append(dict(route=route,width=width,**metrics))
                (RUN/'viewports.json').write_text(json.dumps(viewports,indent=2),encoding='utf-8')
                if metrics['document']>metrics['viewport']:
                    overflow=page.evaluate('Array.from(document.querySelectorAll("body *")).map(e=>({tag:e.tagName,id:e.id,cls:e.className,x:e.getBoundingClientRect().x,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width})).filter(e=>e.right>innerWidth+0.01&&e.width>0).slice(-40)')
                    (RUN/('overflow-'+str(width)+'-'+field+'.json')).write_text(json.dumps(overflow,indent=2),encoding='utf-8')
                check(metrics['body']<=metrics['viewport'] and metrics['document']<=metrics['viewport'],'strict native viewport '+str(width)+' '+field,continue_on_failure=True)
        (RUN/'viewports.json').write_text(json.dumps(viewports,indent=2),encoding='utf-8')

        def token():
            page.goto(BASE+'/product/card.php?action=create&type=0')
            return page.locator('input[name=token]').first.input_value()

        payload=dict(action='add',ref='Post-SkuCase',label='istanbul çiçek',type='0',status='1',status_buy='1',token=token())
        # APIRequestContext shares the real authenticated browser session, but runs no client JS.
        result=ctx.request.post(BASE+'/product/card.php',form=payload)
        check(result.ok and api('GET','products/ref/Post-SkuCase').json()['label']=='İSTANBUL ÇİÇEK','direct native POST bypass persists uppercase')
        payload.update(ref='Query-SkuCase',label='gövde',token=token())
        result=ctx.request.post(BASE+'/product/card.php?'+urlencode(dict(label='sorgu çiçek')),form=payload)
        check(result.ok and api('GET','products/ref/Query-SkuCase').json()['label']=='SORGU ÇİÇEK','native query precedence uppercase on POST')
        payload.update(ref='Get-SkuCase',label='şişli get',token=token())
        result=ctx.request.get(BASE+'/product/card.php',params=payload)
        check(result.ok and api('GET','products/ref/Get-SkuCase').json()['label']=='ŞİŞLİ GET','direct native GET write bypass uppercase')
        payload.update(ref='Csrf-SkuCase',label='csrf çiçek',token='Invalid-Case-Token')
        result=ctx.request.post(BASE+'/product/card.php',form=payload)
        check(api('GET','products/ref/Csrf-SkuCase').status==404,'native CSRF rejection leaves no product')
        payload.update(ref='Post-Overflow',label='ß'*128,token=token())
        result=ctx.request.post(BASE+'/product/card.php',form=payload)
        check(result.status==400 and api('GET','products/ref/Post-Overflow').status==404,'native POST expansion overflow rejected without row')
        # Inspect server persistence rather than treating an HTTP 200 access-denied page as success.
        ctx.clear_cookies();ctx.add_cookies([dict(name=ACCOUNTS['privacy'],value='1',url=BASE)]);page.goto(BASE+'/index.php')
        page.locator('input[name=username]').fill('input-denied');page.locator('input[name=password]').fill(ACCOUNTS['denied']['password']);page.locator('input[name=password]').press('Enter');page.wait_for_load_state('networkidle')
        payload.update(ref='Role-SkuCase',label='izinsiz',token=page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content'))
        ctx.request.post(BASE+'/product/card.php',form=payload)
        check(api('GET','products/ref/Role-SkuCase').status==404,'native web denied user creates no row')
        check(not errors,'native browser no JavaScript errors')
        check(not external,'native browser no external requests')
        browser.close()
        if any(row['status']=='FAIL' for row in CHECKS): raise AssertionError('Native checks failed; strict conditions preserved')


if __name__=='__main__': main()
