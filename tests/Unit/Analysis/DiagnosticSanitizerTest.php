<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\DiagnosticSanitizer;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

final class DiagnosticSanitizerTest extends TestCase
{
    public function testRedactsSensitiveKeysByName(): void
    {
        $out = (new DiagnosticSanitizer())->sanitize([
            'password' => 'hunter2',
            'api_key' => 'abc123',
            'csrf' => 'xyz',
            'ok' => 'plain-value',
        ]);

        self::assertSame('[redacted]', $out['password']);
        self::assertSame('[redacted]', $out['api_key']);
        self::assertSame('[redacted]', $out['csrf']);
        self::assertSame('plain-value', $out['ok']);
    }

    public function testCookiesAreAlwaysFullyRedacted(): void
    {
        $out = (new DiagnosticSanitizer())->sanitizeCookies([
            'PHPSESSID' => 'abcdef123456',
            'other_pref' => 'dark-mode',
        ]);

        self::assertSame('[redacted]', $out['PHPSESSID']);
        self::assertSame('[redacted]', $out['other_pref']);
    }

    public function testNestedArraysAreSanitizedRecursivelyAndBoundedByDepth(): void
    {
        $out = (new DiagnosticSanitizer())->sanitize([
            'level1' => ['password' => 'secret', 'level2' => ['level3' => ['level4' => ['level5' => 'deep']]]],
        ]);

        self::assertSame('[redacted]', $out['level1']['password']);
        // depth is bounded — somewhere below MAX_DEPTH it collapses
        $flat = json_encode($out);
        self::assertIsString($flat);
        self::assertStringContainsString('maximum depth reached', $flat);
    }

    public function testLongStringsAreTruncated(): void
    {
        $long = str_repeat('x', 5000);
        $out = (new DiagnosticSanitizer())->sanitize(['field' => $long]);

        self::assertLessThan(5000, strlen((string) $out['field']));
        self::assertStringEndsWith('...', (string) $out['field']);
    }

    public function testManyEntriesAreTruncatedWithCountMarker(): void
    {
        $values = [];
        for ($i = 0; $i < 150; ++$i) {
            $values['key' . $i] = 'v';
        }
        $out = (new DiagnosticSanitizer())->sanitize($values);

        self::assertArrayHasKey('[truncated]', $out);
        self::assertCount(101, $out); // 100 entries + the truncated marker
    }

    public function testUrlUserinfoIsRedacted(): void
    {
        $sanitizer = new DiagnosticSanitizer();
        $out = $sanitizer->sanitizeUrl('https://user:hunter2@example.com/path?a=1');

        self::assertStringNotContainsString('hunter2', $out);
        self::assertStringContainsString('[redacted]@example.com', $out);
    }

    public function testUrlSensitiveQueryParamsAreRedactedButOthersPreserved(): void
    {
        $sanitizer = new DiagnosticSanitizer();
        $out = $sanitizer->sanitizeUrl('https://example.com/login?token=abc123&page=2');

        self::assertStringNotContainsString('abc123', $out);
        self::assertStringContainsString('page=2', $out);
    }

    public function testUrlFragmentIsPreservedAfterQuerySanitization(): void
    {
        $sanitizer = new DiagnosticSanitizer();
        $out = $sanitizer->sanitizeUrl('https://example.com/page?token=abc#section');

        self::assertStringEndsWith('#section', $out);
        self::assertStringNotContainsString('abc', $out);
    }

    public function testSanitizeHeadersDelegatesToSanitize(): void
    {
        $out = (new DiagnosticSanitizer())->sanitizeHeaders(['Authorization' => 'Bearer xyz', 'X-Custom' => 'value']);

        self::assertSame('[redacted]', $out['Authorization']);
        self::assertSame('value', $out['X-Custom']);
    }

    public function testUrlKeyedValuesAreSanitizedAsUrls(): void
    {
        $out = (new DiagnosticSanitizer())->sanitize(['url' => 'https://user:pw@example.com/x?token=t']);

        self::assertStringNotContainsString('pw', $out['url']);
        self::assertStringNotContainsString('token=t', $out['url']);
    }

    public function testNonScalarNonArrayValuesBecomeDebugType(): void
    {
        $out = (new DiagnosticSanitizer())->sanitize(['obj' => new \stdClass()]);

        self::assertSame('stdClass', $out['obj']);
    }
}
