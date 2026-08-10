<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Builds a bounded, read-only diagnostic snapshot for the module admin page.
 *
 * The collector deliberately accepts only the few public XOOPS configuration
 * keys it needs. It never returns absolute paths, request data, credentials,
 * environment variables, file contents, or stack traces.
 */
final class SystemDiagnostics
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $varPath,
    ) {
    }

    /**
     * Collect checks rendered by the DebugBar admin diagnostics page.
     *
     * @param array<string, mixed> $xoopsConfig XOOPS runtime configuration.
     * @return array<string, list<array{id: string, value: string, status: string, detail: string}>>
     */
    public function collect(array $xoopsConfig): array
    {
        $debugEnabled = (int) ($xoopsConfig['debug_mode'] ?? 0) !== 0;
        $frontTheme = $this->safeName($xoopsConfig['theme_set'] ?? '');
        $adminTheme = $this->safeName($xoopsConfig['cpanel'] ?? '');

        return [
            'runtime' => [
                $this->row('xoops_version', defined('XOOPS_VERSION') ? (string) XOOPS_VERSION : 'Unavailable', 'info'),
                $this->row('php_version', PHP_VERSION, 'info', PHP_SAPI),
                $this->row(
                    'xoops_debug',
                    $debugEnabled ? 'Enabled' : 'Disabled',
                    $debugEnabled ? 'warning' : 'ok',
                    $debugEnabled ? 'Disable after testing on production sites.' : 'Recommended for production.'
                ),
                $this->row('environment', defined('XOOPS_ENV') ? (string) XOOPS_ENV : 'Not defined', 'info'),
                $this->row('timezone', date_default_timezone_get(), 'info'),
                $this->debugFileRow(),
                $this->errorHandlerRow(),
                $this->sqlModeRow(),
            ],
            'themes' => [
                $this->directoryRow('front_theme', $frontTheme, $this->rootPath . '/themes/' . $frontTheme),
                $this->directoryRow(
                    'admin_theme',
                    $adminTheme,
                    $this->rootPath . DIRECTORY_SEPARATOR . 'modules/system/themes/' . $adminTheme
                ),
            ],
            'tools' => [
                $this->extensionRow('xdebug', extension_loaded('xdebug'), $this->xdebugDetail()),
                $this->extensionRow('opcache', extension_loaded('Zend OPcache'), $this->opcacheDetail()),
                $this->packageRow('php_debugbar', ['php-debugbar/php-debugbar', 'maximebf/debugbar']),
                $this->packageRow('monolog', ['monolog/monolog']),
                $this->packageRow('whoops', ['filp/whoops']),
                $this->packageRow('ray', ['spatie/ray', 'spatie/global-ray'], function_exists('ray')),
                $this->packageRow('tracy', ['tracy/tracy']),
                $this->errorScreenRow(),
                $this->explainStashRow(),
            ],
            'storage' => [
                $this->writableRow('logs', $this->varPath . '/logs'),
                $this->writableRow('caches', $this->varPath . '/caches'),
                $this->writableRow('data', $this->varPath . '/data'),
                $this->writableRow('debugbar_data', $this->varPath . '/debugbar'),
                $this->writableRow('template_compile', $this->rootPath . '/templates_c'),
            ],
            'theme_system' => [
                $this->fileRow('theme_engine', 'class/theme.php'),
                $this->fileRow('theme_blocks', 'class/theme_blocks.php'),
                $this->fileRow('front_theme_entry', 'themes/' . $frontTheme . '/theme.html'),
                $this->fileRow(
                    'admin_theme_entry',
                    'modules/system/themes/' . $adminTheme . '/' . $adminTheme . '.php'
                ),
            ],
        ];
    }

    /** @return array{id: string, value: string, status: string, detail: string} */
    private function row(string $id, string $value, string $status, string $detail = ''): array
    {
        return compact('id', 'value', 'status', 'detail');
    }

    /** @return array{id: string, value: string, status: string, detail: string} */
    private function directoryRow(string $id, string $name, string $path): array
    {
        if ($name === '') {
            return $this->row($id, 'Not configured', 'warning');
        }

        return $this->row($id, $name, is_dir($path) ? 'ok' : 'warning', is_dir($path) ? 'Directory found.' : 'Directory missing.');
    }

    /** @return array{id: string, value: string, status: string, detail: string} */
    private function extensionRow(string $id, bool $available, string $detail = ''): array
    {
        return $this->row($id, $available ? 'Available' : 'Not available', $available ? 'ok' : 'info', $detail);
    }

    /**
     * Composer metadata is inspected without loading optional runtime classes.
     * This prevents an incompatible diagnostics package from breaking this page.
     *
     * @param list<string> $packages
     * @return array{id: string, value: string, status: string, detail: string}
     */
    private function packageRow(string $id, array $packages, bool $active = false): array
    {
        foreach ($packages as $package) {
            if (! $this->packageInstalled($package)) {
                continue;
            }

            $version = \Composer\InstalledVersions::getPrettyVersion($package);
            $detail = $package . ($version !== null ? ' ' . $version : '');

            return $this->row($id, $active ? 'Active' : 'Installed', $active ? 'ok' : 'info', $detail);
        }

        return $this->row($id, 'Not installed', 'info');
    }

    /**
     * Who currently holds PHP's error handler.
     *
     * The only state on this page that is invisible everywhere else AND silently disables
     * features when it changes. PHP has one error handler; whoever calls
     * set_error_handler() last owns it. When it is taken away from XoopsLogger, DebugBar's
     * Messages tab simply goes empty -- no error, no log line, no other symptom. "Why is
     * my Messages tab blank" has no other tell anywhere in the system.
     *
     * Probed, not assumed: set_error_handler() returns the handler it displaces, so
     * installing a throwaway closure and immediately popping it reports the live one and
     * leaves the stack exactly as it was.
     *
     * Limitation worth stating: register_shutdown_function() has no getter, so a shutdown
     * handler cannot be reported here -- and that is the part of Whoops which catches
     * genuine fatals.
     *
     * @return array{id: string, value: string, status: string, detail: string}
     */
    private function errorHandlerRow(): array
    {
        $current = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        $name = $this->describeCallable($current);
        $detected = match (true) {
            '' === $name => 'php',
            str_contains($name, 'XoopsErrorHandler'), str_contains($name, 'XoopsLogger') => 'core',
            str_contains($name, 'Whoops') => 'whoops',
            str_contains($name, 'Tracy') => 'tracy',
            default => 'other',
        };

        $declared = function_exists('xoops_getErrorScreenOwner')
            ? (string) \xoops_getErrorScreenOwner()
            : '';

        // 'whoops' declared while core still holds the ERROR handler is the designed
        // outcome, not drift: xwhoops returns the error handler immediately after
        // register() and keeps only the exception and shutdown handlers.
        $agrees = '' === $declared
            || $detected === $declared
            || ('whoops' === $declared && 'core' === $detected);

        return $this->row(
            'error_handler',
            // No inner fallback: the match above maps an empty $name to 'php', so
            // reaching the false branch means $name is non-empty by construction.
            'php' === $detected ? 'PHP default' : $name,
            $agrees ? 'ok' : 'warning',
            '' === $declared
                ? 'This XOOPS does not declare an error_screen owner in debug.php.'
                : sprintf('Declared owner: %s. Detected: %s.', $declared, $detected)
        );
    }

    /**
     * Whether the database connection rejects bad values or silently mangles them.
     *
     * Outside strict mode MySQL turns a value that does not fit its column into a
     * truncation plus a warning nobody reads. XOOPS's own session table is the sharpest
     * example: sess_data is TEXT (65,535 bytes), and an oversized $_SESSION was therefore
     * stored truncated, became undecodable, and was destroyed on the next request --
     * presenting as a lost login or a CSRF failure with no visible link to the write that
     * caused it. Strict mode turns that into an error at the point of failure.
     *
     * Reads $GLOBALS['xoopsDB'] directly. This class otherwise confines itself to the few
     * config keys it is handed, but there is no way to ask a database about its own mode
     * without asking the database.
     *
     * @return array{id: string, value: string, status: string, detail: string}
     */
    private function sqlModeRow(): array
    {
        $db = $GLOBALS['xoopsDB'] ?? null;
        // fetchRow is named here too: the guard has to cover every method this
        // routine goes on to call, or a loose $xoopsDB satisfies the check and
        // then fatals on the call below.
        if (! is_object($db)
            || ! method_exists($db, 'query')
            || ! method_exists($db, 'isResultSet')
            || ! method_exists($db, 'fetchRow')) {
            return $this->row('sql_mode', 'Unavailable', 'info', 'No database connection to query.');
        }

        try {
            $result = $db->query('SELECT @@SESSION.sql_mode');
            // Two-part fetch guard, per the XOOPS convention: isResultSet() alone
            // still admits `true`, which query() returns for a statement yielding
            // no result set, and fetchRow() cannot be handed that.
            if (! $db->isResultSet($result) || ! ($result instanceof \mysqli_result)) {
                return $this->row('sql_mode', 'Unavailable', 'info', 'The server returned no result.');
            }
            $row = $db->fetchRow($result);
            $mode = is_array($row) ? trim((string) ($row[0] ?? '')) : '';
        } catch (\Throwable) {
            return $this->row('sql_mode', 'Unavailable', 'info', 'The mode could not be read.');
        }

        if ('' === $mode) {
            return $this->row(
                'sql_mode',
                'Not strict',
                'warning',
                'sql_mode is empty: oversized and out-of-range values are truncated silently, not rejected.'
            );
        }

        $strict = str_contains($mode, 'STRICT_TRANS_TABLES') || str_contains($mode, 'STRICT_ALL_TABLES');

        return $this->row(
            'sql_mode',
            $strict ? 'Strict' : 'Not strict',
            $strict ? 'ok' : 'warning',
            $strict
                ? $mode
                : 'Oversized values are truncated silently rather than rejected. ' . $mode
        );
    }

    /** Render a callable as a readable name without invoking it. */
    private function describeCallable(mixed $handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler) && 2 === count($handler)) {
            $target = $handler[0];

            return (is_object($target) ? $target::class : (string) $target) . '::' . (string) $handler[1];
        }

        if ($handler instanceof \Closure) {
            return 'Closure';
        }

        // An invokable object is a perfectly legal handler and set_error_handler
        // accepts one. Reporting it as '' made errorHandlerRow() say "PHP
        // default" for a handler that was very much installed.
        return is_object($handler) ? $handler::class . '::__invoke' : '';
    }

    /**
     * What xoops_data/data/debug.php is doing this request -- including failing.
     *
     * This is the ONLY place a broken debug.php is reported. xoops_getDebugConfig()
     * deliberately swallows the failure during bootstrap, because at that point nothing
     * has configured error display and a warning would be emitted under php.ini's rules
     * -- a path printed to whoever loaded the page on a misconfigured host. The reason is
     * carried to here instead, where the request is already known to belong to an
     * authenticated administrator looking at a diagnostics page.
     *
     * @return array{id: string, value: string, status: string, detail: string}
     */
    private function debugFileRow(): array
    {
        if (! function_exists('xoops_getDebugConfig')) {
            return $this->row('debug_file', 'Not supported', 'info', 'This XOOPS core predates the debug.php loader.');
        }

        if (function_exists('xoops_getDebugConfigError')) {
            $error = (string) \xoops_getDebugConfigError();
            if ('' !== $error) {
                return $this->row('debug_file', 'Load failed', 'warning', $error);
            }
        }

        $config = \xoops_getDebugConfig();
        if (! is_array($config) || [] === $config) {
            return $this->row('debug_file', 'Not present', 'ok', 'Production behaviour; no debug.php in xoops_data/data.');
        }

        $enablesDebugbar = true === ($config['debugbar']['enabled'] ?? false);

        return $this->row(
            'debug_file',
            'Active',
            'info',
            $enablesDebugbar
                ? 'debug.php is enabling DebugBar independently of the database Debug Mode.'
                : 'debug.php is loaded but does not enable DebugBar.'
        );
    }

    /**
     * Who owns PHP's error and exception handlers, from the horse's mouth.
     *
     * This used to be a Tracy-shaped row reading XOOPS_TRACY_STATUS, which told the truth
     * on a Tracy site and nothing at all on any other: a site running xWhoops saw "Not
     * installed" while Whoops was holding the handlers. The row was named for the tool
     * somebody happened to be using rather than for the question being asked.
     *
     * XOOPS 2.7.3 publishes the answer for ANY provider, so read that instead. Falls back
     * to a package check on an older core, where the constants do not exist -- an absent
     * constant is a statement in itself: this core predates the seam.
     *
     * @return array{id: string, value: string, status: string, detail: string}
     */
    private function errorScreenRow(): array
    {
        if (! defined('XOOPS_ERROR_SCREEN_STATUS')) {
            return $this->row(
                'error_screen',
                'Unknown',
                'info',
                'This XOOPS predates the error-screen seam, so nothing publishes who owns the handlers.'
            );
        }

        $status = (string) XOOPS_ERROR_SCREEN_STATUS;
        $owner = defined('XOOPS_ERROR_SCREEN_OWNER') ? (string) XOOPS_ERROR_SCREEN_OWNER : '';
        $source = defined('XOOPS_ERROR_SCREEN_SOURCE') ? (string) XOOPS_ERROR_SCREEN_SOURCE : '';
        $detail = defined('XOOPS_ERROR_SCREEN_MESSAGE') ? (string) XOOPS_ERROR_SCREEN_MESSAGE : '';

        if ('' !== $source && 'core' !== $owner) {
            $detail = trim($detail . ' (owner "' . $owner . '", from ' . $source . ')');
        }

        // 'core' is not a problem and must not read as one -- it is what a site without a
        // provider is supposed to look like. The warnings are reserved for the states
        // where somebody configured something and it is not doing what they think.
        return match ($status) {
            'core' => $this->row('error_screen', 'XoopsLogger', 'info', $detail),
            'active' => $this->row('error_screen', $owner, 'ok', $detail),
            'dormant' => $this->row('error_screen', 'Dormant', 'info', $detail),
            'disabled' => $this->row('error_screen', 'Disabled', 'info', $detail),
            'suppressed' => $this->row('error_screen', 'Suppressed', 'info', $detail),
            'unclaimed' => $this->row('error_screen', 'Unclaimed', 'warning', $detail),
            'contested' => $this->row('error_screen', 'Contested', 'warning', $detail),
            'error' => $this->row('error_screen', 'Failed to start', 'warning', $detail),
            'missing' => $this->row('error_screen', 'Library missing', 'warning', $detail),
            'incompatible' => $this->row('error_screen', 'Incompatible', 'warning', $detail),
            // A provider may report anything; core publishes it verbatim and so do we.
            default => $this->row('error_screen', ucfirst($status), 'info', $detail),
        };
    }

    /** @return array{id: string, value: string, status: string, detail: string} */
    private function writableRow(string $id, string $path): array
    {
        if (! is_dir($path)) {
            return $this->row($id, 'Missing', 'warning', 'Required directory was not found.');
        }

        $writable = is_writable($path);

        return $this->row($id, $writable ? 'Writable' : 'Read only', $writable ? 'ok' : 'warning');
    }

    /**
     * The on-demand EXPLAIN design now stashes the server's own recorded SQL
     * server-side instead of trusting a client-submitted signed token (see
     * DebugbarLogger::stashQueriesForExplain()/explain.php). This row reports
     * the health of that stash directory instead: does it exist, is it
     * writable, how many cached entries are sitting in it.
     *
     * @return array{id: string, value: string, status: string, detail: string}
     */
    private function explainStashRow(): array
    {
        $path = rtrim($this->varPath, '/\\') . '/caches/debugbar_explain';

        if (! is_dir($path)) {
            return $this->row(
                'explain_stash',
                'Missing',
                'info',
                'Created automatically the first time a slow query is stashed for on-demand EXPLAIN.'
            );
        }

        if (! is_writable($path)) {
            return $this->row(
                'explain_stash',
                'Read only',
                'warning',
                'Make the directory writable so slow-query EXPLAIN stashing can work.'
            );
        }

        $files = glob($path . '/*.json');
        $count = is_array($files) ? count($files) : 0;

        return $this->row(
            'explain_stash',
            'Ready',
            'ok',
            sprintf('%d cached %s', $count, $count === 1 ? 'stash file' : 'stash files')
        );
    }

    /** @return array{id: string, value: string, status: string, detail: string} */
    private function fileRow(string $id, string $relativePath): array
    {
        $present = is_file($this->rootPath . '/' . $relativePath);

        return $this->row($id, str_replace('\\', '/', $relativePath), $present ? 'ok' : 'warning', $present ? 'File found.' : 'File missing.');
    }

    private function safeName(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $name = basename(str_replace('\\', '/', trim((string) $value)));

        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1 ? $name : '';
    }

    private function xdebugDetail(): string
    {
        if (! extension_loaded('xdebug')) {
            return '';
        }

        $mode = trim((string) ini_get('xdebug.mode'));

        return $mode === '' ? 'Mode not reported.' : 'Modes: ' . $mode;
    }

    private function opcacheDetail(): string
    {
        if (! extension_loaded('Zend OPcache')) {
            return '';
        }

        return filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN) ? 'Enabled for web requests.' : 'Loaded but disabled for web requests.';
    }

    private function packageInstalled(string $package): bool
    {
        if (! class_exists(\Composer\InstalledVersions::class)) {
            return false;
        }

        return \Composer\InstalledVersions::isInstalled($package);
    }
}
