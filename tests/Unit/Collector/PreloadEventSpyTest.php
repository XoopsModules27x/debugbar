<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Collector;

use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Collector\EventListenerProxy;
use XoopsModules\Debugbar\Collector\PreloadEventSpy;

if (! defined('XOOPS_ROOT_PATH')) {
    define('XOOPS_ROOT_PATH', dirname(__DIR__, 3));
}

/**
 * Listener double. XoopsPreload dispatches with
 * `call_user_func([$class_name, $method], $args)`, so listeners are static and
 * take a single array argument.
 */
final class SpyTestListener
{
    /** @var list<array<mixed>> */
    public static array $calls = [];

    /** @param array<mixed> $args */
    public static function eventSomething(array $args): void
    {
        self::$calls[] = $args;
    }

    /** @param array<mixed> $args */
    public static function eventThatThrows(array $args): void
    {
        throw new \RuntimeException('listener blew up');
    }
}

/**
 * The spy stands in for a core singleton's public state, so these tests
 * exercise the substitution the way XoopsPreload::triggerEvent() actually uses
 * it — `isset($events[$name])` followed by a foreach over `$events[$name]` —
 * rather than calling the methods directly.
 */
final class PreloadEventSpyTest extends TestCase
{
    protected function setUp(): void
    {
        SpyTestListener::$calls = [];
    }

    /**
     * Simulate one `XoopsPreload::triggerEvent()` against the spy, including
     * core's name normalisation.
     *
     * @param array<mixed> $args
     */
    private function trigger(PreloadEventSpy $spy, string $eventName, array $args = []): void
    {
        $key = strtolower(str_replace('.', '', $eventName));
        if (isset($spy[$key])) {
            foreach ($spy[$key] as $event) {
                $callable = [$event['class_name'], $event['method']];
                self::assertIsCallable($callable);
                call_user_func($callable, $args);
            }
        }
    }

    public function testRecordsEventsThatNobodyListensTo(): void
    {
        // The whole reason for the ArrayObject: on a stock site most events
        // have no listeners, and "the event never fired" is indistinguishable
        // from "my hook never ran" unless the unlistened ones are reported.
        $spy = new PreloadEventSpy([]);

        $this->trigger($spy, 'core.header.checkcache');

        $records = $spy->records();
        self::assertCount(1, $records);
        self::assertSame('core.header.checkcache', $records[0]['name']);
        self::assertSame(0, $records[0]['listeners']);
    }

    public function testTranslatesNormalisedNamesBackToDottedForm(): void
    {
        $spy = new PreloadEventSpy([]);

        $this->trigger($spy, 'core.include.common.start');

        self::assertSame('core.include.common.start', $spy->records()[0]['name']);
    }

    public function testReportsUnknownEventsUnderTheirNormalisedName(): void
    {
        // Core strips the dots before we ever see the name, so a third-party
        // event we have no mapping for can only be reported as-received.
        $spy = new PreloadEventSpy([]);

        $this->trigger($spy, 'somemodule.custom.thing');

        self::assertSame('somemodulecustomthing', $spy->records()[0]['name']);
    }

    public function testListenersStillRunAndAreCountedAndTimed(): void
    {
        $spy = new PreloadEventSpy([
            'coreheaderstart' => [
                ['class_name' => SpyTestListener::class, 'method' => 'eventSomething'],
            ],
        ]);

        $this->trigger($spy, 'core.header.start', ['payload']);

        self::assertSame([['payload']], SpyTestListener::$calls, 'the real listener must still run, with its arguments');

        $records = $spy->records();
        self::assertCount(1, $records);
        self::assertSame('core.header.start', $records[0]['name']);
        self::assertSame(1, $records[0]['listeners']);
        self::assertGreaterThanOrEqual(0.0, $records[0]['ms']);
    }

    public function testListenerListIsHandedBackWrappedInAProxy(): void
    {
        $spy = new PreloadEventSpy([
            'coreheaderstart' => [
                ['class_name' => SpyTestListener::class, 'method' => 'eventSomething'],
            ],
        ]);

        $listeners = $spy['coreheaderstart'];

        self::assertIsArray($listeners);
        self::assertInstanceOf(EventListenerProxy::class, $listeners[0]['class_name']);
        self::assertSame(SpyTestListener::class, $listeners[0]['class_name']->listener());
        self::assertSame('eventSomething', $listeners[0]['method']);
    }

    public function testAThrowingListenerStillPropagatesAndIsStillTimed(): void
    {
        // The spy observes; it must not turn a fatal listener into a silent one.
        $spy = new PreloadEventSpy([
            'coreheaderstart' => [
                ['class_name' => SpyTestListener::class, 'method' => 'eventThatThrows'],
            ],
        ]);

        try {
            $this->trigger($spy, 'core.header.start');
            self::fail('the listener exception should have propagated');
        } catch (\RuntimeException $e) {
            self::assertSame('listener blew up', $e->getMessage());
        }

        self::assertCount(1, $spy->records());
        self::assertGreaterThanOrEqual(0.0, $spy->records()[0]['ms']);
    }

    public function testMalformedListenerEntriesArePassedThroughUntouched(): void
    {
        // A table we do not understand must still dispatch exactly as core would.
        $spy = new PreloadEventSpy([
            'coreheaderstart' => [
                ['not_a_listener' => true],
            ],
        ]);

        $listeners = $spy['coreheaderstart'];

        self::assertIsArray($listeners);
        self::assertSame(['not_a_listener' => true], $listeners[0]);
    }

    public function testStopsRecordingAtTheCapAndCountsTheRest(): void
    {
        $spy = new PreloadEventSpy([]);

        for ($i = 0; $i < 310; $i++) {
            $this->trigger($spy, 'core.event.number' . $i);
        }

        self::assertCount(300, $spy->records(), 'recording must stop at the cap');
        self::assertSame(10, $spy->dropped(), 'dispatches past the cap must still be counted');
    }

    public function testPastTheCapListenersAreHandedBackUnwrapped(): void
    {
        // Past the cap the spy must cost nothing, which means no proxy objects.
        $spy = new PreloadEventSpy([
            'coreheaderstart' => [
                ['class_name' => SpyTestListener::class, 'method' => 'eventSomething'],
            ],
        ]);
        for ($i = 0; $i < 300; $i++) {
            $this->trigger($spy, 'core.filler.number' . $i);
        }

        $listeners = $spy['coreheaderstart'];

        self::assertIsArray($listeners);
        self::assertSame(SpyTestListener::class, $listeners[0]['class_name'], 'no proxy should be allocated past the cap');
    }

    public function testNestedDispatchesAttributeTimeToTheRightEvent(): void
    {
        // A listener may trigger another event, so timing cannot be tracked
        // with a single "current event" field.
        $spy = new PreloadEventSpy([
            'coreheaderstart' => [
                ['class_name' => SpyTestListener::class, 'method' => 'eventSomething'],
            ],
            'corefooterstart' => [
                ['class_name' => SpyTestListener::class, 'method' => 'eventSomething'],
            ],
        ]);

        $key = 'coreheaderstart';
        if (isset($spy[$key])) {
            foreach ($spy[$key] as $event) {
                // Inner dispatch, while the outer one is still in flight.
                $this->trigger($spy, 'core.footer.start');
                $callable = [$event['class_name'], $event['method']];
                self::assertIsCallable($callable);
                call_user_func($callable, []);
            }
        }

        $records = $spy->records();
        self::assertCount(2, $records);
        self::assertSame('core.header.start', $records[0]['name']);
        self::assertSame('core.footer.start', $records[1]['name']);
        self::assertSame(1, $records[0]['listeners']);
        self::assertSame(1, $records[1]['listeners']);
    }

    public function testWritesThroughToTheUnderlyingTable(): void
    {
        // Core never appends after construction, but if anything ever did, the
        // spy must behave like the array it replaced.
        $spy = new PreloadEventSpy([]);
        $spy['corenewevent'] = [['class_name' => SpyTestListener::class, 'method' => 'eventSomething']];

        self::assertTrue(isset($spy['corenewevent']));
        self::assertArrayHasKey('corenewevent', $spy->getArrayCopy());
    }
}
