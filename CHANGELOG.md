# Changelog

All notable changes to the XOOPS DebugBar module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses semantic versioning.

## [1.4.0] - 2026-07-29

Reconciles this module with the parallel copy maintained on the XMF 2.0 line, harvesting what that line did better and fixing three features that had shipped without working.

### Added

- Added call-site attribution to query findings. The N+1 and duplicate detection could report that sixty-two identical statements ran but not which code ran them, leaving the file to be found by reading source. A trimmed backtrace is now captured with each query — the database drivers, the logger, this module and the generic persistence layer are dropped as never being the interesting caller — and the analyzer groups the surviving frames per statement and per shape, ranked by frequency. On the first real page it saw this named a genuine N+1 in Publisher: sixty-two executions from `ItemHandler.php:255` and sixty-one from `class/model/stats.php:52`. Capture happens inside the existing query-log cap, so the cost stays bounded.
- Added a Boot / SQL / App time split, naming whichever segment dominated the request. This is the granularity that decides what to do next: a request dominated by App time will not be improved by query tuning, and one dominated by Boot is a bootstrap problem rather than anything in the module. It appears on the Performance panel, and the `Server-Timing` header now reports boot, db with its query count, app and total instead of one lumped figure, so the browser's own performance panel shows the same breakdown the Timeline does.
- Added the per-request block cache split. A front page rendering nineteen uncached blocks pays nineteen block renders, each with its own queries, on every view; the Blocks panel showed that one request at a time, which is the wrong granularity for deciding what to fix. `blocks_cached` and `blocks_uncached` are stored per profile and averaged into the per-module Analytics table. Existing installations gain the columns through an idempotent migration.
- Added real-user monitoring: `assets/xoops-debugbar-rum.js` collects field web vitals (LCP, INP, CLS) in the browser and posts them to a new `beacon.php` endpoint via `navigator.sendBeacon()`, which attaches them to the matching stored profile. The Analytics page gained a Field web-vitals table showing per-URL averages against a colour-coded LCP threshold. The endpoint is POST-only and gated on module admin, XOOPS debug mode, a `DEBUGBAR_RUM` token, and a shape-validated request id.
- Added an Xdebug profile viewer. `CachegrindParser` reads `cachegrind.out.*` output (plain or gzip-compressed) into an immutable `CachegrindResult`, and the Analytics page lists profiles with creator, command, total cost, and a top-functions breakdown by inclusive/self cost. Individual profiles can be deleted and the directory purged, both behind CSRF tokens.
- Added `AssetScanner`, which scans rendered HTML for script and stylesheet tags, sums local asset sizes, and flags a duplicated JavaScript runtime (two copies of jQuery, Alpine, htmx, and similar) on one page.
- Added `AnalyticsBuilder`, which aggregates stored profiles, flight-recorder dumps, and OPcache health into plain arrays, leaving the admin page responsible only for rendering and escaping.
- Added `AccessPolicy`, centralising the "may this admin page run" decision (module admin, XOOPS debug mode, and the module's own enable switch must all agree) for pages that expose profiling data.
- Added `RequestShape`, sharing fragment/AJAX detection and URL normalisation between the logger and the profiler. It prefers `Xmf\Http\FragmentNegotiator` when available and falls back to a header check, and never throws, since it runs on the hot path of every profiled request.
- Added `QueryFingerprinter`, normalising statements so that structurally identical queries differing only in literal values can be grouped for N+1 and repeat analysis.
- Added `CachegrindCatalog::delete()`. The Analytics page already offered a per-profile Delete button that called this method, so pressing it raised a fatal "call to undefined method" error. Deletion resolves the name through the existing containment check, so a caller cannot escape the configured output directory by traversal or symlink.
- Added SQL syntax highlighting to the Queries panel. The highlighter had shipped in `assets/vendor/highlightjs` since the module began without anything ever loading it, so every statement rendered as flat text. It publishes under its own class prefix and cannot collide with a copy the surrounding page loads. Query rows are highlighted in place rather than only once expanded, and the theme is written against the toolbar's own custom properties so it follows light and dark without a second stylesheet. Both the highlighted and the fallback path escape, so no statement text reaches `innerHTML` unescaped.
- Implemented the `collect_events` preference, which had shipped since the module began with nothing behind it. `XoopsPreload::triggerEvent()` asks `isset($this->_events[$name])` before dispatching, so standing an `ArrayObject` in for the event table makes every dispatch observable — including the events nothing listens to, which on a stock site is most of them. That is the case worth reporting: from outside, "my hook never ran" and "the event never fired" look identical, and watching the listeners instead can only ever see the former. Listener lists are handed back with each class name replaced by a timing proxy, so each listener is timed while still running normally, and one that throws still throws.
- Implemented the `collect_templates` preference, also previously inert. Every template XOOPS renders goes through the `db:` Smarty resource, and a registered resource takes precedence over the one Smarty autoloads, so a subclass registered from `core.class.template.new` records each render with the source that served it — theme override, module file, or database — plus its size, render count, and fetch time. The origin is established by matching the modification time and size of the candidate files against the bytes core actually returned, rather than by repeating core's private resolution logic, so it costs no extra queries and cannot drift out of step with it. Two indistinguishable candidates are reported as ambiguous rather than guessed between.
- Added three budget checks the profiler already had the data for but never reported: failed queries, a fragment request that rendered the full theme, and more than one copy of the same JavaScript runtime on a page. The last is what the asset scanner detects, so its findings now reach the stored profile, the Analytics violations feed, and the flight recorder instead of only the Performance panel.

### Fixed

- Connected the editor source links, which had never worked. The toolbar has rendered clickable `file:line` links for four kinds of row since it was written, but nothing ever gave them a file: the logger called `collectFileTrace(false)` — the library's own default, so a no-op — under a comment claiming it preserved source context, and no editor template was ever set. Both halves are now wired across every message collector, with the logger and this module excluded from the backtrace so a link points at the code that logged the message rather than the debugbar's own dispatch. An `editor_link` preference chooses the scheme; `xdebug.file_link_format` in php.ini takes precedence when set.
- Gated the Logs page against the full access policy. It renders log file contents — statements, paths, and whatever a module chose to log — but was gated only by `admin_header.php`, so being a module admin was enough, while Analytics and Diagnostics additionally require XOOPS debug mode to be on and the module itself enabled. A site that had finished debugging, with the module disabled, still served its logs to anyone holding the admin bit.
- Inferred the core's block-logging contract instead of assuming one. XOOPS 2.7.3 dispatches the raw block name and leaves formatting to the sub-logger, while the XMF 2.0 line pre-formats the message itself; appending the status unconditionally doubles it on the second, and taking the message as given loses translation on the first. The contract is now read from the data.
- Added the direct-access guard to `LogCatalog`, `MonologLogParser` and `QueryAnalyzer`, the only three classes in the module without it.
- Defined 45 `_AM_DEBUGBAR_*` language constants that the Analytics, Diagnostics, and Ray admin pages referenced but that were never declared. Referencing an undefined constant is a fatal error on PHP 8, so the affected panels crashed rather than rendering.
- Truncated each stored profile column to its own width instead of a single shared 500-character cap. `slowest_fp` is a normalised SQL fingerprint and routinely exceeds its `VARCHAR(255)`; under MySQL strict mode the oversized insert raised error 1406, which the surrounding `catch` swallowed, so affected profiles were silently never recorded at all.
- Recorded the slowest query over every statement rather than only over those past the slow-query threshold. On any page where nothing crossed the threshold, `slowest_ms` was stored as `0.0` and `slowest_fp` as an empty string, which made the Analytics slowest columns and worst-URL ranking wrong for exactly the ordinary pages they are meant to characterise.
- Reported the Xdebug environment through the fuller status evaluator, which reads effective modes via `xdebug_info()` with an ini fallback and adds a shared-temporary-directory warning. The Analytics page had been reading keys the previous evaluator never returned, so the panel reported "not loaded" no matter how Xdebug was configured.
- Indexed `request_id` on the profiles table. `updateVitals()` looks a profile up by request id on every web-vitals beacon, which is once per page view, and without the index that was a scan of the entire retained table. Existing installations gain the index through an idempotent migration.
- Realigned `sql/mysql.sql` with the installer's own `CREATE TABLE`, which had drifted: the standalone file was missing the `lcp_ms`, `inp_ms`, and `cls` columns that `updateVitals()` writes to unconditionally.
- Removed an unreachable branch in `QueryAnalyzer` that tested for a single query shape variant after an earlier guard had already required at least three.
- Revived the on-demand EXPLAIN button, dead since the logger moved real message values into `context_json`. Four faults hid one another: the button never rendered, because its guard read `value.context.is_query` while context now carries only null-valued keys for rendering; it then hung on "Running…" forever, because it derived the stash key with `crypto.subtle.digest()`, which is undefined on a plain-HTTP host and threw before any promise existed for the `.catch()` to attach to, so the server now ships the hash it already computed and the browser echoes it back; the returned plan was unreadable, because MySQL 8's single-column tree format is multi-line text and `JSON.stringify` escaped every newline into one line; and the plan rendered as a narrow strip, because a `<pre>` appended straight to a `<table>` is invalid and the browser hoisted it into the first column. The shared value resolver also revives the editor source links, which were dark for the same reason.
- Followed the surrounding page's dark mode rather than only the operating system's. The toolbar's `auto` theme asked `prefers-color-scheme` and nothing else, so a XOOPS theme switched to dark inside a light OS session left a bright white toolbar pinned to a dark page. It now reads `data-bs-theme` or `data-theme` from the document first and falls back to the media query, with a `MutationObserver` so a site theme toggle re-themes the bar immediately instead of at the next page load. An explicit Light or Dark choice in the toolbar's own settings still wins. The collector label also stops being drawn in a hardcoded `#555`, which was very nearly invisible against a dark background.
- Named the open collector when the tab bar is narrow enough that php-debugbar drops every tab label. Nothing on screen then said which collector was open: the panel below is rows of data with no heading, and the only clue was one highlighted icon among twenty. The selected tab keeps its label inline while every other tab stays an icon, so almost no width is spent.
- Corrected the per-module "Avg payload KB" column on the Analytics page, which read `avg_payload` where the query selects `avg_payload_kb`. The missing key evaluated to `(float) null`, so the column showed `0.0` for every module; it also divided by 1024 a second time, which would have been wrong even with the right key, because the query already converts to KB.
- Carried `trigger_value_set` on `XdebugStatus::read()`'s never-throw fallback path, which returned one key fewer than its success path, so a consumer reading it after a failure hit an undefined key.

### Security

- Reworked the on-demand EXPLAIN endpoint so the browser never sends SQL. The client now posts a request id and a statement hash, and the server resolves them against a short-lived stash it recorded itself, rejecting anything that is not a `SELECT`. The endpoint additionally requires POST, an authenticated admin, a `DEBUGBAR_EXPLAIN` token, and the `explain_on_demand` preference, and it returns generic JSON errors that leak neither SQL nor paths.
- Added `SqlRedactor`, which rewrites every string literal to `''` and every numeric literal to `0` before a statement is stashed for EXPLAIN. No session id, password, or token value is persisted, while the statement still yields a representative plan.

### Changed

- A test now enumerates which admin pages must sit behind the access policy and which must not, since gating navigation or layout would break the control panel. It also checks each refusal is escaped, uses a language constant, and that the constant is defined — an undefined one is a fatal on PHP 8, which is worse than no gate at all. Nothing pinned that set before, which is why the Logs page omission was invisible.
- The Core Web Vitals and block-counter migrations now share one helper instead of repeating the `information_schema` existence check; a third copy was the point at which that stopped being acceptable. `sql/mysql.sql` adopts the XMF line's column-per-line layout.
- Replaced the "Profile this request" mechanism. The button previously appended `XDEBUG_TRIGGER` to the current URL and reloaded, with no server-side gate at all: the resulting address was replayable by anyone and passed through browser history, `Referer` headers, and access logs before it was scrubbed. It now posts to a new `xdebug-arm.php`, which sets a 60-second one-shot trigger cookie only after checking POST, the shared `AccessPolicy` decision (module admin, debug mode, module enabled), a single-use `DEBUGBAR_XDEBUG` token, and Xdebug's own readiness. `Profiler` consumes and deletes the cookie on the next request whatever the outcome, reports the resulting cachegrind file, and says so when arming produced nothing. The `xdebug_button_enable` preference is unchanged, so no reconfiguration is needed.
- Capped each query-findings list (slow queries, duplicates, N+1 groups, similar shapes) at ten entries. A page issuing thousands of queries previously emitted one entry per finding into the Performance panel, the flight-recorder dump, and the warning log. The reported counts remain exact.
- Sealed the Analytics view model. `ProfileRepository`, `XdebugStatus::read()`, and `AnalyticsBuilder::build()` returned `array<string, mixed>`, so static analysis could not check a single one of the admin page's key accesses — three key-mismatch bugs had already shipped behind that blind spot. Each producer now declares the exact keys it returns, so the page reading a key that is not in the shape is an analysis error rather than a blank cell. The shapes are asserted at the query boundary rather than derived from the SQL, so they catch a mistake in the page but not a renamed alias in the query itself; each shape is therefore kept directly above the query it describes.
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
