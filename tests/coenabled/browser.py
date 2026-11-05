"""Shared Core/Mobile hooks on the same populated native pages, both brands."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys
from playwright.sync_api import sync_playwright

BASE='http://127.0.0.1:8080'
PAGES=['societe/list.php','product/list.php','commande/list.php','expedition/list.php','compta/facture/list.php']
OUT=Path('.local/coenabled-evidence'); measurements=[]; errors=[]; role_checks=[]
def need(value,label):
    if not value: raise AssertionError(label)
def save(): (OUT/'native-layout.json').write_text(json.dumps(measurements,indent=2))
def cli(mode): subprocess.run(['docker','compose','exec','-T','--user','www-data','web','php','/checks/mobile/runtime.php',mode],check=True,stdout=subprocess.DEVNULL)
def login(browser,account):
    context=browser.new_context(viewport={'width':390,'height':900})
    context.route('**/*',lambda r:r.continue_() if r.request.url.startswith(BASE+'/') else r.abort())
    page=context.new_page(); page.on('pageerror',lambda e:errors.append({'kind':'javascript','hash':hashlib.sha256(str(e).encode()).hexdigest()}))
    page.on('console',lambda m:errors.append({'kind':'console','hash':hashlib.sha256(m.text.encode()).hexdigest()}) if m.type=='error' and not m.text.startswith('Failed to load resource: the server responded with a status of 403') else None)
    page.on('response',lambda r:errors.append({'kind':'asset','status':r.status}) if r.status>=400 and r.request.resource_type in ('image','font','stylesheet','script') else None)
    page.goto(BASE+'/'); page.locator('[name=username]').fill(account['login']); page.locator('[name=password]').fill(account['password']); page.locator('[type=submit]').first.click(); page.wait_for_load_state('networkidle')
    need(not page.locator('[name=password]').count(),'Native login'); return context,page

def set_brand(page,name):
    page.goto(BASE+'/custom/maxcore/admin/setup.php?lang=tr_TR')
    page.locator('[name=MAXCORE_DISPLAY_NAME]').fill(name); page.locator('[name=MAXCORE_FAVICON]').select_option('logo')
    page.locator('[name=brand_logo]').set_input_files({'name':'synthetic.jpg','mimeType':'image/jpeg','buffer':Path('.local/coenabled-logo.jpg').read_bytes()+b'INERT_COENABLED_TAIL'})
    with page.expect_response(lambda r:'/custom/maxcore/admin/setup.php' in r.url and r.request.method=='POST') as saved: page.locator('button[type=submit]').click()
    page.wait_for_load_state('networkidle'); need(saved.value.status==303,'Raster brand upload')

def hooks(page,name):
    need(name in page.title(),'Shared title brand')
    need(page.locator('#id-top .maxcore-global-brand').count()==1,'Single top brand injection')
    need(page.locator('.maxcore-content-brand .maxcore-global-brand').count()==1,'Single mobile brand injection')
    need(name in page.locator('.maxcore-content-brand').inner_text(),'Visible mobile brand')
    need(page.locator('.maxcore-content-brand img').evaluate('(n)=>n.complete && n.naturalWidth>0'),'Uploaded logo decoded')
    need(page.locator('link[href*="/maxcore/css/branding.css"]').count()==1,'Core shared header hook')
    need(page.locator('link[href*="/maxmobile/css/mobile.css"]').count()==1 and page.locator('script[src*="/maxmobile/js/mobile.js"]').count()==1,'Mobile shared header hook')
    need(page.locator('body.maxmobile-page').count()==1,'Mobile body hook')
    for module in ('maxcore','maxorders','maxcustoms','maxmail'): need(page.locator('a[href*="/'+module+'/index.php"]').count()>=1,'Retained MAX menus '+module)
    need('kind=favicon' in page.locator('link[rel="shortcut icon"]').get_attribute('href'),'Central favicon hook')

def measure(page,width,path,phase):
    page.evaluate('document.fonts.ready')
    row=page.evaluate('({width:innerWidth,document:document.documentElement.scrollWidth,body:document.body.scrollWidth})');row.update(surface=path,phase=phase)
    measurements.append(row); save()
    page.screenshot(path=str(OUT/('native-'+phase+'-'+str(width)+'-'+path.replace('/','-')+'.png')),full_page=True)
    if phase=='after': need(row['width']==width and max(row['body'],row['document'])<=width,'STRICT native document overflow')

def main():
    with sync_playwright() as p:
        browser=p.chromium.launch(); c,page=login(browser,{'login':'maxadmin','password':Path('.local/p2-admin-secret').read_text().strip()})
        set_brand(page,'Coenabled North'); logo=c.request.get(BASE+'/custom/maxcore/asset.php?kind=logo')
        need(logo.status==200 and logo.body().startswith(b'\x89PNG') and b'INERT_COENABLED_TAIL' not in logo.body(),'Raster normalization removed tail')
        need(c.request.post(BASE+'/custom/maxcore/asset.php?kind=logo').status==405,'Artwork GET only')
        need(c.request.get(BASE+'/custom/maxcore/asset.php?kind=favicon').body()==logo.body(),'Central favicon artwork')
        cli('off')
        for width in (360,390,768):
            page.set_viewport_size({'width':width,'height':900})
            for path in PAGES:
                need(page.goto(BASE+'/'+path+'?lang=tr_TR').status==200,'Native baseline page'); measure(page,width,path,'before')
        cli('on'); set_brand(page,'Coenabled South')
        for width in (360,390,768):
            page.set_viewport_size({'width':width,'height':900})
            for path in PAGES:
                need(page.goto(BASE+'/'+path+'?lang=tr_TR').status==200,'Native coenabled page'); page.locator('.maxmobile-table-scroll').first.wait_for(); hooks(page,'Coenabled South')
                region=page.locator('.maxmobile-table-scroll').first
                need(region.get_attribute('role')=='region' and region.get_attribute('aria-label'),'Table local accessible scroll')
                region.focus(); need(region.evaluate('(n)=>n===document.activeElement'),'Keyboard table focus')
                region.press('End'); need(region.evaluate('(n)=>n.scrollLeft+n.clientWidth>=n.scrollWidth-1'),'Last columns reachable')
                region.press('Home'); need(region.evaluate('(n)=>n.scrollLeft')==0,'First columns reachable')
                field=region.locator('input[name^=search_]:visible').first; need(field.count()>0,'Native filters retained'); field.focus(); need(field.evaluate('(n)=>n===document.activeElement'),'Keyboard filter focus')
                search=page.locator('button.button_search[name=button_search_x]').first; need(search.count()>0,'Native filter action');search.click(trial=True)
                selection=region.locator('[type=checkbox][name="toselect[]"]')
                if selection.count(): selection.first.check();need(selection.first.is_checked(),'Pointer row selection');selection.first.uncheck()
                measure(page,width,path,'after')
        page.goto(BASE+'/product/list.php?lang=tr_TR');field=page.locator('[name=search_ref]');field.fill('P2-SKU');field.press('Enter');page.wait_for_load_state('networkidle')
        need(page.locator('table.liste a').filter(has_text='P2-CARTON').count()==0,'Filter applies')
        link=page.locator('table.liste a').filter(has_text='P2-SKU').first;need(link.count()>0,'Filtered product exists');link.focus();link.press('Enter');page.wait_for_load_state('networkidle');need('/product/card.php' in page.url,'Keyboard card navigation');need(not page.locator('body.maxmobile-page').count(),'Mobile hook stays on supported lists')
        c.close()
        accounts=json.loads(Path('.local/mobile-accounts.json').read_text())
        for role,a in accounts.items():
            c,page=login(browser,a)
            for path in PAGES:
                r=page.goto(BASE+'/'+path+'?lang=tr_TR')
                if role=='reader':need(r.status==200 and page.locator('table.liste').count()>0,'Native read permission retained')
                else:
                    need(r.status in (200,403) and page.locator('table.liste').count()==0 and not page.locator('.maxmobile-table-scroll').count(),'Native denied user has no list content')
                    denial=page.locator('div.error').first.inner_text(); need('Access denied' in denial or 'Erişim' in denial,'Native denial body oracle')
                need('Fatal error' not in r.text() and 'Parse error' not in r.text(),'Native role page has no runtime error')
                role_checks.append({'role':role,'surface':path,'status':r.status,'denial':role=='denied'})
            c.close()
        guest=browser.new_context();page=guest.new_page();page.goto(BASE+'/');need('Coenabled South' in page.title() and '@ 24.0.1' in page.title(),'Login product and native version preserved');need('Dolibarr 24.0.1' in page.locator('.maxcore-origin').inner_text(),'Upstream origin retained');guest.close()
        browser.close()
    (OUT/'native-browser.json').write_text(json.dumps({'passed':not errors,'errors':errors,'strict_checks':15,'baseline_checks':15,'brand_changes':2,'hooks_same_page':True,'roles':role_checks},indent=2));need(not errors,'No JavaScript or missing asset failures')
    print('PASS shared native hooks two brands assets roles and 15 STRICT viewport cases')

if __name__=='__main__':
    try: main()
    except BaseException as e:
        trace=e.__traceback__; locations=[]
        while trace: locations.append({'file':Path(trace.tb_frame.f_code.co_filename).name,'line':trace.tb_lineno});trace=trace.tb_next
        (OUT/'native-failure.json').write_text(json.dumps({'type':type(e).__name__,'locations':locations,'errors':errors},indent=2));save();print('FAIL native shared browser; sanitized failure locations saved',file=sys.stderr);raise SystemExit(1) from None
