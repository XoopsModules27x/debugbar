<?php

declare(strict_types=1);

/**
 * PHPStan bootstrap — XOOPS module DevOps baseline.
 *
 * Defines the legacy XOOPS constants/globals that static analysis needs to "see"
 * but that only exist at runtime inside a booted XOOPS instance.
 *
 * xoops-overlay:profile=core27
 */

// Common XOOPS path constants referenced by modules at analysis time.
if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', __DIR__);
}
if (! defined('XOOPS_TRUST_PATH')) {
    define('XOOPS_TRUST_PATH', __DIR__);
}
if (! defined('XOOPS_URL')) {
    define('XOOPS_URL', 'https://localhost');
}
if (! defined('_CHARSET')) {
    define('_CHARSET', 'utf-8');
}
if (! defined('XOOPS_CONF')) {
    define('XOOPS_CONF', 1);
}
if (! defined('_AM_MODULEADMIN_ADMIN_FOOTER')) {
    define('_AM_MODULEADMIN_ADMIN_FOOTER', 'Module administration');
}

foreach (['modinfo.php', 'admin.php', 'main.php'] as $languageFile) {
    require_once __DIR__ . '/language/english/' . $languageFile;
}

// Profile target: XoopsCore27 / PHP 8.2+
