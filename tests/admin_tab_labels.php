<?php
/** Actual runtime fallback with an English-only translator, not just XLIFF presence. */
require dirname(__DIR__).'/src/Service/AdminTabLabels.php';
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\AdminTabLabels;
$path = dirname(__DIR__).'/translations/admin-tabs.json';
$presenter = new AdminTabLabels($path);
$catalogues = json_decode(file_get_contents($path),true);
$keys = ['links','availability','trader','security','data'];
$result = [];
foreach ($catalogues as $locale=>$targets) {
    $language = (object) ['locale'=>$locale,'iso_code'=>strtolower(explode('-',$locale)[0])];
    $labels = $presenter->labels($language,static fn ($source)=>$source);
    if (array_values($labels) !== array_values($targets)) { throw new RuntimeException('Runtime fallback failed for '.$locale); }
    $result[$locale] = $labels;
}
$sk = $presenter->labels((object) ['locale'=>'sk_sk','iso_code'=>'sk'],static fn ($source)=>$source);
if (implode('|',$sk) !== 'Odkazy|Dostupnosť|Obchodník|Bezpečnosť|Vymazanie') { throw new RuntimeException('Slovak captions must never fall back to English.'); }
$custom = $presenter->labels((object) ['locale'=>'sk-SK'],static fn ($source)=>$source==='Links' ? 'Moje vlastné odkazy' : $source);
if ($custom['links'] !== 'Moje vlastné odkazy') { throw new RuntimeException('Merchant translation overwritten.'); }
$methodLocale = new class { public function getLocale() { return 'fr-BE'; } };
if ($presenter->labels($methodLocale,static fn ($s)=>$s)['links'] !== 'Liens') { throw new RuntimeException('Native language getLocale failed.'); }
if ($presenter->labels((object) ['iso_code'=>'de'],static fn ($s)=>'')['security'] !== 'Sicherheit') { throw new RuntimeException('ISO fallback failed.'); }
if (in_array('--json',$argv,true)) { echo json_encode($result,JSON_UNESCAPED_UNICODE); }
else { echo 'PASS: runtime tab fallback in '.count($catalogues)." locales, stale English translator, locale normalization, ISO and merchant overrides.\n"; }
