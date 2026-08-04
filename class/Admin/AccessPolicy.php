<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Admin;

use XoopsModules\Debugbar\Helper;

/**
 * DebugBar Module - Access Policy
 *
 * Centralizes the "is this allowed to run" decision. Module admin, XOOPS
 * debug mode, and the module's own enable switch must all agree; the two
 * wrappers below differ only in how strictly "admin" is read.
 *
 * There are deliberately TWO gates, because the two audiences differ:
 *
 *  - isAllowed()        — admin PAGES (analytics, diagnostics, logs). These
 *                         expose site-wide SQL, sessions and configuration
 *                         accumulated across other people's requests, so they
 *                         demand webmaster-group membership via
 *                         xoops_isDeveloperRequest().
 *  - isRuntimeAllowed() — the TOOLBAR and the endpoints that serve it
 *                         (beacon.php, explain.php, xdebug-arm.php). These
 *                         only ever expose the caller's own request, and they
 *                         must match the gate that decides whether the bar is
 *                         rendered at all — otherwise the bar shows a button
 *                         whose endpoint answers 403.
 *
 * Keeping both here is the point: an endpoint must never be laxer than the
 * toolbar that offers its button, nor stricter than the page it belongs to.
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
     * Shared by BOTH wrappers. The strict/runtime distinction lives entirely
     * in what gets passed as $isModuleAdmin, so the three-way AND itself can
     * never drift between them.
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

    /**
     * Never-throw wrapper for the toolbar and the endpoints that serve it.
     *
     * Same three-way decision as isAllowed(), but "admin" is the raw
     * $GLOBALS['xoopsUserIsAdmin'] flag rather than xoops_isDeveloperRequest().
     * That is deliberate, and it is what the preload has always used to decide
     * whether to render the bar. Reusing the strict gate here would diverge on
     * two axes that the toolbar does not care about:
     *
     *  - xoops_isDeveloperRequest() additionally requires XOOPS_GROUP_ADMIN
     *    membership, so a delegated module admin would see the bar and get 403
     *    from every button on it;
     *  - it accepts only debug_mode 1 or 2, while the bar renders for any
     *    non-zero value — debug_mode 3 (Smarty debug) would render a toolbar
     *    whose endpoints all refuse.
     *
     * The endpoints are still not open: each one additionally requires its own
     * server-minted XoopsSecurity token, and they expose only the caller's own
     * request. Site-wide history stays behind isAllowed().
     *
     * @return bool
     */
    public static function isRuntimeAllowed(): bool
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
