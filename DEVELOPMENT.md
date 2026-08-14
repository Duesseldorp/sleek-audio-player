# Development Guide

Single source of truth for how this plugin is developed and released.
AI assistant rule files (`.windsurfrules`, `CLAUDE.md`) point here — if you
change a convention, change it in this file first.

## Project layout

| Path | Purpose |
|---|---|
| `sleek-audio-player.php` | Entire PHP backend: `SleekAudio_Theme_Manager`, `SleekAudio_Waveform_Manager`, `SleekAudio_Player` (shortcode, block, REST, meta boxes, embed, SEO) |
| `assets/js/player.js` | Frontend player (vanilla JS, no dependencies) |
| `assets/js/player.min.js` | **Generated** — never edit by hand |
| `assets/css/player.css` | Frontend styles |
| `assets/css/player.min.css` | **Generated** — never edit by hand |
| `assets/js/admin.js` | Playlist editor (jQuery, backend only) |
| `assets/js/block.js` | Gutenberg block (editor only) |
| `languages/` | `.pot` template, `.po` translations, **generated** `.mo` binaries |
| `tools/minify.py` | Builds the `.min` assets (`pip install rjsmin rcssmin`) |
| `tools/po2mo.py` | Compiles `.po` → `.mo` (stdlib only) |
| `tools/build-zip.py` | Builds the distribution ZIP (`dist/`) for wordpress.org — whitelist-based, dev files can never leak in |
| `readme.txt` | wordpress.org readme (**shipped**) — changelog, FAQ, External Services section |
| `readme.md` | GitHub readme (repo only) — changelog kept in sync with readme.txt |

### Repository-only files (never shipped in the plugin ZIP)

These serve GitHub and contributors; `tools/build-zip.py` excludes them by
whitelist, so they cannot end up in a wordpress.org submission:

| Path | Purpose |
|---|---|
| `LICENSE` | Verbatim GPL-2.0 text — makes GitHub detect the license (the plugin header and readmes state it too) |
| `SECURITY.md` | Private vulnerability reporting (GitHub advisories + kontakt@duesseldorp.de), response times, scope. Linked from readme.md and rendered at the repo's Security tab |
| `CONTRIBUTING.md` | Contributor entry point — points here for the conventions |
| `CODE_OF_CONDUCT.md` | Short, plain-language conduct rules |
| `.github/ISSUE_TEMPLATE/` | Issue forms; the bug form requires device, browser, WP version and embedding method up front |
| `.github/PULL_REQUEST_TEMPLATE.md` | PR checklist mirroring this document |
| `.github/workflows/ci.yml` | Static checks on every push/PR (see Testing a change) |
| `.github/workflows/release.yml` | Tag-triggered release automation (see Release checklist) |
| `DEVELOPMENT.md`, `CLAUDE.md`, `.windsurfrules` | Conventions for humans and AI assistants |

Keep these in sync when conventions change: edit `DEVELOPMENT.md` first, then
mirror the hard constraints into `CLAUDE.md` and `.windsurfrules`.

## Build steps (the two rules you must not forget)

1. **After every change to `player.js` or `player.css`:**
   ```bash
   python tools/minify.py
   ```
   The `.min` files are committed and served in production. If you skip this,
   visitors keep getting the old code even though the source changed.
   For local development set `define('SCRIPT_DEBUG', true);` in `wp-config.php`
   — WordPress then serves the readable sources and you only need to rebuild
   once before committing.

2. **After every change to a `.po` file or to translatable strings:**
   ```bash
   python tools/po2mo.py
   ```
   WordPress only loads the compiled `.mo`. New strings also go into
   `languages/sleek-audio-player.pot` and `languages/sleek-audio-player-de_DE.po`.

## Release checklist

Version lives in **four places** — all must match:

1. `sleek-audio-player.php` plugin header → ` * Version: X.Y.Z`
2. `sleek-audio-player.php` → `define('SLEEKAUDIO_VERSION', 'X.Y.Z');`
3. `readme.txt` → `Stable tag: X.Y.Z`
4. `readme.md` → `**Current Version:** X.Y.Z`

Then:

5. Changelog entry in **both** `readme.md` (detailed) and `readme.txt` (bullet style)
6. Run both build steps above if sources changed
7. `SLEEKAUDIO_VERSION` is the cache-buster for enqueued assets — shipping a code
   change without a version bump means cached sites never receive it
8. **Publish the GitHub Release:** `git tag vX.Y.Z && git push origin vX.Y.Z` —
   the Release workflow (`.github/workflows/release.yml`) then verifies that
   `.min`/`.mo` files are in sync and the tag matches the plugin version,
   builds the distribution ZIP, and publishes the Release with changelog
   notes automatically. Never build release ZIPs by hand for GitHub.

Versioning: patch for fixes, minor for new features **or behavior changes**
(e.g. 2.5.0 because asset loading changed site-wide).

## Architecture conventions

### Asset loading (do not regress this)

Frontend assets load **only on pages that render a player**:

- `register_assets()` registers (never enqueues) style + script
- `maybe_enqueue_assets()` (on `wp_enqueue_scripts`) enqueues early only when a
  player is detectable: `is_singular('sap_playlist')`, `has_shortcode`, `has_block`
- `render_player()` late-enqueues for page-builder/widget/template rendering and
  prints the stylesheet inline if `wp_head` already ran (avoids FOUC)

**Never add an unconditional `wp_enqueue_*` for frontend assets.**

### wpautop safety

`render_player()` strips all whitespace between tags before returning, because
page builders may run `wpautop` over the shortcode output (turning template
newlines into stray `<br>`/`<p>` that break the layout — this happened on the
production homepage). Therefore:

- Player markup must never rely on whitespace between tags
- Player markup must never contain literal `<br>` or `<p>` elements
  (`player.css` hides/neutralizes foreign ones as a safety net)

### Track meta fields

A track field exists in **four places** — adding or renaming one means
touching all four (a missed one silently destroys data on the next save,
as happened with `duration`):

1. PHP meta box form in `render_meta_box` (hidden/text input per track)
2. `addTrackRow()` template in `assets/js/admin.js`
3. `save_meta()` sanitization whitelist
4. `sleekaudio_validate_track()` defaults

### Security (non-negotiable)

- Every AJAX handler: `check_ajax_referer` **and** `current_user_can`
- Every output: `esc_attr` / `esc_url` / `esc_html` / `wp_json_encode`
- Every saved field: individually sanitized (`sanitize_text_field`,
  `esc_url_raw`, `absint`, …)
- Audio streaming tokens: HMAC-SHA256 via `wp_salt('auth')`, verified with
  `hash_equals`, time-limited

### i18n

- PHP strings: `__('...', 'sleek-audio-player')`
- Frontend JS strings: never hardcode user-facing text in `player.js`; pass it
  through the `sapSettings.i18n` array in `register_assets()`
  (`wp_localize_script`), with an English fallback in the JS
- Text domain loads via `load_plugin_textdomain` on `init`

### Compatibility

- The legacy `[simple_player]` shortcode alias must keep working
- Requires PHP 7.4+ / WordPress 5.0+ — no syntax or APIs newer than that in PHP
- `player.js` runs as-is in browsers (no transpiler): ES6 is fine,
  keep it dependency-free and jQuery-free

## Testing a change

### What CI checks automatically

`.github/workflows/ci.yml` runs on every push and pull request and fails on:

- PHP syntax errors under **PHP 7.4 and 8.3** (the supported range)
- JavaScript syntax errors (`node --check` — the dev machine has no Node,
  so this is the only real parser check that exists)
- `.min` / `.mo` files that are out of sync with their sources
- development files leaking into the distribution ZIP
- version mismatches across the four locations
- WordPress Plugin Check findings (keeps wordpress.org compliance intact)

CI does **not** test behaviour — a green run means "it parses, builds and
complies", not "it plays audio".

### What you still have to test by hand

Browser behaviour is not covered yet, so before committing test at minimum:

1. **A regular page with a player** (shortcode in content → early enqueue path)
2. **A page-builder page with a player** (e.g. the production homepage layout →
   late enqueue + wpautop path)
3. **A page without a player** — view source: no `sleek-audio-player` assets
   may be present
4. For playback changes: a full track-to-track transition and the mobile
   autoplay path (browser autoplay policies differ; see the
   `visibilitychange` resume handling in `player.js`)

### Local test environment (exists — use it)

A LocalWP site is set up on the development machine at
**https://duesseldorp-test.local/** with:

- this repo **junction-linked** into `wp-content/plugins/sleek-audio-player`
  — every edit in the repo is live on the test site immediately, no copying
- `SCRIPT_DEBUG` enabled — the site serves the readable `player.js`/`player.css`
  sources, so you can test JS/CSS changes **without** running the minify build
  (the build is still required before committing)
- `WP_DEBUG` + `WP_DEBUG_LOG` enabled — plugin debug output (`sleekaudio_log`) lands in
  `wp-content/debug.log` of the test site

Test every change there first. **Never test against the production site**
(https://www.duesseldorp.de/) — it runs the released, minified build.
If the test site is unreachable, start the site in the LocalWP app first.

## WordPress.org submission

The plugin was submitted to the wordpress.org plugin directory on 2025-12-28
(slug `sleek-audio-player`, wordpress.org account: `d0211` — must stay listed
as Contributor in readme.txt). The review runs as an **email thread** with
plugins@wordpress.org:

- **Never resubmit the plugin** via the submission form — always reply to the
  existing email thread with an updated ZIP attached (rule from the team;
  the thread stays open "even years")
- Build the ZIP with `python tools/minify.py && python tools/build-zip.py`
  → `dist/sleek-audio-player-<version>.zip` (whitelist-based, no dev files)
- Reply promptly to reviewer emails — 90 days without progress triggers an
  automatic rejection (happened once in March 2026; resolved by replying to
  the rejection email with the 2.5.2 ZIP on 2026-08-09)
- Directory guidelines already implemented and to be preserved: unique global
  prefixes (`sleekaudio_` / `SleekAudio_` / `SLEEKAUDIO_`), prepared SQL /
  documented table-name escaping, "External Services" section in readme.txt,
  `Tested up to:` never above the released WP version

## Git workflow

- `main` is the released state; work happens on branches
  (`release/X.Y.Z` for releases, `fix/...` for isolated fixes)
- PRs target `main`; the PR description explains what changed and why
- Commit messages: summary line, then the reasoning (what broke, why this fix)
