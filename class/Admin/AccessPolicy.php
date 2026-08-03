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
            // $GLOBALS['xoopsUserIsAdmin'] is not a stable question to ask. common.php
            // sets it during auth, then REASSIGNS it to isAdmin($xoopsModule mid) once a
            // module is resolved -- so on these pages it means "admin of debugbar", and
            // on some other page it means something else. It has been the right answer
            // here only by coincidence of where the file happens to live.
            //
            // xoops_isDeveloperRequest() states the intent instead: an authenticated
            // member of the webmaster group, with debugging on, holding admin rights on
            // this module. NOTE this is a deliberate tightening -- a delegated module
            // admin who is NOT in the webmaster group loses access. That is the right
            // default for a page exposing site-wide SQL, sessions and configuration.
            // Drop the '?:' branch to a plain $GLOBALS read to revert.
            $isModuleAdmin = function_exists('xoops_isDeveloperRequest')
                ? \xoops_isDeveloperRequest('debugbar')
                : (bool) ($GLOBALS['xoopsUserIsAdmin'] ?? false);
            $debugMode = (int) ($GLOBALS['xoopsConfig']['debug_mode'] ?? 0);
            $moduleEnabled = (bool) (Helper::getInstance()->getConfig('debugbar_enable') ?? true);

            return self::evaluate($isModuleAdmin, $debugMode, $moduleEnabled);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
