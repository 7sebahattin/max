"""Native societe_1 / societe_2 HTTP wizard, synthetic CSV/XLSX only.

Business fields use the central registry; IDs/codes/email/URI and raw uploads
are opaque/immutable. No direct SQL or session mapping seeding is used.
"""
import hashlib
import json
from urllib.parse import parse_qs, urlencode, urlparse
from playwright.sync_api import sync_playwright
from wizard_http import RUN, BASE, ACCOUNTS, source_bytes

CHECKS=[]
COMPANY=['s.nom','s.status','s.client','s.fournisseur','s.code_client','s.code_fournisseur','s.name_alias','s.address','s.town','s.email','s.url','s.note_public','s.note_private']
CONTACT=['s.rowid','s.fk_soc','s.lastname','s.firstname','s.address','s.town','s.poste','s.email','s.note_public','s.note_private']


def check(ok,label):
    CHECKS.append(dict(check=label,status='PASS' if ok else 'FAIL'))
    (RUN/'people-checks.json').write_text(json.dumps(CHECKS,indent=2),encoding='utf-8')
    print(('PASS ' if ok else 'FAIL ')+label,flush=True)
    if not ok:raise AssertionError(label)


def main():
    hashes={};external=[];errors=[]
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True,chromium_sandbox=True)
        def login(role):
            ctx=browser.new_context(base_url=BASE)
            def guard(route):
                if route.request.url.startswith(BASE+'/'):route.continue_()
                else:external.append(route.request.resource_type);route.abort()
            ctx.route('**/*',guard);ctx.add_cookies([dict(name=ACCOUNTS['privacy'],value='1',url=BASE)])
            page=ctx.new_page();page.on('pageerror',lambda err:errors.append(type(err).__name__))
            page.goto(BASE+'/index.php');page.locator('[name=username]').fill(ACCOUNTS[role]['login']);page.locator('[name=password]').fill(ACCOUNTS[role]['password']);page.locator('[name=password]').press('Enter');page.wait_for_load_state('networkidle')
            check(page.locator('[name=password]').count()==0,'people native login '+role)
            return ctx,page
        admin,page=login('admin');denied,denied_page=login('denied')
        def token(p):return p.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')
        def api(path,params=None):
            response=admin.request.get(BASE+'/api/index.php/'+path,params=params,headers={'DOLAPIKEY':ACCOUNTS['admin']['key']})
            if response.status==404:return None
            check(response.status==200,'people native read API')
            return response.json()
        def read(dataset,key):
            if dataset=='societe_2':return api('contacts/'+str(key))
            rows=api('thirdparties',{'sqlfilters':"(t.code_client:=:'"+str(key)+"')"})
            check(rows is None or len(rows)<=1,'people unique native customer lookup')
            return rows[0] if rows else None
        def make(dataset,key,label=None,first=None):
            email='Case.'+str(key)+'@Example.invalid'
            if dataset=='societe_1':
                return [label or 'istanbul ıslak c\u0327ic\u0327ek straße','1','1','1',str(key),'Sup-'+str(key),'şişli','şişli sokak','izmir',email,'https://example.invalid/Case?Key=aB','özel çiçek','gizli şişli']
            return [str(key),'id:'+str(ACCOUNTS['fixture_customer']),label or 'ışık straße',first or 'c\u0327ic\u0327ek','şişli sokak','izmir','şube yöneticisi',email,'özel çiçek','gizli şişli']
        def upload(dataset,fmt,name,rows):
            fields=COMPANY if dataset=='societe_1' else CONTACT
            content=source_bytes(fmt,rows,headers=fields)
            response=page.goto(BASE+'/imports/import.php?'+urlencode(dict(step=2,datatoimport=dataset,format=fmt)))
            check(response.status==200 and page.locator('input[name=userfile]').count()==1,'people upload form '+name)
            page.locator('input[name=userfile]').set_input_files(dict(name=name,mimeType='text/csv' if fmt=='csv' else 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',buffer=content))
            page.locator('[name=sendit]').click();page.wait_for_load_state('networkidle')
            links=page.locator('a[href*="step=3"]').evaluate_all('(nodes)=>nodes.map(n=>n.href)')
            found=[url for url in links if parse_qs(urlparse(url).query).get('filetoimport',[''])[0].endswith('-'+name)]
            check(len(found)==1,'people actual upload '+name)
            params={key:val[0] for key,val in parse_qs(urlparse(found[0]).query).items()}
            filename=params['filetoimport'];stored=RUN/'documents/import/temp'/filename
            check(stored.is_file() and stored.read_bytes()==content,'people raw upload exact '+name)
            hashes[filename]=hashlib.sha256(content).hexdigest()
            response=page.goto(found[0]);check(response.status==200 and page.locator('meta[name=anti-csrf-newtoken]').count()==1,'people mapping form '+name)
            response=admin.request.post(BASE+'/imports/import.php',form=dict(step=3,datatoimport=dataset,format=fmt,filetoimport=filename,action='saveselectorder',selectorder=','.join(['']+fields),token=token(page)))
            check(response.ok and response.json()=={},'people native mapping '+name)
            params.update(step='4',format=fmt,separator=',',excludefirstline='2',importtriggermode='strict_line')
            response=page.goto(BASE+'/imports/import.php?'+urlencode(params));check(response.status==200 and page.locator('form:has(input[value=launchsimu])').count()==1,'people simulation form '+name)
            return params
        def simulate(label,success):
            page.locator('form:has(input[value=launchsimu]) input[type=submit]').click();page.wait_for_load_state('networkidle')
            link=page.locator('a.butAction[href*="step=5"]')
            check((link.count()==1)==success,'people simulation gate '+label)
        for dataset in ('societe_1','societe_2'):
            for index,fmt in enumerate(('csv','xlsx')):
                label=dataset+'-'+fmt
                key='People-'+fmt+'-Case' if dataset=='societe_1' else 100100+index
                success_rows=[make(dataset,key)]
                boundary=100110+index
                if dataset=='societe_2':success_rows.append(make(dataset,boundary,'ß'*25,'ß'*25))
                params=upload(dataset,fmt,label+'.'+fmt,success_rows)
                simulate(label,True);check(read(dataset,key) is None,'people simulation rollback '+label)
                for method,bad_token in (('GET',None),('GET','InvalidToken'),('POST','InvalidToken'),('POST',None)):
                    bad=dict(params,step='5')
                    if bad_token is not None:bad['token']=bad_token
                    response=admin.request.get(BASE+'/imports/import.php',params=bad) if method=='GET' else admin.request.post(BASE+'/imports/import.php?'+urlencode(dict(step=5,datatoimport=dataset,format=fmt,filetoimport=params['filetoimport'])),form=bad)
                    check(response.status==403 and read(dataset,key) is None,'people rejected '+method+' '+str(bad_token)+' '+label)
                page.goto(BASE+'/imports/import.php?'+urlencode(params));simulate(label,True)
                page.locator('a.butAction[href*="step=5"]').click();page.wait_for_load_state('networkidle')
                saved=read(dataset,key);check(saved is not None,'people native committed '+label)
                if dataset=='societe_1':
                    check(saved['name']=='İSTANBUL ISLAK ÇİÇEK STRASSE' and saved['name_alias']=='ŞİŞLİ','people company Unicode NFC expansion '+fmt)
                    check(saved['code_client']==key and saved['code_fournisseur']=='Sup-'+key and saved['url']=='https://example.invalid/Case?Key=aB' and int(saved['client'])==1 and int(saved['fournisseur'])==1,'people customer supplier opaque values '+fmt)
                else:
                    check(saved['lastname']=='IŞIK STRASSE' and saved['firstname']=='ÇİÇEK' and saved['poste']=='ŞUBE YÖNETİCİSİ','people contact business names and function '+fmt)
                    check(int(saved['id'])==key and int(saved['socid'])==int(ACCOUNTS['fixture_customer']),'people contact ID and native company association '+fmt)
                    accepted=read(dataset,boundary);check(accepted is not None and accepted['lastname']=='SS'*25 and accepted['firstname']=='SS'*25,'people exact 50 character native boundary '+fmt)
                    response=admin.request.put(BASE+'/api/index.php/contacts/'+str(key),data=json.dumps({'firstname':'ß'*26}),headers={'DOLAPIKEY':ACCOUNTS['admin']['key'],'Content-Type':'application/json'})
                    check(response.status==400 and read(dataset,key)==saved,'people contact API expansion guard leaves record unchanged '+fmt)
                check(saved['address']=='ŞİŞLİ SOKAK' and saved['town']=='İZMİR' and saved['note_public']=='ÖZEL ÇİÇEK' and saved['note_private']=='GİZLİ ŞİŞLİ','people address and notes normalized '+label)
                check(saved['email']=='Case.'+str(key)+'@Example.invalid','people email case exact '+label)
                pending='Pending-'+fmt if dataset=='societe_1' else 100200+index
                bad=upload(dataset,fmt,'duplicate-'+label+'.'+fmt,[make(dataset,pending),make(dataset,key,'çakışma')])
                simulate('duplicate-'+label,False);check(read(dataset,pending) is None,'people duplicate simulation rollback '+label)
                response=admin.request.post(BASE+'/imports/import.php',form=dict(bad,step='5',token=token(page)))
                check(response.status==200 and 'data-max-input-import="rejected"' in response.text() and read(dataset,pending) is None and read(dataset,key)==saved,'people duplicate batch rollback no merge '+label)
                for column in ('name',) if dataset=='societe_1' else ('lastname','firstname'):
                    badkey='Overflow-'+fmt if dataset=='societe_1' else 100300+index+(10 if column=='firstname' else 0)
                    limit=128 if dataset=='societe_1' else 50
                    overflow='ß'*(limit//2+1)
                    rows=[make(dataset,badkey,overflow)] if column!='firstname' else [make(dataset,badkey,first=overflow)]
                    bad=upload(dataset,fmt,'overflow-'+column+'-'+label+'.'+fmt,rows);simulate('overflow-'+column+'-'+label,False)
                    response=admin.request.post(BASE+'/imports/import.php',form=dict(bad,step='5',token=token(page)))
                    check(response.status==200 and 'data-max-input-import="rejected"' in response.text() and read(dataset,badkey) is None,'people post expansion overflow '+column+' '+label)
                deniedkey='Denied-'+fmt if dataset=='societe_1' else 100400+index
                denied_params=upload(dataset,fmt,'denied-'+label+'.'+fmt,[make(dataset,deniedkey)])
                before=set((RUN/'documents/import/temp').iterdir());content=source_bytes(fmt,[make(dataset,deniedkey)],headers=COMPANY if dataset=='societe_1' else CONTACT)
                response=denied.request.post(BASE+'/imports/import.php',multipart=dict(step='2',datatoimport=dataset,format=fmt,token=token(denied_page),sendit='1',userfile=dict(name='denied-upload.'+fmt,mimeType='application/octet-stream',buffer=content)))
                check('Access denied' in response.text() and set((RUN/'documents/import/temp').iterdir())==before,'people denied upload '+label)
                response=denied.request.post(BASE+'/imports/import.php',form=dict(denied_params,step='5',token=token(denied_page)))
                check('Access denied' in response.text() and read(dataset,deniedkey) is None,'people denied import '+label)
                response=admin.request.post(BASE+'/imports/import.php?'+urlencode(dict(step=2,datatoimport=dataset,format=fmt)),multipart=dict(token='InvalidToken',sendit='1',userfile=dict(name='csrf-upload.'+fmt,mimeType='application/octet-stream',buffer=content)))
                check(set((RUN/'documents/import/temp').iterdir())==before,'people invalid upload CSRF '+label)
        for filename,expected in hashes.items():
            check(hashlib.sha256((RUN/'documents/import/temp'/filename).read_bytes()).hexdigest()==expected,'people source hash '+filename.split('-',1)[-1])
        (RUN/'people-source-hashes.json').write_text(json.dumps(hashes,indent=2),encoding='utf-8')
        check(not errors and not external,'people final source integrity and network checks')
        browser.close()


if __name__=='__main__':main()
