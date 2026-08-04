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
 * SCOPE: this is a regex over SQL, not a SQL parser, so it cannot model
 * every statement. It does NOT understand `backtick` identifiers containing a
 * quote character, -- line or slash-star block comments containing one,
 * servers running with NO_BACKSLASH_ESCAPES (where a trailing backslash inside
 * a literal is data, not an escape), or unterminated literals carried by a
 * failed query. In each of those a following literal could otherwise survive.
 *
 * Rather than leaving those as silent gaps, the scan verifies its own work and
 * REFUSES when it cannot account for the statement — see scanLiterals(). A
 * tokenizer would let those statements be redacted instead of refused; until
 * then they degrade to no EXPLAIN rather than to a leak.
 *
 * READ THE GUARANTEE NARROWLY: "every LITERAL was removed, or nothing is
 * returned". It is not "no sensitive text survives". Comments, identifiers and
 * variable names are not literals, so
 *     SELECT * FROM t /* password=hunter2 *​/ WHERE id = 7
 * passes the check — there is no quote to betray it — and the comment is
 * returned verbatim. The same holds for `token_abc123` as a backtick
 * identifier or @password_abc as a user variable. Anything that puts a value
 * outside a literal is outside what this class removes.
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
     *
     * The final branch covers MySQL's leading-dot form (`.5`), which the
     * lookbehind would otherwise reject: that lookbehind exists to protect
     * qualified names like `tbl_2.x`, and it was also refusing every decimal
     * written without a leading zero, so `.534543524554` survived intact. The
     * branch starts AT the dot, so the lookbehind then examines the character
     * before it — still blocking `a.5` while allowing `= .5`.
     */
    public const NUMERIC_LITERAL_PATTERN = '/(?<![A-Za-z0-9_`.])(?:0[xX][0-9a-fA-F]+|0[bB][01]+|\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|\.\d+(?:[eE][+-]?\d+)?)/';

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
     *                when it is too long to scan, a pass could not complete, or
     *                the scan could not account for every quote character
     */
    public static function redact(string $sql): string
    {
        $scanned = self::scanLiterals($sql);
        if (null === $scanned) {
            return self::REDACTION_FAILED;
        }

        // Numeric literals -> 0
        $out = preg_replace(self::NUMERIC_LITERAL_PATTERN, '0', str_replace(self::SENTINEL, "''", $scanned));

        return null === $out ? self::REDACTION_FAILED : $out;
    }

    /**
     * Replace every string literal with SENTINEL, or return null when the scan
     * cannot be trusted.
     *
     * The trust check is the important part, and it is exact rather than a list
     * of suspicious constructs: a statement whose literals have all been
     * accounted for has no quote character left once they are removed. If one
     * survives, the scan did not model the statement — the quote is inside a
     * `backtick` identifier or a comment the pattern cannot see, the literal was
     * never terminated, or the server treats backslashes as data
     * (NO_BACKSLASH_ESCAPES) and a literal ended earlier than assumed. Each of
     * those is a case where a LATER literal can survive unredacted, so all of
     * them must fail closed. This is what makes the cases named in the class
     * docblock refusals rather than silent leaks.
     */
    private static function scanLiterals(string $sql): ?string
    {
        if (strlen($sql) > self::MAX_INPUT_LENGTH || str_contains($sql, self::SENTINEL)) {
            return null;
        }

        $out = preg_replace(self::STRING_LITERAL_PATTERN, self::SENTINEL, $sql);
        if (null === $out) {
            return null;
        }

        return (str_contains($out, "'") || str_contains($out, '"')) ? null : $out;
    }

    /**
     * Stand-in for a matched literal while the scan is checked.
     *
     * Deliberately quote-free, so any quote remaining afterwards is proof that
     * the scan missed something rather than an artefact of the replacement.
     */
    private const SENTINEL = "\x01";
}
