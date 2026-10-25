from pathlib import Path
import json
import re
import uuid

root = Path(__file__).resolve().parents[1]
library = json.loads((root / 'library.json').read_text())
module = json.loads((root / 'Vitovent300FTile/module.json').read_text())
assert set(library) == {'id','author','name','url','compatibility','version','build','date'}
assert set(module) == {'id','name','type','vendor','aliases','url','parentRequirements','childRequirements','implemented','prefix'}
for manifest in [library, module]:
    assert re.fullmatch(r'\{[A-F0-9]{8}(?:-[A-F0-9]{4}){3}-[A-F0-9]{12}\}', manifest['id'])
    uuid.UUID(manifest['id'])
    assert manifest['url'].startswith('https://github.com/somm86/')
assert library['id'] == '{B24E29F4-981D-4B92-842B-785D28962BD5}'
assert module['id'] == '{4305595E-0E5F-4124-AD59-8E11257A1E97}'
assert library['id'] != module['id']
assert library['compatibility'] == {'version': '7.1'}
assert isinstance(library['version'], str) and isinstance(library['build'], int) and isinstance(library['date'], int)
assert module['type'] == 3 and module['name'] == 'Vitovent300FTile' and module['prefix'] == 'VVF'
for field in ['aliases', 'parentRequirements', 'childRequirements', 'implemented']:
    assert isinstance(module[field], list)
assert module['parentRequirements'] == module['childRequirements'] == module['implemented'] == []
assert 'class Vitovent300FTile extends IPSModule' in (root / 'Vitovent300FTile/module.php').read_text()
print('PASS: manifests, fields, GUIDs, class and prefix')
