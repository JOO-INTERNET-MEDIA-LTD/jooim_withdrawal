# Installation and deployment

## 1. Staging first

Install the ZIP on a staging clone with the same PrestaShop, PHP, database, theme, SMTP and multistore configuration as production.

## 2. Install

Use Module Manager, or from the PrestaShop root:

```bash
php bin/console prestashop:module install jooim_withdrawal
```

Clear the application cache if the new console command or translation catalogue is not immediately visible.

## 3. Configure each shop

For every shop context configure:

- trader legal name;
- registered address;
- company identifier;
- contact email and phone;
- return address/instructions;
- privacy policy URL;
- reference prefix and rate limits if defaults are unsuitable.

Do not use JOO INTERNET MEDIA LTD as the trader unless it is actually the seller for that shop. JOO INTERNET MEDIA LTD is the software vendor.

## 4. Cron

Preferred:

```bash
php bin/console jooim:withdrawal:maintenance
```

Run every 5–15 minutes. The task removes expired technical sessions/drafts and retries failed acknowledgement emails. Submitted legal records are not deleted.

For hosting without CLI, use the module maintenance controller with the generated secret cron token. Never expose the token in public documentation or analytics URLs.

## 5. Production smoke test

Use a real test order for every shop. Verify magic link delivery, GET scanner protection, POST verification, full and partial withdrawal, direct withdrawal, PDF email attachment, admin visibility, CSV export, order panel, status mail, failed-mail retry and PDF access control.

## 6. Upgrading from a pre-release 1.0.0 test build

During development, corrected test packages deliberately keep the same module version `1.0.0`. PrestaShop therefore has no version change that could trigger a normal module upgrade script.

Use this procedure instead:

1. Back up the database and the existing `/modules/jooim_withdrawal/` directory.
2. Replace the complete module directory with the files from the new dedicated installation ZIP. Do not merge a few selected files and do not use GitHub's automatically generated source archive.
3. Open **Modules → Module Manager → JOOIM Withdrawal → Configure** once for every shop context used by the module. This imports missing packaged translations for every already installed language, synchronizes localized routes and repairs shop-specific hook registrations.
4. Confirm that **Runtime configuration** reports that the required security values and hooks are ready. Opening this page removes stale visual registrations from `displayFooterPart1` and keeps `displayFooter` as the accessible fallback. In the storefront, the normalizer inserts the legal link immediately after the `ps_linklist` static Stores item identified by `link-static-page-stores-`; no manual edit of `ps_linklist` data is required.
5. Clear the PrestaShop production and development cache from Back Office or remove the contents of `var/cache/prod/` and `var/cache/dev/` without deleting the directories themselves.
6. Test both public pages and submit a disposable test withdrawal on staging.

Opening the configuration page performs an idempotent runtime synchronization. It creates only missing technical state and does not rotate an existing module secret or rewrite submitted withdrawal evidence.

Do **not** use **Permanent data purge** for an update. That operation is intended only for a disposable test installation and permanently drops legal and audit records.

## 7. Localized public routes and diagnostics

Friendly routes are language-specific. Examples for four commonly used active languages are:

```text
/sk/odstupenie-od-zmluvy
/sk/odstupenie-od-zmluvy/priamo

/en/contract-withdrawal
/en/contract-withdrawal/direct

/uk/vidmova-vid-dohovoru
/uk/vidmova-vid-dohovoru/bezposeredno

/de/vertragswiderruf
/de/vertragswiderruf/direkt
```

The configuration page generates the real absolute URL for every active language and shop. See [Localized routes](LOCALIZED-ROUTES.md) for the complete route map.

For **Design → Link List** or another module with only one URL field, use the language-neutral entry URLs:

```text
/index.php?fc=module&module=jooim_withdrawal&controller=entry&target=identify
/index.php?fc=module&module=jooim_withdrawal&controller=entry&target=direct
```

Canonical controller URLs for isolating rewrite problems:

```text
/index.php?fc=module&module=jooim_withdrawal&controller=identify
/index.php?fc=module&module=jooim_withdrawal&controller=direct
```

Interpretation:

- language-neutral entry URL works but friendly URL fails: clear PrestaShop cache and save **Shop Parameters → Traffic & SEO** to regenerate routes and `.htaccess`;
- canonical controller URL works but the localized friendly URL fails: inspect route cache and confirm that `actionAfterLoadRoutes` is registered for the current shop;
- all URLs return the guarded HTTP 503 page: use the component-qualified `WDERR-...` reference in `ps_log`, the protected module runtime log or **Advanced parameters → Logs**;
- pages render but forms fail: verify the small `jooim_withdrawal_csrf` cookie, HTTPS detection, database tables, SMTP and current-shop configuration.

## 8. Theme integration

All public module templates extend the active theme's `page.tpl`. For a signed-in customer the controller uses `customer/page.tpl` when the active theme or its declared parent provides that standard template; otherwise it safely falls back to `page.tpl`.

The module therefore inherits the active theme's page layout, header, navigation, notification blocks, content container, typography and footer. Its own stylesheet contains only withdrawal-specific spacing and component rules. A commercial or heavily customized theme must still be checked on staging because non-standard block names or CSS can require a small theme-specific compatibility adjustment.

Never enable debug mode permanently on a public production shop. Use it only for a controlled diagnostic request and disable it immediately afterwards.
