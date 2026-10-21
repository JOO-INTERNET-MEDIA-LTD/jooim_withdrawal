# Customer Right of Withdrawal

Lets customers exercise and administer the right of withdrawal through authenticated or verified guest flows, with retained records, mail/PDF support and Back Office processing.

| | |
| --- | --- |
| Module | `jooim_withdrawal` |
| Source version | `1.1.1` |
| Target platforms | PrestaShop 8.x and 9.x; staging validation required |
| Author | JOO INTERNET MEDIA LTD |

## Features and scope

Controllers delegate identity lookup, draft/submission validation, period/quantity evaluation and persistence to services. AccessResolver, CSRF, token and rate-limit services gate requests. Submission/recovery services retain evidence and transaction boundaries; storage/mail/PDF services handle confirmation artifacts. Maintenance handles expiry/retries.

## Requirements and compatibility

Use a PHP version supported by your selected PrestaShop release. PHP 8.1 or later is required. No payment-provider credentials. Configure merchant identity, privacy/return address and working shop mail delivery; protect generated access tokens and legal documents.

## Installation

1. Back up shop files/database and use staging first.
2. Download an approved installation ZIP and its SHA256 from the release assets.
3. Verify the checksum and upload the ZIP through Module Manager.
4. Install and select the intended shop before configuration.
5. Test the module’s actual workflow and theme hooks before production.

Do not install GitHub’s automatic **Source code ZIP**. The archive must contain one `jooim_withdrawal/` root with its module entry point.

## Configuration and usage

JOOIM_WITHDRAWAL_* defaults include enabled and footer/account/order/direct switches; WD reference prefix; magic TTL 30 min; idle/max session 30/120 min; resend cooldown 60 s; IP 15m/day 5/20; order hour/day 3/10; note limit 2000; attach PDF=1; retry count=3; privacy URL and merchant/return identity fields. ConfigurationValidator defines ranges and dependencies. Legal records and retained files are scoped to their shop; operational Configuration uses the current context. Use the complete source/default inventory and existing manuals for exact fields.

## Languages

English source uses the module’s existing PrestaShop translation mechanism. Bundled language coverage varies by module and installed shop languages. Belgian locales use French/Dutch/German variants; Canadian locales use English/French variants. New wording may use English fallback where a locale is incomplete. Native-speaker review is not claimed.

## Troubleshooting and known limitations

Legal suitability, actual consumer journey, mail/PDF delivery, retention and authorization need merchant/platform review. Do not describe this module as a blanket legal compliance guarantee.

Check the selected shop, configured credentials/options, assigned hooks, PHP extensions and PrestaShop cache first. Redact access URLs, secrets and customer data before sharing diagnostic output.

## Updates, uninstall and recovery

Apply reviewed installation packages on staging, retain a backup and recheck hooks/translations afterward. Source versions and existing releases are independent for each module. No automatic release or remote installation is performed.

Uninstall removes tabs/operational configuration but preserves legal records and storage for evidence/recovery. A separate explicit purge is destructive. Back up evidence before any purge or rollback.

For rollback, restore matching module files and the corresponding configuration/database backup when necessary.

## License and support

See [LICENSE](LICENSE) for the actual terms.

This free GitHub module is provided without individual technical support. Installation, configuration, production testing, updates and troubleshooting are available to customers of Joobox PrestaShop Cloud or another paid service expressly agreed with JOO INTERNET MEDIA LTD.

[Get Joobox support](https://support.joobox.eu) · [GitHub repository](https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal)

## Translated guides

[Slovak](README.sk.md) · [Czech](README.cs.md) · [German](README.de.md) · [French](README.fr.md) · [Polish](README.pl.md)

English is the source language. Localized guides may retain wording from an earlier source revision; verify settings against the installed version.

## Joobox support for this module

Use the support link above for paid installation, configuration, testing and troubleshooting services.

## Joobox footer attribution

The shared manager shows one eligible “PrestaShop module by joobox.eu” credit across installed modules. A visible existing manual Joobox footer link takes precedence. Cross-module deduplication requires JavaScript and does not hide the legal withdrawal link or cookie controls.

## GitHub update check

The configuration page shows the installed version, latest verified release metadata and last check in UTC. Cached checks are limited to six hours unless manually refreshed. No code is downloaded or installed automatically. Use an approved installation asset such as `jooim_withdrawal-1.1.1.zip` with its matching SHA256; an example filename is not proof that a release has been published.
