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

// Profile target: XoopsCore27 / PHP 8.2+
