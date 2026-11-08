"""Real sandboxed Chromium, synthetic forms only. Not installed Dolibarr acceptance."""
import json
import os
from pathlib import Path
import sys
from playwright.sync_api import sync_playwright

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'.local';OUT.mkdir(exist_ok=True)
registry=json.loads((ROOT/'modules/maxcore/input-fields.json').read_text())
script=(ROOT/'modules/maxcore/js/input.js').read_text()
checks=[];viewports=[]


def check(value,label):
    if not value:raise AssertionError(label)
    checks.append(dict(check=label,status='PASS'))


def run():
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path=os.environ.get('MAX_INPUT_BROWSER_EXECUTABLE','/usr/bin/chromium'),headless=True,chromium_sandbox=True)
        context=browser.new_context()
        requests=[];errors=[]
        context.route('**/*',lambda route:(requests.append(route.request.resource_type),route.abort())[1])
        page=context.new_page();page.on('pageerror',lambda error:errors.append(type(error).__name__))
        surfaces=[('societe/card.php','name'),('product/card.php','label'),('custom/maxcore/admin/setup.php','MAXCORE_DISPLAY_NAME'),('custom/maxcustoms/index.php','office'),('custom/maxmail/settings.php','label')]
        for width in [360,390,768]:
            page.set_viewport_size(dict(width=width,height=850))
            for route,field in surfaces:
                page.goto('about:blank')
                config=json.dumps(dict(locale='tr-TR',fields=registry['web'][route],facts=False))
                page.set_content('<!doctype html><html><head><meta charset="utf-8"><style>*{box-sizing:border-box}body{margin:0;padding:12px;width:100%}label{display:block}input,textarea,button{width:100%;max-width:100%;min-height:44px}</style></head><body><form><label>İş metni<input name="'+field+'"></label><input name="password" type="password" value="CaseSecret"><input name="email" type="email" value="User@Example.com"><input name="token" type="hidden" value="CaseToken"><button>Kaydet</button></form><script id="max-input-config" type="application/json">'+config+'</script></body></html>')
                page.add_script_tag(content=script)
                node=page.locator('[name="'+field+'"]')
                node.fill('istanbul ıslak çiçek')
                check(node.input_value()=='İSTANBUL ISLAK ÇİÇEK','actual uppercase '+route+' '+str(width))
                check(page.locator('[name=password]').input_value()=='CaseSecret' and page.locator('[name=email]').input_value()=='User@Example.com' and page.locator('[name=token]').input_value()=='CaseToken','technical fields exact')
                node.evaluate("n=>{n.value='şi';n.dispatchEvent(new CompositionEvent('compositionstart',{bubbles:true}));n.dispatchEvent(new InputEvent('input',{bubbles:true,isComposing:true}));}")
                check(node.input_value()=='şi','IME draft preserved')
                node.evaluate("n=>n.dispatchEvent(new CompositionEvent('compositionend',{bubbles:true}))")
                check(node.input_value()=='Şİ','IME completion normalized')
                node.evaluate("n=>{n.value='straße';n.setSelectionRange(5,5);n.dispatchEvent(new InputEvent('input',{bubbles:true,inputType:'insertFromPaste'}));}")
                check(node.input_value()=='STRASSE' and node.evaluate('n=>n.selectionStart')==6,'paste expansion and caret preserved')
                metrics=page.evaluate('({width:innerWidth,body:document.body.scrollWidth,document:document.documentElement.scrollWidth})')
                viewports.append(dict(surface=route,**metrics))
                check(metrics['width']==width and max(metrics['body'],metrics['document'])<=width,'strict synthetic form viewport')
        node=page.locator('[name=label]')
        page.evaluate("document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();window.captured=Object.fromEntries(new FormData(e.target));});")
        node.evaluate("n=>{n.value='çiçek';n.form.requestSubmit();}")
        check(page.evaluate('captured.label')=='ÇİÇEK' and page.evaluate('captured.token')=='CaseToken','submit capture normalizes scripted input without touching CSRF')
        node.evaluate("n=>{n.value='ß'.repeat(41);n.dispatchEvent(new InputEvent('input',{bubbles:true}));}")
        check(not node.evaluate('n=>n.checkValidity()'),'client validates length after expansion without truncation')
        check(len(viewports)==15 and len({(v['surface'],v['width']) for v in viewports})==15,'exact 15-case synthetic form matrix')
        check(page.evaluate("MAXInput.text('ad User@Example.com https://x.invalid/Case')")=='AD User@Example.com https://x.invalid/Case','client protected spans')
        check(page.evaluate("MAXInput.text('a'.repeat(65535))==='A'.repeat(65535)"),'long client text remains usable')
        rich=page.evaluate('MAXInput.html(\'<p title="Case">çiçek <a href="https://example.invalid/Case">istanbul</a><code>Case()</code><img src="https://example.invalid/NoFetch"></p>\')')
        check('ÇİÇEK' in rich and 'İSTANBUL' in rich and 'https://example.invalid/Case' in rich and '<code>Case()</code>' in rich,'client rich text and technical markup')
        check(not requests,'inert normalization performs no external requests')
        check(not errors,'no JS errors')
        context.close();browser.close()


try:
    run()
except Exception as error:
    # No raw values, DOM, secrets or exception payload enter the report.
    (OUT/'input-browser.json').write_text(json.dumps(dict(passed=False,error_type=type(error).__name__,checks=checks,viewports=viewports,scope='synthetic_form_only')))
    print('FAIL local input browser '+type(error).__name__,file=sys.stderr)
    raise SystemExit(1) from None
(OUT/'input-browser.json').write_text(json.dumps(dict(passed=True,checks=checks,viewports=viewports,scope='synthetic_form_only',installed_native='NOT RUN'),indent=2))
print('PASS '+str(len(checks))+' browser checks; 15 strict synthetic viewports')
