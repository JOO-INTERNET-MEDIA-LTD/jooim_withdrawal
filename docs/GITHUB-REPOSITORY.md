# GitHub repository metadata

Repository:

```text
https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal
```

## Recommended GitHub “About” description

```text
Free multilingual PrestaShop 8/9 module for online contract withdrawal — odstúpenie od zmluvy, odstoupení od smlouvy, Vertragswiderruf, rétractation — with EU Article 11a flows, PDF acknowledgements and multistore.
```

## Recommended website

```text
https://joobox.eu
```

## Recommended topics

GitHub permits at most 20 repository topics. Use these 20 topics:

```text
prestashop
prestashop-module
prestashop-8
prestashop-9
php8
ecommerce
consumer-rights
consumer-protection
eu-consumer-law
contract-withdrawal
online-withdrawal
multistore
joobox
odstupenie-od-zmluvy
odstoupeni-od-smlouvy
vertragswiderruf
retractation-du-contrat
odstapienie-od-umowy
desistimiento-del-contrato
recesso-dal-contratto
```

Additional localized search terms are documented in [TERMINOLOGY.md](TERMINOLOGY.md).

## Repository language strategy

- `README.md` is the canonical English source document.
- `README.sk.md`, `README.cs.md`, `README.de.md`, `README.fr.md` and `README.pl.md` are maintained translations.
- English is the source language for PHP, Smarty, email variables and XLIFF `<source>` values.
- Translations must not introduce or rename technical identifiers, placeholders, database columns, configuration keys or template variables.

## Visibility and discovery

A private repository is visible only to users who already have access. Public discovery through GitHub topics, repository search and external search engines requires repository visibility `public`.

Keep the repository private while importing and running the first GitHub Actions workflow. Change it to public only after the source, workflow output and repository history have been checked for secrets and production data.

## Stable release policy

Version `1.0.0` is the first stable public release. Later functional or compatibility changes must use a new semantic version and an upgrade path when database or configuration changes require one.

Attach the dedicated PrestaShop installation asset to release tag `v1.0.0`:

```text
jooim_withdrawal-1.0.0.zip
jooim_withdrawal-1.0.0.sha256.txt
```

The installation ZIP must contain exactly one top-level folder named:

```text
jooim_withdrawal/
```

GitHub-generated **Source code** archives are development snapshots and must not be presented as PrestaShop Module Manager installation packages.
