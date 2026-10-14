# JOOIM Withdrawal — Online odstoupení od smlouvy

[English](README.md) · [Slovenčina](README.sk.md) · [Čeština](README.cs.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Polski](README.pl.md)

**JOOIM Withdrawal** je modul pro PrestaShop určený k přijímání, potvrzování a administrativnímu zpracování online odstoupení spotřebitele od smlouvy. Nabízí přímou online funkci odstoupení, bezpečný proces podle reference objednávky, úplné i částečné odstoupení, neměnný důkazní záznam, e-mailové a PDF potvrzení, oddělení multistore a auditní historii.

Repozitář: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal  
Autor: JOO INTERNET MEDIA LTD  
Technický název a povinný název složky: `jooim_withdrawal`  
Verze vydání: `1.0.0`  
Stav: **stabilní veřejné vydání**

Angličtina je zdrojovým jazykem kódu, šablon, dokumentace a překladových katalogů. Čeština, slovenština, němčina, francouzština a polština jsou samostatné překlady.

## Hlavní funkce

- přímé online odstoupení s jednoznačným závěrečným potvrzením;
- bezpečné vyhledání objednávky bez zveřejnění osobních údajů před ověřením;
- jednorázový HMAC chráněný odkaz zaslaný na e-mail objednávky;
- token se spotřebuje až po POST potvrzení, nikoli pouhým otevřením odkazu;
- proces pro přihlášeného zákazníka i ověřeného hosta;
- úplné nebo částečné odstoupení podle položek a množství;
- nepovinný důvod a poznámka;
- neměnný JSON snapshot a SHA-256 hash;
- potvrzení na obrazovce, e-mailem a v neveřejném PDF;
- seznam případů, detail, historie, manuální podání, evidence refundace a CSV export;
- konfigurace a izolace objednávek pro každý obchod v multistore;
- CSRF, idempotence, rate limiting, kontrola vlastnictví a neutrální odpovědi proti enumeraci;
- údržba přes Symfony CLI nebo HTTP endpoint chráněný tokenem;
- běžná odinstalace zachovává právně významné záznamy.

## Právní rozsah

Modul vychází z rámce spotřebitelského práva EU a požadavků na online funkci odstoupení u relevantních smluv uzavřených na dálku prostřednictvím online rozhraní. Podání automaticky neodmítá pouze na základě data, stavu objednávky nebo typu produktu.

Provozovatel obchodu nadále odpovídá za posouzení použitelného práva, správné údaje obchodníka, místní poučení, zákonné výjimky, náklady na vrácení, rozhodnutí o refundaci a retenční lhůty. Viz lokální projektová dokumentace.

## Požadavky

- PrestaShop 8.1.x až 9.1.x;
- PHP 8.1 nebo novější;
- podporovaný MySQL/MariaDB;
- funkční e-mailová konfigurace PrestaShopu;
- TCPDF z PrestaShopu;
- zapisovatelný soukromý adresář `var/`.

Modul nepoužívá override jádra PrestaShopu.

## Instalace

1. Použijte instalační soubor `jooim_withdrawal-1.0.0.zip`.
2. Nepoužívejte automatický GitHub archiv **Source code (zip)**.
3. V Back Office otevřete **Moduly → Správce modulů → Nahrát modul**.
4. Nahrajte ZIP a modul nainstalujte.
5. V multistore nakonfigurujte každý obchod zvlášť.
6. Doplňte identitu obchodníka, kontakty, adresu/pokyny pro vrácení a URL zásad ochrany osobních údajů.
7. Ověřte přímý formulář, bezpečný proces objednávky, e-mail, PDF a Back Office.
8. Proveďte akceptační testy na stagingu.

Verze **1.0.0** je první stabilní veřejné vydání. Další funkční nebo kompatibilitní změny použijí nové číslo verze.

Při aktualizaci instalace, která používala předprodukční testovací balík `1.0.0` nahraďte celý adresář modulu, v každém kontextu obchodu jednou otevřete konfiguraci modulu a vymažte mezipaměť PrestaShopu. Konfigurační stránka idempotentně doplní chybějící runtime konfiguraci, hooky a bezpečnostní hodnoty bez změny existujícího tajemství nebo odstranění přijatých odstoupení. Viz lokální projektová dokumentace.

## Zákaznické procesy

### Přímé odstoupení

Spotřebitel může odeslat jednoznačné prohlášení bez předchozího zobrazení objednávky. Formulář shromažďuje pouze údaje potřebné k identifikaci spotřebitele a smlouvy a k odeslání potvrzení.

### Bezpečný proces podle objednávky

Reference objednávky je pouze identifikátor. Veřejná stránka neprozradí existenci objednávky, jejího vlastníka ani e-mail. Pokud je objednávka nalezena v aktuálním obchodě, jednorázový odkaz se odešle na uložený e-mail objednávky. GET token nespotřebuje; pokračování vyžaduje CSRF chráněný POST.

## Administrace a údržba

Modul přidává **Objednávky → Odstoupení od smlouvy** a panel do detailu objednávky. Původní podání se zpětně nepřepisuje; změny se přidávají do historie.

```bash
php bin/console jooim:withdrawal:maintenance
```

HTTP cron je chráněn tajným tokenem. Token ani URL nezveřejňujte.

## Podpora Joobox pro tento modul

Bezplatná GitHub distribuce neobsahuje individuální technickou podporu. Instalace, kontrola konfigurace, produkční testování, aktualizace a řešení problémů mohou být poskytovány jako placená služba Joobox PrestaShop Cloud nebo JOO INTERNET MEDIA LTD. Viz [SUPPORT.md](SUPPORT.md).

## Atribuce Joobox v patičce

Modul zobrazuje:

```text
Modul PrestaShop od joobox.eu
```

Pokud už stejnou atribuci zobrazil jiný modul JOOIM, duplicitní kopie se odstraní. To neovlivňuje právně relevantní odkaz **Odstoupit od smlouvy**.

Právně relevantní odkaz **Odstoupit od smlouvy** se ve standardním zápatí PrestaShop `ps_linklist` vloží jako běžná položka seznamu bezprostředně za statický odkaz **Prodejny**. Modul používá stabilní prefix ID `link-static-page-stores-` a převezme CSS třídy sousedního odkazu, takže vzhled i mobilní rozbalování řídí aktivní téma. `displayFooter` zůstává přístupným fallbackem bez JavaScriptu; `displayFooterPart1` již nevykresluje viditelný prvek, protože některé komerční šablony tento hook umisťují nad skutečné zápatí webu.

Ochrana CSRF používá samostatný malý soubor cookie `jooim_withdrawal_csrf` (`Secure` při HTTPS, `HttpOnly`, `SameSite=Lax`) a nezvětšuje hlavní soubor cookie PrestaShopu. Konfigurace zobrazuje oba veřejné odkazy pro každý aktivní jazyk obchodu a označí chybějící překlad jako anglický fallback.

## Kontrola aktualizací

Konfigurace obsahuje pouze čtecí kontrolu nejnovějšího GitHub Release a výsledek ukládá na šest hodin. Modul nikdy automaticky nestahuje ani nepřepisuje soubory.

## Jazyky a vývoj

Zdroj a fallback: angličtina. Překlady: čeština, slovenština, němčina, francouzština a polština. E-mailové šablony jsou ve stejných jazycích.

```bash
php tests/static_validation.php
```

Statická kontrola nenahrazuje test na reálné instalaci PrestaShopu.

## Mimo rozsah verze 1.0.0

Automatické refundace, vratkové štítky, skladové pohyby, účetnictví, plný reklamační/RMA systém, veřejné API a automatické právní rozhodování.

## Licence

Modul je distribuován pod [licencí MIT](LICENSE). Copyright © 2026 JOO INTERNET MEDIA LTD.


## Ďalšie jazykové názvy / Other language terms

Pozri multilingual terminology and search aliases.
