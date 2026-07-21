<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

use XoopsModules\Debugbar\ExplainSecretStore;

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
                $this->explainSecretRow(),
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

    /** @return array{id: string, value: string, status: string, detail: string} */
    private function explainSecretRow(): array
    {
        $status = (new ExplainSecretStore($this->varPath . '/data'))->status();
        $underDocumentRoot = $this->isPathWithin($this->varPath, $this->rootPath);

        if ($status === 'available') {
            return $this->row(
                'explain_secret',
                'Available',
                $underDocumentRoot ? 'warning' : 'ok',
                $underDocumentRoot
                    ? 'Protected data is below the document root; verify that the web server denies direct access.'
                    : 'Signed EXPLAIN actions are available.'
            );
        }

        return match ($status) {
            'invalid' => $this->row('explain_secret', 'Invalid', 'warning', 'Run the module update to replace the signing key.'),
            'unsafe' => $this->row('explain_secret', 'Unsafe', 'warning', 'The signing-key destination is not a safe regular file.'),
            'unwritable' => $this->row('explain_secret', 'Unwritable', 'warning', 'Make the protected XOOPS data directory writable and run the module update.'),
            default => $this->row('explain_secret', 'Missing', 'warning', 'Run the module update to create the signing key.'),
        };
    }

    private function isPathWithin(string $path, string $parent): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/') . '/';
        $parent = rtrim(str_replace('\\', '/', $parent), '/') . '/';

        return str_starts_with(strtolower($path), strtolower($parent));
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
