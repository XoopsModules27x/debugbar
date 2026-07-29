(function () {
    'use strict';

    var script = document.currentScript;
    if (script && script.dataset) {
        window.phpdebugbar_explain = {
            url: script.dataset.explainUrl || '',
            token: script.dataset.explainToken || '',
            requestId: script.dataset.explainRequestId || ''
        };
    }

    // The Xdebug profile button lives in assets/xoops-debugbar-xdebug.js: it
    // arms a one-shot capture server-side through xdebug-arm.php instead of
    // putting XDEBUG_TRIGGER into the URL.

    if (typeof phpdebugbar !== 'undefined' && typeof phpdebugbar._initSettings === 'function') {
        try {
            phpdebugbar._initSettings();
        } catch (e) {
            // Settings are optional and must never affect the page.
        }
    }

    function addFrontendCollector() {
        if (typeof phpdebugbar === 'undefined' || !phpdebugbar.createTab || !window.performance) {
            return;
        }

        var entries = window.performance.getEntriesByType ? window.performance.getEntriesByType('resource') : [];
        var navigation = window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation')[0] : null;
        var messages = [];

        if (navigation) {
            messages.push({message: 'DOM interactive: ' + navigation.domInteractive.toFixed(1) + ' ms', label: 'info'});
            messages.push({message: 'DOMContentLoaded: ' + navigation.domContentLoadedEventEnd.toFixed(1) + ' ms', label: 'info'});
            messages.push({message: 'Load event: ' + navigation.loadEventEnd.toFixed(1) + ' ms', label: 'info'});
            if (navigation.transferSize) {
                messages.push({message: 'Transferred: ' + Math.round(navigation.transferSize / 1024) + ' KB', label: 'info'});
            }
        }

        messages.push({message: 'Resources: ' + entries.length, label: 'info'});
        entries.slice().sort(function (a, b) {
            return (b.duration || 0) - (a.duration || 0);
        }).slice(0, 5).forEach(function (entry) {
            messages.push({
                message: 'Slow resource: ' + entry.name + ' (' + entry.duration.toFixed(1) + ' ms)',
                label: entry.duration >= 500 ? 'warning' : 'info'
            });
        });

        // Keep a small, browser-local trail of recent profiles. It is bounded,
        // contains no request parameters, and can be cleared with browser storage.
        try {
            var history = JSON.parse(localStorage.getItem('xoops-debugbar-history') || '[]');
            history.unshift({
                time: new Date().toISOString(),
                url: window.location.pathname,
                load: navigation ? Number(navigation.loadEventEnd.toFixed(1)) + ' ms' : 'n/a',
                resources: entries.length
            });
            history = history.slice(0, 10);
            localStorage.setItem('xoops-debugbar-history', JSON.stringify(history));

            var historyWidget = new PhpDebugBar.Widgets.MessagesWidget();
            var historyTab = phpdebugbar.createTab('History', historyWidget, 'History');
            historyWidget.set('data', history.map(function (item) {
                return {
                    message: item.time + ' — ' + item.url + ' — load ' + item.load + ', resources ' + item.resources,
                    label: 'info'
                };
            }));
            if (historyTab && historyTab.set) {
                historyTab.set('data', history);
            }
        } catch (e) {
            // Storage may be disabled; history is optional.
        }

        try {
            var widget = new PhpDebugBar.Widgets.MessagesWidget();
            var tab = phpdebugbar.createTab('Frontend', widget, 'Frontend');
            widget.set('data', messages);
            if (tab && tab.set) {
                tab.set('data', messages);
            }
        } catch (e) {
            // Frontend metrics are optional and must never affect the page.
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addFrontendCollector);
    } else {
        addFrontendCollector();
    }
}());
