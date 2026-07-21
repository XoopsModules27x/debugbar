<?php
declare(strict_types=1);

/**
 * DebugBar Module - Admin Menu
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             debugbar
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');
use Xmf\Module\Admin;
use XoopsModules\Debugbar\{
    Helper
};

/** @var Admin $adminObject */
/** @var \XoopsModules\Debugbar\Helper $helper */

include_once dirname(__DIR__) . '/preloads/autoloader.php';

$moduleDirName = \basename(\dirname(__DIR__));
$moduleDirNameUpper = mb_strtoupper($moduleDirName);

$helper = Helper::getInstance();

$pathIcon32 = Admin::menuIconPath('');
$pathModIcon32 = XOOPS_URL . '/modules/' . $moduleDirName . '/assets/images/icons/32/';
$module = $helper->getModule();
if ($module instanceof \XoopsModule && false !== $module->getInfo('modicons32')) {
    $pathModIcon32 = $helper->url((string) $module->getInfo('modicons32'));
}

$adminmenu = [];

$adminmenu[] = [
    'title' => _MI_DEBUGBAR_ADMENU1,
    'link' => 'admin/index.php',
    'icon' => $pathIcon32 . '/home.png',
];

$adminmenu[] = [
    'title' => _MI_DEBUGBAR_MENU_ANALYTICS,
    'link' => 'admin/analytics.php',
    'icon' => $pathIcon32 . '/stats.png',
];

$adminmenu[] = [
    'title' => _MI_DEBUGBAR_MENU_LOGS,
    'link' => 'admin/logs.php',
    'icon' => $pathIcon32 . '/compfile.png',
];

$adminmenu[] = [
    'title' => _MI_DEBUGBAR_MENU_DIAGNOSTICS,
    'link' => 'admin/diagnostics.php',
    'icon' => $pathIcon32 . '/search.png',
];

$adminmenu[] = [
    'title' => _MI_DEBUGBAR_MENU_ABOUT,
    'link' => 'admin/about.php',
    'icon' => $pathIcon32 . '/about.png',
];
