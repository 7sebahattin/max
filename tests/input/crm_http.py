"""Owned synthetic CRM web/API writes; business text uses central MaxInput.

Email, URL, codes, IDs, country/enums and tokens are opaque. No SQL writes,
real customer data, SMTP, or changes to protected commercial fixtures.
"""
import json
from urllib.parse import parse_qs, urlencode, urlparse
from playwright.sync_api import sync_playwright
from wizard_http import RUN, BASE, ACCOUNTS

TEXT='istanbul ıslak c\u0327ic\u0327ek straße'
UPPER='İSTANBUL ISLAK ÇİÇEK STRASSE'
CHECKS=[]


def check(ok,label):
    CHECKS.append(dict(check=label,status='PASS' if ok else 'FAIL'))
    (RUN/'crm-checks.json').write_text(json.dumps(CHECKS,indent=2),encoding='utf-8')
    print(('PASS ' if ok else 'FAIL ')+label,flush=True)
    if not ok:raise AssertionError(label)


def main():
    errors=[];external=[];owned=[]
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True,chromium_sandbox=True)
        def login(role):
            ctx=browser.new_context(base_url=BASE)
            def guard(route):
                if urlparse(route.request.url).netloc==urlparse(BASE).netloc:route.continue_()
                else:external.append(route.request.resource_type);route.abort()
            ctx.route('**/*',guard);ctx.add_cookies([dict(name=ACCOUNTS['privacy'],value='1',url=BASE)])
            page=ctx.new_page();page.on('pageerror',lambda e:errors.append(type(e).__name__))
            page.goto(BASE+'/index.php');page.locator('[name=username]').fill(ACCOUNTS[role]['login']);page.locator('[name=password]').fill(ACCOUNTS[role]['password']);page.locator('[name=password]').press('Enter');page.wait_for_load_state('networkidle')
            check(page.locator('[name=password]').count()==0,'CRM native login '+role)
            return ctx,page
        admin,page=login('admin');denied,denied_page=login('denied')
        anonymous=browser.new_context(base_url=BASE)
        def api(method,path,data=None,role='admin',raw=None,params=None):
            headers={'Content-Type':'application/json'}
            if role:headers['DOLAPIKEY']=ACCOUNTS[role]['key']
            request=anonymous.request if role is None else (denied.request if role=='denied' else admin.request)
            return request.fetch(BASE+'/api/index.php/'+path,method=method,data=raw if raw is not None else (json.dumps(data,ensure_ascii=False) if data is not None else None),headers=headers,params=params)
        def read(resource,identity):
            response=api('GET',resource+'/'+str(identity));check(response.status==200,'CRM independent API read '+resource)
            return response.json()
        def create(resource,data):
            response=api('POST',resource,data);check(response.ok,'CRM native API create '+resource)
            identity=int(response.json());owned.append((resource,identity));return identity
        def company(code):return dict(name=TEXT,name_alias='şişli',address='çiçek sokak',town='izmir',client=1,code_client=code,country_id=1,email='Case.Company@Example.invalid',url='https://Example.invalid/Case?Key=aB',note_public='özel çiçek',note_private='gizli şişli')
        def contact(parent):return dict(socid=parent,firstname='c\u0327ic\u0327ek',lastname='ışık straße',poste='şube yöneticisi',address='çiçek sokak',town='izmir',country_id=1,email='Case.Contact@Example.invalid',note_public='özel çiçek',note_private='gizli şişli')
        def form_token(resource,identity):
            path='/societe/card.php' if resource=='thirdparties' else '/contact/card.php'
            query=dict(action='edit',**({'socid':identity} if resource=='thirdparties' else {'id':identity}))
            page.goto(BASE+path+'?'+urlencode(query))
            form=page.locator('form:has(input[name=action][value=update])')
            check(form.count()==1,'CRM native edit form '+resource)
            return path,form.locator('input[name=token]').first.input_value()
        def webdata(resource,data,identity):
            data=dict(data,action='update')
            if resource=='thirdparties':data.update(socid=identity,customer_code=data.pop('code_client'),status='1')
            else:data.update(id=identity,contactid=identity)
            return data
        try:
            for resource,other_key in [('thirdparties','other_societe'),('contacts','other_contact')]:
                sample=company('Crm-Unauth') if resource=='thirdparties' else contact(ACCOUNTS['fixture_customer'])
                for role in (None,'denied'):
                    response=api('POST',resource,sample,role=role)
                    check(response.status in (401,403),'CRM API create auth/role '+resource+' '+str(role))
                other=str(ACCOUNTS[other_key])
                other_response=api('GET',resource+'/'+other)
                if resource=='contacts':
                    # Native full-permission internal users without MultiCompany
                    # lack this entity boundary. Observe; never claim isolation or
                    # issue an admin write against the protected foreign row.
                    check(other_response.status==200 and int(other_response.json()['entity'])==2,'CRM native contact entity limitation reproduced; isolation BLOCKED')
                    (RUN/'crm-limitations.json').write_text(json.dumps([dict(surface='contacts entity boundary for full-permission internal user without MultiCompany',status='BLOCKED',observed_native_status=200,full_permission_write_attempted=False,delivery_boundary='separate installation and database per customer; two-install acceptance NOT RUN')],indent=2),encoding='utf-8')
                    check(api('PUT',resource+'/'+other,{'lastname':'izinsiz'},role='denied').status==403,'CRM denied role cannot modify protected foreign contact')
                    continue
                check(other_response.status in (403,404),'CRM cross entity API read denied '+resource)
                change={'name':'sızma'} if resource=='thirdparties' else {'lastname':'sızma'}
                check(api('PUT',resource+'/'+other,change).status in (403,404),'CRM cross entity API update denied '+resource)
            cid=create('thirdparties',company('Crm-ApiCase'))
            pid=create('contacts',contact(cid))
            for resource,identity,original in [('thirdparties',cid,company('Crm-ApiCase')),('contacts',pid,contact(cid))]:
                saved=read(resource,identity)
                if resource=='thirdparties':
                    check(saved['name']==UPPER and saved['name_alias']=='ŞİŞLİ','CRM company API business name alias persistence')
                    check(saved['code_client']=='CRM-APICASE','CRM native customer code validator canonicalization')
                    # Societe::update uses native clean_url(), which lowercases
                    # the DNS hostname. Path/query case must remain exact.
                    check(saved['url']=='https://example.invalid/Case?Key=aB','CRM native URL host canonicalization and exact path query')
                else:
                    check(saved['firstname']=='ÇİÇEK' and saved['lastname']=='IŞIK STRASSE' and saved['poste']=='ŞUBE YÖNETİCİSİ' and int(saved['socid'])==cid,'CRM contact API names role parent persistence')
                check(saved['address']=='ÇİÇEK SOKAK' and saved['town']=='İZMİR' and saved['email']==original['email'],'CRM address town email integrity '+resource)
                check(saved['note_public']=='ÖZEL ÇİÇEK' and saved['note_private']=='GİZLİ ŞİŞLİ','CRM public private notes API normalized '+resource)
                rich='<p>şişli <a href="https://Example.invalid/Case">çiçek</a></p>'
                response=api('PUT',resource+'/'+str(identity),{'note_public':rich,'note_private':'yeni özel'})
                saved=read(resource,identity)
                check(response.ok and 'ŞİŞLİ' in saved['note_public'] and 'ÇİÇEK' in saved['note_public'] and 'https://Example.invalid/Case' in saved['note_public'] and saved['note_private']=='YENİ ÖZEL','CRM rich note update exact link '+resource)
                for role in (None,'denied'):
                    response=api('PUT',resource+'/'+str(identity),{'note_private':'izinsiz'},role=role)
                    check(response.status in (401,403) and read(resource,identity)==saved,'CRM API update auth role unchanged '+resource+' '+str(role))
                for field,limit in ([('name',128),('name_alias',128),('town',50),('address',255)] if resource=='thirdparties' else [('firstname',50),('lastname',50),('poste',255),('address',255),('town',64)]):
                    boundary='ß'*(limit//2)+('a' if limit%2 else '')
                    response=api('PUT',resource+'/'+str(identity),{field:boundary})
                    accepted=read(resource,identity)
                    check(response.ok and accepted[field]=='SS'*(limit//2)+('A' if limit%2 else ''),'CRM API exact expansion boundary '+resource+' '+field)
                    response=api('PUT',resource+'/'+str(identity),{field:'ß'*(limit//2+1)})
                    check(response.status==400 and read(resource,identity)==accepted,'CRM API overflow no mutation '+resource+' '+field)
                current=read(resource,identity)
                response=api('PUT',resource+'/'+str(identity),raw='{"note_private":"ilk","note_private":"son"}')
                check(response.status==400 and read(resource,identity)==current,'CRM duplicate JSON key rejects '+resource)
                # Real native web edit page and actual browser input value.
                path,token=form_token(resource,identity)
                field='name' if resource=='thirdparties' else 'firstname'
                page.locator('form:has(input[value=update]) [name='+field+']').fill(TEXT if resource=='thirdparties' else 'çiçek')
                check(page.locator('form:has(input[value=update]) [name='+field+']').input_value()==(UPPER if resource=='thirdparties' else 'ÇİÇEK'),'CRM actual edit input uppercase '+resource)
                payload=webdata(resource,original,identity);payload['token']=token
                response=admin.request.post(BASE+path,form=payload)
                saved=read(resource,identity)
                check(response.ok and saved[field]==(UPPER if resource=='thirdparties' else 'ÇİÇEK') and saved['email']==original['email'],'CRM direct web update server and opaque email '+resource)
                if resource=='contacts':check(saved['note_public']=='ÖZEL ÇİÇEK' and saved['note_private']=='GİZLİ ŞİŞLİ','CRM contact web notes normalized')
                # Query precedence also normalizes the authoritative native GETPOST value.
                path,token=form_token(resource,identity);payload['token']=token
                response=admin.request.post(BASE+path+'?'+urlencode({field:'sorgu çiçek'}),form=payload)
                saved=read(resource,identity)
                check(response.ok and saved[field]=='SORGU ÇİÇEK','CRM web query cannot bypass casing '+resource)
                for bad in (None,'InvalidToken'):
                    rejected=dict(payload);rejected.pop('token',None)
                    if bad:rejected['token']=bad
                    response=admin.request.post(BASE+path,form=rejected)
                    check(read(resource,identity)==saved,'CRM web CSRF leaves object unchanged '+resource+' '+str(bad))
                rejected=dict(payload,token=denied_page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content'))
                denied.request.post(BASE+path,form=rejected)
                check(read(resource,identity)==saved,'CRM denied web update leaves object unchanged '+resource)
                path,token=form_token(resource,identity);overflow=dict(payload,token=token)
                overflow['name' if resource=='thirdparties' else 'firstname']='ß'*(65 if resource=='thirdparties' else 26)
                response=admin.request.post(BASE+path,form=overflow)
                check(response.status==400 and read(resource,identity)==saved,'CRM web post expansion overflow no mutation '+resource)
                if resource=='thirdparties':
                    path,token=form_token(resource,identity);overflow=dict(payload,token=token,town='ß'*26)
                    response=admin.request.post(BASE+path,form=overflow)
                    check(response.status==400 and read(resource,identity)==saved,'CRM web company town expansion overflow no mutation')
            # Native browser create flows; technical codes/email are kept exact.
            page.goto(BASE+'/societe/card.php?action=create')
            form=page.locator('form:has(input[name=action][value=add])');form.locator('[name=name]').fill('web çiçek')
            check(form.locator('[name=name]').input_value()=='WEB ÇİÇEK','CRM browser company create actual uppercase')
            form.locator('select[name=client]').select_option('1')
            code=form.locator('input[name=customer_code],input[name=code_client]');code.fill('Crm-WebCase')
            form.locator('input[type=submit]').first.click();page.wait_for_load_state('networkidle')
            response=api('GET','thirdparties',params={'sqlfilters':"(t.code_client:=:'Crm-WebCase')"})
            rows=response.json() if response.status==200 else []
            check(len(rows)==1,'CRM browser company create persisted');webcid=int(rows[0]['id']);owned.append(('thirdparties',webcid))
            check(rows[0]['name']=='WEB ÇİÇEK' and rows[0]['code_client']=='CRM-WEBCASE','CRM browser company persistence and native canonical code')
            page.goto(BASE+'/contact/card.php?'+urlencode(dict(action='create',socid=webcid)))
            form=page.locator('form:has(input[name=action][value=add])')
            form.locator('[name=lastname]').fill('ışık straße');form.locator('[name=firstname]').fill('çiçek');form.locator('[name=email]').fill('Web.Contact@Example.invalid')
            check(form.locator('[name=lastname]').input_value()=='IŞIK STRASSE' and form.locator('[name=firstname]').input_value()=='ÇİÇEK','CRM browser contact create actual uppercase')
            form.locator('input[type=submit]').first.click();page.wait_for_load_state('networkidle')
            response=api('GET','contacts',params={'thirdparty_ids':str(webcid)});rows=response.json() if response.status==200 else []
            check(len(rows)==1,'CRM browser contact create persisted');webpid=int(rows[0]['id']);owned.append(('contacts',webpid))
            check(rows[0]['lastname']=='IŞIK STRASSE' and rows[0]['firstname']=='ÇİÇEK' and rows[0]['email']=='Web.Contact@Example.invalid','CRM browser contact persistence and email exact')
        finally:
            for resource,identity in sorted(owned,key=lambda row:row[0]!='contacts'):
                response=api('DELETE',resource+'/'+str(identity))
                check(response.status==200 and api('GET',resource+'/'+str(identity)).status==404,'CRM native owned cleanup '+resource)
        check(not errors and not external,'CRM no JavaScript errors or external browser requests')
        check(True,'CRM final owned cleanup and network checks')
        browser.close()


if __name__=='__main__':main()
