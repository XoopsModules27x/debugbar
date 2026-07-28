<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Admin;

use XoopsModules\Debugbar\Helper;

/**
 * DebugBar Module - Admin Access Policy
 *
 * Centralizes the "is this admin page allowed to run" decision: module
 * admin, XOOPS debug mode, and the module's own enable switch must all
 * agree. Used by admin pages that expose profiling data and must never
 * run when debug mode is off.
 *
 * @category  Module
 * @package   debugbar
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Class AccessPolicy
 */
final class AccessPolicy
{
    /**
     * Pure decision: allowed only when the caller is a module admin, XOOPS
     * debug mode is on (non-zero), and the module's own enable switch is on.
     *
     * @param bool $isModuleAdmin caller has module admin rights
     * @param int  $debugMode     XOOPS debug_mode config value
     * @param bool $moduleEnabled module's debugbar_enable config value
     *
     * @return bool
     */
    public static function evaluate(bool $isModuleAdmin, int $debugMode, bool $moduleEnabled): bool
    {
        return $isModuleAdmin && (0 !== $debugMode) && $moduleEnabled;
    }

    /**
     * Never-throw wrapper reading the live environment.
     *
     * @return bool
     */
    public static function isAllowed(): bool
    {
        try {
            $isModuleAdmin = (bool) ($GLOBALS['xoopsUserIsAdmin'] ?? false);
            $debugMode = (int) ($GLOBALS['xoopsConfig']['debug_mode'] ?? 0);
            $moduleEnabled = (bool) (Helper::getInstance()->getConfig('debugbar_enable') ?? true);

            return self::evaluate($isModuleAdmin, $debugMode, $moduleEnabled);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
