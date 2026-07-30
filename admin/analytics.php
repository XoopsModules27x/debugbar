<?php
declare(strict_types=1);

/**
 * DebugBar Module - Admin Analytics
 *
 * Aggregated view of stored request profiles: worst offenders, N+1
 * leaderboard, per-module comparison, budget violations, field web-vitals,
 * OPcache health, and the flight recorder drill-down.
 *
 * @copyright       (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             debugbar
 */

use Xmf\Module\Admin;
use Xmf\Request;
use XoopsModules\Debugbar\Admin\AccessPolicy;
use XoopsModules\Debugbar\Admin\AnalyticsBuilder;

require_once __DIR__ . '/admin_header.php';
xoops_cp_header();

if (! AccessPolicy::isAllowed()) {
    echo '<p style="color:#a00;font-weight:bold;">' . htmlspecialchars(_AM_DEBUGBAR_AN_FORBIDDEN, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    require_once __DIR__ . '/admin_footer.php';

    return;
}

$adminObject = Admin::getInstance();
$adminObject->displayNavigation(basename(__FILE__));

/**
 * Escape for HTML output.
 *
 * @param mixed $value value to escape
 * @return string
 */
$esc = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

/**
 * Render one data table.
 *
 * @param string   $title   section heading (already translated)
 * @param string[] $headers column headings
 * @param array    $rows    list of cell lists (pre-escaped strings)
 * @return string
 */
$table = static function (string $title, array $headers, array $rows) use ($esc): string {
    $html = '<h3 style="margin:18px 0 6px;">' . $esc($title) . '</h3>';
    if ([] === $rows) {
        $html .= '<p style="color:#777;">' . $esc(_AM_DEBUGBAR_AN_NODATA) . '</p>';

        return $html;
    }
    $html .= '<table class="outer" style="border-collapse:collapse;width:100%;"><tr>';
    foreach ($headers as $header) {
        $html .= '<th style="text-align:left;padding:4px 8px;">' . $esc($header) . '</th>';
    }
    $html .= '</tr>';
    foreach ($rows as $i => $cells) {
        $html .= '<tr class="' . (0 === $i % 2 ? 'even' : 'odd') . '">';
        foreach ($cells as $cell) {
            // Cells arrive pre-escaped (links allowed)
            $html .= '<td style="padding:4px 8px;vertical-align:top;">' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    return $html;
};

$builder = new AnalyticsBuilder();

// ---- Cachegrind POST actions (delete / purge) ------------------------------
$op = Request::getString('op', '', 'POST');
if ('cg_delete' === $op || 'cg_purge' === $op) {
    if (! $GLOBALS['xoopsSecurity']->check()) {
        redirect_header('analytics.php', 3, implode('<br>', $GLOBALS['xoopsSecurity']->getErrors()));
    }
    if ('cg_delete' === $op) {
        $cgFile = Request::getString('cg_file', '', 'POST');
        $builder->catalog()->delete($cgFile);
    } else {
        $builder->catalog()->purgeOlderThan(30);
    }
    redirect_header('analytics.php', 2, _AM_DEBUGBAR_AN_CG_DELETED);
}

// ---- Flight-recorder detail view -------------------------------------------
$file = Request::getString('file', '', 'GET');
if ('' !== $file) {
    $record = $builder->flightRecord($file);
    echo '<p><a href="analytics.php">&larr; ' . $esc(_AM_DEBUGBAR_AN_BACK) . '</a></p>';
    if (null === $record) {
        echo '<p style="color:#a00;">' . $esc(_AM_DEBUGBAR_AN_RECORD_MISSING) . '</p>';
    } else {
        echo '<h3>' . $esc(_AM_DEBUGBAR_AN_RECORD) . ': ' . $esc($record['url'] ?? $file) . '</h3>';
        echo '<pre style="background:#f7f7f7;border:1px solid #ddd;padding:10px;overflow:auto;max-height:70vh;">'
            . $esc(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            . '</pre>';
    }
    require_once __DIR__ . '/admin_footer.php';

    return;
}

// ---- Cachegrind profile detail view -----------------------------------------
$cgFile = Request::getString('cg', '', 'GET');
if ('' !== $cgFile) {
    echo '<p><a href="analytics.php">&larr; ' . $esc(_AM_DEBUGBAR_AN_BACK) . '</a></p>';
    $top = $builder->cachegrindTop($cgFile);
    if (null === $top) {
        echo '<p style="color:#a00;">' . $esc(_AM_DEBUGBAR_AN_CG_MISSING) . '</p>';
    } else {
        $result = $top['result'];
        echo '<h3>' . $esc(basename($cgFile)) . '</h3>';

        $totalFormatted = null === $result->total
            ? '—'
            : ('ms' === $result->unit
                ? number_format($result->total, 1) . ' ms'
                : number_format($result->total) . ' ' . $result->unit);
        // A truncated parse never reached the summary line: the total covers
        // only the parsed prefix and must not read as the request total.
        if ('truncated' === $result->status && '—' !== $totalFormatted) {
            $totalFormatted = '≥ ' . $totalFormatted . ' (' . _AM_DEBUGBAR_AN_CG_PARTIAL . ')';
        }

        echo '<p style="color:#555;">'
            . $esc($top['path']) . '<br>'
            . $esc(_AM_DEBUGBAR_AN_CG_CREATOR) . ': ' . $esc($result->creator ?? '—') . ' &nbsp; '
            . $esc(_AM_DEBUGBAR_AN_CG_COMMAND) . ': ' . $esc($result->command ?? '—') . '<br>'
            . $esc(formatTimestamp($top['mtime'], 'mysql')) . ' &nbsp; '
            . $esc(number_format($top['bytes'] / 1024, 1)) . ' KB &nbsp; '
            . $esc(_AM_DEBUGBAR_AN_CG_TOTAL) . ': ' . $esc($totalFormatted)
            . '</p>';

        if ([] !== $result->warnings) {
            echo '<ul style="color:#a60;">';
            foreach ($result->warnings as $warning) {
                echo '<li>' . $esc($warning) . '</li>';
            }
            echo '</ul>';
        }

        $rows = [];
        foreach ($result->functions as $fn) {
            $callsCell = 0 === (int) $fn['calls'] ? (int) $fn['invocations'] : (int) $fn['calls'];
            $selfCell = 'ms' === $result->unit ? number_format((float) $fn['self'], 1) : number_format((float) $fn['self']);
            $inclCell = 'ms' === $result->unit ? number_format((float) $fn['incl'], 1) : number_format((float) $fn['incl']);
            $rows[] = [
                $esc($fn['name']),
                $esc($callsCell),
                $esc($selfCell),
                $esc(number_format((float) $fn['self_pct'], 1)) . '%',
                $esc($inclCell),
            ];
        }
        echo $table(
            _AM_DEBUGBAR_AN_CG_TOP_FUNCTIONS,
            [_AM_DEBUGBAR_AN_CG_FUNCTION, _AM_DEBUGBAR_AN_CG_CALLS, _AM_DEBUGBAR_AN_CG_SELF, _AM_DEBUGBAR_AN_CG_SELF_PCT, _AM_DEBUGBAR_AN_CG_INCL],
            $rows
        );

        foreach ($result->warnings as $warning) {
            if (str_starts_with($warning, 'recursion')) {
                echo '<p style="color:#a60;">' . $esc(_AM_DEBUGBAR_AN_CG_RECURSION_NOTE) . '</p>';

                break;
            }
        }
    }
    require_once __DIR__ . '/admin_footer.php';

    return;
}

// ---- Overview ----------------------------------------------------------------
$sinceDays = Request::getInt('days', 7, 'GET');
$sinceDays = in_array($sinceDays, [1, 7, 30], true) ? $sinceDays : 7;
$data = $builder->build($sinceDays);

// Window switcher + row count
$windows = [];
foreach ([1, 7, 30] as $days) {
    $label = sprintf(_AM_DEBUGBAR_AN_DAYS, $days);
    $windows[] = $days === $sinceDays
        ? '<strong>' . $esc($label) . '</strong>'
        : '<a href="analytics.php?days=' . $days . '">' . $esc($label) . '</a>';
}
echo '<p>' . implode(' | ', $windows)
    . ' &nbsp;&mdash;&nbsp; ' . $esc(sprintf(_AM_DEBUGBAR_AN_ROWCOUNT, (int) $data['row_count'])) . '</p>';

if (true !== $data['table_exists']) {
    echo '<p style="color:#a00;font-weight:bold;">' . $esc(_AM_DEBUGBAR_AN_NOTABLE) . '</p>';
}

// ---- OPcache health card -------------------------------------------------------
$opcache = $data['opcache'];
if (isset($opcache['available']) && true === $opcache['available']) {
    $restartColor = $opcache['restarts'] > 0 ? '#a00' : '#080';
    $wastedColor = $opcache['wasted_pct'] > 10 ? '#a60' : '#080';
    echo '<h3 style="margin:18px 0 6px;">' . $esc(_AM_DEBUGBAR_AN_OPCACHE) . '</h3><p>'
        . $esc(_AM_DEBUGBAR_AN_OPCACHE_HITRATE) . ': <strong>' . $esc($opcache['hit_rate']) . '%</strong> &nbsp; '
        . $esc(_AM_DEBUGBAR_AN_OPCACHE_MEM) . ': ' . $esc($opcache['used_mb']) . ' MB used / ' . $esc($opcache['free_mb']) . ' MB free / '
        . '<span style="color:' . $wastedColor . ';">' . $esc($opcache['wasted_mb']) . ' MB (' . $esc($opcache['wasted_pct']) . '%) wasted</span> &nbsp; '
        . $esc(_AM_DEBUGBAR_AN_OPCACHE_SCRIPTS) . ': ' . $esc($opcache['cached_scripts']) . ' &nbsp; '
        . $esc(_AM_DEBUGBAR_AN_OPCACHE_RESTARTS) . ': <span style="color:' . $restartColor . ';">' . $esc($opcache['restarts']) . '</span></p>';
} else {
    echo '<h3 style="margin:18px 0 6px;">' . $esc(_AM_DEBUGBAR_AN_OPCACHE) . '</h3><p style="color:#777;">'
        . $esc(_AM_DEBUGBAR_AN_OPCACHE_OFF) . '</p>';
}

// ---- Worst offenders ------------------------------------------------------------
$rows = [];
foreach ($data['worst_urls'] as $row) {
    $rows[] = [
        $esc($row['url']),
        $esc((null !== $row['dirname'] && '' !== $row['dirname']) ? $row['dirname'] : '—'),
        $esc((int) $row['hits']),
        $esc(number_format((float) $row['avg_ms'], 1)),
        $esc(number_format((float) $row['max_ms'], 1)),
        $esc(number_format((float) $row['avg_queries'], 1)),
        $esc((int) $row['max_nplus1']),
        $esc((int) $row['violations']),
    ];
}
echo $table(
    _AM_DEBUGBAR_AN_WORST,
    [_AM_DEBUGBAR_AN_URL, _AM_DEBUGBAR_AN_MODULE, _AM_DEBUGBAR_AN_HITS, _AM_DEBUGBAR_AN_AVG_MS, _AM_DEBUGBAR_AN_MAX_MS, _AM_DEBUGBAR_AN_AVG_QUERIES, _AM_DEBUGBAR_AN_MAX_NPLUS1, _AM_DEBUGBAR_AN_VIOLATIONS],
    $rows
);

// ---- N+1 leaderboard --------------------------------------------------------------
$rows = [];
foreach ($data['nplus1_leaders'] as $row) {
    $rows[] = [
        $esc($row['url']),
        $esc((null !== $row['dirname'] && '' !== $row['dirname']) ? $row['dirname'] : '—'),
        $esc((int) $row['hits']),
        $esc((int) $row['max_nplus1']),
        $esc(number_format((float) $row['avg_queries'], 1)),
        '<code style="font-size:11px;">' . $esc(mb_strimwidth((string) $row['sample_fp'], 0, 120, '…')) . '</code>',
    ];
}
echo $table(
    _AM_DEBUGBAR_AN_NPLUS1,
    [_AM_DEBUGBAR_AN_URL, _AM_DEBUGBAR_AN_MODULE, _AM_DEBUGBAR_AN_HITS, _AM_DEBUGBAR_AN_MAX_NPLUS1, _AM_DEBUGBAR_AN_AVG_QUERIES, _AM_DEBUGBAR_AN_SAMPLE_FP],
    $rows
);

// ---- Per-module comparison -----------------------------------------------------------
$rows = [];
foreach ($data['modules'] as $row) {
    $rows[] = [
        $esc((null !== $row['dirname'] && '' !== $row['dirname']) ? $row['dirname'] : '—'),
        $esc((int) $row['hits']),
        $esc(number_format((float) $row['avg_ms'], 1)),
        $esc(number_format((float) $row['avg_queries'], 1)),
        // moduleAggregates() already divides payload_bytes by 1024, so the value
        // is KB at the source. Dividing again here would be wrong even with the
        // right key — and the key was wrong, so the column read 0.0 for every module.
        $esc(number_format((float) $row['avg_payload_kb'], 1)),
        // Cached / uncached block renders per request. The uncached figure is
        // the actionable one: it is repeated work on every single page view.
        $esc(sprintf(
            '%s / %s',
            number_format((float) $row['avg_blocks_cached'], 1),
            number_format((float) $row['avg_blocks_uncached'], 1)
        )),
        $esc((int) $row['fragment_hits']),
        $esc((int) $row['violations']),
    ];
}
echo $table(
    _AM_DEBUGBAR_AN_MODULES,
    [_AM_DEBUGBAR_AN_MODULE, _AM_DEBUGBAR_AN_HITS, _AM_DEBUGBAR_AN_AVG_MS, _AM_DEBUGBAR_AN_AVG_QUERIES, _AM_DEBUGBAR_AN_AVG_PAYLOAD, _AM_DEBUGBAR_AN_BLOCKS, _AM_DEBUGBAR_AN_FRAGMENTS, _AM_DEBUGBAR_AN_VIOLATIONS],
    $rows
);

// ---- Recent violations -------------------------------------------------------------------
$rows = [];
foreach ($data['violations'] as $row) {
    $rows[] = [
        $esc(formatTimestamp((int) $row['created'], 'mysql')),
        $esc($row['url']),
        $esc((null !== $row['dirname'] && '' !== $row['dirname']) ? $row['dirname'] : '—'),
        $esc(number_format((float) $row['total_ms'], 1)),
        $esc((int) $row['query_count']),
        $esc(implode(', ', $row['flag_names'])),
    ];
}
echo $table(
    _AM_DEBUGBAR_AN_VIOLATIONS_FEED,
    [_AM_DEBUGBAR_AN_WHEN, _AM_DEBUGBAR_AN_URL, _AM_DEBUGBAR_AN_MODULE, _AM_DEBUGBAR_AN_TOTAL_MS, _AM_DEBUGBAR_AN_QUERIES, _AM_DEBUGBAR_AN_FLAGS],
    $rows
);

// ---- Field web-vitals ------------------------------------------------------------------------
$rows = [];
foreach ($data['vitals'] as $row) {
    $lcp = (float) $row['avg_lcp'];
    $lcpColor = $lcp <= 2500 ? '#080' : ($lcp <= 4000 ? '#a60' : '#a00');
    $rows[] = [
        $esc($row['url']),
        $esc((int) $row['samples']),
        '<span style="color:' . $lcpColor . ';">' . $esc(number_format($lcp, 0)) . '</span>',
        $esc(null !== $row['avg_inp'] ? number_format((float) $row['avg_inp'], 0) : '—'),
        $esc(null !== $row['avg_cls'] ? number_format((float) $row['avg_cls'], 3) : '—'),
        $esc(number_format((float) $row['avg_server_ms'], 1)),
    ];
}
echo $table(
    _AM_DEBUGBAR_AN_VITALS,
    [_AM_DEBUGBAR_AN_URL, _AM_DEBUGBAR_AN_SAMPLES, _AM_DEBUGBAR_AN_LCP, _AM_DEBUGBAR_AN_INP, _AM_DEBUGBAR_AN_CLS, _AM_DEBUGBAR_AN_SERVER_MS],
    $rows
);

// ---- Flight recorder ---------------------------------------------------------------------------
$rows = [];
foreach ($data['flight_records'] as $record) {
    $rows[] = [
        $esc(formatTimestamp($record['created'], 'mysql')),
        true === $record['violation']
            ? '<span style="color:#a00;font-weight:bold;">' . $esc(_AM_DEBUGBAR_AN_VIOLATION) . '</span>'
            : $esc(_AM_DEBUGBAR_AN_OK),
        $esc($record['request_id']),
        $esc(number_format($record['bytes'] / 1024, 1) . ' KB'),
        '<a href="analytics.php?file=' . $esc(rawurlencode($record['file'])) . '">' . $esc(_AM_DEBUGBAR_AN_VIEW) . '</a>',
    ];
}
echo $table(
    _AM_DEBUGBAR_AN_FLIGHT,
    [_AM_DEBUGBAR_AN_WHEN, _AM_DEBUGBAR_AN_STATUS, _AM_DEBUGBAR_AN_REQUEST, _AM_DEBUGBAR_AN_SIZE, ''],
    $rows
);

// ---- Xdebug profiles ------------------------------------------------------------------------
$xdebug = $data['xdebug'];
echo '<h3 style="margin:18px 0 6px;">' . $esc(_AM_DEBUGBAR_AN_CG_SECTION) . '</h3>';

$dirStateLabels = [
    'unconfigured' => _AM_DEBUGBAR_AN_CG_DIR_UNCONFIGURED,
    'missing' => _AM_DEBUGBAR_AN_CG_DIR_MISSING,
    'unreadable' => _AM_DEBUGBAR_AN_CG_DIR_UNREADABLE,
    'ok' => _AM_DEBUGBAR_AN_CG_DIR_OK,
];

echo '<p>'
    . $esc(_AM_DEBUGBAR_AN_CG_EXTENSION) . ': ' . $esc(true === $xdebug['extension_loaded'] ? _AM_DEBUGBAR_AN_CG_LOADED : _AM_DEBUGBAR_AN_CG_NOT_LOADED) . ' &nbsp; '
    . $esc(_AM_DEBUGBAR_AN_CG_MODES) . ': ' . $esc([] !== $xdebug['modes'] ? implode(', ', $xdebug['modes']) : '—') . ' &nbsp; '
    . $esc(_AM_DEBUGBAR_AN_CG_START) . ': ' . $esc('' !== $xdebug['start_with_request'] ? $xdebug['start_with_request'] : '—') . '<br>'
    . $esc(_AM_DEBUGBAR_AN_CG_DIR) . ': ' . $esc('' !== $xdebug['output_dir'] ? $xdebug['output_dir'] : '—')
    . ' (' . $esc($dirStateLabels[$xdebug['output_dir_state']] ?? $xdebug['output_dir_state']) . ') &nbsp; '
    . $esc(_AM_DEBUGBAR_AN_CG_ZLIB) . ': ' . $esc(true === $xdebug['zlib'] ? _AM_DEBUGBAR_AN_CG_LOADED : _AM_DEBUGBAR_AN_CG_NOT_LOADED)
    . '</p>';

if (isset($xdebug['shared_dir_warning']) && true === $xdebug['shared_dir_warning']) {
    echo '<p style="color:#a60;">' . $esc(_AM_DEBUGBAR_AN_CG_SHARED_DIR) . '</p>';
}

if (true !== $xdebug['can_trigger']) {
    echo '<p>' . $esc(_AM_DEBUGBAR_AN_CG_INI_HINT) . '</p>'
        . '<pre style="background:#f7f7f7;border:1px solid #ddd;padding:10px;font-family:monospace;">'
        . "xdebug.mode=develop,profile\n"
        . "xdebug.start_with_request=trigger\n"
        . 'xdebug.output_dir="C:/wamp64/tmp/xdebug"' . "\n"
        . 'xdebug.profiler_append=0'
        . '</pre>';
}

$tokenHtml = $GLOBALS['xoopsSecurity']->getTokenHTML();

$rows = [];
foreach ($data['cachegrind_files'] as $cgRow) {
    $encodedFile = rawurlencode($cgRow['file']);
    $deleteForm = '<form method="post" action="analytics.php" style="display:inline;margin:0;">'
        . '<input type="hidden" name="op" value="cg_delete">'
        . '<input type="hidden" name="cg_file" value="' . $esc($cgRow['file']) . '">'
        . $tokenHtml
        . '<button type="submit" class="formButton">' . $esc(_AM_DEBUGBAR_AN_DELETE) . '</button>'
        . '</form>';
    $rows[] = [
        $esc(formatTimestamp((int) $cgRow['modified'], 'mysql')),
        $esc($cgRow['file']),
        $esc(number_format($cgRow['size'] / 1024, 1)),
        '<a href="analytics.php?cg=' . $esc($encodedFile) . '">' . $esc(_AM_DEBUGBAR_AN_CG_VIEW) . '</a>',
        $deleteForm,
    ];
}
// The section already carries an <h3> above; passing a caption here too would
// print the same heading twice.
echo $table(
    '',
    [_AM_DEBUGBAR_AN_WHEN, _AM_DEBUGBAR_AN_CG_FILE, _AM_DEBUGBAR_AN_CG_SIZE, '', ''],
    $rows
);

echo '<form method="post" action="analytics.php" style="margin:8px 0;">'
    . '<input type="hidden" name="op" value="cg_purge">'
    . $tokenHtml
    . '<button type="submit" class="formButton">' . $esc(_AM_DEBUGBAR_AN_CG_PURGE) . '</button>'
    . '</form>';

echo '<p style="color:#777;">' . $esc(_AM_DEBUGBAR_AN_CG_ALTERNATIVES) . '</p>';

require_once __DIR__ . '/admin_footer.php';
