import unittest
from browser_assertions import assert_denied, assert_allowed, assert_no_overflow

class BrowserAssertionTests(unittest.TestCase):
    def test_denied_page_can_retain_common_native_navigation(self):
        assert_denied(403, '<a href="/custom/maxcore/admin/setup.php">Marka ayarları</a><p>Forbidden</p>')

    def test_status_alone_does_not_prove_non_disclosure(self):
        payloads = [
            '<main class="maxcore-shell">private body</main>',
            '<input style="display:none" name="MAXCORE_DISPLAY_NAME" value="fixture">',
            '<script>{"MAXCORE_LOGO":"max.svg"}</script>',
            '<!-- MAXCORE_FAVICON -->',
            '<p>Prototype module. This page does not enable integrations.</p>',
            '<p>Prototip modül. Yönetim içeriği.</p>',
            '<!-- PRIVATE_SAVED_BRAND -->']
        for payload in payloads:
            with self.subTest(payload=payload):
                with self.assertRaises(AssertionError) as error:
                    assert_denied(403, payload, ['PRIVATE_SAVED_BRAND'])
                self.assertNotIn(payload, str(error.exception))
                self.assertNotIn('PRIVATE_SAVED_BRAND', str(error.exception))
        with self.assertRaises(AssertionError):
            assert_denied(200, '<p>Forbidden</p>')

    def test_positive_controls_require_actual_protected_content(self):
        settings = '<main class="maxcore-shell"><input name="MAXCORE_DISPLAY_NAME"><select name="MAXCORE_LOGO"></select><select name="MAXCORE_FAVICON"></select></main>'
        assert_allowed(200, settings, 'settings')
        assert_allowed(200, '<main class="maxcore-shell"><p>Prototip modül.</p></main>', 'administration')
        for kind in ['home', 'settings', 'administration']:
            with self.assertRaises(AssertionError):
                assert_allowed(200, '<a href="setup.php">Settings</a>', kind)

    def test_both_scroll_widths_and_all_viewports_are_enforced(self):
        for width in (360, 390, 768):
            good = dict(width=width, viewport=width, body_scroll_width=width, document_scroll_width=width)
            assert_no_overflow(good)
            for key in ('body_scroll_width', 'document_scroll_width'):
                for excess in (0.5, 1, 100):
                    with self.subTest(width=width, key=key, excess=excess):
                        with self.assertRaises(AssertionError):
                            assert_no_overflow(dict(good, **{key: width + excess}))
            with self.assertRaises(AssertionError):
                assert_no_overflow(dict(good, viewport=width+1))

if __name__ == '__main__':
    unittest.main()
