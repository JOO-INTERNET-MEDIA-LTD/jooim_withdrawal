#!/usr/bin/env python3
"""Negative freshness checks using disposable public-source copies only."""
from pathlib import Path
import importlib.util,json,tempfile
ROOT=Path(__file__).resolve().parents[2]
spec=importlib.util.spec_from_file_location('manual_builder',ROOT/'tools/manual/build.py');builder=importlib.util.module_from_spec(spec);spec.loader.exec_module(builder)
data=json.loads((ROOT/'tools/manual/settings.json').read_text());module=ROOT.name
first=next(iter(data['modules'][module].values()))[0]['meaning']
with tempfile.TemporaryDirectory(prefix='jooim-guide-test-') as directory:
 root=Path(directory);(root/'tools/manual').mkdir(parents=True)
 original=json.loads(json.dumps(data))
 del data['translations']['de'][first]
 (root/'tools/manual/settings.json').write_text(json.dumps(data))
 assert first in builder.payload(root,module,'de')['missing_translations'],'Missing German explanation accepted'
 data=json.loads(json.dumps(original))
 label=data['modules'][module][next(iter(data['modules'][module]))][0]['label']
 data['manual_labels']['de'].pop(label,None)
 (root/'tools/manual/settings.json').write_text(json.dumps(data))
 assert 'label:'+label in builder.payload(root,module,'de')['missing_translations'],'Missing German label accepted'
 data=original
 data['translations']['en'][first]+=' changed'
 (root/'tools/manual/settings.json').write_text(json.dumps(data))
 assert first in builder.payload(root,module,'sk')['missing_translations'],'Stale translated explanation accepted'
 contract=json.loads((ROOT/'tools/manual/source-contract.json').read_text())
 for name in contract['files']:
  target=root/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes((ROOT/name).read_bytes())
 assert builder.source_digest(root,contract['files'])==contract['sha256'],'Baseline contract differs'
 target=root/contract['files'][0];target.write_bytes(target.read_bytes()+b'\n// synthetic new control\n')
 assert builder.source_digest(root,contract['files'])!=contract['sha256'],'Changed form/default source accepted'
print('PASS: stale translation and changed-source negative checks')
