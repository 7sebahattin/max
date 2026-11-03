from pathlib import Path
import re
root=Path(__file__).resolve().parents[2]
module=root/'modules/maxcore'
for path in module.rglob('*.php'):
    text=path.read_text()
    assert 'NOCSRFCHECK' not in text and 'NOLOGIN' not in text
    assert not re.search(r'CREATE\s+TABLE|UPDATE\s+llx_|INSERT\s+INTO\s+llx_',text,re.I)
for name in ['index.php','admin/setup.php','admin/status.php']:
    text=(module/name).read_text()
    assert 'bootstrap.php' in text and 'maxCoreRequire(' in text
for lang in ['en_US','tr_TR']:
    keys={s.split('=',1)[0] for s in (module/f'langs/{lang}/maxcore.lang').read_text().splitlines() if '=' in s}
    assert 'MAXLegalSeparation' in keys
    if lang=='en_US': reference=keys
    else: assert keys==reference
assert "define('CSRFCHECK_WITH_TOKEN',1)" in (module/'lib/bootstrap.php').read_text()
print('PASS authenticated entrypoints, native CSRF, translations and no shadow-schema invariants')
