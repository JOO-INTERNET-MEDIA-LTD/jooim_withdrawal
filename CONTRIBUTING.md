# Contributing

## Development baseline

- Keep the module technical name and folder name exactly `jooim_withdrawal`.
- Follow semantic versioning after the stable `1.0.0` release; add an upgrade path when schema or configuration changes require one.
- Use PHP 8.1-compatible syntax and no PrestaShop core override.
- Treat English as the source language for code, templates and documentation.
- Use explicit translation domains based on `Jooimwithdrawal`.
- Preserve placeholders and technical identifiers in all translations.
- Do not weaken order ownership, token, CSRF, idempotency, multistore or document-access controls.
- Do not physically rewrite or delete submitted withdrawal records through normal operational actions.

## Before opening a pull request

Run:

```bash
php tests/static_validation.php
```

Also lint every PHP file, validate all XLIFF/XML files, test the installation archive and complete the relevant staging scenarios in the staging acceptance scenarios maintained locally.

Do not commit secrets, cron tokens, customer data, generated withdrawal PDFs or production logs.
