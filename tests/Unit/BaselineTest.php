<?php

declare(strict_types=1);

namespace XoopsModules\Yourmodule\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Baseline smoke test.
 *
 * Proves the DevOps baseline passes its own quality gate on an empty module:
 * Composer autoload resolves and PHPUnit executes at least one test (so `composer qa`
 * exits 0 before any real code exists). Also exercises the RequiresXoops integration
 * helper. Adopters replace/extend this with real unit tests in tests/Unit/.
 */
final class BaselineTest extends TestCase
{
    use \RequiresXoops;

    public function testComposerAutoloadResolves(): void
    {
        self::assertTrue(class_exists(TestCase::class));
    }

    public function testRequiresXoopsSkipsWithoutRuntime(): void
    {
        // Self-skips in unit-only mode; only reached when a XOOPS runtime is booted.
        $this->requiresXoops();

        self::assertTrue(function_exists('xoops_load'));
    }
}
