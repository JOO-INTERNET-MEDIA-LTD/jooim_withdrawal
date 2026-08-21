# Runtime diagnostics

Public error pages do not disclose exception details. The same failure is recorded in:

- PrestaShop database log (`ps_log`), visible in Back Office under **Advanced Parameters → Logs**;
- private module files: `var/logs/runtime-YYYY-MM-DD.log`.

Search for the displayed `WDERR-...` reference. On managed hosting the database log may contain the error even when no filesystem log is visible.

Example diagnostic query:

```sql
SELECT id_log, severity, message, date_add
FROM ps_log
WHERE message LIKE '%WDERR-%'
ORDER BY id_log DESC
LIMIT 50;
```

Replace `ps_` with the actual database prefix when necessary.

Module log files are protected by an Apache deny rule and `index.php` guard and must never be linked publicly. Logs may contain technical paths and exception messages and therefore require administrator-only access.

## Final POST action boundary

The `submit` and `directsubmit` controllers do not render the storefront. They process the legal action in `postProcess()` and redirect before PrestaShop initializes `displayHeader`. This prevents an unrelated theme, analytics or marketing hook from blocking the database transaction or leaving a partially rendered menu.

If the action itself fails, it records a `WDERR-...` reference and redirects to a normal GET page. Diagnose the reference in `ps_log` or the protected module runtime log; do not expect a rendered error page at `/odoslat`.

Component `EXT` identifies an exception whose source file belongs to another PrestaShop module. It separates third-party hook failures from `jooim_withdrawal` application (`APP`), database (`DB`), security (`SEC`/`CSRF`) and view (`VIEW`) failures.
