<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Compatibility bridge to the existing preload config reader. */
final class DebugbarCoreConfig
{
    /** @return array<string, mixed> */
    public static function get(): array
    {
        $handler = xoops_getHandler('config');
        $modules = xoops_getHandler('module');
        if (!$handler || !$modules) return [];
        $module = $modules->getByDirname('debugbar');
        return is_object($module) ? (array) $handler->getConfigsByCat(0, $module->getVar('mid')) : [];
    }
}
