"""Cross-module rights are independent; existing real private files are denied."""
import json
from pathlib import Path
from playwright.sync_api import sync_playwright
BASE='http://127.0.0.1:8080'; OUT=Path('.local/coenabled-evidence')

def main():
    customs=json.loads(Path('.local/customs-session.json').read_text()); mail=json.loads(Path('.local/mailintake-session.json').read_text()); orders=json.loads(Path('.local/orders-accounts.json').read_text()); core=json.loads(Path('.local/maxcore-accounts.json').read_text())
    targets={
        'mail-reviewer':(mail['accounts']['reviewer'],['/custom/maxorders/index.php','/custom/maxcustoms/api.php?id='+str(customs['id']),'/custom/maxcustoms/download.php?id='+str(customs['id'])+'&evidence_id='+str(customs['evidence']),'/custom/maxcore/admin/setup.php']),
        'customs-viewer':(customs['accounts']['viewer'],['/custom/maxmail/index.php','/custom/maxmail/download.php?id='+str(mail['targets']['quarantine']['id'])+'&file='+str(mail['targets']['quarantine']['attachment']['id']),'/custom/maxorders/index.php','/custom/maxcore/admin/setup.php']),
        'orders-reviewer':(orders['accounts']['reviewer'],['/custom/maxmail/index.php','/custom/maxcustoms/api.php?id='+str(customs['id']),'/custom/maxcore/admin/setup.php']),
        'core-editor':(core['editor'],['/custom/maxmail/index.php','/custom/maxcustoms/api.php?id='+str(customs['id']),'/custom/maxorders/index.php'])
    };results=[]
    with sync_playwright() as p:
        browser=p.chromium.launch()
        for role,(account,paths) in targets.items():
            c=browser.new_context();c.route('**/*',lambda r:r.continue_() if r.request.url.startswith(BASE+'/') else r.abort());page=c.new_page();page.goto(BASE+'/');page.locator('[name=username]').fill(account['login']);page.locator('[name=password]').fill(account['password']);page.locator('[type=submit]').first.click();page.wait_for_load_state('networkidle');assert not page.locator('[name=password]').count(),'Native login failed'
            for path in paths:
                r=c.request.get(BASE+path);assert r.status==403,'Cross module GET status'
                assert all(x not in r.text() for x in ['data-maxorders=','data-maxcustoms=','PRIVATE_EVIDENCE_SENTINEL','CONTACT_RESTRICTED_SENTINEL','Synthetic intake 202','MAXCORE_DISPLAY_NAME']),'Cross module denial body'
                results.append({'role':role,'target':path.split('?')[0],'status':r.status})
            c.close()
        browser.close()
    (OUT/'cross-roles.json').write_text(json.dumps(results,indent=2));print('PASS independent cross module roles and private file denials')
if __name__=='__main__':
    try: main()
    except BaseException:
        print('FAIL cross module roles; no private response serialized');raise SystemExit(1) from None
