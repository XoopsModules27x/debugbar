(function () {
    'use strict';

    var script = document.currentScript;
    var profileConfig = null;
    if (script && script.dataset) {
        window.phpdebugbar_explain = {
            url: script.dataset.explainUrl || '',
            token: script.dataset.explainToken || ''
        };
        profileConfig = {
            enabled: script.dataset.profileButton === '1',
            trigger: script.dataset.profileTrigger || '1',
            label: script.dataset.profileLabel || 'Profile this request',
            loadingLabel: script.dataset.profileLoadingLabel || 'Profiling…'
        };
    }

    function removeOneShotProfileTrigger() {
        if (!window.URL || !window.history || !window.history.replaceState) {
            return;
        }
        var url = new URL(window.location.href);
        if (url.searchParams.get('_debugbar_profile_once') !== '1') {
            return;
        }
        url.searchParams.delete('XDEBUG_TRIGGER');
        url.searchParams.delete('_debugbar_profile_once');
        window.history.replaceState(window.history.state, document.title, url.toString());
    }

    function addProfileButton() {
        if (!profileConfig || !profileConfig.enabled || !window.URL) {
            return;
        }
        var header = document.querySelector('div.phpdebugbar-header-right');
        if (!header || header.querySelector('.phpdebugbar-profile-request')) {
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'phpdebugbar-profile-request';
        button.textContent = profileConfig.label;
        button.title = profileConfig.label;
        button.addEventListener('click', function () {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = profileConfig.loadingLabel;

            var url = new URL(window.location.href);
            url.searchParams.set('XDEBUG_TRIGGER', profileConfig.trigger);
            url.searchParams.set('_debugbar_profile_once', '1');
            window.location.assign(url.toString());
        });
        header.insertBefore(button, header.firstChild);
    }

    removeOneShotProfileTrigger();

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

        addProfileButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addFrontendCollector);
    } else {
        addFrontendCollector();
    }
}());
