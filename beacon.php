<?php
declare(strict_types=1);

/**
 * DebugBar Module - RUM Web-Vitals Beacon Endpoint
 *
 * Receives the browser-side web-vitals payload (LCP/INP/CLS) sent by
 * assets/xoops-debugbar-rum.js via navigator.sendBeacon() and attaches it
 * to the matching stored profile row.
 *
 * Hard gates: POST only, authenticated admin, XOOPS debug mode on, valid
 * DEBUGBAR_RUM token, request id shape-validated. Responds 204 and never
 * renders anything — this endpoint must stay invisible to page flow.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             debugbar
 */

use XoopsModules\Debugbar\ProfileRepository;

require_once dirname(__DIR__, 2) . '/mainfile.php';

// Never let the debug logger append its panel to a beacon response
if (isset($GLOBALS['xoopsLogger']) && $GLOBALS['xoopsLogger'] instanceof \XoopsLogger) {
    $GLOBALS['xoopsLogger']->activated = false;
}

/**
 * Terminate with a bare status code.
 *
 * @param int $status HTTP status
 * @return never
 */
function debugbar_beacon_exit(int $status): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    exit;
}

if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
    debugbar_beacon_exit(405);
}

// Admin + debug mode gates mirror the preload's display gates
if (! (bool) ($GLOBALS['xoopsUserIsAdmin'] ?? false)) {
    debugbar_beacon_exit(403);
}
if (isset($GLOBALS['xoopsConfig']['debug_mode']) && 0 === (int) $GLOBALS['xoopsConfig']['debug_mode']) {
    debugbar_beacon_exit(403);
}

// sendBeacon cannot set headers, so the payload carries the token
$raw = file_get_contents('php://input', false, null, 0, 4096);
if (false === $raw || '' === $raw) {
    debugbar_beacon_exit(400);
}
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    debugbar_beacon_exit(400);
}

$token = isset($payload['token']) && is_string($payload['token']) ? $payload['token'] : '';
if (!$GLOBALS['xoopsSecurity']->check(true, $token, 'DEBUGBAR_RUM')) {
    debugbar_beacon_exit(403);
}

// Read-only from here on — release the session lock early
session_write_close();

$requestId = isset($payload['id']) && is_string($payload['id']) ? $payload['id'] : '';
if (1 !== preg_match('/^[0-9a-f]{16}$/', $requestId)) {
    debugbar_beacon_exit(400);
}

$toFloat = static function ($value): ?float {
    return is_numeric($value) ? (float) $value : null;
};

$repository = new ProfileRepository();
$repository->updateVitals(
    $requestId,
    $toFloat($payload['lcp'] ?? null),
    $toFloat($payload['inp'] ?? null),
    $toFloat($payload['cls'] ?? null)
);

debugbar_beacon_exit(204);
