# Changelog

All notable changes to the XOOPS DebugBar module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses semantic versioning.

## [1.4.0] - 2026-07-28

### Added

- Added real-user monitoring: `assets/xoops-debugbar-rum.js` collects field web vitals (LCP, INP, CLS) in the browser and posts them to a new `beacon.php` endpoint via `navigator.sendBeacon()`, which attaches them to the matching stored profile. The Analytics page gained a Field web-vitals table showing per-URL averages against a colour-coded LCP threshold. The endpoint is POST-only and gated on module admin, XOOPS debug mode, a `DEBUGBAR_RUM` token, and a shape-validated request id.
- Added an Xdebug profile viewer. `CachegrindParser` reads `cachegrind.out.*` output (plain or gzip-compressed) into an immutable `CachegrindResult`, and the Analytics page lists profiles with creator, command, total cost, and a top-functions breakdown by inclusive/self cost. Individual profiles can be deleted and the directory purged, both behind CSRF tokens.
- Added `AssetScanner`, which scans rendered HTML for script and stylesheet tags, sums local asset sizes, and flags a duplicated JavaScript runtime (two copies of jQuery, Alpine, htmx, and similar) on one page.
- Added `AnalyticsBuilder`, which aggregates stored profiles, flight-recorder dumps, and OPcache health into plain arrays, leaving the admin page responsible only for rendering and escaping.
- Added `AccessPolicy`, centralising the "may this admin page run" decision (module admin, XOOPS debug mode, and the module's own enable switch must all agree) for pages that expose profiling data.
- Added `RequestShape`, sharing fragment/AJAX detection and URL normalisation between the logger and the profiler. It prefers `Xmf\Http\FragmentNegotiator` when available and falls back to a header check, and never throws, since it runs on the hot path of every profiled request.
- Added `QueryFingerprinter`, normalising statements so that structurally identical queries differing only in literal values can be grouped for N+1 and repeat analysis.
- Added `CachegrindCatalog::delete()`. The Analytics page already offered a per-profile Delete button that called this method, so pressing it raised a fatal "call to undefined method" error. Deletion resolves the name through the existing containment check, so a caller cannot escape the configured output directory by traversal or symlink.

### Fixed

- Defined 45 `_AM_DEBUGBAR_*` language constants that the Analytics, Diagnostics, and Ray admin pages referenced but that were never declared. Referencing an undefined constant is a fatal error on PHP 8, so the affected panels crashed rather than rendering.
- Truncated each stored profile column to its own width instead of a single shared 500-character cap. `slowest_fp` is a normalised SQL fingerprint and routinely exceeds its `VARCHAR(255)`; under MySQL strict mode the oversized insert raised error 1406, which the surrounding `catch` swallowed, so affected profiles were silently never recorded at all.
- Recorded the slowest query over every statement rather than only over those past the slow-query threshold. On any page where nothing crossed the threshold, `slowest_ms` was stored as `0.0` and `slowest_fp` as an empty string, which made the Analytics slowest columns and worst-URL ranking wrong for exactly the ordinary pages they are meant to characterise.
- Reported the Xdebug environment through the fuller status evaluator, which reads effective modes via `xdebug_info()` with an ini fallback and adds a shared-temporary-directory warning. The Analytics page had been reading keys the previous evaluator never returned, so the panel reported "not loaded" no matter how Xdebug was configured.
- Indexed `request_id` on the profiles table. `updateVitals()` looks a profile up by request id on every web-vitals beacon, which is once per page view, and without the index that was a scan of the entire retained table. Existing installations gain the index through an idempotent migration.
- Realigned `sql/mysql.sql` with the installer's own `CREATE TABLE`, which had drifted: the standalone file was missing the `lcp_ms`, `inp_ms`, and `cls` columns that `updateVitals()` writes to unconditionally.
- Removed an unreachable branch in `QueryAnalyzer` that tested for a single query shape variant after an earlier guard had already required at least three.

### Security

- Reworked the on-demand EXPLAIN endpoint so the browser never sends SQL. The client now posts a request id and a statement hash, and the server resolves them against a short-lived stash it recorded itself, rejecting anything that is not a `SELECT`. The endpoint additionally requires POST, an authenticated admin, a `DEBUGBAR_EXPLAIN` token, and the `explain_on_demand` preference, and it returns generic JSON errors that leak neither SQL nor paths.
- Added `SqlRedactor`, which rewrites every string literal to `''` and every numeric literal to `0` before a statement is stashed for EXPLAIN. No session id, password, or token value is persisted, while the statement still yields a representative plan.

### Changed

- Capped each query-findings list (slow queries, duplicates, N+1 groups, similar shapes) at ten entries. A page issuing thousands of queries previously emitted one entry per finding into the Performance panel, the flight-recorder dump, and the warning log. The reported counts remain exact.
- Expanded the unit suite from 4 tests to 53 (214 assertions), covering the admin access policy, diagnostic sanitiser, SQL redactor and EXPLAIN stash redaction, Monolog log parsing and cataloguing, system diagnostics, endpoint gating, and profile-schema consistency between the DDL and the insert statement.

### Removed

- Removed `SqlStatementClassifier` and `ExplainSecretStore`. Both existed to make client-submitted EXPLAIN SQL safe, and the redesign above means no client-submitted SQL reaches the endpoint at all.

## [1.3.3] - 2026-07-28

### Fixed

- Pointed the Logs viewer at the XOOPS 2.7.3 core debug log (`logs/debug.log` under the configured XOOPS data directory). It still pointed at the pre-2.7.3 `/log/log.txt`, which no longer exists, so the plain-text log was simply never listed.

### Changed

- Renamed the plain-text log source from `legacy` to `core`, so the Source column describes the current log rather than a retired one.
- Rendered analytics and log timestamps through `formatTimestamp()`, so they honour the site's configured timezone rather than the server's. XOOPS applies the site default here rather than each administrator's own offset, so the column reads the same for everyone. The sortable `Y-m-d H:i:s` layout is kept deliberately — these are data tables, not prose — and the trailing timezone abbreviation is dropped, since it would name the server's zone rather than the one the value was converted to.
- Held the core log to the same directory containment as the Monolog files when reading. The path is built by the caller and never taken from the request, but the core file logger refuses to write through a symlink, and the reader should not be more trusting than the writer.

## [1.3.2] - 2026-07-21

### Security

- Rendered message-context scalar values as text while preserving structured JSON dumps, preventing diagnostic strings from being interpreted as HTML.
- Limited message-context iteration to own enumerable properties and preserved valid falsy values such as `0`, `false`, and empty strings.

### Fixed

- Restored interactive Debugbar tabs and expandable message details by loading the JSON VarDumper assets explicitly.
- Replaced raw Symfony dump markup in message contexts with safe, structured, collapsible arrays.

### Changed

- Added regression coverage for JSON message formatting, required VarDumper assets, context serialization, and normal logger construction without XOOPS runtime side effects.

## [1.3.1] - 2026-07-21

### Security

- Hardened read-only SQL classification by correctly terminating backtick-quoted identifiers and rejecting executable MySQL and MariaDB comments.

### Fixed

- Corrected the empty-Monolog-directory regression test so it exercises an existing empty directory while preserving access to the legacy log.
- Applied the project coding style required by the PHP 8.2, 8.3, and 8.4 CI quality jobs.

### Changed

- Reduced SQL-tokenizer complexity and allocation overhead by extracting parsing helpers, consuming identifiers in one anchored match, and using the native line-comment scan.
- Corrected the Scrutinizer analysis-node configuration and kept Rector modernization available separately from the merge-blocking QA gate.
- Extended the GitHub Actions compatibility matrix and Scrutinizer test coverage to PHP 8.5, while keeping automated formatting checks on the minimum supported PHP version.

## [1.3.0] - 2026-07-20

### Security

- Added one bounded recursive sanitizer for request metadata, URLs and cURL output, cookies, headers, HTTP/mail records, xWhoops snapshots, Profiler data, and Smarty variables.
- Replaced the EXPLAIN HMAC fallback with a dedicated random signing key under protected XOOPS variable data; EXPLAIN now fails closed when that key is unavailable.
- Changed EXPLAIN failures to return a generic client response while recording details through the server-side XOOPS logging path.
- Hardened dumped-value and email-preview rendering against markup injection and external tracking-resource loads.
- Restricted on-demand EXPLAIN to one syntactically complete, read-only `SELECT`, including CTEs whose top-level statement is `SELECT`; writable CTEs, stacked statements, and file-output clauses are rejected.

### Fixed

- Made `nplus1_threshold = 0` disable repeated-query findings and normalized `1` to the minimum meaningful threshold of `2`.
- Connected the bootstrap budget to the measured `XOOPS Boot` lifecycle duration in milliseconds and persisted that value in request profiles.
- Kept malformed optional Ray channel metadata from escaping the integration boundary.
- Corrected Diagnostics to prefer the canonical `php-debugbar/php-debugbar` Composer package name.
- Removed the unavailable web-vitals placeholder from the compatibility Analytics page.
- Fixed AJAX editor links, failed OpenHandler requests, blocked mail-preview popups, falsy toolbar values, and late `Server-Timing` headers.
- Corrected dark-mode syntax-highlighting and VarDumper selector/line-height behavior.
- Prevented scalar HTTP details, template names, and template parameters from being interpreted as HTML in browser widgets.
- Made profile-table detection escape SQL `LIKE` wildcards and made vendor-asset corrections detect source drift instead of silently succeeding.
- Prevented an unavailable Monolog directory from degrading into a filesystem-root glob and added the standard XOOPS data-directory fallback.

### Changed

- Bumped module metadata to 1.3.0; existing installations must run the XOOPS module update.
- Added the bootstrap-time preference with a conservative default of `0` (disabled).
- Changed Smarty collection to off by default for new installations; existing saved preferences remain unchanged during update.
- Added EXPLAIN-key creation to install/update as a fail-soft module step and exposed capability status in Diagnostics.
- Prepared the module for standalone distribution with release documentation.
- Added user/webmaster and extension-development tutorials.
- Updated the optional Ray guide to match current module defaults, capability checks, and installation guidance.
- Made the optional Tracy administration control conditional on an explicit host-bootstrap capability.
- Clarified effective toolbar status when XOOPS Debug is disabled.
- Added persistent post-copy corrections for affected vendor assets and completed the standalone XOOPS/XMF PHPStan stubs.
- Focused Sonar analysis on authored sources by excluding generated browser-asset mirrors and declarative manifest duplication.

### Documentation

- Documented administrator gating, site-wide Monolog scope, query-mode visibility, sanitizer limits, Smarty defaults, bootstrap/N+1 semantics, and protected-data web-server rules.
- Replaced the generic built-in help page with a concise installation, configuration, security, troubleshooting, and documentation guide.
- Regenerated the standalone module file inventory.

### Compatibility

- Confirmed there are no hard imports, includes, inheritance relationships, or instantiations of xWhoops or Tracy.
- xWhoops and Tracy integrations remain optional and capability-detected.

## [1.2.0] - 2026-07-18

### Added

- XMF 2-aligned performance Analytics with slow-URL, N+1, module comparison, budget violation, and OPcache views.
- Compact request profile storage with configurable retention and row limits.
- Flight-recorder snapshots for budget violations.
- Xdebug status, one-shot “Profile this request” support, cachegrind catalog, viewing, deletion, and 30-day purge control.
- Protected XOOPS and Monolog log catalog with bounded tail reads, structured-context formatting, and secret redaction.
- Administrator-only system Diagnostics page for runtime, theme, package, and writable-directory checks.
- CSRF-protected controls for global XOOPS Debug and the DebugBar toolbar.
- Optional Monolog file adapter with configurable minimum level.
- Optional Ray forwarding.
- Optional redacted DebugBar context callback for xWhoops.

### Changed

- Ported the request lifecycle integration to XOOPS 2.7 preload events.
- Restricted toolbar output to authenticated administrators with global XOOPS Debug enabled.
- Added query modes, slow-query highlighting, repeated-query detection, and performance budgets.
- Aligned administration navigation and analytics terminology with the XMF 2 implementation.
- Added PHP 8.2 through PHP 8.5 compatibility coverage.

### Security

- Added CSRF validation to state-changing admin actions.
- Added allowlisted file selection for logs, flight records, and Xdebug profiles.
- Added output escaping and bounded/redacted diagnostic contexts.
- Prevented early anonymous Ray forwarding and non-admin toolbar rendering.

## [1.0.0-beta1]

### Added

- Initial XOOPS DebugBar module based on the XOOPS 2.6 work by Richard Griffith and trabis.
- Browser toolbar collectors for queries, timers, blocks, errors, Smarty data, and included files.
