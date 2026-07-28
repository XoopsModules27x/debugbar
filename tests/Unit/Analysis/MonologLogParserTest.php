<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\MonologLogParser;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

final class MonologLogParserTest extends TestCase
{
    public function testParsesWellFormedMonologLine(): void
    {
        $line = '[2026-07-22T10:00:00.000000+00:00] xoops.ERROR: Something broke {"errno":8,"errfile":"/a/b.php","errline":42} {"memory":123}';
        $out = (new MonologLogParser())->parse($line);

        self::assertCount(1, $out);
        self::assertTrue($out[0]['parsed']);
        self::assertSame('xoops', $out[0]['channel']);
        self::assertSame('error', $out[0]['level']);
        self::assertSame('Something broke', $out[0]['message']);
        self::assertSame(8, $out[0]['context']['errno']);
        self::assertSame(['memory' => 123], $out[0]['extra']);
    }

    public function testFallsBackToRawForUnparsableLines(): void
    {
        $line = 'not a monolog line at all';
        $out = (new MonologLogParser())->parse($line);

        self::assertCount(1, $out);
        self::assertFalse($out[0]['parsed']);
        self::assertSame($line, $out[0]['raw']);
        self::assertSame($line, $out[0]['message']);
    }

    public function testSkipsBlankLinesAndParsesMultipleLines(): void
    {
        $contents = "[2026-07-22T10:00:00+00:00] app.INFO: one {} {}\n\n[2026-07-22T10:00:01+00:00] app.WARNING: two {} {}\n";
        $out = (new MonologLogParser())->parse($contents);

        self::assertCount(2, $out);
        self::assertSame('one', $out[0]['message']);
        self::assertSame('two', $out[1]['message']);
    }

    public function testFallsBackWhenContextIsNotValidJson(): void
    {
        $line = '[2026-07-22T10:00:00+00:00] app.INFO: msg {not json} {}';
        $out = (new MonologLogParser())->parse($line);

        self::assertFalse($out[0]['parsed']);
    }
}
