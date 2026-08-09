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
- Version must match in four places (plugin header, `SAP_VERSION`,
  readme.txt Stable tag, readme.md Current Version) and every user-facing
  change gets a changelog entry in both readme.md and readme.txt.
- Frontend assets load only on pages that render a player — never add an
  unconditional `wp_enqueue_*` for them.
- Player markup: no `<br>`/`<p>`, no whitespace-dependent layout
  (`render_player` strips inter-tag whitespace as wpautop defense).
- Track meta fields exist in four places (meta box form, `addTrackRow()`,
  `save_meta()`, `sap_validate_track()`) — always change all four.
- AJAX: nonce + capability check. All output escaped, all input sanitized.
- No hardcoded user-facing strings in `player.js` — use `sapSettings.i18n`.
- PHP 7.4 / WP 5.0 compatible; `player.js` dependency-free; legacy
  `[simple_player]` shortcode must keep working.
- Test on: a shortcode page, a page-builder page, and a page without a
  player (must load zero plugin assets). Never test against production.
