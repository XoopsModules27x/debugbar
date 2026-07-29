<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Collector;

/**
 * DebugBar Module - Preload Listener Timing Proxy
 *
 * Stands in for a preload class name so the listener call can be timed.
 *
 * @category  Module
 * @package   debugbar
 * @author    XOOPS Development Team
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @link      https://xoops.org
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Times one preload listener without changing what it does.
 *
 * `XoopsPreload::triggerEvent()` dispatches with
 * `call_user_func([$event['class_name'], $event['method']], $args)`. That value
 * is only ever used as the first element of a callable array, so replacing the
 * class-name string with an object is legal: PHP resolves the call through
 * __call() here, which forwards to the real static listener and reports how
 * long it took.
 *
 * @internal Handed out by PreloadEventSpy; not part of the module's API.
 */
final class EventListenerProxy
{
    public function __construct(
        private readonly PreloadEventSpy $spy,
        private readonly int $index,
        private readonly string $listener
    ) {
    }

    /**
     * Forward to the real listener and record its wall time.
     *
     * @param string       $method    listener method name
     * @param array<mixed> $arguments arguments core is dispatching with
     */
    public function __call(string $method, array $arguments): mixed
    {
        $callable = [$this->listener, $method];
        if (! is_callable($callable)) {
            // Unreachable in practice: XoopsPreload::setEvents() derives method
            // names from get_class_methods(), so the target always exists. Kept
            // so a malformed table fails loudly here instead of silently
            // dropping a listener the site depends on.
            throw new \BadMethodCallException(sprintf('Preload listener %s::%s is not callable', $this->listener, $method));
        }

        $start = microtime(true);

        try {
            return call_user_func($callable, ...$arguments);
        } finally {
            // `finally`, not a trailing statement: a listener that throws is
            // still a listener that spent time, and swallowing its exception
            // here would change behaviour the module is only meant to observe.
            $this->spy->addListenerTime($this->index, (microtime(true) - $start) * 1000);
        }
    }

    /** The listener this proxy stands in for. */
    public function listener(): string
    {
        return $this->listener;
    }
}
