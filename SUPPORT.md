# Support

[English](SUPPORT.md) · [Slovenčina](README.sk.md#joobox-podpora-pre-tento-modul) · [Čeština](README.cs.md#podpora-joobox-pro-tento-modul) · [Deutsch](README.de.md#joobox-support-für-dieses-modul) · [Français](README.fr.md#support-joobox-pour-ce-module) · [Polski](README.pl.md#wsparcie-joobox-dla-tego-modułu)

The free GitHub distribution of **JOOIM Withdrawal** does not include individual technical support, installation work or production approval.

Installation, configuration review, staging and production testing, troubleshooting, updates, integrations and custom modifications may be available as a paid service through Joobox PrestaShop Cloud or another service explicitly agreed with JOO INTERNET MEDIA LTD.

## Before requesting support

1. Confirm that the module is installed in `modules/jooim_withdrawal/`.
2. Confirm the exact PrestaShop, PHP, database and module versions.
3. Test on a staging clone, not first on the live shop.
4. Open the module configuration separately for every shop in multistore.
5. Complete the trader identity, return instructions and privacy-policy URL.
6. Verify the direct withdrawal page and the secure order-reference flow.
7. Verify SMTP delivery and PDF generation.
8. Run `php tests/static_validation.php` from the module root.
9. Check PrestaShop logs, PHP logs and the module mail/audit records.
10. Remove passwords, tokens and customer data before sharing diagnostics.

## Information to include

- PrestaShop version and theme;
- PHP version;
- MySQL or MariaDB version;
- exact module version;
- single-shop or multistore context;
- shop and Back Office language;
- exact reproduction steps;
- exact error message and relevant sanitized log lines;
- whether the issue affects direct withdrawal, magic-link access, email, PDF, Back Office or maintenance cron.

## Localized Joobox support URLs

The configuration page selects a support destination from the active Back Office language:

- Slovak: https://joobox.eu/sk-sk/podpora
- Czech: https://joobox.eu/cs-cz/podpora
- English (UK): https://joobox.eu/en-gb/support
- English (Canada): https://joobox.eu/en-ca/support
- German (Germany): https://joobox.eu/de-de/unterstutzung
- German (Austria): https://joobox.eu/de-at/unterstutzung
- German (Switzerland): https://joobox.eu/de-ch/unterstutzung
- French (France): https://joobox.eu/fr-fr/soutien
- French (Canada): https://joobox.eu/fr-ca/support
- Italian: https://joobox.eu/it-it/supporto
- Polish: https://joobox.eu/pl-pl/wsparcie

## Security reports

Do not publish vulnerabilities, access tokens, cron tokens, customer data or withdrawal documents in a public GitHub issue. Follow [SECURITY.md](SECURITY.md) and contact the vendor privately.
