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
| Collect browser web-vitals (RUM beacon) | Yes | Reports LCP, INP, and CLS from real administrator sessions, which is the only way to see slowness the server does not cause. |

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

An **EXPLAIN** action can appear beside a recorded read-only query. It is administrator-only, token-protected, and accepts only one `SELECT`, or a `WITH` query whose top-level statement is `SELECT`. Writable CTEs, stacked statements, and `INTO OUTFILE`/`INTO DUMPFILE` are rejected. Use its output to look for full table scans, temporary tables, filesorts, and missing indexes.

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
- **Field web vitals** for per-URL LCP, INP, and CLS measured in real administrator sessions, shown beside the server time for the same requests;
- **Flight recorder** records containing bounded request metrics and findings;
- OPcache health, including hit rate, memory, cached scripts, and restarts;
- Xdebug cachegrind files when Xdebug profiling is configured, with a per-file breakdown of the most expensive functions by inclusive and self cost.

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

- The XOOPS core debug log (logs/debug.log in the configured data directory) is shown as a bounded raw tail.
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
4. The button arms a single capture and reloads the page once. The trigger is a short-lived cookie set by the server, never a URL parameter, so the armed request cannot be replayed by anyone who later sees the address in browser history, a `Referer` header, or an access log. It is consumed on the next request whether or not a profile results.
5. Open **Analytics > Xdebug profiles** to find the generated file.

If arming does not produce a file, the toolbar says so rather than failing silently. That normally means Xdebug is loaded but not configured for trigger-based profiling; **Analytics > Xdebug profiles** states which of `mode`, `start_with_request`, and `output_dir` is unsatisfied. The button is hidden entirely when the extension cannot capture a profile at all.

Cachegrind files can become large. Download or inspect what you need, delete individual files, or use **Purge files older than 30 days**. The purge action is token-protected and limited to recognized cachegrind filenames in Xdebug's configured output directory.

## 8. Practical webmaster investigations

Each scenario below starts from a complaint a site owner actually receives, not from a metric. The pattern is the same throughout: use Analytics to decide *which page* to look at, then use the toolbar to understand *that one request*, then change one thing and measure again.

A note on reading budget violations: every stored profile carries a set of flags, shown in **Recent budget violations** and on the flight recorder. The names are `queries`, `sql`, `boot`, `request`, `memory`, `payload`, `n+1`, `fragment-full-theme`, `duplicate-runtime`, and `query-errors`. The first seven fire only when you have set a corresponding budget; the last three always fire when the condition occurs, because none of them is ever intentional.

### “The site became slow after enabling a module”

**Decide whether the module is actually responsible.** Open **Analytics > Per-module comparison** over a 7-day window. The comparison shows average time, average queries, average payload, and violation counts per module, so a module that is merely *present* on slow pages is distinguishable from one that is *causing* them. If the suspect module's average request time is close to the site average, the problem is more likely a page that happens to include it.

**Separate the kind of slowness.** Pick the module's worst URL, reproduce it as an administrator, and read two numbers off the request summary: total server time and SQL time.

- SQL time is most of total time → the problem is queries. Continue with “The database server is busy”.
- SQL time is a small fraction of a large total → the time is in PHP, templates, or an outbound call. Open the **Timeline**, and compare the `XOOPS Boot` duration with module display. A large boot figure on every page points at a preload rather than the page itself.
- Both are small but the page still feels slow → the server is fine and the browser is not. Continue with “Fast for me, slow for visitors”.

**Confirm before and after.** Disable the module, reload the same URL with comparable data, and compare. One request is a diagnosis; several comparable requests are evidence.

### “Fast for me, slow for visitors”

This is the scenario server-side timing cannot answer, and the one most often misdiagnosed. The server may genuinely respond in 80 ms while visitors wait several seconds for something useful to appear.

Enable **Collect browser web-vitals (RUM beacon)** in Preferences. The browser then reports Largest Contentful Paint, Interaction to Next Paint, and Cumulative Layout Shift back to the site, and those land beside the server timing for the same request.

Open **Analytics > Field web vitals** and read each row against the server time in the same table:

| Pattern | Reading |
|---|---|
| Server ms low, LCP high | The server is not the problem. Look at images, fonts, render-blocking scripts, and the **Frontend** collector's five slowest resources. |
| Server ms high, LCP high | Fix the server first; the browser is waiting on the response. |
| LCP acceptable, CLS high | Content is moving while it loads — usually images or ads without reserved dimensions. Nothing to do with PHP. |
| LCP acceptable, INP high | The page paints quickly but responds slowly to input, which points at JavaScript on the main thread. |

LCP is colored against the usual 2.5 s / 4 s thresholds. Note that these are *field* measurements from real administrator sessions on real connections, which is why they can be far worse than anything you observe locally.

### “A plugin stopped working after I changed themes”

Symptoms are typically a JavaScript widget that silently does nothing, or works on one page and not another.

DebugBar scans the rendered HTML for the same JavaScript runtime loaded more than once — two copies of jQuery, Alpine, htmx, and similar. When it finds one, the request is flagged `duplicate-runtime` and the runtime name and both URLs are reported in the **Performance** panel.

This is worth checking early because the failure is so confusing: the second copy re-initializes the library and discards the plugins the first copy registered, so nothing errors and nothing works. The usual cause is a theme that hard-codes a library the core or another module already provides. The fix is to remove the theme's copy, not to add load-order workarounds.

The scan is skipped for fragment and AJAX responses and for very large pages, so reproduce on a normal full page load.

### “An AJAX call is much slower than it should be”

Open the page that issues the request, then look for the `fragment-full-theme` flag on the profile for the AJAX URL.

That flag means the request was recognized as a fragment or AJAX call but the response still contained a complete themed HTML document. In other words the endpoint booted the theme, rendered the header, the blocks, and the footer, and then returned a fragment of it — paying for an entire page render to deliver a few hundred bytes. It is one of the most expensive and least visible mistakes in a XOOPS module.

The fix is on the endpoint side: return early, before theme rendering, and emit only the fragment or JSON the caller expects.

### “The database server is busy”

**Find the pages, not the queries, first.** Open **Analytics > N+1 leaderboard**. It groups queries by fingerprint — the statement with its literal values normalized away — so a query executed 200 times with 200 different ids appears as one entry with a count, rather than as 200 unrelated statements.

**Reproduce with full logging, briefly.** Set Query Logging to **All queries**, reload the worst URL once, and set it straight back to **Slow & errors only**. In the **Queries** collector the repeats are marked `DUP` with an execution count.

**Confirm the shape.** An N+1 looks like one query that loads a list, followed by many near-identical queries that differ only in an id. The fix is a join, a bulk `IN (...)` lookup, or preloading through the handler — not caching the symptom.

**Check the plan for the ones that are genuinely slow.** Use the **EXPLAIN** action beside a recorded read-only query and look for full table scans, temporary tables, and filesorts. The client never sends SQL to the server for this: it references a statement the server already recorded, so the plan you see is for the statement that actually ran.

If **Recent budget violations** shows `query-errors`, stop and read those first. A failing query is never a performance question, and it is flagged regardless of any budget you configured.

### “The page works for me but fails intermittently”

Intermittent faults are rarely reproducible on demand, so collect rather than chase.

Set a realistic budget — request time or query count — so that a bad request records itself. When a budget is crossed, the **flight recorder** writes a bounded JSON record containing the request metrics, the decoded flags, the findings, the N+1 groups, and the slow queries for that request. Open **Analytics > Flight recorder** afterwards and read the record for the failure, rather than trying to reproduce it live.

Cross-reference with **Logs**. Match the Monolog entry's timestamp and source location with the flight record's request id. Common causes that show up this way are cache-directory permissions that fail only for some users, a remote call that times out under load, and errors raised after a redirect has already been issued — which is exactly the case where nothing appears on screen.

### “The theme is missing content”

Enable **Smarty Debug** temporarily and open the **Smarty** collector on the affected page. Confirm the actual variable name, type, and structure before editing a template — most “missing content” is a template reading a variable that was never assigned, or assuming an array where a scalar was passed. Remember the `<{ ... }>` delimiters.

If the variable exists and is correct, the template you are editing is probably not the one being used. Enable **Included Files** temporarily and confirm which theme file and which override actually loaded. **Diagnostics** verifies the configured theme directories and entry files independently.

Turn both preferences off afterwards. Smarty collection adds work on every request and exposes business data to any administrator; the included-files list can run to hundreds of entries.

### “Where is the time going inside PHP?”

Reach for this when the timeline shows the time is in PHP rather than SQL, but not *which* PHP.

Configure Xdebug for trigger-based profiling, then arm one capture with **Profile this request** (see section 7). Open **Analytics > Xdebug profiles**, select the generated file, and read the top functions by cost. Two columns matter and they answer different questions:

- **Inclusive** cost includes everything a function called. A high inclusive cost near the top of the stack tells you which subsystem is expensive.
- **Self** cost excludes callees. A high self cost is the function actually burning the CPU.

A function with high inclusive and low self cost is a router, not a bottleneck — follow its callees. A function with high self cost and a large call count is usually the real answer, and is often something cheap being called far too often.

Cachegrind files are large. Delete them individually when finished, or use **Purge files older than 30 days**.

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
