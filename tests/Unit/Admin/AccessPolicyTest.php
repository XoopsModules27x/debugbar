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
}
