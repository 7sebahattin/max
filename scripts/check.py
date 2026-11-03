#!/usr/bin/env python3
"""Repository boundaries and documentation links, without network or dependencies."""
import json
from pathlib import Path
import re
import subprocess

root = Path(__file__).resolve().parents[1]
files = subprocess.check_output(["git", "ls-files", "--cached", "--others", "--exclude-standard"], cwd=root, text=True).splitlines()
required = ['AGENTS.md', 'README.md', '.env.example', 'upstream.lock.json', 'compose.yaml', 'docs/PRODUCT_SCOPE.md', 'docs/ARCHITECTURE.md', 'docs/LEGACY_FUNCTIONAL_MATRIX.md', 'docs/SECURITY.md', 'docs/BRANDING.md', 'docs/ROADMAP.md', 'docs/COMPATIBILITY.md', 'docs/ACCEPTANCE.md', 'docs/adr/0001-native-first.md']
errors = []
for name in required:
    if not (root / name).is_file() or not (root / name).stat().st_size:
        errors.append('Missing required file: ' + name)
for name in files:
    path = Path(name)
    if (path.name.startswith('.env') and path.name != '.env.example') or path.suffix in ['.pem', '.key', '.sql'] or path.name in ['conf.php', 'credentials.json', 'id_rsa'] or path.parts[0] in ['htdocs', 'vendor', 'storage', '.local']:
        errors.append('Forbidden tracked file: ' + name)
    if path.suffix == '.md':
        for link in re.findall(r'\[[^\]]*\]\(([^)]+)\)', (root / name).read_text()):
            if '://' not in link and not link.startswith('#') and not (root / path.parent / link.split('#')[0]).exists():
                errors.append('Broken local link: ' + name + ': ' + link)
    content = (root / name).read_text(errors='replace')
    if re.search(r'-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----|gh[pousr]_[A-Za-z0-9]{30,}', content):
        errors.append('Potential secret: ' + name)
lock = json.loads((root / 'upstream.lock.json').read_text())
if not re.fullmatch(r'[0-9a-f]{64}', lock['sha256']) or not re.fullmatch(r'[0-9a-f]{40}', lock['commit']):
    errors.append('Invalid upstream pin')
if errors:
    raise SystemExit('\n'.join(errors))
print('Repository boundaries, required docs, local links and basic secret patterns PASS')
