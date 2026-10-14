# JOOIM Withdrawal — Online Contract Withdrawal

[![Quality](https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/actions/workflows/quality.yml/badge.svg)](https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/actions/workflows/quality.yml) [![Release](https://img.shields.io/github/v/release/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal)](https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/releases) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

[English](README.md) · [Slovenčina](README.sk.md) · [Čeština](README.cs.md) · [Deutsch](README.de.md) · [Français](README.fr.md) · [Polski](README.pl.md)

**JOOIM Withdrawal** is a PrestaShop module for receiving, confirming and administering online consumer contract withdrawals. It provides a direct online withdrawal function, a secure order-reference flow, full and partial withdrawals, immutable evidence, email/PDF acknowledgements, multistore separation and an administrative audit trail.

Common local search terms include **odstúpenie od zmluvy**, **odstoupení od smlouvy**, **Vertragswiderruf**, **rétractation du contrat**, **odstąpienie od umowy**, **desistimiento del contrato** and **recesso dal contratto**. See [multilingual terminology](docs/TERMINOLOGY.md).

Repository: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal  
Author: JOO INTERNET MEDIA LTD  
Technical name and required module folder: `jooim_withdrawal`  
Release version: `1.1.0`
Status: **stable public release**

English is the source language for code, templates, documentation and translation catalogues. Complete five-domain catalogues and email templates are supplied for all packaged locales listed below.

## Main functions

- direct online contract-withdrawal function with an explicit final confirmation;
- secure lookup by order reference without exposing personal or order data before verification;
- one-time HMAC-protected access link sent to the email associated with the order;
- access token consumed only after a POST confirmation, so email security scanners do not invalidate it;
- logged-in customer and verified guest flows;
- full withdrawal or withdrawal from selected order items and quantities;
- optional reason and note; the reason is never mandatory;
- immutable JSON submission snapshot with SHA-256 hash;
- transactional withdrawal reference sequence scoped by shop and year;
- immediate on-screen acknowledgement, email acknowledgement and private PDF document;
- administrative list, case detail, status history, notes, manual records, refund evidence and CSV export;
- multistore-specific merchant data, return instructions, legal texts and order isolation;
- rate limiting, browser-bound CSRF protection in a dedicated small cookie, idempotency, ownership checks and neutral anti-enumeration responses;
- maintenance through a Symfony console command or a token-protected HTTP endpoint;
- normal uninstall preserves legally relevant withdrawal records.

## Legal scope

The module is designed around the EU consumer withdrawal framework and the online withdrawal-function requirements applicable to qualifying distance contracts concluded through an online interface. It deliberately does not automatically reject a submission merely because a date, order status or product category suggests a possible exception.

This software is an operational tool, not a substitute for legal advice. The merchant remains responsible for:

- determining whether consumer withdrawal law applies to a specific contract;
- configuring the correct merchant identity, return address, privacy information and market-specific wording;
- reviewing national implementation, statutory exceptions, return costs and sector-specific requirements;
- deciding whether a withdrawal is valid and how much must be refunded;
- setting lawful retention periods for submitted records and technical logs.

See [docs/LEGAL-COMPLIANCE.md](docs/LEGAL-COMPLIANCE.md).

Footer integration details are documented in [docs/FOOTER-PLACEMENT.md](docs/FOOTER-PLACEMENT.md).

## Requirements

- PrestaShop 8.x or 9.x running PHP 8.1 or newer;
- PHP 8.1 or newer;
- MySQL or MariaDB supported by the installed PrestaShop version;
- working PrestaShop email configuration;
- TCPDF available through PrestaShop;
- writable private storage under the PrestaShop `var/` directory.

The module uses no PrestaShop core override.

## Installation

1. Download the dedicated installation ZIP named `jooim_withdrawal-1.1.0.zip` from the GitHub Release assets.
2. Do **not** use GitHub's automatically generated **Source code (zip)** archive for installation. Its folder layout is intended for developers, not the PrestaShop Module Manager.
3. In PrestaShop Back Office, open **Modules → Module Manager → Upload a module**.
4. Upload the installation ZIP and install the module.
5. Open the module configuration for each shop in a multistore installation.
6. Configure the merchant name, registered address, company identifier, contact details, return address/instructions and privacy-policy URL.
7. Verify the direct withdrawal page, secure order-reference flow, email delivery, PDF generation and Back Office case handling.
8. Complete the staging tests in [docs/ACCEPTANCE-TESTS.md](docs/ACCEPTANCE-TESTS.md) before any production deployment.

Version `1.1.0` adds complete locale packages, globally unique multistore references and post-commit recovery. Existing withdrawal records and references are preserved when upgrading. The declared compatibility range must still be verified on the merchant’s staging installation.

When upgrading an installation that used a pre-release `1.0.0` test build, replace the complete module directory, open the module configuration once for each shop context and clear the PrestaShop cache. The configuration page idempotently repairs missing runtime configuration, hooks and security values without rotating an existing secret or deleting submitted records. See [docs/INSTALLATION.md](docs/INSTALLATION.md#6-replacing-an-earlier-100-test-build).

## Public customer flows

### Direct withdrawal

The consumer can submit an unequivocal withdrawal statement without first displaying an order. The form collects only the information needed to identify the consumer and contract, review the statement and send the acknowledgement.

### Secure order-reference flow

The public form accepts an order reference but treats it only as an identifier. It does not disclose whether the order exists, who owns it or which email address is attached. If the order is found in the current shop, a one-time continuation link is sent to the stored order email.

Opening the link through GET does not consume it. The customer must explicitly continue through a CSRF-protected POST request. CSRF tokens use a dedicated host-only `jooim_withdrawal_csrf` cookie (`Secure` on HTTPS, `HttpOnly`, `SameSite=Lax`) and never add data to PrestaShop’s encrypted main cookie. The resulting guest session is scoped to the shop, order, customer and withdrawal purpose.

## Administration and maintenance

The module adds a language-specific **Orders → Contract withdrawals** tab in Back Office and a withdrawal panel to the order detail. Submitted records are not physically editable; operational changes are added as history events. The tab label follows the signed-in employee language when a packaged label exists and falls back to English otherwise.

Recommended CLI maintenance command:

```bash
php bin/console jooim:withdrawal:maintenance
```

A token-protected HTTP maintenance URL is available for hosting without CLI cron. Never publish the cron URL or token and never commit a real token to GitHub.

## Joobox support for this module

The free GitHub module is provided without individual technical support. Installation, configuration review, production testing, updates, troubleshooting and custom development may be available through Joobox PrestaShop Cloud or another paid service explicitly agreed with JOO INTERNET MEDIA LTD.

The configuration page automatically opens the localized Joobox support destination for the active Back Office language, including Slovak, Czech, English, German, French, Italian and Polish variants.

See [SUPPORT.md](SUPPORT.md).

## Joobox footer attribution

The module displays a small frontend attribution:

```text
PrestaShop module by joobox.eu
```

The fixed `joobox.eu` text links to `https://joobox.eu`. The text before the link is translated from the English source string `PrestaShop module by`.

All JOOIM modules use the same duplicate-prevention convention. If another installed JOOIM module already rendered a Joobox attribution in the footer, `jooim_withdrawal` removes its duplicate and only one attribution remains visible. The legally relevant customer link **Withdraw from contract here** is independent from marketing attribution. On standard PrestaShop `ps_linklist` footers, the module inserts it as a normal list item immediately after the static **Stores** link (identified by PrestaShop's stable `link-static-page-stores-` id prefix) and copies the neighbouring link classes so the active theme controls typography, spacing, hover states and mobile collapsing. `displayFooter` remains an accessible no-JavaScript and generic-theme fallback. The theme-specific `displayFooterPart1` hook is deliberately not used for visual output because some commercial themes emit it inside the page-content footer above the real site footer.

## GitHub update check

The module configuration includes a read-only GitHub release checker for this repository. It compares the installed version with the latest published release tag and caches the result for six hours.

It never downloads, extracts or overwrites module files automatically. Updates must be installed from the dedicated PrestaShop installation ZIP attached to a GitHub Release.

## Included languages

English (`en-US`) is the authoritative source and fallback. All packaged locales
contain the Admin, Shop, Email, Pdf and Validation domains and localized mail
text. The package covers English, French, German, Polish, Czech, Slovak, Spanish,
Italian, Portuguese, Dutch, Luxembourgish, Danish, Swedish, Norwegian Bokmål and
Nynorsk, Finnish, Ukrainian, Bulgarian, Croatian, Estonian, Greek, Hungarian,
Irish, Latvian, Lithuanian, Maltese, Romanian and Slovenian.

Belgian variants include `fr-BE`, `nl-BE` and `de-BE`; Canada includes `en-CA` and
`fr-CA`. Legacy `no-NO` reuses Bokmål. Regional French, German and Dutch variants
reuse their language's wording and can be customized independently by merchants.

Translation completeness is checked against the English source, placeholders
and mail variables. Newly added translations have not received independent
native-speaker or market-specific legal review. See `tools/locales.json` for the
locale-to-mail-language map.

## Localized routes and theme integration

Each active language receives a language-appropriate friendly route rather than a Slovak slug under every language prefix. Examples:

```text
/sk/odstupenie-od-zmluvy
/en/contract-withdrawal
/uk/vidmova-vid-dohovoru
/de/vertragswiderruf
```

Use the language-neutral `entry` controller in **Design → Link List**, because that module stores only one URL for all translated labels. The complete route map and universal URLs are documented in [docs/LOCALIZED-ROUTES.md](docs/LOCALIZED-ROUTES.md).

Public withdrawal templates extend the active theme's `page.tpl`. For a signed-in customer they use `customer/page.tpl` when the active theme or its declared parent provides it, so the process inherits the shop header, menu, account shell, typography, content container and footer instead of rendering an isolated module page.

## Development and validation

Static validation can be run from the module root:

```bash
php tests/static_validation.php
```

The test package also requires PHP linting, XLIFF/XML parsing, archive integrity checks and staging integration tests. Static checks cannot prove compatibility with every theme, mail transport, payment module, database version or national legal implementation.

## Out of scope for version 1.1.0

- automatic payment refunds;
- carrier return labels;
- automatic stock movements;
- accounting integration;
- full complaints/RMA management;
- automatic legal rejection of products or submissions;
- public API and AI-based legal assessment.

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Acceptance tests](docs/ACCEPTANCE-TESTS.md)
- [Legal compliance notes](docs/LEGAL-COMPLIANCE.md)
- [Localized routes](docs/LOCALIZED-ROUTES.md)
- [Translations](docs/TRANSLATIONS.md)
- [Multilingual terminology and search aliases](docs/TERMINOLOGY.md)
- [Build report](docs/BUILD-REPORT.md)
- [GitHub repository description and topics](docs/GITHUB-REPOSITORY.md)
- [Security policy](SECURITY.md)
- [Support policy](SUPPORT.md)

## License

The module is distributed under the [MIT License](LICENSE). Copyright © 2026 JOO INTERNET MEDIA LTD.


<!-- UNIVERSAL-ENTRY-1.0.0 -->
## Language-neutral public links

Use these URLs in CMS blocks or PrestaShop **Design → Link List**. They do not
contain a fixed language prefix. The entry controller selects an active shop
language from the current shop page, PrestaShop cookie, browser preferences and
finally the shop default, then redirects to the localized module route.

```text
/index.php?fc=module&module=jooim_withdrawal&controller=entry&target=direct
/index.php?fc=module&module=jooim_withdrawal&controller=entry&target=identify
```

The footer link points to the localized order-reference entry page (for example
`/sk/odstupenie-od-zmluvy`). That page keeps the direct withdrawal function visibly
available for customers who cannot use the original order email.
