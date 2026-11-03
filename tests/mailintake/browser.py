"""Native sessions, mailbox ACLs, CSRF, private downloads and mobile metrics."""
import hashlib
import json
import secrets
import subprocess
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright

BASE='http://127.0.0.1:8080'
DATA=json.loads(Path('.local/mailintake-session.json').read_text())
A=DATA['boxes']['alpha']; B=DATA['boxes']['beta']; T=DATA['targets']
metrics=[]
def check(value,label):
    if not value: raise AssertionError(label)
    print('PASS '+label,flush=True)
def cli(command):
    r=subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','/checks/mailintake/native.php',command],capture_output=True)
    check(r.returncode==0,'Native lifecycle '+command)
def path(name,query=''): return BASE+'/custom/maxmail/'+name+'.php'+query
def token(page):
    value=page.locator('meta[name="anti-csrf-newtoken"]').get_attribute('content')
    assert value,'Native CSRF token present'
    return value
def forbidden(response,label):
    check(response.status==403,label+' status')
    text=response.text()
    check('Synthetic intake 202' not in text and 'sender@source.invalid' not in text and 'Sandbox beta' not in text,label+' response redaction')
def layout(page,route,kind):
    for width in (360,390,768):
        page.set_viewport_size({'width':width,'height':900}); r=page.goto(route); check(r.status==200,'Mobile route '+kind)
        m=page.evaluate('({viewport:innerWidth,doc:document.documentElement.scrollWidth,body:document.body.scrollWidth})')
        check(m['doc']<=m['viewport']+1 and m['body']<=m['viewport']+1,'No horizontal page overflow '+kind+' '+str(width))
        metrics.append({'screen':kind,**m})
        region=page.locator('.maxmail-scroll')
        if region.count():
            check(region.first.get_attribute('tabindex')=='0','Table region keyboard focus')
            region.first.focus(); page.keyboard.press('ArrowRight')

with sync_playwright() as p:
    browser=p.chromium.launch()
    sessions={}
    for role,account in DATA['accounts'].items():
        context=browser.new_context(viewport={'width':390,'height':900})
        context.route('**/*',lambda route: route.continue_() if urlsplit(route.request.url).netloc=='127.0.0.1:8080' else route.abort())
        page=context.new_page(); page.goto(BASE+'/index.php')
        page.locator('input[name="username"]').fill(account['login']); page.locator('input[name="password"]').fill(account['password'])
        page.locator('input[name="password"]').press('Enter'); page.wait_for_load_state('networkidle')
        sessions[role]=(context,page)
    for role in ('denied','no_scope','reader_alpha','download_alpha','operator'):
        context,page=sessions[role]
        forbidden(context.request.get(path('message','?id='+str(T['quarantine']['id']))),role+' cross account HTML')
        forbidden(context.request.get(path('api','?kind=entry&id='+str(T['quarantine']['id']))),role+' cross account API')
        forbidden(context.request.get(path('download','?id='+str(T['quarantine']['id'])+'&file='+str(T['quarantine']['attachment']['id']))),role+' existing private PDF')
        forbidden(context.request.get(path('settings')),role+' admin')
    ca,pa=sessions['reader_alpha']; check(pa.goto(path('index','?account='+str(A))).status==200,'Scoped inbox allowed')
    check('Sandbox beta' not in pa.locator('.maxmail-shell').inner_text(),'Other mailbox not listed')
    cd,pd=sessions['download_alpha']
    normal=T['normal']; url=path('download','?id='+str(normal['id'])+'&file='+str(normal['attachment']['id']))
    r=cd.request.get(url); check(r.status==200 and hashlib.sha256(r.body()).hexdigest()==normal['attachment']['hash'],'Authorized text attachment integrity')
    check(r.headers.get('content-type','').startswith('application/octet-stream') and r.headers.get('x-content-type-options')=='nosniff','Private attachment response headers')
    forbidden(cd.request.get(path('download','?id='+str(normal['id'])+'&file='+str(normal['original']['id']))),'Raw source requires review role')
    cb,pb=sessions['download_beta']; quarantine=T['quarantine']
    forbidden(cb.request.get(path('download','?id='+str(quarantine['id'])+'&file='+str(quarantine['attachment']['id']))),'Quarantine requires review role')
    co,po=sessions['operator']; po.goto(path('index','?account='+str(A)))
    r=co.request.post(path('api'),form={'token':token(po),'action':'poll','account':str(B)})
    forbidden(r,'Cross account poll')
    before=co.request.get(path('api','?account='+str(A))).body()
    r=co.request.post(path('api'),form={'token':'invalid-intake-token','action':'poll','account':str(A)})
    check(r.status==403,'Invalid native CSRF rejected')
    check(co.request.get(path('api','?account='+str(A))).body()==before,'CSRF rejection leaves ledger unchanged')
    po.goto(path('index','?account='+str(A)))
    r=co.request.post(path('api'),form={'token':token(po),'action':'poll','account':str(A)})
    check(r.status==409 and r.json()['result']['transport_error'],'Unverified FETCH not HTTP success')
    cr,pr=sessions['reviewer']; pr.goto(path('message','?id='+str(quarantine['id'])))
    for key in ('attachment','original'):
        f=quarantine[key]; r=cr.request.get(path('download','?id='+str(quarantine['id'])+'&file='+str(f['id'])))
        check(r.status==200 and hashlib.sha256(r.body()).hexdigest()==f['hash'],'Review authorized private '+key)
    pr.locator('input[name="confirm"]').check(); pr.locator('form:has(input[value="acknowledge"]) button').click(); pr.wait_for_load_state('networkidle')
    detail=cr.request.get(path('api','?kind=entry&id='+str(quarantine['id']))).json()
    check(detail['state']=='review_required' and int(detail['reviewed_by'])==DATA['accounts']['reviewer']['id'],'Human acknowledgement preserves quarantine state')
    for key in ('blocked','retry'):
        pr.goto(path('message','?id='+str(T[key]['id'])))
        r=cr.request.post(path('api'),form={'token':token(pr),'action':'retry','id':str(T[key]['id'])})
        check(r.status==409,'Manual review cannot bypass '+key)
    cm,pm=sessions['manager']; pm.goto(path('settings'))
    form=pm.locator('form:has(input[value="account"])')
    values={'label':'Synthetic HTTP only','host':'fakeimap','username':'third@intake.invalid','folder':'INBOX','port':'1143','password':secrets.token_hex(24)}
    for key,value in values.items(): form.locator('[name="'+key+'"]').fill(value)
    form.locator('select').select_option('synthetic'); form.locator('button').click(); pm.wait_for_load_state('networkidle')
    check('Synthetic HTTP only' in pm.locator('.maxmail-shell').inner_text(),'Native admin account form')
    # Revocation takes effect in an existing native session, without logout.
    for enabled in (False,True):
        pm.goto(path('settings'))
        payload={'token':token(pm),'action':'grant','account':str(A),'target_user':str(DATA['accounts']['reader_alpha']['id'])}
        if enabled: payload['scope_view']='yes'
        r=cm.request.post(path('api'),form=payload); check(r.status==200,'Native mailbox scope administration')
        response=ca.request.get(path('api','?account='+str(A)))
        check(response.status==(200 if enabled else 403),'Existing session reflects scope change')
    layout(pa,path('index'),'accounts'); layout(pa,path('index','?account='+str(A)),'inbox')
    layout(pr,path('message','?id='+str(quarantine['id'])),'message'); layout(pm,path('settings'),'settings')
    pm.goto(path('index')); check(pm.locator('.maxmail-shell').inner_text().find('MAX Mail')>=0,'Turkish interface title')
    before=cm.request.get(path('api','?account='+str(A))).body()
    cli('off'); check(cm.request.get(path('api','?account='+str(A))).status==403,'Disabled module denies existing session')
    cli('on'); check(cm.request.get(path('api','?account='+str(A))).body()==before,'Re-enabled module preserves ledger')
    for context,page in sessions.values(): context.close()
    browser.close()
Path('.local/mailintake-layout.json').write_text(json.dumps(metrics,indent=2))
Path('.local/mailintake-summary.json').write_text(json.dumps({'native_sessions':9,'mobile_renders':len(metrics),'mailbox_acl':'passed','csrf':'passed','private_files':'passed','lifecycle':'passed'}))
print('PASS native HTTP security role and mobile acceptance')
