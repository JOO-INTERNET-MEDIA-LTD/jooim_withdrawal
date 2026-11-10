<?php
/** Synthetic documentation tests: never bootstrap a shop or read saved configuration. */
require __DIR__.'/../../classes/JooboxManual.php';
$root = realpath(__DIR__.'/../..');
$module = basename($root);
$count = 0;
function manualCheck($ok, $name) { global $count; ++$count; if (!$ok) { throw new RuntimeException($name); } }
foreach (glob($root.'/views/manual/*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    $lang = (object) ['locale'=>basename($file,'.json')];
    $html = JooboxModuleManual::render($root,$lang,$module,'test');
    manualCheck(empty($data['configuration']['missing_translations']), 'Complete translations: '.$lang->locale);
    $expected = 0;
    foreach ($data['configuration']['groups'] as $group) {
        foreach ($group['fields'] as $field) {
            ++$expected;
            manualCheck($field['text_language'] === $lang->locale, 'Localized purpose: '.$field['name']);
            manualCheck($field['text'] !== '', 'Nonempty purpose: '.$field['name']);
            foreach ($field['options'] as $option) {
                manualCheck($option['text'] !== '', 'Choice purpose: '.$field['name']);
                manualCheck($option['text_language'] === $lang->locale, 'Localized choice: '.$field['name']);
            }
        }
    }
    manualCheck(substr_count($html,'<summary>') === $expected, 'Every setting rendered');
    manualCheck(!preg_match('/<(form|input|script|iframe)\b/i',$html), 'Read-only guide');
    $ids=[];
    preg_match_all('/\bid="([^"]+)"/',$html,$matches);
    manualCheck(count($matches[1])===count(array_unique($matches[1])), 'Unique manual anchors');
}
foreach (['fr-BE'=>'fr','fr-CA'=>'fr','fr-LU'=>'fr','nl-BE'=>'nl','de-LU'=>'de','en-CA'=>'en','en-IE'=>'en','lb-LU'=>'lb','no-NO'=>'nb','nn-NO'=>'nb'] as $locale=>$base) {
    $regional=JooboxModuleManual::data($root,(object)['locale'=>$locale]);
    manualCheck($regional['language']===$base,'Regional locale: '.$locale);
}
$form='<form method="post"><input name="example" value="1"></form>';
$html=JooboxModuleManual::tabs($root,(object)['locale'=>'sk'],$module,'test',$form,'/modules/'.$module.'/');
manualCheck(substr_count($html,$form)===1 && substr_count($html,'<form')===1,'Settings form preserved once');
manualCheck(strpos($html,'Manuál')!==false,'Slovak Manual caption');
manualCheck(strpos($html,'data-documentation-tab="manual"')!==false,'Manual navigation');
manualCheck(strpos($html,'<section id="'.$module.'-documentation-manual"')>strpos($html,'</form>'),'Manual outside form');
// An untrusted catalog is escaped, including labels, purposes, choices and defaults.
$dir=sys_get_temp_dir().'/jooim-manual-'.bin2hex(random_bytes(6));mkdir($dir);mkdir($dir.'/views');mkdir($dir.'/views/manual');
$data=json_decode(file_get_contents($root.'/views/manual/en.json'),true);
$data['configuration']['groups'][0]['fields'][0]['label']='<svg onload="bad">';
$data['configuration']['groups'][0]['fields'][0]['text']='<script>bad</script>';
file_put_contents($dir.'/views/manual/en.json',json_encode($data));
$escaped=JooboxModuleManual::render($dir,(object)['locale'=>'../../en'],$module,'test');
manualCheck(strpos($escaped,'<svg')===false && strpos($escaped,'<script>')===false,'Escape settings content and safe locale');
unlink($dir.'/views/manual/en.json');rmdir($dir.'/views/manual');rmdir($dir.'/views');rmdir($dir);
echo "PASS $count setting-guide assertions\n";
