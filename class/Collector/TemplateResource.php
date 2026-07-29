<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Collector;

/**
 * DebugBar Module - Observing Template Resource
 *
 * Wraps the core `db:` Smarty resource so every template render is recorded
 * with the source that served it.
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
 * The core `db:` resource, instrumented.
 *
 * Everything XOOPS renders through a template goes through this resource: the
 * module's main template (header.php prefixes it with `db:`), every block
 * (theme_blocks.php), and the content template. Only the theme canvas itself is
 * a plain `file:` resource and stays invisible, which is one template.
 *
 * Smarty consults a *registered* resource before the one it would autoload from
 * the plugins directory, so registering an instance of this subclass takes over
 * without a core change. Registration has to happen in the
 * `core.class.template.new` hook: once Smarty_Resource::load() caches a handler
 * for `db`, a later swap is ignored.
 *
 * fetch() runs on every render, warm compile cache or not —
 * Smarty_Template_Source::load() calls populate() unconditionally, and the core
 * resource does not override fetchTimestamp(), so populate() always falls
 * through to fetch().
 */
final class TemplateResource extends \Smarty_Resource_Db
{
    /** Recording stops here; a page rendering more templates than this has a bigger problem. */
    private const RECORD_CAP = 200;

    /** @var array<string, array{name: string, source: string, path: string, renders: int, ms: float, bytes: int}> */
    private array $records = [];

    private int $dropped = 0;

    private readonly TemplateOrigin $origin;

    public function __construct(private readonly string $themeSet)
    {
        $this->origin = new TemplateOrigin();
    }

    /**
     * Fetch through core, then record what it produced.
     *
     * Parameters stay untyped to match core: Smarty_Resource_Db sets both
     * by-ref values to null when stat() fails on the resolved file, so neither
     * is guaranteed to come back a string or an int.
     *
     * @param mixed $name   template name
     * @param mixed $source template source, by reference
     * @param mixed $mtime  modification time, by reference
     */
    protected function fetch($name, &$source, &$mtime): void
    {
        $start = microtime(true);

        try {
            parent::fetch($name, $source, $mtime);
        } finally {
            // `finally`, so a template that fails to load is still reported —
            // that is precisely the render a webmaster is hunting for. Recording
            // is wrapped separately because a throw here would blank the site.
            try {
                $this->record((string) $name, is_string($source) ? $source : '', (int) $mtime, (microtime(true) - $start) * 1000);
            } catch (\Throwable) {
                // Observation must never be the reason a page dies.
            }
        }
    }

    /**
     * Everything rendered this request.
     *
     * @return list<array{name: string, source: string, path: string, renders: int, ms: float, bytes: int}>
     */
    public function records(): array
    {
        return array_values($this->records);
    }

    /** How many distinct templates went unrecorded because the cap was reached. */
    public function dropped(): int
    {
        return $this->dropped;
    }

    private function record(string $name, string $source, int $mtime, float $milliseconds): void
    {
        if (isset($this->records[$name])) {
            // Same template rendered again — count it rather than listing it
            // twice, since a repeated render is itself worth seeing.
            $this->records[$name]['renders']++;
            $this->records[$name]['ms'] += $milliseconds;

            return;
        }

        if (count($this->records) >= self::RECORD_CAP) {
            $this->dropped++;

            return;
        }

        $bytes = strlen($source);
        $origin = '' === $source && 0 === $mtime
            // Core sets both to null when the template could not be read at all.
            ? ['source' => 'not found', 'path' => '']
            : $this->origin->resolve($name, $mtime, $bytes, $this->themeSet, $source);

        $this->records[$name] = [
            'name' => $name,
            'source' => $origin['source'],
            'path' => $origin['path'],
            'renders' => 1,
            'ms' => $milliseconds,
            'bytes' => $bytes,
        ];
    }
}
