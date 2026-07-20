<?php

declare(strict_types=1);

if (defined('XOOPS_ROOT_PATH')) {
    /** @see https://www.php-fig.org/psr/psr-4/examples/ */
    spl_autoload_register(
        static function (string $class): void {
            $prefix = 'XoopsModules\\' . ucfirst(basename(dirname(__DIR__)));
            $baseDir = dirname(__DIR__) . '/class/';
            $prefixLength = strlen($prefix);

            if (strncmp($prefix, $class, $prefixLength) !== 0) {
                return;
            }

            $relativeClass = ltrim(substr($class, $prefixLength), '\\');
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        }
    );
} else {
    http_response_code(404);
    exit('Restricted access');
}
