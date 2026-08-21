# Tests

Run the dependency-free static release checks from the module root:

```bash
php tests/static_validation.php
```

Run the targeted regression smoke test for the storefront failure paths, module-secret recovery and documented Back Office settings:

```bash
php tests/runtime_smoke.php
```


Run the transaction and final-POST regression gate with:

```bash
php tests/submission_pipeline_smoke.php
php tests/submission_controller_lifecycle_smoke.php
php tests/submission_action_lifecycle_smoke.php
php tests/submission_transaction_smoke.php
```

These tests prevent `FOR UPDATE` reads from using PrestaShop helpers that append `LIMIT 1`, verify SQL escaping of immutable JSON snapshots, and protect the PrestaShop 8 controller lifecycle through both source-level and runtime-model gates. The final `submit` and `directsubmit` POST endpoints must commit in `postProcess()`, disable storefront rendering and redirect before `initContent()` can execute any third-party `displayHeader` hook.

Validate the English-source XLIFF catalogues, supplied translations and localized mail-template variables with:

```bash
python3 tests/validate_catalogs.py
```

The GitHub Actions workflow executes all release checks with PHP 8.1 and PHP 8.4 on every push and pull request. These tests do not replace the live PrestaShop integration matrix in `docs/ACCEPTANCE-TESTS.md`.

Validate localized withdrawal status labels, Back Office options and audit-history presentation with:

```bash
php tests/status_localization_smoke.php
```
