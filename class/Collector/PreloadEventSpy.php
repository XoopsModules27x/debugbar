<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Collector;

/**
 * DebugBar Module - Preload Event Spy
 *
 * Observes every event XOOPS dispatches, including the ones nothing listens
 * to, by standing in for XoopsPreload's event table.
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
 * Stand-in for XoopsPreload::$_events.
 *
 * `XoopsPreload::triggerEvent()` asks `isset($this->_events[$name])` before it
 * dispatches, and only reads the listener list when that answers true. An
 * ArrayObject routes `isset()` to offsetExists(), so substituting one for the
 * plain array makes *every* dispatch observable — including events with no
 * listeners at all, which on a stock site is most of them. Watching the
 * listeners instead would see only the minority that someone hooked, which is
 * the opposite of what a webmaster asking "did my hook fire?" needs.
 *
 * Listener lists are handed back with each `class_name` replaced by a timing
 * proxy. Core only ever uses that value as the first element of a callable
 * array, so an object resolves through EventListenerProxy::__call() and the
 * real listener still runs, timed.
 *
 * The value type is deliberately loose. Core declares `$_events` untyped and
 * builds it reflectively, so the spy validates each entry's shape at runtime
 * rather than assuming it; claiming a strict shape here would contradict that
 * defence and make a malformed table impossible to test.
 *
 * @extends \ArrayObject<string, list<array<string, mixed>>>
 */
final class PreloadEventSpy extends \ArrayObject
{
    /**
     * Hard cap on recorded dispatches. Reached only by a page that loops over
     * event-triggering code; past it recording stops and listeners are handed
     * back unwrapped, so the spy costs nothing more for the rest of the request.
     */
    private const RECORD_CAP = 300;

    /**
     * Dotted names for the core events, keyed by the form triggerEvent()
     * normalises to (lowercased, dots stripped). Anything not listed here —
     * a third-party module's own event — is reported under its normalised
     * name, which is all core preserves by the time we see it.
     */
    private const KNOWN_EVENTS = [
        'corebehaviorcaptcharesult' => 'core.behavior.captcha.result',
        'corebehaviorsessionstart' => 'core.behavior.session.start',
        'corebehavioruseractivate' => 'core.behavior.user.activate',
        'corebehavioruserlogin' => 'core.behavior.user.login',
        'corebehavioruserlogout' => 'core.behavior.user.logout',
        'coreclassdatabasedatabasefactoryconnection' => 'core.class.database.databasefactory.connection',
        'coreclasssmartyxoops_pluginsxoinboxcount' => 'core.class.smarty.xoops_plugins.xoinboxcount',
        'coreclasstemplatenew' => 'core.class.template.new',
        'coreclasstheme_blocksretrieveblocks' => 'core.class.theme_blocks.retrieveBlocks',
        'coreclassxoopsformformdhtmltextareacodeicon' => 'core.class.xoopsform.formdhtmltextarea.codeicon',
        'coreedituserstart' => 'core.edituser.start',
        'corefooterend' => 'core.footer.end',
        'corefooterstart' => 'core.footer.start',
        'coreheaderaddmeta' => 'core.header.addmeta',
        'coreheadercacheend' => 'core.header.cacheend',
        'coreheadercheckcache' => 'core.header.checkcache',
        'coreheaderend' => 'core.header.end',
        'coreheaderstart' => 'core.header.start',
        'coreincludecommonauthsuccess' => 'core.include.common.auth.success',
        'coreincludecommonend' => 'core.include.common.end',
        'coreincludecommonlanguage' => 'core.include.common.language',
        'coreincludecommonstart' => 'core.include.common.start',
        'coreincludefunctionsredirectheader' => 'core.include.functions.redirectheader',
        'coreincludefunctionsredirectheaderstart' => 'core.include.functions.redirectheader.start',
        'coreindexstart' => 'core.index.start',
        'corelostpassstart' => 'core.lostpass.start',
        'corepmlitestart' => 'core.pmlite.start',
        'corereadpmsgstart' => 'core.readpmsg.start',
        'coreregisterstart' => 'core.register.start',
        'coreuserstart' => 'core.user.start',
        'coreuserinfostart' => 'core.userinfo.start',
        'coreviewpmsgstart' => 'core.viewpmsg.start',
        'debuglog' => 'debug.log',
        'systemblockssystem_blocksusershow' => 'system.blocks.system_blocks.usershow',
        'systemclassguiheader' => 'system.class.gui.header',
    ];

    /** @var list<array{name: string, listeners: int, ms: float}> */
    private array $records = [];

    private int $dropped = 0;

    /**
     * Substitute a spy for the live event table.
     *
     * Returns null rather than throwing if anything about the table is not
     * what we expect: this runs on every request, before an admin could
     * disable the module, so it must never be the reason a site goes down.
     */
    public static function install(\XoopsPreload $preload): ?self
    {
        try {
            $events = $preload->_events;
            if (! is_array($events)) {
                // Already swapped, or core changed shape. Leave it alone.
                return null;
            }
            $spy = new self($events);
            $preload->_events = $spy;

            return $spy;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Put the plain array back, discarding the spy. */
    public function uninstall(\XoopsPreload $preload): void
    {
        try {
            if ($preload->_events === $this) {
                $preload->_events = $this->getArrayCopy();
            }
        } catch (\Throwable) {
            // Restoring is best-effort; the spy is harmless if it stays.
        }
    }

    /**
     * Every dispatch passes through here, whether or not anyone is listening.
     * Only the unlistened ones are recorded here — offsetGet() records the rest,
     * where the listener count is known.
     */
    public function offsetExists(mixed $key): bool
    {
        $exists = parent::offsetExists($key);
        if (! $exists) {
            $this->record((string) $key, 0);
        }

        return $exists;
    }

    /**
     * Hand back the listener list with each listener wrapped so it can be timed.
     *
     * @param mixed $key event name
     *
     * @return list<array<string, mixed>>|null the listener list, wrapped where possible
     */
    public function offsetGet(mixed $key): mixed
    {
        $listeners = parent::offsetGet($key);
        if (! is_array($listeners) || [] === $listeners) {
            return $listeners;
        }

        $index = $this->record((string) $key, count($listeners));
        if (null === $index) {
            // Past the cap: hand back the real list untouched.
            return $listeners;
        }

        $wrapped = [];
        foreach ($listeners as $listener) {
            if (! is_array($listener) || ! isset($listener['class_name'], $listener['method']) || ! is_string($listener['class_name'])) {
                $wrapped[] = $listener;

                continue;
            }
            $wrapped[] = [
                'class_name' => new EventListenerProxy($this, $index, $listener['class_name']),
                'method' => $listener['method'],
            ];
        }

        return $wrapped;
    }

    /** Accumulate one listener's wall time onto its dispatch. */
    public function addListenerTime(int $index, float $milliseconds): void
    {
        if (isset($this->records[$index])) {
            $this->records[$index]['ms'] += $milliseconds;
        }
    }

    /**
     * Everything observed this request, in dispatch order.
     *
     * @return list<array{name: string, listeners: int, ms: float}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /** How many dispatches went unrecorded because the cap was reached. */
    public function dropped(): int
    {
        return $this->dropped;
    }

    /** Record a dispatch; returns its index, or null once the cap is reached. */
    private function record(string $key, int $listeners): ?int
    {
        if (count($this->records) >= self::RECORD_CAP) {
            $this->dropped++;

            return null;
        }
        $this->records[] = [
            'name' => self::KNOWN_EVENTS[$key] ?? $key,
            'listeners' => $listeners,
            'ms' => 0.0,
        ];

        return count($this->records) - 1;
    }
}
