<?php
declare(strict_types=1);

/**
 * DebugBar Module - Admin Language Constants
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             debugbar
 */

// Status panel labels
\define('_AM_DEBUGBAR_PHP_DEBUGBAR', 'PHP DebugBar library');
\define('_AM_DEBUGBAR_MONOLOG', 'Monolog library');
\define('_AM_DEBUGBAR_PHP_VERSION', 'PHP Version');
\define('_AM_DEBUGBAR_ASSETS', 'DebugBar Assets');
\define('_AM_DEBUGBAR_RAY', 'Ray Integration');

// Status values
\define('_AM_DEBUGBAR_INSTALLED', 'Installed');
\define('_AM_DEBUGBAR_NOT_FOUND', 'Not Found');
\define('_AM_DEBUGBAR_AVAILABLE', 'Available');
\define('_AM_DEBUGBAR_NOT_INSTALLED', 'Not installed');
\define('_AM_DEBUGBAR_COPIED', 'Copied');
\define('_AM_DEBUGBAR_NOT_COPIED', 'Not yet copied (run module update)');
\define('_AM_DEBUGBAR_ANALYTICS', 'Performance Analytics');
\define('_AM_DEBUGBAR_ANALYTICS_TITLE', 'DebugBar Performance Analytics');
\define('_AM_DEBUGBAR_NO_PROFILES', 'No stored profiles are available.');
\define('_AM_DEBUGBAR_PROFILE_COUNT', 'Stored profiles');
\define('_AM_DEBUGBAR_RECENT_VIOLATIONS', 'Recent budget violations');
\define('_AM_DEBUGBAR_WORST_URLS', 'Slowest request paths');
\define('_AM_DEBUGBAR_FLIGHT_RECORDS', 'Flight recorder files');

// Analytics (kept aligned with the XMF 2 DebugBar navigation vocabulary)
\define('_AM_DEBUGBAR_AN_NODATA', 'No data is available for this period.');
\define('_AM_DEBUGBAR_AN_DAYS', 'Last %d day(s)');
\define('_AM_DEBUGBAR_AN_ROWCOUNT', '%s profiles stored');
\define('_AM_DEBUGBAR_AN_BACK', 'Back to Analytics');
\define('_AM_DEBUGBAR_AN_RECORD', 'Flight record: %s');
\define('_AM_DEBUGBAR_AN_RECORD_MISSING', 'The requested flight record is unavailable.');
\define('_AM_DEBUGBAR_AN_OPCACHE', 'OPcache health (server-wide)');
\define('_AM_DEBUGBAR_AN_OPCACHE_UNAVAILABLE', 'OPcache status is unavailable.');
\define('_AM_DEBUGBAR_AN_HIT_RATE', 'Hit rate');
\define('_AM_DEBUGBAR_AN_MEMORY', 'Memory');
\define('_AM_DEBUGBAR_AN_CACHED_SCRIPTS', 'Cached scripts');
\define('_AM_DEBUGBAR_AN_RESTARTS', 'Restarts');
\define('_AM_DEBUGBAR_AN_WORST', 'Worst offenders (slowest URLs)');
\define('_AM_DEBUGBAR_AN_NPLUS1', 'N+1 leaderboard');
\define('_AM_DEBUGBAR_AN_MODULES', 'Per-module comparison');
\define('_AM_DEBUGBAR_AN_VIOLATIONS_FEED', 'Recent budget violations');
\define('_AM_DEBUGBAR_AN_VITALS', 'Field web-vitals (RUM beacon)');
\define('_AM_DEBUGBAR_AN_VITALS_UNAVAILABLE', 'Web-vitals collection is available after the XMF 2 migration; this compatibility module does not inject a browser beacon.');
\define('_AM_DEBUGBAR_AN_FLIGHT', 'Flight recorder (full request dumps)');
\define('_AM_DEBUGBAR_AN_URL', 'URL');
\define('_AM_DEBUGBAR_AN_MODULE', 'Module');
\define('_AM_DEBUGBAR_AN_HITS', 'Hits');
\define('_AM_DEBUGBAR_AN_AVG_MS', 'Avg ms');
\define('_AM_DEBUGBAR_AN_MAX_MS', 'Max ms');
\define('_AM_DEBUGBAR_AN_AVG_QUERIES', 'Avg queries');
\define('_AM_DEBUGBAR_AN_MAX_NPLUS1', 'Worst N+1');
\define('_AM_DEBUGBAR_AN_VIOLATIONS', 'Violations');
\define('_AM_DEBUGBAR_AN_SAMPLE_FP', 'Sample fingerprint');
\define('_AM_DEBUGBAR_AN_AVG_PAYLOAD', 'Avg payload KB');
\define('_AM_DEBUGBAR_AN_FRAGMENTS', 'Fragment hits');
\define('_AM_DEBUGBAR_AN_WHEN', 'When');
\define('_AM_DEBUGBAR_AN_TOTAL_MS', 'Total ms');
\define('_AM_DEBUGBAR_AN_QUERIES', 'Queries');
\define('_AM_DEBUGBAR_AN_FLAGS', 'Violated budgets');
\define('_AM_DEBUGBAR_AN_STATUS', 'Status');
\define('_AM_DEBUGBAR_AN_REQUEST', 'Request ID');
\define('_AM_DEBUGBAR_AN_SIZE', 'Size');
\define('_AM_DEBUGBAR_AN_VIEW', 'View');
\define('_AM_DEBUGBAR_AN_VIOLATION', 'Violation');
\define('_AM_DEBUGBAR_AN_OK', 'OK');
\define('_AM_DEBUGBAR_AN_CG_SECTION', 'Xdebug profiles');
\define('_AM_DEBUGBAR_AN_CG_EXTENSION', 'Xdebug extension');
\define('_AM_DEBUGBAR_AN_CG_MODES', 'Effective modes');
\define('_AM_DEBUGBAR_AN_CG_START', 'start_with_request');
\define('_AM_DEBUGBAR_AN_CG_DIR', 'Output directory');
\define('_AM_DEBUGBAR_AN_CG_ZLIB', 'zlib (for .gz output)');
\define('_AM_DEBUGBAR_AN_CG_FILE', 'File');
\define('_AM_DEBUGBAR_AN_CG_LOADED', 'Loaded');
\define('_AM_DEBUGBAR_AN_CG_NOT_LOADED', 'Not loaded');
\define('_AM_DEBUGBAR_AN_CG_ALTERNATIVES', 'Open these files in QCacheGrind/KCacheGrind or another cachegrind viewer for call-graph analysis.');
\define('_AM_DEBUGBAR_AN_CG_PURGE', 'Purge files older than 30 days');
\define('_AM_DEBUGBAR_AN_CG_PURGE_CONFIRM', 'Permanently delete Xdebug profile files older than 30 days?');
\define('_AM_DEBUGBAR_AN_CG_PURGED', '%d expired Xdebug profile file(s) deleted.');
\define('_AM_DEBUGBAR_AN_CG_BAD_TOKEN', 'The security token expired. No files were deleted.');

\define('_AM_DEBUGBAR_LOGS_TITLE', 'XOOPS logs');
\define('_AM_DEBUGBAR_LOGS_DESCRIPTION', 'Monolog files are stored outside the web root. The legacy XOOPS log is also shown for migration and troubleshooting.');
\define('_AM_DEBUGBAR_LOGS_EMPTY', 'No readable XOOPS log files were found.');
\define('_AM_DEBUGBAR_LOGS_FILE', 'Log file');
\define('_AM_DEBUGBAR_LOGS_SOURCE', 'Source');
\define('_AM_DEBUGBAR_LOGS_MODIFIED', 'Last modified');
\define('_AM_DEBUGBAR_LOGS_SIZE', 'Size');
\define('_AM_DEBUGBAR_LOGS_VIEW', 'View tail');
\define('_AM_DEBUGBAR_LOGS_BACK', 'Back to Logs');
\define('_AM_DEBUGBAR_LOGS_MISSING', 'The requested log is unavailable.');
\define('_AM_DEBUGBAR_LOGS_TAIL_NOTE', 'Showing at most the last 256 KB.');
\define('_AM_DEBUGBAR_LOGS_ENTRY_COUNT', '%d log entries');
\define('_AM_DEBUGBAR_LOGS_NEWEST_FIRST', 'Newest first');
\define('_AM_DEBUGBAR_LOGS_CONTEXT', 'Show structured context');
\define('_AM_DEBUGBAR_LOGS_ACTIVITY', 'Activity Log');
\define('_AM_DEBUGBAR_LOGS_TIME', 'Time');
\define('_AM_DEBUGBAR_LOGS_LEVEL', 'Level');
\define('_AM_DEBUGBAR_LOGS_DESCRIPTION_COLUMN', 'Description');
\define('_AM_DEBUGBAR_LOGS_CHANNEL', 'Channel');
\define('_AM_DEBUGBAR_LOGS_LOCATION', 'Location');
\define('_AM_DEBUGBAR_LOGS_DETAILS', 'Details');
\define('_AM_DEBUGBAR_REGISTERED', 'Registered and active');
\define('_AM_DEBUGBAR_INSTALLED_INACTIVE', 'Installed, not active');
\define('_AM_DEBUGBAR_XOOPS_DEBUG', 'XOOPS Debug');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_CONTROL', 'XOOPS Debug control');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_DSC', 'The DebugBar toolbar requires global XOOPS Debug. Optional exception integrations can also use this state. Enable it while testing, then turn it off on a production site.');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_TURN_ON', 'Turn XOOPS Debug ON');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_TURN_OFF', 'Turn XOOPS Debug OFF');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_ENABLED_MSG', 'XOOPS Debug is now enabled. The DebugBar toolbar can run on the next administrator request.');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_DISABLED_MSG', 'XOOPS Debug is now disabled.');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_FAILED', 'XOOPS Debug could not be updated.');
\define('_AM_DEBUGBAR_XOOPS_DEBUG_BAD_TOKEN', 'The security token expired. XOOPS Debug was not changed.');
\define('_AM_DEBUGBAR_ENABLED', 'Enabled');
\define('_AM_DEBUGBAR_DISABLED', 'Disabled');
\define('_AM_DEBUGBAR_WAITING_FOR_XOOPS_DEBUG', 'Enabled, waiting for XOOPS Debug');
\define('_AM_DEBUGBAR_TOOLBAR', 'DebugBar toolbar');
\define('_AM_DEBUGBAR_TOOLBAR_CONTROL', 'DebugBar toolbar control');
\define('_AM_DEBUGBAR_TOOLBAR_DSC', 'Controls the PHP DebugBar toolbar preference. The module and global XOOPS Debug must also be enabled.');
\define('_AM_DEBUGBAR_TOOLBAR_BLOCKED', 'The toolbar preference is enabled, but XOOPS Debug is OFF. Turn XOOPS Debug ON to display the toolbar.');
\define('_AM_DEBUGBAR_TOOLBAR_TURN_ON', 'Turn DebugBar toolbar ON');
\define('_AM_DEBUGBAR_TOOLBAR_TURN_OFF', 'Turn DebugBar toolbar OFF');
\define('_AM_DEBUGBAR_TOOLBAR_ENABLED_MSG', 'The DebugBar toolbar preference is now enabled.');
\define('_AM_DEBUGBAR_TOOLBAR_DISABLED_MSG', 'The DebugBar toolbar preference is now disabled.');
\define('_AM_DEBUGBAR_TOOLBAR_FAILED', 'The DebugBar toolbar preference could not be updated.');
\define('_AM_DEBUGBAR_TOOLBAR_BAD_TOKEN', 'The security token expired. The DebugBar toolbar was not changed.');
\define('_AM_DEBUGBAR_TRACY', 'Tracy toolbar');
\define('_AM_DEBUGBAR_TRACY_CONTROL', 'Tracy toolbar control');
\define('_AM_DEBUGBAR_TRACY_DSC', 'Controls the Tracy bootstrap toolbar for the next request. This writes a protected JSON override, not executable PHP.');
\define('_AM_DEBUGBAR_TRACY_TURN_ON', 'Turn Tracy toolbar ON');
\define('_AM_DEBUGBAR_TRACY_TURN_OFF', 'Turn Tracy toolbar OFF');
\define('_AM_DEBUGBAR_TRACY_ENABLED_MSG', 'The Tracy toolbar is now enabled.');
\define('_AM_DEBUGBAR_TRACY_DISABLED_MSG', 'The Tracy toolbar is now disabled.');
\define('_AM_DEBUGBAR_TRACY_FAILED', 'The Tracy toolbar setting could not be updated. Check that xoops_data/data is writable.');
\define('_AM_DEBUGBAR_TRACY_BAD_TOKEN', 'The security token expired. Tracy was not changed.');
\define('_AM_DEBUGBAR_TRACY_UNAVAILABLE', 'This installation does not expose an optional Tracy bootstrap control. DebugBar itself does not require Tracy.');

// Safe, read-only system diagnostics
\define('_AM_DEBUGBAR_DIAG_TITLE', 'System diagnostics');
\define('_AM_DEBUGBAR_DIAG_DESCRIPTION', 'A read-only snapshot of the XOOPS runtime, themes, diagnostic tools, and required storage.');
\define('_AM_DEBUGBAR_DIAG_PRIVACY', 'Admin only · no secrets or file contents');
\define('_AM_DEBUGBAR_DIAG_RUNTIME', 'Runtime');
\define('_AM_DEBUGBAR_DIAG_THEMES', 'Themes');
\define('_AM_DEBUGBAR_DIAG_TOOLS', 'Diagnostic tools');
\define('_AM_DEBUGBAR_DIAG_STORAGE', 'Writable storage');
\define('_AM_DEBUGBAR_DIAG_THEME_SYSTEM', 'Theme system files');
\define('_AM_DEBUGBAR_DIAG_CHECK', 'Check');
\define('_AM_DEBUGBAR_DIAG_VALUE', 'Value');
\define('_AM_DEBUGBAR_DIAG_STATUS', 'Status');
\define('_AM_DEBUGBAR_DIAG_DETAILS', 'Details');
\define('_AM_DEBUGBAR_DIAG_OK', 'OK');
\define('_AM_DEBUGBAR_DIAG_WARNING', 'Review');
\define('_AM_DEBUGBAR_DIAG_INFO', 'Info');
\define('_AM_DEBUGBAR_DIAG_XOOPS_VERSION', 'XOOPS version');
\define('_AM_DEBUGBAR_DIAG_ENVIRONMENT', 'Environment');
\define('_AM_DEBUGBAR_DIAG_TIMEZONE', 'Timezone');
\define('_AM_DEBUGBAR_DIAG_FRONT_THEME', 'Front-end theme');
\define('_AM_DEBUGBAR_DIAG_ADMIN_THEME', 'Admin theme');
\define('_AM_DEBUGBAR_DIAG_XDEBUG', 'Xdebug extension');
\define('_AM_DEBUGBAR_DIAG_OPCACHE', 'OPcache extension');
\define('_AM_DEBUGBAR_DIAG_WHOOPS', 'Whoops library');
\define('_AM_DEBUGBAR_DIAG_TRACY', 'Tracy library');
\define('_AM_DEBUGBAR_DIAG_EXPLAIN_SECRET', 'EXPLAIN signing key');
\define('_AM_DEBUGBAR_DIAG_LOG_DIR', 'XOOPS log directory');
\define('_AM_DEBUGBAR_DIAG_CACHE_DIR', 'XOOPS cache directory');
\define('_AM_DEBUGBAR_DIAG_DATA_DIR', 'XOOPS data directory');
\define('_AM_DEBUGBAR_DIAG_DEBUGBAR_DIR', 'DebugBar data directory');
\define('_AM_DEBUGBAR_DIAG_COMPILE_DIR', 'Template compile directory');
\define('_AM_DEBUGBAR_DIAG_THEME_ENGINE', 'Theme engine');
\define('_AM_DEBUGBAR_DIAG_THEME_BLOCKS', 'Theme block renderer');
\define('_AM_DEBUGBAR_DIAG_FRONT_ENTRY', 'Front theme entry file');
\define('_AM_DEBUGBAR_DIAG_ADMIN_ENTRY', 'Admin theme entry file');
