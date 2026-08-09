=== Sleek Audio Player ===
Contributors: d0211
Author: Martin Gräbing
Author URI: https://www.duesseldorp.de
Plugin URI: https://www.duesseldorp.de/sleek-audio-player
Tags: audio, music, player, playlist, mp3
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 2.5.1
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

== Changelog ==

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
