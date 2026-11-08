"""Native owned draft order writes. No validation, shipment, invoice or payment.

Notes/descriptions are rich business text; Incoterms location and line label are
business text. Refs, IDs, dates, prices, quantities, enum keys and tokens are exact.
"""
import json
import os
from pathlib import Path
from urllib.parse import urlencode, urlparse
from playwright.sync_api import sync_playwright

RUN=Path(os.environ['MAX_INPUT_NATIVE_RUN'])
BASE='http://127.0.0.1:'+os.environ['MAX_INPUT_NATIVE_HTTP']
ACCOUNTS=json.loads(Path(os.environ.get('MAX_INPUT_NATIVE_ACCOUNTS',str(RUN/'accounts.json'))).read_text(encoding='utf-8'))
CHECKS_PATH=Path(os.environ.get('MAX_INPUT_NATIVE_CHECKS',str(RUN/'order-write-checks.json')))

TEXT='istanbul ıslak c\u0327ic\u0327ek straße'
UPPER='İSTANBUL ISLAK ÇİÇEK STRASSE'
PROSE=TEXT+' https://Example.invalid/Case?Key=aB'
EXPECTED=UPPER+' https://Example.invalid/Case?Key=aB'
CHECKS=[]


def check(ok,label):
    CHECKS.append(dict(check=label,status='PASS' if ok else 'FAIL'))
    CHECKS_PATH.write_text(json.dumps(CHECKS,indent=2),encoding='utf-8')
    print(('PASS ' if ok else 'FAIL ')+label,flush=True)
    if not ok:raise AssertionError(label)


def launch_browser(pw):
    try:
        options=dict(headless=True,chromium_sandbox=True)
        executable=os.environ.get('MAX_INPUT_BROWSER_EXECUTABLE')
        if executable:options['executable_path']=executable
        return pw.chromium.launch(**options)
    except Exception as error:
        message=str(error).lower()
        categories=(
            ('sandbox_root','running as root without --no-sandbox'),
            ('sandbox_unavailable','no usable sandbox'),
            ('sandbox_namespace','failed to move to new namespace'),
            ('missing_library','error while loading shared libraries'),
            ('permission_denied','permission denied'),
            ('resource_limit','resource temporarily unavailable'),
            ('resource_limit','out of memory'),
            ('executable_missing','executable doesn’t exist'),
            ('executable_missing','executable does not exist'),
        )
        category=next((name for name,marker in categories if marker in message),'browser_closed_unclassified')
        print('FAILBROWSER '+category,flush=True)
        raise


def main():
    owned=[];errors=[];external=[]
    with sync_playwright() as pw:
        browser=launch_browser(pw)
        def login(role):
            ctx=browser.new_context(base_url=BASE)
            def guard(route):
                if urlparse(route.request.url).netloc==urlparse(BASE).netloc:route.continue_()
                else:external.append(route.request.resource_type);route.abort()
            ctx.route('**/*',guard);ctx.add_cookies([dict(name=ACCOUNTS['privacy'],value='1',url=BASE)])
            page=ctx.new_page();page.on('pageerror',lambda e:errors.append(type(e).__name__))
            page.goto(BASE+'/index.php');page.locator('[name=username]').fill(ACCOUNTS[role]['login']);page.locator('[name=password]').fill(ACCOUNTS[role]['password']);page.locator('[name=password]').press('Enter');page.wait_for_load_state('networkidle')
            check(page.locator('[name=password]').count()==0,'order write native login '+role)
            return ctx,page
        admin,page=login('admin');denied,denied_page=login('denied');anonymous=browser.new_context(base_url=BASE)
        def api(method,path='',data=None,role='admin',raw=None):
            headers={'Content-Type':'application/json'}
            if role:headers['DOLAPIKEY']=ACCOUNTS[role]['key']
            request=anonymous.request if role is None else (denied.request if role=='denied' else admin.request)
            return request.fetch(BASE+'/api/index.php/orders'+('/'+str(path) if path!='' else ''),method=method,headers=headers,data=raw if raw is not None else (json.dumps(data,ensure_ascii=False) if data is not None else None))
        def read(identity):
            response=api('GET',identity);check(response.status==200,'order write independent native read');return response.json()
        def header():return dict(socid=ACCOUNTS['fixture_customer'],date=1794009600,ref_ext='OrderWrite-Case',ref_client='Customer-PoCase',note_public=PROSE,note_private=TEXT,location_incoterms=TEXT,fk_incoterms=1)
        def line():return dict(desc=PROSE,label='şişli straße',subprice=12.5,qty=2,tva_tx=0,localtax1_tx=0,localtax2_tx=0,fk_product=0,remise_percent=0,product_type=0,price_base_type='HT',ref_ext='Line-Case',info_bits=0,fk_remise_except=0,date_start='',date_end='',rang=-1,special_code=0,fk_parent_line=0,fk_fournprice=0,pa_ht=0,array_options={},fk_unit=None,origin='',origin_id=0,multicurrency_subprice=0,vat_src_code='',id=0)
        def token(path,identity,action):
            page.goto(BASE+path+'?'+urlencode(dict(id=identity)))
            # Native edit links include their own CSRF parameter even though
            # they only show a form. Follow the supported link, never seed it.
            link=page.locator('a[href*="action='+action+'"]')
            check(link.count()==1,'order write native edit link '+action)
            # Native pencil links can be hidden until hover; navigate their exact
            # native href. The editable form itself is submitted in the browser.
            page.goto(link.get_attribute('href'));page.wait_for_load_state('networkidle')
            return page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')
        def integrity(record):
            return (record['ref'],record['ref_ext'],record['ref_client'],record['socid'],record['date'],record['status'],record['total_ht'],record['total_tva'],record['total_ttc'],record['lines'])
        try:
            for role in (None,'denied'):
                check(api('POST',data=header(),role=role).status in (401,403),'order write API create auth role '+str(role))
            check(api('POST',data=dict(header(),entity=2)).status==403,'order write API foreign entity create denied')
            check(api('POST',data=dict(header(),socid=ACCOUNTS['other_societe'])).status in (403,404),'order write API foreign customer denied')
            response=api('POST',data=dict(header(),lines=[line()]));check(response.ok,'order write API nested create accepted')
            identity=int(response.json());owned.append(identity);saved=read(identity)
            check(saved['note_public']==EXPECTED and saved['note_private']==UPPER and saved['location_incoterms']==UPPER,'order write API header business persistence')
            check(saved['ref_ext']=='OrderWrite-Case' and saved['ref_client']=='Customer-PoCase' and int(saved['status'])==0 and int(saved['socid'])==int(ACCOUNTS['fixture_customer']) and int(saved['date'])==1794009600,'order write API exact refs date customer draft')
            check(len(saved['lines'])==1 and saved['lines'][0]['desc']==EXPECTED and saved['lines'][0]['label']=='ŞİŞLİ STRASSE','order write API nested line business persistence')
            check(float(saved['total_ht'])==25 and float(saved['total_tva'])==0 and float(saved['total_ttc'])==25 and float(saved['lines'][0]['qty'])==2 and saved['lines'][0]['ref_ext']=='Line-Case','order write native totals quantity and opaque line ref')
            fixed=integrity(saved)
            for role in (None,'denied'):
                for method,path,data in [('PUT',str(identity),{'note_public':'izinsiz'}),('POST',str(identity)+'/lines',line()),('PUT',str(identity)+'/lines/'+str(saved['lines'][0]['id']),line()),('DELETE',str(identity),None)]:
                    check(api(method,path,data,role=role).status in (401,403) and read(identity)==saved,'order write API protected role '+str(role)+' '+method+' '+('line' if '/lines' in path else 'header'))
            response=api('PUT',identity,{'note_public':'<p>şişli <a href="https://Example.invalid/Case">çiçek</a></p>','note_private':PROSE})
            saved=read(identity)
            check(response.ok and 'ŞİŞLİ' in saved['note_public'] and 'ÇİÇEK' in saved['note_public'] and 'https://Example.invalid/Case' in saved['note_public'] and saved['note_private']==EXPECTED and integrity(saved)==fixed,'order write API rich header update preserves commercial fields')
            response=api('PUT',identity,raw='{"note_public":"ilk","note_public":"son"}')
            check(response.status==400 and read(identity)==saved,'order write duplicate JSON rejected without mutation')
            for field in ('note_public','note_private'):
                response=api('PUT',identity,{field:'ﬃ'*21846})
                check(response.status==400 and read(identity)==saved,'order write API rich expansion overflow '+field)
            for payload in [dict(header(),location_incoterms='ß'*128),dict(header(),lines=[dict(line(),label='ß'*128)]),dict(header(),lines=[dict(line(),desc='ﬃ'*21846)])]:
                check(api('POST',data=payload).status==400,'order write API create expansion rejected before insert')
            # Native generic Orders PUT does not persist Incoterms: do not invent
            # an API setter. Its supported web set_incoterms action is tested below.
            response=api('POST',str(identity)+'/lines',data=dict(line(),label='ß'*127+'a',ref_ext='Second-LineCase'))
            check(response.ok,'order write API line add accepted');lineid=int(response.json());saved=read(identity)
            added=next(row for row in saved['lines'] if int(row['id'])==lineid)
            check(added['label']=='SS'*127+'A' and added['desc']==EXPECTED and added['ref_ext']=='Second-LineCase' and float(saved['total_ht'])==50,'order write API line boundary and native total')
            for method,path in [('POST',str(identity)+'/lines'),('PUT',str(identity)+'/lines/'+str(lineid))]:
                for field,value in [('label','ß'*128),('desc','ﬃ'*21846)]:
                    response=api(method,path,dict(line(),**{field:value}))
                    check(response.status==400 and read(identity)==saved,'order write API line overflow '+method+' '+field)
            response=api('PUT',str(identity)+'/lines/'+str(lineid),dict(line(),label='güncel çiçek',qty=3,ref_ext='Updated-LineCase'))
            saved=read(identity);updated=next(row for row in saved['lines'] if int(row['id'])==lineid)
            check(response.ok and updated['label']=='GÜNCEL ÇİÇEK' and updated['desc']==EXPECTED and updated['ref_ext']=='Updated-LineCase' and float(updated['qty'])==3 and float(saved['total_ht'])==62.5,'order write native line update normalized totals recomputed')
            fixed=integrity(saved)
            for field in ('note_public','note_private'):
                path='/commande/note.php';tok=token(path,identity,'edit'+field)
                editor=page.locator('textarea[name='+field+']')
                check(editor.count()==1 and editor.is_visible(),'order write native plain note editor '+field)
                editor.fill(PROSE);check(editor.input_value()==EXPECTED,'order write actual browser note uppercase '+field)
                form=page.locator('form:has(textarea[name='+field+'])')
                form.locator('input[type=submit]').first.click();page.wait_for_load_state('networkidle');saved=read(identity)
                check(saved[field]==EXPECTED and integrity(saved)==fixed,'order write browser note persistence and unchanged totals '+field)
                payload=dict(id=identity,action='set'+field,token=token(path,identity,'edit'+field),**{field:PROSE+' şişli'})
                response=admin.request.post(BASE+path,form=payload);saved=read(identity)
                check(response.ok and saved[field]==EXPECTED+' ŞİŞLİ' and integrity(saved)==fixed,'order write JS-less note persistence '+field)
                for bad_token in (None,'InvalidToken'):
                    bad=dict(payload,**{field:'izinsiz'});bad.pop('token',None)
                    if bad_token:bad['token']=bad_token
                    admin.request.post(BASE+path,form=bad)
                    check(read(identity)==saved,'order write note CSRF no mutation '+field+' '+str(bad_token))
                bad=dict(payload,token=denied_page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content'),**{field:'izinsiz'})
                denied.request.post(BASE+path,form=bad);check(read(identity)==saved,'order write denied web note unchanged '+field)
                bad=dict(payload,token=token(path,identity,'edit'+field),**{field:'ﬃ'*21846})
                response=admin.request.post(BASE+path,form=bad);check(response.status==400 and read(identity)==saved,'order write web note expansion overflow '+field)
                payload['token']=token(path,identity,'edit'+field)
                response=admin.request.post(BASE+path+'?'+urlencode({field:'sorgu çiçek'}),form=payload);saved=read(identity)
                check(response.ok and saved[field]=='SORGU ÇİÇEK' and integrity(saved)==fixed,'order write note query precedence '+field)
            path='/commande/card.php';tok=token(path,identity,'editincoterm')
            location=page.locator('[name=location_incoterms]');location.fill(TEXT)
            check(location.input_value()==UPPER,'order write actual browser Incoterms location uppercase')
            payload=dict(id=identity,action='set_incoterms',token=tok,incoterm_id=1,location_incoterms='ß'*127+'a')
            response=admin.request.post(BASE+path,form=payload);saved=read(identity)
            check(response.ok and saved['location_incoterms']=='SS'*127+'A' and int(saved['fk_incoterms'])==1 and integrity(saved)==fixed,'order write Incoterms exact post-conversion boundary and typed code')
            for bad_token in (None,'InvalidToken'):
                bad=dict(payload,location_incoterms='izinsiz');bad.pop('token',None)
                if bad_token:bad['token']=bad_token
                admin.request.post(BASE+path,form=bad)
                check(read(identity)==saved,'order write Incoterms CSRF no mutation '+str(bad_token))
            bad=dict(payload,token=denied_page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content'),location_incoterms='izinsiz')
            denied.request.post(BASE+path,form=bad)
            check(read(identity)==saved,'order write Incoterms denied web no mutation')
            payload.update(token=token(path,identity,'editincoterm'),location_incoterms='ß'*128)
            response=admin.request.post(BASE+path,form=payload)
            check(response.status==400 and read(identity)==saved,'order write Incoterms expansion overflow no mutation')
        finally:
            for identity in reversed(owned):
                record=read(identity)
                check(int(record['status'])==0 and int(record['socid'])==int(ACCOUNTS['fixture_customer']) and record['ref_ext']=='OrderWrite-Case','order write cleanup owned draft customer and ref only')
                check(api('DELETE',identity).status==200 and api('GET',identity).status==404,'order write native owned draft deletion')
        check(not errors and not external,'order write no JavaScript errors or external browser requests')
        check(True,'order write final owned draft cleanup and network checks')
        browser.close()


if __name__=='__main__':main()
