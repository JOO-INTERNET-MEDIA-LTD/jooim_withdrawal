# JOOIM Withdrawal — Online-Vertragswiderruf

[English](README.md) · [Slovenčina](README.sk.md) · [Čeština](README.cs.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Polski](README.pl.md)

**JOOIM Withdrawal** ist ein PrestaShop-Modul zur Entgegennahme, Bestätigung und administrativen Bearbeitung von Online-Widerrufserklärungen. Es bietet eine direkte Online-Widerrufsfunktion, einen sicheren Bestellreferenz-Ablauf, vollständige und teilweise Widerrufe, unveränderliche Nachweise, E-Mail-/PDF-Bestätigungen, Multistore-Trennung und eine Audit-Historie.

Repository: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal  
Autor: JOO INTERNET MEDIA LTD  
Technischer Name und erforderlicher Modulordner: `jooim_withdrawal`  
Release-Version: `1.0.0`  
Status: **stabile öffentliche Version**

Englisch ist die Quellsprache für Code, Templates, Dokumentation und Übersetzungskataloge. Deutsch, Slowakisch, Tschechisch, Französisch und Polnisch werden als Übersetzungen bereitgestellt.

## Hauptfunktionen

- direkte Online-Widerrufsfunktion mit eindeutiger finaler Bestätigung;
- sichere Suche über die Bestellreferenz ohne Offenlegung personenbezogener Daten vor der Verifizierung;
- einmaliger HMAC-geschützter Zugangslink an die zur Bestellung gespeicherte E-Mail-Adresse;
- Verbrauch des Tokens erst nach einer POST-Bestätigung, nicht durch bloßes Öffnen des Links;
- Abläufe für angemeldete Kunden und verifizierte Gäste;
- vollständiger oder teilweiser Widerruf nach Position und Menge;
- freiwilliger Grund und optionale Notiz;
- unveränderlicher JSON-Snapshot mit SHA-256-Hash;
- Bildschirm-, E-Mail- und nicht öffentlich zugängliche PDF-Bestätigung;
- Back-Office-Liste, Falldetail, Historie, manuelle Erfassung, Erstattungsevidenz und CSV-Export;
- shopbezogene Händlerdaten und Bestellisolierung im Multistore;
- CSRF-Schutz, Idempotenz, Rate Limiting, Eigentumsprüfung und neutrale Anti-Enumeration-Antworten;
- Wartung über Symfony CLI oder einen token-geschützten HTTP-Endpunkt;
- normale Deinstallation bewahrt rechtlich relevante Widerrufsdatensätze.

## Rechtlicher Umfang

Das Modul orientiert sich am EU-Verbraucherrecht und an den Anforderungen an eine Online-Widerrufsfunktion für einschlägige Fernabsatzverträge, die über eine Online-Benutzeroberfläche geschlossen wurden. Eine Erklärung wird nicht allein aufgrund eines Datums, Bestellstatus oder Produkttyps automatisch abgelehnt.

Der Händler bleibt für die rechtliche Einordnung, korrekte Händlerdaten, nationale Texte, Ausnahmen, Rücksendekosten, Erstattungsentscheidungen und Aufbewahrungsfristen verantwortlich. Siehe lokale Projektdokumentation.

## Anforderungen

- PrestaShop 8.1.x bis 9.1.x;
- PHP 8.1 oder neuer;
- eine von PrestaShop unterstützte MySQL-/MariaDB-Version;
- funktionierende PrestaShop-Mailkonfiguration;
- TCPDF über PrestaShop;
- beschreibbares privates `var/`-Verzeichnis.

Das Modul verwendet keine Core-Overrides.

## Installation

1. Verwenden Sie das Installationspaket `jooim_withdrawal-1.0.0.zip`.
2. Verwenden Sie nicht das automatisch erzeugte GitHub-Archiv **Source code (zip)**.
3. Öffnen Sie im Back Office **Module → Modulmanager → Modul hochladen**.
4. Laden Sie das ZIP hoch und installieren Sie das Modul.
5. Konfigurieren Sie im Multistore jeden Shop separat.
6. Hinterlegen Sie Händlername, Anschrift, Kennung, Kontakte, Rücksendeadresse/-hinweise und Datenschutz-URL.
7. Testen Sie Direktformular, sicheren Bestellablauf, E-Mail, PDF und Back Office.
8. Führen Sie die Abnahmetests auf einer Staging-Kopie durch.

Version **1.0.0** ist die erste stabile öffentliche Veröffentlichung. Spätere Funktions- oder Kompatibilitätsänderungen erhalten eine neue Versionsnummer.

Beim Aktualisieren einer Installation, die ein Vorab-Testpaket `1.0.0` verwendet hat muss das vollständige Modulverzeichnis ersetzt, die Modulkonfiguration einmal je Shop-Kontext geöffnet und der PrestaShop-Cache geleert werden. Die Konfigurationsseite ergänzt fehlende Laufzeitkonfiguration, Hooks und Sicherheitswerte idempotent, ohne ein vorhandenes Geheimnis zu ändern oder eingegangene Widerrufe zu löschen. Siehe lokale Projektdokumentation.

## Kundenabläufe

### Direkter Widerruf

Verbraucher können eine eindeutige Widerrufserklärung absenden, ohne zuvor Bestelldaten anzuzeigen. Es werden nur die zur Identifikation des Verbrauchers und Vertrags sowie zur Bestätigung erforderlichen Daten abgefragt.

### Sicherer Ablauf über die Bestellreferenz

Die Bestellreferenz ist nur ein Identifikator. Die öffentliche Seite bestätigt weder die Existenz der Bestellung noch Eigentümer oder E-Mail-Adresse. Bei einem Treffer im aktuellen Shop wird ein einmaliger Link an die gespeicherte Bestell-E-Mail versandt. GET verbraucht den Token nicht; die Fortsetzung erfordert einen CSRF-geschützten POST.

## Administration und Wartung

Das Modul ergänzt **Bestellungen → Vertragswiderrufe** sowie ein Panel in der Bestelldetailansicht. Die ursprüngliche Erklärung wird nicht rückwirkend verändert; spätere Vorgänge werden als Historie ergänzt.

```bash
php bin/console jooim:withdrawal:maintenance
```

Der alternative HTTP-Cron ist durch ein geheimes Token geschützt. URL und Token dürfen nicht veröffentlicht werden.

## Joobox-Support für dieses Modul

Die kostenlose GitHub-Version enthält keinen individuellen technischen Support. Installation, Konfigurationsprüfung, Produktivtests, Updates und Fehlerbehebung können als kostenpflichtige Leistung über Joobox PrestaShop Cloud oder JOO INTERNET MEDIA LTD angeboten werden. Siehe [SUPPORT.md](SUPPORT.md).

## Joobox-Footer-Attribution

Das Modul zeigt:

```text
PrestaShop-Modul von joobox.eu
```

Wenn ein anderes JOOIM-Modul diese Attribution bereits ausgegeben hat, wird das Duplikat entfernt. Der rechtlich relevante Link **Vertrag widerrufen** wird in standardmäßigen PrestaShop-`ps_linklist`-Footern als normaler Listeneintrag unmittelbar nach dem statischen Link **Filialen** eingefügt. Dazu nutzt das Modul den stabilen ID-Präfix `link-static-page-stores-` und übernimmt die CSS-Klassen des benachbarten Links, sodass Darstellung und mobiles Aufklappen vom aktiven Theme gesteuert werden. `displayFooter` bleibt als barrierearmer Fallback ohne JavaScript erhalten; `displayFooterPart1` wird nicht mehr visuell ausgegeben, weil einige kommerzielle Themes diesen Hook oberhalb des eigentlichen Site-Footers rendern.

Der CSRF-Schutz verwendet das kleine separate Cookie `jooim_withdrawal_csrf` (`Secure` bei HTTPS, `HttpOnly`, `SameSite=Lax`) und vergrößert das PrestaShop-Hauptcookie nicht. Die Konfiguration zeigt beide öffentlichen URLs für jede aktive Shop-Sprache und kennzeichnet fehlende Übersetzungen als englischen Fallback.

## Update-Prüfung

Die Konfiguration enthält eine rein lesende Prüfung des neuesten GitHub Release. Das Ergebnis wird sechs Stunden zwischengespeichert. Dateien werden niemals automatisch heruntergeladen oder überschrieben.

## Sprachen und Entwicklung

Quelle/Fallback: Englisch. Übersetzungen: Deutsch, Slowakisch, Tschechisch, Französisch und Polnisch. E-Mail-Templates sind in denselben Sprachen enthalten.

```bash
php tests/static_validation.php
```

Statische Prüfungen ersetzen keinen Integrationstest in einer realen PrestaShop-Umgebung.

## Nicht Bestandteil von Version 1.0.0

Automatische Zahlungsrückerstattungen, Retourenlabels, automatische Lagerbewegungen, Buchhaltung, vollständiges Reklamations-/RMA-System, öffentliche API und automatische rechtliche Entscheidungen.

## Lizenz

Veröffentlicht unter der [MIT-Lizenz](LICENSE). Copyright © 2026 JOO INTERNET MEDIA LTD.


## Ďalšie jazykové názvy / Other language terms

Pozri multilingual terminology and search aliases.
