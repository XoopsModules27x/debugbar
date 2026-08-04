<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

/**
 * DebugBar Module - SQL Literal Redactor
 *
 * Produces a RUNNABLE, value-free version of a statement for the on-demand
 * EXPLAIN stash: string literals become '' and numeric literals (decimal,
 * hexadecimal, binary, scientific) become 0, in place. The statement still
 * EXPLAINs to a representative plan — index usage and scan type are unchanged,
 * only value-dependent row estimates shift.
 *
 * SCOPE, stated honestly: this is a regex over SQL, not a SQL parser, so it
 * removes literals in well-formed statements written the way XOOPS writes
 * them. It does NOT understand:
 *   - `backtick` identifiers containing a quote character;
 *   - -- line and slash-star block comments containing a quote character;
 *   - servers running with NO_BACKSLASH_ESCAPES, where a trailing backslash
 *     inside a literal is data rather than an escape;
 *   - unterminated literals, which a failed query can still carry.
 * In each of those a following literal can survive. Treat the output as
 * "values stripped on the common path", not as a guarantee that no secret can
 * ever appear. Closing those cases needs a tokenizer; see docs/file-list.md.
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
    /**
     * The canonical definition of "a SQL string literal", shared with
     * QueryFingerprinter so the two can never disagree about where a literal
     * ends.
     *
     * Both quote styles MUST be matched in one alternation pass. Scanning them
     * in two sequential passes is wrong: an apostrophe inside a double-quoted
     * value (`"O'Brien"`) lets the single-quote pass run past it and stop on
     * the OPENING quote of a later single-quoted literal, consuming only that
     * quote and leaving the following secret — value and closing quote — in the
     * output. Both classes shipped that two-pass form and both leaked.
     *
     * The `[^'\\]` / `[^"\\]` classes must keep excluding the backslash:
     * dropping it makes the two alternatives overlap on `\`, which is an
     * ambiguous quantified alternation that exhausts pcre.backtrack_limit on a
     * literal containing a run of backslashes. preg_replace() then returns null
     * and the caller's (string) cast silently yields an empty statement.
     *
     * The `''` / `""` alternatives handle MySQL's doubled-quote escape, which
     * is one literal, not two. Without them `'O''Reilly'` scanned as a pair of
     * adjacent literals and fingerprinted to `??` while `'Smith'` gave `?`, so
     * two structurally identical statements landed in different N+1 groups. The
     * three alternatives stay disjoint on their first character, so this adds
     * no ambiguity and the scan remains linear.
     */
    public const STRING_LITERAL_PATTERN = "/'(?:[^'\\\\]|\\\\.|'')*'|\"(?:[^\"\\\\]|\\\\.|\"\")*\"/s";

    /**
     * Numeric literals not embedded in identifiers (`x123`, `utf8mb4`, `tbl_2`).
     *
     * Hexadecimal and binary forms are matched explicitly. A bare \d+ rule
     * consumed only the leading 0 of 0x534543524554 and left the payload
     * standing, which made a hex-encoded value survive redaction untouched;
     * the exponent clause does the same job for 6.022e23, which otherwise
     * reduced to 0e23. Shared with QueryFingerprinter for the same reason as
     * the string pattern.
     */
    public const NUMERIC_LITERAL_PATTERN = '/(?<![A-Za-z0-9_`.])(?:0[xX][0-9a-fA-F]+|0[bB][01]+|\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/';

    /**
     * Statements longer than this are refused rather than scanned.
     *
     * The literal pattern is linear on well-formed input, but PCRE's JIT hits
     * its stack limit on a single very long quoted literal (~16 KB observed on
     * PHP 8.2) and preg_replace() then returns null. Callers cap SQL at 4 KB
     * today, so this is a backstop for a future caller that does not — the
     * point is that failure must be visible rather than a silent empty string.
     */
    public const MAX_INPUT_LENGTH = 8000;

    /** Returned instead of an empty string when redaction cannot be completed. */
    public const REDACTION_FAILED = '/* debugbar: statement could not be redacted */';

    /**
     * Strip literal values from a statement, leaving it runnable.
     *
     * @param string $sql raw SQL statement
     *
     * @return string the statement with literals replaced, or REDACTION_FAILED
     *                when it is too long to scan or a pass could not complete
     */
    public static function redact(string $sql): string
    {
        if (strlen($sql) > self::MAX_INPUT_LENGTH) {
            return self::REDACTION_FAILED;
        }

        // String literals (single- and double-quoted, backslash escapes) -> ''
        $out = preg_replace(self::STRING_LITERAL_PATTERN, "''", $sql);
        if (null === $out) {
            return self::REDACTION_FAILED;
        }

        // Numeric literals -> 0
        $out = preg_replace(self::NUMERIC_LITERAL_PATTERN, '0', $out);
        if (null === $out) {
            return self::REDACTION_FAILED;
        }

        return $out;
    }
}
