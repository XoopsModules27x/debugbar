<?php

declare(strict_types=1);

if (!defined('XOOPS_ROOT_PATH')) {
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

if (empty($GLOBALS['xoopsUserIsAdmin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Administrator access required']);
    exit;
}

$token = \Xmf\Request::getString('DEBUGBAR_EXPLAIN_REQUEST', '', 'POST');
$xoopsTokenValid = isset($GLOBALS['xoopsSecurity'])
    && $GLOBALS['xoopsSecurity']->check(true, false, 'DEBUGBAR_EXPLAIN');
$signedTokenValid = false;
if (class_exists('XoopsModules\\Debugbar\\DebugbarLogger')) {
    $signedTokenValid = \XoopsModules\Debugbar\DebugbarLogger::getInstance()->isValidExplainToken($token);
}
if (!$xoopsTokenValid && !$signedTokenValid) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$sql = \Xmf\Request::getString('sql', '', 'POST');
$sql = trim($sql);

// EXPLAIN is deliberately restricted to one read-only SELECT. For CTEs, the
// classifier identifies the top-level statement after all CTE definitions;
// quoted keywords and nested SELECTs cannot disguise a writable statement.
if ($sql === '' || strlen($sql) > 100000
    || !\XoopsModules\Debugbar\Analysis\SqlStatementClassifier::isReadOnlySelect($sql)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only a single read-only SELECT or WITH ... SELECT query can be explained']);
    exit;
}

/** @var XoopsMySQLDatabase $xoopsDB */
$xoopsDB = $GLOBALS['xoopsDB'];
$explainSql = 'EXPLAIN ' . $sql;

try {
    $result = $xoopsDB->query($explainSql);
    if (!$xoopsDB->isResultSet($result) || !($result instanceof \mysqli_result)) {
        throw new \RuntimeException('The database did not return an EXPLAIN result set');
    }

    $rows = [];
    while (false !== ($row = $xoopsDB->fetchArray($result))) {
        $rows[] = $row;
    }

    echo json_encode(['sql' => $explainSql, 'rows' => $rows], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    if (isset($GLOBALS['xoopsLogger']) && is_object($GLOBALS['xoopsLogger']) && method_exists($GLOBALS['xoopsLogger'], 'log')) {
        $GLOBALS['xoopsLogger']->log(
            \Psr\Log\LogLevel::ERROR,
            'DebugBar EXPLAIN failed',
            ['channel' => 'messages', 'exception' => $e]
        );
    }
    http_response_code(400);
    echo json_encode(['error' => 'Explain failed']);
}
