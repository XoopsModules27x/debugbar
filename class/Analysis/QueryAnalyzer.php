<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

// Pure analysis helper — safe without a booted XOOPS (unit tests load this class alone).

/**
 * Analyse the compact query log without touching the database.
 *
 * N+1 / duplicate detection uses *exact* SQL identity (whitespace-normalised),
 * not a fully parameterised fingerprint. Stripping every integer made legitimate
 * one-shot loads look identical, e.g.:
 *
 *   SELECT * FROM config WHERE conf_modid = 22
 *   SELECT * FROM config WHERE conf_modid = 155
 *
 * were fingerprinted as one "repeated" query. Real N+1s we care about on XOOPS
 * pages are usually the same statement re-executed (same mid, same tpl_file, …).
 *
 * A separate shape fingerprint (strings + numbers collapsed) is retained for
 * "similar_shapes" so classic id-loop N+1 can still be spotted when useful.
 */
final class QueryAnalyzer
{
    /** Maximum entries returned per findings list, so one pathological page cannot flood the report. */
    public const MAX_REPORTED = 10;

    /**
     * @param array<int, array<string, mixed>> $queries
     * @return array{
     *     count: int,
     *     total_ms: float,
     *     error_count: int,
     *     slowest_ms: float,
     *     slowest_fp: string,
     *     worst_repeat: int,
     *     duplicates: list<array{sql: string, count: int}>,
     *     n_plus_one: list<array{sql: string, count: int}>,
     *     similar_shapes: list<array{sql: string, count: int, variants: int}>,
     *     slow: list<array{sql: string, ms: float}>
     * }
     */
    public static function analyze(array $queries, float $slowThreshold, int $repeatThreshold = 5): array
    {
        $repeatThreshold = self::normalizeRepeatThreshold($repeatThreshold);
        $exactCounts = [];
        $exactSamples = [];
        $shapeCounts = [];
        $shapeSamples = [];
        $shapeVariants = [];
        $slow = [];
        $total = 0.0;
        $errors = 0;
        $count = 0;
        // Tracked over every query, not just the ones past the slow threshold:
        // on a page where nothing is slow enough to report, the slowest query is
        // still the one worth recording against the profile.
        $slowestMs = 0.0;
        $slowestSql = '';

        foreach ($queries as $query) {
            $sql = trim((string) ($query['sql'] ?? ''));
            $ms = (float) ($query['ms'] ?? 0.0);
            if ($sql === '') {
                continue;
            }
            $count++;
            $total += $ms;

            $exactKey = self::exactKey($sql);
            $exactCounts[$exactKey] = ($exactCounts[$exactKey] ?? 0) + 1;
            $exactSamples[$exactKey] = $sql;

            $shapeKey = QueryFingerprinter::fingerprint($sql);
            $shapeCounts[$shapeKey] = ($shapeCounts[$shapeKey] ?? 0) + 1;
            $shapeSamples[$shapeKey] = $sql;
            $shapeVariants[$shapeKey][$exactKey] = true;

            if (self::hasError($query['error'] ?? null)) {
                $errors++;
            }
            if ($ms > $slowestMs) {
                $slowestMs = $ms;
                $slowestSql = $sql;
            }
            if ($ms >= $slowThreshold * 1000.0) {
                $slow[] = ['sql' => $sql, 'ms' => $ms];
            }
        }

        $duplicates = [];
        foreach ($exactCounts as $key => $n) {
            $duplicates[] = ['sql' => $exactSamples[$key], 'count' => $n];
        }
        usort($duplicates, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        usort($slow, static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);

        $nPlusOne = $repeatThreshold === 0
            ? []
            : array_values(array_filter($duplicates, static fn (array $row): bool => $row['count'] >= $repeatThreshold));

        // Shape-based: many executions of the same pattern with multiple distinct
        // exact variants (classic SELECT … WHERE id = N loop). Ignore shapes that
        // are already reported as exact duplicates.
        $similarShapes = [];
        if ($repeatThreshold > 0) {
            foreach ($shapeCounts as $shapeKey => $n) {
                $variants = count($shapeVariants[$shapeKey] ?? []);
                // The >= 3 variant floor also excludes pure exact-dupe shapes (a single
                // variant repeated), which are already reported as exact duplicates.
                if ($n < $repeatThreshold || $variants < 3) {
                    continue;
                }
                $similarShapes[] = [
                    'sql' => $shapeSamples[$shapeKey],
                    'count' => $n,
                    'variants' => $variants,
                ];
            }
            usort($similarShapes, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        }

        return [
            'count' => $count,
            'total_ms' => round($total, 3),
            'error_count' => $errors,
            'slowest_ms' => $slowestMs,
            'slowest_fp' => $slowestSql === '' ? '' : QueryFingerprinter::fingerprint($slowestSql),
            'worst_repeat' => $nPlusOne === [] ? 0 : max(array_column($nPlusOne, 'count')),
            // Capped: a page issuing thousands of queries would otherwise flood the
            // Performance panel, the flight-recorder JSON, and the warning log with
            // one entry per finding. The counts above stay exact.
            'duplicates' => array_slice(array_values(array_filter($duplicates, static fn (array $row): bool => $row['count'] > 1)), 0, self::MAX_REPORTED),
            'n_plus_one' => array_slice($nPlusOne, 0, self::MAX_REPORTED),
            'similar_shapes' => array_slice($similarShapes, 0, self::MAX_REPORTED),
            'slow' => array_slice($slow, 0, self::MAX_REPORTED),
        ];
    }

    public static function normalizeRepeatThreshold(int $threshold): int
    {
        if ($threshold <= 0) {
            return 0;
        }

        return max(2, $threshold);
    }

    private static function hasError(mixed $error): bool
    {
        return ! in_array($error, [null, false, 0, 0.0, '', '0', []], true);
    }

    /**
     * Exact-identity key for duplicate / N+1 detection (keeps module ids etc.).
     */
    public static function exactKey(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
        $sql = preg_replace('/\s*([=<>(),])\s*/', '$1', $sql) ?? $sql;

        return strtolower($sql);
    }
}
