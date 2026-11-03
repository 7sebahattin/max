"""Pure assertions: never include response HTML, private values or credentials in errors."""
from html import unescape
from html.parser import HTMLParser

FIELDS = frozenset({'MAXCORE_DISPLAY_NAME', 'MAXCORE_LOGO', 'MAXCORE_FAVICON'})
ADMIN_TEXT = ('Prototype module.', 'Prototip modül.')

class Content(HTMLParser):
    def __init__(self, html):
        super().__init__(convert_charrefs=True)
        self.shell = False
        self.fields = set()
        self.feed(html)
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if 'maxcore-shell' in attrs.get('class', '').split():
            self.shell = True
        if tag in ('input', 'select', 'textarea'):
            self.fields.add(attrs.get('name', ''))

def assert_denied(status, html, private_values=()):
    assert status == 403, 'Expected HTTP 403'
    content = Content(html)
    decoded = unescape(html)
    assert not content.shell, 'Denied response contains protected MAX body'
    assert not (content.fields & FIELDS), 'Denied response contains protected settings controls'
    # Also reject JSON, comments and script payloads, not only rendered controls.
    assert not any(field in decoded for field in FIELDS), 'Denied response contains settings payload'
    assert not any(marker in decoded for marker in ADMIN_TEXT), 'Denied response contains administration payload'
    assert not any(value and value in decoded for value in private_values), 'Denied response contains saved brand value'

def assert_allowed(status, html, page):
    assert status == 200, 'Expected HTTP 200'
    content = Content(html)
    assert content.shell, 'Authorized MAX body missing'
    if page == 'settings':
        assert FIELDS <= content.fields, 'Authorized settings controls missing'
    if page == 'administration':
        assert any(marker in unescape(html) for marker in ADMIN_TEXT), 'Authorized administration payload missing'

def assert_no_overflow(metrics):
    # No tolerance: body and documentElement must fit the requested CSS viewport.
    assert metrics['viewport'] == metrics['width'], 'Unexpected CSS viewport'
    assert max(metrics['body_scroll_width'], metrics['document_scroll_width']) <= metrics['width'], (
        'Horizontal overflow: width=%s body=%s document=%s' %
        (metrics['width'], metrics['body_scroll_width'], metrics['document_scroll_width']))
