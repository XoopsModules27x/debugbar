<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\FlightRecorder;

/**
 * The retention cap is the only bound on the flight-recorder directory, and
 * record() reports success to a caller that has no other way to learn the cap
 * was not applied. These tests exist because it was possible for both to be
 * true at once: 31 records surviving a cap of 30, every call returning true.
 */
#[CoversClass(FlightRecorder::class)]
final class FlightRecorderTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/debugbar-flight-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->dir . '/*') as $path) {
            if (is_string($path)) {
                is_dir($path) ? @rmdir($path) : @unlink($path);
            }
        }
        @rmdir($this->dir);
    }

    public function testThePruneKeepsTheDirectoryAtTheCap(): void
    {
        $recorder = new FlightRecorder($this->dir);

        for ($i = 0; $i < 31; $i++) {
            self::assertTrue($recorder->record($this->requestId($i), ['n' => $i], false, 30));
        }

        self::assertCount(30, $recorder->listRecords(PHP_INT_MAX));
    }

    /**
     * Violations outlive plain records when the cap bites: prune() sorts on
     * violation first, so the ordinary records go before any flagged one does.
     */
    public function testViolationsSurviveThePruneBeforePlainRecordsDo(): void
    {
        $recorder = new FlightRecorder($this->dir);

        self::assertTrue($recorder->record($this->requestId(0), ['n' => 0], true, 3));
        for ($i = 1; $i < 6; $i++) {
            self::assertTrue($recorder->record($this->requestId($i), ['n' => $i], false, 3));
        }

        $records = $recorder->listRecords(PHP_INT_MAX);
        self::assertCount(3, $records);
        self::assertContains(true, array_column($records, 'violation'), 'the flagged record must not be pruned first');
    }

    /**
     * The regression. A record that cannot be removed used to be indistinguishable
     * from one that was: removeFile() returned void, prune() returned void, and
     * record() returned true regardless -- so the cap silently became advisory.
     *
     * A directory standing at a record's path is the portable way to produce an
     * un-removable record. It fails identically on Windows and Linux, on every
     * supported PHP version, and needs no permission changes -- which matters
     * because chmod is a no-op on Windows and a no-op again for a root CI user.
     * It is also the exact shape the old code was blind to: the is_file() guard
     * skipped it without ever calling unlink(), which is why removeFile() now
     * confirms the path is gone rather than trusting a return value.
     *
     * The cap is still met: prune() walks past a failed removal to the next
     * candidate rather than giving up on the initial overflow set, so one stuck
     * entry costs one other record rather than the whole guarantee.
     */
    public function testAnUnremovableRecordDoesNotStopTheCapBeingEnforced(): void
    {
        $stuck = $this->dir . '/0000000001-r-' . str_repeat('a', 16) . '.json';
        mkdir($stuck);

        $recorder = new FlightRecorder($this->dir);
        for ($i = 0; $i < 30; $i++) {
            self::assertTrue($recorder->record($this->requestId($i), ['n' => $i], false, 30));
        }

        // record() still reports success, and should: the record it was asked to
        // write was written.
        self::assertDirectoryExists($stuck, 'the un-removable entry is still there');
        self::assertCount(30, $recorder->listRecords(PHP_INT_MAX), 'the cap is met by removing the next candidate');
    }

    public function testAMalformedRequestIdIsRefusedBeforeAnythingIsWritten(): void
    {
        $recorder = new FlightRecorder($this->dir);

        self::assertFalse($recorder->record('not-a-request-id', ['n' => 1], false, 30));
        self::assertFalse($recorder->record(strtoupper($this->requestId(0)), ['n' => 1], false, 30));
        self::assertSame([], $recorder->listRecords(PHP_INT_MAX));
    }

    public function testLoadRefusesAPathThatIsNotARecordName(): void
    {
        $recorder = new FlightRecorder($this->dir);
        $recorder->record($this->requestId(0), ['marker' => 'kept'], false, 30);

        self::assertNull($recorder->load('../../mainfile.php'));
        self::assertNull($recorder->load('anything.json'));

        $records = $recorder->listRecords(PHP_INT_MAX);
        self::assertCount(1, $records);
        self::assertSame(['marker' => 'kept'], $recorder->load($records[0]['file']));
    }

    /** Request ids are 16 lowercase hex characters; record() refuses anything else. */
    private function requestId(int $seed): string
    {
        return substr(md5((string) $seed), 0, 16);
    }
}
