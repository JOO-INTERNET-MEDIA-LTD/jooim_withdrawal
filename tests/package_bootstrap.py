#!/usr/bin/env python3
"""Load every front controller from the actual installation ZIP without Composer."""
from pathlib import Path
import subprocess,sys,tempfile,zipfile
repository=Path(__file__).resolve().parents[1]
archive=Path(sys.argv[1]).resolve() if len(sys.argv)>1 else max((repository/'dist/audit-candidate').glob('*.zip'),key=lambda p:p.stat().st_mtime)
with tempfile.TemporaryDirectory(prefix='withdrawal-package-') as directory:
    root=Path(directory)
    with zipfile.ZipFile(archive) as z:
        for name in z.namelist():
            p=Path(name)
            assert not p.is_absolute() and '..' not in p.parts
        z.extractall(root)
    module=root/'jooim_withdrawal'
    assert not (module/'vendor').exists(),'Fixture must exercise the vendor-free installation'
    bootstrap=root/'bootstrap.php'
    bootstrap.write_text('''<?php
error_reporting(E_ALL);
set_error_handler(function ($level,$message,$file,$line) { throw new ErrorException($message,0,$level,$file,$line); });
define('_PS_VERSION_', '9.0.0');
class ModuleFrontController {}
$root = $argv[1];
foreach (glob($root.'/controllers/front/*.php') as $controller) { require_once $controller; }
if (!class_exists('Jooim_WithdrawalDirectModuleFrontController',false)) { throw new RuntimeException('Direct controller not loaded'); }
$tabs = new \\JooInternetMedia\\PrestaShop\\Module\\JooimWithdrawal\\Service\\AdminTabLabels($root.'/translations/admin-tabs.json');
$labels = $tabs->labels((object) ['locale'=>'sk-SK','iso_code'=>'sk'],static fn ($source)=>$source);
if ($labels['links'] !== 'Odkazy' || $labels['data'] !== 'Vymazanie') { throw new RuntimeException('Packaged localized tab captions missing'); }
echo "PASS: all packaged front controllers load without vendor/autoload.php.\\n";
''')
    result=subprocess.run(['php',str(bootstrap),str(module)],capture_output=True,text=True)
    print(result.stdout,end='')
    if result.returncode:
        print(result.stderr,end='');raise SystemExit(result.returncode)
