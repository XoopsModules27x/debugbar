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
    /** Returned instead of a partial fingerprint when normalisation cannot complete. */
    public const FINGERPRINT_FAILED = '(statement could not be fingerprinted)';

    /**
     * Normalize an SQL statement to its structural fingerprint.
     *
     * String and numeric literals become `?`, `IN (?, ?, ...)` lists
     * collapse to `IN (?+)`, and whitespace is normalized, so
     * `... WHERE uid = 7` and `... WHERE uid = 42` share one fingerprint.
     *
     * @param string $sql raw SQL statement
     *
     * @return string normalized fingerprint, or FINGERPRINT_FAILED when the
     *                statement is too long to scan or a pass could not complete
     */
    public static function fingerprint(string $sql): string
    {
        $sql = trim($sql);
        if (strlen($sql) > SqlRedactor::MAX_INPUT_LENGTH) {
            return self::FINGERPRINT_FAILED;
        }

        // String and numeric literals. Shares SqlRedactor's patterns
        // deliberately: this fingerprint is stored in
        // debugbar_profiles.slowest_fp, so a literal that survives the scan is
        // persisted for the whole retention window. See those constants for why
        // one alternation pass is required and why hex/binary/exponent forms
        // are matched explicitly.
        foreach ([SqlRedactor::STRING_LITERAL_PATTERN, SqlRedactor::NUMERIC_LITERAL_PATTERN] as $pattern) {
            $replaced = preg_replace($pattern, '?', $sql);
            if (null === $replaced) {
                return self::FINGERPRINT_FAILED;
            }
            $sql = $replaced;
        }

        // Collapse IN lists of placeholders to a single marker
        $sql = (string) preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'IN (?+)', $sql);

        // Normalize whitespace
        $sql = (string) preg_replace('/\s+/', ' ', $sql);

        return trim($sql);
    }
}
