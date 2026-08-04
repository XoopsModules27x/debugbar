<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\QueryFingerprinter;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

/**
 * The fingerprint is not a transient grouping key: QueryAnalyzer stores it as
 * `slowest_fp`, Profiler writes that into debugbar_profiles, and Analytics
 * reads it back as `sample_fp` in the N+1 leaderboard. Anything the literal
 * scan fails to normalize is therefore persisted for the whole retention
 * window, which is why the redaction-grade cases below belong here too.
 */
final class QueryFingerprinterTest extends TestCase
{
    public function testLiteralsCollapseToPlaceholders(): void
    {
        $out = QueryFingerprinter::fingerprint("SELECT * FROM users WHERE uid = 42 AND uname = 'admin'");

        self::assertSame('SELECT * FROM users WHERE uid = ? AND uname = ?', $out);
    }

    public function testStructurallyIdenticalQueriesShareOneFingerprint(): void
    {
        $a = QueryFingerprinter::fingerprint('SELECT * FROM t WHERE uid = 7');
        $b = QueryFingerprinter::fingerprint('SELECT  *  FROM t   WHERE uid = 4242');

        self::assertSame($a, $b);
    }

    /**
     * A doubled-quote escape is one literal. Treating it as two produced `??`
     * where a plain value produced `?`, so the same statement shape split
     * across two N+1 groups depending on whether a value held an apostrophe.
     */
    public function testADoubledQuoteEscapeDoesNotSplitTheFingerprint(): void
    {
        self::assertSame(
            QueryFingerprinter::fingerprint("SELECT * FROM t WHERE name = 'Smith'"),
            QueryFingerprinter::fingerprint("SELECT * FROM t WHERE name = 'O''Reilly'")
        );
    }

    public function testInListsCollapseToASingleMarker(): void
    {
        $out = QueryFingerprinter::fingerprint('SELECT * FROM t WHERE id IN (1, 2, 3)');

        self::assertSame('SELECT * FROM t WHERE id IN (?+)', $out);
    }

    /**
     * Regression: the two-pass literal scan let an apostrophe inside a
     * double-quoted value swallow the opening quote of a later single-quoted
     * literal, so the session id below was written verbatim into
     * debugbar_profiles.slowest_fp.
     */
    public function testMixedQuoteTypesDoNotLeakSubsequentSecretIntoTheFingerprint(): void
    {
        $out = QueryFingerprinter::fingerprint(
            'SELECT * FROM sess WHERE owner = "O\'Brien" AND sess_id = \'a1b2c3d4e5f6SECRET\''
        );

        self::assertStringNotContainsString('SECRET', $out);
        self::assertSame('SELECT * FROM sess WHERE owner = ? AND sess_id = ?', $out);
    }

    /**
     * A fully normalized fingerprint carries no quote characters at all. The
     * upgrade routine relies on exactly this to find rows written by the buggy
     * scan, so pin it.
     */
    public function testAFullyNormalizedFingerprintCarriesNoQuoteCharacters(): void
    {
        $out = QueryFingerprinter::fingerprint(
            'SELECT * FROM t WHERE a = "it\'s fine" AND b = \'x\' AND c = 3'
        );

        self::assertStringNotContainsString("'", $out);
        self::assertStringNotContainsString('"', $out);
    }

    /**
     * A hex literal previously kept its payload: only the leading 0 matched the
     * numeric rule, so `0x534543524554` normalised to `?x534543524554` and was
     * stored in slowest_fp with the value intact.
     */
    public function testQuoteFreeNumericLiteralsAreNormalised(): void
    {
        self::assertSame(
            'SELECT * FROM t WHERE tok = ? AND b = ? AND n = ?',
            QueryFingerprinter::fingerprint('SELECT * FROM t WHERE tok = 0x534543524554 AND b = 0b1010 AND n = 6.022e23')
        );
    }

    /**
     * An overlong statement must produce a stated failure rather than a partial
     * fingerprint. The previous version of this test used 40 backslashes, far
     * below the real threshold, so it passed against the unguarded code.
     */
    public function testAnOverlongStatementFailsVisiblyRatherThanSilently(): void
    {
        $sql = "SELECT * FROM t WHERE note = '" . str_repeat('a', 16000) . "' AND uid = 7";

        $out = QueryFingerprinter::fingerprint($sql);

        self::assertSame(QueryFingerprinter::FINGERPRINT_FAILED, $out);
        self::assertNotSame('', $out);
    }
}
