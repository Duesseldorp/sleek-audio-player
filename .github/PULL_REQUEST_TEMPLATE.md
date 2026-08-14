# What does this change?

<!-- Short description of the change and, more importantly, why it's needed.
     If it fixes an issue: "Fixes #123" -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Documentation
- [ ] Refactoring / maintenance

## Testing

<!-- How did you verify this works? -->

- [ ] Tested on a page with a player (shortcode or block)
- [ ] Tested on a page **without** a player — no plugin assets in the page source
- [ ] Tested a page-builder page (if the change touches rendering)
- [ ] Tested a full track-to-track transition (if the change touches playback)

**Environment tested:** <!-- WordPress version, PHP version, browser/device -->

## Checklist

<!-- See DEVELOPMENT.md for the full conventions -->

- [ ] I read [DEVELOPMENT.md](../DEVELOPMENT.md)
- [ ] Ran `python tools/minify.py` and committed the `.min` files (if `player.js`/`player.css` changed)
- [ ] Ran `python tools/po2mo.py` and committed the `.mo` files (if translations changed)
- [ ] No hardcoded user-facing strings in `player.js` (used `sapSettings.i18n`)
- [ ] AJAX changes have a nonce **and** a capability check
- [ ] Output escaped, input sanitized
- [ ] Track meta field changes touch all **four** places
- [ ] Compatible with PHP 7.4 and WordPress 5.0
- [ ] I did **not** bump the version number (releases are handled by the maintainer)
