#!/usr/bin/env python3
"""Download and verify upstream outside the tracked tree; never modify its core."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile

root = Path(__file__).resolve().parents[1]
lock = json.loads((root / "upstream.lock.json").read_text())
local = root / ".local"
local.mkdir(exist_ok=True)
# CA certificates are public trust material; stage inside the build workspace
# for Compose/Bake filesystem restrictions without adding them to the image.
ca_bundle = Path(os.environ.get("MAX_CA_BUNDLE", "/etc/ssl/certs/ca-certificates.crt"))
shutil.copyfile(ca_bundle, local / "build-ca.pem")
archive = local / "dolibarr.tar.gz"
if not archive.exists():
    subprocess.run(["curl", "--fail", "--location", "--retry", "2", lock["archive_url"], "--output", str(archive)], check=True)
if hashlib.sha256(archive.read_bytes()).hexdigest() != lock["sha256"]:
    raise SystemExit("Upstream checksum mismatch; remove .local/dolibarr.tar.gz and investigate")
destination = local / "upstream"
if not destination.exists():
    with tempfile.TemporaryDirectory(dir=local) as temp:
        with tarfile.open(archive) as source:
            source.extractall(temp, filter="data")
        trees = list(Path(temp).iterdir())
        if len(trees) != 1:
            raise SystemExit("Unexpected archive layout")
        shutil.move(str(trees[0]), destination)
for name in ("conf", "documents"):
    (local / name).mkdir(exist_ok=True)
# Only the configuration overlay is writable, never the upstream source tree.
example = destination / "htdocs/conf/conf.php.example"
shutil.copyfile(example, local / "conf/conf.php.example")
config = local / "conf/conf.php"
if not config.exists():
    config.write_text("")
# Sandbox only: Apache UID 33 needs access to isolated disposable configuration.
os.chmod(local / "conf", 0o777)
os.chmod(config, 0o666)
os.chmod(local / "documents", 0o777)
print("Verified Dolibarr " + lock["version"] + "; local installer prepared (not installed)")
