# Ray Integration for XOOPS DebugBar

Ray is an optional desktop companion for XOOPS DebugBar. DebugBar remains fully functional when Ray is not installed.

The browser toolbar and Ray serve different workflows:

- **DebugBar** keeps request diagnostics with the page in the browser.
- **Ray** sends selected queries, blocks, messages, exceptions, timers, and explicitly inspected template values to the Ray desktop application.

Ray output is enabled only for an authenticated administrator when global XOOPS Debug is on and **Enable Ray Integration** is set to **Yes**.

## Requirements

- an installed and active XOOPS DebugBar module;
- the Ray desktop application;
- either `spatie/ray` in this XOOPS installation or `spatie/global-ray` on the development machine;
- global XOOPS Debug enabled;
- the DebugBar Ray preference enabled.

Ray is not installed by the DebugBar module and is not a module dependency.

## Install Ray

Download and run the Ray desktop application from [myray.app](https://myray.app/).

### Option A: Install Ray in this XOOPS project

Run Composer from the XOOPS Composer root:

```powershell
cd C:\path\to\xoops\xoops_lib
composer require --dev spatie/ray
```

This is the most predictable choice for a project that should declare its development tooling. Do not commit Ray as a production requirement unless the deployment intentionally uses it.

### Option B: Install Ray globally

Use the current official Global Ray installer:

```powershell
composer global require spatie/global-ray
global-ray install
```

Restart the web server after a global installation if the installer changes PHP startup configuration. Use the official [Ray PHP installation guide](https://myray.app/docs/php/vanilla-php/installation) for platform-specific details rather than copying an `auto_prepend_file` path from another machine.

## Enable the integration

1. Start the Ray desktop application.
2. Open **XOOPS Administration > DebugBar > Home**.
3. Turn **XOOPS Debug ON**.
4. Confirm the **DebugBar toolbar** is on.
5. Open **DebugBar > Preferences**.
6. Set **Enable Ray Integration** to **Yes**.
7. Reload a front-end page while signed in as an administrator.

The relevant current defaults are:

| Preference | Default |
|---|---:|
| Display DebugBar | Yes |
| Enable Smarty Debug | No |
| Enable Included Files Tab | No |
| Slow Query Threshold | `0.05` seconds |
| Query Logging | Slow & errors only |
| Enable Ray Integration | No |

Diagnostics reports whether a Ray package is installed and whether the `ray()` function is active in the web request. Remember that command-line PHP and Apache/FPM can load different `php.ini` files.

## What DebugBar sends automatically

When enabled, `RayLogger` receives XOOPS logger activity and forwards:

### Queries

- SQL text;
- execution time when provided by XOOPS;
- a sequential query number;
- duplicate counts;
- slow-query highlighting based on the DebugBar preference;
- database error number and message for failed queries.

Normal queries are purple, repeated queries are orange, and slow or failed queries are red.

### Blocks

Block events identify the block and whether it was cached. Cached blocks are green; uncached blocks are blue.

### Messages and exceptions

PSR-3 severity is mapped to a Ray color and label. Deprecations are orange. Exceptions use Ray's exception display when DebugBar is asked to record them.

### Timers

DebugBar lifecycle timers can use Ray's `measure()` facility. This complements the browser timeline but does not replace the stored Analytics profile.

## Smarty template helpers

Some XOOPS 2.7 installations provide Ray Smarty helpers through the XOOPS Smarty extension package or compatible legacy plugins. These helpers are outside the standalone DebugBar module, so confirm their availability in the target XOOPS core.

All examples use XOOPS Smarty delimiters: `<{ ... }>`.

### Send a value or message

```smarty
<{ray msg="Reached the profile section" color="green"}>
<{ray value=$user label="Current user" color="blue"}>
```

`value` or `msg` supplies the data. `label` and `color` are optional.

### Inspect a value

```smarty
<{ray_dump value=$items label="Items"}>
```

This sends the value using Ray's normal expandable object and array display.

### Display an array as a table

```smarty
<{ray_table value=$items label="Item rows"}>
```

`ray_table` accepts arrays. Other value types are ignored.

### Inspect the template context

```smarty
<{ray_context label="Before item loop" exclude="xoops_*,smarty"}>
```

The context helper sends a sorted summary of assigned template variables. Objects are represented by class name, arrays by item count, and long strings are truncated. The `exclude` parameter accepts comma-separated exact names and prefix patterns ending in `*`.

### Inspect an inline value without changing output

```smarty
<h1><{$item.title|ray:"Item title"}></h1>
```

The `ray` modifier returns the original value after sending it, so normal template output is unchanged.

Each helper checks that `RayLogger` is enabled and that `ray()` exists. When Ray is unavailable or disabled, function tags produce no output and the modifier returns its value unchanged.

## Practical workflows

### Find an N+1 query

1. Start Ray and load the slow page once.
2. Look for orange query entries with increasing duplicate counts.
3. Compare them with DebugBar's N+1 analysis and Analytics leaderboard.
4. Move the repeated lookup outside the loop or replace it with a bulk query.
5. Reload and confirm both the count and request time decreased.

### Discover template variables

Place this temporarily near the top of the template:

```smarty
<{ray_context label="Template start" exclude="smarty"}>
```

Then inspect only the relevant variable:

```smarty
<{ray_dump value=$items label="Items supplied by controller"}>
```

Remove temporary Ray calls before release. Even when disabled, development probes add noise and can be enabled accidentally later.

### Trace a branch without changing HTML

```smarty
<{if $is_preview}>
    <{ray msg="Preview branch" color="orange"}>
<{/if}>
```

Ray is useful here because no diagnostic markup is inserted into the browser response.

## Behavior when Ray is absent or disabled

| Situation | Browser DebugBar | Ray forwarding | Smarty Ray helpers |
|---|---|---|---|
| Ray package absent | Works | Disabled | Silent no-op/pass-through |
| Ray preference is No | Works | Disabled | Silent no-op/pass-through |
| XOOPS Debug is off | Hidden | Disabled | Silent no-op/pass-through |
| User is not an administrator | Hidden | Disabled | Silent no-op/pass-through |
| Ray app is running and all checks pass | Works | Enabled | Enabled when the XOOPS Smarty helpers exist |

If the PHP package is present but the desktop application is closed, behavior and connection timing are controlled by the installed Ray package and its configuration. Disable the module preference whenever Ray is not actively being used.

## Troubleshooting

### Diagnostics says Ray is not installed

Confirm the package was installed in `xoops_lib`, or run the Global Ray installer. Compare the `php.ini` reported by the web server with the one used by CLI PHP, then restart the web server.

### Diagnostics finds Ray, but the desktop receives nothing

Confirm the following:

- the Ray desktop application is open;
- XOOPS Debug is on;
- the current user is an administrator;
- **Enable Ray Integration** is Yes;
- the page completed the normal XOOPS authentication lifecycle;
- local security software is not blocking the Ray connection.

### PHP events arrive, but Smarty tags do not

The standalone module supplies `RayLogger`, not the XOOPS Smarty plugin registration. Confirm that the installed XOOPS core or `xoops/smartyextensions` version provides `ray`, `ray_dump`, `ray_table`, `ray_context`, and the `ray` modifier. Clear compiled Smarty templates after changing plugin availability.

### A page becomes slow when Ray is enabled

Turn the Ray preference off and compare the same request. Avoid sending data inside large loops, use a summarized table or a limited sample, and keep the desktop application running while the integration is enabled.

## Security and release checklist

- Enable Ray only on a trusted development or diagnostic environment.
- Do not send passwords, session identifiers, authorization headers, personal data, or complete request objects.
- Remove temporary template probes before publishing a theme or module.
- Turn the Ray preference off after testing.
- Turn XOOPS Debug off on a public production site.
- Review the [official Ray PHP usage documentation](https://myray.app/docs/php/vanilla-php/usage) when using Ray APIs directly.

## Related guides

- [Using XOOPS DebugBar](using-debugbar.md)
- [Extending XOOPS DebugBar](extending-debugbar.md)
