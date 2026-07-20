![alt XOOPS CMS](https://xoops.org/images/logo.png)
## XOOPS DebugBar module for [XOOPS CMS 2.7.0+](https://xoops.org)
[![XOOPS CMS Module](https://img.shields.io/badge/XOOPS%20CMS-Module-blue.svg)](https://xoops.org)
[![Software License](https://img.shields.io/badge/license-GPL-brightgreen.svg?style=flat)](https://www.gnu.org/licenses/gpl-2.0.html)

[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/mambax7/debugbar.svg?style=flat)](https://scrutinizer-ci.com/g/mambax7/debugbar/?branch=master)
[![Latest Pre-Release](https://img.shields.io/github/tag/XoopsModules27x/debugbar.svg?style=flat)](https://github.com/XoopsModules27x/debugbar/tags/)
[![Latest Version](https://img.shields.io/github/release/XoopsModules27x/debugbar.svg?style=flat)](https://github.com/XoopsModules27x/debugbar/releases/)

# XOOPS DebugBar

XOOPS DebugBar is a developer-focused diagnostics and performance module for XOOPS 2.7. It adds an administrator-only PHP DebugBar toolbar to rendered pages and provides protected administration pages for performance analytics, logs, system diagnostics, and Xdebug profiles.

The module is designed to fail closed: anonymous visitors never receive diagnostic output, optional integrations are capability-detected, and disabling or removing an optional tool does not prevent DebugBar from loading.

## Requirements

- XOOPS 2.7.0 or newer
- PHP 8.2 or newer
- MySQL 8.0 or a compatible MariaDB release
- `php-debugbar/php-debugbar` available through the XOOPS Composer autoloader
- A writable module assets directory during installation or module update

xWhoops and Tracy are **not dependencies**. Ray, Monolog enhancements, Xdebug profiling, OPcache statistics, and external cachegrind viewers are also optional.

## Installation

1. Copy the `debugbar` directory to `modules/debugbar`.
2. Confirm that `php-debugbar/php-debugbar` is installed in the XOOPS Composer environment.
3. In XOOPS Administration, open **System > Modules** and install DebugBar.
4. For an existing installation, run **Update** to install the 1.3.0 preferences and create the protected EXPLAIN signing key. Also run Update after changing the PHP DebugBar Composer package so its browser assets are refreshed.
5. Open **DebugBar > Preferences** and review the collection and retention settings.
6. On **DebugBar > Home**, turn on both **XOOPS Debug** and the **DebugBar toolbar**.

The frontend toolbar is shown only to a site administrator accepted by XOOPS's global administrator gate. A user with permission for only this module may not satisfy that gate. Activating the module is not sufficient when global XOOPS Debug or the `Display DebugBar` preference is off.

## Administration

### Home

Shows the effective runtime state and provides CSRF-protected controls for:

- global XOOPS Debug;
- the DebugBar browser toolbar;
- an optional Tracy bootstrap, but only when the host installation explicitly exposes `XOOPS_TRACY_STATUS`.

The Tracy control is deliberately absent on standard installations. DebugBar never installs, loads, or requires Tracy.

### Analytics

Displays stored request profiles, slow URLs, N+1 candidates, per-module comparisons, recent budget violations, flight-recorder snapshots, OPcache health, and available Xdebug cachegrind profiles. Stored profile retention and row limits are configurable.

### Logs

Provides a protected, bounded tail viewer for the XOOPS legacy log and optional Monolog files. Structured Monolog context is formatted and secrets are redacted by the logger integration.

### Diagnostics

Reports PHP and XOOPS runtime information, active themes, optional diagnostic packages, EXPLAIN signing-key readiness, writable storage, and required theme files. It does not display credentials, sessions, source contents, or absolute application paths.

## Important preferences

- **Display DebugBar** — master preference for the browser toolbar.
- **Enable Smarty Debug** — collects a bounded, recursively sanitized Smarty template context; off by default on new installations.
- **Enable Included Files Tab** — lists PHP files loaded by the request.
- **Query Logging** — all queries or slow/error queries only.
- **Slow query/request and resource budgets** — highlight expensive requests.
- **Bootstrap time budget** — compares the measured XOOPS Boot lifecycle duration in milliseconds; `0` disables the warning. Observe representative cold and warm requests before choosing a local threshold.
- **Repeated-query warning threshold** — `0` disables N+1 findings; `1` is normalized to the minimum meaningful value of `2`.
- **Store request profiles** — enables the Analytics history.
- **Profile retention / maximum rows** — bounds stored diagnostic data.
- **Enable Monolog file logging** — uses Monolog when it is available.
- **Enable Ray Integration** — forwards data only when the `ray()` function exists.
- **Show “Profile this request” button** — available when Xdebug profiling is configured.

Collection-heavy options should be enabled only while diagnosing a problem. Included files, complete query logging, profiling, and verbose Monolog levels can add meaningful overhead on busy sites.

## Optional integrations

### xWhoops

When xWhoops is installed and dispatches its handler-configuration event, DebugBar contributes a small redacted diagnostics table through a capability-checked callback. There is no import, include, inheritance relationship, or runtime requirement. Without xWhoops, the event never fires and DebugBar behaves normally.

### Tracy

DebugBar does not instantiate or autoload Tracy. The Diagnostics page can report Composer package metadata without loading the Tracy class. A Tracy toolbar control is shown only when the host bootstrap defines `XOOPS_TRACY_STATUS`; this is an optional site-specific contract and is not required by the module.

### Ray

Ray support is inactive unless `ray()` exists and the module preference is enabled. Missing Ray libraries are treated as a normal disabled state.

### Monolog

The file adapter is registered site-wide when Monolog is available and its preference is enabled, even though the browser toolbar remains administrator-only. Only events at or above the configured minimum level are written. DebugBar continues to use the standard XOOPS logger when Monolog is absent.

### Xdebug and OPcache

Xdebug unlocks the one-shot profile button and cachegrind catalog. OPcache adds server health statistics. Neither PHP extension is required for the normal toolbar.

## Security and production use

- Toolbar output is restricted to authenticated administrators.
- State-changing administration actions use POST requests and XOOPS CSRF tokens.
- Log files and flight records are selected from server-generated allowlists.
- Structured request, cookie, header, URL, HTTP, mail, Whoops, and Smarty fields are bounded and sanitized before diagnostic display. Arbitrary secrets embedded inside preformatted message text cannot be guaranteed to be detected.
- Request, cookie, session, password, and token values are not exposed by the Diagnostics page.
- Signed SQL EXPLAIN actions use a dedicated key under `XOOPS_VAR_PATH/data`. If that directory is beneath the document root, configure the web server to deny direct access; `.htaccess` protection alone is Apache-specific.
- XOOPS Debug and diagnostic collection should be disabled when testing is complete.

DebugBar is a development aid, not an application performance monitoring service. Do not leave verbose diagnostics enabled on a public production site.

## Troubleshooting

### The module is active, but the toolbar is missing

Confirm all of the following:

1. You are signed in as an administrator.
2. Global XOOPS Debug is ON.
3. **Display DebugBar** is set to Yes.
4. The module is active.
5. The module assets were copied during install/update.
6. The page renders the normal XOOPS footer lifecycle event.

The Home page reports “Enabled, waiting for XOOPS Debug” when the toolbar preference is enabled but global XOOPS Debug is off.

### Assets are missing

Run the XOOPS module update operation. The update callback copies the PHP DebugBar browser resources into the module assets directory and reapplies the module-owned CSS/JavaScript overlay.

### Analytics is empty

Enable **Store request profiles**, browse several pages as an administrator with XOOPS Debug enabled, and verify that the profile table exists.

## Documentation

- [Using XOOPS DebugBar](docs/using-debugbar.md) — a practical tutorial for developers, site builders, and webmasters.
- [Extending XOOPS DebugBar](docs/extending-debugbar.md) — lifecycle, instrumentation APIs, collectors, Analytics, optional integrations, and tests.
- [Ray integration](docs/ray-integration.md) — optional Ray setup, automatic forwarding, and Smarty helpers.

## License

GNU General Public License 2.0 or later. See the license information in `xoops_version.php` and the source-file headers.

## Credits

Based on the earlier XOOPS DebugBar integration by Richard Griffith and trabis, with subsequent XOOPS 2.7 compatibility, diagnostics, analytics, logging, and security work maintained by the XOOPS community.
