# JOOIM Withdrawal 1.0.0 — public release build report

- Module: `jooim_withdrawal`
- Release: `1.0.0`
- Release date: 2026-08-21
- Vendor: JOO INTERNET MEDIA LTD
- License: MIT
- Status: stable public release

## Release scope

The release contains the secure order-reference flow, direct online withdrawal, full and partial withdrawal, immutable evidence, email/PDF acknowledgement, multistore administration, localized status presentation, localized routes, audit history and maintenance tooling described in the repository documentation.

The stable database status codes remain technical identifiers. Customer and Back Office interfaces use localized human-readable labels, preserving compatibility with history records and integrations.

## Quality gates

The source tree is validated through GitHub Actions and the bundled checks:

```bash
php tests/static_validation.php
php tests/runtime_smoke.php
php tests/translation_bridge_smoke.php
php tests/status_localization_smoke.php
php tests/submission_pipeline_smoke.php
php tests/submission_controller_lifecycle_smoke.php
php tests/submission_action_lifecycle_smoke.php
php tests/submission_transaction_smoke.php
python3 tests/validate_catalogs.py
```

The workflow also lints every PHP file, checks frontend JavaScript syntax, parses the translation catalogues and verifies that generated translation files are deterministic.

## Submission architecture

Final submission uses a headless POST action followed by Post/Redirect/Get. The legally decisive database transaction completes before optional PDF, email, analytics or integration work. A failure in a post-commit service cannot remove an accepted withdrawal or leave the customer on a partially rendered submission page.

The transaction revalidates the draft, order, customer/shop ownership, order-detail ownership, quantities and idempotency key. Row locks preserve the terminal `FOR UPDATE` clause without relying on PrestaShop helpers that append their own `LIMIT` clause.

## Translation scope

English (`en-US`) is the source and fallback language. Complete customer-facing catalogues and email templates are supplied for English, Slovak, Czech, German, French and Polish. Back Office settings and human-readable statuses are packaged for the locales listed in `docs/TRANSLATION-STATUS.json`.

A settings-only locale is not represented as a fully reviewed customer-facing legal translation.

## Packaging

The GitHub repository ZIP contains source files at archive root. The PrestaShop installation asset `jooim_withdrawal-1.0.0.zip` contains exactly one top-level directory named `jooim_withdrawal/`.

GitHub-generated source archives are development snapshots and must not be used as Module Manager installation packages.

## Operational boundary

Static and runtime smoke tests cannot prove compatibility with every commercial theme, mail transport, payment module, database build or national legal implementation. Merchants must complete the staging scenarios in `docs/ACCEPTANCE-TESTS.md`, configure their own legal and merchant data and review the applicable national rules before production use.
