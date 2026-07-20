<?php

declare(strict_types=1);

/**
 * Print the module's XOOPS "dirname" so CI can place it at modules/<dirname>.
 *
 * Resolution order:
 *   1. $modversion['dirname'] in xoops_version.php
 *   2. "dirname" key in module.json
 *   3. the repository folder name
 *
 * xoops-overlay:profile=core27
 */

$root = dirname(__DIR__, 2);

// 1. xoops_version.php
$versionFile = $root . '/xoops_version.php';
if (is_file($versionFile)) {
    $src = (string) file_get_contents($versionFile);
    if (1 === preg_match('/[\'"]dirname[\'"]\s*\]\s*=\s*[\'"]([^\'"]+)[\'"]/', $src, $m)) {
        echo $m[1];
        exit(0);
    }
}

// 2. module.json
$moduleJson = $root . '/module.json';
if (is_file($moduleJson)) {
    $data = json_decode((string) file_get_contents($moduleJson), true);
    if (is_array($data) && isset($data['dirname']) && '' !== $data['dirname']) {
        echo (string) $data['dirname'];
        exit(0);
    }
}

// 3. folder name
echo basename($root);
exit(0);
