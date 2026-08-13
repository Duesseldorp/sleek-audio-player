# Sleek Audio Player

A modern, fast and shareable audio player for WordPress.  
Built for music, podcasts and playlists – with strong UX, clean SEO and reliable performance.

👉 Product page & background: https://www.duesseldorp.de/sleek-audio-player/

**Current Version:** 2.5.5

---

## Why Sleek Audio Player?

Most WordPress audio players are either too basic or overloaded with features that hurt performance and usability.  
I built Sleek Audio Player because I needed a solution that:

- feels modern and intuitive
- loads fast and works reliably on all devices
- makes sharing individual tracks actually useful
- treats audio as first class content, not as an afterthought

This plugin focuses on **clarity, performance and distribution** instead of visual noise.

---

## Core Features

- **Modern UI**  
  Clean layout with optional waveform and visualizer for intuitive navigation.

- **Playlist & Track Support**  
  Use single tracks or playlists without additional configuration.

- **Streaming Service Integration**  
  Add links to Spotify, Apple Music, Amazon Music and SoundCloud for each track. Links appear in the player's "More" menu.

- **Shareable Audio**  
  Track specific Open Graph and Twitter Card support so shared links show the correct title, cover and metadata.

- **Autoplay Support**  
  Optional autoplay parameter for shortcodes to start playback automatically on page load.

- **SEO for Audio Content**  
  Structured data (JSON-LD) for `MusicRecording` and `MusicPlaylist` to help search engines understand your content.

- **Performance First**  
  Lightweight frontend, no unnecessary dependencies, optimized loading behavior.

- **Mobile Friendly**  
  Touch gestures, responsive layout and smooth playback on mobile devices.

---

## Typical Use Cases

- Musicians publishing tracks or albums
- Podcasters embedding episodes and playlists
- Bands or labels sharing audio previews
- Blogs that want audio content to be discoverable and shareable

---

## Architecture & Tech

- WordPress Plugin
- PHP for backend logic
- JavaScript for playback and UI interactions
- HTML & CSS for a clean, minimal interface
- JSON-LD structured data based on Schema.org

Every technical decision aims to keep the plugin:
- understandable
- maintainable
- performant

---

## Installation

1. Download or clone this repository
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate **Sleek Audio Player** in the WordPress admin panel
4. Insert the player via block or shortcode

## Usage

### Basic Shortcode
```
[sleek_player id="123"]
```

### Wide Layout
```
[sleek_player id="123" layout="wide"]
```

### With Autoplay
```
[sleek_player id="123" autoplay="true"]
```

### Combined Parameters
```
[sleek_player id="123" layout="wide" autoplay="true"]
```

### Adding Streaming Links
In the WordPress backend, edit your playlist and add streaming service URLs for each track:
- 🎵 Spotify
- 🍎 Apple Music
- 📦 Amazon Music
- ☁️ SoundCloud

These links will appear in the player's "More" menu for each track.

Detailed usage examples are documented on the product page.

---

## Requirements

- WordPress 5.0+ and PHP 7.4+
- **Serve your site and its audio files over HTTPS.** The audio visualizer uses
  the Web Audio API, which only works when the audio is served from the **same
  origin** as the page (or from a trusted CDN) over the **same protocol**.
  If your page loads over HTTPS but an audio file is referenced over HTTP
  (mixed content), the browser blocks it and the visualizer stays disabled
  – playback may still work, but no bars/waveform animation appears.
  Make sure WordPress Site/Home URL and all stored media URLs use `https://`.

---

## Security & Privacy

- No external tracking
- No third party APIs required
- Audio files stay on your server or your configured storage
- No user data collected by the plugin itself

Security issues can be reported privately. See `SECURITY.md`.

---

## Roadmap

- [ ] Extended playlist customization
- [ ] More player layout variants
- [ ] Improved accessibility options
- [ ] Optional advanced analytics hooks

The roadmap is driven by real usage, not feature checklists.

---

## Context & Documentation

I document the design decisions and use cases behind this plugin openly on my site:  
👉 https://www.duesseldorp.de/sleek-audio-player/

---

## Changelog

### Version 2.5.5 (2026-08-13)

**Translations (German now complete for the visitor-facing UI):**
- Several player strings were already wrapped for translation in the code but were **missing from the translation catalog**, so German visitors saw English: the "More" menu (Share, Shuffle, Sleep Timer and its presets, Cover, Adaptive Colors, Embed Player) and the embed dialog (Layout, Embed Code, Copy Code, Preview, Close). These are now translated
- Added the missing post-type labels (`Add New`, `All Playlists`) and the `No playable tracks found.` notice
- Rebuilt `sleek-audio-player.pot` and `sleek-audio-player-de_DE.po` to reflect the code exactly: removed ~35 catalog entries that were never actually wrapped in translation functions (hardcoded admin-area strings) plus a duplicate `Tap to Play`, and recompiled the `.mo`
- No code behavior change beyond the now-correct translations; the admin settings/theme/waveform pages remain English (their strings are not translatable yet)

### Version 2.5.4 (2026-08-13)

**Bug Fixes:**
- Fixed playback stopping between tracks on locked screens / backgrounded tabs (reported on Android Chrome after ~2 songs): `play()` is now called immediately in the same task chain as the `ended` event instead of being deferred via `loadeddata`/timeouts - deferred timers are throttled on hidden pages and the delayed call lost its autoplay allowance. The event/timeout machinery remains as a fallback for legacy mobile quirks
- Blocked playback now also resumes on the **first touch anywhere on the page** and on bfcache restores (`pageshow`), not just via the overlay button or a visibility change

### Version 2.5.3 (2026-08-10)

**Plugin Check pass (no functional changes for visitors):**
- Theme queries now use the WordPress object cache (`wp_cache_*`) with invalidation on every write - faster on sites with persistent object caching, and resolves all `NoCaching` warnings properly instead of suppressing them
- Waveform peak data from the editor is now element-wise cast to floats before saving (defense in depth)
- Every remaining Plugin Check warning carries an inline justification at the exact line (public GET endpoints without nonces, custom-table queries, gated `error_log`, binary streaming via `fread`, `load_plugin_textdomain` for non-wordpress.org installs)
- uninstall.php: prefixed the global variable, moved the phpcs justification to the correct line

### Version 2.5.2 (2026-08-09)

**WordPress.org compliance (internal, no functional changes):**
- Renamed all global PHP symbols to unique prefixes (`sleekaudio_*` functions, `SleekAudio_*` classes, `SLEEKAUDIO_*` constants) per plugin directory guidelines; stored data (options, post type, meta keys) is unchanged, so updating is seamless
- Hardened SQL: table-existence check now uses `$wpdb->prepare()`; remaining table-name interpolations documented (safe: names derive from `$wpdb->prefix`)
- Added the required "External Services" disclosure section (Umami Analytics, CDN URL rewriting - both opt-in) to readme.txt
- New `tools/build-zip.py` builds a clean distribution ZIP without development files

### Version 2.5.1 (2026-08-09)

**Documentation:**
- Documented that the audio visualizer requires audio to be served over the same origin/protocol as the page (or a trusted CDN). On an HTTPS page, audio referenced over HTTP is blocked as mixed content and the visualizer stays disabled – ensure Site/Home URL and stored media URLs use `https://`

### Version 2.5.0 (2026-08-09)

**Performance:**
- Player assets (JS/CSS) now load **only on pages that actually contain a player** - previously ~200 KB were loaded on every page of the site. Detection covers shortcodes, the Gutenberg block, playlist pages, and page-builder/widget rendering (late enqueue with inline stylesheet printing to avoid unstyled flashes)
- Minified builds are now shipped and served by default (player.js 141 KB → 72 KB, player.css 48 KB → 34 KB); set `SCRIPT_DEBUG` to serve the readable sources. Build via `python tools/minify.py`

**Bug Fixes:**
- Fixed player silently stopping between tracks when the browser blocks the automatic transition (e.g. locked screen or backgrounded tab on Android Chrome) - the "Tap to Play" overlay now always appears and playback resumes automatically once the page is visible again
- Fixed More menu rendering with excessive spacing on pages where the page builder runs `wpautop` over the shortcode output (e.g. homepage) - the player markup is now emitted without inter-tag whitespace so `wpautop` cannot inject stray `<br>`/`<p>` elements, plus a CSS safety net hides any that still appear
- Fixed track durations being deleted when saving a playlist - the duration field is now persisted in the editor form
- Added missing SoundCloud field to newly added track rows in the playlist editor
- Added SoundCloud links to JSON-LD schema markup (`sameAs`), as documented since 2.2.0

**Maintenance:**
- Fixed translations never loading: `load_plugin_textdomain` was missing and no compiled `.mo` file was shipped - the German translation now actually works
- "Tap to Play" overlay text is now translatable
- Uninstall now also removes the Umami script URL / website ID options and stored waveform data
- Corrected license statement in readme.md (GPL v2 or later, matching the plugin header)

### Version 2.4.5 (2026-08-07)

**Bug Fixes:**
- Fixed More menu appearing in the wrong position when the player is placed inside transformed/animated theme sections (e.g. page builder homepages) - `position: fixed` coordinates are now converted to the correct containing block
- Hardened More menu item CSS against theme style overrides that caused offset entries with excessive spacing

### Version 2.3.2 (2026-03-24)

**Bug Fixes:**
- Fixed "Tap to Play" overlay not disappearing when starting playback from playlist
- Overlay now automatically removes when audio starts playing via any method (play button, track selection, or overlay click)

### Version 2.3.1 (2026-03-24)

**Bug Fixes:**
- Fixed autoplay not working when `autoplay="true"` parameter is set in shortcode
- Added autoplay logic for normal page loads (not just shared links)
- Autoplay now triggers after audio metadata loads for better reliability

### Version 2.3.0 (2026-03-24)

**New Features:**
- Added `autoplay` parameter to shortcode: `[sleek_player id="123" autoplay="true"]`
- Enables automatic playback on page load (subject to browser autoplay policies)
- Shows "Tap to Play" overlay if browser blocks autoplay
- Works with all shortcode parameters (layout, etc.)

### Version 2.2.2 (2026-03-24)

**UI Improvements:**
- Fixed track list alignment issues in backend
- All cover images now perfectly aligned at same height
- Improved grid layout with consistent spacing
- Elements in first row are vertically centered
- Streaming links properly aligned in second row

### Version 2.2.1 (2026-03-24)

**UI Improvements:**
- Optimized backend track list layout for better visual consistency
- Streaming link input fields now display in a clean second row
- All four streaming services (Spotify, Apple Music, Amazon, SoundCloud) appear side-by-side
- Improved responsive behavior for tablet and mobile screens

### Version 2.2.0 (2026-03-24)

**New Features:**
- Added SoundCloud integration alongside existing streaming services
- Backend: SoundCloud link field in track list for each track
- Frontend: SoundCloud link appears in player's "More" menu
- CSS: Orange hover effect matching SoundCloud brand colors
- Schema markup extended to include SoundCloud links

**Technical Changes:**
- Removed global streaming links in favor of track-specific links only
- Cleaned up meta data storage for better performance
- Updated grid layout to accommodate four streaming services

### Version 2.1.4 (2026-03-18)

**Bug Fixes:**
- Improved mobile autoplay reliability with `loadeddata` event and timeout fallback
- Fixed race condition in track loading on mobile browsers
- Added 500ms fallback timeout to ensure playback starts even if events don't fire
- Switched from `canplay` to `loadeddata` event for better mobile compatibility
- Prevents duplicate play attempts with flag-based control

**Technical Details:**
- The `canplay` event was unreliable on mobile browsers during track transitions
- Now uses `loadeddata` event which fires when the first frame is loaded (more reliable)
- Timeout fallback ensures playback continues even if the event system fails
- This combination provides robust autoplay on all mobile devices

### Version 2.1.3 (2026-03-18)

**Bug Fixes:**
- Fixed mobile playback issue where player would stop after one song
- Improved audio readiness detection on mobile browsers
- Enhanced promise handling for autoplay after track ends
- Added `canplay` event listener to ensure audio is ready before playback
- Better error logging for mobile playback debugging

**Technical Details:**
- Mobile browsers have strict autoplay policies that can block automatic track progression
- The fix ensures the audio element is fully ready (`readyState >= 3`) before attempting playback
- Improved handling of the `ended` event to reliably trigger the next track on mobile devices

### Version 2.1.2
- Previous stable release

---

## License

GPL v2 or later
