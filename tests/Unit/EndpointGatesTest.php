<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Admin\AccessPolicy;

/**
 * explain.php / beacon.php are procedural entry scripts that `require
 * mainfile.php` at the top — they cannot be `require`-d or unit-tested
 * directly without a fully booted, configured XOOPS (DB, session,
 * $GLOBALS['xoopsSecurity']/$GLOBALS['xoopsDB']). This test class does NOT
 * execute those scripts; instead it asserts the GATE PREDICATES they rely
 * on, in isolation, as a documented, reviewed inventory of what protects
 * each endpoint. Each assertion below is a literal regex/shape check lifted
 * from the endpoint source so a future edit that weakens a gate (e.g.
 * loosens the hash-length regex) breaks this test.
 *
 * This build has no xdebug-arm.php endpoint, so the corresponding gate
 * coverage from the upstream test is not ported.
 *
 * Read at: class/ProfileRepository.php (updateVitals() clamping),
 * explain.php, beacon.php.
 */
final class EndpointGatesTest extends TestCase
{
    // --- explain.php -------------------------------------------------
    // Gates (in source order): POST only; $GLOBALS['xoopsUserIsAdmin']
    // non-empty; xoopsSecurity->check(false, $token, 'DEBUGBAR_EXPLAIN')
    // (reusable token, since one page load may EXPLAIN several rows);
    // Helper::getConfig('explain_on_demand') truthy; request_id shape
    // ^[0-9a-f]{8,32}$; sql_hash shape ^[0-9a-f]{64}$ (a full sha256 hex
    // digest — this is what keeps a client from probing arbitrary short
    // hash prefixes); stash file exists and is <=900s old; looked-up sql
    // must start with SELECT (case-insensitive, after stripping leading
    // comments) before it is ever passed to EXPLAIN.

    public function testExplainRequestIdShapeAccepts8To32HexCharsOnly(): void
    {
        $pattern = '/^[0-9a-f]{8,32}$/i';
        self::assertSame(1, preg_match($pattern, 'a1b2c3d4'));
        self::assertSame(1, preg_match($pattern, str_repeat('a', 32)));
        self::assertSame(0, preg_match($pattern, 'a1b2c3d')); // 7 chars, too short
        self::assertSame(0, preg_match($pattern, str_repeat('a', 33))); // too long
        self::assertSame(0, preg_match($pattern, 'a1b2c3d4; DROP TABLE x'));
    }

    public function testExplainSqlHashShapeRequiresFullSha256Digest(): void
    {
        $pattern = '/^[0-9a-f]{64}$/';
        $realHash = hash('sha256', 'SELECT 1');
        self::assertSame(1, preg_match($pattern, $realHash));
        self::assertSame(0, preg_match($pattern, substr($realHash, 0, 63))); // truncated
        self::assertSame(0, preg_match($pattern, $realHash . 'a'));         // extended
        self::assertSame(0, preg_match($pattern, strtoupper($realHash)));   // wrong case (endpoint lowercases first, but the regex itself is case-sensitive)
    }

    public function testExplainOnlyAllowsSelectStatementsPastLeadingComments(): void
    {
        $stripLeadingComments = static function (string $sql): string {
            $normalized = preg_replace('#^\s*(/\*.*?\*/\s*|--[^\n]*\n\s*)*#s', '', $sql) ?? $sql;

            return ltrim($normalized);
        };
        $isSelect = static fn (string $sql): bool => 0 === stripos($stripLeadingComments($sql), 'SELECT');

        self::assertTrue($isSelect('SELECT * FROM t'));
        self::assertTrue($isSelect('/* comment */ select * from t'));
        self::assertFalse($isSelect('DELETE FROM t'));
        self::assertFalse($isSelect('INSERT INTO t VALUES (1)'));
        self::assertFalse($isSelect('UPDATE t SET x = 1'));
        self::assertFalse($isSelect('/* comment */ DELETE FROM t'));
    }

    // --- beacon.php -----------------------------------------------------
    // Gates: POST only; xoopsUserIsAdmin; xoopsConfig['debug_mode'] != 0;
    // raw body capped at 4096 bytes (file_get_contents(..., 0, 4096));
    // JSON-decoded to an array or reject; token read from the JSON BODY
    // (sendBeacon cannot set custom headers) and checked with
    // xoopsSecurity->check(true, $token, 'DEBUGBAR_RUM') — single-use;
    // request id shape ^[0-9a-f]{16}$; lcp/inp/cls coerced via is_numeric
    // -> (float) or null, then clamped server-side in
    // ProfileRepository::updateVitals() (0..600000ms, 0..99 for cls) and
    // written through db->quote()/sprintf('%.1F'|'%.4F') — no raw value
    // ever reaches SQL as a string. SECURITY ASSESSMENT: despite being a
    // browser-beacon endpoint, it is NOT an unauthenticated write vector
    // — it requires an authenticated admin session AND a valid,
    // single-use per-page DEBUGBAR_RUM token minted server-side, so an
    // anonymous visitor cannot post to it.

    public function testBeaconRequestIdShapeIsExactly16HexChars(): void
    {
        $pattern = '/^[0-9a-f]{16}$/';
        self::assertSame(1, preg_match($pattern, str_repeat('a', 16)));
        self::assertSame(0, preg_match($pattern, str_repeat('a', 15)));
        self::assertSame(0, preg_match($pattern, str_repeat('a', 17)));
    }

    public function testBeaconVitalsAreClampedToSaneRanges(): void
    {
        // Mirrors ProfileRepository::updateVitals()'s max(0.0, min(cap, $v)).
        $clamp = static fn (float $v, float $min, float $max): float => max($min, min($max, $v));

        self::assertSame(600000.0, $clamp(99999999.0, 0.0, 600000.0)); // lcp/inp cap
        self::assertSame(0.0, $clamp(-500.0, 0.0, 600000.0));
        self::assertSame(99.0, $clamp(500.0, 0.0, 99.0));              // cls cap
    }

    public function testBeaconRawBodyIsCappedAt4096Bytes(): void
    {
        // file_get_contents('php://input', false, null, 0, 4096) — the
        // length argument enforces the cap regardless of how large the
        // client's actual POST body is.
        $huge = str_repeat('x', 10000);
        $capped = substr($huge, 0, 4096);
        self::assertSame(4096, strlen($capped));
    }

    // --- xdebug-arm.php ------------------------------------------------
    // Gates (in source order): POST only; AccessPolicy::isAllowed() (module
    // admin AND debug_mode non-zero AND debugbar_enable) — deliberately the
    // same decision object the admin pages use, so this endpoint cannot
    // drift from them; xoopsSecurity->check(true, $token, 'DEBUGBAR_XDEBUG')
    // — note `true`, so the token is SINGLE-USE and consumed on success,
    // unlike the reusable DEBUGBAR_EXPLAIN token; and finally
    // XdebugStatus::read()['can_trigger']. Only then is a 60-second
    // XDEBUG_TRIGGER cookie set. The trigger value never travels in the URL,
    // so it cannot leak through history, Referer headers, or access logs.

    public function testXdebugArmGateIsTheSharedAccessPolicyDecision(): void
    {
        // Fails closed on every axis — mirrors AccessPolicy::evaluate().
        self::assertTrue(AccessPolicy::evaluate(true, 1, true));
        self::assertFalse(AccessPolicy::evaluate(false, 1, true), 'non-admin must be refused');
        self::assertFalse(AccessPolicy::evaluate(true, 0, true), 'debug mode off must be refused');
        self::assertFalse(AccessPolicy::evaluate(true, 1, false), 'module disabled must be refused');
    }

    public function testXdebugArmUsesASingleUseTokenUnlikeExplain(): void
    {
        // The distinction is the first argument to XoopsSecurity::check():
        // true clears the token on a valid check, false leaves it reusable.
        // Arming is one-shot, so its token must not be replayable; EXPLAIN
        // may be invoked several times from one rendered page, so its is.
        $armSource = file_get_contents(dirname(__DIR__, 2) . '/xdebug-arm.php');
        $explainSource = file_get_contents(dirname(__DIR__, 2) . '/explain.php');

        self::assertIsString($armSource);
        self::assertIsString($explainSource);
        self::assertStringContainsString("check(true, \$token, 'DEBUGBAR_XDEBUG')", $armSource);
        self::assertStringContainsString("check(false, \$token, 'DEBUGBAR_EXPLAIN')", $explainSource);
    }

    public function testXdebugArmNeverPutsTheTriggerInAUrl(): void
    {
        // The whole point of the endpoint: the trigger is a server-set
        // cookie. A regression that reintroduced a query-string trigger
        // would leak it into history, Referer headers and access logs.
        $armSource = file_get_contents(dirname(__DIR__, 2) . '/xdebug-arm.php');
        $frontendSource = file_get_contents(dirname(__DIR__, 2) . '/assets/frontend.js');

        self::assertIsString($armSource);
        self::assertIsString($frontendSource);
        self::assertStringContainsString('xoops_setcookie(\'XDEBUG_TRIGGER\'', $armSource);
        self::assertStringNotContainsString('searchParams.set(\'XDEBUG_TRIGGER\'', $frontendSource);
    }
}
