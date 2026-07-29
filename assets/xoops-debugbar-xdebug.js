/**
 * XOOPS DebugBar Xdebug Profile Trigger Button
 *
 * Injects a "Profile this request" button into the debug bar header that
 * arms a one-shot Xdebug profile capture (via xdebug-arm.php) for the
 * browser's very next page load, then reloads the page.
 *
 * Configuration is provided by the server as:
 *   window.XoopsDebugbarXdebug = { armUrl, token, available, profiledFile,
 *                                   armFailed, analyticsUrl, labels }
 *
 * @copyright (c) 2000-2026 XOOPS Project (https://xoops.org)
 * @license   GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 */
(function () {
    'use strict';

    var config = window.XoopsDebugbarXdebug;
    if (!config || !config.armUrl || !config.token) {
        return;
    }

    var csscls = (window.PhpDebugBar && PhpDebugBar.utils && PhpDebugBar.utils.makecsscls)
        ? PhpDebugBar.utils.makecsscls('phpdebugbar-')
        : function (c) { return 'phpdebugbar-' + c; };

    function label(key, fallback) {
        return (config.labels && config.labels[key]) || fallback;
    }

    // The debug bar's header (const phpdebugbar = ...) is declared by an
    // inline script earlier in the document, but wait/retry defensively —
    // mirrors the availability-check style of xoops-debugbar-settings.js.
    function mount(tries) {
        if (typeof phpdebugbar === 'undefined' || !phpdebugbar.headerRight) {
            if (tries > 0) {
                setTimeout(function () { mount(tries - 1); }, 100);
            }
            return;
        }

        var wrap = document.createElement('span');
        wrap.className = csscls('xoops-xdebug');
        wrap.style.display = 'inline-flex';
        wrap.style.alignItems = 'center';

        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = '⏱ ' + label('button', 'Profile this request');
        wrap.appendChild(button);

        var notice = document.createElement('span');
        notice.style.fontSize = '11px';
        notice.style.opacity = '0.75';
        notice.style.marginLeft = '6px';
        wrap.appendChild(notice);

        function fail() {
            button.disabled = false;
            button.textContent = '⏱ ' + label('button', 'Profile this request');
            notice.textContent = label('failed', '');
        }

        if (!config.available) {
            button.disabled = true;
            button.title = label('notReady', '');
        } else {
            button.addEventListener('click', function () {
                button.disabled = true;
                button.textContent = label('arming', 'Arming...');

                fetch(config.armUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'token=' + encodeURIComponent(config.token)
                }).then(function (response) {
                    if (204 === response.status) {
                        window.location.reload();
                        return;
                    }
                    fail();
                }).catch(fail);
            });
        }

        if (config.profiledFile) {
            var link = document.createElement('a');
            link.href = config.analyticsUrl + '?cg=' + encodeURIComponent(config.profiledFile);
            link.textContent = config.profiledFile;
            notice.textContent = label('captured', '') + ': ';
            notice.appendChild(link);
        } else if (config.armFailed) {
            notice.textContent = label('failed', '');
        }

        phpdebugbar.headerRight.insertBefore(wrap, phpdebugbar.headerRight.firstChild);
    }

    mount(20);
}());
