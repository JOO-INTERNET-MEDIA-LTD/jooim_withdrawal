# Security policy

## Supported version

Version `1.0.0` is the current supported stable release. Security fixes are published through a new release and documented in the changelog.

## Reporting a vulnerability

Do not publish vulnerabilities, access tokens, cron tokens, customer data, order data or withdrawal documents in a public GitHub issue.

Report security issues privately to JOO INTERNET MEDIA LTD through the appropriate Joobox support contact listed in [SUPPORT.md](SUPPORT.md). Include the module version, PrestaShop/PHP versions, reproduction steps and sanitized evidence. Do not include live secrets.

## Implemented controls

The module includes, among other controls:

- CSRF protection and server-side validation;
- shop, customer and order ownership checks;
- HMAC one-time access tokens with raw tokens excluded from storage;
- non-consuming GET verification followed by explicit POST confirmation;
- idempotency and transaction locking for final submission;
- rate limiting and neutral anti-enumeration responses;
- Secure, HttpOnly and SameSite guest-session cookies;
- private PDF storage and authorization checks;
- administration permission checks and audit logging;
- read-only GitHub update checking with no automatic code installation.

Order references are identifiers, not credentials. Raw IP addresses used for abuse controls are converted to rotating HMAC values and are not copied into the immutable legal snapshot.

## Operational responsibility

Security depends on the complete environment. Keep PrestaShop, PHP, the database, theme and other modules supported and patched; use HTTPS; protect Back Office and cron secrets; configure mail securely; restrict filesystem permissions; and test every release on staging before production.
