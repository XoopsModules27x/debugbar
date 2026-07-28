<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar;

use DebugBar\DataCollector\ConfigCollector;
use Xmf\Request;
use XoopsModules\Debugbar\Analysis\AssetScanner;
use XoopsModules\Debugbar\Analysis\BudgetChecker;
use XoopsModules\Debugbar\Analysis\DiagnosticSanitizer;
use XoopsModules\Debugbar\Analysis\QueryAnalyzer;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Final request analysis, persistence, and developer-facing performance warnings. */
final class Profiler
{
    private static ?self $instance = null;
    private bool $finalized = false;
    private string $requestId;
    private ?DiagnosticSanitizer $diagnosticSanitizer = null;

    /** Max slowest SELECTs to run EXPLAIN on per request. */
    private const MAX_EXPLAIN = 3;

    private function __construct()
    {
        $this->requestId = bin2hex(random_bytes(8));
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    /**
     * RUM (Real User Monitoring) bootstrap: emit the web-vitals beacon config
     * and loader when the rum_enable preference is on. The client script
     * (assets/xoops-debugbar-rum.js) posts Core Web Vitals to beacon.php, which
     * records them via ProfileRepository::updateVitals(). Skipped for
     * fragment/AJAX responses and when RUM is disabled. Never throws.
     */
    public function getRumHtml(): string
    {
        if ($this->isFragment()) {
            return '';
        }

        try {
            $config = DebugbarCoreConfig::get();
            $rumEnable = ! array_key_exists('rum_enable', $config) || (bool) $config['rum_enable'];
            if (! $rumEnable) {
                return '';
            }

            $rum = [
                'url' => XOOPS_URL . '/modules/debugbar/beacon.php',
                'id' => $this->requestId,
                'token' => $GLOBALS['xoopsSecurity']->createToken(0, 'DEBUGBAR_RUM'),
            ];

            return '<script>window.XoopsDebugbarRum = ' . json_encode($rum, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>' . "\n"
                . '<script defer src="' . XOOPS_URL . '/modules/debugbar/assets/xoops-debugbar-rum.js"></script>' . "\n";
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function finalize(DebugbarLogger $logger): void
    {
        if ($this->finalized) {
            return;
        }
        $this->finalized = true;

        try {
            $debugbar = $logger->getDebugbar();
            $queries = $logger->getQueryLog();
            $budgets = $this->budgets();
            $budgets['nplus1_threshold'] = QueryAnalyzer::normalizeRepeatThreshold((int) ($budgets['nplus1_threshold'] ?? 0));
            $stats = QueryAnalyzer::analyze($queries, $logger->getSlowQueryThreshold(), $budgets['nplus1_threshold']);
            // EXPLAIN the slowest SELECTs and flag full scans / filesorts (dev only).
            $explainSlow = ! array_key_exists('explain_slow', $budgets) || (bool) $budgets['explain_slow'];
            $explainFindings = $explainSlow ? $this->explainSlowQueries($stats['slow']) : [];
            $totalMs = (microtime(true) - $logger->getRequestStart()) * 1000.0;
            $bootMs = $logger->getLifecycleDurationMs('XOOPS Boot');
            $memoryMb = memory_get_peak_usage(true) / 1048576;
            $module = '';
            if (isset($GLOBALS['xoopsModule']) && is_object($GLOBALS['xoopsModule']) && method_exists($GLOBALS['xoopsModule'], 'getVar')) {
                $module = (string) $GLOBALS['xoopsModule']->getVar('dirname', 'n');
            }
            $url = $this->path(Request::getString('REQUEST_URI', '/', 'SERVER'));
            $metrics = ['queries' => $stats['count'], 'query_ms' => $stats['total_ms'], 'boot_ms' => $bootMs, 'total_ms' => $totalMs, 'memory_mb' => $memoryMb, 'payload_kb' => $this->payloadKb(), 'worst_repeat' => $stats['worst_repeat']];
            $verdict = BudgetChecker::check($metrics, $budgets);
            $decodedFlags = BudgetChecker::decodeFlags($verdict['flags']);

            // Duplicate-runtime detection: scan the buffered response for the
            // same JS library (jQuery/Alpine/htmx, ...) loaded more than once.
            // Skipped for fragments and oversized payloads. Never throws.
            $duplicateRuntimes = [];
            if (ob_get_level() > 0 && ! $this->isFragment()) {
                $html = (string) @ob_get_contents();
                if ($html !== '' && strlen($html) <= 2097152) {
                    $scan = AssetScanner::scan($html, XOOPS_URL, XOOPS_ROOT_PATH);
                    if (is_array($scan['duplicate_runtimes'] ?? null)) {
                        $duplicateRuntimes = $scan['duplicate_runtimes'];
                    }
                }
                unset($html);
            }

            if (is_object($debugbar)) {
                $debugbar->addCollector(new ConfigCollector([
                    'Request ID' => $this->requestId,
                    'URL' => $url,
                    'Module' => $module !== '' ? $module : '(none)',
                    'Fragment' => $this->isFragment() ? 'yes' : 'no',
                    'Total' => sprintf('%.1f ms', $totalMs),
                    'Bootstrap' => sprintf('%.1f ms', $bootMs),
                    'Queries' => (string) $stats['count'],
                    'SQL time' => sprintf('%.1f ms', $stats['total_ms']),
                    'Peak memory' => sprintf('%.1f MB', $memoryMb),
                    'Payload' => sprintf('%.1f KB', $metrics['payload_kb']),
                ], 'Profiler'));
                $debugbar->addCollector(new ConfigCollector([
                    'Method' => Request::getString('REQUEST_METHOD', 'GET', 'SERVER'),
                    'Query parameters' => $this->sanitizer()->sanitize($_GET),
                    'POST parameters' => $this->sanitizer()->sanitize($_POST),
                    'Cookies' => $this->sanitizer()->sanitizeCookies($_COOKIE),
                    'Headers' => $this->safeHeaders(),
                    'Locale' => (string) ($GLOBALS['xoopsConfig']['language'] ?? ''),
                    'Theme' => (string) ($GLOBALS['xoopsConfig']['theme_set'] ?? ''),
                    'User' => isset($GLOBALS['xoopsUser']) && is_object($GLOBALS['xoopsUser']) && method_exists($GLOBALS['xoopsUser'], 'getVar') ? (string) $GLOBALS['xoopsUser']->getVar('uname') : '(anonymous)',
                ], 'Request details'));
                $debugbar->addCollector(new ConfigCollector([
                    'Flags' => $decodedFlags === [] ? 'none' : implode(', ', $decodedFlags),
                    'Findings' => $verdict['findings'] === [] ? ['none'] : $verdict['findings'],
                    // Exact SQL repeats (true re-executions of the same statement).
                    'N+1 candidates' => $stats['n_plus_one'] === [] ? ['none'] : $stats['n_plus_one'],
                    // Parameterised shapes with multiple distinct variants (id-loop style).
                    'Similar shapes' => ($stats['similar_shapes'] ?? []) === [] ? ['none'] : $stats['similar_shapes'],
                    'Duplicate runtimes' => $duplicateRuntimes === [] ? 'none' : array_keys($duplicateRuntimes),
                ], 'Performance'));
            }
            foreach ($verdict['findings'] as $finding) {
                $logger->log(\Psr\Log\LogLevel::WARNING, $finding, ['channel' => 'messages', 'source' => 'Debugbar performance budget']);
            }
            foreach ($duplicateRuntimes as $runtime => $urls) {
                $logger->log(\Psr\Log\LogLevel::WARNING, sprintf('Multiple copies of runtime "%s": %s', (string) $runtime, implode(' | ', (array) $urls)), ['channel' => 'messages', 'source' => 'Debugbar asset scan']);
            }
            foreach ($explainFindings as $finding) {
                $logger->log(\Psr\Log\LogLevel::WARNING, sprintf('Slow query EXPLAIN (%.1f ms): %s — %s', $finding['ms'], implode('; ', $finding['issues']), self::snippetSql($finding['sql'])), ['channel' => 'messages', 'source' => 'Debugbar slow query EXPLAIN']);
            }
            (new ProfileRepository())->insert(['request_id' => $this->requestId, 'created' => time(), 'url' => $url, 'url_hash' => hash('xxh128', $url), 'dirname' => $module, 'is_fragment' => $this->isFragment(), 'is_admin_side' => str_contains($url, '/admin'), 'total_ms' => $totalMs, 'boot_ms' => $bootMs, 'query_count' => $stats['count'], 'query_ms' => $stats['total_ms'], 'slowest_ms' => $stats['slowest_ms'], 'slowest_fp' => $stats['slowest_fp'], 'n_plus_one' => $stats['worst_repeat'], 'peak_mem_kb' => (int) round(memory_get_peak_usage(true) / 1024), 'payload_bytes' => (int) round($metrics['payload_kb'] * 1024), 'flags' => $verdict['flags']], (int) ($budgets['profiles_retention_days'] ?? 7), (int) ($budgets['profiles_max_rows'] ?? 10000));
            (new FlightRecorder())->record($this->requestId, ['request_id' => $this->requestId, 'url' => $url, 'module' => $module, 'metrics' => $metrics, 'flags' => $decodedFlags, 'findings' => $verdict['findings'], 'n_plus_one' => $stats['n_plus_one'], 'slow' => $stats['slow']], $verdict['flags'] !== 0, 30);
            if (is_object($debugbar) && ! headers_sent()) {
                header('Server-Timing: xoops;dur=' . round($totalMs, 1) . ', sql;dur=' . round((float) $stats['total_ms'], 1), false);
            }
        } catch (\Throwable $e) {
            trigger_error('debugbar profiler failed: ' . $e->getMessage(), E_USER_WARNING);
        }
    }

    /**
     * Run EXPLAIN on the slowest recorded SELECTs (capped at MAX_EXPLAIN) and
     * flag full table scans (type=ALL) and filesorts. Best-effort: any failure
     * on an individual statement is swallowed so profiling never breaks the page.
     *
     * @param array<int, array{sql: string, ms: float}> $slowQueries analyzer 'slow' list
     *
     * @return array<int, array{sql: string, ms: float, issues: string[]}>
     */
    private function explainSlowQueries(array $slowQueries): array
    {
        $findings = [];
        if ([] === $slowQueries || ! isset($GLOBALS['xoopsDB']) || ! is_object($GLOBALS['xoopsDB'])) {
            return $findings;
        }
        /** @var \XoopsMySQLDatabase $db */
        $db = $GLOBALS['xoopsDB'];
        $explained = 0;

        foreach ($slowQueries as $entry) {
            if ($explained >= self::MAX_EXPLAIN) {
                break;
            }
            $sql = trim($entry['sql']);
            if (0 !== stripos($sql, 'SELECT')) {
                continue;   // EXPLAIN only read queries
            }
            $explained++;

            try {
                $result = $db->query('EXPLAIN ' . $sql);
                if (! $db->isResultSet($result) || ! $result instanceof \mysqli_result) {
                    continue;
                }
                $issues = [];
                while (false !== ($row = $db->fetchArray($result))) {
                    if (null === $row) {
                        break;
                    }
                    if (isset($row['type']) && 'ALL' === strtoupper((string) $row['type'])) {
                        $issues[] = 'full table scan (type=ALL) on ' . (string) ($row['table'] ?? '?');
                    }
                    if (isset($row['Extra']) && false !== stripos((string) $row['Extra'], 'filesort')) {
                        $issues[] = 'filesort on ' . (string) ($row['table'] ?? '?');
                    }
                }
                if ([] !== $issues) {
                    $findings[] = ['sql' => $sql, 'ms' => $entry['ms'], 'issues' => $issues];
                }
            } catch (\Throwable $e) {
                // EXPLAIN is best-effort
            }
        }

        return $findings;
    }

    /** Collapse whitespace and truncate SQL for a compact one-line warning. */
    private static function snippetSql(string $sql): string
    {
        $sql = (string) preg_replace('/\s+/', ' ', trim($sql));

        return strlen($sql) > 160 ? substr($sql, 0, 157) . '...' : $sql;
    }

    /** @return array<string, mixed> */
    private function budgets(): array
    {
        $config = [];

        try {
            $config = DebugbarCoreConfig::get();
        } catch (\Throwable) {
        }

        return $config + ['budget_queries' => 30, 'budget_query_ms' => 120, 'budget_boot_ms' => 0, 'budget_total_ms' => 300, 'budget_memory_mb' => 32, 'budget_payload_kb' => 250, 'nplus1_threshold' => 5, 'profiles_retention_days' => 7, 'profiles_max_rows' => 10000];
    }

    private function payloadKb(): float
    {
        return ob_get_level() > 0 ? strlen((string) ob_get_contents()) / 1024 : 0.0;
    }

    private function isFragment(): bool
    {
        return RequestShape::wantsFragment();
    }

    private function path(string $uri): string
    {
        return RequestShape::normalizePath($uri, 500);
    }

    /** @return array<array-key, mixed> */
    private function safeHeaders(): array
    {
        /** @var array<array-key, mixed> $headers */
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        return $this->sanitizer()->sanitizeHeaders($headers);
    }

    private function sanitizer(): DiagnosticSanitizer
    {
        return $this->diagnosticSanitizer ??= new DiagnosticSanitizer();
    }
}
