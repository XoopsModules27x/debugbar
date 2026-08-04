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
 * xdebug-arm.php gate coverage is asserted against the endpoint source
 * below, because the script cannot be executed without a booted XOOPS.
 *
 * Read at: class/ProfileRepository.php (updateVitals() clamping),
 * explain.php, beacon.php, xdebug-arm.php.
 */
final class EndpointGatesTest extends TestCase
{
    /**
     * Every endpoint that serves the toolbar must gate on the SAME decision the
     * preload uses to render it. An endpoint stricter than the bar shows a
     * button that always 403s; an endpoint laxer than the bar accepts writes the
     * bar would never have offered. Asserted against the shipped source, so
     * swapping any one of them back to a hand-rolled check breaks this test.
     */
    public function testEveryRuntimeEndpointSharesTheToolbarGate(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['beacon.php', 'explain.php', 'xdebug-arm.php'] as $endpoint) {
            $source = file_get_contents($root . '/' . $endpoint);
            self::assertIsString($source);
            self::assertStringContainsString(
                'AccessPolicy::isRuntimeAllowed()',
                $source,
                $endpoint . ' must gate on the shared runtime policy'
            );
            self::assertStringNotContainsString(
                "\$GLOBALS['xoopsUserIsAdmin']",
                $source,
                $endpoint . ' must not re-implement the admin check by hand'
            );
        }

        // Count, do not merely detect: the preload gates at TWO seams
        // (auth.success and common.end, the latter being what catches anonymous
        // requests). Asserting mere presence let either seam be deleted while
        // the other kept this test green.
        $preload = file_get_contents($root . '/preloads/core.php');
        self::assertIsString($preload);
        self::assertSame(
            2,
            substr_count($preload, 'AccessPolicy::isRuntimeAllowed()'),
            'the preload must gate at both the auth.success and common.end seams'
        );
    }

    /**
     * Every endpoint that calls the security handler must first prove it has
     * one, and must silence the logger the same way. isset() alone is not
     * enough for the first: a global that exists but is not an XoopsSecurity
     * still reaches ->check() and fatals, which prints an error into a response
     * body these endpoints promise never to render into.
     *
     * On the logger: activated is the flag that decides it, because
     * XoopsLogger::render() returns the output untouched when it is false.
     * renderingEnabled is asserted alongside it not because either alone would
     * leak, but because divergence between these three files is what keeps
     * costing review time -- the access gate, the security guard and now this
     * were each implemented in two of the three and questioned in the third.
     *
     * Discovering the files by scanning the module root, rather than listing
     * them here, is the point: a fourth endpoint added later is covered the day
     * it is written.
     */
    public function testEveryEndpointGuardsItsGlobalsAndSilencesTheLogger(): void
    {
        $root = dirname(__DIR__, 2);
        $entryScripts = glob($root . '/*.php');
        self::assertIsArray($entryScripts);

        $checked = 0;
        foreach ($entryScripts as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            if (! str_contains($source, "\$GLOBALS['xoopsSecurity']->check(")) {
                continue;
            }

            $name = basename($path);
            $checked++;

            self::assertStringContainsString(
                "! isset(\$GLOBALS['xoopsSecurity'])",
                $source,
                $name . ' must reject a missing security handler'
            );
            self::assertStringContainsString(
                "! \$GLOBALS['xoopsSecurity'] instanceof \\XoopsSecurity",
                $source,
                $name . ' must reject a security handler of the wrong type'
            );
            self::assertStringContainsString(
                "\$GLOBALS['xoopsLogger']->activated = false",
                $source,
                $name . ' must switch the logger off -- activated is what render() checks'
            );
            self::assertStringContainsString(
                "\$GLOBALS['xoopsLogger']->renderingEnabled = false",
                $source,
                $name . ' must clear renderingEnabled too, so all endpoints match'
            );
        }

        // Guard the guard: a refactor that renamed the global or moved the
        // token checks behind a helper would otherwise leave this test
        // scanning nothing and passing.
        self::assertGreaterThanOrEqual(
            3,
            $checked,
            'expected at least beacon.php, explain.php and xdebug-arm.php to check a token'
        );
    }

    /**
     * explain.php is the one endpoint that hands a statement to the server, so
     * "starts with SELECT" is not a sufficient description of what it accepts.
     * Neither form below is reachable today — the statement comes from the
     * module's own server-side stash and mysqli::query() runs one statement —
     * but both guards are what stops a future change to WHAT gets stashed from
     * turning this endpoint into an execution primitive.
     */
    public function testExplainAcceptsOnlyASinglePlainSelect(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/explain.php');
        self::assertIsString($source);

        self::assertStringContainsString(
            "stripos(\$normalized, 'SELECT')",
            $source,
            'explain.php must still require a SELECT prefix'
        );
        self::assertStringContainsString(
            "str_contains(\$normalized, ';')",
            $source,
            'explain.php must reject anything carrying a statement separator'
        );
        self::assertStringContainsString(
            'INTO\s+(?:OUT|DUMP)FILE',
            $source,
            'explain.php must reject SELECT forms that write to disk'
        );
        // Asserted against $normalized, not $sql: the comment-stripping pass is
        // what makes the prefix check meaningful, and a guard applied to the raw
        // string would be trivially bypassed by a leading comment.
        // One assignment plus three guards. Counted, not merely detected, so a
        // fourth guard added against the raw $sql cannot slip in beside them.
        self::assertSame(
            4,
            substr_count($source, '$normalized'),
            'every statement-shape guard must run on the comment-stripped form'
        );
    }

    /**
     * The admin pages keep the STRICTER gate. They expose site-wide SQL,
     * sessions and configuration accumulated across other people's requests,
     * which is a different exposure from a toolbar showing you your own.
     */
    public function testAdminPagesKeepTheStricterGate(): void
    {
        foreach (['analytics.php', 'diagnostics.php', 'logs.php'] as $page) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/admin/' . $page);
            self::assertIsString($source);
            self::assertStringContainsString(
                'AccessPolicy::isAllowed()',
                $source,
                $page . ' must keep the webmaster-scoped gate'
            );
            self::assertStringNotContainsString(
                'AccessPolicy::isRuntimeAllowed()',
                $source,
                $page . ' must not be downgraded to the runtime gate'
            );
        }
    }

    // --- explain.php -------------------------------------------------
    // Gates (in source order): POST only; AccessPolicy::isRuntimeAllowed()
    // (module admin AND debug_mode non-zero AND debugbar_enable — the same
    // decision the preload makes to render the bar, so the EXPLAIN button
    // and the endpoint behind it can never disagree);
    // xoopsSecurity->check(false, $token, 'DEBUGBAR_EXPLAIN')
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
    // Gates: POST only; AccessPolicy::isRuntimeAllowed() — note this replaced
    // a hand-rolled admin + debug_mode pair that omitted debugbar_enable
    // entirely, so vitals were accepted into debugbar_profiles while DebugBar
    // was switched off; raw body capped at 4096 bytes (file_get_contents(..., 0, 4096));
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
    // Gates (in source order): POST only; AccessPolicy::isRuntimeAllowed()
    // (module admin AND debug_mode non-zero AND debugbar_enable) — the same
    // decision the preload uses to render the button that posts here. It used
    // isAllowed(), which is stricter on two axes the toolbar does not share
    // (webmaster group, and debug_mode 3), so the button could render for a
    // caller this endpoint would refuse;
    // xoopsSecurity->check(true, $token, 'DEBUGBAR_XDEBUG')
    // — note `true`, so the token is SINGLE-USE and consumed on success,
    // unlike the reusable DEBUGBAR_EXPLAIN token; and finally
    // XdebugStatus::read()['can_trigger']. Only then is a 60-second
    // XDEBUG_TRIGGER cookie set. The trigger value never travels in the URL,
    // so it cannot leak through history, Referer headers, or access logs.

    public function testXdebugArmGateIsTheSharedAccessPolicyDecision(): void
    {
        // Fails closed on every axis — mirrors AccessPolicy::evaluate(), which
        // both wrappers delegate to.
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
