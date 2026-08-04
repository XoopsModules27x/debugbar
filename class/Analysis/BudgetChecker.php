<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Compare request metrics with optional development budgets. */
final class BudgetChecker
{
    public const FLAG_QUERIES = 1;

    public const FLAG_QUERY_MS = 2;

    public const FLAG_BOOT_MS = 4;

    public const FLAG_TOTAL_MS = 8;

    public const FLAG_MEMORY = 16;

    public const FLAG_PAYLOAD = 32;

    public const FLAG_NPLUSONE = 64;

    public const FLAG_FRAGMENT_FULL_THEME = 128;

    public const FLAG_DUPLICATE_RUNTIME = 256;

    public const FLAG_QUERY_ERRORS = 512;

    /**
     * Metric key => [budget key, flag, human label] for the numeric "value <= budget" checks.
     */
    private const NUMERIC_CHECKS = [
        'queries' => ['budget_queries', self::FLAG_QUERIES, 'queries'],
        'query_ms' => ['budget_query_ms', self::FLAG_QUERY_MS, 'SQL time (ms)'],
        'boot_ms' => ['budget_boot_ms', self::FLAG_BOOT_MS, 'boot time (ms)'],
        'total_ms' => ['budget_total_ms', self::FLAG_TOTAL_MS, 'request time (ms)'],
        'memory_mb' => ['budget_memory_mb', self::FLAG_MEMORY, 'peak memory (MB)'],
        'payload_kb' => ['budget_payload_kb', self::FLAG_PAYLOAD, 'payload (KB)'],
    ];

    /**
     * Compare a request's metrics against the configured budgets.
     *
     * Findings are returned structured rather than preformatted so a caller can
     * render them per surface — the Performance tab colours them by severity and
     * shows the budget alongside the value, while the log wants one line. Use
     * describe() for the one-line form.
     *
     * @param array<string, mixed> $metrics keys: queries, query_ms, boot_ms, total_ms,
     *                                      memory_mb, payload_kb (numeric); worst_repeat,
     *                                      query_errors (int); fragment_full_theme (bool);
     *                                      duplicate_runtimes (array keyed by runtime name)
     * @param array<string, int|float> $budgets a value of 0 disables that check
     *
     * @return array{flags: int, findings: list<array{metric: string, label: string, value: float, budget: float, ok: bool}>}
     */
    public static function check(array $metrics, array $budgets): array
    {
        $findings = [];
        $flags = 0;

        foreach (self::NUMERIC_CHECKS as $metric => [$budgetKey, $flag, $label]) {
            $limit = (float) ($budgets[$budgetKey] ?? 0);
            if ($limit <= 0) {
                continue;
            }
            $value = (float) ($metrics[$metric] ?? 0);
            $ok = $value <= $limit;
            if (! $ok) {
                $flags |= $flag;
            }
            $findings[] = ['metric' => $metric, 'label' => $label, 'value' => $value, 'budget' => $limit, 'ok' => $ok];
        }

        $repeatLimit = QueryAnalyzer::normalizeRepeatThreshold((int) ($budgets['nplus1_threshold'] ?? 0));
        if ($repeatLimit > 0) {
            $worstRepeat = (int) ($metrics['worst_repeat'] ?? 0);
            $ok = $worstRepeat < $repeatLimit;
            if (! $ok) {
                $flags |= self::FLAG_NPLUSONE;
            }
            $findings[] = ['metric' => 'n_plus_one', 'label' => 'repeated query executions', 'value' => (float) $worstRepeat, 'budget' => (float) $repeatLimit, 'ok' => $ok];
        }

        // A failed query is always a violation — there is no budget for it.
        $queryErrors = (int) ($metrics['query_errors'] ?? 0);
        if ($queryErrors > 0) {
            $flags |= self::FLAG_QUERY_ERRORS;
            $findings[] = ['metric' => 'query_errors', 'label' => 'failed queries', 'value' => (float) $queryErrors, 'budget' => 0.0, 'ok' => false];
        }

        // A fragment/AJAX response that still rendered a whole themed document
        // took the full theme path it should have bypassed.
        if (true === ($metrics['fragment_full_theme'] ?? false)) {
            $flags |= self::FLAG_FRAGMENT_FULL_THEME;
            $findings[] = ['metric' => 'fragment_full_theme', 'label' => 'fragment request rendered the full theme', 'value' => 1.0, 'budget' => 0.0, 'ok' => false];
        }

        // More than one copy of the same JS runtime on the page.
        $duplicateRuntimes = $metrics['duplicate_runtimes'] ?? [];
        if (is_array($duplicateRuntimes) && [] !== $duplicateRuntimes) {
            $flags |= self::FLAG_DUPLICATE_RUNTIME;
            $findings[] = ['metric' => 'duplicate_runtime', 'label' => 'duplicated JS runtimes', 'value' => (float) count($duplicateRuntimes), 'budget' => 0.0, 'ok' => false];
        }

        return ['flags' => $flags, 'findings' => $findings];
    }

    /**
     * Render one finding as a single log-friendly line.
     *
     * @param array{metric: string, label: string, value: float, budget: float, ok: bool} $finding
     */
    public static function describe(array $finding): string
    {
        if ($finding['ok']) {
            return sprintf('%s within budget: %s <= %s', $finding['label'], self::format($finding['value']), self::format($finding['budget']));
        }
        if ($finding['budget'] > 0.0) {
            return sprintf('%s exceeded: %s > %s', $finding['label'], self::format($finding['value']), self::format($finding['budget']));
        }

        return sprintf('%s: %s', $finding['label'], self::format($finding['value']));
    }

    /**
     * Reduce findings to the violated ones, as log-friendly lines.
     *
     * @param list<array{metric: string, label: string, value: float, budget: float, ok: bool}> $findings
     * @return list<string>
     */
    public static function describeViolations(array $findings): array
    {
        $lines = [];
        foreach ($findings as $finding) {
            if (! $finding['ok']) {
                $lines[] = self::describe($finding);
            }
        }

        return $lines;
    }

    private static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    /** @return list<string> */
    public static function decodeFlags(int $flags): array
    {
        $names = [
            self::FLAG_QUERIES => 'queries',
            self::FLAG_QUERY_MS => 'sql',
            self::FLAG_BOOT_MS => 'boot',
            self::FLAG_TOTAL_MS => 'request',
            self::FLAG_MEMORY => 'memory',
            self::FLAG_PAYLOAD => 'payload',
            self::FLAG_NPLUSONE => 'n+1',
            self::FLAG_FRAGMENT_FULL_THEME => 'fragment-full-theme',
            self::FLAG_DUPLICATE_RUNTIME => 'duplicate-runtime',
            self::FLAG_QUERY_ERRORS => 'query-errors',
        ];
        $result = [];
        foreach ($names as $flag => $name) {
            if (($flags & $flag) !== 0) {
                $result[] = $name;
            }
        }

        return $result;
    }
}
