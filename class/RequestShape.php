<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar;

/**
 * DebugBar Module - Request Shape Helpers
 *
 * Library-neutral fragment/AJAX detection and URL normalization shared by
 * the logger and the profiler. Prefers Xmf\Http\FragmentNegotiator when
 * available and falls back to a legacy header check otherwise. Every method
 * here is defensive — it must never throw, since it runs on the hot path of
 * every profiled request.
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
 * Class RequestShape
 */
final class RequestShape
{
    /**
     * Detect a fragment/AJAX request library-neutrally (htmx, Alpine, XHR).
     *
     * Prefers Xmf\Http\FragmentNegotiator (also recognizes X-Alpine-Request);
     * falls back to the legacy 3-header check when the Xmf class is
     * unavailable or the call fails for any reason. Never throws.
     *
     * @return bool
     */
    public static function wantsFragment(): bool
    {
        if (class_exists(\Xmf\Http\FragmentNegotiator::class) && class_exists(\Xmf\Http\Message\HttpRequest::class)) {
            try {
                return \Xmf\Http\FragmentNegotiator::wantsFragment(\Xmf\Http\Message\HttpRequest::fromGlobals());
            } catch (\Throwable $e) {
                // fall through to the legacy header check
            }
        }

        return 'XMLHttpRequest' === ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')
            || '' !== ($_SERVER['HTTP_HX_REQUEST'] ?? '')
            || '' !== ($_SERVER['HTTP_X_REQUESTED_FRAGMENT'] ?? '');
    }

    /**
     * Reduce a raw request URI to a storable, privacy-safe shape: the path
     * portion plus an allowlisted query remnant (only numeric `start`/`limit`
     * survive, in that fixed order). Everything else — tokens, emails, or
     * any other query data — is dropped. Never throws; malformed input
     * degrades to the path-only portion (or the raw string truncated at the
     * first '?').
     *
     * @param string $uri    raw request URI (e.g. $_SERVER['REQUEST_URI'])
     * @param int    $maxLen maximum length of the returned string
     *
     * @return string
     */
    public static function normalizePath(string $uri, int $maxLen = 500): string
    {
        $maxLen = max(0, $maxLen);

        try {
            $parsedPath = parse_url($uri, PHP_URL_PATH);
            $path = (is_string($parsedPath) && '' !== $parsedPath) ? $parsedPath : '/';

            $remnant = '';
            $parsedQuery = parse_url($uri, PHP_URL_QUERY);
            if (is_string($parsedQuery) && '' !== $parsedQuery) {
                parse_str($parsedQuery, $params);
                $parts = [];
                foreach (['start', 'limit'] as $key) {
                    if (isset($params[$key]) && is_string($params[$key]) && '' !== $params[$key] && ctype_digit($params[$key])) {
                        $parts[] = $key . '=' . $params[$key];
                    }
                }
                if ([] !== $parts) {
                    $remnant = '?' . implode('&', $parts);
                }
            }

            return substr($path . $remnant, 0, $maxLen);
        } catch (\Throwable $e) {
            $cut = strpos($uri, '?');
            $fallback = false !== $cut ? substr($uri, 0, $cut) : $uri;

            return substr($fallback, 0, $maxLen);
        }
    }
}
