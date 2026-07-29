<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Admin;

/**
 * DebugBar Module - Analytics View-Model Builder
 *
 * Aggregates stored request profiles, flight-recorder dumps, and server
 * health (OPcache) into plain arrays for the admin Analytics page.
 * No HTML here — the page renders and escapes.
 *
 * @category  Module
 * @package   debugbar
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

use XoopsModules\Debugbar\Analysis\BudgetChecker;
use XoopsModules\Debugbar\Analysis\CachegrindCatalog;
use XoopsModules\Debugbar\Analysis\CachegrindParser;
use XoopsModules\Debugbar\Analysis\CachegrindResult;
use XoopsModules\Debugbar\Analysis\XdebugStatus;
use XoopsModules\Debugbar\FlightRecorder;
use XoopsModules\Debugbar\ProfileRepository;

/**
 * Class AnalyticsBuilder
 *
 * @phpstan-import-type UrlAggregateRow from ProfileRepository
 * @phpstan-import-type NPlusOneRow from ProfileRepository
 * @phpstan-import-type ModuleAggregateRow from ProfileRepository
 * @phpstan-import-type ViolationRow from ProfileRepository
 * @phpstan-import-type VitalsRow from ProfileRepository
 *
 * @phpstan-type DecodedViolationRow array{
 *     request_id: string|null, created: string|null, url: string|null, dirname: string|null,
 *     total_ms: string|null, query_count: string|null, n_plus_one: string|null,
 *     flags: string|null, flag_names: list<string>
 * }
 * @phpstan-type OpcacheHealth array{available: false}|array{
 *     available: true, hit_rate: float, used_mb: float, free_mb: float,
 *     wasted_mb: float, wasted_pct: float, cached_scripts: int, restarts: int
 * }
 */
class AnalyticsBuilder
{
    /**
     * Parse budget (ms) for the explicit cachegrind detail view. Generous on
     * purpose: the web SAPI often runs under Xdebug develop-mode overhead
     * (~3x slower than bare CLI), and a finished parse is cached per file so
     * the cost is paid at most once.
     */
    private const DETAIL_TIME_BUDGET_MS = 20000;

    /** Cached parse results older than this are swept at write time */
    private const CACHE_MAX_AGE_SECONDS = 604800; // 7 days

    private ProfileRepository $repository;

    private FlightRecorder $flightRecorder;

    private CachegrindCatalog $catalog;

    /**
     * @param ProfileRepository|null  $repository     profile storage
     * @param FlightRecorder|null     $flightRecorder dump storage
     * @param CachegrindCatalog|null  $catalog        cachegrind file catalog
     */
    public function __construct(?ProfileRepository $repository = null, ?FlightRecorder $flightRecorder = null, ?CachegrindCatalog $catalog = null)
    {
        $this->repository = $repository ?? new ProfileRepository();
        $this->flightRecorder = $flightRecorder ?? new FlightRecorder();
        $this->catalog = $catalog ?? new CachegrindCatalog();
    }

    /**
     * Build the complete view model.
     *
     * @param int $sinceDays look-back window in days
     *
     * @return array{
     *     table_exists: bool,
     *     since_days: int,
     *     row_count: int,
     *     worst_urls: list<UrlAggregateRow>,
     *     nplus1_leaders: list<NPlusOneRow>,
     *     modules: list<ModuleAggregateRow>,
     *     violations: list<DecodedViolationRow>,
     *     vitals: list<VitalsRow>,
     *     opcache: OpcacheHealth,
     *     flight_records: list<array{file: string, created: int, violation: bool, request_id: string, bytes: int}>,
     *     xdebug: array{
     *         extension_loaded: bool, modes: string[], start_with_request: string,
     *         output_dir: string, output_dir_state: string, can_trigger: bool,
     *         can_list: bool, can_parse: bool, shared_dir_warning: bool, zlib: bool,
     *         trigger_value_set: bool
     *     },
     *     cachegrind_files: list<array{file: string, modified: int, size: int}>
     * }
     */
    public function build(int $sinceDays = 7): array
    {
        $tableExists = $this->repository->tableExists();

        return [
            'table_exists' => $tableExists,
            'since_days' => $sinceDays,
            'row_count' => $tableExists ? $this->repository->countRows() : 0,
            'worst_urls' => $tableExists ? $this->repository->worstUrls($sinceDays) : [],
            'nplus1_leaders' => $tableExists ? $this->repository->nPlusOneLeaders($sinceDays) : [],
            'modules' => $tableExists ? $this->repository->moduleAggregates($sinceDays) : [],
            'violations' => $tableExists ? $this->decodeViolations($this->repository->recentViolations()) : [],
            'vitals' => $tableExists ? $this->repository->vitalsByUrl($sinceDays) : [],
            'opcache' => $this->opcacheHealth(),
            'flight_records' => $this->flightRecorder->listRecords(30),
            'xdebug' => XdebugStatus::read(),
            'cachegrind_files' => $this->catalog->listFiles(50),
        ];
    }

    /**
     * Load one flight-recorder dump (filename is validated inside).
     *
     * @param string $file stored filename
     *
     * @return array<string, mixed>|null
     */
    public function flightRecord(string $file): ?array
    {
        return $this->flightRecorder->load($file);
    }

    /**
     * Parse the top-N functions of one stored cachegrind file.
     *
     * @param string $file  stored filename
     * @param int    $topN  maximum number of function rows to return
     *
     * @return array{result: \XoopsModules\Debugbar\Analysis\CachegrindResult, path: string, mtime: int, bytes: int}|null
     */
    public function cachegrindTop(string $file, int $topN = 30): ?array
    {
        $path = $this->catalog->resolve($file);
        if (null === $path) {
            return null;
        }
        $mtime = (int) @filemtime($path);
        $bytes = (int) @filesize($path);

        // The detail view is an explicit admin action on a bounded (<=20MB) file:
        // real Xdebug profiles of a full page routinely exceed the 500ms default
        // budget and would render a misleading bootstrap-only prefix. A finished
        // parse is cached (keyed by name+mtime+size+topN) so it is paid once.
        $result = $this->cacheRead($file, $mtime, $bytes, $topN);
        if (null === $result) {
            $result = (new CachegrindParser(self::DETAIL_TIME_BUDGET_MS))->parse($path, $topN);
            // Truncated/unreadable parses are retryable — never lock them in.
            if ('truncated' !== $result->status && 'unreadable' !== $result->status) {
                $this->cacheWrite($file, $mtime, $bytes, $topN, $result);
            }
        }

        return [
            'result' => $result,
            'path' => $path,
            'mtime' => $mtime,
            'bytes' => $bytes,
        ];
    }

    /**
     * @return CachegrindCatalog
     */
    public function catalog(): CachegrindCatalog
    {
        return $this->catalog;
    }

    /**
     * Attach human-readable flag names to violation rows.
     *
     * @param list<ViolationRow> $rows raw violation rows
     *
     * @return list<DecodedViolationRow>
     */
    private function decodeViolations(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['flag_names'] = BudgetChecker::decodeFlags((int) ($row['flags'] ?? 0));
        }
        unset($row);

        return $rows;
    }

    /**
     * Directory for cached parse results; null when unavailable.
     *
     * @return string|null
     */
    private function cacheDir(): ?string
    {
        if (! \defined('XOOPS_VAR_PATH')) {
            return null;
        }
        $dir = XOOPS_VAR_PATH . '/caches/debugbar_cachegrind';
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return null;
        }
        if (! is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '<script>history.go(-1);</script>');
        }

        return $dir;
    }

    /**
     * Cache file path for one profile parse.
     *
     * @param string $file  validated basename
     * @param int    $mtime profile mtime
     * @param int    $bytes profile size
     * @param int    $topN  requested row count
     *
     * @return string|null
     */
    private function cachePath(string $file, int $mtime, int $bytes, int $topN): ?string
    {
        $dir = $this->cacheDir();
        if (null === $dir) {
            return null;
        }

        return $dir . '/' . md5($file . '|' . $mtime . '|' . $bytes . '|' . $topN) . '.json';
    }

    /**
     * Load a cached parse result; null on miss or any problem (never throws).
     *
     * @param string $file  validated basename
     * @param int    $mtime profile mtime
     * @param int    $bytes profile size
     * @param int    $topN  requested row count
     *
     * @return CachegrindResult|null
     */
    private function cacheRead(string $file, int $mtime, int $bytes, int $topN): ?CachegrindResult
    {
        try {
            $path = $this->cachePath($file, $mtime, $bytes, $topN);
            if (null === $path || ! is_file($path)) {
                return null;
            }
            $data = json_decode((string) @file_get_contents($path), true);
            if (! \is_array($data) || ! isset($data['status'])) {
                return null;
            }

            return new CachegrindResult(
                (string) $data['status'],
                (string) ($data['unit'] ?? ''),
                isset($data['total']) ? (float) $data['total'] : null,
                (array) ($data['functions'] ?? []),
                array_values(array_map('strval', (array) ($data['warnings'] ?? []))),
                isset($data['creator']) ? (string) $data['creator'] : null,
                isset($data['command']) ? (string) $data['command'] : null,
                (int) ($data['linesRead'] ?? 0)
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Persist a parse result and sweep stale cache entries (never throws).
     *
     * @param string           $file   validated basename
     * @param int              $mtime  profile mtime
     * @param int              $bytes  profile size
     * @param int              $topN   requested row count
     * @param CachegrindResult $result finished parse
     *
     * @return void
     */
    private function cacheWrite(string $file, int $mtime, int $bytes, int $topN, CachegrindResult $result): void
    {
        try {
            $path = $this->cachePath($file, $mtime, $bytes, $topN);
            if (null === $path) {
                return;
            }
            @file_put_contents($path, json_encode($result->toArray()), LOCK_EX);

            $cutoff = time() - self::CACHE_MAX_AGE_SECONDS;
            foreach ((array) @glob(\dirname($path) . '/*.json') as $old) {
                if (\is_string($old) && @filemtime($old) < $cutoff) {
                    @unlink($old);
                }
            }
        } catch (\Throwable $e) {
            // best-effort cache — parsing still worked
        }
    }

    /**
     * Server-wide OPcache health card data.
     *
     * @return OpcacheHealth
     */
    private function opcacheHealth(): array
    {
        if (! function_exists('opcache_get_status')) {
            return ['available' => false];
        }

        try {
            $status = @opcache_get_status(false);
        } catch (\Throwable $e) {
            $status = false;
        }
        if (! is_array($status) || ! isset($status['opcache_enabled']) || ! $status['opcache_enabled']) {
            return ['available' => false];
        }

        $memory = $status['memory_usage'] ?? [];
        $statistics = $status['opcache_statistics'] ?? [];
        $used = (float) ($memory['used_memory'] ?? 0);
        $free = (float) ($memory['free_memory'] ?? 0);
        $wasted = (float) ($memory['wasted_memory'] ?? 0);
        $totalMem = $used + $free + $wasted;

        return [
            'available' => true,
            'hit_rate' => round((float) ($statistics['opcache_hit_rate'] ?? 0), 2),
            'used_mb' => round($used / 1048576, 1),
            'free_mb' => round($free / 1048576, 1),
            'wasted_mb' => round($wasted / 1048576, 1),
            'wasted_pct' => $totalMem > 0 ? round($wasted * 100 / $totalMem, 1) : 0.0,
            'cached_scripts' => (int) ($statistics['num_cached_scripts'] ?? 0),
            'restarts' => (int) ($statistics['oom_restarts'] ?? 0) + (int) ($statistics['hash_restarts'] ?? 0),
        ];
    }
}
