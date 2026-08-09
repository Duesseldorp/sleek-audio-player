# Development Guide

Single source of truth for how this plugin is developed and released.
AI assistant rule files (`.windsurfrules`, `CLAUDE.md`) point here — if you
change a convention, change it in this file first.

## Project layout

| Path | Purpose |
|---|---|
| `sleek-audio-player.php` | Entire PHP backend: `SAP_Theme_Manager`, `SAP_Waveform_Manager`, `Simple_Audio_Player` (shortcode, block, REST, meta boxes, embed, SEO) |
| `assets/js/player.js` | Frontend player (vanilla JS, no dependencies) |
| `assets/js/player.min.js` | **Generated** — never edit by hand |
| `assets/css/player.css` | Frontend styles |
| `assets/css/player.min.css` | **Generated** — never edit by hand |
| `assets/js/admin.js` | Playlist editor (jQuery, backend only) |
| `assets/js/block.js` | Gutenberg block (editor only) |
| `languages/` | `.pot` template, `.po` translations, **generated** `.mo` binaries |
| `tools/minify.py` | Builds the `.min` assets (`pip install rjsmin rcssmin`) |
| `tools/po2mo.py` | Compiles `.po` → `.mo` (stdlib only) |

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
2. `sleek-audio-player.php` → `define('SAP_VERSION', 'X.Y.Z');`
3. `readme.txt` → `Stable tag: X.Y.Z`
4. `readme.md` → `**Current Version:** X.Y.Z`

Then:

5. Changelog entry in **both** `readme.md` (detailed) and `readme.txt` (bullet style)
6. Run both build steps above if sources changed
7. `SAP_VERSION` is the cache-buster for enqueued assets — shipping a code
   change without a version bump means cached sites never receive it

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
4. `sap_validate_track()` defaults

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

There is no automated test suite yet, so before committing test at minimum:

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
- `WP_DEBUG` + `WP_DEBUG_LOG` enabled — plugin debug output (`sap_log`) lands in
  `wp-content/debug.log` of the test site

Test every change there first. **Never test against the production site**
(https://www.duesseldorp.de/) — it runs the released, minified build.
If the test site is unreachable, start the site in the LocalWP app first.

## Git workflow

- `main` is the released state; work happens on branches
  (`release/X.Y.Z` for releases, `fix/...` for isolated fixes)
- PRs target `main`; the PR description explains what changed and why
- Commit messages: summary line, then the reasoning (what broke, why this fix)
