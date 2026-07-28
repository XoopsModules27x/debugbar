<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar\Analysis;

/**
 * DebugBar Module - Cachegrind Parse Result
 *
 * Immutable value object returned by CachegrindParser::parse().
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
 * Result of parsing a Cachegrind/Callgrind profile file.
 */
final readonly class CachegrindResult
{
    /**
     * @param string      $status    one of 'ok'|'truncated'|'too_large'|'unsupported'|'invalid'|'unreadable'
     * @param string      $unit      'ms' when converted, otherwise the raw events token label (e.g. 'Time')
     * @param float|null  $total     total cost in $unit (null when unknown)
     * @param array<int, array{name: string, file: string, calls: int, invocations: int, self: float, incl: float, self_pct: float}> $functions list of rows
     * @param list<string> $warnings  short machine-ish codes + text
     * @param string|null $creator   'creator:' header value, if seen
     * @param string|null $command   'cmd:' header value reduced to basename(), if seen
     * @param int         $linesRead number of lines consumed while parsing
     */
    public function __construct(
        public string $status,
        public string $unit,
        public ?float $total,
        public array $functions,
        public array $warnings,
        public ?string $creator,
        public ?string $command,
        public int $linesRead,
    ) {
    }

    /**
     * Convert to a plain array (for JSON encoding / templates).
     *
     * @return array{
     *     status: string,
     *     unit: string,
     *     total: float|null,
     *     functions: array<int, array{name: string, file: string, calls: int, invocations: int, self: float, incl: float, self_pct: float}>,
     *     warnings: list<string>,
     *     creator: string|null,
     *     command: string|null,
     *     linesRead: int
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'unit' => $this->unit,
            'total' => $this->total,
            'functions' => $this->functions,
            'warnings' => $this->warnings,
            'creator' => $this->creator,
            'command' => $this->command,
            'linesRead' => $this->linesRead,
        ];
    }
}
