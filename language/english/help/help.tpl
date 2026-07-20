<div id="help-template" class="outer">
    <{include file=$smarty.const._MI_DEBUGBAR_HELP_HEADER}>

    <h4 class="odd">OVERVIEW</h4>
    <div class="even">
        <p>DebugBar is an administrator-focused diagnostics and performance module for XOOPS. It provides:</p>
        <ul>
            <li>a browser toolbar for the current request;</li>
            <li>performance Analytics across stored request profiles;</li>
            <li>a protected viewer for XOOPS and Monolog logs;</li>
            <li>system and dependency Diagnostics;</li>
            <li>optional Xdebug profiling, Ray forwarding, Tracy status/control, and xWhoops context.</li>
        </ul>
        <p>The toolbar is intended for development and troubleshooting. Disable XOOPS Debug when testing is complete, especially on public production sites.</p>
    </div>

    <h4 class="odd">REQUIREMENTS AND INSTALLATION</h4>
    <div class="even">
        <ul>
            <li>XOOPS 2.7.0 or newer;</li>
            <li>PHP 8.2 or newer;</li>
            <li><code>php-debugbar/php-debugbar</code> available through the XOOPS Composer autoloader;</li>
            <li>writable XOOPS variable-data and module asset directories during installation or update.</li>
        </ul>
        <p>Copy the module to <code>modules/debugbar</code>, install it through the XOOPS module manager, and review its Preferences. After upgrading to DebugBar 1.3.0, run the XOOPS module <strong>Update</strong> operation once to register new preferences, refresh browser assets, and create the protected EXPLAIN signing key.</p>
        <p>Ray, Tracy, xWhoops, Xdebug, and enhanced Monolog support are optional. Their absence does not prevent DebugBar from operating.</p>
    </div>

    <h4 class="odd">SHOW THE BROWSER TOOLBAR</h4>
    <div class="even">
        <ol>
            <li>Open <strong>Administration &gt; DebugBar &gt; Home</strong>.</li>
            <li>Select <strong>Turn XOOPS Debug ON</strong>.</li>
            <li>Select <strong>Turn DebugBar toolbar ON</strong>.</li>
            <li>Confirm that <strong>Display DebugBar</strong> is set to Yes in Preferences.</li>
            <li>Reload a front-end page while signed in as a site administrator.</li>
        </ol>
        <p>The module must be active, XOOPS Debug must be enabled, the toolbar preference must be enabled, and the current account must pass XOOPS's site-administrator check. Module-only administration permission may not be sufficient.</p>
    </div>

    <h4 class="odd">RECOMMENDED STARTING SETTINGS</h4>
    <div class="even">
        <ul>
            <li><strong>Query Logging:</strong> Slow &amp; errors only;</li>
            <li><strong>Smarty Debug:</strong> No, except while actively inspecting templates;</li>
            <li><strong>Included Files:</strong> No, except for short investigations;</li>
            <li><strong>Monolog level:</strong> Warning;</li>
            <li><strong>Store request profiles:</strong> Yes, with bounded retention and row limits;</li>
            <li><strong>Bootstrap budget:</strong> 0 initially; observe normal warm and cold requests before choosing a local millisecond threshold;</li>
            <li><strong>Repeated-query threshold:</strong> 5 is a practical start; 0 disables the check and 1 is treated as 2.</li>
        </ul>
        <p>Full query lists, Smarty context, included files, verbose logging, Ray, and Xdebug profiling can add overhead. Enable them only when they answer a specific diagnostic question.</p>
    </div>

    <h4 class="odd">ADMINISTRATION PAGES</h4>
    <div class="even">
        <ul>
            <li><strong>Home:</strong> shows the effective debug state and provides protected controls for XOOPS Debug, the DebugBar toolbar, and an available Tracy bootstrap.</li>
            <li><strong>Analytics:</strong> identifies slow URLs, repeated queries, per-module trends, budget violations, flight records, OPcache health, and Xdebug profiles.</li>
            <li><strong>Logs:</strong> reads a bounded tail of allowlisted XOOPS and Monolog files and formats structured entries.</li>
            <li><strong>Diagnostics:</strong> checks runtime versions, themes, optional libraries, writable storage, theme files, and EXPLAIN-key readiness without displaying secrets or absolute paths.</li>
        </ul>
    </div>

    <h4 class="odd">SECURITY AND PRIVACY</h4>
    <div class="even">
        <ul>
            <li>The browser toolbar and diagnostic administration pages are administrator-only.</li>
            <li>Structured request, URL, cookie, header, HTTP, mail, Smarty, Profiler, and xWhoops data is bounded and sanitized.</li>
            <li>Arbitrary secrets embedded inside preformatted log-message text cannot be detected reliably; review logs before sharing them.</li>
            <li>Monolog collection is site-wide when enabled, although only events at or above its configured level are written.</li>
            <li>The EXPLAIN key is stored under <code>XOOPS_VAR_PATH/data</code>. If that directory is below the document root, ensure the web server denies direct access; non-Apache servers do not use XOOPS <code>.htaccess</code> rules.</li>
        </ul>
    </div>

    <h4 class="odd">QUICK TROUBLESHOOTING</h4>
    <div class="even">
        <p>If the toolbar is missing, confirm the administrator account, XOOPS Debug state, Display DebugBar preference, module activation, and normal XOOPS footer rendering. Run the module Update operation if assets or new preferences are missing.</p>
        <p>If Analytics is empty, enable stored request profiles and browse several pages as an administrator with XOOPS Debug active. If EXPLAIN is unavailable, open Diagnostics and check the signing-key and writable-data rows.</p>
    </div>

    <h4 class="odd">DETAILED TUTORIALS</h4>
    <div class="even">
        <ul>
            <li><a href="<{$smarty.const.XOOPS_URL}>/modules/debugbar/docs/using-debugbar.md" target="_blank" rel="noopener noreferrer">Using XOOPS DebugBar</a> — practical workflows for developers, webmasters, and theme builders.</li>
            <li><a href="<{$smarty.const.XOOPS_URL}>/modules/debugbar/docs/extending-debugbar.md" target="_blank" rel="noopener noreferrer">Extending XOOPS DebugBar</a> — lifecycle, collectors, metrics, integrations, security rules, and testing.</li>
            <li><a href="<{$smarty.const.XOOPS_URL}>/modules/debugbar/docs/ray-integration.md" target="_blank" rel="noopener noreferrer">Ray Integration</a> — optional installation, behavior, Smarty helpers, and troubleshooting.</li>
            <li><a href="<{$smarty.const.XOOPS_URL}>/modules/debugbar/README.md" target="_blank" rel="noopener noreferrer">README</a> — installation, features, optional dependencies, and release overview.</li>
            <li><a href="<{$smarty.const.XOOPS_URL}>/modules/debugbar/CHANGELOG.md" target="_blank" rel="noopener noreferrer">Changelog</a> — version history and current changes.</li>
        </ul>
        <p>These are Markdown source documents. A browser may display them as plain text; they are also suitable for GitHub and Markdown viewers.</p>
    </div>

    <h4 class="odd">SUPPORT AND CONTRIBUTING</h4>
    <div class="even">
        <p>For community support, visit the <a href="https://xoops.org/modules/newbb/viewforum.php?forum=28/" target="_blank" rel="noopener noreferrer">XOOPS support forum</a>. Source development takes place in the <a href="https://github.com/XOOPS/XoopsCore27" target="_blank" rel="noopener noreferrer">XOOPS Core repository</a>.</p>
    </div>
</div>
