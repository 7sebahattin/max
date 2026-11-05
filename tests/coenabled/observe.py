"""Add error observation around unchanged merged browser scripts; never weaken assertions."""
import contextlib
import hashlib
import io
import json
from pathlib import Path
import re
import runpy
import shutil
import sys
from urllib.parse import urlsplit
from playwright.sync_api import Browser

ALLOWED={'core':'tests/maxcore/browser.py','orders':'tests/orders/browser.py','customs':'tests/customs/browser.py','mail':'tests/mailintake/browser.py'}

def run(lane):
    path=Path(ALLOWED[lane]); errors=[]; navigation=[]; failed_requests=[]; contexts=[]; original=Browser.new_context
    def new_context(self,*args,**kwargs):
        context=original(self,*args,**kwargs); ordinal=len(contexts); contexts.append(ordinal)
        def page_created(page):
            page.on('pageerror',lambda e:errors.append({'kind':'javascript','digest':hashlib.sha256(str(e).encode()).hexdigest()}))
            def console(message):
                if message.type=='error' and not re.match(r'^Failed to load resource: the server responded with a status of (403|404|405|409|503)\b',message.text):
                    errors.append({'kind':'console','digest':hashlib.sha256(message.text.encode()).hexdigest()})
            def response(r):
                if r.request.resource_type=='document': navigation.append({'context':ordinal,'path':urlsplit(r.url).path,'status':r.status})
                if r.status>=400 and r.request.resource_type in ('script','stylesheet','font','image'):
                    errors.append({'kind':'asset','status':r.status,'resource':r.request.resource_type})
            def failed(r):
                url=urlsplit(r.url); failed_requests.append({'context':ordinal,'scheme':url.scheme,'host':url.netloc if url.hostname in ('127.0.0.1','localhost','web') else 'external','path':url.path if url.hostname in ('127.0.0.1','localhost','web') else '[omitted]','resource':r.resource_type})
            page.on('requestfailed',failed); page.on('console',console); page.on('response',response)
        context.on('page',page_created); return context
    Browser.new_context=new_context
    output=io.StringIO(); result={'lane':lane,'passed':False,'errors':errors,'navigation':navigation,'failed_requests':failed_requests}
    sys.path.insert(0,str(path.parent.resolve()))
    try:
        with contextlib.redirect_stdout(output),contextlib.redirect_stderr(output): runpy.run_path(str(path),run_name='__main__')
        if errors: raise AssertionError('JavaScript console or asset failure')
        result['passed']=True
    except BaseException as e:
        trace=e.__traceback__; locations=[]
        while trace:
            filename=trace.tb_frame.f_code.co_filename
            if filename.startswith(str(Path.cwd())) or filename.startswith('tests/'):
                locations.append({'file':str(Path(filename).name),'line':trace.tb_lineno})
            trace=trace.tb_next
        result.update(error_type=type(e).__name__,locations=locations)
        message=str(e)
        if re.fullmatch(r'[A-Za-z0-9 /_+.,:()=-]{1,180}',message): result['assertion']=message
    finally:
        Browser.new_context=original
        result['last_passes']=[line for line in output.getvalue().splitlines() if re.fullmatch(r'PASS [A-Za-z0-9 /_+.,:()=-]{1,180}',line)][-12:]
        Path('.local/coenabled-evidence').mkdir(exist_ok=True)
        Path('.local/coenabled-evidence/'+lane+'-observer.json').write_text(json.dumps(result,indent=2))
        metric={'core':'maxcore-mobile.json','orders':'orders-mobile.json','customs':'customs-layout.json','mail':'mailintake-layout.json'}[lane]
        if Path('.local/'+metric).is_file(): shutil.copyfile('.local/'+metric,'.local/coenabled-evidence/'+lane+'-layout-raw.json')
        patterns={'core':'maxcore*-*.png','orders':'orders-*.png','customs':'customs-view-*.png'}
        if lane in patterns:
            for screenshot in Path('.local').glob(patterns[lane]): shutil.copyfile(screenshot,Path('.local/coenabled-evidence')/screenshot.name)
    print('PASS' if result['passed'] else 'FAIL',lane,'browser observer',flush=True)
    if not result['passed']: raise SystemExit(1)

if __name__=='__main__': run(sys.argv[1])
