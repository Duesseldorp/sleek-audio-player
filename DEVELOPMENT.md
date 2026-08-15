# Development Guide

Single source of truth for how this plugin is developed and released.
AI assistant rule files (`.windsurfrules`, `CLAUDE.md`) point here — if you
change a convention, change it in this file first.

## Project layout

| Path | Purpose |
|---|---|
| `sleek-audio-player.php` | Bootstrap: plugin header, constants, helper functions, `require`s and initialisation. Keep it small — new code belongs in `includes/` |
| `includes/class-theme-manager.php` | `SleekAudio_Theme_Manager` — colour schemes in a custom table, admin page, AJAX |
| `includes/class-waveform-manager.php` | `SleekAudio_Waveform_Manager` — waveform peaks per attachment, admin page, AJAX |
| `includes/class-player.php` | `SleekAudio_Player` — shortcodes, block, REST, meta boxes, assets, embed, SEO output |
| `assets/js/player.js` | Frontend player (vanilla JS, no dependencies) |
| `assets/js/player.min.js` | **Generated** — never edit by hand |
| `assets/css/player.css` | Frontend styles |
| `assets/css/player.min.css` | **Generated** — never edit by hand |
| `assets/js/admin.js` | Playlist editor (jQuery, backend only) |
| `assets/js/block.js` | Gutenberg block (editor only) |
| `languages/` | `.pot` template, `.po` translations, **generated** `.mo` binaries |
| `tools/minify.py` | Builds the `.min` assets (`pip install rjsmin rcssmin`) |
| `tools/make-pot.py` | **Generates** the `.pot` from the PHP sources (stdlib only) |
| `tools/po2mo.py` | Compiles `.po` → `.mo`, plural forms included (stdlib only) |
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

## Build steps (the three rules you must not forget)

1. **After every change to `player.js` or `player.css`:**
   ```bash
   python tools/minify.py
   ```
   The `.min` files are committed and served in production. If you skip this,
   visitors keep getting the old code even though the source changed.
   For local development set `define('SCRIPT_DEBUG', true);` in `wp-config.php`
   — WordPress then serves the readable sources and you only need to rebuild
   once before committing.

2. **After adding, changing or removing any translatable string:**
   ```bash
   python tools/make-pot.py
   ```
   The `.pot` is generated from the PHP sources — never edit it by hand. Then
   add the German wording for any new entry to
   `languages/sleek-audio-player-de_DE.po`. CI fails if a translatable string
   is missing from the `.pot` or has no German translation.

3. **After every change to a `.po` file:**
   ```bash
   python tools/po2mo.py
   ```
   WordPress only loads the compiled `.mo` — editing a `.po` without this step
   means the translation never reaches the site.

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
8. **Look at the change on the running local site** (https://duesseldorp-test.local/)
   before tagging, whenever the release touches anything a visitor can see. Open
   the page and read the thing you changed. Not the source, not the built file,
   not a diff — the rendered result.

   This step exists because 2.6.0 shipped without it. Its translation work was
   verified by counting keys, spot-checking the `.po` and recompiling the `.mo`;
   all three passed, all three sit *above* the layer that was broken, and every
   string on every site stayed English. Green CI does not substitute: CI runs in
   English and is structurally blind to a missing translation. Neither does
   reading the file you just wrote — that only proves what you wrote, never that
   WordPress reads it.

   For anything language-dependent, the check must happen on a site set to that
   language. The local site is German; that is the point of it.

9. **Publish the GitHub Release:** `git tag vX.Y.Z && git push origin vX.Y.Z` —
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

### Plugin paths (learned the hard way in 2.6.1)

Only `sleek-audio-player.php` may derive plugin paths from `__FILE__`. In
`includes/` those helpers resolve one directory too deep — `plugin_basename()`
returns `sleek-audio-player/includes/…` — and nothing complains at runtime.
This is how the bundled translations quietly stopped loading between 2.5.7 and
2.6.0: `load_plugin_textdomain()` searched `includes/languages/`, found
nothing, and every site kept showing English. Use `SLEEKAUDIO_PLUGIN_DIR`,
`SLEEKAUDIO_PLUGIN_URL` or `SLEEKAUDIO_PLUGIN_BASENAME`; CI rejects the rest.

### i18n

- PHP strings: `__('...', 'sleek-audio-player')`, escaped variants
  `esc_html__()` / `esc_attr__()` — **including `aria-label` and `title`
  attributes in the player markup**, which are user-facing text
- Frontend JS strings: never hardcode user-facing text in `player.js`; pass it
  through the `sapSettings.i18n` array in `register_assets()`
  (`wp_localize_script`) and read it with `sapText(key, englishFallback)`. The
  fallback is mandatory, so an unknown key renders the English original rather
  than `undefined`
- Stateful labels are stored as complete sentences (`'Repeat: One'`), never
  assembled from fragments (`'Repeat: ' + mode`) — other languages need their
  own word order. Server-rendered initial labels use the *same* strings as the
  JS, otherwise a label visibly switches language on first click
- Text domain loads via `load_plugin_textdomain` on `init`, with the path
  derived from `SLEEKAUDIO_PLUGIN_BASENAME` (see above)
- Translating is only half the job: verify against a site whose language is
  actually set to German. The local test site is (see below)

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
- a translatable string missing from the `.pot`, or missing its German
  translation (`tools/make-pot.py --check`)
- `__FILE__`-relative plugin paths inside `includes/` (see "Plugin paths")
- development files leaking into the distribution ZIP
- version mismatches across the four locations
- WordPress Plugin Check findings (keeps wordpress.org compliance intact)

CI does **not** test behaviour — a green run means "it parses, builds and
complies", not "it plays audio".

### End-to-end tests

`tests/e2e/` drives a real browser against a real WordPress instance
(`@wordpress/env` in Docker) on every push. 52 tests covering:

- **Playback**: starts, **track transitions at the end of a song** (the
  regression that shipped five times), next/previous, durations rendering
- **Repeat modes**: One restarts the track, All wraps last → first,
  Off stops at the end
- **Admin round-trip**: saving a playlist unchanged must preserve every track
  field — the only test that exercises `save_meta`, and exactly the path that
  silently destroyed all durations in 2.5.0
- **More menu**: opens, item spacing in pixels (wpautop defence), no foreign
  `<br>`/`<p>`, scroll tolerance, streaming links only where present
- **Assets**: zero plugin assets without a player, exactly one minified
  script + stylesheet with one, mini layout, playlist page with JSON-LD
- **Compatibility**: legacy `[simple_player]`, shared links
  (`?track=2&play=1`), two players on one page (the second pauses the first),
  global keyboard shortcuts (N, Space, S)
- **Controls**: playback speed (`playbackRate`), volume mute/unmute, seeking
  by clicking the waveform, sleep timer "End of Track" (stops instead of
  advancing), download button visibility per track
- **Embedding**: Gutenberg block, oEmbed endpoint (valid document, and 404 for
  a non-playlist URL), embed-code generator incl. layout switch, `?embed=1`
- **Labels**: no menu label is empty or renders `undefined`, every control has
  a non-empty accessible name, the track count uses the plural form. The suite
  runs in English, so it guards the plumbing, not the German wording — that
  still needs the German test site

Still uncovered on purpose: visualizers, adaptive colours, cover carousel and
swipe gestures — visual behaviour makes brittle tests. Uncovered and worth
adding later: URL protection with expired tokens, resume playback, and pure
PHP logic (validation, schema, token verification), which belongs in PHPUnit
rather than a browser.

Two rules learned the hard way:

- Do not add `node:` imports to files under `tests/e2e/` — it changes how
  Playwright classifies the module and the **entire suite** stops loading
  ("exports is not defined in ES module scope" / "No tests found").
- Scroll before clicking the More button, never rely on `click()`'s own
  auto-scroll (see the comment in `helpers/player.js`).

Run locally (needs Node and Docker):

```bash
npm install
npm test          # fixtures + wp-env start + seed + playwright
npm run test:e2e  # just the tests, if the environment is already up
```

**How to add a test:**

1. Put interactions and selectors in `tests/e2e/helpers/player.js` — never
   query the player DOM directly from a spec. When markup changes, that one
   file is the only thing to update.
2. Add the test to the matching spec (`playback`, `menu`, `assets`) or create
   a new `*.spec.js` — CI picks up new files automatically.
3. Need new content? Extend `tests/seed.php` (idempotent, runs before tests).
4. Need another browser or a mobile viewport? Uncomment the projects in
   `playwright.config.js`.

**Flaky tests are repaired at the root cause — never calmed with a longer
`waitForTimeout`.** A suite that goes red at random gets ignored within weeks,
and once it is ignored every other test in it is worthless too. So when a test
becomes unreliable:

1. **Wait for a condition, not for a duration.** `page.waitForFunction(...)` or
   an auto-retrying `expect(...)` — never "sleep 300ms and hope". The helper
   waits for the menu's animation to have *finished* (opacity 1, transform
   settled) rather than for an assumed 200ms.
2. **Ask whether the flake is real.** Timing that is unreliable in a test is
   often unreliable for users too. The locked-screen playback bug behaved
   exactly like a flaky test before it was understood.
3. **The only legitimate fixed wait** is when asserting that something does
   *not* happen — there is no condition to wait for. There is exactly one in
   this suite (scroll tolerance in `menu.spec.js`), and it says so in a comment.
4. **Never skip or quarantine a test to get green.** A skipped test is a lie
   about coverage. Fix it, or delete it and say so in the commit message.

**What these tests cannot cover:** real devices and locked screens, actual
audible sound, iOS Safari, third-party page builders, visual design, and
conflicts with other plugins. A green run means the logic works in headless
Chromium — not that it works on every phone.

### What you still have to test by hand

Before committing, and always for playback changes:

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

## Refactoring

The plugin is being restructured incrementally. The method is deliberate —
follow it, or the test suite stops being a safety net:

1. **Never change structure and behaviour in the same commit.** A commit either
   moves code verbatim *or* changes what it does. If a test fails after a pure
   move, the move is wrong — you never have to wonder which of two causes it was.
2. **Extend the tests for an area *before* touching it**, not after. See the
   characterization tests in `tests/e2e/characterization.spec.js`; they exist to
   make a pure move provably harmless.
3. **The public surface stays frozen**: option names, the `sap_playlist` post
   type, meta keys (`_sap_tracks`, `_sap_waveform`), shortcode names, CSS
   classes and AJAX actions are the contract with existing installs. Internal
   PHP symbols may be renamed, stored data may not.
4. **A step is not finished until it is released.** Version bump in all four
   places, changelog entry in both readmes (say plainly when there is no
   functional change), CI green, then `git tag vX.Y.Z && git push origin vX.Y.Z`.
   That is what makes the previous state one ZIP away if something surfaces
   later. CI cannot check this for you — it is on whoever does the step.
5. **Add hooks at the seams while extracting**, not afterwards. Pulling a method
   into its own class is the natural moment for an `apply_filters` around its
   return value.

Done so far: the three classes moved from the main file into `includes/`
(2.5.7). Next: split `SleekAudio_Player` into Assets / Renderer / Admin / SEO /
Streaming, one class per release. The JavaScript split is deliberately deferred
— `player.js` is a single closure with shared state, so the risk is high and the
gain small compared to the PHP side.

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
