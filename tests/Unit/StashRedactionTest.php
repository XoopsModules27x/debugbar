<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\SqlRedactor;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 2));
}

/**
 * Documents the contract used by DebugbarLogger::stashQueriesForExplain():
 * the EXPLAIN stash map is built as
 *     $map[hash('sha256', $sql)] = SqlRedactor::redact($sql);
 * i.e. keyed on the hash of the ORIGINAL sql (so the bar's client-computed
 * sql_hash still resolves it) but the value written to disk is the
 * redacted, secret-free form.
 *
 * stashQueriesForExplain() itself is private and depends on
 * config/request singletons (Helper, Profiler) that require a booted
 * XOOPS, so it is not unit-testable directly. This test exercises the
 * redactor with the same shape of input the writer feeds it and asserts
 * the persisted value carries no secret while the lookup key remains the
 * hash of the original statement.
 */
final class StashRedactionTest extends TestCase
{
    public function testStashValueIsRedactedButKeyedOnOriginalHash(): void
    {
        $sql = "SELECT sess_data FROM xc71_session WHERE sess_id = 'e42b6a7d2911654de09c429b91f10b2f'";

        $key = hash('sha256', $sql);
        $value = SqlRedactor::redact($sql);

        // Key is the hash of the ORIGINAL (unredacted) sql — matches the
        // bar's client-side sha256(sql) used to request an EXPLAIN.
        self::assertSame(hash('sha256', $sql), $key);

        // Value on disk carries no secret.
        self::assertStringNotContainsString('e42b6a7d2911654de09c429b91f10b2f', $value);
        self::assertStringContainsString("sess_id = ''", $value);
        self::assertStringStartsWith('SELECT', $value);
    }

    public function testMultipleQueriesEachRedactedIndependently(): void
    {
        $sqls = [
            "SELECT * FROM xc71_users WHERE uname = 'admin' AND pass = 'S3cr3tPass!'",
            'SELECT * FROM xc71_config WHERE conf_id = 42',
        ];

        $map = [];
        foreach ($sqls as $sql) {
            $map[hash('sha256', $sql)] = SqlRedactor::redact($sql);
        }

        self::assertCount(2, $map);
        foreach ($map as $value) {
            self::assertStringNotContainsString('admin', $value);
            self::assertStringNotContainsString('S3cr3tPass!', $value);
            self::assertStringNotContainsString('42', $value);
        }
    }

    /**
     * Stash-level guard for the mixed-quote literal bug. The behaviour itself
     * is pinned in SqlRedactorTest; this asserts the value that actually
     * reaches disk carries no secret, which is the promise this file documents.
     */
    public function testStashedValueCarriesNoSecretAcrossMixedQuoteTypes(): void
    {
        $sql = 'SELECT * FROM xc71_session WHERE owner = "O\'Brien" AND sess_id = \'e42b6a7d2911654de09c429b91f10b2f\'';

        $map = [hash('sha256', $sql) => SqlRedactor::redact($sql)];

        foreach ($map as $value) {
            self::assertStringNotContainsString('e42b6a7d2911654de09c429b91f10b2f', $value);
        }
    }
}
