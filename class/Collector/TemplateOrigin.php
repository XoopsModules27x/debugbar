<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Collector;

/**
 * DebugBar Module - Template Origin Resolver
 *
 * Identifies which file on disk actually supplied a rendered template.
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
 * Answers "where did this template actually come from?".
 *
 * The obvious way would be to repeat what Smarty_Resource_Db::dbTplInfo() does,
 * but that method is private, so a subclass cannot reuse it, and re-deriving it
 * means another tplfile lookup per template — a debug tool inflating the very
 * Queries panel it ships.
 *
 * So this does not re-derive the resolution at all. It works backwards from the
 * bytes core already handed back: glob the two places an override can live, then
 * accept a candidate only when its modification time and size both match what
 * was actually fetched. That verifies the attribution rather than guessing it,
 * costs no queries, and cannot drift out of step with core's resolution order —
 * because it is not reimplementing it.
 */
final class TemplateOrigin
{
    public const THEME_OVERRIDE = 'theme override';

    public const MODULE_FILE = 'module file';

    public const DATABASE = 'database';

    public const AMBIGUOUS = 'ambiguous';

    /** @var array<string, array{source: string, path: string}> resolved origins, keyed by name+mtime+size */
    private array $cache = [];

    /**
     * @param string $name   template name, e.g. "system_block_login.tpl"
     * @param int    $mtime  modification time core reported for the fetched source
     * @param int    $bytes  length of the fetched source
     * @param string $theme  current theme set
     *
     * @return array{source: string, path: string}
     */
    public function resolve(string $name, int $mtime, int $bytes, string $theme): array
    {
        $key = $name . '|' . $mtime . '|' . $bytes;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->probe($name, $mtime, $bytes, $theme);
    }

    /**
     * @return array{source: string, path: string}
     */
    private function probe(string $name, int $mtime, int $bytes, string $theme): array
    {
        // A template name is a bare filename by XOOPS convention. Anything with
        // a separator in it is not something we should be globbing for.
        if ('' === $name || 1 === preg_match('#[/\\\\]#', $name)) {
            return ['source' => self::DATABASE, 'path' => ''];
        }

        $root = rtrim(XOOPS_ROOT_PATH, '/\\');
        $safeTheme = 1 === preg_match('/^[A-Za-z0-9_.-]+$/', $theme) ? $theme : '*';

        // Theme overrides win in core's resolution, so they are checked first.
        $themeMatches = $this->matching($root . '/themes/' . $safeTheme . '/modules/*/' . $name, $mtime, $bytes);
        $themeMatches = array_merge(
            $themeMatches,
            // Block and admin templates sit one level deeper.
            $this->matching($root . '/themes/' . $safeTheme . '/modules/*/blocks/' . $name, $mtime, $bytes),
            $this->matching($root . '/modules/system/themes/*/modules/*/admin/' . $name, $mtime, $bytes)
        );
        if ([] !== $themeMatches) {
            return $this->verdict(self::THEME_OVERRIDE, $themeMatches);
        }

        $moduleMatches = array_merge(
            $this->matching($root . '/modules/*/templates/' . $name, $mtime, $bytes),
            $this->matching($root . '/modules/*/templates/blocks/' . $name, $mtime, $bytes),
            $this->matching($root . '/modules/*/templates/admin/' . $name, $mtime, $bytes)
        );
        if ([] !== $moduleMatches) {
            return $this->verdict(self::MODULE_FILE, $moduleMatches);
        }

        // Nothing on disk accounts for these bytes, so they came from the
        // tplfile table — which is also the answer when a theme is installed
        // whose templates were imported into the database.
        return ['source' => self::DATABASE, 'path' => ''];
    }

    /**
     * Candidate paths whose mtime and size both match the fetched source.
     *
     * @return list<string>
     */
    private function matching(string $pattern, int $mtime, int $bytes): array
    {
        $found = glob($pattern, GLOB_NOSORT);
        if (false === $found) {
            return [];
        }

        $matches = [];
        foreach ($found as $path) {
            if (! is_file($path)) {
                continue;
            }
            // Both must agree. Size alone collides between near-identical
            // overrides; mtime alone collides across a bulk checkout or copy.
            if ((int) @filemtime($path) === $mtime && (int) @filesize($path) === $bytes) {
                $matches[] = $path;
            }
        }

        return $matches;
    }

    /**
     * @param list<string> $matches
     *
     * @return array{source: string, path: string}
     */
    private function verdict(string $source, array $matches): array
    {
        if (count($matches) > 1) {
            // Two files with identical name, size and timestamp. Saying which
            // one won would be a guess, and a wrong guess here is worse than
            // no answer: the whole point is to settle "is my override live?".
            return ['source' => self::AMBIGUOUS, 'path' => implode(', ', array_map([$this, 'relative'], $matches))];
        }

        return ['source' => $source, 'path' => $this->relative($matches[0])];
    }

    /** Path relative to the XOOPS root, so no server layout is disclosed. */
    private function relative(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', XOOPS_ROOT_PATH), '/');
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : basename($path);
    }
}
