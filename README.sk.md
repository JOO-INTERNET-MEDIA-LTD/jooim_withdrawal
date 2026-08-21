# JOOIM Withdrawal — Online odstúpenie od zmluvy

[English](README.md) · [Slovenčina](README.sk.md) · [Čeština](README.cs.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Polski](README.pl.md)

**JOOIM Withdrawal** je modul pre PrestaShop určený na prijatie, potvrdenie a administratívne spracovanie online odstúpenia spotrebiteľa od zmluvy. Obsahuje priamu online funkciu odstúpenia, bezpečný proces podľa referencie objednávky, celé aj čiastočné odstúpenie, nemenný dôkazný záznam, e-mailové a PDF potvrdenie, multistore oddelenie a auditnú históriu.

Repozitár: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal  
Autor: JOO INTERNET MEDIA LTD  
Technický názov a povinný názov adresára: `jooim_withdrawal`  
Verzia vydania: `1.0.0`  
Stav: **stabilné verejné vydanie**

Angličtina je zdrojovým jazykom kódu, šablón, dokumentácie a prekladových katalógov. Kompletné zákaznícke katalógy sú pripravené pre slovenčinu, češtinu, nemčinu, francúzštinu a poľštinu. Nastavenia Back Office sú navyše lokalizované pre všetkých 28 vytvorených locale EÚ, USA, Kanady a Ukrajiny.

## Hlavné funkcie

- priama online funkcia odstúpenia s jednoznačným finálnym potvrdením;
- bezpečné vyhľadanie podľa referencie objednávky bez zobrazenia osobných údajov pred overením;
- jednorazový HMAC chránený odkaz odoslaný na e-mail priradený k objednávke;
- token sa spotrebuje až po POST potvrdení, takže ho automatický e-mailový skener nezneplatní;
- proces pre prihláseného zákazníka aj overeného hosťa;
- celé odstúpenie alebo výber konkrétnych položiek a množstiev;
- nepovinný dôvod a poznámka;
- nemenný JSON snapshot podania s SHA-256 hashom;
- transakčne generovaná referencia podľa obchodu a roka;
- potvrdenie na obrazovke, e-mail a neverejný PDF dokument;
- administratívny zoznam, detail prípadu, stavová história, poznámky, manuálne podania, evidencia refundácie a CSV export;
- údaje obchodníka a izolácia objednávok osobitne pre každý obchod v multistore;
- rate limiting, CSRF, idempotencia, kontrola vlastníctva a neutrálna ochrana proti enumerácii;
- údržba cez Symfony CLI alebo HTTP endpoint chránený tajným tokenom;
- bežné odinštalovanie zachová právne relevantné záznamy odstúpení.

## Právny rozsah

Modul je navrhnutý pre rámec spotrebiteľského práva EÚ a online funkciu odstúpenia pri relevantných zmluvách uzavretých na diaľku prostredníctvom online rozhrania. Podanie automaticky neodmieta iba preto, že dátum, stav objednávky alebo typ produktu naznačuje možnú výnimku.

Modul nie je náhradou právneho posúdenia. Prevádzkovateľ e-shopu zodpovedá najmä za:

- určenie, či sa právo na odstúpenie vzťahuje na konkrétnu zmluvu;
- správne údaje obchodníka, adresu na vrátenie, poučenie a zásady ochrany osobných údajov;
- kontrolu vnútroštátnej právnej úpravy, zákonných výnimiek a nákladov na vrátenie;
- rozhodnutie o platnosti odstúpenia a výške refundácie;
- zákonné retenčné lehoty právnych a technických záznamov.

Pozri [docs/LEGAL-COMPLIANCE.md](docs/LEGAL-COMPLIANCE.md).

## Požiadavky

- PrestaShop 8.1.x, 8.2.x, 9.0.x alebo 9.1.x;
- PHP 8.1 alebo novšie;
- MySQL alebo MariaDB podporované použitou verziou PrestaShopu;
- funkčné odosielanie e-mailov v PrestaShope;
- TCPDF dostupné cez PrestaShop;
- zapisovateľné súkromné úložisko v adresári `var/` PrestaShopu.

Modul nepoužíva override jadra PrestaShopu.

## Inštalácia

1. Použite výhradne inštalačný balík `jooim_withdrawal-1.0.0.zip`.
2. Nepoužívajte automatický GitHub archív **Source code (zip)**; nie je určený na priamu inštaláciu cez Module Manager.
3. V administrácii otvorte **Moduly → Správca modulov → Nahrať modul**.
4. Nahrajte ZIP a modul nainštalujte.
5. V multistore otvorte konfiguráciu osobitne pre každý obchod.
6. Doplňte obchodné meno, sídlo, identifikátor, kontakty, adresu/pokyny na vrátenie a URL zásad ochrany osobných údajov.
7. Otestujte priamu stránku odstúpenia, bezpečný proces podľa objednávky, e-mail, PDF a administráciu prípadov.
8. Pred produkciou vykonajte scenáre z [docs/ACCEPTANCE-TESTS.md](docs/ACCEPTANCE-TESTS.md).

Verzia **1.0.0** je prvé stabilné verejné vydanie. Ďalšie funkčné alebo kompatibilitné zmeny použijú nové číslo verzie.

Pri aktualizácii inštalácie, ktorá používala predprodukčný testovací balík `1.0.0` nahraďte celý adresár modulu, v každom kontexte obchodu raz otvorte konfiguráciu modulu a vymažte cache PrestaShopu. Konfiguračná stránka idempotentne doplní chýbajúcu runtime konfiguráciu, hooky a bezpečnostné hodnoty bez zmeny existujúceho tajomstva alebo vymazania prijatých odstúpení. Pozrite [docs/INSTALLATION.md](docs/INSTALLATION.md#6-replacing-an-earlier-100-test-build).

## Zákaznícke procesy

### Priame odstúpenie

Spotrebiteľ môže odoslať jednoznačné vyhlásenie bez predchádzajúceho zobrazenia objednávky. Formulár získava iba údaje potrebné na identifikáciu spotrebiteľa a zmluvy, kontrolu vyhlásenia a odoslanie potvrdenia.

### Bezpečný proces podľa referencie objednávky

Verejný formulár prijíma referenciu objednávky iba ako identifikátor. Neoznámi, či objednávka existuje, komu patrí ani aký e-mail je k nej priradený. Ak sa objednávka nájde v aktuálnom obchode, jednorazový odkaz sa odošle na uložený e-mail objednávky.

Otvorenie odkazu metódou GET token nespotrebuje. Zákazník musí pokračovať cez CSRF chránenú POST požiadavku. Hosťovská relácia je viazaná na obchod, objednávku, zákazníka a účel odstúpenia.

## Administrácia a údržba

Modul pridáva položku **Objednávky → Odstúpenia od zmluvy** a panel do detailu objednávky. Pôvodné podanie sa spätne neprepisuje; prevádzkové zmeny sa ukladajú ako nové udalosti histórie.

Odporúčaný CLI príkaz:

```bash
php bin/console jooim:withdrawal:maintenance
```

Pre hosting bez CLI je dostupná URL chránená tajným tokenom. Cron URL ani token nikdy nezverejňujte a neukladajte do GitHubu.

## Joobox podpora pre tento modul

Bezplatný GitHub modul sa poskytuje bez individuálnej technickej podpory. Inštalácia, kontrola konfigurácie, staging/produkčné testovanie, aktualizácie, riešenie problémov a úpravy môžu byť dostupné cez Joobox PrestaShop Cloud alebo inú platenú službu výslovne dohodnutú so spoločnosťou JOO INTERNET MEDIA LTD.

Konfigurácia modulu zvolí lokalizovanú URL podpory podľa aktívneho jazyka administrácie. Podrobnosti sú v [SUPPORT.md](SUPPORT.md).

## Joobox odkaz v pätičke

Modul zobrazuje malú atribúciu:

```text
PrestaShop modul od joobox.eu
```

Pevný text `joobox.eu` odkazuje na `https://joobox.eu`. Ak už atribúciu zobrazil iný modul JOOIM, druhá kópia sa odstráni a zostane iba jeden odkaz.

Táto deduplikácia sa netýka právne relevantného odkazu **Odstúpiť od zmluvy**. V štandardnej pätičke PrestaShop modulu `ps_linklist` sa odkaz vloží ako bežná položka zoznamu bezprostredne za statický odkaz **Naše predajne**. Modul používa stabilný prefix ID `link-static-page-stores-` a preberie CSS triedy susedného odkazu, takže vzhľad, rozostupy, hover aj mobilné rozbalenie riadi aktívna téma. `displayFooter` zostáva prístupným fallbackom bez JavaScriptu a pre netypické témy. Hook `displayFooterPart1` sa už vizuálne nepoužíva, pretože niektoré komerčné témy ho vykresľujú v pätičke obsahu stránky vysoko nad skutočným footerom.

CSRF ochrana používa samostatnú malú cookie `jooim_withdrawal_csrf` (`Secure` pri HTTPS, `HttpOnly`, `SameSite=Lax`) a nezväčšuje hlavnú PrestaShop cookie. V konfigurácii sa zobrazuje tabuľka oboch verejných URL pre každý aktívny jazyk obchodu; chýbajúci preklad je označený ako anglický fallback.

## Kontrola aktualizácií

Konfigurácia obsahuje iba čítaciu kontrolu najnovšieho GitHub Release. Výsledok sa ukladá na šesť hodín. Modul automaticky nesťahuje, nerozbaľuje ani neprepisuje svoje súbory.

Aktualizácia sa vykonáva iba dedikovaným inštalačným ZIP súborom priloženým k vydaniu.

## Jazyky

Angličtina (`en-US`) je autoritatívny zdroj a fallback.

Kompletné päťdoménové zákaznícke katalógy sú skontrolované pre:

```text
en-US, sk-SK, cs-CZ, de-DE, fr-FR, pl-PL
```

Všetkých 120 názvov, popisov a upozornení v nastavení Back Office je pripravených pre 28 vytvorených locale pokrývajúcich všetky úradné jazyky EÚ, angličtinu USA/Kanady, francúzštinu Kanady a ukrajinčinu. Katalóg iba pre nastavenia sa neoznačuje ako kompletný zákaznícky preklad. Tabuľka aktívnych jazykov rozlišuje **Zahrnutý**, **Iba nastavenia Back Office** a **Anglický fallback**.

E-mailové šablóny zostávajú kompletne lokalizované v `en`, `sk`, `cs`, `de`, `fr` a `pl`. Podrobnosti sú v [docs/TRANSLATIONS.md](docs/TRANSLATIONS.md).

## Lokalizované URL a vizuál témy

Každý jazyk dostáva vlastnú čitateľnú cestu, napríklad:

```text
/sk/odstupenie-od-zmluvy
/en/contract-withdrawal
/uk/vidmova-vid-dohovoru
/de/vertragswiderruf
```

Do **Dizajn → Zoznam odkazov** sa vkladá jazykovo neutrálna URL `entry` controllera, pretože zoznam odkazov poskytuje iba jedno URL pole pre všetky preložené názvy. Celá mapa ciest je v [docs/LOCALIZED-ROUTES.md](docs/LOCALIZED-ROUTES.md).

Verejné šablóny modulu rozširujú `page.tpl` aktívnej témy. Pri prihlásenom zákazníkovi použijú `customer/page.tpl`, ak ju poskytuje aktívna téma alebo jej nadradená téma. Stránka tak preberá hlavičku, menu, obsahový kontajner, vizuál zákazníckeho účtu, typografiu a footer témy.

## Vývoj a kontrola

```bash
php tests/static_validation.php
```

Statická kontrola nenahrádza test na reálnom PrestaShope, téme, SMTP, databáze a multistore konfigurácii.

## Mimo rozsahu verzie 1.0.0

- automatická refundácia platby;
- vratkové štítky dopravcov;
- automatické skladové pohyby;
- účtovníctvo;
- komplexný reklamačný/RMA systém;
- automatické právne odmietanie podaní;
- verejné API a AI právne posudzovanie.

## Dokumentácia

- [Inštalácia](docs/INSTALLATION.md)
- [Akceptačné testy](docs/ACCEPTANCE-TESTS.md)
- [Právne poznámky](docs/LEGAL-COMPLIANCE.md)
- [Preklady](docs/TRANSLATIONS.md)
- [GitHub popis a témy](docs/GITHUB-REPOSITORY.md)
- [Bezpečnosť](SECURITY.md)
- [Podpora](SUPPORT.md)

## Licencia

Modul sa distribuuje pod [licenciou MIT](LICENSE). Copyright © 2026 JOO INTERNET MEDIA LTD.


## Ďalšie jazykové názvy / Other language terms

Pozri [multilingual terminology and search aliases](docs/TERMINOLOGY.md).
