<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Tests\Unit\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use XoopsModules\Debugbar\Collector\EventListenerProxy;
use XoopsModules\Debugbar\Collector\PreloadEventSpy;

/**
 * The proxy stands between core's preload dispatch and every listener on the
 * site, so its failure mode is the site's failure mode. R-014 (preloads/core.php)
 * states the rule it has to keep: a preload handler must never throw, because
 * XoopsPreload::triggerEvent() dispatches through a bare call_user_func() with no
 * try/catch — a fatal there blanks the front end and the admin together, leaving
 * nowhere to switch the module off.
 */
#[CoversClass(EventListenerProxy::class)]
final class EventListenerProxyTest extends TestCase
{
    public function testForwardsToTheRealListenerAndReturnsItsValue(): void
    {
        $proxy = new EventListenerProxy(new PreloadEventSpy(), 0, RecordingListener::class);

        // Dispatched the way XoopsPreload::triggerEvent() does it, through an array
        // callable — the exact shape that routes into __call() in production.
        self::assertSame('handled: ping', self::dispatch($proxy, 'eventSomething', 'ping'));
        self::assertSame(['ping'], RecordingListener::$calls);
    }

    /**
     * The regression: this used to throw BadMethodCallException. Nothing catches
     * it on the dispatch path, so an unknown method took the whole site down
     * rather than dropping one observation.
     */
    public function testUnknownMethodWarnsAndReturnsNullInsteadOfThrowing(): void
    {
        $proxy = new EventListenerProxy(new PreloadEventSpy(), 0, RecordingListener::class);

        $warnings = [];
        // Scoped handler: the warning IS the intended behaviour, and PHPUnit runs
        // with failOnWarning, so it is captured here rather than escaping.
        set_error_handler(
            static function (int $errno, string $message) use (&$warnings): bool {
                $warnings[] = $message;

                return true;
            },
            \E_USER_WARNING
        );

        try {
            $result = self::dispatch($proxy, 'noSuchListenerMethod', 'x');
        } finally {
            restore_error_handler();
        }

        self::assertNull($result, 'an unobservable listener must degrade to null, not a fatal');
        self::assertCount(1, $warnings);
        self::assertStringContainsString('noSuchListenerMethod', $warnings[0]);
        self::assertStringContainsString('not callable', $warnings[0]);
    }

    /**
     * A listener that throws is still the listener's exception, not the proxy's --
     * and it is still timed, which is the half that matters to a reader of the
     * panel. A throwing listener is usually the slow or broken one, so losing its
     * timing would lose the dispatch most worth seeing.
     *
     * expectException() would end the test at the throw and leave the spy
     * uninspected, so the exception is caught here instead: with expectException
     * this test passed even with addListenerTime() deleted from the proxy.
     */
    public function testAThrowingListenerStillPropagatesAndIsStillTimed(): void
    {
        // Built the way production reaches the proxy: the spy records the
        // dispatch in offsetGet() and hands back the listener list with
        // class_name replaced. Constructing the proxy directly with an index the
        // spy never recorded would make addListenerTime() a silent no-op, and
        // the timing assertion below vacuous.
        $spy = new PreloadEventSpy([
            'eventexplodes' => [['class_name' => RecordingListener::class, 'method' => 'eventExplodes']],
        ]);

        $listeners = $spy['eventexplodes'];
        self::assertIsArray($listeners);
        $proxy = $listeners[0]['class_name'];
        self::assertInstanceOf(EventListenerProxy::class, $proxy);

        // The proxy only observes; swallowing here would change behaviour core
        // already has. This is deliberately NOT the same case as the one above:
        // there the proxy invented the exception, here it is passing one on.
        $propagated = null;

        try {
            self::dispatch($proxy, 'eventExplodes');
        } catch (\RuntimeException $e) {
            $propagated = $e;
        }

        self::assertInstanceOf(\RuntimeException::class, $propagated, "the listener's exception must reach the caller");
        self::assertSame('listener failed', $propagated->getMessage(), 'and must reach it unchanged');

        $records = $spy->records();
        self::assertCount(1, $records);
        self::assertSame(1, $records[0]['listeners']);
        // The timing is taken in a finally, so it survives the throw. The
        // listener sleeps 1ms precisely so this can assert a real duration
        // rather than a value indistinguishable from the 0.0 it starts at.
        self::assertGreaterThan(0.0, $records[0]['ms'], 'a throwing listener must still be timed');
    }

    /**
     * Dispatch the way XoopsPreload::triggerEvent() does: an array callable whose
     * method name comes from the events table, never a literal in the caller.
     */
    private static function dispatch(EventListenerProxy $proxy, string $method, mixed ...$args): mixed
    {
        /** @var callable $callable */
        $callable = [$proxy, $method];

        return $callable(...$args);
    }
}

/** Stand-in for a preload listener class; core dispatches these statically. */
final class RecordingListener
{
    /** @var list<string> */
    public static array $calls = [];

    public static function eventSomething(string $arg): string
    {
        self::$calls = [$arg];

        return 'handled: ' . $arg;
    }

    public static function eventExplodes(): void
    {
        // Sleeps before throwing so the recorded duration is measurably above
        // the 0.0 a dispatch starts at -- otherwise "is still timed" could not
        // be told apart from "was never timed". 10ms rather than 1ms so the
        // assertion holds on a CI host with a coarse timer.
        usleep(10000);

        throw new \RuntimeException('listener failed');
    }
}
