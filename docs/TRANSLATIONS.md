# Translation workflow

## Source language

English (`en-US`) is the authoritative source language for this module.

New user-visible strings must first be written in English in PHP or Smarty and must use an explicit translation domain:

- `Modules.Jooimwithdrawal.Admin`
- `Modules.Jooimwithdrawal.Shop`
- `Modules.Jooimwithdrawal.Email`
- `Modules.Jooimwithdrawal.Pdf`
- `Modules.Jooimwithdrawal.Validation`

The translation base is `Jooimwithdrawal`. Do not derive it from the underscored technical name `jooim_withdrawal`.

## Translation scopes

The repository deliberately distinguishes **Back Office settings coverage** from a **complete customer-facing translation**.

### Complete reviewed module catalogues

The following locales contain all five XLIFF domains and have corresponding localized mail templates where applicable:

- `en-US` — source and fallback;
- `sk-SK` — Slovak;
- `cs-CZ` — Czech;
- `de-DE` — German;
- `fr-FR` — French;
- `pl-PL` — Polish.

Email templates use `en`, `sk`, `cs`, `de`, `fr` and `pl`.

### Back Office settings catalogues

All 120 configuration labels, descriptions, operational notices and URL-help texts are packaged for these 28 locales:

- EU languages/locales: `bg-BG`, `cs-CZ`, `da-DK`, `de-DE`, `el-GR`, `en-GB`, `es-ES`, `et-EE`, `fi-FI`, `fr-FR`, `ga-IE`, `hr-HR`, `hu-HU`, `it-IT`, `lt-LT`, `lv-LV`, `mt-MT`, `nl-NL`, `pl-PL`, `pt-PT`, `ro-RO`, `sk-SK`, `sl-SI`, `sv-SE`;
- North America: `en-US`, `en-CA`, `fr-CA`;
- additional packaged locale: `uk-UA`.

A settings catalogue does **not** by itself mean that the shop form, PDF, validation messages and email templates are fully translated. The Back Office language table therefore distinguishes:

- **Included** — complete five-domain catalogue;
- **Back Office settings only** — settings are localized, but customer-facing content can fall back to English;
- **English fallback** — no matching packaged catalogue is available.

## Same-version catalogue synchronization

Pre-release test packages used the same version number `1.0.0`. Upgrading one of those installations to the stable release therefore does not trigger a normal PrestaShop upgrade or translation-install step.

`TranslationCatalogInstaller` runs idempotently when the module is installed or its configuration page is opened. It imports packaged XLIFF entries into PrestaShop translation storage for already installed languages. It:

- inserts missing rows;
- replaces an empty value or untouched English fallback with the packaged target;
- preserves a genuine merchant-edited translation;
- clears translation caches after a successful change;
- redirects the configuration page once so the refreshed translator is used.

After replacing a pre-release `1.0.0` test build, open the module configuration once in every relevant shop context and clear the PrestaShop cache.

## Deterministic Back Office catalogue source

The 120 settings source strings are stored in:

```text
tools/admin_settings_sources.json
```

Settings-only target text is stored in:

```text
tools/admin-settings-targets/<locale>.txt
```

Regenerate the XLIFF catalogues with:

```bash
python3 tools/build_admin_translation_catalogs.py
```

The GitHub Actions workflow regenerates the catalogues and fails when the committed files differ from deterministic output.

## Rules

1. Preserve placeholders exactly, including `%reference%`, `%error%` and all `{template_variables}`.
2. Do not translate technical names, configuration keys, status codes, database fields, URLs or hashes.
3. Keep legal wording neutral. The module records a withdrawal statement but does not automatically decide legal validity.
4. Use the same legal meaning in front office, email and PDF.
5. Verify XLIFF syntax after every catalogue change.
6. Verify English fallback when a customer-facing target is unavailable.
7. Review translations in the actual PrestaShop interface; catalogue validity alone does not prove correct terminology, layout or national-law suitability.

## Localized public routes

Friendly route slugs are localized by language through `actionAfterLoadRoutes`; only the language-neutral entry URLs use the same path for every language. See [Localized routes](LOCALIZED-ROUTES.md).

## Footer attribution

The English source string is:

```text
PrestaShop module by
```

The link text `joobox.eu` and its URL are fixed. Only the preceding phrase is translated.

## Withdrawal status labels

The database stores stable machine codes (`submitted`, `awaiting_goods`, `refunded`, and others). Presentation layers resolve those codes through `WithdrawalStatusPresenter` and show localized human labels. All 28 packaged Admin and Shop locales include the 11 status labels and the Back Office catalogues also include four audit-event labels. The six complete Email catalogues include the same localized status labels for customer notifications.
