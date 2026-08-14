# Sleek Audio Player

WordPress audio player plugin. **Read DEVELOPMENT.md before making changes** —
it is the single source of truth for all conventions. The same rules are
mirrored in `.windsurfrules` for other AI tools; keep the three files in sync
by editing DEVELOPMENT.md first.

Hard constraints (details in DEVELOPMENT.md):

- `*.min.js` / `*.min.css` / `*.mo` are generated — never edit by hand.
  After changing `player.js`/`player.css` run `python tools/minify.py`;
  after changing `.po` files run `python tools/po2mo.py`. Commit build
  outputs together with the sources.
- Version must match in four places (plugin header, `SLEEKAUDIO_VERSION`,
  readme.txt Stable tag, readme.md Current Version) and every user-facing
  change gets a changelog entry in both readme.md and readme.txt.
- Releases are automated: `git tag vX.Y.Z && git push origin vX.Y.Z` — the
  workflow verifies builds and publishes the GitHub Release. Never create
  releases or release ZIPs by hand.
- Refactoring: never change structure and behaviour in the same commit; keep
  the public surface (options, post type, meta keys, shortcodes, CSS classes,
  AJAX actions) frozen; **a step is only done once it is released** (bump,
  changelog, green CI, tag). Details in DEVELOPMENT.md.
- Frontend assets load only on pages that render a player — never add an
  unconditional `wp_enqueue_*` for them.
- Player markup: no `<br>`/`<p>`, no whitespace-dependent layout
  (`render_player` strips inter-tag whitespace as wpautop defense).
- Track meta fields exist in four places (meta box form, `addTrackRow()`,
  `save_meta()`, `sleekaudio_validate_track()`) — always change all four.
- AJAX: nonce + capability check. All output escaped, all input sanitized.
- No hardcoded user-facing strings in `player.js` — use `sapSettings.i18n`.
- PHP 7.4 / WP 5.0 compatible; `player.js` dependency-free; legacy
  `[simple_player]` shortcode must keep working.
- CI (`.github/workflows/ci.yml`) runs on every push: syntax (PHP 7.4/8.3,
  JS), build sync, ZIP cleanliness, version consistency, Plugin Check, and
  Playwright end-to-end tests (`tests/e2e/`). Behaviour changes need a test;
  selectors belong in `tests/e2e/helpers/player.js`, content in
  `tests/seed.php`. Real devices/locked screens stay manual.
- Test on: a shortcode page, a page-builder page, and a page without a
  player (must load zero plugin assets). Never test against production.
  Local test site exists at https://duesseldorp-test.local/ (LocalWP,
  repo junction-linked, SCRIPT_DEBUG on — edits are live instantly).
