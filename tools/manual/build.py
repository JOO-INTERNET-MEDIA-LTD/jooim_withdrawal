#!/usr/bin/env python3
"""Build static, read-only setting explanations. Never load merchant configuration."""
import hashlib
import html
import json
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def native_labels(root, lang):
    labels = {}
    # Read native catalog literals as data; never execute PHP files.
    catalog = root / 'translations' / (lang + '.php')
    if catalog.exists():
        for key, value in re.findall(r"\$_MODULE\['([^']+)'\]\s*=\s*'((?:\\.|[^'\\])*)';", catalog.read_text()):
            digest = key.rsplit('_', 1)[-1]
            if re.fullmatch('[a-f0-9]{32}', digest):
                labels['@md5:' + digest] = html.unescape(value.replace("\\'", "'").replace('\\\\', '\\'))
    for path in sorted((root / 'translations').glob(lang + '*/**/*.xlf')):
        tree = ET.parse(str(path))
        for unit in tree.iter():
            if unit.tag.split('}')[-1] != 'trans-unit':
                continue
            values = {c.tag.split('}')[-1]: ''.join(c.itertext()) for c in unit}
            if values.get('source') and values.get('target'):
                labels[values['source']] = values['target']
    return labels


def payload(root, module, lang):
    data = json.loads((root / 'tools/manual/settings.json').read_text())
    english = data['translations']['en']
    revisions = data.get('translation_revisions', {}).get(lang, {})
    messages = {key: value for key, value in data['translations'].get(lang, {}).items()
                if lang == 'en' or revisions.get(key) == hashlib.sha256(english[key].encode()).hexdigest()}
    labels = native_labels(root, lang)
    missing = set()

    def label(value):
        native = labels.get(value, labels.get('@md5:' + hashlib.md5(value.encode()).hexdigest()))
        if native and (lang == 'en' or native != value):
            return native
        translated = data.get('manual_labels', {}).get(lang, {}).get(value)
        if translated:
            return translated
        if lang != 'en':
            missing.add('label:' + value)
        return value

    def message(key):
        if key not in messages:
            missing.add(key)
        return messages.get(key, english[key])

    groups = []
    for group, fields in data['modules'][module].items():
        entries = []
        for field in fields:
            item = dict(field)
            if module == 'jooim_cookie_banner' and field['name'].startswith('joo_texts['):
                catalog = root / 'translations' / (lang + '.json')
                if catalog.exists():
                    item['default'] = json.loads(catalog.read_text()).get(field['name'][10:-1], item['default'])
            item['label'] = label(field['label'])
            item['text'] = message(field['meaning'])
            item['text_language'] = lang if field['meaning'] in messages else 'en'
            item['options'] = [dict(option, label=label(option['label']),
                                    text=message(option['meaning']),
                                    text_language=lang if option['meaning'] in messages else 'en')
                               for option in field.get('options', [])]
            entries.append(item)
        english_caption = data['group_labels']['en'][group]
        groups.append({'id': group, 'title': label(english_caption), 'fields': entries})
    captions = {key: message(key) for key in ['intro', 'default', 'expected', 'choices', 'on', 'off', 'empty', 'action', 'dynamic']}
    return {'schema': 2, 'groups': groups, 'captions': captions,
            'missing_translations': sorted(missing), 'source_language': 'en'}


def source_digest(root, files):
    digest = hashlib.sha256()
    for name in sorted(files):
        digest.update(name.encode())
        digest.update((root / name).read_bytes())
    return digest.hexdigest()


def main():
    data = json.loads((ROOT / 'tools/manual/settings.json').read_text())
    module = ROOT.name
    assert module in data['modules'], 'Wrong module registry'
    fields = [f for group in data['modules'][module].values() for f in group]
    assert len({f['name'] for f in fields}) == len(fields), 'Duplicate setting'
    captions_path = ROOT / 'tools/manual/locales.json'
    locale_file = captions_path if captions_path.exists() else ROOT / 'tools/readme/locales.json'
    required = set('en fr de pl cs sk es it pt nl lb da sv nb fi uk bg hr et el hu ga lv lt mt ro sl is'.split())
    assert set(json.loads(locale_file.read_text())) == required, 'Required Manual language set mismatch'
    assert set(data['translations']) == required, 'Missing Manual translation catalog'
    for lang in sorted(required):
        missing = payload(ROOT, module, lang)['missing_translations']
        assert not missing, 'Incomplete {}: {}'.format(lang, ', '.join(missing))
    contract = json.loads((ROOT / 'tools/manual/source-contract.json').read_text())
    assert source_digest(ROOT, contract['files']) == contract['sha256'], 'Settings source changed: review field/choice documentation and refresh its source contract'
    if captions_path.exists():
        captions = json.loads(captions_path.read_text())
        for lang, caption in captions.items():
            result = {'language': lang, 'title': caption['manual'], 'settings': caption['settings'],
                      'scope': '', 'examples': '', 'sections': [{'id': 'configuration', 'title': caption['settings'], 'text': ''}],
                      'configuration': payload(ROOT, module, lang)}
            path = ROOT / 'views/manual' / (lang + '.json')
            serialized = json.dumps(result, ensure_ascii=False, indent=2) + '\n'
            if '--write' in sys.argv:
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(serialized)
            else:
                assert path.read_text() == serialized, 'Stale runtime Manual: ' + lang
    report = {lang: payload(ROOT, module, lang)['missing_translations'] for lang in
              json.loads((ROOT / ('tools/manual/locales.json' if captions_path.exists() else 'tools/readme/locales.json')).read_text())}
    (ROOT / 'docs').mkdir(exist_ok=True)
    (ROOT / 'docs/manual-translation-coverage.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    lines = ['# Configuration reference', '', 'Generated from tools/manual/settings.json; do not edit this copy.', '']
    for group in payload(ROOT, module, 'en')['groups']:
        lines += ['## ' + group['title'], '']
        for field in group['fields']:
            lines += ['### ' + field['label'], '', field['text'], '',
                      'Field: `' + field['name'] + '`; public default: `' + str(field['default']) + '`.', '']
            if field.get('expected'):
                lines += ['Expected: ' + field['expected'], '']
            lines += ['- **' + option['label'] + '**: ' + option['text'] for option in field['options']]
            lines += ['']
    (ROOT / 'docs/settings-reference.md').write_text('\n'.join(lines))
    if '--require-complete-translations' in sys.argv:
        assert not any(report.values()), 'Detailed Manual translations remain incomplete; inspect docs/manual-translation-coverage.json'
    print('PASS: {}: {} documented settings/actions; all {} languages complete; source contract current'.format(module, len(fields), len(required)))


if __name__ == '__main__':
    main()
