# DebugBar 1.4.0 file inventory

This inventory describes the standalone `modules/debugbar` release tree. The three peer-review working notes (`claude.md`, `grok.md`, and `codex.md`) are development artifacts and are not included in the release count.

## Release summary

| Area | Files | Purpose |
|---|---:|---|
| Module root | 7 | Manifest, entry points, README, and changelog |
| Administration | 8 | Home, Analytics, Logs, Diagnostics, navigation, and wrappers |
| PHP classes | 23 | Collection, profiling, storage, diagnostics, and optional Ray bridge |
| Install and preload | 4 | Autoloading, lifecycle registration, schema/assets/key setup |
| Language and help | 10 | English strings and XOOPS help templates |
| Documentation shipped | 26 | Tutorials, Ray guide, credits, legacy notes, and this inventory, plus the 20 screenshots under docs/images/ referenced by the usage guide |
| SQL | 1 | Profile table definition |
| Module asset overlay | 6 | XOOPS-specific styles and scripts reapplied during update |
| Generated browser assets | 32 | Web-readable PHP DebugBar resources plus copied overlay files |
| **Total** | **117** | Excludes the three review notes and repository tests outside the module tree |

## Module-owned source tree

```text
debugbar/
|-- README.md
|-- CHANGELOG.md
|-- xoops_version.php
|-- index.php
|-- explain.php
|-- xdebug-arm.php
|-- beacon.php
|-- admin/
|   |-- about.php
|   |-- admin_footer.php
|   |-- admin_header.php
|   |-- analytics.php
|   |-- diagnostics.php
|   |-- index.php
|   |-- logs.php
|   `-- menu.php
|-- assets-custom/
|   |-- admin-diagnostics.css
|   |-- admin-logs.css
|   |-- debugbar.css
|   |-- debugbar.js
|   |-- widgets.css
|   `-- widgets.js
|-- class/
|   |-- Admin/
|   |   |-- AccessPolicy.php
|   |   `-- AnalyticsBuilder.php
|   |-- Analysis/
|   |   |-- AssetScanner.php
|   |   |-- BudgetChecker.php
|   |   |-- CachegrindCatalog.php
|   |   |-- CachegrindParser.php
|   |   |-- CachegrindResult.php
|   |   |-- DiagnosticSanitizer.php
|   |   |-- LogCatalog.php
|   |   |-- MonologLogParser.php
|   |   |-- QueryAnalyzer.php
|   |   |-- QueryFingerprinter.php
|   |   |-- SqlRedactor.php
|   |   |-- SystemDiagnostics.php
|   |   `-- XdebugStatus.php
|   |-- DebugbarCoreConfig.php
|   |-- DebugbarLogger.php
|   |-- FlightRecorder.php
|   |-- Helper.php
|   |-- Profiler.php
|   |-- ProfileRepository.php
|   |-- RayLogger.php
|   `-- RequestShape.php
|-- include/
|   `-- install.php
|-- preloads/
|   |-- autoloader.php
|   |-- core.php
|   `-- index.html
|-- language/english/
|   |-- admin.php
|   |-- common.php
|   |-- main.php
|   |-- modinfo.php
|   `-- help/
|       |-- disclaimer.tpl
|       |-- help.tpl
|       |-- helpheader.tpl
|       |-- index.php
|       |-- license.tpl
|       `-- support.tpl
|-- docs/
|   |-- changelog.txt
|   |-- credits.txt
|   |-- extending-debugbar.md
|   |-- file-list.md
|   |-- ray-integration.md
|   `-- using-debugbar.md
`-- sql/
    `-- mysql.sql
```

## Generated browser assets

`assets/` contains the web-readable PHP DebugBar distribution copied during module install/update. After that copy, the six files from `assets-custom/` are overlaid so XOOPS-specific toolbar, Analytics, Logs, and Diagnostics behavior remains intact. The installer then applies a small, explicit set of compatibility and security corrections to vendor-owned files that are not duplicated in the overlay. Every transformation accepts an already-corrected file but fails the update when neither the expected vendor source nor the corrected form is found, preventing silent vendor drift. The RUM collector, `assets/xoops-debugbar-rum.js`, is a module-owned addition shipped directly under `assets/` and is included in this count. Do not hand-edit generated copies in `assets/`; edit `assets-custom/` for module-owned files or update the guarded post-copy patch list in `include/install.php` for a vendor-owned file, then run the XOOPS module update.

## Tests

The regression suite lives in the XOOPS repository at `tests/unit/modules/debugbar/`, outside the standalone module package. It covers analysis and budgets, request sanitization, the EXPLAIN secret store, logger contracts, profile storage, optional integrations, admin-page structure, log parsing, cachegrind handling, and diagnostics.
