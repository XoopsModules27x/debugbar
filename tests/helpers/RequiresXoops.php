<?php

declare(strict_types=1);

/**
 * RequiresXoops — XOOPS module DevOps baseline.
 *
 * Mix into any PHPUnit test case whose assertions need a live, booted XOOPS
 * runtime (handlers, Criteria queries, core globals). Call requiresXoops() at the
 * top of such a test (or in setUp) and it self-skips when the suite is running in
 * unit-only mode — see tests/bootstrap.php, which defines XOOPS_OVERLAY_INTEGRATION.
 *
 * Defined in the global namespace and required by tests/bootstrap.php so it is
 * available without any PSR-4 mapping. In a namespaced test, reference it with the
 * leading backslash: `use \RequiresXoops;`.
 *
 * Example:
 *     final class HandlerTest extends \PHPUnit\Framework\TestCase
 *     {
 *         use \RequiresXoops;
 *
 *         protected function setUp(): void
 *         {
 *             $this->requiresXoops();
 *         }
 *     }
 *
 * xoops-overlay:profile=core27
 */
trait RequiresXoops
{
    /**
     * Skip the current test unless a bootable XOOPS runtime is available.
     */
    protected function requiresXoops(): void
    {
        if (! defined('XOOPS_OVERLAY_INTEGRATION') || true !== XOOPS_OVERLAY_INTEGRATION) {
            static::markTestSkipped('No bootable XOOPS runtime; skipping integration test.');
        }
    }
}
