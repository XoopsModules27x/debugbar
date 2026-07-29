<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

/**
 * DebugBar Module - Xdebug Status Evaluator
 *
 * Pure evaluation of the live Xdebug/profiler environment (extension
 * loaded, effective modes, output directory reachability) so the
 * Analytics page can explain why profiling is or isn't available
 * without duplicating ini-reading logic.
 *
 * @category  Module
 * @package   debugbar
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Evaluate whether Xdebug profiling is usable and where its output lives.
 */
final class XdebugStatus
{
    /**
     * Pure decision over an already-collected snapshot of the environment.
     *
     * @param bool     $loaded            xdebug extension loaded
     * @param string[] $effectiveModes    effective xdebug.mode values
     * @param string   $startWithRequest  xdebug.start_with_request value
     * @param string   $outputDir         xdebug.output_dir value
     * @param bool     $dirExists         is_dir($outputDir)
     * @param bool     $dirReadable       is_readable($outputDir)
     * @param bool     $zlibLoaded        zlib extension loaded
     * @param string   $sysTempDir        sys_get_temp_dir() value
     *
     * @return array{
     *     extension_loaded: bool,
     *     modes: string[],
     *     start_with_request: string,
     *     output_dir: string,
     *     output_dir_state: string,
     *     can_trigger: bool,
     *     can_list: bool,
     *     can_parse: bool,
     *     shared_dir_warning: bool,
     *     zlib: bool
     * }
     */
    public static function evaluate(
        bool $loaded,
        array $effectiveModes,
        string $startWithRequest,
        string $outputDir,
        bool $dirExists,
        bool $dirReadable,
        bool $zlibLoaded,
        string $sysTempDir
    ): array {
        if ('' === $outputDir) {
            $state = 'unconfigured';
        } elseif (! $dirExists) {
            $state = 'missing';
        } elseif (! $dirReadable) {
            $state = 'unreadable';
        } else {
            $state = 'ok';
        }

        $canListParse = 'ok' === $state;
        $canTrigger = $loaded && \in_array('profile', $effectiveModes, true) && 'trigger' === $startWithRequest;

        $sharedDirWarning = 'ok' === $state
            && rtrim($outputDir, '/\\') === rtrim($sysTempDir, '/\\');

        return [
            'extension_loaded' => $loaded,
            'modes' => array_values($effectiveModes),
            'start_with_request' => $startWithRequest,
            'output_dir' => $outputDir,
            'output_dir_state' => $state,
            'can_trigger' => $canTrigger,
            'can_list' => $canListParse,
            'can_parse' => $canListParse,
            'shared_dir_warning' => $sharedDirWarning,
            'zlib' => $zlibLoaded,
        ];
    }

    /**
     * Never-throw live wrapper.
     *
     * @return array{
     *     extension_loaded: bool,
     *     modes: string[],
     *     start_with_request: string,
     *     output_dir: string,
     *     output_dir_state: string,
     *     can_trigger: bool,
     *     can_list: bool,
     *     can_parse: bool,
     *     shared_dir_warning: bool,
     *     zlib: bool,
     *     trigger_value_set: bool
     * }
     */
    public static function read(): array
    {
        try {
            $loaded = \extension_loaded('xdebug');

            if (\function_exists('xdebug_info')) {
                $modes = (array) @\xdebug_info('mode');
            } else {
                $modes = [];
            }
            if ([] === $modes) {
                $raw = (string) \ini_get('xdebug.mode');
                $modes = '' === $raw ? [] : explode(',', $raw);
            }
            $modes = array_values(array_map('strval', $modes));

            $startWithRequest = (string) \ini_get('xdebug.start_with_request');
            $outputDir = (string) \ini_get('xdebug.output_dir');
            $dirExists = '' !== $outputDir && is_dir($outputDir);
            $dirReadable = '' !== $outputDir && is_readable($outputDir);
            $zlibLoaded = \extension_loaded('zlib');
            $sysTempDir = sys_get_temp_dir();

            $status = self::evaluate(
                $loaded,
                $modes,
                $startWithRequest,
                $outputDir,
                $dirExists,
                $dirReadable,
                $zlibLoaded,
                $sysTempDir
            );
            $status['trigger_value_set'] = ('' !== (string) \ini_get('xdebug.trigger_value'));

            return $status;
        } catch (\Throwable $e) {
            // The fallback must carry every key of the success shape: a consumer
            // reading trigger_value_set here would otherwise hit an undefined key.
            $status = self::evaluate(false, [], '', '', false, false, false, '');
            $status['trigger_value_set'] = false;

            return $status;
        }
    }
}
