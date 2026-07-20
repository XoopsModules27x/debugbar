<?php

declare(strict_types=1);

/**
 * Rector configuration — XOOPS module DevOps baseline.
 *
 * Generated from the baseline template for profile: XoopsCore27 / PHP 8.2+
 * Overlay version: 1.1.0  (do not edit the marker line below)
 * xoops-overlay:profile=core27
 *
 * @see https://getrector.com/documentation/config-configuration
 *
 * @copyright 2002-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2.0 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 */

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Xoops\Rector\Set\XoopsSetList;

// Only scan paths that actually exist in this module — a legacy module may have
// `class/` but no `src/`, a modern one the reverse. Filter at runtime so Rector
// never errors on a missing directory.
$paths = array_filter([
    __DIR__ . '/admin',
    __DIR__ . '/blocks',
    __DIR__ . '/class',
    __DIR__ . '/include',
    __DIR__ . '/preloads',
    __DIR__ . '/src',
    __DIR__ . '/tests',
], is_dir(...));

return RectorConfig::configure()
    ->withCache(__DIR__ . '/.build/rector')
    ->withPaths($paths ?: [__DIR__])
    ->withRootFiles()
    // Pin the language level for this profile so refactors never overshoot the supported runtime.
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    // XOOPS-specific modernisation (DB query/exec split, removed PHP funcs, MyTextSanitizer +
    // Smarty PHP-API renames, …) from xoops/rector-xoops. Behaviour-preserving; also add
    // XoopsSetList::XOOPS_RISKY if you will review every diff (input filtering / escaping rewrites).
    ->withSets([XoopsSetList::XOOPS])
    // XOOPS legacy globals & dynamic handler patterns generate noise; skip what we cannot safely change.
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/language',
        __DIR__ . '/templates',
        // Smarty-compiled or generated files, if any.
        '*/templates_c/*',
    ]);
