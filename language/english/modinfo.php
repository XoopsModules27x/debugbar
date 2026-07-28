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
define('_MI_DEBUGBAR_BUDGET_QUERIES', 'Budget: query count');
define('_MI_DEBUGBAR_BUDGET_QUERY_MS', 'Budget: total query time (ms)');
define('_MI_DEBUGBAR_BUDGET_BOOT_MS', 'Budget: bootstrap time (ms)');
define('_MI_DEBUGBAR_BUDGET_TOTAL_MS', 'Budget: total request time (ms)');
define('_MI_DEBUGBAR_BUDGET_MEMORY_MB', 'Budget: peak memory (MB)');
define('_MI_DEBUGBAR_BUDGET_PAYLOAD_KB', 'Budget: HTML payload (KB)');
define('_MI_DEBUGBAR_NPLUS1_THRESHOLD', 'N+1 repeat threshold');
define('_MI_DEBUGBAR_NPLUS1_THRESHOLD_DSC', 'Same query shape repeated this many times in one request is flagged as an N+1 suspect');
define('_MI_DEBUGBAR_PROFILES_RETENTION', 'Profile retention (days)');
define('_MI_DEBUGBAR_PROFILES_RETENTION_DSC', 'Profiles older than this are trimmed automatically');
define('_MI_DEBUGBAR_PROFILES_MAX_ROWS', 'Profile row cap');
define('_MI_DEBUGBAR_PROFILES_MAX_ROWS_DSC', 'Hard cap on stored profile rows (oldest are trimmed first)');
define('_MI_DEBUGBAR_PROFILES_ENABLE', 'Store request profiles');
define('_MI_DEBUGBAR_PROFILES_ENABLE_DSC', 'Persist one compact row per profiled request to feed the Analytics page');
define('_MI_DEBUGBAR_RUM_ENABLE', 'Collect browser web-vitals (RUM beacon)');
define('_MI_DEBUGBAR_RUM_ENABLE_DSC', 'Inject a small beacon (admins in debug mode only) that reports LCP/INP/CLS back to the Analytics page');
define('_MI_DEBUGBAR_COLLECT_EVENTS', 'Collect preload events');
define('_MI_DEBUGBAR_COLLECT_EVENTS_DSC', 'Log XOOPS preload events with listener counts and durations to an Events tab (max 300 rows).');
define('_MI_DEBUGBAR_COLLECT_AUTHZ', 'Collect permission checks');
define('_MI_DEBUGBAR_COLLECT_AUTHZ_DSC', 'Log group-permission checks with ALLOW/DENY results to an Authz tab (max 500 rows). High volume — enable only while debugging permissions.');
define('_MI_DEBUGBAR_COLLECT_TEMPLATES', 'Collect template resolution');
define('_MI_DEBUGBAR_COLLECT_TEMPLATES_DSC', 'Log which source (theme override, module file, database) served each template to a Templates tab (max 300 rows).');
define('_MI_DEBUGBAR_XDEBUG_BUTTON', "Show 'Profile this request' button");
define('_MI_DEBUGBAR_XDEBUG_BUTTON_DSC', 'Adds a button to the debug bar (admins in debug mode only) that arms a one-shot Xdebug profile trigger for the next page load');
define('_MI_DEBUGBAR_EXPLAIN_ON_DEMAND', 'On-demand SQL EXPLAIN');
define('_MI_DEBUGBAR_EXPLAIN_ON_DEMAND_DSC', 'Adds an EXPLAIN button to Queries rows (admin-only, two-step confirm). The server only EXPLAINs SELECT statements it recorded itself for the current request. Off by default.');
define('_MI_DEBUGBAR_EXPLAIN_SLOW', 'EXPLAIN slow queries');
define('_MI_DEBUGBAR_EXPLAIN_SLOW_DSC', 'Run EXPLAIN on the slowest SELECTs and flag full scans / filesorts (dev mode only)');
define('_MI_DEBUGBAR_COPY_REDACT', 'Redact secrets in copied output');
define('_MI_DEBUGBAR_COPY_REDACT_DSC', 'Mask password/token/session/cookie values in the Copy-to-clipboard output. Display in the bar is never redacted.');
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
define('_MI_DEBUGBAR_BUDGET_DSC', 'Requests exceeding this are flagged in the Perf tab and Analytics; 0 disables the check');

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
