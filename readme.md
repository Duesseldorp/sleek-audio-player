# Sleek Audio Player

A modern, fast and shareable audio player for WordPress.  
Built for music, podcasts and playlists – with strong UX, clean SEO and reliable performance.

👉 Product page & background: https://www.duesseldorp.de/sleek-audio-player/

**Current Version:** 2.1.3

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

- **Shareable Audio**  
  Track specific Open Graph and Twitter Card support so shared links show the correct title, cover and metadata.

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

Detailed usage examples are documented on the product page.

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

MIT License
