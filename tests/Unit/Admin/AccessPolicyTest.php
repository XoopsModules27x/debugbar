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

    // ------------------------------------------------------------------
    // Second activation source: a 'debugbar' block in xoops_data/data/debug.php
    // ------------------------------------------------------------------

    /**
     * Either source switches debugging on. This is what lets a developer enable
     * the bar on a local checkout without carrying a debug flag in the database.
     */
    public function testEitherActivationSourceIsSufficient(): void
    {
        self::assertTrue(AccessPolicy::evaluate(true, 2, true, false), 'database debug_mode alone');
        self::assertTrue(AccessPolicy::evaluate(true, 0, true, true), 'debug.php alone');
        self::assertTrue(AccessPolicy::evaluate(true, 2, true, true), 'both together');
        self::assertFalse(AccessPolicy::evaluate(true, 0, true, false), 'neither');
    }

    /**
     * The file relaxes the debug_mode term and NOTHING else. The administrator
     * requirement is structural here on purpose: it is not configurable, and a
     * debug.php that says otherwise must not be able to make it so.
     */
    public function testTheFileSourceCannotRelaxTheAdminOrModuleGates(): void
    {
        self::assertFalse(AccessPolicy::evaluate(false, 0, true, true), 'non-admin stays refused');
        self::assertFalse(AccessPolicy::evaluate(false, 2, true, true), 'non-admin stays refused with both sources on');
        self::assertFalse(AccessPolicy::evaluate(true, 2, false, true), 'module switch still overrides');
    }

    /**
     * $fileEnabled defaults to false, so every caller written before the second
     * source existed keeps its exact meaning.
     */
    public function testTheThreeArgumentFormIsUnchanged(): void
    {
        self::assertTrue(AccessPolicy::evaluate(true, 1, true));
        self::assertFalse(AccessPolicy::evaluate(true, 0, true));
    }

    /**
     * On a core without the 2.7.3 debug API there is no file source at all, and
     * asking for one must not fatal — the module still supports 2.7.0-2.7.2.
     */
    public function testFileEnablesDebugbarIsFalseWithoutTheCoreDebugApi(): void
    {
        if (\function_exists('xoops_getDebugConfig')) {
            self::markTestSkipped('Core debug API is present in this run.');
        }

        self::assertFalse(AccessPolicy::fileEnablesDebugbar());
    }

    /**
     * Both wrappers must consult the file source, or the toolbar renders from one
     * answer while its endpoints refuse from another — the mismatch this class
     * exists to prevent.
     */
    public function testBothWrappersConsultTheFileSource(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/class/Admin/AccessPolicy.php');
        self::assertIsString($source);

        [, $strict] = explode('public static function isAllowed(): bool', $source, 2);
        [$strict, $runtime] = explode('public static function isRuntimeAllowed(): bool', $strict, 2);

        self::assertStringContainsString('fileEnablesDebugbar()', $strict);
        self::assertStringContainsString('fileEnablesDebugbar()', $runtime);
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
