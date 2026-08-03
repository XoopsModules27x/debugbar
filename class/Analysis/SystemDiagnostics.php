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
                $this->tracyBootstrapRow(),
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

        $name     = $this->describeCallable($current);
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
            'php' === $detected ? 'PHP default' : ('' !== $name ? $name : $detected),
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
        if (! is_object($db) || ! method_exists($db, 'query') || ! method_exists($db, 'isResultSet')) {
            return $this->row('sql_mode', 'Unavailable', 'info', 'No database connection to query.');
        }

        try {
            $result = $db->query('SELECT @@SESSION.sql_mode');
            if (! $db->isResultSet($result)) {
                return $this->row('sql_mode', 'Unavailable', 'info', 'The server returned no result.');
            }
            $row  = $db->fetchRow($result);
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

        return $handler instanceof \Closure ? 'Closure' : '';
    }

    /** @return array{id: string, value: string, status: string, detail: string} */
    private function tracyBootstrapRow(): array
    {
        if (! defined('XOOPS_TRACY_STATUS')) {
            return $this->packageRow('tracy', ['tracy/tracy']);
        }

        $bootstrapStatus = (string) XOOPS_TRACY_STATUS;
        $detail = defined('XOOPS_TRACY_MESSAGE') ? (string) XOOPS_TRACY_MESSAGE : '';

        return match ($bootstrapStatus) {
            'active' => $this->row('tracy', 'Active', 'ok', $detail),
            'incompatible' => $this->row('tracy', 'Incompatible', 'warning', $detail),
            'error' => $this->row('tracy', 'Initialization failed', 'warning', $detail),
            'missing' => $this->row('tracy', 'Not installed', 'warning', $detail),
            default => $this->row('tracy', 'Disabled', 'info', $detail),
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
     * A's on-demand EXPLAIN design now stashes the server's own recorded SQL
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
            sprintf('%d cached %s', $count, $count === 1 ? 'query' : 'queries')
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
