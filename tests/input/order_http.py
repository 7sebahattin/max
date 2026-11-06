"""Native order header/line HTTP import acceptance, synthetic draft orders only.

Notes/descriptions are business text. References, links, dates, quantities,
prices, enums and mapping are opaque/typed; source uploads remain exact.
No SQL/session seeding; owned drafts are removed through the native API.
"""
import hashlib
import json
from urllib.parse import parse_qs, quote, urlencode, urlparse
from playwright.sync_api import sync_playwright
from wizard_http import RUN, BASE, ACCOUNTS, source_bytes

CHECKS=[]
HEADER=['c.ref','c.fk_soc','c.date_commande','c.fk_statut','c.ref_client','c.note_public','c.note_private']
LINE=['cd.fk_commande','cd.description','cd.qty','cd.subprice','cd.tva_tx','cd.product_type','cd.total_ht','cd.total_tva','cd.total_ttc']
TEXT='istanbul ıslak c\u0327ic\u0327ek straße https://Example.invalid/Case?Key=aB'
EXPECTED='İSTANBUL ISLAK ÇİÇEK STRASSE https://Example.invalid/Case?Key=aB'


def check(ok,label):
    CHECKS.append(dict(check=label,status='PASS' if ok else 'FAIL'))
    (RUN/'order-checks.json').write_text(json.dumps(CHECKS,indent=2),encoding='utf-8')
    print(('PASS ' if ok else 'FAIL ')+label,flush=True)
    if not ok:raise AssertionError(label)


def main():
    hashes={};external=[];errors=[];owned_refs=[]
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True,chromium_sandbox=True)
        def login(role):
            ctx=browser.new_context(base_url=BASE)
            def guard(route):
                if urlparse(route.request.url).netloc==urlparse(BASE).netloc:route.continue_()
                else:external.append(route.request.resource_type);route.abort()
            ctx.route('**/*',guard);ctx.add_cookies([dict(name=ACCOUNTS['privacy'],value='1',url=BASE)])
            page=ctx.new_page();page.on('pageerror',lambda err:errors.append(type(err).__name__))
            page.goto(BASE+'/index.php');page.locator('[name=username]').fill(ACCOUNTS[role]['login']);page.locator('[name=password]').fill(ACCOUNTS[role]['password']);page.locator('[name=password]').press('Enter');page.wait_for_load_state('networkidle')
            check(page.locator('[name=password]').count()==0,'orders native login '+role)
            return ctx,page
        admin,page=login('admin');denied,denied_page=login('denied')
        def token(p):return p.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')
        def read(ref):
            response=admin.request.get(BASE+'/api/index.php/orders/ref/'+quote(ref,safe=''),headers={'DOLAPIKEY':ACCOUNTS['admin']['key']})
            if response.status==404:return None
            check(response.status==200,'orders independent native read')
            return response.json()
        def header(ref,public=TEXT,private='şişli özel'):
            return [ref,'id:'+str(ACCOUNTS['fixture_customer']),'2026-11-06','0','Client-Case',public,private]
        def line(ref,text=TEXT):return [ref,text,'2','12.340000','0','0','24.680000','0','24.680000']
        def upload(dataset,fmt,name,rows):
            fields=HEADER if dataset=='commande_1' else LINE
            content=source_bytes(fmt,rows,headers=fields)
            response=page.goto(BASE+'/imports/import.php?'+urlencode(dict(step=2,datatoimport=dataset,format=fmt)))
            check(response.status==200 and page.locator('input[name=userfile]').count()==1,'orders upload form '+name)
            page.locator('input[name=userfile]').set_input_files(dict(name=name,mimeType='text/csv' if fmt=='csv' else 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',buffer=content))
            page.locator('[name=sendit]').click();page.wait_for_load_state('networkidle')
            links=page.locator('a[href*="step=3"]').evaluate_all('(nodes)=>nodes.map(n=>n.href)')
            found=[url for url in links if parse_qs(urlparse(url).query).get('filetoimport',[''])[0].endswith('-'+name)]
            check(len(found)==1,'orders actual upload '+name)
            params={key:val[0] for key,val in parse_qs(urlparse(found[0]).query).items()}
            filename=params['filetoimport'];stored=RUN/'documents/import/temp'/filename
            check(stored.is_file() and stored.read_bytes()==content,'orders raw source exact '+name)
            hashes[filename]=hashlib.sha256(content).hexdigest()
            page.goto(found[0])
            response=admin.request.post(BASE+'/imports/import.php',form=dict(step=3,datatoimport=dataset,format=fmt,filetoimport=filename,action='saveselectorder',selectorder=','.join(['']+fields),token=token(page)))
            check(response.ok and response.json()=={},'orders native mapping '+name)
            params.update(step='4',format=fmt,separator=',',excludefirstline='2',importtriggermode='strict_line')
            response=page.goto(BASE+'/imports/import.php?'+urlencode(params))
            check(response.status==200 and page.locator('form:has(input[value=launchsimu])').count()==1,'orders simulation form '+name)
            return params
        def simulate(label,success):
            page.locator('form:has(input[value=launchsimu]) input[type=submit]').click();page.wait_for_load_state('networkidle')
            check((page.locator('a.butAction[href*="step=5"]').count()==1)==success,'orders simulation gate '+label)
        try:
            for fmt in ('csv','xlsx'):
                ref='OrderHttp-'+fmt+'-Case';owned_refs.append(ref)
                for dataset in ('commande_1','commande_lines_2'):
                    is_line=dataset=='commande_lines_2';label=dataset+'-'+fmt
                    before=read(ref)
                    params=upload(dataset,fmt,label+'.'+fmt,[line(ref) if is_line else header(ref)])
                    simulate(label,True)
                    check(read(ref)==before,'orders simulation leaves parent and lines unchanged '+label)
                    for method,bad_token in (('GET',None),('GET','InvalidToken'),('POST',None),('POST','InvalidToken')):
                        bad=dict(params,step='5')
                        if bad_token is not None:bad['token']=bad_token
                        response=admin.request.get(BASE+'/imports/import.php',params=bad) if method=='GET' else admin.request.post(BASE+'/imports/import.php',form=bad)
                        check(response.status==403 and read(ref)==before,'orders CSRF '+method+' '+str(bad_token)+' '+label)
                    page.goto(BASE+'/imports/import.php?'+urlencode(params));simulate(label,True)
                    with page.expect_request(lambda req:req.is_navigation_request() and urlparse(req.url).path=='/imports/import.php') as navigation:
                        page.locator('a.butAction[href*="step=5"]').click()
                    page.wait_for_load_state('networkidle')
                    check(navigation.value.method=='POST' and 'token' not in parse_qs(urlparse(navigation.value.url).query),'orders real commit uses token POST '+label)
                    saved=read(ref);check(saved is not None,'orders commit persisted '+label)
                    if is_line:
                        rows=saved['lines'];check(len(rows)==1 and rows[0]['desc']==EXPECTED,'orders line Unicode NFC and URI exact '+fmt)
                        check(float(rows[0]['qty'])==2 and float(rows[0]['subprice'])==12.34 and float(rows[0]['total_ht'])==24.68,'orders line typed amounts exact '+fmt)
                    else:
                        check(saved['note_public']==EXPECTED and saved['note_private']=='ŞİŞLİ ÖZEL','orders header notes normalized '+fmt)
                        check(saved['ref']==ref and saved['ref_client']=='Client-Case' and int(saved['socid'])==int(ACCOUNTS['fixture_customer']) and int(saved['statut'])==0,'orders reference company and draft exact '+fmt)
                    check(page.locator('[data-max-input-import=rejected]').count()==0,'orders successful batch no rejection '+label)
                    if not is_line:
                        pending='OrderHttp-Pending-'+fmt
                        bad=upload(dataset,fmt,'duplicate-'+label+'.'+fmt,[header(pending),header(ref,'çakışma')]);simulate('duplicate-'+label,False)
                        response=admin.request.post(BASE+'/imports/import.php',form=dict(bad,step='5',token=token(page)))
                        check('data-max-input-import="rejected"' in response.text() and read(pending) is None and read(ref)==saved,'orders duplicate batch rollback without merge '+fmt)
                    for column in (('description',) if is_line else ('public','private')):
                        # 21846 source characters fit XLSX's 32767-character cell
                        # limit, then expand to 65538 business characters (FFI).
                        overflow='ﬃ'*21846
                        pending='OrderHttp-Overflow-'+fmt
                        rows=[line(ref,'geçici'),line(ref,overflow)] if is_line else [header(pending,overflow if column=='public' else TEXT,overflow if column=='private' else 'şişli')]
                        bad=upload(dataset,fmt,'overflow-'+column+'-'+label+'.'+fmt,rows);simulate('overflow-'+column+'-'+label,False)
                        response=admin.request.post(BASE+'/imports/import.php',form=dict(bad,step='5',token=token(page)))
                        check('data-max-input-import="rejected"' in response.text() and read(ref)==saved and (is_line or read(pending) is None),'orders expansion overflow and pending rollback '+column+' '+label)
                    role_ref='OrderHttp-Denied-'+fmt
                    rows=[line(ref,'izinsiz')] if is_line else [header(role_ref)]
                    role=upload(dataset,fmt,'role-'+label+'.'+fmt,rows)
                    before_files=set((RUN/'documents/import/temp').iterdir());content=source_bytes(fmt,rows,headers=LINE if is_line else HEADER)
                    response=denied.request.post(BASE+'/imports/import.php',multipart=dict(step='2',datatoimport=dataset,format=fmt,token=token(denied_page),sendit='1',userfile=dict(name='denied.'+fmt,mimeType='application/octet-stream',buffer=content)))
                    check('Access denied' in response.text() and set((RUN/'documents/import/temp').iterdir())==before_files,'orders denied upload '+label)
                    response=denied.request.post(BASE+'/imports/import.php',form=dict(role,step='5',token=token(denied_page)))
                    check('Access denied' in response.text() and read(ref)==saved and (is_line or read(role_ref) is None),'orders denied import '+label)
                    admin.request.post(BASE+'/imports/import.php?'+urlencode(dict(step=2,datatoimport=dataset,format=fmt)),multipart=dict(token='InvalidToken',sendit='1',userfile=dict(name='csrf.'+fmt,mimeType='application/octet-stream',buffer=content)))
                    check(set((RUN/'documents/import/temp').iterdir())==before_files,'orders invalid upload CSRF '+label)
        finally:
            for ref in owned_refs:
                saved=read(ref)
                if saved is None:continue
                check(ref.startswith('OrderHttp-') and saved['ref']==ref and int(saved['socid'])==int(ACCOUNTS['fixture_customer']) and int(saved['statut'])==0,'orders cleanup owned synthetic draft only')
                response=admin.request.delete(BASE+'/api/index.php/orders/'+str(saved['id']),headers={'DOLAPIKEY':ACCOUNTS['admin']['key']})
                check(response.status==200 and read(ref) is None,'orders native API removes draft and lines '+ref)
        for filename,expected in hashes.items():
            check(hashlib.sha256((RUN/'documents/import/temp'/filename).read_bytes()).hexdigest()==expected,'orders retained source hash '+filename.split('-',1)[-1])
        (RUN/'order-source-hashes.json').write_text(json.dumps(hashes,indent=2),encoding='utf-8')
        check(not errors and not external,'orders no JavaScript errors or external browser requests')
        check(True,'orders final source integrity network and owned draft cleanup')
        browser.close()


if __name__=='__main__':main()
