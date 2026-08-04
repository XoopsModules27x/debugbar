<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\SqlRedactor;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

final class SqlRedactorTest extends TestCase
{
    public function testStripsStringAndNumericLiterals(): void
    {
        $sql = "SELECT sess_data, sess_ip FROM xc71_session WHERE sess_id = 'e42b6a7d2911654de09c429b91f10b2f'";
        $out = SqlRedactor::redact($sql);
        self::assertStringNotContainsString('e42b6a7d2911654de09c429b91f10b2f', $out);
        self::assertStringContainsString("sess_id = ''", $out);
        self::assertStringStartsWith('SELECT', $out);
    }

    public function testNumericLiteralsBecomeZeroButIdentifiersSurvive(): void
    {
        $out = SqlRedactor::redact('SELECT col1, tbl_2.x FROM utf8tbl WHERE uid = 42 AND flag = 1 LIMIT 5, 10');
        self::assertStringContainsString('col1', $out);       // identifier digit preserved
        self::assertStringContainsString('tbl_2.x', $out);
        self::assertStringContainsString('utf8tbl', $out);
        self::assertStringNotContainsString('42', $out);
        self::assertStringContainsString('uid = 0', $out);
    }

    public function testInListValuesRedactedButRunnable(): void
    {
        $out = SqlRedactor::redact("SELECT * FROM t WHERE id IN (1, 2, 3) AND name IN ('a', 'b')");
        self::assertStringNotContainsString("'a'", $out);
        self::assertStringContainsString('IN (0, 0, 0)', $out);
        self::assertStringContainsString("IN ('', '')", $out);
    }

    public function testNoSecretSurvivesInDoubleQuotedLiteral(): void
    {
        $out = SqlRedactor::redact('SELECT * FROM t WHERE token = "s3cr3t-value"');
        self::assertStringNotContainsString('s3cr3t-value', $out);
    }

    /**
     * Regression: scanning the two quote styles in separate passes let an
     * apostrophe inside a double-quoted value swallow the opening quote of a
     * LATER single-quoted literal, leaving that literal's value in the output
     * that gets written to the on-disk EXPLAIN stash.
     */
    public function testMixedQuoteTypesDoNotLeakSubsequentSecret(): void
    {
        $out = SqlRedactor::redact('SELECT * FROM t WHERE name = "O\'Brien" AND token = \'SECRET123\'');

        self::assertStringNotContainsString('SECRET123', $out);
        self::assertStringNotContainsString('Brien', $out);
        self::assertSame("SELECT * FROM t WHERE name = '' AND token = ''", $out);
    }

    public function testApostropheInDoubleQuotedValueDoesNotLeakLaterPassword(): void
    {
        $out = SqlRedactor::redact('SELECT uid FROM users WHERE note = "it\'s fine" AND pass = \'hunter2\'');

        self::assertStringNotContainsString('hunter2', $out);
        self::assertStringNotContainsString('fine', $out);
    }

    /**
     * The two-pass scan this replaced could only be caught by a statement that
     * MIXES quote styles. Cases with one style alone pass under both
     * implementations, so they document behaviour without pinning the fix —
     * every case below therefore mixes.
     */
    #[DataProvider('mixedQuoteStatements')]
    public function testMixedQuoteStatementsNeverLeakTheFollowingValue(string $sql, string $secret): void
    {
        self::assertStringNotContainsString($secret, SqlRedactor::redact($sql));
    }

    /**
     * Each case is verified to FAIL against the previous two-pass
     * implementation — an apostrophe inside a double-quoted value, positioned
     * before the value that must not survive. Merely putting both quote styles
     * in one statement is not enough and yields a test that passes either way.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mixedQuoteStatements(): array
    {
        return [
            'double-quoted value holding an apostrophe' => [
                'SELECT * FROM t WHERE name = "O\'Brien" AND token = \'SECRET123\'',
                'SECRET123',
            ],
            'doubled-quote escape after an apostrophe in a double-quoted value' => [
                'SELECT * FROM t WHERE note = "it\'s fine" AND a = \'O\'\'Brien\' AND token = \'SECRET456\'',
                'SECRET456',
            ],
            'target is the last of three literals' => [
                'UPDATE t SET label = "o\'clock", note = \'x\', tok = \'SECRET321\' WHERE id = 1',
                'SECRET321',
            ],
        ];
    }

    /**
     * Characterisation, NOT a regression test: a backslash-escaped quote inside
     * a single-quoted literal was handled correctly by the old two-pass code
     * too, so this case cannot pin the fix. Kept because the behaviour is worth
     * stating, labelled so nobody mistakes it for coverage of the bug.
     */
    public function testBackslashEscapedQuoteIsTreatedAsPartOfTheLiteral(): void
    {
        $out = SqlRedactor::redact('SELECT * FROM t WHERE a = \'O\\\'Brien\' AND token = \'SECRET789\'');

        self::assertStringNotContainsString('SECRET789', $out);
    }

    /**
     * A bare \d+ rule consumed only the leading 0 of a hex literal and left the
     * payload standing, so a hex-encoded value passed through untouched.
     */
    #[DataProvider('quoteFreeLiterals')]
    public function testQuoteFreeNumericLiteralsAreNormalised(string $sql, string $payload): void
    {
        self::assertStringNotContainsString($payload, SqlRedactor::redact($sql));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function quoteFreeLiterals(): array
    {
        return [
            'hexadecimal' => ['SELECT * FROM t WHERE tok = 0x534543524554', '534543524554'],
            'binary' => ['SELECT * FROM t WHERE b = 0b101010111100', '101010111100'],
            'scientific' => ['SELECT * FROM t WHERE n = 6.022e23', 'e23'],
            // MySQL accepts a decimal written without a leading zero. The
            // lookbehind that protects qualified names such as tbl_2.x was also
            // refusing this form, so the value survived untouched.
            'leading-dot decimal' => ['SELECT * FROM t WHERE ratio = .534543524554', '534543524554'],
            'leading-dot with exponent' => ['SELECT * FROM t WHERE r = .5e10', '5e10'],
        ];
    }

    /**
     * The lookbehind must keep doing its original job: a qualified name is not
     * a literal and must survive.
     */
    public function testQualifiedIdentifiersAreNotMistakenForDecimals(): void
    {
        $out = SqlRedactor::redact('SELECT tbl_2.x, a.b FROM t WHERE id = 5');

        self::assertStringContainsString('tbl_2.x', $out);
        self::assertStringContainsString('a.b', $out);
        self::assertStringContainsString('id = 0', $out);
    }

    /**
     * PCRE's JIT hits its stack limit on a single very long quoted literal and
     * preg_replace() returns null; a (string) cast turned that into an empty
     * statement with nothing to say why. The threshold is real — the previous
     * version of this test used 40 backslashes, far below it, and passed
     * against the unguarded implementation.
     */
    public function testAnOverlongStatementFailsVisiblyRatherThanSilently(): void
    {
        $sql = "SELECT * FROM t WHERE note = '" . str_repeat('a', 16000) . "' AND tok = 'SECRET'";

        $out = SqlRedactor::redact($sql);

        self::assertSame(SqlRedactor::REDACTION_FAILED, $out);
        self::assertStringNotContainsString('SECRET', $out);
        self::assertNotSame('', $out, 'failure must be reported, not an empty statement');
    }

    /**
     * Exactly at the limit, and exactly one byte over. An earlier version used
     * 200 characters against an 8000-byte limit and so tested nothing about the
     * boundary it was named for.
     */
    public function testTheLengthLimitIsInclusive(): void
    {
        $atLimit = str_pad("SELECT 'x' /*", SqlRedactor::MAX_INPUT_LENGTH - 2, 'a') . '*/';
        self::assertSame(SqlRedactor::MAX_INPUT_LENGTH, strlen($atLimit));
        self::assertNotSame(SqlRedactor::REDACTION_FAILED, SqlRedactor::redact($atLimit));

        $overLimit = $atLimit . 'a';
        self::assertSame(SqlRedactor::MAX_INPUT_LENGTH + 1, strlen($overLimit));
        self::assertSame(SqlRedactor::REDACTION_FAILED, SqlRedactor::redact($overLimit));
    }

    /**
     * A regex cannot model comments, backtick identifiers, NO_BACKSLASH_ESCAPES
     * or unterminated literals — in each, a later literal could survive. The
     * scan checks its own work instead: once literals are removed, no quote
     * character may remain, so these refuse rather than emit a partial result.
     *
     * @param string $sql    a statement the pattern cannot account for
     * @param string $secret the value that must never appear in any output
     */
    #[DataProvider('ambiguousStatements')]
    public function testStatementsTheScanCannotAccountForAreRefused(string $sql, string $secret): void
    {
        $out = SqlRedactor::redact($sql);

        self::assertSame(SqlRedactor::REDACTION_FAILED, $out);
        self::assertStringNotContainsString($secret, $out);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function ambiguousStatements(): array
    {
        return [
            'line comment containing an apostrophe' => [
                "-- user's filter\nSELECT * FROM t WHERE password = 's3cr3t'",
                's3cr3t',
            ],
            'block comment containing an apostrophe' => [
                "/* user's filter */ SELECT * FROM t WHERE password = 's3cr3t'",
                's3cr3t',
            ],
            'backtick identifier containing an apostrophe' => [
                "SELECT `O'Brien` FROM t WHERE password = 's3cr3t'",
                's3cr3t',
            ],
            'trailing backslash under NO_BACKSLASH_ESCAPES' => [
                "SELECT 'public\\', 's3cr3t'",
                's3cr3t',
            ],
            'unterminated literal from a failed query' => [
                "SELECT * FROM t WHERE a = 's3cr3t",
                's3cr3t',
            ],
        ];
    }

    /**
     * The refusal must be narrow. Backtick identifiers, doubled quotes, IN
     * lists and the quote-free numeric forms are all ordinary and must still be
     * redacted rather than refused.
     */
    #[DataProvider('ordinaryStatements')]
    public function testOrdinaryStatementsAreRedactedNotRefused(string $sql): void
    {
        self::assertNotSame(SqlRedactor::REDACTION_FAILED, SqlRedactor::redact($sql));
    }

    /** @return array<string, array{0: string}> */
    public static function ordinaryStatements(): array
    {
        return [
            'plain literals' => ["SELECT * FROM users WHERE uname = 'admin' AND uid = 42"],
            'backtick identifiers' => ['SELECT `uname` FROM `xc71_users` WHERE `uid` = 7'],
            'doubled quote and a double-quoted value' => ['SELECT * FROM t WHERE a = \'O\'\'Reilly\' AND b = "x"'],
            'IN list' => ['SELECT * FROM t WHERE id IN (1, 2, 3)'],
            'hex and exponent' => ["UPDATE t SET a = 'x' WHERE b = 0x1F AND c = 6.022e23"],
        ];
    }

    /**
     * MySQL's doubled-quote escape is ONE literal. Scanning it as two adjacent
     * literals made `'O''Reilly'` and `'Smith'` normalise differently, so two
     * structurally identical statements landed in different N+1 groups.
     */
    public function testADoubledQuoteEscapeIsOneLiteralNotTwo(): void
    {
        self::assertSame(
            "SELECT * FROM t WHERE a = '' AND b = ''",
            SqlRedactor::redact("SELECT * FROM t WHERE a = 'O''Reilly' AND b = 'Smith'")
        );
    }
}
