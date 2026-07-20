<?php

declare(strict_types=1);

use XoopsModules\Debugbar\Analysis\SystemDiagnostics;

require_once __DIR__ . '/admin_header.php';

$adminObject = \Xmf\Module\Admin::getInstance();

xoops_cp_header();
$adminObject->displayNavigation(basename(__FILE__));

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cssFile = XOOPS_ROOT_PATH . '/modules/debugbar/assets-custom/admin-diagnostics.css';
$cssVersion = is_file($cssFile) ? (string) filemtime($cssFile) : '1';
echo '<link rel="stylesheet" href="'
    . $esc(XOOPS_URL . '/modules/debugbar/assets-custom/admin-diagnostics.css?v=' . rawurlencode($cssVersion))
    . '">';

$report = (new SystemDiagnostics(
    XOOPS_ROOT_PATH,
    (defined('XOOPS_VAR_PATH') && XOOPS_VAR_PATH !== '') ? XOOPS_VAR_PATH : XOOPS_ROOT_PATH . '/xoops_data'
))->collect(is_array($GLOBALS['xoopsConfig'] ?? null) ? $GLOBALS['xoopsConfig'] : []);

$sections = [
    'runtime'      => _AM_DEBUGBAR_DIAG_RUNTIME,
    'themes'       => _AM_DEBUGBAR_DIAG_THEMES,
    'tools'        => _AM_DEBUGBAR_DIAG_TOOLS,
    'storage'      => _AM_DEBUGBAR_DIAG_STORAGE,
    'theme_system' => _AM_DEBUGBAR_DIAG_THEME_SYSTEM,
];
$labels = [
    'xoops_version'     => _AM_DEBUGBAR_DIAG_XOOPS_VERSION,
    'php_version'       => _AM_DEBUGBAR_PHP_VERSION,
    'xoops_debug'       => _AM_DEBUGBAR_XOOPS_DEBUG,
    'environment'       => _AM_DEBUGBAR_DIAG_ENVIRONMENT,
    'timezone'          => _AM_DEBUGBAR_DIAG_TIMEZONE,
    'front_theme'       => _AM_DEBUGBAR_DIAG_FRONT_THEME,
    'admin_theme'       => _AM_DEBUGBAR_DIAG_ADMIN_THEME,
    'xdebug'            => _AM_DEBUGBAR_DIAG_XDEBUG,
    'opcache'           => _AM_DEBUGBAR_DIAG_OPCACHE,
    'php_debugbar'      => _AM_DEBUGBAR_PHP_DEBUGBAR,
    'monolog'           => _AM_DEBUGBAR_MONOLOG,
    'whoops'            => _AM_DEBUGBAR_DIAG_WHOOPS,
    'ray'               => _AM_DEBUGBAR_RAY,
    'tracy'             => _AM_DEBUGBAR_DIAG_TRACY,
    'explain_secret'    => _AM_DEBUGBAR_DIAG_EXPLAIN_SECRET,
    'logs'              => _AM_DEBUGBAR_DIAG_LOG_DIR,
    'caches'            => _AM_DEBUGBAR_DIAG_CACHE_DIR,
    'data'              => _AM_DEBUGBAR_DIAG_DATA_DIR,
    'debugbar_data'     => _AM_DEBUGBAR_DIAG_DEBUGBAR_DIR,
    'template_compile'  => _AM_DEBUGBAR_DIAG_COMPILE_DIR,
    'theme_engine'      => _AM_DEBUGBAR_DIAG_THEME_ENGINE,
    'theme_blocks'      => _AM_DEBUGBAR_DIAG_THEME_BLOCKS,
    'front_theme_entry' => _AM_DEBUGBAR_DIAG_FRONT_ENTRY,
    'admin_theme_entry' => _AM_DEBUGBAR_DIAG_ADMIN_ENTRY,
];
$statusLabels = [
    'ok'      => _AM_DEBUGBAR_DIAG_OK,
    'warning' => _AM_DEBUGBAR_DIAG_WARNING,
    'info'    => _AM_DEBUGBAR_DIAG_INFO,
];

echo '<div class="debugbar-diagnostics">';
echo '<header class="debugbar-diagnostics__header"><div><h2>' . $esc(_AM_DEBUGBAR_DIAG_TITLE) . '</h2>';
echo '<p>' . $esc(_AM_DEBUGBAR_DIAG_DESCRIPTION) . '</p></div>';
echo '<span class="debugbar-diagnostics__privacy">' . $esc(_AM_DEBUGBAR_DIAG_PRIVACY) . '</span></header>';

foreach ($sections as $sectionId => $sectionLabel) {
    echo '<section class="debugbar-diagnostics__section"><h3>' . $esc($sectionLabel) . '</h3>';
    echo '<div class="debugbar-diagnostics__table-wrap"><table class="debugbar-diagnostics__table">';
    echo '<colgroup><col class="debugbar-diagnostics__col--check"><col class="debugbar-diagnostics__col--value">';
    echo '<col class="debugbar-diagnostics__col--status"><col class="debugbar-diagnostics__col--details"></colgroup>';
    echo '<thead><tr>';
    echo '<th>' . $esc(_AM_DEBUGBAR_DIAG_CHECK) . '</th><th>' . $esc(_AM_DEBUGBAR_DIAG_VALUE) . '</th>';
    echo '<th>' . $esc(_AM_DEBUGBAR_DIAG_STATUS) . '</th><th>' . $esc(_AM_DEBUGBAR_DIAG_DETAILS) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($report[$sectionId] as $row) {
        $status = array_key_exists($row['status'], $statusLabels) ? $row['status'] : 'info';
        echo '<tr><th scope="row">' . $esc($labels[$row['id']] ?? $row['id']) . '</th>';
        echo '<td><code>' . $esc($row['value']) . '</code></td>';
        echo '<td><span class="debugbar-diagnostics__status debugbar-diagnostics__status--' . $esc($status) . '">'
            . $esc($statusLabels[$status]) . '</span></td>';
        echo '<td>' . $esc($row['detail'] !== '' ? $row['detail'] : '—') . '</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

echo '</div>';

require_once __DIR__ . '/admin_footer.php';
