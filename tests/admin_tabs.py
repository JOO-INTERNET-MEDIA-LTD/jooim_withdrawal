#!/usr/bin/env python3
"""Test the real tab markup/JS with synthetic template values, without production data."""
from pathlib import Path
import html,json,re,subprocess,tempfile
from xml.etree import ElementTree as ET
ROOT=Path(__file__).resolve().parents[1]
ns={'x':'urn:oasis:names:tc:xliff:document:1.2'}
source=(ROOT/'views/templates/admin/configure.tpl').read_text()
keys=set(re.findall(r"\{l s='([^']*)' d='Modules.Jooimwithdrawal.Admin'\}",source))
catalogs={}
for p in (ROOT/'translations').glob('*/ModulesJooimwithdrawalAdmin.*.xlf'):
    entries={u.find('x:source',ns).text:u.find('x:target',ns).text for u in ET.parse(p).findall('.//x:trans-unit',ns)}
    assert all(entries.get(k) for k in keys),(p.parent.name,keys-entries.keys())
    catalogs[p.parent.name]=entries
print('PASS:',len(keys),'actual settings-template strings translated in',len(catalogs),'locales.')
runtime_labels=json.loads(subprocess.check_output(['php',str(ROOT/'tests/admin_tab_labels.php'),'--json'],text=True))
for key in ['links','availability','trader','security','data']:
    assert '{$withdrawal_tab_labels.'+key+'|' in source,'Tab caption must use runtime-resolved label: '+key
# This is a DOM fixture, not a substitute for native Smarty/PrestaShop integration.
markup=re.sub(r"\{l s='([^']*)' d='Modules.Jooimwithdrawal.Admin'\}",lambda m:html.escape(catalogs['sk-SK'][m[1]],quote=True),source)
markup=re.sub(r'\{\$withdrawal_tab_labels\.([a-z]+)[^}]*\}',lambda m:html.escape(runtime_labels['sk-SK'][m[1]],quote=True),markup)
markup=re.sub(r'\{\$withdrawal_active_tab[^}]*\}','links',markup)
markup=re.sub(r'\{\$[^}]+\}','',markup)
markup=re.sub(r'\{(?:if|elseif|else|/if|foreach|/foreach)\b[^}]*\}','',markup)
assert '{l ' not in markup
script='''
try {
let checks=0;function check(ok,label){checks++;if(!ok)throw Error(label);}
const root=document.querySelector('[data-wd-settings]'),tabs=[...root.querySelectorAll('[role=tab]')],panels=[...root.querySelectorAll('[role=tabpanel]')];
check(tabs.length===5 && panels.length===5,'Five actual tabs');
check(tabs.map(t=>t.textContent).join('|')==='Odkazy|Dostupnosť|Obchodník|Bezpečnosť|Vymazanie','Slovak captions');
check(panels.filter(p=>!p.hidden).length===1 && !panels[0].hidden,'Only links initially visible');
check(root.querySelector('[data-wd-settings-actions]').hidden,'No settings Save on links');
for(let i=1;i<5;i++){tabs[i].click();check(!panels[i].hidden && panels.filter(p=>!p.hidden).length===1,'Panel '+i);}
const save=root.querySelector('[name=submitJooimWithdrawal]'),purge=root.querySelector('[name=purgeJooimWithdrawalData]');
check(save.form!==purge.form && !save.form.contains(purge),'Purge form isolated');
check(root.querySelector('[data-wd-settings-actions]').hidden,'No settings Save in purge');
tabs[2].click();check(!root.querySelector('[data-wd-settings-actions]').hidden,'Save available in Trader');
check(root.querySelector('[name=JOOIM_WITHDRAWAL_ENABLED]').closest('[data-wd-panel]').dataset.wdPanel==='availability','Legal availability grouped');
check(root.querySelector('[name=JOOIM_WITHDRAWAL_MERCHANT_NAME]').closest('[data-wd-panel]').dataset.wdPanel==='trader','Trader inputs grouped');
check(root.querySelector('.jooim-withdrawal-secret-url').closest('[data-wd-panel]').dataset.wdPanel==='security','Maintenance grouped with security');
check([...root.querySelectorAll('[name=withdrawal_active_tab]')].every(f=>f.value==='trader'),'POST tab state');
tabs[2].dispatchEvent(new KeyboardEvent('keydown',{key:'End',bubbles:true}));check(tabs[4].getAttribute('aria-selected')==='true','End keyboard activation');
root.querySelectorAll('input[type=number]').forEach(x=>x.value=x.min||'0');
const invalid=root.querySelector('#jooim-magic-ttl');invalid.value='-1';
save.form.checkValidity();check(!document.getElementById('wd-panel-security').hidden,'Invalid hidden input opens its panel');
check(!document.querySelector('form form'),'No nested forms');
check(document.documentElement.scrollWidth<=window.innerWidth,'No horizontal overflow');
tabs[0].click();document.getElementById('result').textContent=JSON.stringify({pass:true,checks:checks});
}catch(e){document.getElementById('result').textContent=JSON.stringify({pass:false,error:e.message});}
'''
output=ROOT/'docs/admin-tabs-preview.html'
output.write_text('<!doctype html><meta charset="utf-8"><style>body{font:14px Arial;margin:20px}'+(ROOT/'views/css/portfolio-admin.css').read_text()+(ROOT/'views/css/admin-tabs.css').read_text()+'</style><div class="jooim-portfolio-admin">'+markup+'</div><pre id="result"></pre><script>'+(ROOT/'views/js/admin-tabs.js').read_text()+'</script><script>'+script+'</script>')
for width in [1280,390]:
    with tempfile.TemporaryDirectory(prefix='withdrawal-tabs-') as profile:
        run=subprocess.run(['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome','--headless','--no-sandbox','--disable-background-networking','--no-first-run','--user-data-dir='+profile,'--window-size='+str(width)+',1000','--virtual-time-budget=1200','--dump-dom',output.as_uri()],capture_output=True,text=True,timeout=50)
        match=re.search(r'<pre id="result">(.*?)</pre>',run.stdout)
        result=json.loads(html.unescape(match[1])) if match else {'pass':False,'error':'Missing browser result'}
        print(width,result);assert result['pass']
