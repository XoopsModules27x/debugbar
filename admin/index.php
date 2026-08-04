<?php
declare(strict_types=1);

/**
 * DebugBar Module - Admin Index
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             debugbar
 */

use Xmf\Module\Admin;
use Xmf\Request;
use XoopsModules\Debugbar\{
    Helper
};
use XoopsModules\Debugbar\Admin\AccessPolicy;

/** @var Admin $adminObject */
/** @var Helper $helper */

require_once __DIR__ . '/admin_header.php';

$action = Request::getCmd('action', '', 'POST');
if ($action === 'set_xoops_debug') {
    if (! isset($GLOBALS['xoopsSecurity'])
        || ! $GLOBALS['xoopsSecurity'] instanceof \XoopsSecurity
        || ! $GLOBALS['xoopsSecurity']->check(true, false, 'DEBUGBAR_XOOPS_DEBUG')) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_XOOPS_DEBUG_BAD_TOKEN);
        exit;
    }

    /** @var \XoopsConfigHandler $configHandler */
    $configHandler = xoops_getHandler('config');
    $criteria = new \CriteriaCompo(new \Criteria('conf_name', 'debug_mode'));
    $criteria->add(new \Criteria('conf_modid', 0));
    $criteria->add(new \Criteria('conf_catid', XOOPS_CONF));
    $configs = $configHandler->getConfigs($criteria);
    $debugConfig = $configs[0] ?? null;
    if (! $debugConfig instanceof \XoopsConfigItem) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_XOOPS_DEBUG_FAILED);
        exit;
    }

    $enabled = Request::getInt('enabled', 0, 'POST') === 1;
    // XoopsConfigItem keeps its legacy by-reference parameter, so the value
    // must be assigned before it is passed to setConfValueForInput().
    $debugValue = $enabled ? 1 : 0;
    $debugConfig->setConfValueForInput($debugValue);
    if (! $configHandler->insertConfig($debugConfig)) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_XOOPS_DEBUG_FAILED);
        exit;
    }
    redirect_header('index.php', 2, $enabled ? _AM_DEBUGBAR_XOOPS_DEBUG_ENABLED_MSG : _AM_DEBUGBAR_XOOPS_DEBUG_DISABLED_MSG);
    exit;
}

if ($action === 'set_debugbar') {
    if (! isset($GLOBALS['xoopsSecurity'])
        || ! $GLOBALS['xoopsSecurity'] instanceof \XoopsSecurity
        || ! $GLOBALS['xoopsSecurity']->check(true, false, 'DEBUGBAR_TOOLBAR_TOGGLE')) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TOOLBAR_BAD_TOKEN);
        exit;
    }

    $module = $helper->getModule();
    if (! $module instanceof \XoopsModule) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TOOLBAR_FAILED);
        exit;
    }

    /** @var \XoopsConfigHandler $configHandler */
    $configHandler = xoops_getHandler('config');
    $criteria = new \CriteriaCompo(new \Criteria('conf_name', 'debugbar_enable'));
    $criteria->add(new \Criteria('conf_modid', (int) $module->getVar('mid')));
    $configs = $configHandler->getConfigs($criteria);
    $toolbarConfig = $configs[0] ?? null;
    if (! $toolbarConfig instanceof \XoopsConfigItem) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TOOLBAR_FAILED);
        exit;
    }

    $enabled = Request::getInt('enabled', 0, 'POST') === 1;
    $toolbarValue = $enabled ? 1 : 0;
    $toolbarConfig->setConfValueForInput($toolbarValue);
    if (! $configHandler->insertConfig($toolbarConfig)) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TOOLBAR_FAILED);
        exit;
    }

    redirect_header('index.php', 2, $enabled ? _AM_DEBUGBAR_TOOLBAR_ENABLED_MSG : _AM_DEBUGBAR_TOOLBAR_DISABLED_MSG);
    exit;
}

if ($action === 'set_ray') {
    if (! isset($GLOBALS['xoopsSecurity'])
        || ! $GLOBALS['xoopsSecurity'] instanceof \XoopsSecurity
        || ! $GLOBALS['xoopsSecurity']->check(true, false, 'DEBUGBAR_RAY_TOGGLE')) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_RAY_BAD_TOKEN);
        exit;
    }

    $module = $helper->getModule();
    if (! $module instanceof \XoopsModule) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_RAY_FAILED);
        exit;
    }

    /** @var \XoopsConfigHandler $configHandler */
    $configHandler = xoops_getHandler('config');
    $criteria = new \CriteriaCompo(new \Criteria('conf_name', 'ray_enable'));
    $criteria->add(new \Criteria('conf_modid', (int) $module->getVar('mid')));
    $configs = $configHandler->getConfigs($criteria);
    $rayConfig = $configs[0] ?? null;
    if (! $rayConfig instanceof \XoopsConfigItem) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_RAY_FAILED);
        exit;
    }

    $enabled = Request::getInt('enabled', 0, 'POST') === 1;
    $rayValue = $enabled ? 1 : 0;
    $rayConfig->setConfValueForInput($rayValue);
    if (! $configHandler->insertConfig($rayConfig)) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_RAY_FAILED);
        exit;
    }

    redirect_header('index.php', 2, $enabled ? _AM_DEBUGBAR_RAY_ENABLED_MSG : _AM_DEBUGBAR_RAY_DISABLED_MSG);
    exit;
}

if ($action === 'set_tracy') {
    if (! isset($GLOBALS['xoopsSecurity'])
        || ! $GLOBALS['xoopsSecurity'] instanceof \XoopsSecurity
        || ! $GLOBALS['xoopsSecurity']->check(true, false, 'DEBUGBAR_TRACY_TOGGLE')) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TRACY_BAD_TOKEN);
        exit;
    }

    if (! defined('XOOPS_TRACY_STATUS')) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TRACY_UNAVAILABLE);
        exit;
    }

    $enabled = Request::getInt('enabled', 0, 'POST') === 1;
    $runtimeBase = defined('XOOPS_VAR_PATH') ? (string) constant('XOOPS_VAR_PATH') : '';
    if ($runtimeBase === '') {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TRACY_FAILED);
        exit;
    }
    $runtimeFile = $runtimeBase . '/data/debug-runtime.json';

    try {
        $runtimeJson = json_encode(
            ['tracy_enabled' => $enabled],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (\JsonException) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TRACY_FAILED);
        exit;
    }

    if (file_put_contents($runtimeFile, $runtimeJson . PHP_EOL, LOCK_EX) === false) {
        redirect_header('index.php', 3, _AM_DEBUGBAR_TRACY_FAILED);
        exit;
    }

    redirect_header('index.php', 2, $enabled ? _AM_DEBUGBAR_TRACY_ENABLED_MSG : _AM_DEBUGBAR_TRACY_DISABLED_MSG);
    exit;
}

xoops_cp_header();

$adminObject = Admin::getInstance();
$adminObject->displayNavigation(\basename(__FILE__));

// --- InfoBox: Module Status ---
$adminObject->addInfoBox(\constant('_CO_DEBUGBAR_STATS_SUMMARY'));

// Build status rows
$hasDebugbar = \class_exists('DebugBar\StandardDebugBar');
$hasMonolog = \class_exists('Monolog\Logger');
$monologActive = false;
if ($hasMonolog) {
    foreach (\XoopsLogger::getInstance()->getLoggers() as $registeredLogger) {
        if ($registeredLogger instanceof \XoopsMonologLogger && $registeredLogger->isActive()) {
            $monologActive = true;

            break;
        }
    }
}
$assetsDir = XOOPS_ROOT_PATH . '/modules/debugbar/assets';
$assetFiles = \glob($assetsDir . '/*');
$assetsExist = \is_dir($assetsDir) && \is_array($assetFiles) && \count($assetFiles) > 0;
// Ray is a 3-state: not installed / installed but preference off / installed + on.
$rayInstalled = \function_exists('ray')
    || \class_exists('Spatie\\Ray\\Ray')
    || \class_exists('Spatie\\GlobalRay\\GlobalRay');
$rayActivated = $rayInstalled && (bool) $helper->getConfig('ray_enable', 0);
$debugMode = (int) ($GLOBALS['xoopsConfig']['debug_mode'] ?? 0);
// One rule, shared with AccessPolicy::evaluate(): any non-zero debug_mode means
// debugging is on. Reading 1|2 here made debug_mode 3 (Smarty debug) render a
// toolbar while this page reported XOOPS Debug disabled -- the split-brain the
// policy class exists to prevent, surviving in the page that reports the state.
$xoopsDebugEnabled = 0 !== $debugMode;
$debugbarPreferenceEnabled = (bool) $helper->getConfig('debugbar_enable', 1);

// Second activation source. Read through AccessPolicy rather than xoops_getDebugConfig()
// directly, so this page can never disagree with the gate that actually renders the bar.
$debugEnabledByFile = AccessPolicy::fileEnablesDebugbar();

// What the toolbar will actually do, which is the question an administrator is asking.
$debugbarToolbarActive = ($xoopsDebugEnabled || $debugEnabledByFile) && $debugbarPreferenceEnabled;
$tracyControlAvailable = defined('XOOPS_TRACY_STATUS');
$tracyActive = $tracyControlAvailable && constant('XOOPS_TRACY_STATUS') === 'active';

$statusRows = [
    [_AM_DEBUGBAR_PHP_DEBUGBAR, $hasDebugbar ? _AM_DEBUGBAR_INSTALLED : _AM_DEBUGBAR_NOT_FOUND, $hasDebugbar ? 'green' : 'red'],
    [_AM_DEBUGBAR_MONOLOG, $monologActive ? _AM_DEBUGBAR_REGISTERED : ($hasMonolog ? _AM_DEBUGBAR_INSTALLED_INACTIVE : _AM_DEBUGBAR_NOT_FOUND), $monologActive ? 'green' : ($hasMonolog ? 'orange' : 'red')],
    [_AM_DEBUGBAR_PHP_VERSION,  PHP_VERSION, 'green'],
    [_AM_DEBUGBAR_ASSETS,       $assetsExist ? _AM_DEBUGBAR_COPIED : _AM_DEBUGBAR_NOT_COPIED,   $assetsExist ? 'green' : 'orange'],
    [_AM_DEBUGBAR_RAY,          $rayInstalled ? ($rayActivated ? _AM_DEBUGBAR_INSTALLED_ACTIVE : _AM_DEBUGBAR_INSTALLED_INACTIVE) : _AM_DEBUGBAR_NOT_INSTALLED, $rayInstalled ? ($rayActivated ? 'green' : 'orange') : 'gray'],
    // Names the SOURCE, not just the state. "Enabled" alone would leave an administrator
    // hunting through Preferences for a database value that is off, wondering why the bar
    // is still there.
    [
        _AM_DEBUGBAR_XOOPS_DEBUG,
        $xoopsDebugEnabled
            ? _AM_DEBUGBAR_ENABLED
            : ($debugEnabledByFile ? _AM_DEBUGBAR_XOOPS_DEBUG_BY_FILE : _AM_DEBUGBAR_DISABLED),
        ($xoopsDebugEnabled || $debugEnabledByFile) ? 'green' : 'orange',
    ],
    [_AM_DEBUGBAR_TOOLBAR, $debugbarToolbarActive ? _AM_DEBUGBAR_ENABLED : ($debugbarPreferenceEnabled ? _AM_DEBUGBAR_WAITING_FOR_XOOPS_DEBUG : _AM_DEBUGBAR_DISABLED), $debugbarToolbarActive ? 'green' : 'orange'],
];
// Always reported, like the Ray row above, rather than hidden when unavailable.
// Three distinct states matter to an administrator and "no row at all" tells
// them none of them: Tracy is running, Tracy is available but switched off, or
// this installation does not expose the bootstrap control (XOOPS_TRACY_STATUS is
// defined in mainfile.php, so 2.7.x installations generally do not have it).
// Silence read as "not supported" on the very line where it means "not present".
if ($tracyControlAvailable) {
    $statusRows[] = [_AM_DEBUGBAR_TRACY, $tracyActive ? _AM_DEBUGBAR_ENABLED : _AM_DEBUGBAR_DISABLED, $tracyActive ? 'green' : 'gray'];
} else {
    $statusRows[] = [_AM_DEBUGBAR_TRACY, _AM_DEBUGBAR_NOT_INSTALLED, 'gray'];
}

// Render as a single HTML table inside one info box line
$html = '<table style="border-collapse: collapse; width: auto;">';
// The colour lands inside a style attribute, where escaping would not save us —
// htmlspecialchars passes a CSS payload straight through. The XMF 2.0 line
// allowlists the value at runtime for that reason. Here it is unnecessary and
// deliberately absent: every row of $statusRows is built in this file from a
// closed set of literals, and analysis proves it, so the constraint is enforced
// statically instead. If this ever takes a colour from config or a caller, the
// allowlist goes back in — and analysis will stop proving the invariant, which
// is the signal to notice.
foreach ($statusRows as $row) {
    $label = \htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
    $value = \htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8');
    $color = $row[2];

    $html .= '<tr>'
        . '<td style="padding: 2px 20px 2px 0; white-space: nowrap;">' . $label . '</td>'
        . '<td style="padding: 2px 0; font-weight: bold; color: ' . $color . ';">' . $value . '</td>'
        . '</tr>';
}
$html .= '</table>';

$adminObject->addInfoBoxLine($html, 'information');

$adminObject->addInfoBox(_AM_DEBUGBAR_XOOPS_DEBUG_CONTROL);
$toggleHtml = '<p>' . htmlspecialchars(_AM_DEBUGBAR_XOOPS_DEBUG_DSC, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';

// Say so BEFORE offering the button. Without this the button is a lie: it writes the
// database value, the file still satisfies the OR, and the bar carries on exactly as
// before -- which reads as the button being broken rather than as the file winning.
if ($debugEnabledByFile) {
    $toggleHtml .= '<p><strong>'
        . htmlspecialchars(_AM_DEBUGBAR_XOOPS_DEBUG_BY_FILE_DSC, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></p>';
}

$toggleHtml .= '<form method="post" action="index.php">'
    . $GLOBALS['xoopsSecurity']->getTokenHTML('DEBUGBAR_XOOPS_DEBUG')
    . '<input type="hidden" name="action" value="set_xoops_debug">'
    . '<input type="hidden" name="enabled" value="' . ($xoopsDebugEnabled ? '0' : '1') . '">'
    . '<button class="formButton" type="submit">'
    . htmlspecialchars($xoopsDebugEnabled ? _AM_DEBUGBAR_XOOPS_DEBUG_TURN_OFF : _AM_DEBUGBAR_XOOPS_DEBUG_TURN_ON, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '</button></form>';
$adminObject->addInfoBoxLine(
    $toggleHtml,
    $xoopsDebugEnabled ? 'success' : ($debugEnabledByFile ? 'information' : 'warning')
);

$adminObject->addInfoBox(_AM_DEBUGBAR_TOOLBAR_CONTROL);
$toolbarHtml = '<p>' . htmlspecialchars(_AM_DEBUGBAR_TOOLBAR_DSC, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
if ($debugbarPreferenceEnabled && ! $xoopsDebugEnabled) {
    $toolbarHtml .= '<p><strong>'
        . htmlspecialchars(_AM_DEBUGBAR_TOOLBAR_BLOCKED, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</strong></p>';
}
$toolbarHtml .= '<form method="post" action="index.php">'
    . $GLOBALS['xoopsSecurity']->getTokenHTML('DEBUGBAR_TOOLBAR_TOGGLE')
    . '<input type="hidden" name="action" value="set_debugbar">'
    . '<input type="hidden" name="enabled" value="' . ($debugbarPreferenceEnabled ? '0' : '1') . '">'
    . '<button class="formButton" type="submit">'
    . htmlspecialchars($debugbarPreferenceEnabled ? _AM_DEBUGBAR_TOOLBAR_TURN_OFF : _AM_DEBUGBAR_TOOLBAR_TURN_ON, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '</button></form>';
$adminObject->addInfoBoxLine($toolbarHtml, $debugbarToolbarActive ? 'success' : 'warning');

if ($tracyControlAvailable) {
    $adminObject->addInfoBox(_AM_DEBUGBAR_TRACY_CONTROL);
    $tracyHtml = '<p>' . htmlspecialchars(_AM_DEBUGBAR_TRACY_DSC, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<form method="post" action="index.php">'
        . $GLOBALS['xoopsSecurity']->getTokenHTML('DEBUGBAR_TRACY_TOGGLE')
        . '<input type="hidden" name="action" value="set_tracy">'
        . '<input type="hidden" name="enabled" value="' . ($tracyActive ? '0' : '1') . '">'
        . '<button class="formButton" type="submit">'
        . htmlspecialchars($tracyActive ? _AM_DEBUGBAR_TRACY_TURN_OFF : _AM_DEBUGBAR_TRACY_TURN_ON, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</button></form>';
    $adminObject->addInfoBoxLine($tracyHtml, $tracyActive ? 'success' : 'information');
}

// --- InfoBox: Ray integration control ---
// Only shown when the Ray library is installed; the button then flips the
// ray_enable preference on or off (the same value as the module Preferences,
// reachable here in one click). Hidden entirely when Ray is not installed.
if ($rayInstalled) {
    $adminObject->addInfoBox(_AM_DEBUGBAR_RAY_CONTROL);
    $rayHtml = '<p>' . htmlspecialchars(_AM_DEBUGBAR_RAY_DSC, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
        . '<form method="post" action="index.php">'
        . $GLOBALS['xoopsSecurity']->getTokenHTML('DEBUGBAR_RAY_TOGGLE')
        . '<input type="hidden" name="action" value="set_ray">'
        . '<input type="hidden" name="enabled" value="' . ($rayActivated ? '0' : '1') . '">'
        . '<button class="formButton" type="submit">'
        . htmlspecialchars($rayActivated ? _AM_DEBUGBAR_RAY_TURN_OFF : _AM_DEBUGBAR_RAY_TURN_ON, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</button></form>';
    $adminObject->addInfoBoxLine($rayHtml, $rayActivated ? 'success' : 'information');
}

$adminObject->displayIndex();

require_once __DIR__ . '/admin_footer.php';
