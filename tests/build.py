"""Reproducible module ZIP with one library root; no tests, Git data or secrets."""
from pathlib import Path
import hashlib
import json
import zipfile

ROOT = Path(__file__).resolve().parents[1]
FILES = ['library.json', 'README.md', 'CHANGELOG.md', 'docs/INSTALLATION.md',
         'docs/VALIDATION.md', 'docs/API.md', 'docs/inspect-symcon.php', 'docs/dashboard.png',
         'Vitovent300FTile/module.json', 'Vitovent300FTile/module.php', 'Vitovent300FTile/tile.html']
version = json.loads((ROOT / 'library.json').read_text())['version']
dest = ROOT / 'dist'
dest.mkdir(exist_ok=True)
archive = dest / f'Vitovent300F-Kachel-{version}.zip'
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for name in sorted(FILES):
        info = zipfile.ZipInfo('Vitovent300F_Kachel/' + name, date_time=(2026, 10, 25, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        z.writestr(info, (ROOT / name).read_bytes())
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    assert 'Vitovent300F_Kachel/library.json' in z.namelist()
    for name in FILES:
        assert z.read('Vitovent300F_Kachel/' + name) == (ROOT / name).read_bytes()
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(archive.with_suffix('.zip.sha256')).write_text(f'{digest}  {archive.name}\n')
print(f'PASS: {archive.name}, {len(FILES)} files, SHA-256 {digest}')

# Separate source bundle for upload to GitHub, including tests and CI, excluding outputs and .git.
source_files = FILES + ['.gitignore', '.github/workflows/check.yml', 'tests/build.py',
    'tests/module_test.php', 'tests/browser_test.py', 'tests/manifests_test.py',
    'tests/install_test.php', 'tests/package_test.py',
    'tests/fixtures/ActionTarget/module.php', 'tests/fixtures/ActionTarget/module.json']
source_archive = dest / f'Vitovent300F-GitHub-{version}.zip'
with zipfile.ZipFile(source_archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for name in sorted(source_files):
        info = zipfile.ZipInfo('Viessmann-Vitovent-300F-Kachel-Symcon/' + name, date_time=(2026, 10, 25, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        z.writestr(info, (ROOT / name).read_bytes())
with zipfile.ZipFile(source_archive) as z:
    assert z.testzip() is None
source_digest = hashlib.sha256(source_archive.read_bytes()).hexdigest()
source_archive.with_suffix('.zip.sha256').write_text(f'{source_digest}  {source_archive.name}\n')
print(f'PASS: {source_archive.name}, {len(source_files)} source files')
