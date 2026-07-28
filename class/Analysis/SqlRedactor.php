<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

/**
 * DebugBar Module - SQL Literal Redactor
 *
 * Produces a RUNNABLE, secret-free version of a statement for the on-demand
 * EXPLAIN stash: every string literal becomes '' and every numeric literal
 * becomes 0, in place. No session id / password / token value is ever
 * persisted, while the statement still EXPLAINs to a representative plan
 * (index usage / scan type unchanged; only value-dependent row estimates shift).
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
 * Pure, stateless — safe to call outside a booted XOOPS.
 */
final class SqlRedactor
{
    public static function redact(string $sql): string
    {
        // String literals (single- and double-quoted, backslash escapes) -> ''
        $sql = (string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "''", $sql);
        $sql = (string) preg_replace('/"(?:[^"\\\\]|\\\\.)*"/s', "''", $sql);

        // Numeric literals not embedded in identifiers (`x123`, `utf8mb4`, `tbl_2`) -> 0
        $sql = (string) preg_replace('/(?<![A-Za-z0-9_`.])\d+(?:\.\d+)?/', '0', $sql);

        return $sql;
    }
}
