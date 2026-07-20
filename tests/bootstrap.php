<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap — XOOPS module DevOps baseline.
 *
 * Two run modes, decided automatically:
 *   1. Integration: a *configured* XOOPS is reachable (XOOPS_ROOT_PATH points at a
 *      tree that actually has a mainfile.php). We boot it so handlers, Criteria and
 *      core classes are live. XOOPS_OVERLAY_INTEGRATION is then true.
 *   2. Unit-only: no bootable XOOPS (fresh CI clone has only mainfile.dist.php and
 *      no DB). We DO NOT require the core — that would fatal — and just rely on the
 *      module's Composer autoload. XOOPS_OVERLAY_INTEGRATION is false; integration
 *      tests self-skip via the RequiresXoops trait.
 *
 * The guard is the difference from naive bootstraps: we only require the core when
 * mainfile.php exists, because a bare XoopsCore clone ships mainfile.dist.php and
 * booting it without a configured database is a fatal error, not a skippable one.
 *
 * xoops-overlay:profile=core27
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers/RequiresXoops.php';

$xoopsRoot = getenv('XOOPS_ROOT_PATH') ?: '';
$integration = false;

if ($xoopsRoot !== '' && is_dir($xoopsRoot)) {
    // A configured XOOPS has a real mainfile.php (NOT just mainfile.dist.php).
    $mainfile = $xoopsRoot . '/mainfile.php';

    if (is_file($mainfile)) {
        if (! defined('XOOPS_ROOT_PATH')) {
            define('XOOPS_ROOT_PATH', $xoopsRoot);
        }
        $trust = getenv('XOOPS_TRUST_PATH') ?: $xoopsRoot;
        if (! defined('XOOPS_TRUST_PATH')) {
            define('XOOPS_TRUST_PATH', $trust);
        }

        try {
            // @phpstan-ignore-next-line — runtime-only file outside analysis scope.
            require_once $mainfile;
            $integration = true;
        } catch (\Throwable $e) {
            // Misconfigured core (e.g. no DB) — fall back to unit-only rather than
            // failing the whole suite.
            fwrite(STDERR, "XOOPS present but not bootable ({$e->getMessage()}); running unit-only.\n");
            $integration = false;
        }
    } else {
        fwrite(STDERR, "XOOPS_ROOT_PATH set but no mainfile.php (unconfigured clone); running unit-only.\n");
    }
}

/**
 * Single source of truth for "are integration tests possible in this run?".
 * Read by the RequiresXoops trait.
 */
if (! defined('XOOPS_OVERLAY_INTEGRATION')) {
    define('XOOPS_OVERLAY_INTEGRATION', $integration);
}
