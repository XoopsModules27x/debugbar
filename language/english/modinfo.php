<?php
/**
 * DebugBar Module - Module Info Language Constants
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author              Richard Griffith <richard@geekwright.com>
 */

define('_MI_DEBUGBAR_NAME', 'DebugBar');
define('_MI_DEBUGBAR_DSC', 'Error reporting and performance analysis using PHP DebugBar');

define('_MI_DEBUGBAR_ENABLE', 'Display DebugBar');
define('_MI_DEBUGBAR_SMARTYDEBUG', 'Enable Smarty Debug');
define('_MI_DEBUGBAR_FILESDEBUG', 'Enable Included Files Tab');
define('_MI_DEBUGBAR_FILESDEBUG_DSC', 'Show all PHP files loaded during the request');
define('_MI_DEBUGBAR_SLOWQUERY', 'Slow Query Threshold (seconds)');
define('_MI_DEBUGBAR_SLOWQUERY_DSC', 'Queries slower than this are highlighted in red (e.g. 0.05 = 50ms)');

define('_MI_DEBUGBAR_QUERYMODE',      'Query Logging');
define('_MI_DEBUGBAR_QUERYMODE_DSC',  'All queries shown, or slow queries & errors only');
define('_MI_DEBUGBAR_QUERYMODE_ALL',  'All queries');
define('_MI_DEBUGBAR_QUERYMODE_SLOW', 'Slow & errors only');

define('_MI_DEBUGBAR_RAY_ENABLE', 'Enable Ray Integration');
define('_MI_DEBUGBAR_RAY_ENABLE_DSC', 'Send debug data to Ray desktop app (requires spatie/ray or spatie/global-ray)');

define('_MI_DEBUGBAR_SLOWREQUEST', 'Slow Request Threshold (seconds)');
define('_MI_DEBUGBAR_SLOWREQUEST_DSC', 'Highlight requests that exceed this duration; use 0 to disable the budget warning');
define('_MI_DEBUGBAR_MEMORY_THRESHOLD', 'Memory Threshold (MB)');
define('_MI_DEBUGBAR_MEMORY_THRESHOLD_DSC', 'Highlight requests whose peak memory exceeds this value; use 0 to disable the budget warning');
define('_MI_DEBUGBAR_BUDGET_QUERIES', 'Query count budget');
define('_MI_DEBUGBAR_BUDGET_QUERY_MS', 'Total SQL time budget (ms)');
define('_MI_DEBUGBAR_BUDGET_BOOT_MS', 'Bootstrap time budget (ms)');
define('_MI_DEBUGBAR_BUDGET_TOTAL_MS', 'Request time budget (ms)');
define('_MI_DEBUGBAR_BUDGET_MEMORY_MB', 'Profiler memory budget (MB)');
define('_MI_DEBUGBAR_BUDGET_PAYLOAD_KB', 'Response payload budget (KB)');
define('_MI_DEBUGBAR_NPLUS1_THRESHOLD', 'Repeated-query warning threshold');
define('_MI_DEBUGBAR_NPLUS1_THRESHOLD_DSC', 'Set to 0 to disable; the minimum enabled threshold is 2 repeated queries');
define('_MI_DEBUGBAR_PROFILES_RETENTION', 'Profile retention (days)');
define('_MI_DEBUGBAR_PROFILES_MAX_ROWS', 'Maximum stored profiles');
define('_MI_DEBUGBAR_PROFILES_ENABLE', 'Store request profiles');
define('_MI_DEBUGBAR_PROFILES_ENABLE_DSC', 'Store compact admin-only performance profiles for later analysis');
define('_MI_DEBUGBAR_PROFILE_BUTTON_ENABLE', "Show 'Profile this request' button");
define('_MI_DEBUGBAR_PROFILE_BUTTON_ENABLE_DSC', 'Add a toolbar button that reloads the current page once with an Xdebug profiling trigger; requires xdebug.mode=profile');
define('_MI_DEBUGBAR_MONOLOG_ENABLE', 'Enable Monolog file logging');
define('_MI_DEBUGBAR_MONOLOG_ENABLE_DSC', 'Write XOOPS warnings and more severe messages to the protected xoops_data logs directory.');
define('_MI_DEBUGBAR_MONOLOG_LEVEL', 'Monolog minimum level');
define('_MI_DEBUGBAR_MONOLOG_LEVEL_DSC', 'Warning is recommended. Debug and Info can generate large logs on busy sites.');
define('_MI_DEBUGBAR_LEVEL_DEBUG', 'Debug');
define('_MI_DEBUGBAR_LEVEL_INFO', 'Info');
define('_MI_DEBUGBAR_LEVEL_NOTICE', 'Notice');
define('_MI_DEBUGBAR_LEVEL_WARNING', 'Warning (recommended)');
define('_MI_DEBUGBAR_LEVEL_ERROR', 'Error');
define('_MI_DEBUGBAR_LEVEL_CRITICAL', 'Critical');
define('_MI_DEBUGBAR_BUDGET_DSC', 'Set to 0 to disable this budget');

define('_MI_DEBUGBAR_ADMENU1', 'Home');
define('_MI_DEBUGBAR_MENU_ABOUT', 'About');
define('_MI_DEBUGBAR_MENU_ANALYTICS', 'Analytics');
define('_MI_DEBUGBAR_MENU_LOGS', 'Logs');
define('_MI_DEBUGBAR_MENU_DIAGNOSTICS', 'Diagnostics');
define('_MI_DEBUGBAR_ANALYTICS', _MI_DEBUGBAR_MENU_ANALYTICS);

//Help
\define('_MI_DEBUGBAR_DIRNAME', basename(dirname(__DIR__, 2)));
\define('_MI_DEBUGBAR_HELP_HEADER', __DIR__ . '/help/helpheader.tpl');
\define('_MI_DEBUGBAR_BACK_2_ADMIN', 'Back to Administration of ');
\define('_MI_DEBUGBAR_OVERVIEW', 'Overview');

//help multipage
\define('_MI_DEBUGBAR_DISCLAIMER', 'Disclaimer');
\define('_MI_DEBUGBAR_LICENSE', 'License');
\define('_MI_DEBUGBAR_SUPPORT', 'Support');
