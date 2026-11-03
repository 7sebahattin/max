"""Render installed native screens with synthetic credentials; no external requests."""
import json
from pathlib import Path
from playwright.sync_api import sync_playwright

base = 'http://127.0.0.1:8080'
password = Path('.local/p2-admin-secret').read_text().strip()
def run():
    results = []
    with sync_playwright() as p:
        browser = p.chromium.launch()
        context = browser.new_context()
        context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(base + '/') else route.abort())
        page = context.new_page()
        page.goto(base + '/')
        page.locator('input[name=username]').fill('maxadmin')
        page.locator('input[name=password]').fill(password)
        page.locator('input[type=submit], button[type=submit]').first.click()
        page.wait_for_load_state('networkidle')
        assert page.locator('input[name=password]').count() == 0, 'Login failed'
        for width in [360, 390, 768]:
            page.set_viewport_size({'width': width, 'height': 900})
            for path in ['societe/list.php', 'product/list.php', 'commande/list.php', 'expedition/list.php', 'compta/facture/list.php']:
                response = page.goto(base + '/' + path)
                page.wait_for_load_state('networkidle')
                assert response.status == 200, path
                text = page.locator('body').inner_text()
                assert 'Fatal error' not in text and 'Parse error' not in text, path
                assert page.locator('input[name=password]').count() == 0, 'Session lost'
                name = path.replace('/', '-')
                page.screenshot(path=f'.local/p2-{width}-{name}.png', full_page=True)
                results.append({'width': width, 'path': path, 'status': response.status,
                                'body_scroll_width': page.evaluate('document.body.scrollWidth')})
        browser.close()
    Path('.local/p2-browser.json').write_text(json.dumps(results, indent=2))
    print('PASS native browser login and 15 authenticated screen/viewport render checks; task usability requires human review')

try:
    run()
except Exception as error:
    raise SystemExit(str(error).replace(password, "[redacted]")) from None
