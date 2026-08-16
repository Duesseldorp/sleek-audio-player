# Contributing

Thanks for your interest in Sleek Audio Player! Bug reports, ideas and pull
requests are all welcome.

## Reporting bugs

Open an issue using the **Bug report** template. Audio playback problems are
often device- or browser-specific, so please include the device, browser,
WordPress version and — if possible — a public URL where the problem can be
seen. That saves a round of questions and usually leads to a fix much faster.

**Security issues do not belong in public issues** — see [SECURITY.md](SECURITY.md).

## Suggesting features

Open an issue using the **Feature request** template. The plugin deliberately
stays lightweight, so the most useful thing you can describe is the *problem*
you're trying to solve, not only the feature you have in mind.

## Contributing code

Before you start on anything larger, please open an issue so we can agree on
the approach — that avoids work being thrown away.

**Read [DEVELOPMENT.md](DEVELOPMENT.md) before your first pull request.** It is
the single source of truth for the conventions of this project. The two rules
people most often miss:

1. `player.min.js` / `player.min.css` are **generated**. After changing
   `player.js` or `player.css`, run `python tools/minify.py` and commit the
   result together with your change.
2. `languages/*.mo` are **generated**. After changing a `.po` file, run
   `python tools/po2mo.py`.

Further essentials from DEVELOPMENT.md:

- Frontend assets must only load on pages that actually render a player
- Player markup must never contain `<br>`/`<p>` or depend on whitespace
- A track meta field lives in **four** places — change all of them
- Every AJAX handler needs a nonce **and** a capability check; all output
  escaped, all input sanitized
- No hardcoded user-facing strings in `player.js` — use `sapSettings.i18n`
- PHP 7.4 / WordPress 5.0 compatible; `player.js` stays dependency-free

### Testing

There is no automated test suite yet, so please test manually:

1. A page with a player via shortcode
2. A page built with a page builder
3. A page **without** a player — its source must contain no plugin assets
4. For playback changes: a full track-to-track transition

A local WordPress with `SCRIPT_DEBUG` enabled is the recommended setup. Never
test against a production site.

## Pull requests

- Branch off `main`, one topic per pull request
- Write commit messages that explain *why*, not just *what*
- Fill in the pull request template
- Don't bump the version number in your PR — releases are handled by the
  maintainer via tags

## Translations

Translations are very welcome, and there are two ways to make one.

### Just use it on your own site — no pull request needed

WordPress looks for plugin translations in `wp-content/languages/plugins/`
before it looks inside the plugin. So you can translate the plugin for your own
site without touching it, and your file survives every plugin update:

1. Open `languages/sleek-audio-player.pot` in [Poedit](https://poedit.net/)
   (or any gettext editor) and translate it
2. Save it as `sleek-audio-player-<locale>.po` — for example
   `sleek-audio-player-fr_FR.po`. The locale must match your site's language
   exactly; you can see it under Settings → General
3. Poedit writes the compiled `.mo` next to it when you save
4. Copy **the `.mo`** to `wp-content/languages/plugins/`

That is all. No code, no build step, no waiting for a release.

### Contribute it back

If you would like the translation to ship with the plugin, put the `.po` in
`languages/`, run `python tools/po2mo.py` to compile it, and open a pull
request with both files. CI checks that the `.mo` matches its `.po`.

### What to expect when translating

- The template is generated from the sources (`python tools/make-pot.py`) and
  CI fails when a translatable string is missing from it, so it is never stale
- Strings with placeholders carry a `translators:` comment explaining what each
  placeholder contains
- Stateful labels are complete sentences ("Repeat: One") rather than pieces
  glued together, so you are free to use your language's word order
- Plural forms are supported; `%s Track` / `%s Tracks` is a real plural entry
- **Right-to-left languages are not supported yet.** The stylesheet still uses
  fixed left/right rules, so Arabic or Hebrew would render with a broken
  layout. The translation itself would work; the layout would not

## License

By contributing you agree that your contributions are licensed under the
GPL v2 or later, the same license as this project.
