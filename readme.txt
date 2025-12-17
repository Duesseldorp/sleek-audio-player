=== Simple Audio Player ===
Contributors: Martin Gräbing
Author: Martin Gräbing
Author URI: https://www.duesseldorp.de
Plugin URI: https://www.duesseldorp.de
Tags: audio, music, player, mp3, playlist, spotify, apple music, amazon, visualizer
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.3.2
License: GPLv2 or later

Minimaler, schneller Audio-Player mit Download, Shuffle, Visualizer und Streaming-Links.

== Description ==

Ein schlanker Audio-Player für WordPress mit folgenden Features:

* ▶️ Play/Pause/Prev/Next
* 🔀 Shuffle-Modus
* ⬇️ Track-Download (pro Track aktivierbar)
* 🎨 Album-Cover mit Swipe-Gesten & Animationen
* 🎵 Audio Visualizer
* 🔗 Links zu Spotify, Apple Music & Amazon Music
* 📊 Umami Analytics Integration
* 🌐 iFrame Embed für externe Websites
* 🚀 BunnyCDN Unterstützung
* 📱 Responsive Design (Wide & Standard Layout)
* ⚡ Vanilla JavaScript (kein jQuery, kein Framework)
* 🎯 Nur ~15KB CSS + JS

== Installation ==

1. Lade den Ordner `simple-audio-player` in `/wp-content/plugins/` hoch
2. Aktiviere das Plugin im WordPress Admin
3. Erstelle eine neue Playlist unter "Playlists"
4. Füge Tracks hinzu und setze ein Featured Image als Cover
5. Nutze den Shortcode auf jeder Seite

== Usage ==

**Shortcode:**
[simple_player id="123"]

Ersetze `123` durch die ID deiner Playlist.

**Playlist erstellen:**
1. Gehe zu "Playlists" > "Neue Playlist"
2. Titel eingeben
3. Featured Image als Cover setzen
4. Tracks hinzufügen (Titel, Audio-URL, Download erlauben)
5. Streaming-Links eintragen (Spotify, Apple, Amazon)
6. Veröffentlichen

== Changelog ==

= 1.3.2 =
* Cover-Animationen (Ken Burns Effekt)
* iFrame Embed für externe Websites
* Umami Analytics Integration (aktivierbar)
* Verbessertes Click-to-Play auf Cover
* Edge Browser Kompatibilität
* Embed Codes im Backend

= 1.3.0 =
* Wide Layout mit horizontaler Ansicht
* Audio Visualizer
* BunnyCDN Integration
* Swipe-Gesten für Cover-Wechsel
* Nur ein Player spielt gleichzeitig

= 1.0.0 =
* Erste Version
* Play/Pause, Prev/Next, Shuffle
* Download-Funktion
* Streaming-Links
* Responsive Design
