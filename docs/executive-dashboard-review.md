# Executive dashboard review — 23 October 2026

## Changes

- Academic-year filter/options use saved `academicyear`, with creation-year fallback only for blank legacy records.
- Overall performance uses every matching valid score record, weighted by record count, not the ten plotted subjects. The chart remains top ten. These are score-record percentages, not terminal-report rankings; no report ranking rules were changed.
- Invalid/missing score denominators are excluded, inactive bills and attendance sessions are excluded from those summaries.
- Feed sorting uses numeric timestamps and considers five recent entries from each source before selecting the latest five.
- Student lookup links use the destination's `search-item` parameter.
- Notification counts, lists and mark-read action now cover the same audit-log set. Pending alumni approvals are not added to an unread-log badge that cannot clear them.
- Mark-read requires a session CSRF token; dashboard access requires an administrator. Viewing this dashboard no longer deletes old read audit logs. Shared authentication and other pages were not redesigned.
- Population chart includes other/unspecified gender/residence records; tooltip percentages use the chart's population scope.
- Invalid saved tool-menu state no longer breaks initialization. Category filtering reveals its tools, clearing restores the saved sections, and dismissed search requests cannot reopen the results.
- Chart.js 4.5.1 is local, with its MIT license. A missing chart script produces an explanatory message instead of blank chart panels.

## Validation

`tests/executive-dashboard-regression.php` validates SELECTs using EXPLAIN on the local schema, renders only synthetic data, verifies calculations and simulates CSRF actions without database writes.

`tests/waec-comparison-browser.ps1` has an executive-fixture mode testing tabs, menus, themes, tool filtering, stale search responses, mobile navigation and real Chart.js chart initialization at 1440px and 390px. External fonts are omitted from the fixture and heartbeat is mocked. No real payment, notification update or SMS is submitted. PHP 8.4 checks use the isolated runtime, not production.

Static local links/assets were checked for existence. This does not certify every linked module's workflows or its production permissions. Authenticated live search/heartbeat, database contents, Hostinger schema differences, and notification writes still require a controlled live/staging check.

## Deploy

Deploy `executive-dashboard.php` and **`js/vendor/chart.umd.min.js`** plus `js/vendor/Chart.js-LICENSE.md`. The tests and this document need not be publicly served.

Chart source: https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js

License: https://github.com/chartjs/Chart.js/blob/v4.5.1/LICENSE.md

After deployment, verify an administrator can open the dashboard; compare one selected academic year against known scores; check the notification badge/mark-read with a permitted test action; and check real search, heartbeat and student lookup. Review private PHP logs if any component fails. Do not enable public error display.
