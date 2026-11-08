"""Native empty-warehouse web/API acceptance. Never moves or adjusts stock.

Label/location/address/town/description are business text; IDs, parent, status,
country, postal code, phone, barcode and tokens are opaque/typed.
"""
import json
from urllib.parse import urlencode, urlparse
from playwright.sync_api import sync_playwright
from wizard_http import RUN, BASE, ACCOUNTS

TEXT='depo ıslak c\u0327ic\u0327ek straße'
UPPER='DEPO ISLAK ÇİÇEK STRASSE'
CHECKS=[]


def check(ok,label):
    CHECKS.append(dict(check=label,status='PASS' if ok else 'FAIL'))
    (RUN/'warehouse-checks.json').write_text(json.dumps(CHECKS,indent=2),encoding='utf-8')
    print(('PASS ' if ok else 'FAIL ')+label,flush=True)
    if not ok:raise AssertionError(label)


def main():
    owned=[];errors=[];external=[]
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
            check(page.locator('[name=password]').count()==0,'warehouse native login '+role)
            return ctx,page
        admin,page=login('admin');denied,denied_page=login('denied');anonymous=browser.new_context(base_url=BASE)
        def api(method,path='',data=None,role='admin',raw=None,params=None):
            headers={'Content-Type':'application/json'}
            if role:headers['DOLAPIKEY']=ACCOUNTS[role]['key']
            request=anonymous.request if role is None else (denied.request if role=='denied' else admin.request)
            return request.fetch(BASE+'/api/index.php/warehouses'+('/'+str(path) if path!='' else ''),method=method,headers=headers,data=raw if raw is not None else (json.dumps(data,ensure_ascii=False) if data is not None else None),params=params)
        def read(identity):
            response=api('GET',identity);check(response.status==200,'warehouse independent native read');return response.json()
        def lookup(label):
            response=api('GET',params={'sqlfilters':"(t.ref:=:'"+label+"')"})
            if response.status==404:return None
            check(response.status==200,'warehouse independent native label lookup');rows=response.json()
            check(len(rows)<=1,'warehouse unique native label lookup');return rows[0] if rows else None
        def data(label=TEXT):return dict(label=label,lieu='şişli raf',description='özel çiçek https://Example.invalid/Case?Key=aB',address='çiçek sokak',town='izmir',zip='Tr-Case',phone='0900123456',fax='0900654321',country_id=1,statut=1,fk_parent=0,warehouse_usage=1,barcode='Case-Barcode')
        def token(identity):
            page.goto(BASE+'/product/stock/card.php?'+urlencode(dict(id=identity,action='edit')))
            form=page.locator('form:has(input[name=action][value=update])')
            check(form.count()==1,'warehouse native edit form');return form.locator('input[name=token]').first.input_value()
        def webdata(identity):
            payload=data();payload['libelle']=payload.pop('label');payload['desc']=payload.pop('description');payload['zipcode']=payload.pop('zip');payload.update(id=identity,action='update',token=token(identity));return payload
        try:
            check(lookup(UPPER) is None,'warehouse API label absent before creation')
            for role in (None,'denied'):
                check(api('POST',data=data('WH-DENIED'),role=role).status in (401,403),'warehouse API create auth role '+str(role))
            check(lookup('WH-DENIED') is None,'warehouse denied creates leave no row')
            other=ACCOUNTS['other_warehouse']
            for method in ('GET','PUT','DELETE'):
                response=api(method,other,{'label':'sızma'} if method=='PUT' else None)
                check(response.status in (403,404),'warehouse foreign entity '+method+' denied')
            response=api('POST',data=data());check(response.ok,'warehouse API create accepted');identity=int(response.json());owned.append(identity)
            saved=read(identity)
            check(saved['label']==UPPER and saved['lieu']=='ŞİŞLİ RAF' and saved['town']=='İZMİR' and saved['address']=='ÇİÇEK SOKAK','warehouse API business fields normalized')
            check(saved['description']=='ÖZEL ÇİÇEK https://Example.invalid/Case?Key=aB','warehouse API description and exact URI')
            check(saved['zip']=='Tr-Case' and saved['phone']=='0900123456' and saved['barcode']=='Case-Barcode' and int(saved['statut'])==1 and int(saved['fk_parent'] or 0)==0,'warehouse typed opaque values exact')
            for role in (None,'denied'):
                check(api('PUT',identity,{'lieu':'izinsiz'},role=role).status in (401,403) and read(identity)==saved,'warehouse API update auth role unchanged '+str(role))
                check(api('DELETE',identity,role=role).status in (401,403) and read(identity)==saved,'warehouse API delete auth role unchanged '+str(role))
            for field,limit in [('label',255),('lieu',64),('town',50),('address',255)]:
                boundary='ß'*(limit//2)+('a' if limit%2 else '')
                response=api('PUT',identity,{field:boundary});accepted=read(identity)
                check(response.ok and accepted[field]=='SS'*(limit//2)+('A' if limit%2 else ''),'warehouse API exact post-conversion boundary '+field)
                response=api('PUT',identity,{field:'ß'*(limit//2+1)})
                check(response.status==400 and read(identity)==accepted,'warehouse API expansion overflow no mutation '+field)
                bad=data('WH-REJECT-'+field.upper());bad[field]='ß'*(limit//2+1)
                response=api('POST',data=bad)
                check(response.status==400,'warehouse API create expansion overflow '+field)
            response=api('PUT',identity,{'label':TEXT,'lieu':'istanbul raf','description':'<p>şişli <a href="https://Example.invalid/Case">çiçek</a></p>'})
            saved=read(identity)
            check(response.ok and saved['label']==UPPER and saved['lieu']=='İSTANBUL RAF' and 'ŞİŞLİ' in saved['description'] and 'ÇİÇEK' in saved['description'] and 'https://Example.invalid/Case' in saved['description'],'warehouse API rich update normalized with exact link')
            response=api('PUT',identity,raw='{"lieu":"ilk","lieu":"son"}')
            check(response.status==400 and read(identity)==saved,'warehouse duplicate JSON keys reject')
            check(api('PUT',identity,{'ref':'Opaque-Case'}).status==400 and read(identity)==saved,'warehouse native deprecated ref update rejected')
            response=api('POST',data=data('DEPO ISLAK ÇİÇEK STRASSE'))
            check(not response.ok and read(identity)==saved,'warehouse uppercase uniqueness collision never merges')
            payload=webdata(identity)
            form=page.locator('form:has(input[name=action][value=update])')
            for name,value,expected in [('libelle',TEXT,UPPER),('lieu','şişli raf','ŞİŞLİ RAF'),('town','izmir','İZMİR')]:
                form.locator('[name='+name+']').fill(value);check(form.locator('[name='+name+']').input_value()==expected,'warehouse actual browser edit uppercase '+name)
            response=admin.request.post(BASE+'/product/stock/card.php',form=payload);saved=read(identity)
            check(response.ok and saved['label']==UPPER and saved['lieu']=='ŞİŞLİ RAF' and saved['town']=='İZMİR' and saved['zip']=='Tr-Case','warehouse direct web update server persistence')
            payload['token']=token(identity)
            response=admin.request.post(BASE+'/product/stock/card.php?'+urlencode(dict(lieu='sorgu çiçek')),form=payload);saved=read(identity)
            check(response.ok and saved['lieu']=='SORGU ÇİÇEK','warehouse query precedence cannot bypass casing')
            for bad_token in (None,'InvalidToken'):
                rejected=dict(payload);rejected.pop('token',None)
                if bad_token:rejected['token']=bad_token
                admin.request.post(BASE+'/product/stock/card.php',form=rejected)
                check(read(identity)==saved,'warehouse web CSRF no mutation '+str(bad_token))
            denied.request.post(BASE+'/product/stock/card.php',form=dict(payload,token=denied_page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')))
            check(read(identity)==saved,'warehouse denied web update no mutation')
            for field,limit in [('libelle',255),('lieu',64),('town',50),('address',255)]:
                bad=dict(payload,token=token(identity));bad[field]='ß'*(limit//2+1)
                response=admin.request.post(BASE+'/product/stock/card.php',form=bad)
                check(response.status==400 and read(identity)==saved,'warehouse web expansion overflow no mutation '+field)
            overflow='ﬃ'*21846
            response=api('PUT',identity,{'description':overflow})
            check(response.status==400 and read(identity)==saved,'warehouse API description expansion overflow')
            bad=dict(payload,token=token(identity),desc=overflow);response=admin.request.post(BASE+'/product/stock/card.php',form=bad)
            check(response.status==400 and read(identity)==saved,'warehouse web description expansion overflow')
            # Real native create form. No stock/lot/movement endpoint is called.
            web_label='WEB DEPO ÇİÇEK';check(lookup(web_label) is None,'warehouse browser label absent before creation')
            page.goto(BASE+'/product/stock/card.php?action=create');form=page.locator('form:has(input[name=action][value=add])')
            for name,value,expected in [('libelle','web depo çiçek',web_label),('lieu','şişli','ŞİŞLİ'),('town','izmir','İZMİR')]:
                form.locator('[name='+name+']').fill(value);check(form.locator('[name='+name+']').input_value()==expected,'warehouse browser create uppercase '+name)
            form.locator('[name=zipcode]').fill('Tr-Web');form.locator('input[type=submit]').first.click();page.wait_for_load_state('networkidle')
            created=lookup(web_label);check(created is not None,'warehouse browser create persisted');owned.append(int(created['id']))
            created=read(owned[-1]);check(created['label']==web_label and created['lieu']=='ŞİŞLİ' and created['town']=='İZMİR' and created['zip']=='Tr-Web','warehouse browser persistence and opaque zip')
        finally:
            for identity in reversed(owned):
                record=read(identity);check(int(record['entity'])==1 and identity!=int(ACCOUNTS['other_warehouse']),'warehouse cleanup owned current entity ID only')
                products=api('GET',str(identity)+'/products',params={'includestockdata':1})
                check(products.status==404 or (products.status==200 and products.json()==[]),'warehouse cleanup requires no stored products')
                response=api('DELETE',identity);check(response.status==200 and api('GET',identity).status==404,'warehouse native owned empty cleanup')
        check(not errors and not external,'warehouse no JavaScript errors or external browser requests')
        check(True,'warehouse final owned empty cleanup and network checks')
        browser.close()


if __name__=='__main__':main()
