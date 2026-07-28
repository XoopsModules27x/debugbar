<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

/**
 * DebugBar Module - SQL Query Fingerprinter
 *
 * Normalizes SQL statements to a fingerprint so structurally identical
 * queries that differ only in literal values can be grouped and counted
 * (N+1 detection, repeat analysis).
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
 * Pure, stateless SQL normalizer — safe to call outside a booted XOOPS.
 */
final class QueryFingerprinter
{
    /**
     * Normalize an SQL statement to its structural fingerprint.
     *
     * String and numeric literals become `?`, `IN (?, ?, ...)` lists
     * collapse to `IN (?+)`, and whitespace is normalized, so
     * `... WHERE uid = 7` and `... WHERE uid = 42` share one fingerprint.
     *
     * @param string $sql raw SQL statement
     *
     * @return string normalized fingerprint
     */
    public static function fingerprint(string $sql): string
    {
        $sql = trim($sql);

        // String literals (single- and double-quoted, with backslash escapes)
        $sql = (string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", '?', $sql);
        $sql = (string) preg_replace('/"(?:[^"\\\\]|\\\\.)*"/s', '?', $sql);

        // Numeric literals not embedded in identifiers (`x123`, `utf8mb4`, `tbl_2`)
        $sql = (string) preg_replace('/(?<![A-Za-z0-9_`.])\d+(?:\.\d+)?/', '?', $sql);

        // Collapse IN lists of placeholders to a single marker
        $sql = (string) preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'IN (?+)', $sql);

        // Normalize whitespace
        $sql = (string) preg_replace('/\s+/', ' ', $sql);

        return trim($sql);
    }
}
