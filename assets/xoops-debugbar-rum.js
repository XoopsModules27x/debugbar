/**
 * DebugBar Module - RUM web-vitals beacon
 *
 * Collects LCP, INP (worst interaction approximation), and CLS with native
 * PerformanceObserver APIs and posts them once to the module's beacon
 * endpoint when the page is hidden. Injected only for authenticated admins
 * with XOOPS Debug mode on — this is a dev instrument, not analytics.
 *
 * Configuration is provided by the server as:
 *   window.XoopsDebugbarRum = { url: "...", id: "...", token: "..." }
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
(function () {
    'use strict';

    var config = window.XoopsDebugbarRum;
    if (!config || !config.url || !config.id || typeof PerformanceObserver === 'undefined') {
        return;
    }

    var lcp = null;
    var inp = null;
    var cls = 0;
    var sent = false;

    function observe(type, buffered, callback) {
        try {
            var observer = new PerformanceObserver(function (list) {
                callback(list.getEntries());
            });
            observer.observe({ type: type, buffered: buffered });
            return observer;
        } catch (e) {
            return null; // entry type unsupported in this browser
        }
    }

    // Largest Contentful Paint — the last candidate wins
    observe('largest-contentful-paint', true, function (entries) {
        var last = entries[entries.length - 1];
        if (last) {
            lcp = last.startTime;
        }
    });

    // Cumulative Layout Shift — sum shifts not caused by recent input
    observe('layout-shift', true, function (entries) {
        entries.forEach(function (entry) {
            if (!entry.hadRecentInput) {
                cls += entry.value;
            }
        });
    });

    // INP approximation — worst event interaction duration
    observe('event', true, function (entries) {
        entries.forEach(function (entry) {
            if (entry.interactionId && (inp === null || entry.duration > inp)) {
                inp = entry.duration;
            }
        });
    });

    function send() {
        if (sent) {
            return;
        }
        sent = true;
        var payload = {
            token: config.token,
            id: config.id,
            lcp: lcp !== null ? Math.round(lcp * 10) / 10 : null,
            inp: inp !== null ? Math.round(inp * 10) / 10 : null,
            cls: Math.round(cls * 10000) / 10000
        };
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(config.url, JSON.stringify(payload));
            }
        } catch (e) {
            // beacon is best-effort
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            send();
        }
    });
    window.addEventListener('pagehide', send);
}());
