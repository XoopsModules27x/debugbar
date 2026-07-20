# Extending XOOPS DebugBar

This tutorial explains how to add new diagnostic features to the XOOPS DebugBar module without weakening its administrator-only, optional, and fail-safe behavior.

It is intended for developers working on the module itself and for XOOPS module authors who want to contribute diagnostic data when DebugBar is available.

## 1. Understand the request lifecycle

DebugBar is integrated through `preloads/core.php`. The important stages are:

| XOOPS event | DebugBar responsibility |
|---|---|
| `core.include.common.start` | Register the module autoloader, attach loggers, and start early timers. |
| `core.include.common.auth.success` | Confirm administrator access, global XOOPS Debug, and module preferences before enabling output. |
| `core.include.common.end` | Register optional Monolog support and finish bootstrap timing. |
| `core.header.start` / `end` | Measure output initialization and begin module display timing. |
| `core.footer.start` | Collect late Smarty and included-file data. |
| `core.footer.end` | Finalize the profile, add request collectors, and render the toolbar. |

Preload code runs on nearly every web request. It must never make the site dependent on an optional package and must not throw an uncaught exception. Keep optional integrations behind `class_exists()`, `function_exists()`, `defined()`, `is_object()`, or `method_exists()` checks as appropriate.

## 2. Use the existing public instrumentation API

The safest way for another XOOPS module to contribute data is to use `DebugbarLogger` only when its class has already been made available by the active DebugBar preload.

```php
use XoopsModules\Debugbar\DebugbarLogger;

if (class_exists(DebugbarLogger::class, false)) {
    $debug = DebugbarLogger::getInstance();
    if ($debug->isEnabled()) {
        $debug->tag('invoice', $invoiceId);
    }
}
```

The second argument to `class_exists()` is `false` deliberately. A module that merely offers optional diagnostics should not locate or load DebugBar itself. When DebugBar is missing or inactive, normal application behavior must remain unchanged.

### Add a timer

```php
if (class_exists(DebugbarLogger::class, false)) {
    $debug = DebugbarLogger::getInstance();
    if ($debug->isEnabled()) {
        $debug->startTime('publisher.related-items', 'Load related items');
    }
}

try {
    $items = $relatedItems->findForArticle($articleId);
} finally {
    if (isset($debug) && $debug->isEnabled()) {
        $debug->stopTime('publisher.related-items');
    }
}
```

Use a module-prefixed timer key so it cannot collide with another component. A `finally` block ensures the timer closes even when the operation throws.

### Record cache activity

```php
$started = microtime(true);
$value = $cache->get($cacheKey);

if (isset($debug) && $debug->isEnabled()) {
    $debug->recordCache(
        operation: 'read',
        key: 'publisher.article.' . $articleId,
        hit: $value !== null,
        duration: microtime(true) - $started,
        backend: 'module-cache',
    );
}
```

Do not pass a key containing a session ID, access token, email address, or other secret. Diagnostic labels should identify the operation without copying private values.

### Record an outbound HTTP request

```php
if (isset($debug) && $debug->isEnabled()) {
    $debug->recordHttp([
        'method' => 'GET',
        'url' => 'https://api.example.test/catalog',
        'status' => $responseStatus,
        'duration_ms' => $durationMs,
    ]);
}
```

`recordHttp()` recursively redacts keys that look like passwords, tokens, secrets, cookies, authorization values, or API keys. Do not rely on redaction as permission to pass an entire request object; provide only the fields needed to diagnose the call.

The shared `Analysis\DiagnosticSanitizer` also protects Request Summary, cURL reconstruction, cookie/header maps, mail and HTTP records, xWhoops snapshots, Profiler metadata, and Smarty context. Extend that single policy when adding a new structured diagnostic surface; do not introduce a second sensitive-key list. Cookie maps intentionally redact every value. Preformatted free-text messages remain the caller's responsibility.

### Record a mail attempt

```php
if (isset($debug) && $debug->isEnabled()) {
    $debug->recordMail([
        'to' => $recipient,
        'subject' => $subject,
        'transport' => 'smtp',
        'success' => $sent,
    ]);
}
```

The logger removes `body` and `html` fields. Avoid passing attachments or complete mailer objects.

### Add a request tag

```php
if (isset($debug) && $debug->isEnabled()) {
    $debug->tag('publisher.mode', 'preview');
    $debug->tag('publisher.cacheable', false);
}
```

Tags appear in the current request toolbar. Keep them scalar, bounded, and non-sensitive.

### Report an exception

```php
try {
    $result = $service->perform();
} catch (\Throwable $exception) {
    if (isset($debug) && $debug->isEnabled()) {
        $debug->addException($exception);
    }
    throw $exception;
}
```

DebugBar is an observer. It should not change the application's error-handling decision.

## 3. Add a new toolbar collector

Use the built-in methods above when the data fits Cache, HTTP, Mail, Tags, Messages, Exceptions, or the timeline. Add a new collector only when the information needs a distinct presentation or data model.

For a simple key/value collector inside DebugBar itself:

```php
use DebugBar\DataCollector\ConfigCollector;

$debugbar = $logger->getDebugbar();
if ($debugbar !== false && !$debugbar->hasCollector('Publisher')) {
    $debugbar->addCollector(new ConfigCollector([
        'mode' => 'preview',
        'items' => 12,
    ], 'Publisher'));
}
```

For a first-class feature:

1. Create a focused collector class under `class/` or `class/Analysis/`.
2. Keep collection independent from HTML rendering.
3. Register the collector in `DebugbarLogger::enable()` or in a documented late lifecycle stage.
4. Populate it through an explicit method on `DebugbarLogger`.
5. Add presentation assets under `assets-custom/` only if the standard PHP DebugBar widgets are insufficient.
6. Run the module update so the module-owned asset overlay is copied to `assets/`.

Collector names must be unique. Handle duplicate registration and missing data without throwing.

## 4. Add a new XOOPS log channel

`DebugbarLogger::log()` currently understands these context channels:

- `queries`;
- `blocks`;
- `deprecated`;
- `extra`;
- the default `messages` channel.

Cache, HTTP, and Mail use their dedicated recording methods. To introduce a new channel:

1. Add a matching `MessagesCollector` in `DebugbarLogger::enable()`.
2. Add a narrowly scoped branch in `DebugbarLogger::log()`.
3. Normalize and bound the context before it reaches PHP DebugBar.
4. Preserve the default path for unknown channels.
5. Decide explicitly whether the same event should be forwarded to `RayLogger`.

Never allow a diagnostic formatting failure to break page rendering. Catch collector-specific failures at the integration boundary.

## 5. Add a new Analytics metric

Analytics uses a compact, module-owned `debugbar_profiles` table. Adding a durable metric crosses several layers:

1. Measure it in `Profiler::finalize()`.
2. Add the column through an idempotent install/update migration.
3. Add the value to `ProfileRepository::insert()`.
4. Extend the relevant repository aggregate query.
5. Render it in `admin/analytics.php`.
6. If it is a budget, extend `BudgetChecker`, define a unique flag bit, and add a preference in `xoops_version.php`.
7. Add `_MI_DEBUGBAR_*` preference strings and `_AM_DEBUGBAR_*` administration strings.
8. Add unit tests for the calculation, persistence mapping, flags, and page structure.

Do not store complete session, cookie, POST, response-body, or SQL-result data in the profile table. Analytics is intended for small numeric summaries and bounded identifiers.

### Preserve upgrade safety

Module updates can run more than once. Before adding a column or index, check whether it already exists. Existing installations must continue to work if an update was interrupted and restarted.

If the profile format changes incompatibly, add an explicit schema version or migration path rather than assuming a fresh install.

## 6. Add a new administration page

Follow the established pages in `admin/`:

1. Create the page and load `admin_header.php`.
2. Call `xoops_cp_header()` and `$adminObject->displayNavigation()`.
3. Add the page to `admin/menu.php`.
4. Define menu text in `language/english/modinfo.php` with `_MI_DEBUGBAR_*` names.
5. Define page text in `language/english/admin.php` with `_AM_DEBUGBAR_*` names.
6. Escape all rendered values with `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` unless a trusted renderer already performs escaping.
7. Use `Xmf\Request` with an explicit source instead of raw `$_REQUEST`.

Every state-changing action must use POST and validate a named XOOPS CSRF token. Read-only filters may use GET. Never use a user-supplied path directly; resolve files from a server-generated allowlist as `LogCatalog`, `FlightRecorder`, and `CachegrindCatalog` do.

## 7. Add an optional third-party integration

Optional means the module still installs, activates, renders, and passes its tests when the package is absent.

Use this pattern:

```php
if (!class_exists(Vendor\Package\Feature::class)) {
    return;
}

try {
    // Register the optional adapter.
} catch (\Throwable $exception) {
    // Report safely when possible, but do not break the XOOPS request.
}
```

Apply these rules:

- Do not add an unconditional `require`, `include`, `use`-driven instantiation, parent class, interface, or type declaration that forces the package to exist.
- Capability-check the exact method used, not only the object name.
- Do not send diagnostics for anonymous users or before the administrator check.
- Give the integration its own preference when it can add latency, network traffic, or storage.
- Show an unavailable state in Diagnostics instead of treating absence as an error.
- Add an absence test similar to `OptionalIntegrationsTest`.

The xWhoops callback and Tracy status/control are examples of capability-based integration. Neither package is required by DebugBar.

## 8. Extend Ray support

`RayLogger` mirrors selected XOOPS logger events only when `ray()` exists, the preference is enabled, XOOPS Debug is enabled, and the user is an administrator.

When adding a Ray representation:

1. Keep the browser DebugBar feature complete without Ray.
2. Check `$this->activated` before doing transformation work.
3. Catch `Throwable` around Ray calls.
4. Rate-limit or summarize loop-heavy events.
5. Avoid sending secrets, full request objects, and large payloads.
6. Update [ray-integration.md](ray-integration.md) with the exact behavior.

The Smarty Ray functions are supplied by XOOPS/Smarty integration code and coordinate with `RayLogger`. A standalone DebugBar module must not assume those plugins exist in every XOOPS core.

## 9. Tests and verification

Place module tests under `tests/unit/modules/debugbar/`. Prefer small classes whose calculations can be tested without booting a complete web request.

Run the focused suite with the supported PHP binaries:

```powershell
C:\wamp64\bin\php\php8.4.0\php.exe xoops_lib\vendor\phpunit\phpunit\phpunit `
  --configuration tests\unit\phpunit.xml --no-coverage tests\unit\modules\debugbar

C:\wamp64\bin\php\php8.5.0\php.exe xoops_lib\vendor\phpunit\phpunit\phpunit `
  --configuration tests\unit\phpunit.xml --no-coverage tests\unit\modules\debugbar
```

Also verify:

- all changed PHP files pass `php -l`;
- the module works with Ray, Tracy, and xWhoops absent;
- anonymous and non-admin requests receive no toolbar or forwarded Ray data;
- the feature handles missing tables, unwritable storage, and empty data safely;
- install and update callbacks are idempotent;
- new browser assets survive a module update;
- state-changing admin actions reject missing or expired tokens;
- output is escaped and diagnostic values are bounded.

Finally test manually in both a full-page response and any AJAX/fragment response affected by the change. DebugBar sends fragment data differently and must not initialize a second toolbar.

## 10. Contribution checklist

- [ ] The feature answers a concrete diagnostic question.
- [ ] Collection is cheap when the feature is disabled.
- [ ] Optional packages remain genuinely optional.
- [ ] Administrator and XOOPS Debug checks occur before sensitive collection or forwarding.
- [ ] Values are redacted, bounded, and escaped.
- [ ] New preferences have conservative defaults.
- [ ] Storage has retention and hard limits.
- [ ] Admin mutations use POST and CSRF protection.
- [ ] Language constants follow XOOPS module naming rules.
- [ ] Upgrade logic is repeatable.
- [ ] Unit tests cover normal, absent-dependency, and failure behavior.
- [ ] User and developer documentation is updated.

## Related guides

- [Using XOOPS DebugBar](using-debugbar.md)
- [Ray integration](ray-integration.md)
