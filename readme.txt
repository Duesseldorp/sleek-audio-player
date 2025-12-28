=== Sleek Audio Player ===
Contributors: d0211
Author: Martin Gräbing
Author URI: https://www.duesseldorp.de
Plugin URI: https://www.duesseldorp.de/sleek-audio-player
Tags: audio, music, player, playlist, mp3
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 2.0.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minimal, fast audio player with download, shuffle, cover art, and visualization.

== Description ==

A sleek, modern audio player for WordPress - perfect for musicians, podcasters, and bands.

= Player Features =

* ▶️ Play/Pause/Prev/Next Controls
* 🔀 Shuffle mode
* 📤 Share button with social media preview
* ⋮ More menu (Download, Repeat, Speed, Sleep Timer)
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
* 📱 Responsive design (Wide & Standard Layout)
* 🎨 Theme Manager with live preview
* 🔒 URL protection for audio files (optional)

= Streaming & Integration =

* 🔗 Links to Spotify, Apple Music & Amazon Music
* 📊 Umami Analytics integration (track events)
* 🌐 iFrame embed for external websites
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

* ⚡ Vanilla JavaScript (no jQuery dependency for player)
* 🎯 Lightweight (~37KB CSS + ~129KB JS unminified)
* 🔒 WordPress Security Standards compliant
* 🛡️ Full input sanitization (sanitize_text_field, esc_url_raw, absint)
* 🔐 All output escaped (esc_html, esc_attr, esc_url, wp_json_encode)
* ✅ Nonce verification on all forms and AJAX
* 🎭 Only one player plays at a time
* 🖥️ Cross-browser compatible (Chrome, Firefox, Safari, Edge)

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
| `layout` | `wide` | standard | Horizontal layout with cover on left |

= Examples =

Standard Layout:
`[sleek_player id="123"]`

Wide Layout (horizontal):
`[sleek_player id="123" layout="wide"]`

= Creating a Playlist =

1. Go to "Playlists" > "Add New"
2. Enter title
3. Add tracks:
   - Title and artist
   - Audio file from Media Library
   - Cover image (optional, otherwise Featured Image)
   - Allow download (optional)
4. Enter streaming links (Spotify, Apple, Amazon)
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
- Shortcode (Standard)
- Shortcode (Wide Layout)
- iFrame code for external websites

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

== Screenshots ==

1. Player in standard layout
2. Player in wide layout
3. Backend: Edit playlist
4. Backend: Settings

== Changelog ==

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
