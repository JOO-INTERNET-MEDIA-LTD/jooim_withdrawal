# JOOIM Withdrawal — Odstąpienie od umowy online

[English](README.md) · [Slovenčina](README.sk.md) · [Čeština](README.cs.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Polski](README.pl.md)

**JOOIM Withdrawal** to moduł PrestaShop służący do przyjmowania, potwierdzania i administracyjnej obsługi oświadczeń konsumenta o odstąpieniu od umowy online. Zapewnia bezpośrednią funkcję odstąpienia, bezpieczny proces według numeru zamówienia, odstąpienie pełne lub częściowe, niezmienny zapis dowodowy, potwierdzenia e-mail/PDF, separację multistore i historię audytową.

Repozytorium: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal  
Autor: JOO INTERNET MEDIA LTD  
Nazwa techniczna i wymagana nazwa katalogu: `jooim_withdrawal`  
Wersja wydania: `1.0.0`  
Status: **stabilne wydanie publiczne**

Język angielski jest językiem źródłowym kodu, szablonów, dokumentacji i katalogów tłumaczeń. Polski, słowacki, czeski, niemiecki i francuski są dostarczane jako tłumaczenia.

## Najważniejsze funkcje

- bezpośrednia funkcja odstąpienia online z jednoznacznym końcowym potwierdzeniem;
- bezpieczne wyszukiwanie według numeru zamówienia bez ujawniania danych osobowych przed weryfikacją;
- jednorazowy link HMAC wysyłany na adres e-mail przypisany do zamówienia;
- zużycie tokenu dopiero po potwierdzeniu POST, a nie po samym otwarciu linku;
- proces dla zalogowanego klienta i zweryfikowanego gościa;
- odstąpienie od całej umowy lub wybranych pozycji i ilości;
- opcjonalny powód i notatka;
- niezmienny snapshot JSON z hashem SHA-256;
- potwierdzenie na ekranie, e-mailem i w prywatnym PDF;
- lista administracyjna, szczegóły sprawy, historia, wpisy ręczne, ewidencja zwrotu środków i eksport CSV;
- dane sprzedawcy i izolacja zamówień dla każdego sklepu w multistore;
- CSRF, idempotencja, rate limiting, kontrola własności i neutralne odpowiedzi przeciw enumeracji;
- utrzymanie przez Symfony CLI lub endpoint HTTP chroniony tokenem;
- zwykłe odinstalowanie zachowuje prawnie istotne rekordy odstąpień.

## Zakres prawny

Moduł został zaprojektowany w oparciu o unijne ramy prawa konsumenta i wymogi dotyczące funkcji odstąpienia online dla właściwych umów zawartych na odległość przez interfejs online. Nie odrzuca automatycznie oświadczenia wyłącznie na podstawie daty, statusu zamówienia lub typu produktu.

Sprzedawca odpowiada za ocenę prawną, prawidłowe dane przedsiębiorcy, teksty krajowe, wyjątki ustawowe, koszty zwrotu, decyzję o refundacji i okresy retencji. Zobacz lokalna dokumentacja projektu.

## Wymagania

- PrestaShop 8.1.x–9.1.x;
- PHP 8.1 lub nowszy;
- MySQL/MariaDB obsługiwane przez używaną wersję PrestaShop;
- działająca konfiguracja poczty PrestaShop;
- TCPDF dostępny przez PrestaShop;
- zapisywalny prywatny katalog `var/`.

Moduł nie używa override rdzenia PrestaShop.

## Instalacja

1. Użyj pakietu instalacyjnego `jooim_withdrawal-1.0.0.zip`.
2. Nie używaj automatycznego archiwum GitHub **Source code (zip)**.
3. W Back Office otwórz **Moduły → Menedżer modułów → Prześlij moduł**.
4. Prześlij ZIP i zainstaluj moduł.
5. W multistore skonfiguruj każdy sklep osobno.
6. Uzupełnij dane sprzedawcy, kontakty, adres/instrukcje zwrotu i URL polityki prywatności.
7. Przetestuj formularz bezpośredni, bezpieczny proces zamówienia, e-mail, PDF i Back Office.
8. Wykonaj testy akceptacyjne na środowisku staging.

Wersja **1.0.0** jest pierwszym stabilnym wydaniem publicznym. Kolejne zmiany funkcjonalne lub kompatybilności otrzymają nowy numer wersji.

Podczas aktualizacji instalacji, która używała przedpremierowego pakietu testowego `1.0.0` należy wymienić cały katalog modułu, jednokrotnie otworzyć konfigurację modułu w każdym kontekście sklepu i wyczyścić pamięć podręczną PrestaShop. Strona konfiguracji idempotentnie uzupełnia brakującą konfigurację uruchomieniową, hooki i wartości bezpieczeństwa bez zmiany istniejącego sekretu ani usuwania otrzymanych odstąpień. Zobacz lokalna dokumentacja projektu.

## Procesy klienta

### Bezpośrednie odstąpienie

Konsument może wysłać jednoznaczne oświadczenie bez wcześniejszego wyświetlania zamówienia. Formularz zbiera wyłącznie dane potrzebne do identyfikacji konsumenta i umowy oraz wysłania potwierdzenia.

### Bezpieczny proces według zamówienia

Numer zamówienia jest tylko identyfikatorem. Strona publiczna nie potwierdza istnienia zamówienia, właściciela ani adresu e-mail. Po znalezieniu zamówienia w bieżącym sklepie jednorazowy link trafia na zapisany adres. Żądanie GET nie zużywa tokenu; kontynuacja wymaga chronionego przez CSRF żądania POST.

## Administracja i utrzymanie

Moduł dodaje **Zamówienia → Odstąpienia od umowy** oraz panel w szczegółach zamówienia. Pierwotne oświadczenie nie jest przepisywane; kolejne operacje są dopisywane do historii.

```bash
php bin/console jooim:withdrawal:maintenance
```

Alternatywny cron HTTP jest chroniony tajnym tokenem. Nie publikuj adresu URL ani tokenu.

## Wsparcie Joobox dla tego modułu

Bezpłatna dystrybucja GitHub nie obejmuje indywidualnego wsparcia technicznego. Instalacja, przegląd konfiguracji, testy produkcyjne, aktualizacje i rozwiązywanie problemów mogą być świadczone jako płatna usługa Joobox PrestaShop Cloud lub JOO INTERNET MEDIA LTD. Zobacz [SUPPORT.md](SUPPORT.md).

## Atrybucja Joobox w stopce

Moduł wyświetla:

```text
Moduł PrestaShop od joobox.eu
```

Jeżeli inny moduł JOOIM wyświetlił już tę atrybucję, duplikat zostanie usunięty. Prawnie istotny link **Odstąp od umowy** jest w standardowej stopce PrestaShop `ps_linklist` dodawany jako zwykły element listy bezpośrednio po statycznym linku **Sklepy**. Moduł rozpoznaje stabilny prefiks identyfikatora `link-static-page-stores-` i kopiuje klasy CSS sąsiedniego linku, dlatego wygląd oraz rozwijanie mobilne pozostają pod kontrolą aktywnego motywu. `displayFooter` pozostaje dostępnym fallbackiem bez JavaScriptu; `displayFooterPart1` nie generuje już widocznego elementu, ponieważ część motywów komercyjnych renderuje ten hook nad właściwą stopką witryny.

Ochrona CSRF używa osobnego małego pliku cookie `jooim_withdrawal_csrf` (`Secure` przy HTTPS, `HttpOnly`, `SameSite=Lax`) i nie powiększa głównego pliku cookie PrestaShop. Konfiguracja pokazuje oba publiczne adresy URL dla każdego aktywnego języka sklepu i oznacza brakujące tłumaczenie jako angielski fallback.

## Sprawdzanie aktualizacji

Konfiguracja tylko odczytuje najnowszy GitHub Release i buforuje wynik przez sześć godzin. Moduł nigdy automatycznie nie pobiera ani nie nadpisuje plików.

## Języki i rozwój

Źródło/fallback: angielski. Tłumaczenia: polski, słowacki, czeski, niemiecki i francuski. Szablony e-mail są dostępne w tych samych językach.

```bash
php tests/static_validation.php
```

Kontrole statyczne nie zastępują testu integracyjnego na rzeczywistej instalacji PrestaShop.

## Poza zakresem wersji 1.0.0

Automatyczne refundacje, etykiety zwrotne, automatyczne ruchy magazynowe, księgowość, pełny system reklamacji/RMA, publiczne API i automatyczne decyzje prawne.

## Licencja

Moduł jest udostępniany na [licencji MIT](LICENSE). Copyright © 2026 JOO INTERNET MEDIA LTD.


## Ďalšie jazykové názvy / Other language terms

Pozri multilingual terminology and search aliases.
