<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Admin\AccessPolicy;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

final class AccessPolicyTest extends TestCase
{
    public function testAllowsOnlyWhenAdminDebugAndEnabledAllAgree(): void
    {
        self::assertTrue(AccessPolicy::evaluate(true, 1, true));
    }

    public function testDeniesWhenNotModuleAdmin(): void
    {
        self::assertFalse(AccessPolicy::evaluate(false, 1, true));
    }

    public function testDeniesWhenDebugModeIsZero(): void
    {
        self::assertFalse(AccessPolicy::evaluate(true, 0, true));
    }

    public function testDeniesWhenModuleNotEnabled(): void
    {
        self::assertFalse(AccessPolicy::evaluate(true, 1, false));
    }

    public function testDeniesWhenAllGatesFail(): void
    {
        self::assertFalse(AccessPolicy::evaluate(false, 0, false));
    }

    public function testAnyNonZeroDebugModeCounts(): void
    {
        // debug_mode is stored as an int XOOPS config value; any non-zero
        // value (not just 1) is treated as "debug on".
        self::assertTrue(AccessPolicy::evaluate(true, 2, true));
        self::assertTrue(AccessPolicy::evaluate(true, -1, true));
    }

    public function testIsAllowedNeverThrowsOutsideABootedXoops(): void
    {
        // isAllowed() reads $GLOBALS['xoopsUserIsAdmin'] / $GLOBALS['xoopsConfig']
        // and calls Helper::getInstance(), none of which exist in this
        // minimal harness (no live XOOPS boot). The method is documented as
        // "never-throw": it must degrade to false rather than fatal.
        unset($GLOBALS['xoopsUserIsAdmin'], $GLOBALS['xoopsConfig']);

        self::assertFalse(AccessPolicy::isAllowed());
    }

    /**
     * The preload calls isRuntimeAllowed() at core.include.common.start and
     * common.end. A preload that throws takes the whole site down before an
     * admin can reach the page that would disable the module, so the
     * never-throw contract matters more here than anywhere else.
     */
    public function testIsRuntimeAllowedNeverThrowsOutsideABootedXoops(): void
    {
        unset($GLOBALS['xoopsUserIsAdmin'], $GLOBALS['xoopsConfig']);

        self::assertFalse(AccessPolicy::isRuntimeAllowed());
    }

    /**
     * Fails closed on the admin axis specifically: with no authenticated admin
     * flag there is nothing else that could let a request through, whatever the
     * other two gates say.
     */
    public function testIsRuntimeAllowedRefusesWithoutTheAdminFlag(): void
    {
        $GLOBALS['xoopsConfig'] = ['debug_mode' => 1];
        unset($GLOBALS['xoopsUserIsAdmin']);

        try {
            self::assertFalse(AccessPolicy::isRuntimeAllowed());
        } finally {
            unset($GLOBALS['xoopsConfig']);
        }
    }

    /**
     * The two wrappers must stay distinct. If a refactor ever pointed
     * isRuntimeAllowed() at xoops_isDeveloperRequest(), the toolbar would
     * silently narrow to the webmaster group and to debug_mode 1|2 — the exact
     * mismatch that made the bar render buttons its endpoints refused.
     */
    public function testTheTwoGatesReadAdminRightsFromDifferentSources(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/class/Admin/AccessPolicy.php');
        self::assertIsString($source);

        [, $strict] = explode('public static function isAllowed(): bool', $source, 2);
        [$strict, $runtime] = explode('public static function isRuntimeAllowed(): bool', $strict, 2);

        self::assertStringContainsString('xoops_isDeveloperRequest', $strict);
        self::assertStringNotContainsString('xoops_isDeveloperRequest', $runtime);
        self::assertStringContainsString("\$GLOBALS['xoopsUserIsAdmin']", $runtime);
    }
}
