<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\QueryAnalyzer;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

/**
 * Call-site attribution on query findings.
 *
 * Knowing that forty identical statements ran is only half an answer; the half
 * that matters is which file issued them. These tests pin that the origins
 * recorded per query survive grouping, are ranked by frequency, and are bounded.
 */
final class QueryAnalyzerOriginsTest extends TestCase
{
    /** @return list<array{sql: string, ms: float, error: bool, origin: string}> */
    private function queries(string $sql, int $times, string $origin): array
    {
        $rows = [];
        for ($i = 0; $i < $times; $i++) {
            $rows[] = ['sql' => $sql, 'ms' => 1.0, 'error' => false, 'origin' => $origin];
        }

        return $rows;
    }

    public function testDuplicatesCarryTheirCallSite(): void
    {
        $result = QueryAnalyzer::analyze(
            $this->queries('SELECT 1 FROM t WHERE id = 5', 4, '/modules/foo/blocks/bar.php:52'),
            0.05,
            2
        );

        self::assertNotSame([], $result['n_plus_one']);
        $finding = $result['n_plus_one'][0];
        self::assertSame(4, $finding['count']);
        self::assertSame(['/modules/foo/blocks/bar.php:52 (x4)'], $finding['origins']);
    }

    public function testOriginsAreRankedByFrequencyAndCappedAtTwo(): void
    {
        $rows = array_merge(
            $this->queries('SELECT 1 FROM t WHERE id = 5', 1, '/a.php:1'),
            $this->queries('SELECT 1 FROM t WHERE id = 5', 5, '/b.php:2'),
            $this->queries('SELECT 1 FROM t WHERE id = 5', 3, '/c.php:3')
        );

        $result = QueryAnalyzer::analyze($rows, 0.05, 2);
        $origins = $result['n_plus_one'][0]['origins'];

        self::assertCount(2, $origins, 'a third call site would cost more readability than it buys');
        self::assertSame('/b.php:2 (x5)', $origins[0], 'most frequent first');
        self::assertSame('/c.php:3 (x3)', $origins[1]);
    }

    public function testOneOriginIsAggregatedAcrossTheVariantsOfAShape(): void
    {
        $rows = array_merge(
            $this->queries('SELECT a FROM t WHERE id = 1', 1, '/only.php:9'),
            $this->queries('SELECT a FROM t WHERE id = 2', 1, '/only.php:9'),
            $this->queries('SELECT a FROM t WHERE id = 3', 1, '/only.php:9')
        );

        // Three distinct statements of one shape — the id-loop pattern.
        $result = QueryAnalyzer::analyze($rows, 0.05, 2);

        self::assertNotSame([], $result['similar_shapes']);
        self::assertSame(['/only.php:9 (x3)'], $result['similar_shapes'][0]['origins']);
    }

    /**
     * topOrigins() decorates with a count only when an origin was hit more than
     * once. The undecorated single-hit form had no coverage at all, despite the
     * test above having been named for it.
     */
    public function testASingleHitOriginIsNotDecoratedWithACount(): void
    {
        $rows = array_merge(
            $this->queries('SELECT a FROM t WHERE id = 1', 1, '/first.php:1'),
            $this->queries('SELECT a FROM t WHERE id = 2', 1, '/second.php:2'),
            $this->queries('SELECT a FROM t WHERE id = 3', 1, '/third.php:3')
        );

        // One shape, three variants, each from a different call site hit once.
        $result = QueryAnalyzer::analyze($rows, 0.05, 2);

        self::assertNotSame([], $result['similar_shapes']);
        foreach ($result['similar_shapes'][0]['origins'] as $origin) {
            self::assertStringNotContainsString('(x', $origin);
        }
    }

    /**
     * Every refused fingerprint returns the same constant. Counting that as a
     * shape key merged unrelated statements into one group and reported it as
     * an N+1 — three different statements carrying a quote-bearing comment
     * became a single finding with six executions across three variants.
     */
    public function testUnfingerprintableStatementsDoNotMergeIntoOneShape(): void
    {
        $rows = [];
        foreach ([
            "SELECT a FROM alpha /* user's */ WHERE id = 1",
            "SELECT b FROM beta /* user's */ WHERE id = 2",
            "UPDATE gamma SET x = 3 /* user's */",
        ] as $sql) {
            $rows = array_merge($rows, $this->queries($sql, 2, '/x.php:1'));
        }

        $result = QueryAnalyzer::analyze($rows, 0.05, 2);

        self::assertSame([], $result['similar_shapes'], 'refusals must not be grouped as a shape');
    }

    /**
     * Profiler::explainSlowQueries() reads these rebuilt rows rather than the
     * logger's, so dropping the flag here left the automatic EXPLAIN path
     * running against statements cut at QUERY_SQL_CAP.
     */
    public function testTheTruncationFlagSurvivesIntoTheSlowQueryList(): void
    {
        $rows = [
            ['sql' => 'SELECT ' . str_repeat('x', 40), 'ms' => 100.0, 'error' => false, 'origin' => '', 'sql_truncated' => true],
            ['sql' => 'SELECT y FROM t', 'ms' => 100.0, 'error' => false, 'origin' => ''],
        ];

        $result = QueryAnalyzer::analyze($rows, 0.05, 2);

        self::assertCount(2, $result['slow']);
        self::assertTrue($result['slow'][0]['sql_truncated']);
        self::assertFalse($result['slow'][1]['sql_truncated']);
    }

    /**
     * exactKey() returns a fixed-size digest rather than the whole statement,
     * while still collapsing whitespace and case the way the raw form did.
     */
    public function testExactKeyIsAFixedSizeDigestThatStillNormalises(): void
    {
        $a = QueryAnalyzer::exactKey('SELECT  A  FROM t WHERE id = 1');
        $b = QueryAnalyzer::exactKey('select a from t where id=1');
        $c = QueryAnalyzer::exactKey('SELECT a FROM t WHERE id = 2');

        self::assertSame($a, $b, 'whitespace and case must still normalise');
        self::assertNotSame($a, $c);
        self::assertSame(32, strlen($a), 'must be a fixed-size digest, not the statement');
    }

    public function testQueriesWithoutAnOriginStillAnalyseCleanly(): void
    {
        // Origin is absent for anything recorded past the query-log cap, and for
        // callers that predate this field. That must not produce a stray entry.
        $rows = [
            ['sql' => 'SELECT 1 FROM t WHERE id = 5', 'ms' => 1.0, 'error' => false],
            ['sql' => 'SELECT 1 FROM t WHERE id = 5', 'ms' => 1.0, 'error' => false, 'origin' => ''],
        ];

        $result = QueryAnalyzer::analyze($rows, 0.05, 2);

        self::assertSame(2, $result['n_plus_one'][0]['count']);
        self::assertSame([], $result['n_plus_one'][0]['origins']);
    }
}
