<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Read-only description of whether Xdebug can create on-demand profiles. */
final class XdebugStatus
{
    /**
     * @return array{loaded: bool, modes: list<string>, start_with_request: string, output_dir: string, directory_state: string, zlib: bool, can_trigger: bool}
     */
    public static function read(): array
    {
        $loaded = extension_loaded('xdebug');
        $modes = self::modes((string) ini_get('xdebug.mode'));
        $outputDirectory = trim((string) ini_get('xdebug.output_dir'));

        return self::evaluate(
            $loaded,
            $modes,
            trim((string) ini_get('xdebug.start_with_request')),
            $outputDirectory,
            $outputDirectory !== '' && is_readable($outputDirectory),
            extension_loaded('zlib')
        );
    }

    /**
     * @param list<string> $modes
     * @return array{loaded: bool, modes: list<string>, start_with_request: string, output_dir: string, directory_state: string, zlib: bool, can_trigger: bool}
     */
    public static function evaluate(
        bool $loaded,
        array $modes,
        string $startWithRequest,
        string $outputDirectory,
        bool $directoryReadable,
        bool $zlib
    ): array {
        $directoryState = 'unconfigured';
        if ($outputDirectory !== '') {
            $directoryState = is_dir($outputDirectory)
                ? ($directoryReadable ? 'ok' : 'unreadable')
                : 'missing';
        }

        return [
            'loaded' => $loaded,
            'modes' => $modes,
            'start_with_request' => $startWithRequest,
            'output_dir' => $outputDirectory,
            'directory_state' => $directoryState,
            'zlib' => $zlib,
            'can_trigger' => $loaded && in_array('profile', $modes, true) && $directoryState === 'ok',
        ];
    }

    /** @return list<string> */
    private static function modes(string $value): array
    {
        $modes = array_map('trim', explode(',', strtolower($value)));

        return array_values(array_filter($modes, static fn (string $mode): bool => $mode !== ''));
    }
}
