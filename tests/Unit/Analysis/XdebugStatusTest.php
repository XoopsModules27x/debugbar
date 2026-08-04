<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Analysis\XdebugStatus;

/**
 * evaluate() is the whole decision surface of the Xdebug panel: the UI offers
 * or withholds the arm button purely on what it returns, so each flag is
 * asserted against the state that produces it rather than through the panel.
 */
#[CoversClass(XdebugStatus::class)]
final class XdebugStatusTest extends TestCase
{
    /** The happy path — everything present, so every capability is available. */
    public function testFullyConfiguredProfilerCanDoEverything(): void
    {
        $status = self::evaluate('/var/xdebug', true, true);

        self::assertSame('ok', $status['output_dir_state']);
        self::assertTrue($status['can_trigger']);
        self::assertTrue($status['can_list']);
        self::assertTrue($status['can_parse']);
    }

    /**
     * @return iterable<string, array{string, bool, bool, string}>
     */
    public static function unusableOutputDirs(): iterable
    {
        yield 'unconfigured' => ['', false, false, 'unconfigured'];
        yield 'missing' => ['/var/xdebug', false, false, 'missing'];
        yield 'unreadable' => ['/var/xdebug', true, false, 'unreadable'];
    }

    /**
     * The regression this class was fixed for: a triggered run writes its
     * cachegrind file into the output directory, so offering the trigger while
     * that directory is unusable promises a run that cannot produce anything.
     */
    #[DataProvider('unusableOutputDirs')]
    public function testTriggerIsWithheldWhenTheOutputDirCannotBeUsed(
        string $dir,
        bool $exists,
        bool $readable,
        string $expectedState
    ): void {
        $status = self::evaluate($dir, $exists, $readable);

        self::assertSame($expectedState, $status['output_dir_state']);
        self::assertFalse(
            $status['can_trigger'],
            'trigger must not be offered with output_dir_state=' . $expectedState
        );
        self::assertFalse($status['can_list']);
        self::assertFalse($status['can_parse']);
    }

    /** The other three terms still each independently withhold the trigger. */
    public function testEachPrerequisiteIndependentlyWithholdsTrigger(): void
    {
        self::assertFalse(
            XdebugStatus::evaluate(false, ['profile'], 'trigger', '/var/xdebug', true, true, true, '/tmp')['can_trigger'],
            'extension not loaded'
        );
        self::assertFalse(
            XdebugStatus::evaluate(true, ['develop'], 'trigger', '/var/xdebug', true, true, true, '/tmp')['can_trigger'],
            'profile mode not active'
        );
        self::assertFalse(
            XdebugStatus::evaluate(true, ['profile'], 'yes', '/var/xdebug', true, true, true, '/tmp')['can_trigger'],
            'start_with_request is not trigger'
        );
    }

    /** A profile directory shared with the system temp dir is worth warning about. */
    public function testSharedTempDirIsFlaggedOnlyWhenTheDirIsUsable(): void
    {
        $shared = XdebugStatus::evaluate(true, ['profile'], 'trigger', '/tmp', true, true, true, '/tmp');
        self::assertTrue($shared['shared_dir_warning']);

        $separate = self::evaluate('/var/xdebug', true, true);
        self::assertFalse($separate['shared_dir_warning']);

        // Not "ok" means the comparison is not meaningful yet.
        $missing = self::evaluate('/tmp', false, false);
        self::assertFalse($missing['shared_dir_warning']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function evaluate(string $outputDir, bool $dirExists, bool $dirReadable): array
    {
        return XdebugStatus::evaluate(
            true,
            ['profile'],
            'trigger',
            $outputDir,
            $dirExists,
            $dirReadable,
            true,
            '/tmp'
        );
    }
}
