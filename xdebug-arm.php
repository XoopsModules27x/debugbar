<?php
declare(strict_types=1);

/**
 * DebugBar Module - Xdebug Profile Trigger Endpoint
 *
 * Arms a one-shot Xdebug profile capture for this browser's very next
 * request by setting the XDEBUG_TRIGGER cookie server-side, then expects
 * the caller to reload the page. The cookie is consumed and deleted by
 * Profiler::finalize() on that next request regardless of outcome — this
 * endpoint never leaves a lingering trigger cookie behind.
 *
 * Hard gates: POST only, authenticated admin, XOOPS debug mode on, the
 * "xdebug_button_enable" module preference on, a valid DEBUGBAR_XDEBUG
 * token, and XdebugStatus::read()['can_trigger'] true. Responds 204 and
 * never renders anything — this endpoint must stay invisible to page flow.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             debugbar
 */

use XoopsModules\Debugbar\Admin\AccessPolicy;
use XoopsModules\Debugbar\Analysis\XdebugStatus;
use XoopsModules\Debugbar\Helper;

require_once dirname(__DIR__, 2) . '/mainfile.php';

// Never let the debug logger append its panel to this endpoint's response
if (isset($GLOBALS['xoopsLogger']) && $GLOBALS['xoopsLogger'] instanceof \XoopsLogger) {
    $GLOBALS['xoopsLogger']->renderingEnabled = false;
    $GLOBALS['xoopsLogger']->activated = false;
}

/**
 * Terminate with a bare status code.
 *
 * @param int $status HTTP status
 * @return never
 */
function debugbar_arm_exit(int $status): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    exit;
}

if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
    debugbar_arm_exit(405);
}

// Module admin + XOOPS debug mode + the module's own enable switch, decided in
// one place so this endpoint cannot drift from the toolbar whose button posts
// to it. Deliberately the RUNTIME gate, not the admin-page one: the button is
// rendered under the toolbar's gate, so gating the endpoint any tighter would
// show a delegated module admin a button that always answers 403.
if (! AccessPolicy::isRuntimeAllowed()) {
    debugbar_arm_exit(403);
}

$buttonEnabled = (bool) (Helper::getInstance()->getConfig('xdebug_button_enable') ?? true);
if (!$buttonEnabled) {
    debugbar_arm_exit(403);
}

$token = \Xmf\Request::getString('token', '', 'POST');
// Fail closed rather than fatal, for the same reason as beacon.php: a hard-gated
// endpoint that never renders must not throw its way into a response body.
if (! isset($GLOBALS['xoopsSecurity'])
    || ! $GLOBALS['xoopsSecurity'] instanceof \XoopsSecurity
    || ! $GLOBALS['xoopsSecurity']->check(true, $token, 'DEBUGBAR_XDEBUG')) {
    debugbar_arm_exit(403);
}

// Read-only from here on — release the session lock early
session_write_close();

if (!(bool) (XdebugStatus::read()['can_trigger'] ?? false)) {
    debugbar_arm_exit(409);
}

$triggerValue = (string) @\ini_get('xdebug.trigger_value');
xoops_setcookie('XDEBUG_TRIGGER', '' !== $triggerValue ? $triggerValue : '1', time() + 60);

debugbar_arm_exit(204);
