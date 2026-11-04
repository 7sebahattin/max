"""Read-only geometry diagnostics for native P2 lists; no content/credentials saved."""
import json
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8080'
SCREENS = ('societe/list.php', 'product/list.php', 'commande/list.php',
           'expedition/list.php', 'compta/facture/list.php')


def run():
    password = Path('.local/p2-admin-secret').read_text().strip()
    try:
        rows = []
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch()
            context = browser.new_context()
            context.route('**/*', lambda route: route.continue_()
                          if route.request.url.startswith(BASE + '/') else route.abort())
            page = context.new_page()
            page.goto(BASE + '/')
            page.locator('input[name=username]').fill('maxadmin')
            page.locator('input[name=password]').fill(password)
            page.locator('input[type=submit],button[type=submit]').first.click()
            page.wait_for_load_state('networkidle')
            assert page.locator('input[name=password]').count() == 0, 'Native diagnostic login failed'
            for width in (360, 390, 768):
                page.set_viewport_size(dict(width=width, height=900))
                for screen in SCREENS:
                    response = page.goto(BASE + '/' + screen)
                    assert response.status == 200, 'Native diagnostic page denied'
                    assert page.locator('input[name=password]').count() == 0, 'Native diagnostic session lost'
                    page.evaluate('document.fonts.ready')
                    row = page.evaluate("""() => {
                        const boxes = {};
                        for (const selector of ['#id-container','#id-left','#id-right',
                                                'div.fiche','.div-table-responsive','table.liste']) {
                            boxes[selector] = [...document.querySelectorAll(selector)].slice(0,3).map(node => {
                                const rect = node.getBoundingClientRect();
                                const style = getComputedStyle(node);
                                return {left:rect.left,right:rect.right,width:rect.width,
                                        scroll:node.scrollWidth,client:node.clientWidth,
                                        display:style.display,min_width:style.minWidth,
                                        white_space:style.whiteSpace};
                            });
                        }
                        return {viewport:innerWidth,body:document.body.scrollWidth,
                                document:document.documentElement.scrollWidth,boxes};
                    }""")
                    row.update(width=width, screen=screen)
                    rows.append(row)
                    print('P2_LAYOUT ' + json.dumps(row, sort_keys=True))
            browser.close()
        assert len(rows) == 15, 'Incomplete native layout diagnostics'
        Path('.local/integration-p2-layout.json').write_text(json.dumps(rows, indent=2))
    except Exception as error:
        raise SystemExit(str(error).replace(password, '[redacted]')) from None


if __name__ == '__main__':
    run()
