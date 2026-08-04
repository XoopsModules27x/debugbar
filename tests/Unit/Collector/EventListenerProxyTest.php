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

    /** A listener that throws is still the listener's exception, not the proxy's. */
    public function testAThrowingListenerStillPropagatesAndIsStillTimed(): void
    {
        $spy = new PreloadEventSpy();
        $proxy = new EventListenerProxy($spy, 0, RecordingListener::class);

        // The proxy only observes; swallowing here would change behaviour core
        // already has. This is deliberately NOT the same case as the one above:
        // there the proxy invented the exception, here it is passing one on.
        $this->expectException(\RuntimeException::class);
        self::dispatch($proxy, 'eventExplodes');
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
        throw new \RuntimeException('listener failed');
    }
}
