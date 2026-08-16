=== Sleek Audio Player ===
Contributors: d0211
Author: Martin Gräbing
Author URI: https://www.duesseldorp.de
Plugin URI: https://www.duesseldorp.de/sleek-audio-player
Tags: audio, music, player, playlist, mp3
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 2.12.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, customizable audio player with 11 visualizers, waveform display, theme manager, and SEO optimization. No jQuery, no bloat.

== Description ==

A sleek, modern audio player for WordPress - perfect for musicians, podcasters, and bands.

**Why choose Sleek Audio Player?**

* 🚀 **Zero configuration** - Works immediately after activation
* 🎨 **11 unique visualizers** - More than any other free plugin
* 🌊 **Waveform display** - Professional look with seek functionality
* 🔬 **Auto Waveform Analysis** - Real waveform data generated in the background
* 🔗 **oEmbed Support** - Auto-embed on WordPress, Discord, Slack & more
* 🔒 **Security-focused** - Full WordPress coding standards compliance
* ⚡ **Lightweight** - No jQuery, no frameworks, no bloat
* 🎯 **SEO optimized** - Schema.org markup & Open Graph tags included
* 📱 **Fully responsive** - Looks great on all devices
* 🎨 **Theme Manager** - Create unlimited custom color schemes

= Player Features =

* ▶️ Play/Pause/Prev/Next Controls
* 🔀 Shuffle mode
* 📤 Share button with social media preview
* 🔗 Embed Code Generator (Wide & Mini layouts)
* ⋮ More menu (Download, Repeat, Speed, Sleep Timer, Embed)
* ⋮ Mini Player More menu (Share, Shuffle, Download, Repeat, Sleep Timer, Embed, Streaming Links)
* 🔁 Repeat mode (Off / All / One)
* ⏩ Playback speed (1x, 1.25x, 1.5x, 2x)
* 😴 Sleep Timer (5-60 min or end of track)
* ⬇️ Track download (configurable per track)
* 🎵 11 Visualizer types (Bars, Mirror, Circular, Oscilloscope, Dots, Wave, Pulse, Circular Bars, Particles, Starburst, Orbits)
* 🎨 Visualizer color customizable via Theme Manager
* 🎨 Adaptive Colors: Visualizer color extracted from cover art (toggle in More menu)
* 👆 Double-click cover to cycle visualizers
* ⏱️ Progress bar with seek functionality
* 🌊 Waveform display with progress color
* 🔬 Auto Waveform Analysis (generated when adding tracks)
* 👆 Click on waveform jumps to position & starts playback
* 👆 Touch-drag on waveform for precise seeking (swipe to scrub)
* 🔊 Volume control with popup slider
* ⏱️ Click duration to toggle remaining time
* 💾 Resume playback (remembers position per playlist)

= Cover & Design =

* 🎨 Album cover per track or as fallback
* 👆 Swipe gestures for cover switching (Touch & Mouse)
* ✨ Ken Burns animation during playback
* 💫 Pulse effect on track change
* 🔍 Hover zoom on cover
* 💿 Cover animation selector in More menu (Off / Ken Burns / Vinyl)
* 📱 Responsive design (Wide & Mini Layout)
* 🎨 Theme Manager with live preview
* 🔒 URL protection for audio files (optional)

= Streaming & Integration =

* 🔗 Links to Spotify, Apple Music, Amazon Music & SoundCloud
* 📊 Umami Analytics integration (track events)
* 🌐 iFrame embed for external websites
* 🔗 oEmbed provider (auto-embed on other WordPress sites, Discord, Slack)
* 🚀 BunnyCDN support for fast delivery

= SEO & Social Sharing =

* 🔍 JSON-LD Schema.org markup (MusicPlaylist, MusicRecording)
* 📱 Open Graph meta tags for social sharing
* 🐦 Twitter Card support
* 📤 Share tracks with cover art preview on WhatsApp, Facebook, etc.
* 🎵 Track-specific OG tags (title, artist, cover)
* 🔗 Shareable URLs with autoplay (?playlist=X&track=Y&play=1)
* 🌐 Public playlist pages with own URL
* 📋 Playlist archive at /playlist/

= Keyboard Shortcuts =

* Space = Play/Pause
* ← / → = 10 seconds forward/back
* ↑ / ↓ = Volume +/-
* N = Next track
* P = Previous track
* M = Mute/Unmute
* S = Shuffle on/off
* R = Repeat mode cycle
* L = Playback speed cycle
* V = Cycle visualizer

= Technical =

* 🎵 Supports MP3, WAV, OGG, M4A, AAC, WebM, FLAC (browser-dependent)
* ⚡ Vanilla JavaScript - no jQuery, no React, no external dependencies
* 🎯 Lightweight - minimal impact on page load speed
* 🔒 WordPress Security Standards compliant
* 🛡️ Full input sanitization & output escaping
* ✅ Nonce verification on all forms and AJAX
* 🎭 Smart playback - only one player plays at a time
* 🖥️ Cross-browser compatible (Chrome, Firefox, Safari, Edge)
* 🧩 Gutenberg Block & Shortcode support
* 🌐 Works with all major caching plugins

== Installation ==

1. Upload the `sleek-audio-player` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress Admin
3. Create a new playlist under "Playlists"
4. Add tracks and set cover images
5. Use the shortcode on any page

== Usage ==

= Shortcode =

`[sleek_player id="123"]`

Legacy shortcode (still supported):
`[simple_player id="123"]`

= Shortcode Parameters =

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| `id` | number | required | Playlist ID |
| `layout` | `wide`, `mini` | standard | Layout variant |
| `autoplay` | `true`, `1` | off | Start playback on page load (subject to browser autoplay policies) |

= Examples =

Wide Layout (default):
`[sleek_player id="123"]`

Mini Layout (compact embed):
`[sleek_player id="123" layout="mini"]`

= Creating a Playlist =

1. Go to "Playlists" > "Add New"
2. Enter title
3. Add tracks:
   - Title and artist
   - Audio file from Media Library
   - Cover image (optional, otherwise Featured Image)
   - Allow download (optional)
4. Enter streaming links (Spotify, Apple Music, Amazon, SoundCloud)
5. Set Featured Image as default cover
6. Publish

= Settings =

Under "Playlists" > "Settings":
- Configure BunnyCDN URL
- Enable/disable Umami Analytics
- Enable URL protection (obfuscates audio URLs with tokens)

= Theme Manager =

Under "Playlists" > "Themes":
- Create custom color schemes
- Live preview while editing
- Customize visualizer color
- Configure waveform colors (active/inactive)
- Activate/deactivate themes

= Embed Codes =

After publishing, the following appear in playlist editing:
- Shortcode (Wide Layout - default)
- Shortcode (Mini Layout - compact)
- iFrame code for external websites

In the player's More menu, use "Embed Player" to generate embed codes with live layout selection.

== External Services ==

This plugin does not connect to any external service by default. Two **optional, opt-in** integrations exist; both are disabled until a site administrator actively configures them:

**Umami Analytics** (optional)
If the administrator enables "Umami Analytics" in the plugin settings and enters the URL of an Umami instance (self-hosted or Umami Cloud) plus a website ID, the player sends playback events (play, complete, download - containing track title, artist, and track position, no visitor-identifying data added by this plugin) to that Umami instance from the visitor's browser. No data is sent as long as the feature is disabled (default).
Umami is an open-source, privacy-focused analytics tool: https://umami.is/ - Privacy policy: https://umami.is/privacy - Terms: https://umami.is/terms
If a self-hosted Umami instance is configured, data goes only to the administrator's own server.

**CDN URL rewriting** (optional)
If the administrator enters a CDN base URL (e.g. BunnyCDN) in the plugin settings, audio file and cover image URLs are rewritten so that the visitor's browser loads these assets from the configured CDN instead of the WordPress server. The plugin itself sends no data to the CDN; which CDN provider is used (and its terms/privacy policy) is the administrator's choice. No rewriting happens as long as the field is empty (default).

== Frequently Asked Questions ==

= How do I activate keyboard shortcuts? =
Shortcuts are automatically active when the player is in the visible area.

= Can I have multiple players on one page? =
Yes! Only one plays at a time - the others pause automatically.

= How do I track plays with Umami? =
Enable "Umami Analytics" in settings. Events: audio-play, audio-complete, audio-download.

= Does the player work with caching plugins? =
Yes, the player is fully compatible with caching plugins.

= How do the SEO features work? =
Each playlist automatically gets a public URL at /playlist/name/. JSON-LD Schema and Open Graph tags are generated automatically. After updating, save Settings > Permalinks once!

= The visualizer doesn't show – why? =
The audio visualizer uses the Web Audio API, which requires the audio to be served from the same origin as the page (or a trusted CDN) over the same protocol. If your site loads over HTTPS but an audio file is referenced over HTTP, the browser blocks it as mixed content and the visualizer stays disabled (playback may still work, but no animation appears). Make sure your WordPress Site/Home URL and all stored media URLs use https://.

== Screenshots ==

1. Player in wide layout (default)
2. Player in mini layout (compact embed)
3. Backend: Edit playlist
4. Backend: Settings
5. Embed Code Generator modal

== Translating the plugin ==

The plugin ships in English and German. Any other language can be added without
changing the plugin and without waiting for a release.

WordPress looks for plugin translations in wp-content/languages/plugins/ before
it looks inside the plugin, so a file placed there is picked up and survives
every plugin update.

1. Open languages/sleek-audio-player.pot in Poedit (https://poedit.net/) or any
   gettext editor and translate it
2. Save it as sleek-audio-player-<locale>.po, for example
   sleek-audio-player-fr_FR.po. The locale must match the site language exactly
   (Settings -> General)
3. Poedit compiles the .mo file next to it when you save
4. Copy the .mo file to wp-content/languages/plugins/

Notes for translators:

* Strings containing placeholders carry a "translators:" comment explaining what
  each placeholder holds
* Stateful labels are complete sentences rather than assembled fragments, so you
  are free to use your own word order
* Plural forms are supported
* Right-to-left languages are not supported yet: the stylesheet still uses fixed
  left/right rules, so the translation would work but the layout would not

Translations are welcome as pull requests too - see CONTRIBUTING.md in the
repository.

== Accessibility ==

Checked automatically on every push:

* Both sliders can be focused and operated by keyboard (arrows, Page Up/Down, Home, End)
* Sliders report their real value to assistive technology via aria-valuenow, and the time via aria-valuetext
* The track title is a live region, so a screen reader announces what started playing
* Every control carries a non-empty accessible name
* The system setting "reduce motion" stops the continuous animations
* The shipped theme meets the WCAG 2.1 AA contrast minimum of 4.5:1

Known gaps, stated plainly:

* The track numbers reach only 1.96:1 and fail the contrast minimum. Fixing this changes the look, so it is still an open decision
* Custom themes are not checked - the Theme Manager allows unreadable colour combinations
* No real screen reader has been used. The tests check that the markup and values are correct, not that NVDA, JAWS or VoiceOver announce them usefully
* Tested in Chromium only; Firefox, Safari and mobile screen readers are untested

This is not a claim of WCAG 2.1 AA conformance - it is a list of what is verified and what is not.

== Changelog ==

= 2.12.0 =
* ⚡ Only the cover on screen is loaded now. A playlist with eleven covers pulled about 2.9 MB on every page view for the one image anyone could see
* 🎠 Swiping stays instant: the reachable neighbours are fetched ahead of time, the same way the next track's audio already is
* 📐 Covers now offer responsive sizes, so a phone no longer downloads the 1024 px version for a 400 px slot

= 2.11.0 =
* 🌍 The block editor can now be translated: its labels were marked up correctly but never reached a translator, and no translation could have loaded
* 📖 New "Translating the plugin" section: any language can be added by dropping a .mo into wp-content/languages/plugins/ - no pull request, no code, survives updates

= 2.10.1 =
* 🎨 Fixed: the artist name and the time display were too faint to meet the contrast minimum (3.91:1 instead of 4.5:1). Slightly brighter now, at 4.85:1
* 📋 New Accessibility section documenting what is verified automatically - and what is not

= 2.10.0 =
* ♿ The player now respects the system setting "reduce motion": the Ken Burns zoom, the vinyl rotation and the visualizer stop, and sliding panels become instant
* 🔊 Loading and buffering indicators deliberately keep moving - they are the information, not decoration
* 🎛️ The setting is a default, not a lock: anyone who switches a visualizer on themselves keeps it

= 2.9.0 =
* ⌨️ The progress and volume sliders can now be focused and operated by keyboard - arrow keys, Page Up/Down, Home and End. They claimed to be sliders before without being reachable at all
* 👁️ Keyboard focus is visible again on both sliders; the outline was previously removed for everyone
* 🐛 Fixed: the global volume shortcuts changed the audio without moving the visible slider, updating the screen reader value or saving the setting

= 2.8.0 =
* 🕒 Hovering the waveform now shows the time under the cursor, so you can see where a click will land before making it - useful in long mixes
* 📱 Only on devices that really hover; on touch a tap would leave the label stuck on screen

= 2.7.1 =
* ♿ Fixed: the progress and volume sliders never reported their value to screen readers - they announced "0" for a whole track and "70" whatever the volume
* ♿ The progress slider now reads out the time ("1:23 of 3:45") instead of a percentage
* ♿ The track title is now a live region, so screen reader users hear which track started

= 2.7.0 =
* 🌍 The admin interface is now translatable as well: settings page, playlist editor, embed codes and the BunnyCDN guide - 74 further strings, German included
* 🇩🇪 The WordPress menu entries ("Player Settings", "Settings") and the twelve visualizer options now appear in German too
* 🔗 New track rows added in the editor use the same translated labels as existing ones (admin.js shares the strings with PHP)
* 🎨 Theme Manager and Waveform Analysis are translated as well, including their dialogs and status messages - both pages had no translation support at all
* 🐛 Fixed: the waveform page decided which files were pending by reading the status text, so translating it would have stopped the analyse button from finding anything. The state now lives in a data attribute
* 📊 237 translatable strings in total, all with German wording
* 🎯 Fixed: "Save Draft" and "Preview" no longer sit on separate lines in the publish box - the plugin's own padding made an already tight core layout overflow with longer labels

= 2.6.1 =
* 🐛 Fixed: the bundled translations never loaded. Since 2.5.7 the plugin looked for them in the wrong folder, so a German site still showed the English interface - including everything 2.6.0 had just translated
* 🌍 The player markup is now translatable too: all tooltips, screen-reader labels, "Select a track", the track count and the Download entry were still hardcoded English
* 🔢 Fixed: plural forms were silently dropped when compiling translations, so the track count stayed English
* ✅ CI now verifies that every translatable string reaches the translation template and has a German translation

= 2.6.0 =
* 🌍 The player interface is now fully translatable: ~30 runtime strings (More menu labels, all 11 visualizer names, "Copied!", every playback error) were hardcoded English and are now translated - German included
* 🛡️ A missing translation key falls back to the English original by design, so a label can never render empty or as "undefined"

= 2.5.8 =
* ©️ Every shipped file now carries a copyright and licence notice, including the minified assets (minifiers strip comments, so those were previously unattributed). No functional change

= 2.5.7 =
* 🔧 Internal restructuring: the three PHP classes moved into includes/, the main file is now a small bootstrap. No functional change - same options, shortcodes, markup and behaviour

= 2.5.6 =
* 📱 The More menu no longer closes on the slightest scroll - it tolerates 8px and stays attached to its button, so momentum scrolling on touch devices no longer makes it flash open and shut

= 2.5.5 =
* 🌐 German translation now covers the whole player UI: the "More" menu (Share, Shuffle, Sleep Timer + presets, Cover, Adaptive Colors, Embed Player) and the embed dialog (Layout, Embed Code, Copy Code, Preview, Close) were translatable in code but missing from the catalog and showed English - now translated
* 🌐 Added the remaining post-type labels (Add New, All Playlists) and the "No playable tracks found." notice
* 🧹 Rebuilt the .pot/.po to match the code exactly: removed ~35 entries that were never wrapped for translation (hardcoded admin UI) and the duplicate "Tap to Play"

= 2.5.4 =
* 🐛 Fixed playback stopping between tracks on locked screens / backgrounded tabs: play() now runs immediately in the ended-event chain instead of via throttled timers
* 📱 Blocked playback also resumes on the first touch anywhere on the page and on back/forward navigation restores

= 2.5.3 =
* ⚡ Theme queries now use the WordPress object cache with proper invalidation on writes
* 🛡️ Waveform peak data is element-wise cast to floats before saving (defense in depth)
* 🔧 All remaining Plugin Check warnings carry inline justifications at the exact line

= 2.5.2 =
* 🔧 Renamed all global PHP symbols to unique prefixes (sleekaudio_*/SleekAudio_*/SLEEKAUDIO_*) per plugin directory guidelines - no functional changes, stored data unchanged
* 🔒 SQL hardening: table-existence check via $wpdb->prepare(); remaining table-name interpolations documented
* 📝 Added External Services disclosure section (Umami Analytics, CDN URL rewriting - both opt-in)

= 2.5.1 =
* 📝 Documented that the audio visualizer requires audio served over the same origin/protocol as the page (or a trusted CDN); HTTP audio on an HTTPS page is blocked as mixed content and disables the visualizer – use https:// for Site/Home URL and stored media URLs

= 2.5.0 =
* ⚡ Player assets now load only on pages that actually contain a player (previously ~200 KB on every page of the site)
* ⚡ Minified JS/CSS builds shipped and served by default (player.js 141 KB → 72 KB, player.css 48 KB → 34 KB); SCRIPT_DEBUG serves the readable sources
* 🐛 Fixed player silently stopping between tracks when the browser blocks the automatic transition (e.g. locked screen or backgrounded tab on Android Chrome) - the "Tap to Play" overlay now always appears and playback resumes automatically once the page is visible again
* 🐛 Fixed More menu rendering with excessive spacing when page builders run wpautop over the shortcode output (stray br/p elements injected into the player markup)
* 🐛 Fixed track durations being deleted when saving a playlist
* ☁️ Added missing SoundCloud field to newly added track rows in the playlist editor
* 🔍 Added SoundCloud links to JSON-LD schema markup (sameAs)
* 🌍 Fixed translations never loading (missing load_plugin_textdomain and compiled .mo file) - German translation now works
* 🌍 "Tap to Play" overlay text is now translatable
* 🧹 Uninstall now also removes Umami options and stored waveform data

= 2.4.5 =
* 🐛 Fixed More menu appearing in the wrong position inside transformed/animated theme sections (e.g. page builder homepages)
* 🎨 Hardened More menu item CSS against theme style overrides (spacing/offset issues)

= 2.3.2 =
* 🐛 Fixed "Tap to Play" overlay not disappearing when starting playback from playlist

= 2.3.1 =
* 🐛 Fixed autoplay not working when autoplay="true" parameter is set in shortcode

= 2.3.0 =
* ✨ Added autoplay parameter to shortcode: [sleek_player id="123" autoplay="true"]
* 📱 Shows "Tap to Play" overlay if browser blocks autoplay

= 2.2.2 =
* 🎨 Fixed track list alignment issues in backend

= 2.2.1 =
* 🎨 Optimized backend track list layout for better visual consistency

= 2.2.0 =
* ☁️ Added SoundCloud integration alongside existing streaming services
* 🔧 Removed global streaming links in favor of track-specific links only

= 2.1.4 =
* 📱 Improved mobile autoplay reliability with loadeddata event and timeout fallback
* ⚡ Fixed race condition in track loading on mobile browsers
* 🔧 Added 500ms fallback timeout to ensure playback starts even if events don't fire
* 🐛 Switched from canplay to loadeddata event for better mobile compatibility

= 2.1.3 =
* 📱 Fixed mobile playback issue where player would stop after one song
* ⚡ Improved audio readiness detection on mobile browsers
* 🔧 Enhanced promise handling for autoplay after track ends
* 🎵 Added canplay event listener to ensure audio is ready before playback
* 🐛 Better error logging for mobile playback debugging

= 2.1.2 =
* 🔬 Auto Waveform Analysis - Real waveform data generated in the background when adding tracks
* 🔗 oEmbed Provider - Playlist URLs auto-embed on WordPress, Discord, Slack, Notion
* ⚡ Background processing with queue system (no browser freeze)
* 🛡️ Race condition protection for bulk imports
* 🔒 Enhanced security: DoS protection, input validation, MIME type verification

= 2.1.1 =
* ⋮ Mini Player now has its own More menu with essential functions
* 📱 Mini Player menu: Share, Shuffle, Download, Repeat, Sleep Timer, Embed, Streaming Links
* 🎨 Cleaner Mini Player UI with three-dot menu button

= 2.1.0 =
* 🔗 Embed Code Generator in player More menu
* 📱 Mini Layout for compact embeds (80px height)
* 🎨 Wide Layout now default
* 🧩 Gutenberg Block with layout selection (Wide/Mini)
* 🔧 Improved Mini Player: Cover, Buttons, Title in one row

= 2.0.3 =
* 🔄 Renamed plugin to "Sleek Audio Player"
* 🔧 Fixed Theme Import (auto-save on import)
* 🛠️ Auto-create database table after plugin rename
* ✅ Legacy shortcode `[simple_player]` still supported for backwards compatibility

= 2.0.2 =
* 💿 Cover Animation selector in More menu (Off / Ken Burns / Vinyl)
* 💿 Vinyl mode with realistic rotation, center label and light reflection
* 🎨 Adaptive Colors: Visualizer color extracted from cover art
* 👆 Touch-drag on waveform for precise seeking (swipe to scrub)
* 🔧 Improved color visibility on dark covers
* 🔒 Security hardening: Full WordPress coding standards compliance
* 🛡️ All superglobals properly sanitized with wp_unslash()
* ✅ All output escaped according to context

= 2.0.1 =
* 😴 Sleep Timer with live countdown (5, 10, 15, 30, 45, 60 min or end of track)
* 💾 Resume playback - remembers position per playlist (GDPR-compliant, localStorage only)
* 🎵 11 Visualizer types (added Circular Bars, Particles, Starburst, Orbits)
* 🛠️ Stability improvements (error handling, validation, graceful degradation)
* 🔧 Fixed visualizer acceleration bug
* 🔧 Fixed jQuery-Migrate deprecation warning
* ⋮ Improved More menu (fixed positioning, consistent styling)

= 2.0.0 =
* 📤 Share button for social media sharing
* ⋮ More menu with Download, Repeat and Speed options
* 🎵 Track-specific Open Graph tags (title, artist, cover image)
* 🔗 Shareable URLs with playlist ID for embedded playlists
* 👆 "Tap to Play" overlay when autoplay is blocked
* 🎨 Cleaner UI with fewer visible buttons
* 📱 Improved social sharing preview (no emoji in share text)

= 1.9.0 =
* 🎵 11 Visualizer types: Bars, Mirror, Circular, Oscilloscope, Dots, Wave, Pulse, Circular Bars, Particles, Starburst, Orbits
* 👆 Double-click on cover cycles through visualizers
* ⌨️ Keyboard shortcut V for visualizer cycling
* 🔊 Volume control with expandable slider (vertical on desktop, horizontal on mobile)
* ⏱️ Click on duration toggles remaining time display
* 💾 User preferences saved in localStorage (visualizer type, volume, time display)
* 📱 Improved mobile support for volume control

= 1.8.0 =
* 🔁 Repeat mode (Off / All / One track)
* ⏩ Playback speed control (1x, 1.25x, 1.5x, 2x)
* ⌨️ Keyboard shortcuts for Repeat (R) and Speed (L)
* 🔍 SEO: JSON-LD Schema.org markup for playlists and tracks
* 📱 SEO: Open Graph & Twitter Card meta tags
* 🌐 SEO: Public playlist pages with own URL (/playlist/name/)
* 📋 SEO: Playlist archive at /playlist/
* 👆 Waveform click starts playback automatically
* 🔒 Security hardening for all SEO outputs
* 🛡️ Validation of all URLs and text

= 1.7.0 =
* 🌐 CORS support for CDN audio (BunnyCDN, CloudFront, jsDelivr)
* 🎵 Visualizer now works with external audio sources
* 🎨 CSS optimizations for better theme compatibility
* 🔧 Improved stability

= 1.6.0 =
* 🎨 Extended Theme Manager with more color options
* 🛠️ Backend improvements
* ⚡ Performance optimizations for waveform rendering
* 🔒 Improved security validation

= 1.5.0 =
* 🎨 Theme Manager with live preview
* 🌊 Waveform display instead of simple progress bar
* 🔒 URL protection with time-limited tokens
* 🛡️ Improved XSS protection in admin
* 🔧 Range header validation for streaming
* ✨ Separate colors for visualizer and waveform

= 1.4.0 =
* Keyboard shortcuts (Space, Arrow keys, N, P, M, S)
* Security audit passed
* Performance optimizations

= 1.3.2 =
* Cover animations (Ken Burns effect)
* iFrame embed for external websites
* Umami Analytics integration (activatable)
* Improved click-to-play on cover
* Edge browser compatibility
* Embed codes in backend

= 1.3.0 =
* Wide layout with horizontal view
* Audio visualizer
* BunnyCDN integration
* Swipe gestures for cover switching
* Only one player plays at a time

= 1.0.0 =
* First version
* Play/Pause, Prev/Next, Shuffle
* Download function
* Streaming links
* Responsive design

== Upgrade Notice ==

= 2.1.4 =
Improved mobile autoplay! Fixed race condition and added fallback mechanism to ensure continuous playback on all mobile devices.

= 2.1.3 =
Fixed mobile playback! Player now continues automatically to the next track on mobile devices. Improved audio readiness detection for smoother playback.

= 2.1.2 =
Auto Waveform Analysis! Real waveform data is now generated in the background when you add tracks to a playlist. No more manual analysis needed.

= 2.1.1 =
Mini Player now has its own More menu! Access Share, Shuffle, Download, Repeat, Sleep Timer, Embed and Streaming Links directly from the compact player.

= 2.1.0 =
New Embed Code Generator! Share your player on external websites with Wide or Mini layout. Mini layout is perfect for sidebars and compact spaces.

= 2.0.3 =
Plugin renamed to "Sleek Audio Player". All existing playlists and settings remain intact. Legacy shortcode [simple_player] still works.

= 2.0.2 =
Vinyl mode! Cover animation selector with realistic vinyl spin effect. Adaptive Colors extracts visualizer color from cover art. Touch-drag seeking on waveform. Security hardening for WordPress plugin directory compliance.

= 2.0.1 =
Sleep Timer + Resume Playback! Set a timer or stop after track. Playback position is saved per playlist. 11 visualizer types.

= 2.0.0 =
New Share button! Share tracks directly to social media with cover art preview. Cleaner UI with More menu.

= 1.9.0 =
8 new visualizer types! Double-click cover or press V to cycle. New volume slider control.

= 1.8.0 =
SEO update! Playlists get their own URLs and Schema.org markup. After updating: save Settings > Permalinks!

= 1.7.0 =
CORS support for CDN audio! Visualizer now works with BunnyCDN and other CDNs.

= 1.6.0 =
Extended Theme Manager with additional color options and backend improvements.

= 1.5.0 =
New Theme Manager! Create custom color schemes with live preview. Waveform display replaces progress bar. Optional: URL protection for audio files.

= 1.4.0 =
Keyboard shortcuts added! Space for Play/Pause, arrow keys for seek and volume.
