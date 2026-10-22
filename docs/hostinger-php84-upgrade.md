# Hostinger PHP 8.4 rollout

## What was checked (22 October 2026)

An isolated official Windows PHP 8.4.27 NTS runtime was downloaded and its SHA-256
verified against the PHP downloads page. XAMPP and the live server were not changed.
This verifies PHP language/library behaviour locally, not Hostinger's Linux configuration.

Changes:
- Explicitly preserve the existing backslash escape behaviour in 34 CSV calls across
  10 application files. PHP 8.4 deprecates relying on the default escape argument.
- Replace six deprecated `utf8_encode()` calls in the bundled FPDF with equivalent
  ISO-8859-1 to UTF-8 conversion through iconv. This is a local compatibility patch,
  not a full FPDF upgrade. Preserve it if replacing the library.
- Restore the malformed student fetch loop in `examresults.php`. That was an existing
  syntax error, not a new PHP 8.4 restriction. No ranking rules were changed.
- Add explicit nullable parameter types to the bundled Hubtel HTTP classes, and fix
  its invalid-file loop to skip the file instead of only leaving the switch.

The smoke test checks PDF generation, accented metadata, Smalot text extraction,
FPDI import and CSV roundtrip with warnings/deprecations treated as exceptions.
Existing login, WAEC, alumni and payment-pagination regression tests also passed.
Database test suites use temporary tables; payment tests use mocked services.
Final PHP 8.4.27 lint covered 621 application, library, script and test files with
no syntax errors or compile-time warnings/deprecations. Hubtel request construction
was tested without sending network requests. The smoke test also passes on PHP 8.1.25.

## Deployment procedure

1. Back up the production database and files. Keep credentials/configuration out of Git.
2. Create a password-protected staging copy with a separate database. Disable outbound
   SMS/email and cron jobs; use payment sandbox credentials. Do not point staging at
   production payment callbacks.
3. Select a patched PHP 8.4 build. PHP's 21 October advisory identifies 8.4.27 as fixed;
   use that or a newer 8.4 patch, or ask Hostinger to confirm equivalent vendor fixes.
   Ask them to confirm that workers were restarted. A Git deploy cannot patch PHP.
4. Enable mysqli/mysqlnd, mbstring, iconv, curl, openssl, fileinfo, gd, zip, DOM,
   SimpleXML/XML, zlib and the normal PHP core extensions. Check upload/post limits,
   memory, timeouts and writable upload/temp paths against representative imports.
5. Deploy all changed application files and `fpdf181/fpdf.php`, not only index.php.
   Deploy the existing Composer dependencies from the lock file, for example with
   `composer install --no-dev --prefer-dist --optimize-autoloader`, if Composer is
   available. Run `composer check-platform-reqs --no-dev` and `composer audit` there.
   No Composer vulnerability audit was performed in this local compatibility pass.
6. Using the host's PHP 8.4 CLI binary, run:

   ```sh
   php scripts/php84-preflight.php
   php scripts/php84-csv-audit.php
   php tests/php84-smoke.php
   ```

   Confirm `php -v` first: the SSH default can differ from the website runtime.
   The preflight is CLI-only; do not publish a phpinfo page or credentials.
7. In staging, manually test each role's login and permissions, school/branch selection,
   registration, assessment entry, old and new terminal PDFs, WAEC CSV/XLSX/PDF imports,
   file uploads, exports and sandbox payment initialization/callback/idempotency.
   Check existing cron jobs under the correct PHP binary.
8. Verify optional dependencies separately: SmartMark requires its Python service;
   switching PHP does not install Python, OCR, pdftotext or permit exec/proc_open.
   Windows paths and executable fallbacks are not guaranteed on shared hosting.
9. Check staging error logs with E_ALL. For production use display_errors=Off,
   log_errors=On and private logs; do not conceal errors as a compatibility fix.
10. Once staging passes, switch production in a controlled window and monitor errors.
    Keep a rollback to a supported, patched runtime and matching application release,
    not an unpatched PHP version. Clear relevant caches after deployment.

## Limits

This is not a full security audit or a guarantee for every page/data combination.
SQL injection, upload validation, authorization and CSRF concerns are separate from
PHP-version compatibility and still need review. Hostinger PHP/FPM/LiteSpeed settings,
external APIs, real payment flows and every production role/page were not exercised.

References:
- https://www.php.net/manual/en/migration84.deprecated.php
- https://www.php.net/manual/en/migration84.incompatible.php
- https://www.php.net/archive/2026.php#2026-10-21-5
