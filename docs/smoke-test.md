# DebugBar smoke test

Manual pass before tagging a release. Automated tests cover the classes; this
covers the parts only a browser can see — gating, rendering, and the endpoints.

Run the whole list on the newest supported core, then repeat the starred (*)
items on the oldest one to confirm the floor still holds. As of 1.4.0 that means
2.7.3 first, then 2.7.0/2.7.2.

**Precondition that changes several results:** if `xoops_data/data/debug.php`
enables debugbar, the admin pages stay reachable with Debug Mode off — that is
the two-gate design, not a leak. Remove the debugbar block from `debug.php`
before running section A, or expect section A's refusals not to fire.

## A. Activation and gating

| # | Step | Expected |
|---|---|---|
| A1 | Debug Mode off, DebugBar preference on | No toolbar on the front end |
| A2 | Debug Mode on, DebugBar preference off | No toolbar |
| A3 | Both on, logged out * | No toolbar; no `beacon.php` or `explain.php` in the network tab |
| A4 | `debug_mode = 3` (Smarty debug) | Toolbar renders **and** admin home reports XOOPS Debug enabled |
| A5 | Add a debugbar block to `xoops_data/data/debug.php`, Debug Mode off | Toolbar renders; admin home says enabled by debug.php and warns the button cannot turn it off |
| A6 | Remove that block | Toolbar disappears |
| A7 | Log in as a non-admin with everything on * | No toolbar, no endpoint traffic |

## B. Admin home

| # | Step | Expected |
|---|---|---|
| B1 | Open admin home | Four toggles: XOOPS Debug, DebugBar toolbar, Tracy, Ray |
| B2 | Flip each | Status row follows on reload |
| B3 | Tracy row | One of Active / Disabled / Not installed — never absent (reads "Not installed" pre-2.7.3) |
| B4 | Ray row | Correct one of its three states |

## C. Toolbar

| # | Step | Expected |
|---|---|---|
| C1 | Open each tab * | Messages, Queries, Blocks, Events, Templates, Cache, HTTP, Mail, Extra, Deprecated, Timeline, Performance all render |
| C2 | Narrow the window | Header wraps to a second line; labels stay readable (it wraps, it does not collapse to icons) |
| C3 | Switch the site theme to dark | Toolbar follows immediately, no reload |
| C4 | Queries tab | SQL is syntax-highlighted in the row, not only when expanded |
| C5 | Any row with a source location | `file:line` link present; opens PhpStorm by default |
| C6 | Templates tab on a themed page | Each template names its origin (theme override / module / db) |

## D. EXPLAIN

| # | Step | Expected |
|---|---|---|
| D1 | Page with a slow SELECT | EXPLAIN button on the query row |
| D2 | Click it | Plan appears, full width, readable; MySQL 8 tree output prints as text, not one escaped line |
| D3 | Click a second row's EXPLAIN in the same page load | Also works — the token is reusable |
| D4 | Diagnostics page | Explain-stash row reads "*N* cached stash files" |

## E. Xdebug profiling

| # | Step | Expected |
|---|---|---|
| E1 | Xdebug loaded with `mode=profile` | "Profile this request" button appears |
| E2 | Click it | Page reloads; **no `XDEBUG_TRIGGER` in the URL**; toolbar names the captured file |
| E3 | Analytics → Xdebug profiles | Real date and size, not 1969 / 0.0 KB |
| E4 | View a profile | Top functions, inclusive vs self, recursion note where applicable |
| E5 | Delete one, then purge | Both work, both behind tokens, no errors |

## F. Web vitals

| # | Step | Expected |
|---|---|---|
| F1 | Load a page, navigate away | One `beacon.php` request, **204**, empty body — nothing appended |
| F2 | Analytics → field web vitals | LCP / INP / CLS populate |

## G. Analytics and Logs

| # | Step | Expected |
|---|---|---|
| G1 | Analytics page | Worst offenders, per-module table, N+1 leaderboard, budget violations, flight recorder all render |
| G2 | Per-module table | Avg payload KB non-zero; blocks cached/uncached shows a real split |
| G3 | N+1 findings (browse a list-heavy page several times first, so the window has data) | Findings name a call site `file.php:NN`, not just a statement |
| G4 | Logs page, Debug Mode off, no debug.php block | Refused |
| G5 | Raw log view | Fills the window; long paths truncate with an ellipsis and show the full path on hover |

## H. Diagnostics

| # | Step | Expected |
|---|---|---|
| H1 | debug.php row | Reports what that file is doing — including "Load failed" if deliberately broken |
| H2 | Error handler row | Names the real handler, and agrees with the declared owner in debug.php |
| H3 | sql_mode row | Reports strict or not |

## I. Install and upgrade

| # | Step | Expected |
|---|---|---|
| I1 | Upgrade from the previous release | New columns appear, no errors |
| I2 | Run the upgrade twice | Idempotent — no errors, no duplicate columns |
| I3 | Fresh install | `editor_link` defaults to PhpStorm |
