"""Unmodified installed import wizard over real HTTP. No SQL or session seeding.

Labels are business text; refs/URL/numbers/enums/CSRF/mapping are opaque.
Uploaded CSV/XLSX bytes are immutable source, never a browser casing target.
"""
import csv
import hashlib
import io
import json
import os
from pathlib import Path
from urllib.parse import parse_qs, urlencode, urlparse
from xml.sax.saxutils import escape
import zipfile
from playwright.sync_api import sync_playwright

RUN=Path(os.environ['MAX_INPUT_NATIVE_RUN'])
BASE='http://127.0.0.1:'+os.environ['MAX_INPUT_NATIVE_HTTP']
ACCOUNTS=json.loads((RUN/'accounts.json').read_text(encoding='utf-8'))
FIELDS=['p.ref','p.label','p.fk_product_type','p.tosell','p.tobuy','p.price_base_type','p.tva_tx','p.price','p.url']
HEADERS=['Ref','Label','Type','OnSell','OnBuy','PriceBaseType','VATRate','SellingPriceHT','PublicUrl']
CHECKS=[]


def check(ok,label):
    CHECKS.append(dict(check=label,status='PASS' if ok else 'FAIL'))
    (RUN/'wizard-checks.json').write_text(json.dumps(CHECKS,indent=2),encoding='utf-8')
    print(('PASS ' if ok else 'FAIL ')+label,flush=True)
    if not ok:raise AssertionError(label)


def source_bytes(fmt,rows,headers=HEADERS):
    rows=[headers]+rows
    if fmt=='csv':
        stream=io.StringIO();csv.writer(stream,lineterminator='\n').writerows(rows)
        return stream.getvalue().encode('utf-8')
    sheet=[]
    for y,row in enumerate(rows,1):
        cells=''.join(f'<c r="{chr(65+x)}{y}" t="inlineStr"><is><t xml:space="preserve">{escape(str(value))}</t></is></c>' for x,value in enumerate(row))
        sheet.append(f'<row r="{y}">{cells}</row>')
    stream=io.BytesIO()
    with zipfile.ZipFile(stream,'w',zipfile.ZIP_DEFLATED) as archive:
        archive.writestr('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>')
        archive.writestr('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>')
        archive.writestr('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Synthetic" sheetId="1" r:id="rId1"/></sheets></workbook>')
        archive.writestr('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>')
        archive.writestr('xl/styles.xml','<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>')
        archive.writestr('xl/worksheets/sheet1.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'+chr(64+len(headers))+str(len(rows))+'"/><sheetData>'+''.join(sheet)+'</sheetData></worksheet>')
    return stream.getvalue()


def row(ref,label):return [ref,label,'0','1','1','HT','0','12.340000','https://example.invalid/Case?Key=aB']


def main():
    uploads={};external=[];errors=[]
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True,chromium_sandbox=True)
        def context(role):
            ctx=browser.new_context(base_url=BASE)
            def guard(route):
                if urlparse(route.request.url).netloc==urlparse(BASE).netloc:route.continue_()
                else:external.append(route.request.resource_type);route.abort()
            ctx.route('**/*',guard)
            ctx.add_cookies([dict(name=ACCOUNTS['privacy'],value='1',url=BASE)])
            page=ctx.new_page();page.on('pageerror',lambda err:errors.append(type(err).__name__))
            page.goto(BASE+'/index.php');page.locator('[name=username]').fill(ACCOUNTS[role]['login']);page.locator('[name=password]').fill(ACCOUNTS[role]['password']);page.locator('[name=password]').press('Enter');page.wait_for_load_state('networkidle')
            check(page.locator('[name=password]').count()==0,'wizard native login '+role)
            return ctx,page
        admin,page=context('admin')
        def product(ref):
            response=admin.request.get(BASE+'/api/index.php/products/ref/'+ref,headers={'DOLAPIKEY':ACCOUNTS['admin']['key']})
            return response.json() if response.status==200 else None
        def token(page):return page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')
        def upload(fmt,name,rows):
            page.goto(BASE+'/imports/import.php?'+urlencode(dict(step=2,datatoimport='produit_1',format=fmt)))
            content=source_bytes(fmt,rows)
            page.locator('input[name=userfile]').set_input_files(dict(name=name,mimeType='text/csv' if fmt=='csv' else 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',buffer=content))
            page.locator('input[name=sendit]').click();page.wait_for_load_state('networkidle')
            links=page.locator('a[href*="step=3"]').evaluate_all('(nodes)=>nodes.map(n=>n.href)')
            found=[link for link in links if parse_qs(urlparse(link).query).get('filetoimport',[''])[0].endswith('-'+name)]
            check(len(found)==1,'wizard native upload '+name)
            params={key:value[0] for key,value in parse_qs(urlparse(found[0]).query).items()}
            filename=params['filetoimport'];stored=RUN/'documents/import/temp'/filename
            check(stored.is_file() and stored.read_bytes()==content,'wizard uploaded source exact '+name)
            uploads[filename]=hashlib.sha256(content).hexdigest()
            response=page.goto(found[0])
            check(response.status==200 and page.locator('meta[name=anti-csrf-newtoken]').count()==1,'wizard native mapping form '+name)
            mapping=admin.request.post(BASE+'/imports/import.php',form=dict(step=3,datatoimport='produit_1',format=fmt,filetoimport=filename,action='saveselectorder',selectorder=','.join(['']+FIELDS),token=token(page)))
            check(mapping.ok and mapping.json()=={},'wizard native mapping '+name)
            params.update(step='4',format=fmt,separator=',',excludefirstline='2',importtriggermode='strict_line')
            response=page.goto(BASE+'/imports/import.php?'+urlencode(params))
            check(response.status==200 and page.locator('form:has(input[name=action][value=launchsimu])').count()==1,'wizard native simulation form '+name)
            return params
        def profile(params,fmt):
            back=dict(params,step='3')
            page.goto(BASE+'/imports/import.php?'+urlencode(back))
            field=page.locator('input[name=import_name]');field.fill('şişli profil '+fmt)
            check(field.input_value()=='ŞİŞLİ PROFİL '+fmt.upper(),'wizard profile actual business value '+fmt)
            response=admin.request.post(BASE+'/imports/import.php',form=dict(back,action='add_import_model',import_name='şişli profil '+fmt,visibility='private',token=token(page)))
            page.goto(BASE+'/imports/import.php?'+urlencode(back))
            check(page.locator('table[summary=selectofimportprofil]').inner_text().count('ŞİŞLİ PROFİL '+fmt.upper())==1,'wizard profile direct POST normalized and saved '+fmt)
            response=admin.request.post(BASE+'/imports/import.php',form=dict(back,action='add_import_model',import_name='ß'*26,visibility='private',token=token(page)))
            check(response.status==400,'wizard profile post-conversion overflow rejected '+fmt)
            page.goto(BASE+'/imports/import.php?'+urlencode(params))
        def simulate(params,name,success=True):
            form=page.locator('form:has(input[name=action][value=launchsimu])')
            form.locator('input[type=submit]').click();page.wait_for_load_state('networkidle')
            link=page.locator('a.butAction[href*="step=5"]')
            check((link.count()==1)==success,'wizard simulation gate '+name)
            return link.get_attribute('href') if success else None
        for fmt in ('csv','xlsx'):
            ref='Wizard-'+fmt+'-Case';name='success-'+fmt+'.'+fmt
            params=upload(fmt,name,[row(ref,'istanbul ıslak c\u0327ic\u0327ek straße')])
            profile(params,fmt)
            link=simulate(params,name)
            check(product(ref) is None,'wizard simulation rolls back '+fmt)
            # A genuine step-5 URL with no action/token must not become a CSRF write.
            forged=admin.request.get(BASE+link if link.startswith('/') else BASE+'/imports/'+link)
            check(product(ref) is None,'wizard tokenless GET cannot commit '+fmt)
            check(forged.status==403,'wizard tokenless GET rejected '+fmt)
            for method,bad_token in (('GET','Invalid-Case-Token'),('POST','Invalid-Case-Token'),('POST',None)):
                bad=dict(params,step='5')
                if bad_token is not None:bad['token']=bad_token
                response=admin.request.get(BASE+'/imports/import.php',params=bad) if method=='GET' else admin.request.post(BASE+'/imports/import.php?'+urlencode(dict(step=5,datatoimport='produit_1',format=fmt,filetoimport=params['filetoimport'])),form=bad)
                check(response.status==403 and product(ref) is None,'wizard '+('missing' if bad_token is None else 'invalid')+' token '+method+' cannot commit '+fmt)
            before_files=set((RUN/'documents/import/temp').iterdir())
            content=source_bytes(fmt,[row('CsrfUpload-'+fmt,'izinsiz')])
            response=admin.request.post(BASE+'/imports/import.php?'+urlencode(dict(step=2,datatoimport='produit_1',format=fmt)),multipart=dict(token='Invalid-Case-Token',sendit='1',userfile=dict(name='csrf-upload.'+fmt,mimeType='application/octet-stream',buffer=content)))
            check(set((RUN/'documents/import/temp').iterdir())==before_files,'wizard invalid upload CSRF leaves no source '+fmt)
            page.goto(BASE+'/imports/import.php?'+urlencode(params));link=simulate(params,name)
            with page.expect_request(lambda req:req.is_navigation_request() and urlparse(req.url).path=='/imports/import.php') as navigation:
                page.locator('a.butAction[href*="step=5"]').click()
            page.wait_for_load_state('networkidle')
            check(navigation.value.method=='POST' and 'token' not in parse_qs(urlparse(navigation.value.url).query),'wizard native commit link uses token POST '+fmt)
            saved=product(ref)
            check(saved is not None and saved['label']=='İSTANBUL ISLAK ÇİÇEK STRASSE','wizard commit normalizes '+fmt)
            check(page.locator('[data-max-input-import=rejected]').count()==0,'wizard successful batch has no rejection notice '+fmt)
            check(saved['ref']==ref and saved['url']=='https://example.invalid/Case?Key=aB' and float(saved['price'])==12.34,'wizard technical values preserved '+fmt)
            duplicate=upload(fmt,'duplicate-'+fmt+'.'+fmt,[row('Pending-'+fmt,'geçici'),row(ref,'çakışma')])
            simulate(duplicate,'duplicate-'+fmt,success=False)
            check(product('Pending-'+fmt) is None,'wizard duplicate simulation rollback '+fmt)
            # Bypass the UI gate and exercise the real native transaction rollback.
            response=admin.request.post(BASE+'/imports/import.php',form=dict(duplicate,step='5',token=token(page)))
            check(product('Pending-'+fmt) is None and product(ref)['label']=='İSTANBUL ISLAK ÇİÇEK STRASSE','wizard duplicate batch rollback '+fmt)
            check('data-max-input-import="rejected"' in response.text() and 'No records from this batch were saved' in response.text(),'wizard duplicate reports batch rollback '+fmt)
            overflow=upload(fmt,'overflow-'+fmt+'.'+fmt,[row('Overflow-'+fmt,'ß'*128)])
            simulate(overflow,'overflow-'+fmt,success=False)
            response=admin.request.post(BASE+'/imports/import.php',form=dict(overflow,step='5',token=token(page)))
            check(product('Overflow-'+fmt) is None and 'data-max-input-import="rejected"' in response.text(),'wizard overflow rejects without truncation '+fmt)
        denied_targets={fmt:upload(fmt,'role-'+fmt+'.'+fmt,[row('Denied-'+fmt,'izinsiz')]) for fmt in ('csv','xlsx')}
        denied,denied_page=context('denied')
        denied_token=token(denied_page)
        before_files=set((RUN/'documents/import/temp').iterdir())
        for fmt in ('csv','xlsx'):
            content=source_bytes(fmt,[row('Denied-'+fmt,'izinsiz')])
            response=denied.request.post(BASE+'/imports/import.php',multipart=dict(step='2',datatoimport='produit_1',format=fmt,token=denied_token,sendit='1',userfile=dict(name='denied.'+fmt,mimeType='application/octet-stream',buffer=content)))
            check('Access denied' in response.text() and set((RUN/'documents/import/temp').iterdir())==before_files,'wizard denied role cannot upload '+fmt)
            response=denied.request.post(BASE+'/imports/import.php',form=dict(denied_targets[fmt],step='5',token=denied_token))
            check('Access denied' in response.text() and product('Denied-'+fmt) is None,'wizard denied role cannot import '+fmt)
        for filename,expected in uploads.items():
            check(hashlib.sha256((RUN/'documents/import/temp'/filename).read_bytes()).hexdigest()==expected,'wizard retained source hash '+filename.split('-',1)[-1])
        (RUN/'wizard-source-hashes.json').write_text(json.dumps(uploads,indent=2),encoding='utf-8')
        check(not errors,'wizard no JavaScript errors')
        check(not external,'wizard no external browser requests')
        check(True,'wizard final source integrity and network checks')
        browser.close()


if __name__=='__main__':main()
