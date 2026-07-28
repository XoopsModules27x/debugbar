<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

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
}
