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

// The preference titles below are constant NAMES resolved by the system module
// when it renders the form. The line_break dividers additionally need their
// values available while this file is being read, so load the language here
// rather than relying on the caller having done it.
xoops_loadLanguage('modinfo', basename(__DIR__));

$modversion = [];

// --- Module Info ---
$modversion['version']      = '1.4.0';
$modversion['release_date'] = '2026/08/01';
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
// Preferences are ordered into 7 contiguous sections, each introduced by a
// `line_break` divider so they render as labelled groups on the single (flat)
// preferences page. Divider titles use _MI_DEBUGBAR_HDR_* constants whose
// values carry <strong> markup: XOOPS emits a constant title unescaped, so it
// renders bold, whereas a plain-string title would be HTML-escaped and show
// the tags literally.
//
// Order is load-bearing. XoopsConfigItem rows have no explicit weight column,
// so the form renders them in insertion order — a divider only labels the
// block that follows it because that block is inserted immediately after.
// Moving an entry between sections here moves it on the page.

// ============================= 1. General =============================
$modversion['config'][] = [
    'name'        => 'hdr_general',
    'title'       => '_MI_DEBUGBAR_HDR_GENERAL',
    'description' => '',
    'formtype'    => 'line_break',
    'valuetype'   => 'text',
    'default'     => 'even',
    'category'    => 'group_header',
];

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
    'default'     => 1,
];

$modversion['config'][] = [
    'name'        => 'debug_files_enable',
    'title'       => '_MI_DEBUGBAR_FILESDEBUG',
    'description' => '_MI_DEBUGBAR_FILESDEBUG_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
];

// How many backtrace frames each message renders in the bar. The full trace is
// still written to the log file; this only bounds what the toolbar draws.
$modversion['config'][] = [
    'name'        => 'trace_depth',
    'title'       => '_MI_DEBUGBAR_TRACE_DEPTH',
    'description' => '_MI_DEBUGBAR_TRACE_DEPTH_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 25,
];

// Ignored when php.ini sets xdebug.file_link_format, which already tells the
// whole stack which editor to open.
$modversion['config'][] = [
    'name'        => 'editor_link',
    'title'       => '_MI_DEBUGBAR_EDITOR_LINK',
    'description' => '_MI_DEBUGBAR_EDITOR_LINK_DSC',
    'formtype'    => 'select',
    'valuetype'   => 'text',
    'default'     => 'phpstorm',
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

// ================= 2. Integrations & external tools =================
$modversion['config'][] = [
    'name'        => 'hdr_integrations',
    'title'       => '_MI_DEBUGBAR_HDR_INTEGRATIONS',
    'description' => '',
    'formtype'    => 'line_break',
    'valuetype'   => 'text',
    'default'     => 'odd',
    'category'    => 'group_header',
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
    'name'        => 'ray_enable',
    'title'       => '_MI_DEBUGBAR_RAY_ENABLE',
    'description' => '_MI_DEBUGBAR_RAY_ENABLE_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 0,
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

// ========================== 3. Queries & SQL ==========================
$modversion['config'][] = [
    'name'        => 'hdr_queries',
    'title'       => '_MI_DEBUGBAR_HDR_QUERIES',
    'description' => '',
    'formtype'    => 'line_break',
    'valuetype'   => 'text',
    'default'     => 'even',
    'category'    => 'group_header',
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
    'name'        => 'slow_query_threshold',
    'title'       => '_MI_DEBUGBAR_SLOWQUERY',
    'description' => '_MI_DEBUGBAR_SLOWQUERY_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'text',
    'default'     => '0.05',
];

$modversion['config'][] = [
    'name'        => 'nplus1_threshold',
    'title'       => '_MI_DEBUGBAR_NPLUS1_THRESHOLD',
    'description' => '_MI_DEBUGBAR_NPLUS1_THRESHOLD_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 5,
];

$modversion['config'][] = [
    'name'        => 'explain_slow',
    'title'       => '_MI_DEBUGBAR_EXPLAIN_SLOW',
    'description' => '_MI_DEBUGBAR_EXPLAIN_SLOW_DSC',
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

// =============== 4. Performance (thresholds + budgets) ===============
$modversion['config'][] = [
    'name'        => 'hdr_performance',
    'title'       => '_MI_DEBUGBAR_HDR_PERFORMANCE',
    'description' => '',
    'formtype'    => 'line_break',
    'valuetype'   => 'text',
    'default'     => 'odd',
    'category'    => 'group_header',
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

// Performance budgets (0 disables an individual check).
$modversion['config'][] = [
    'name'        => 'budget_queries',
    'title'       => '_MI_DEBUGBAR_BUDGET_QUERIES',
    'description' => '_MI_DEBUGBAR_BUDGET_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 30,
];

$modversion['config'][] = [
    'name'        => 'budget_query_ms',
    'title'       => '_MI_DEBUGBAR_BUDGET_QUERY_MS',
    'description' => '_MI_DEBUGBAR_BUDGET_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 120,
];

$modversion['config'][] = [
    'name'        => 'budget_boot_ms',
    'title'       => '_MI_DEBUGBAR_BUDGET_BOOT_MS',
    'description' => '_MI_DEBUGBAR_BUDGET_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 100,
];

$modversion['config'][] = [
    'name'        => 'budget_total_ms',
    'title'       => '_MI_DEBUGBAR_BUDGET_TOTAL_MS',
    'description' => '_MI_DEBUGBAR_BUDGET_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 300,
];

$modversion['config'][] = [
    'name'        => 'budget_memory_mb',
    'title'       => '_MI_DEBUGBAR_BUDGET_MEMORY_MB',
    'description' => '_MI_DEBUGBAR_BUDGET_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 32,
];

$modversion['config'][] = [
    'name'        => 'budget_payload_kb',
    'title'       => '_MI_DEBUGBAR_BUDGET_PAYLOAD_KB',
    'description' => '_MI_DEBUGBAR_BUDGET_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 250,
];

// ====================== 5. Profiles & Analytics ======================
$modversion['config'][] = [
    'name'        => 'hdr_profiles',
    'title'       => '_MI_DEBUGBAR_HDR_PROFILES',
    'description' => '',
    'formtype'    => 'line_break',
    'valuetype'   => 'text',
    'default'     => 'even',
    'category'    => 'group_header',
];

$modversion['config'][] = [
    'name'        => 'profiles_enable',
    'title'       => '_MI_DEBUGBAR_PROFILES_ENABLE',
    'description' => '_MI_DEBUGBAR_PROFILES_ENABLE_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

$modversion['config'][] = [
    'name'        => 'profiles_retention_days',
    'title'       => '_MI_DEBUGBAR_PROFILES_RETENTION',
    'description' => '_MI_DEBUGBAR_PROFILES_RETENTION_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 7,
];

$modversion['config'][] = [
    'name'        => 'profiles_max_rows',
    'title'       => '_MI_DEBUGBAR_PROFILES_MAX_ROWS',
    'description' => '_MI_DEBUGBAR_PROFILES_MAX_ROWS_DSC',
    'formtype'    => 'textbox',
    'valuetype'   => 'int',
    'default'     => 10000,
];

// RUM web-vitals beacon (feeds the Analytics page — kept with Profiles).
$modversion['config'][] = [
    'name'        => 'rum_enable',
    'title'       => '_MI_DEBUGBAR_RUM_ENABLE',
    'description' => '_MI_DEBUGBAR_RUM_ENABLE_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

// ============================= 6. Privacy =============================
$modversion['config'][] = [
    'name'        => 'hdr_privacy',
    'title'       => '_MI_DEBUGBAR_HDR_PRIVACY',
    'description' => '',
    'formtype'    => 'line_break',
    'valuetype'   => 'text',
    'default'     => 'odd',
    'category'    => 'group_header',
];

$modversion['config'][] = [
    'name'        => 'copy_redact',
    'title'       => '_MI_DEBUGBAR_COPY_REDACT',
    'description' => '_MI_DEBUGBAR_COPY_REDACT_DSC',
    'formtype'    => 'yesno',
    'valuetype'   => 'int',
    'default'     => 1,
];

// ================ 7. Advanced: core-seam collectors ================
$modversion['config'][] = [
    'name'        => 'hdr_advanced',
    'title'       => '_MI_DEBUGBAR_HDR_ADVANCED',
    'description' => '',
    'formtype'    => 'line_break',
    'valuetype'   => 'text',
    'default'     => 'even',
    'category'    => 'group_header',
];

// tap() collectors (Events / Templates)
//
// A collect_authz preference was declared here and removed before release: it
// promised an Authz tab logging group-permission checks, but no seam exists to
// collect them from a module. XoopsGroupPermHandler::checkRight() fires no
// preload event, and xoops_getHandler() caches handlers in a function-local
// static, so the handler cannot be decorated the way PreloadEventSpy decorates
// the event table or TemplateResource hooks Smarty. Implementing it needs a
// core-side seam first; until then the toggle would have done nothing.
$modversion['config'][] = [
    'name'        => 'collect_events',
    'title'       => '_MI_DEBUGBAR_COLLECT_EVENTS',
    'description' => '_MI_DEBUGBAR_COLLECT_EVENTS_DSC',
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
