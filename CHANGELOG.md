# Changelog

All notable changes to **JOOIM Withdrawal** are documented in this file.

## [1.1.1] - 2026-10-14

### Fixed

- explicitly sort XML attributes to keep catalogue regeneration identical on Python 3.7 and modern Python used by CI;
- preserve the original v1.1.0 tag and publish the corrected package as a patch version.

## [1.1.0] - 2026-10-14

### Added

- complete five-domain catalogues and email templates for all supported locales, including Belgian, Canadian, Luxembourgish and Norwegian variants;
- bounded maintenance recovery for missing acknowledgement PDFs and failed creation hooks;
- maintenance locking to prevent overlapping workers and configuration validation before saving;
- a non-destructive upgrade hook and regressions for recovery, multishop references and invalid settings.

### Fixed

- withdrawal references include shop ID to prevent collisions between shops sharing the same country and prefix;
- manual records preserve employee, original receipt time and channel in the immutable snapshot;
- rejected database writes in administrative actions trigger rollback; post-commit notification failures do not undo accepted actions;
- review errors no longer expose raw exception messages;
- Joobox footer attribution remains visible without JavaScript;
- closed cases retain their withdrawn quantities;
- acknowledgement scope and fallback item descriptions use the customer's language.

### Compatibility

- declared module compatibility covers PrestaShop 8.x and 9.x on PHP 8.1 or newer;
- existing records, references and schema are preserved; no database migration is required;
- runtime integration on each supported PrestaShop/hosting combination remains an acceptance check.

## [1.0.0] - 2026-08-21

### Added

- secure public withdrawal flow using an order reference and a one-time email access link;
- direct online withdrawal flow for consumers who cannot use the order-email path;
- full and partial withdrawal, including selected order lines and quantities;
- immutable JSON submission snapshot with SHA-256 integrity hash;
- transaction-safe withdrawal reference generation scoped by shop and year;
- immediate confirmation page, localized email acknowledgement and private PDF document;
- Back Office case list, case detail, human-readable localized statuses, audit history, notes, manual records, refund evidence and CSV export;
- multistore-specific merchant identity, return instructions, legal texts, routes and order isolation;
- localized public routes and Back Office settings for the packaged locales;
- localized README files for English, Slovak, Czech, German, French and Polish;
- Joobox support panel, read-only GitHub release check and shared Joobox footer attribution without duplicate links;
- language-neutral entry URLs for integrations that provide one URL field for all languages;
- CLI and token-protected HTTP maintenance entry points;
- GitHub Actions quality checks for PHP 8.1 and PHP 8.4.

### Security

- HMAC-protected one-time access tokens stored only as hashes;
- token consumption only after an explicit CSRF-protected POST confirmation;
- dedicated Secure/HttpOnly/SameSite CSRF and guest-session cookies;
- rate limiting and neutral anti-enumeration responses;
- ownership, shop, order-line, quantity and document-access checks;
- idempotent final submission and transactional row locking;
- legally decisive database commit isolated from PDF, email, analytics and other presentation hooks;
- protected runtime diagnostics with customer-safe error references;
- normal uninstall preserves legally relevant records.

### Compatibility

- PrestaShop 8.1.x, 8.2.x, 9.0.x and 9.1.x;
- PHP 8.1 or newer;
- no PrestaShop core override;
- active-theme inheritance for public pages and customer-account flows.

[1.0.0]: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/releases/tag/v1.0.0

[1.1.0]: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/releases/tag/v1.1.0

[1.1.1]: https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/releases/tag/v1.1.1
