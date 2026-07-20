# Using XOOPS DebugBar

This tutorial is for XOOPS module and theme developers, site builders, and webmasters who need to understand what a request is doing without adding temporary `echo`, `var_dump()`, or database logging statements throughout the site.

DebugBar combines three views of the same system:

- the browser toolbar explains the page you just loaded;
- Analytics shows patterns across multiple administrator requests;
- Logs and Diagnostics help investigate failures and installation problems.

DebugBar is visible only to authenticated administrators. It is a development and troubleshooting tool, not a public monitoring service.

## 1. Turn on the toolbar

Install and activate the DebugBar module, then open **Administration > DebugBar > Home**.

1. Select **Turn XOOPS Debug ON**.
2. Select **Turn DebugBar toolbar ON**.
3. Open **Preferences** and leave **Display DebugBar** set to **Yes**.
4. Return to the front page while signed in as an administrator.

All four conditions must be true: the module must be active, XOOPS Debug must be on, the DebugBar preference must be on, and the current user must pass XOOPS's site-administrator gate. Module-only administration permission may not be enough for toolbar access.

If the toolbar is still missing, update the module once from the XOOPS module manager. The install/update callback refreshes the browser assets used by PHP DebugBar.

## 2. Start with a low-overhead configuration

The following settings are a practical starting point:

| Preference | Suggested value | Why |
|---|---:|---|
| Display DebugBar | Yes | Enables the administrator toolbar. |
| Enable Smarty Debug | No; Yes temporarily while editing templates | Shows a bounded, sanitized view of final template variables. New installations default to No. |
| Enable Included Files Tab | No | The list can be large and is rarely needed. |
| Slow Query Threshold | `0.05` | Marks queries taking at least 50 ms. |
| Query Logging | Slow & errors only | Keeps the toolbar smaller on query-heavy pages. |
| Enable Ray Integration | No | Enable only when Ray is installed and in use. |
| Store request profiles | Yes | Feeds the Analytics page. |
| Profile retention | 7 days | Keeps enough history for comparisons without indefinite growth. |
| Maximum stored profiles | 10000 | Adds a hard storage limit. |
| Enable Monolog file logging | Yes | Adds structured file logs when Monolog is available. |

Set unused performance budgets to `0` to disable those checks. On a busy or production site, turn off XOOPS Debug when the investigation is finished.

The **Bootstrap time budget** uses the measured `XOOPS Boot` lifecycle duration in milliseconds and defaults to `0` (disabled). First observe several representative warm and cold requests, then choose a threshold appropriate to this installation; 100–300 ms can be an illustrative development range, not a universal recommendation. The repeated-query threshold also uses `0` for disabled, while a saved value of `1` is treated as `2`, the minimum meaningful repeat count.

## 3. Read the browser toolbar

Load the page you want to investigate and expand the toolbar at the bottom of the browser.

### Request summary and health

Start here when the whole page feels slow. The request summary includes:

- HTTP method, URI, status, and content type;
- total server time and peak memory;
- query and duplicate-query counts;
- number of included PHP files;
- compression and cache-header information;
- a reproducible cURL command with sensitive values excluded;
- a request ID that can be matched with stored diagnostic records.

The **Health** collector summarizes whether request time or memory exceeded the configured limits.

### Timeline and lifecycle

The timeline shows measured work such as XOOPS bootstrap, module initialization, output setup, module display, and total request time. Use it to answer questions such as:

- Is the delay in XOOPS startup or in the module itself?
- Did a change speed up SQL but leave rendering slow?
- Is a block or template responsible for most of the request?

Measure the same page before and after a change. A single request is useful for diagnosis; several comparable requests give a more reliable result.

### Queries

The Queries collector records SQL sent through the XOOPS logger.

- Slow queries are promoted to an error-level entry.
- Repeated SQL is marked with `DUP` and an execution count.
- Query time is shown separately from total request time.
- With **Slow & errors only**, fast normal queries—including ordinary duplicate-query messages—are counted and included in aggregate analysis but not rendered as individual rows.

Repeated queries with the same shape often indicate an N+1 problem: code loads a list and then runs another query for every item. Replace that pattern with a join, a bulk lookup, or preloaded handler data.

An **EXPLAIN** action can appear beside a recorded read-only query. It is administrator-only, token-protected, and accepts only a single `SELECT` or `WITH` statement. Use its output to look for full table scans, temporary tables, filesorts, and missing indexes. It never executes an `INSERT`, `UPDATE`, or `DELETE` through this interface.

### Messages, exceptions, and deprecations

These collectors show PHP and XOOPS diagnostic messages with severity, request context, source location, and a bounded trace when available. They are especially useful for:

- warnings that do not stop the page;
- deprecated APIs that will become upgrade problems later;
- caught exceptions that would otherwise be difficult to associate with a request;
- errors that occur only with a particular module or theme.

### Blocks and Smarty

The Blocks collector reports block rendering and whether the result came from cache. The Smarty collector shows variables available after page rendering.

For theme development, use Smarty data to confirm the actual variable name, type, and structure before changing a template. XOOPS templates use `<{ ... }>` delimiters.

Smarty values are recursively sanitized and bounded by depth, entry count, and string size before display. Do not leave Smarty collection enabled merely out of habit on a live site: even bounded collection adds work and may expose non-secret business data to administrators.

### Cache, HTTP, and Mail

These collectors are populated when XOOPS or a module reports the corresponding operation:

- **Cache** can show reads, writes, deletes, hits, misses, and backend summaries.
- **HTTP** can show outbound method, URL, status, and timing metadata.
- **Mail** can show recipient, subject, result, and transport metadata; message bodies are removed.

Not every module reports these operations yet. An empty collector means no compatible event was recorded during that request, not necessarily that the subsystem was unused.

### Frontend and History

The browser-side Frontend collector reports navigation milestones, transferred bytes when available, resource count, and the five slowest browser resources. This helps distinguish a slow PHP response from slow images, scripts, fonts, or stylesheets.

The History collector keeps a maximum of ten small browser-local entries in `localStorage`. It contains the path, load time, and resource count—not request parameters—and can be cleared with the browser's site-data controls.

### Included files

Enable this preference temporarily when you need to identify which preload, override, library, or compatibility file actually loaded. Disable it afterward because large installations can load hundreds of files.

## 4. Use Analytics for patterns, not isolated requests

The toolbar explains one request. **DebugBar > Analytics** aggregates the compact profiles collected while administrators browse with debugging enabled.

Choose a 1-, 7-, or 30-day window and review:

- **Worst offenders** for slow URLs and high query counts;
- the **N+1 leaderboard** for repeated query shapes;
- **Per-module comparison** for average time, queries, payload, and violations;
- **Recent budget violations** to see which limit was crossed;
- **Flight recorder** records containing bounded request metrics and findings;
- OPcache health, including hit rate, memory, cached scripts, and restarts;
- Xdebug cachegrind files when Xdebug profiling is configured.

The stored URL is reduced to its path, so query-string secrets are not used as the Analytics identity. Profile storage is bounded by both retention days and maximum row count.

### A useful optimization loop

1. Select a slow URL in Analytics.
2. Reproduce it as an administrator.
3. Inspect total time, SQL time, the slowest queries, and duplicate counts.
4. Change one relevant part of the code or query.
5. Reload the same page with comparable data.
6. Compare the toolbar and the Analytics averages.
7. Add a realistic performance budget to catch regressions.

## 5. Read XOOPS and Monolog logs

Open **DebugBar > Logs** to see the allowlisted XOOPS log files.

- The legacy XOOPS log is shown as a bounded raw tail.
- Monolog files named `xoops.log` or `xoops-YYYY-MM-DD.log` are parsed into time, level, description, channel, location, and structured details.
- At most the last 256 KB of a selected file is read.
- Parsed Monolog entries are displayed newest first.

Use the log viewer when the failure happened before the toolbar could render, during a redirect, or on a background request. Search for the source location and error number, then expand structured context only when needed.

When enabled, the Monolog adapter is registered for site-wide XOOPS requests, not only requests that can display the administrator toolbar. It writes only events at or above the configured minimum level; at the recommended Warning level, a clean request need not create an entry.

Structured fields are sanitized, but arbitrary preformatted message text cannot be guaranteed secret-free. Logs can contain operational or user-related context, so review and redact any excerpt before sharing it outside the administrator team.

## 6. Run Diagnostics before changing code

Open **DebugBar > Diagnostics** for a read-only snapshot of:

- XOOPS and PHP versions, debug state, environment, and timezone;
- front-end and admin themes;
- Xdebug and OPcache availability;
- PHP DebugBar, Monolog, Whoops, Ray, and Tracy package status;
- EXPLAIN signing-key readiness and a warning when protected variable data sits below the document root;
- writable log, cache, data, profile, and Smarty compile directories;
- required theme engine and entry files.

Run this page first when a feature is missing. It can reveal a disabled extension, unwritable directory, missing theme entry file, or absent optional package without turning the investigation into a code change.

The EXPLAIN key is stored under `XOOPS_VAR_PATH/data`. When that path is below the web server's document root, retain XOOPS's Apache deny rules and configure an equivalent deny rule for nginx, lighttpd, or any server that does not honor `.htaccess`.

## 7. Capture an Xdebug profile

Xdebug is optional. When its `profile` mode and trigger-based startup are configured, DebugBar can request one cachegrind profile:

1. Enable **Show “Profile this request” button** in Preferences.
2. Open the target page as an administrator.
3. Select **Profile this request** in the toolbar.
4. The page reloads once with `XDEBUG_TRIGGER=1` and then removes the trigger from the visible URL.
5. Open **Analytics > Xdebug profiles** to find the generated file.

Cachegrind files can become large. Download or inspect what you need, delete individual files, or use **Purge files older than 30 days**. The purge action is token-protected and limited to recognized cachegrind filenames in Xdebug's configured output directory.

## 8. Practical webmaster investigations

### “The site became slow after enabling a module”

Compare the module in **Per-module comparison**, reproduce its slowest URLs, then separate total request time from SQL time. High SQL time points toward queries and indexes; low SQL time with high total time points toward PHP work, remote calls, templates, or assets.

### “The page works for me but fails intermittently”

Check recent Monolog warnings and errors, then match the source location with the toolbar's request and exception context. Look for cache-directory permissions, missing files, timeouts, and failures after redirects.

### “The theme is missing content”

Enable Smarty Debug, inspect the available variables, and use Included Files to confirm the active theme and overrides. Diagnostics can verify the configured theme directories and entry files.

### “The database server is busy”

Use the N+1 leaderboard and worst URLs to find pages that multiply queries. Switch Query Logging to **All queries** only for a short reproduction, then restore **Slow & errors only**.

## 9. Finish safely

When testing is complete:

1. Turn **XOOPS Debug OFF** from DebugBar Home.
2. Turn off Ray, full query logging, Included Files, and unnecessary profiling options.
3. Review retention and delete obsolete Xdebug profiles.
4. Clear browser-local DebugBar history if it is no longer useful.
5. Never publish screenshots or logs without reviewing request data and paths.

Disabling the DebugBar preference does not enable or disable XOOPS Debug automatically. The two switches are intentionally separate so the administrator can use XOOPS debugging without the browser toolbar, or disable all diagnostic collection with the global XOOPS switch.

## Related guides

- [Extending XOOPS DebugBar](extending-debugbar.md)
- [Ray integration](ray-integration.md)
