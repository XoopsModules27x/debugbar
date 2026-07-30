<?php
/**
 * DebugBar Module for XOOPS 2.7.0
 *
 * Provides PHP DebugBar integration for in-browser debugging.
 * Ported from XOOPS 2.6.0 modules/debugbar.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              trabis <lusopoemas@gmail.com>
 * @author              Richard Griffith <richard@geekwright.com>
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

$modversion = [];

// --- Module Info ---
$modversion['version']      = '1.4.0';
$modversion['release_date'] = '2026/07/28';
$modversion['name']         = _MI_DEBUGBAR_NAME;
$modversion['description']  = _MI_DEBUGBAR_DSC;
$modversion['author']       = 'XOOPS Project';
$modversion['credits']      = 'trabis, Richard Griffith';
$modversion['license']      = 'GNU GPL 2.0 or later';
$modversion['license_url']  = 'https://www.gnu.org/licenses/gpl-2.0.html';
$modversion['official']     = 1;
$modversion['image']        = 'assets/images/logoModule.png'; // optional, module works without it
$modversion['dirname']      = 'debugbar';
$modversion['tables']       = ['debugbar_profiles'];
$modversion['sqlfile']      = ['mysql' => 'sql/mysql.sql'];

// --- Min Requirements ---
$modversion['min_php']   = '8.2.0';
$modversion['min_xoops'] = '2.7.0';

// --- Admin ---
$modversion['hasAdmin']    = 1;
$modversion['system_menu'] = 1;
$modversion['adminindex']  = 'admin/index.php';
$modversion['adminmenu']   = 'admin/menu.php';

// --- Install/Update callbacks ---
$modversion['onInstall'] = 'include/install.php';
$modversion['onUpdate']  = 'include/install.php';

$modversion['help']        = 'page=help';
$modversion['helpsection'] = [
    ['name' => _MI_DEBUGBAR_OVERVIEW, 'link' => 'page=help'],
    ['name' => _MI_DEBUGBAR_DISCLAIMER, 'link' => 'page=disclaimer'],
    ['name' => _MI_DEBUGBAR_LICENSE, 'link' => 'page=license'],
    ['name' => _MI_DEBUGBAR_SUPPORT, 'link' => 'page=support'],
];



// --- Module Config ---
$modversion['config'][] = [
    'name'        => 'debugbar_enable',
    'title'       => '_MI_DEBUGBAR_ENABLE',
    'description' => '',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

$modversion['config'][] = [
    'name'        => 'debug_smarty_enable',
    'title'       => '_MI_DEBUGBAR_SMARTYDEBUG',
    'description' => '',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

$modversion['config'][] = [
    'name'        => 'debug_files_enable',
    'title'       => '_MI_DEBUGBAR_FILESDEBUG',
    'description' => '_MI_DEBUGBAR_FILESDEBUG_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

$modversion['config'][] = [
    'name'        => 'slow_query_threshold',
    'title'       => '_MI_DEBUGBAR_SLOWQUERY',
    'description' => '_MI_DEBUGBAR_SLOWQUERY_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'text',
    'default'     => '0.05',
];

$modversion['config'][] = [
    'name'        => 'query_log_mode',
    'title'       => '_MI_DEBUGBAR_QUERYMODE',
    'description' => '_MI_DEBUGBAR_QUERYMODE_DSC',
    'formtype'    => 'select',
    'valuetype'   => 'int',
    'default'     => 1,
    'options'     => [_MI_DEBUGBAR_QUERYMODE_ALL => 0, _MI_DEBUGBAR_QUERYMODE_SLOW => 1],
];

$modversion['config'][] = [
    'name'        => 'ray_enable',
    'title'       => '_MI_DEBUGBAR_RAY_ENABLE',
    'description' => '_MI_DEBUGBAR_RAY_ENABLE_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

$modversion['config'][] = [
    'name'        => 'slow_request_threshold',
    'title'       => '_MI_DEBUGBAR_SLOWREQUEST',
    'description' => '_MI_DEBUGBAR_SLOWREQUEST_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'text',
    'default'     => '1.0',
];

$modversion['config'][] = [
    'name'        => 'memory_threshold',
    'title'       => '_MI_DEBUGBAR_MEMORY_THRESHOLD',
    'description' => '_MI_DEBUGBAR_MEMORY_THRESHOLD_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 0,
];

foreach ([
    ['budget_queries', '_MI_DEBUGBAR_BUDGET_QUERIES', 30],
    ['budget_query_ms', '_MI_DEBUGBAR_BUDGET_QUERY_MS', 120],
    ['budget_boot_ms', '_MI_DEBUGBAR_BUDGET_BOOT_MS', 0],
    ['budget_total_ms', '_MI_DEBUGBAR_BUDGET_TOTAL_MS', 300],
    ['budget_memory_mb', '_MI_DEBUGBAR_BUDGET_MEMORY_MB', 32],
    ['budget_payload_kb', '_MI_DEBUGBAR_BUDGET_PAYLOAD_KB', 250],
    ['nplus1_threshold', '_MI_DEBUGBAR_NPLUS1_THRESHOLD', 5],
    ['profiles_retention_days', '_MI_DEBUGBAR_PROFILES_RETENTION', 7],
    ['profiles_max_rows', '_MI_DEBUGBAR_PROFILES_MAX_ROWS', 10000],
] as [$name, $title, $default]) {
    $description = match ($name) {
        'nplus1_threshold' => '_MI_DEBUGBAR_NPLUS1_THRESHOLD_DSC',
        'profiles_retention_days' => '_MI_DEBUGBAR_PROFILES_RETENTION_DSC',
        'profiles_max_rows' => '_MI_DEBUGBAR_PROFILES_MAX_ROWS_DSC',
        default => '_MI_DEBUGBAR_BUDGET_DSC',
    };
    $modversion['config'][] = [
        'name' => $name, 'title' => $title,
        'description' => $description,
        'formtype' => 'textbox', 'valuetype' => 'int', 'default' => $default,
    ];
}

$modversion['config'][] = [
    'name' => 'profiles_enable', 'title' => '_MI_DEBUGBAR_PROFILES_ENABLE',
    'description' => '_MI_DEBUGBAR_PROFILES_ENABLE_DSC', 'formtype' => 'yesno',
    'valuetype' => 'int', 'default' => 1,
];

$modversion['config'][] = [
    'name'        => 'rum_enable',
    'title'       => '_MI_DEBUGBAR_RUM_ENABLE',
    'description' => '_MI_DEBUGBAR_RUM_ENABLE_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

// --- Core-seam tap() collectors (Events / Authz / Templates) ---
$modversion['config'][] = [
    'name'        => 'collect_events',
    'title'       => '_MI_DEBUGBAR_COLLECT_EVENTS',
    'description' => '_MI_DEBUGBAR_COLLECT_EVENTS_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

$modversion['config'][] = [
    'name'        => 'collect_authz',
    'title'       => '_MI_DEBUGBAR_COLLECT_AUTHZ',
    'description' => '_MI_DEBUGBAR_COLLECT_AUTHZ_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

$modversion['config'][] = [
    'name'        => 'collect_templates',
    'title'       => '_MI_DEBUGBAR_COLLECT_TEMPLATES',
    'description' => '_MI_DEBUGBAR_COLLECT_TEMPLATES_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

$modversion['config'][] = [
    'name'        => 'xdebug_button_enable',
    'title'       => '_MI_DEBUGBAR_XDEBUG_BUTTON',
    'description' => '_MI_DEBUGBAR_XDEBUG_BUTTON_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

$modversion['config'][] = [
    'name'        => 'monolog_enable',
    'title'       => '_MI_DEBUGBAR_MONOLOG_ENABLE',
    'description' => '_MI_DEBUGBAR_MONOLOG_ENABLE_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

$modversion['config'][] = [
    'name'        => 'explain_on_demand',
    'title'       => '_MI_DEBUGBAR_EXPLAIN_ON_DEMAND',
    'description' => '_MI_DEBUGBAR_EXPLAIN_ON_DEMAND_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

$modversion['config'][] = [
    'name'        => 'monolog_level',
    'title'       => '_MI_DEBUGBAR_MONOLOG_LEVEL',
    'description' => '_MI_DEBUGBAR_MONOLOG_LEVEL_DSC',
    'formtype'    => 'select',
    'valuetype'   => 'text',
    'default'     => 'warning',
    'options'     => [
        _MI_DEBUGBAR_LEVEL_DEBUG => 'debug', _MI_DEBUGBAR_LEVEL_INFO => 'info',
        _MI_DEBUGBAR_LEVEL_NOTICE => 'notice', _MI_DEBUGBAR_LEVEL_WARNING => 'warning',
        _MI_DEBUGBAR_LEVEL_ERROR => 'error', _MI_DEBUGBAR_LEVEL_CRITICAL => 'critical',
    ],
];

// --- Auto-EXPLAIN slowest SELECTs ---
$modversion['config'][] = [
    'name'        => 'explain_slow',
    'title'       => '_MI_DEBUGBAR_EXPLAIN_SLOW',
    'description' => '_MI_DEBUGBAR_EXPLAIN_SLOW_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

// --- Copy-to-clipboard secret redaction ---
$modversion['config'][] = [
    'name'        => 'copy_redact',
    'title'       => '_MI_DEBUGBAR_COPY_REDACT',
    'description' => '_MI_DEBUGBAR_COPY_REDACT_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

// --- Editor for clickable source locations ---
// Ignored when php.ini sets xdebug.file_link_format, which already tells the
// whole stack which editor to open.
$modversion['config'][] = [
    'name'        => 'editor_link',
    'title'       => '_MI_DEBUGBAR_EDITOR_LINK',
    'description' => '_MI_DEBUGBAR_EDITOR_LINK_DSC',
    'formtype'    => 'select',
    'valuetype'   => 'text',
    'default'     => 'vscode',
    'options'     => [
        'VS Code'          => 'vscode',
        'VS Code Insiders' => 'vscode-insiders',
        'VSCodium'         => 'vscodium',
        'PhpStorm'         => 'phpstorm',
        'IntelliJ IDEA'    => 'idea',
        'Cursor'           => 'cursor',
        'Windsurf'         => 'windsurf',
        'Zed'              => 'zed',
        'Sublime Text'     => 'sublime',
        'Netbeans'         => 'netbeans',
        'Atom'             => 'atom',
        'Emacs'            => 'emacs',
        'MacVim'           => 'macvim',
        'Nova'             => 'nova',
        'Xdebug protocol'  => 'xdebug',
    ],
];
