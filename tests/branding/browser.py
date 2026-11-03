"""Two independent synthetic installs. HTTP upload/role/CSRF and native surfaces."""
import io
import json
import os
from pathlib import Path
import struct
import subprocess
import zlib
from playwright.sync_api import sync_playwright

BASE='http://127.0.0.1:8080'
VARIANT=os.environ['BRANDING_VARIANT']
NAME='North Demo' if VARIANT=='a' else 'South Demo'
OTHER='South Demo' if VARIANT=='a' else 'North Demo'
accounts=json.loads(Path('.local/maxcore-accounts.json').read_text())
accounts['owner']=dict(login='maxcore-test',password=Path('.local/maxcore-admin').read_text())
secrets=[x['password'] for x in accounts.values()]
checks=[]

def png(width=32,height=32):
    def chunk(kind,data):
        return struct.pack('!I',len(data))+kind+data+struct.pack('!I',zlib.crc32(kind+data)&0xffffffff)
    color=b'\x22\x88\xcc' if VARIANT=='a' else b'\xcc\x88\x22'
    return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',width,height,8,2,0,0,0))+chunk(b'IDAT',zlib.compress((b'\x00'+color*width)*height))+chunk(b'IEND',b'')

def login(browser,account):
    c=browser.new_context()
    c.route('**/*',lambda r:r.continue_() if r.request.url.startswith(BASE+'/') else r.abort())
    page=c.new_page(); page.goto(BASE+'/')
    page.locator('[name=username]').fill(account['login']); page.locator('[name=password]').fill(account['password'])
    page.locator('input[type=submit],button[type=submit]').first.click(); page.wait_for_load_state('networkidle')
    assert not page.locator('[name=password]').count(), 'Native login failed'
    return c,page

def post(c,page,data,upload=None,token=None):
    page.goto(BASE+'/custom/maxcore/admin/setup.php')
    value=token if token is not None else page.locator('[name=token]').input_value()
    payload={'token':value,'MAXCORE_DISPLAY_NAME':NAME,'MAXCORE_LOGO':'max.svg','MAXCORE_FAVICON':'logo',**data}
    if upload is not None:
        payload['brand_logo']={'name':upload[0],'mimeType':upload[1],'buffer':upload[2]}
    return c.request.post(BASE+'/custom/maxcore/admin/setup.php',multipart=payload,max_redirects=0)

def verify_state():
    subprocess.run(['docker','compose','exec','-T','web','php','/checks/branding/fixture.php','verify'],check=True)

def run():
    with sync_playwright() as p:
        browser=p.chromium.launch()
        c,page=login(browser,accounts['owner'])
        # Record native/global surfaces before change (synthetic only).
        page.goto(BASE+'/')
        page.screenshot(path=f'.local/branding-{VARIANT}-before.png',full_page=True)
        response=post(c,page,{},('brand.png','image/png',png()+b'<?php inert_payload ?>'))
        assert response.status==303, 'Valid upload did not save'
        page.goto(BASE+'/custom/maxcore/admin/setup.php')
        saved=page.locator('[name=MAXCORE_LOGO]').input_value()
        assert saved.startswith('upload:') and len(saved)==75, 'Sanitized asset token missing'
        asset=c.request.get(BASE+'/custom/maxcore/asset.php?kind=logo')
        assert asset.status==200 and asset.headers['content-type']=='image/png'
        assert asset.headers['x-content-type-options']=='nosniff' and b'inert_payload' not in asset.body(), 'Active tail or metadata survived re-encoding'
        assert asset.body().startswith(b'\x89PNG') and b'<svg' not in asset.body(), 'Unsafe image served'
        # Every claimed authenticated surface: own heading, native header/menu/title/favicon.
        for path in ['/', '/societe/list.php','/custom/maxcore/index.php','/custom/maxcore/admin/setup.php']:
            response=page.goto(BASE+path+'?lang=tr_TR')
            assert response.status==200
            assert NAME in page.title(), 'Brand absent from browser title'
            assert NAME in page.locator('.maxcore-global-brand').inner_text(), 'Brand absent from native header'
            assert page.locator('.maxcore-global-brand img').get_attribute('src').startswith('/custom/maxcore/asset.php')
            assert 'kind=favicon' in page.locator('link[rel="shortcut icon"]').get_attribute('href')
            assert page.locator('a[href*="/maxcore/index.php"]').filter(has_text=NAME).count()>=1, 'Owned menu brand absent'
            assert OTHER not in page.locator('body').inner_text(), 'Other installation brand leaked'
            checks.append({'surface':path,'status':response.status,'brand':NAME})
        # Mobile own pages must retain original nine strict viewport checks.
        for width in [360,390,768]:
            page.set_viewport_size(dict(width=width,height=900))
            for path in ['/custom/maxcore/index.php','/custom/maxcore/admin/setup.php','/custom/maxcore/admin/status.php']:
                page.goto(BASE+path+'?lang=tr_TR'); page.evaluate('document.fonts.ready')
                dims=page.evaluate('({body:document.body.scrollWidth,document:document.documentElement.scrollWidth,viewport:innerWidth})')
                page.screenshot(path=f'.local/branding-{VARIANT}-{width}-{path.split("/")[-1]}.png',full_page=True)
                assert dims['body']<=width and dims['document']<=width and dims['viewport']==width, 'Brand caused mobile page overflow'
        for name,mime,body in [('unsafe.svg','image/svg+xml',b'<svg onload="alert(1)"/>'),
                               ('fake.png','image/png',b'not a PNG'),('unsafe.php','image/png',png()),
                               ('large.png','image/png',b'X'*1048577),('wide.png','image/png',png(2049,1))]:
            response=post(c,page,{},(name,mime,body))
            assert response.status==400, 'Unsafe upload was accepted: '+name
            page.goto(BASE+'/custom/maxcore/admin/setup.php')
            assert page.locator('[name=MAXCORE_LOGO]').input_value()==saved, 'Rejected upload changed logo'
        response=post(c,page,{'MAXCORE_DISPLAY_NAME':'Forbidden'},('brand.png','image/png',png()),token='invalid')
        assert response.status==403 and 'MAXCORE_DISPLAY_NAME' not in response.text(), 'CSRF response leaked settings'
        page.goto(BASE+'/custom/maxcore/admin/setup.php')
        assert page.locator('[name=MAXCORE_DISPLAY_NAME]').input_value()==NAME
        c.close()
        for role in ['denied','reader']:
            c,page=login(browser,accounts[role])
            page.goto(BASE+'/')
            token=page.locator('meta[name=anti-csrf-newtoken]').get_attribute('content')
            reply=c.request.post(BASE+'/custom/maxcore/admin/setup.php',multipart={'token':token,'MAXCORE_DISPLAY_NAME':'Forbidden','brand_logo':{'name':'brand.png','mimeType':'image/png','buffer':png()}})
            assert reply.status==403 and 'MAXCORE_DISPLAY_NAME' not in reply.text(), 'Role upload denial failed'
            c.close()
        # Authorized configure role can upload through the same native role/CSRF path.
        c,page=login(browser,accounts['editor'])
        assert post(c,page,{},('editor.jpg','image/jpeg',Path('.local/branding-fixture.jpg').read_bytes())).status==303
        assert c.request.get(BASE+'/custom/maxcore/asset.php?kind=logo').body().startswith(b'\x89PNG'), 'JPEG was not normalized to PNG'
        c.close()
        c=browser.new_context(); page=c.new_page()
        page.goto(BASE+'/')
        assert NAME in page.title() and 'Dolibarr' in page.title(), 'Login brand/native version marker missing'
        assert NAME in page.locator('.maxcore-login-brand').inner_text()
        assert 'kind=favicon' in page.locator('link[rel="shortcut icon"]').get_attribute('href')
        page.screenshot(path=f'.local/branding-{VARIANT}-login.png',full_page=True)
        assert c.request.get(BASE+'/custom/maxcore/asset.php?kind=../../conf').status==404
        assert c.request.get(BASE+'/custom/maxcore/asset.php?kind=logo&loginfunction=1&entity=2').status==404
        assert c.request.get(BASE+'/custom/maxcore/asset.php?kind=logo&file=conf.php').status==404
        assert c.request.post(BASE+'/custom/maxcore/asset.php?kind=logo').status==405
        browser.close()
    verify_state()
    Path(f'.local/branding-{VARIANT}-report.json').write_text(json.dumps({'variant':VARIANT,'brand':NAME,'surfaces':checks,'status':'pass'},indent=2))
    print('PASS isolated brand '+VARIANT+': upload sanitation, native title/menu/favicon/header/login, roles/CSRF, nine mobile checks, legal isolation')

try:
    run()
except Exception as error:
    message=str(error)
    for secret in secrets: message=message.replace(secret,'[redacted]')
    raise SystemExit(message) from None
