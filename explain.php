<?php

declare(strict_types=1);

/**
 * DebugBar Module - On-demand SQL EXPLAIN Endpoint
 *
 * POST {request_id, sql_hash, token}. Only EXPLAINs SELECT statements the
 * server itself recorded this request (stash written by
 * DebugbarLogger::stashQueriesForExplain()). The client never sends SQL —
 * it only references a previously recorded statement by hash.
 *
 * Hard gates: POST only, authenticated admin, valid DEBUGBAR_EXPLAIN token,
 * the "explain_on_demand" module preference on, request id / sql hash
 * shape-validated, stash file present and not expired, statement is a
 * SELECT. Responds with generic JSON errors — never leaks SQL or paths.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             debugbar
 */

if (! defined('XOOPS_ROOT_PATH')) {
    // Bootstrap from the XOOPS root so common.php does not treat this JSON
    // endpoint as the module's front controller and redirect anonymous users
    // before the endpoint can return its own JSON 403 response.
    $originalDirectory = getcwd();
    chdir(dirname(__DIR__, 2));
    require_once dirname(__DIR__, 2) . '/mainfile.php';
    if (is_string($originalDirectory)) {
        chdir($originalDirectory);
    }
}
require_once __DIR__ . '/preloads/autoloader.php';

// This endpoint must return JSON only; suppress the legacy XOOPS logger's
// diagnostic HTML, which otherwise gets appended to the response body.
if (isset($GLOBALS['xoopsLogger']) && $GLOBALS['xoopsLogger'] instanceof \XoopsLogger) {
    $GLOBALS['xoopsLogger']->renderingEnabled = false;
    // If common.php already installed its output-buffer callback, disabling
    // only the flag is insufficient: render() also checks activated.
    $GLOBALS['xoopsLogger']->activated = false;
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Terminate with a generic JSON error body.
 *
 * @param string $msg  generic error message (never SQL or paths)
 * @param int    $code HTTP status
 * @return never
 */
function debugbar_explain_fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
    debugbar_explain_fail('Method not allowed.', 405);
}

// Module admin + XOOPS debug mode + the module's own enable switch, decided in
// one place so this endpoint cannot drift from the toolbar that posts to it.
// The bare admin flag this replaces checked neither debug mode nor
// debugbar_enable, so EXPLAIN stayed reachable with DebugBar switched off.
if (! \XoopsModules\Debugbar\Admin\AccessPolicy::isRuntimeAllowed()) {
    debugbar_explain_fail('Administrator access required', 403);
}

$token = \Xmf\Request::getString('token', '', 'POST');
// Reusable (not single-use): one page load may EXPLAIN several rows.
if (! isset($GLOBALS['xoopsSecurity'])
    || ! $GLOBALS['xoopsSecurity'] instanceof \XoopsSecurity
    || ! $GLOBALS['xoopsSecurity']->check(false, $token, 'DEBUGBAR_EXPLAIN')) {
    debugbar_explain_fail('Invalid security token', 403);
}

// Read-only from here on — release the session lock early
session_write_close();

$helper = \XoopsModules\Debugbar\Helper::getInstance();
if (! (bool) ($helper->getConfig('explain_on_demand') ?? false)) {
    debugbar_explain_fail('Disabled.', 403);
}

$requestId = strtolower(\Xmf\Request::getString('request_id', '', 'POST'));
$sqlHash = strtolower(\Xmf\Request::getString('sql_hash', '', 'POST'));
if (1 !== preg_match('/^[0-9a-f]{8,32}$/', $requestId) || 1 !== preg_match('/^[0-9a-f]{64}$/', $sqlHash)) {
    debugbar_explain_fail('Bad request.');
}

$varPath = defined('XOOPS_VAR_PATH') && XOOPS_VAR_PATH !== '' ? XOOPS_VAR_PATH : XOOPS_ROOT_PATH . '/xoops_data';
$file = $varPath . '/caches/debugbar_explain/' . $requestId . '.json';
if (! is_file($file) || (time() - (int) filemtime($file)) > 900) {
    debugbar_explain_fail('Query record expired.', 404);
}

$map = json_decode((string) file_get_contents($file), true);
$sql = is_array($map) ? ($map[$sqlHash] ?? null) : null;
if (! is_string($sql) || '' === $sql) {
    debugbar_explain_fail('Query not found.', 404);
}

// SELECT-only enforcement on the server-recorded statement.
$normalized = ltrim(preg_replace('#^\s*(/\*.*?\*/\s*|--[^\n]*\n\s*)*#s', '', $sql) ?? $sql);
if (0 !== stripos($normalized, 'SELECT')) {
    debugbar_explain_fail('Only SELECT statements can be explained.', 422);
}

// A SELECT prefix is not the same as "one SELECT and nothing else", and this
// line is about to hand the statement to the server. Neither form below is
// reachable today — the statement comes from the module's own server-side
// stash, not from the request, and mysqli::query() executes only one statement
// — but both protections are one comparison each, and they stop a future change
// to what gets stashed from turning this endpoint into an execution primitive.
//
// Semicolons are rejected wholesale rather than parsed for: telling a statement
// separator from a semicolon inside a string literal needs the tokenizer the
// redactor does not have either. A legitimate query containing one simply does
// not get an EXPLAIN, which is the safe direction for a diagnostic.
if (str_contains($normalized, ';')) {
    debugbar_explain_fail('Only a single statement can be explained.', 422);
}
// INTO OUTFILE / DUMPFILE turn a SELECT into a file write. EXPLAIN does not
// execute the statement, so this is belt-and-braces, but a form whose whole
// purpose is writing to disk should never reach the server from here.
if (1 === preg_match('/\bINTO\s+(?:OUT|DUMP)FILE\b/i', $normalized)) {
    debugbar_explain_fail('Only plain SELECT statements can be explained.', 422);
}

/** @var XoopsMySQLDatabase $xoopsDB */
$xoopsDB = $GLOBALS['xoopsDB'];

try {
    $result = $xoopsDB->query('EXPLAIN ' . $sql);
    if (! $xoopsDB->isResultSet($result) || ! ($result instanceof \mysqli_result)) {
        throw new \RuntimeException('The database did not return an EXPLAIN result set');
    }

    $rows = [];
    while (count($rows) < 50 && false !== ($row = $xoopsDB->fetchArray($result))) {
        $rows[] = array_map(static fn ($v) => null === $v ? null : (string) $v, $row);
    }

    echo json_encode(['rows' => $rows], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    if (isset($GLOBALS['xoopsLogger']) && is_object($GLOBALS['xoopsLogger']) && method_exists($GLOBALS['xoopsLogger'], 'log')) {
        $GLOBALS['xoopsLogger']->log(
            \Psr\Log\LogLevel::ERROR,
            'DebugBar EXPLAIN failed',
            ['channel' => 'messages', 'exception' => $e]
        );
    }
    debugbar_explain_fail('EXPLAIN failed.', 500);
}
