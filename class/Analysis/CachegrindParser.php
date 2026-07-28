<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

/**
 * DebugBar Module - Cachegrind/Callgrind Profile Parser
 *
 * Pure parser for Xdebug profiler output (cachegrind.out.* files, plain or
 * gzip-compressed). No XOOPS calls, no superglobals; never throws.
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
 * Parses a Callgrind-format profile into a CachegrindResult.
 */
final class CachegrindParser
{
    /** Hard cap on input file size (bytes) before we refuse to even open it */
    private const MAX_BYTES = 20_971_520;

    /** Default wall-clock parse budget in milliseconds, checked every 50,000 lines */
    private const TIME_BUDGET_MS = 500;

    /**
     * @param int $timeBudgetMs wall-clock parse budget in milliseconds; the
     *                          production default keeps the admin page bounded —
     *                          override only for offline/batch analysis
     */
    public function __construct(private readonly int $timeBudgetMs = self::TIME_BUDGET_MS)
    {
    }

    /** Hard cap on lines consumed, regardless of elapsed time */
    private const MAX_LINES = 2_000_000;

    /** Read buffer size for fgets()/gzgets() */
    private const READ_CHUNK = 65536;

    /** How often (in lines) to check the wall-clock time budget */
    private const TIME_CHECK_INTERVAL = 50_000;

    /**
     * Time-unit token -> [divisor, resulting unit label].
     * Applied uniformly to self/incl/total once the Time event index is known.
     */
    private const TIME_UNIT_TABLE = [
        'Time_(10ns)' => ['divisor' => 1.0e5, 'unit' => 'ms'],
        'Time_(µs)' => ['divisor' => 1.0e3, 'unit' => 'ms'],
        'Time_(us)' => ['divisor' => 1.0e3, 'unit' => 'ms'],
    ];

    /**
     * Parse a Callgrind/Cachegrind profile file. Never throws.
     *
     * @param string $path input file path (plain or .gz)
     * @param int    $topN maximum number of function rows to return
     *
     * @return CachegrindResult
     */
    public function parse(string $path, int $topN = 30): CachegrindResult
    {
        try {
            return $this->doParse($path, $topN);
        } catch (\Throwable $e) {
            return new CachegrindResult(
                'invalid',
                '',
                null,
                [],
                ['invalid: ' . $e->getMessage()],
                null,
                null,
                0
            );
        }
    }

    /**
     * @param string $path
     * @param int    $topN
     *
     * @return CachegrindResult
     */
    private function doParse(string $path, int $topN): CachegrindResult
    {
        $isGz = str_ends_with(strtolower($path), '.gz');

        if ($isGz && ! \function_exists('gzopen')) {
            return new CachegrindResult(
                'unreadable',
                '',
                null,
                [],
                ['zlib_missing: gz support unavailable (ext-zlib missing)'],
                null,
                null,
                0
            );
        }

        if (! is_file($path) || ! is_readable($path)) {
            return new CachegrindResult(
                'unreadable',
                '',
                null,
                [],
                ['unreadable: file not found or not readable'],
                null,
                null,
                0
            );
        }

        $size = filesize($path);
        if (false !== $size && $size > self::MAX_BYTES) {
            return new CachegrindResult(
                'too_large',
                '',
                null,
                [],
                ['too_large: file exceeds ' . self::MAX_BYTES . ' bytes'],
                null,
                null,
                0
            );
        }

        $h = $isGz ? gzopen($path, 'rb') : fopen($path, 'rb');
        if (false === $h) {
            return new CachegrindResult(
                'unreadable',
                '',
                null,
                [],
                ['unreadable: could not open file'],
                null,
                null,
                0
            );
        }

        try {
            return $this->parseHandle($h, $isGz, $topN);
        } finally {
            $isGz ? gzclose($h) : fclose($h);
        }
    }

    /**
     * @param resource $h
     * @param bool     $isGz
     * @param int      $topN
     *
     * @return CachegrindResult
     */
    private function parseHandle($h, bool $isGz, int $topN): CachegrindResult
    {
        $status = 'ok';
        $warnings = [];

        $creator = null;
        $command = null;

        $fileDict = [];
        $funcDict = [];

        $currentFile = null;
        $currentFunctionName = null;
        $calleeFile = null;
        $pendingCalleeName = null;

        $partSeen = null;
        $eventsSeen = false;
        $eventsTokens = null;
        $timeIndex = null;
        $positionsCount = 1;
        $summaryTotal = null;

        $awaitingInclusiveCostLine = false;
        $awaitingCallsCount = 0;
        $awaitingCalleeName = null;
        $awaitingCallerName = null;

        $hasCostData = false;
        $recursionWarned = false;

        /** @var array<string, array{file: string, calls: int, invocations: int, self: float, inclusive_extra: float}> $functions */
        $functions = [];

        $linesRead = 0;
        $startTime = hrtime(true);

        while (false !== ($line = $isGz ? gzgets($h, self::READ_CHUNK) : fgets($h, self::READ_CHUNK))) {
            $linesRead++;

            if ($linesRead > self::MAX_LINES) {
                $status = 'truncated';
                $warnings[] = 'line_cap: parse stopped at ' . self::MAX_LINES . ' lines';

                break;
            }

            if (0 === $linesRead % self::TIME_CHECK_INTERVAL) {
                $elapsedMs = (hrtime(true) - $startTime) / 1.0e6;
                if ($elapsedMs > $this->timeBudgetMs) {
                    $status = 'truncated';
                    $warnings[] = 'time_budget: parse stopped at ' . $this->timeBudgetMs . 'ms';

                    break;
                }
            }

            $trimmed = trim($line);
            if ('' === $trimmed || str_starts_with($trimmed, '#')) {
                continue;
            }

            $first = $trimmed[0];

            // --- FAST PATH: cost lines, the overwhelming majority of a profile.
            // Position tokens start with a digit, '+', '-', '*' (hex = '0x…').
            if (($first >= '0' && $first <= '9') || '+' === $first || '-' === $first || '*' === $first) {
                $hasCostData = true;
                $toks = (false === strpos($trimmed, '  '))
                    ? explode(' ', $trimmed)
                    : preg_split('/\s+/', $trimmed);
                if (false === $toks) {
                    $toks = [];
                }
                $values = \array_slice($toks, min($positionsCount, \count($toks)));
                $valueAtTime = (null !== $timeIndex && isset($values[$timeIndex])) ? (float) $values[$timeIndex] : 0.0;

                if ($awaitingInclusiveCostLine) {
                    $awaitingInclusiveCostLine = false;
                    $calleeName = $awaitingCalleeName;
                    if (null !== $calleeName) {
                        if (! isset($functions[$calleeName])) {
                            $functions[$calleeName] = self::newRow($calleeFile ?? '');
                        }
                        $functions[$calleeName]['calls'] += $awaitingCallsCount;

                        if ($calleeName === $awaitingCallerName) {
                            // direct recursion: calls counted, cost NOT re-added to caller's inclusive
                            if (! $recursionWarned) {
                                $warnings[] = 'recursion: direct recursive call cost excluded from caller inclusive total';
                                $recursionWarned = true;
                            }
                        } elseif (null !== $awaitingCallerName) {
                            if (! isset($functions[$awaitingCallerName])) {
                                $functions[$awaitingCallerName] = self::newRow($currentFile ?? '');
                            }
                            $functions[$awaitingCallerName]['inclusive_extra'] += $valueAtTime;
                        }
                    }
                } elseif (null !== $currentFunctionName) {
                    if (! isset($functions[$currentFunctionName])) {
                        $functions[$currentFunctionName] = self::newRow($currentFile ?? '');
                    }
                    $functions[$currentFunctionName]['self'] += $valueAtTime;
                }

                continue;
            }

            // --- FAST PATH: name specifiers, parsed without regex; dictionaries
            // are written IN PLACE (a temp copy per define is quadratic churn) ---
            $specKey = null;
            $rest = '';
            if ('f' === $first && isset($trimmed[2]) && '=' === $trimmed[2]) {
                $specKey = substr($trimmed, 0, 2);            // fl fi fe fn
                $rest = substr($trimmed, 3);
            } elseif ('c' === $first && 'f' === $trimmed[1] && isset($trimmed[3]) && '=' === $trimmed[3]) {
                $specKey = substr($trimmed, 0, 3);            // cfl cfi cfn
                $rest = substr($trimmed, 4);
            }
            if (null !== $specKey && \in_array($specKey, ['fl', 'fi', 'fe', 'fn', 'cfl', 'cfi', 'cfn'], true)) {
                $isFileDict = 'fn' !== $specKey && 'cfn' !== $specKey;
                if ('' !== $rest && '(' === $rest[0]) {
                    $close = strpos($rest, ')');
                    if (false === $close) {
                        continue; // malformed compressed-name syntax — skip
                    }
                    $id = substr($rest, 1, $close - 1);
                    $name = trim(substr($rest, $close + 1));
                    if ('' !== $name) {
                        // define (redefinition: last wins)
                        if ($isFileDict) {
                            $fileDict[$id] = $name;
                        } else {
                            $funcDict[$id] = $name;
                        }
                        $resolved = $name;
                    } else {
                        // reference
                        $resolved = $isFileDict ? ($fileDict[$id] ?? '') : ($funcDict[$id] ?? '');
                    }
                } else {
                    $resolved = trim($rest); // literal, uncompressed
                }

                switch ($specKey) {
                    case 'fl':
                    case 'fi':
                        $currentFile = $resolved;

                        break;
                    case 'fe':
                        // dictionary participation only; no aggregation side effect
                        break;
                    case 'cfl':
                    case 'cfi':
                        $calleeFile = $resolved;

                        break;
                    case 'fn':
                        $currentFunctionName = $resolved;
                        if (! isset($functions[$currentFunctionName])) {
                            $functions[$currentFunctionName] = self::newRow($currentFile ?? '');
                        }
                        $functions[$currentFunctionName]['invocations']++;
                        $awaitingInclusiveCostLine = false;
                        $pendingCalleeName = null;
                        $hasCostData = true;

                        break;
                    case 'cfn':
                        $pendingCalleeName = $resolved;

                        break;
                }

                continue;
            }

            // --- calls= line ---
            if ('c' === $first && str_starts_with($trimmed, 'calls=')) {
                $awaitingCallsCount = (int) substr($trimmed, \strlen('calls='));
                $awaitingCalleeName = $pendingCalleeName;
                $awaitingCallerName = $currentFunctionName;
                $awaitingInclusiveCostLine = true;
                $hasCostData = true;

                continue;
            }

            // --- header lines (rare: the file head plus the trailing summary) ---
            if (str_starts_with($trimmed, 'version:')) {
                continue;
            }
            if (str_starts_with($trimmed, 'creator:')) {
                $creator = trim(substr($trimmed, \strlen('creator:')));

                continue;
            }
            if (str_starts_with($trimmed, 'cmd:')) {
                $command = basename(trim(substr($trimmed, \strlen('cmd:'))));

                continue;
            }
            if (str_starts_with($trimmed, 'part:')) {
                $partValue = trim(substr($trimmed, \strlen('part:')));
                if (null === $partSeen) {
                    $partSeen = $partValue;
                } elseif ($partValue !== $partSeen) {
                    $status = 'unsupported';
                    $warnings[] = 'multi_part: set xdebug.profiler_append=0';

                    break;
                }

                continue;
            }
            if (str_starts_with($trimmed, 'positions:')) {
                $rest = trim(substr($trimmed, \strlen('positions:')));
                $toks = '' === $rest ? [] : preg_split('/\s+/', $rest);
                if (false === $toks) {
                    $toks = [];
                }
                $positionsCount = (\count($toks) > 0) ? \count($toks) : 1;

                continue;
            }
            if (str_starts_with($trimmed, 'events:')) {
                if ($eventsSeen) {
                    $status = 'unsupported';
                    $warnings[] = 'multi_part: set xdebug.profiler_append=0';

                    break;
                }
                $eventsSeen = true;
                $rest = trim(substr($trimmed, \strlen('events:')));
                $eventsTokens = '' === $rest ? [] : preg_split('/\s+/', $rest);
                if (false === $eventsTokens) {
                    $eventsTokens = [];
                }
                $timeIndex = null;
                foreach ($eventsTokens as $idx => $tok) {
                    if (str_starts_with($tok, 'Time')) {
                        $timeIndex = $idx;

                        break;
                    }
                }

                continue;
            }
            if (str_starts_with($trimmed, 'summary:')) {
                $rest = trim(substr($trimmed, \strlen('summary:')));
                $toks = '' === $rest ? [] : preg_split('/\s+/', $rest);
                if (null !== $timeIndex && isset($toks[$timeIndex])) {
                    $summaryTotal = (float) $toks[$timeIndex];
                }

                continue;
            }

            // anything else: unknown "key: value" line — skip silently
        }

        if ('ok' === $status && ! $eventsSeen) {
            $status = 'invalid';
            $warnings[] = 'invalid: no events header found';
        }
        if ('ok' === $status && [] === $functions && null === $summaryTotal) {
            $status = 'invalid';
            $warnings[] = 'invalid: no functions or summary parsed';
        }
        unset($hasCostData);

        // --- unit resolution (table-driven on the Time token) ---
        $timeToken = (null !== $eventsTokens && null !== $timeIndex) ? ($eventsTokens[$timeIndex] ?? null) : null;
        $divisor = 1.0;
        $unit = '';
        if (null !== $timeToken) {
            if (isset(self::TIME_UNIT_TABLE[$timeToken])) {
                $divisor = self::TIME_UNIT_TABLE[$timeToken]['divisor'];
                $unit = self::TIME_UNIT_TABLE[$timeToken]['unit'];
            } else {
                $divisor = 1.0;
                $unit = $timeToken;
            }
        } elseif (null !== $eventsTokens && isset($eventsTokens[0])) {
            $unit = $eventsTokens[0];
        }

        // Total: the summary line when reached; otherwise the sum of all self
        // costs — exact for a complete file (self costs partition execution
        // time) and a true lower bound for a truncated parse, so self_pct can
        // never exceed 100%. (A {main}-inclusive fallback is wrong on partial
        // parses and undercounts when direct recursion was excluded.)
        $rawTotal = $summaryTotal;
        if (null === $rawTotal && [] !== $functions) {
            $rawTotal = 0.0;
            foreach ($functions as $data) {
                $rawTotal += $data['self'];
            }
        }
        $total = null !== $rawTotal ? $rawTotal / $divisor : null;

        $rows = [];
        foreach ($functions as $name => $data) {
            $selfConverted = $data['self'] / $divisor;
            $inclConverted = ($data['self'] + $data['inclusive_extra']) / $divisor;
            $selfPct = (null !== $total && $total > 0.0) ? ($selfConverted * 100.0 / $total) : 0.0;

            $rows[] = [
                'name' => $name,
                'file' => $data['file'],
                'calls' => $data['calls'],
                'invocations' => $data['invocations'],
                'self' => $selfConverted,
                'incl' => $inclConverted,
                'self_pct' => $selfPct,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = $b['self'] <=> $a['self'];

            return 0 !== $cmp ? $cmp : ($a['name'] <=> $b['name']);
        });
        $rows = \array_slice($rows, 0, max(0, $topN));

        return new CachegrindResult(
            $status,
            $unit,
            $total,
            $rows,
            $warnings,
            $creator,
            $command,
            $linesRead
        );
    }

    /**
     * @param string $file
     *
     * @return array{file: string, calls: int, invocations: int, self: float, inclusive_extra: float}
     */
    private static function newRow(string $file): array
    {
        return [
            'file' => $file,
            'calls' => 0,
            'invocations' => 0,
            'self' => 0.0,
            'inclusive_extra' => 0.0,
        ];
    }
}
