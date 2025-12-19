=== Simple Audio Player ===
Contributors: Martin Gräbing
Author: Martin Gräbing
Author URI: https://www.duesseldorp.de
Plugin URI: https://www.duesseldorp.de
Tags: audio, music, player, mp3, playlist, spotify, apple music, amazon, visualizer, keyboard
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.7.0
License: GPLv2 or later

Minimaler, schneller Audio-Player mit Download, Shuffle, Visualizer und Streaming-Links.

== Description ==

Ein schlanker, moderner Audio-Player für WordPress - perfekt für Musiker, Podcaster und Bands.

= Player Features =

* ▶️ Play/Pause/Prev/Next Controls
* 🔀 Shuffle-Modus
* ⬇️ Track-Download (pro Track aktivierbar)
* 🎵 Audio Visualizer mit Frequenz-Balken
* ⏱️ Progress-Bar mit Seek-Funktion
* 🌊 Waveform-Anzeige mit Fortschrittsfarbe
* 🔊 Lautstärke-Steuerung per Tastatur

= Cover & Design =

* 🎨 Album-Cover pro Track oder als Fallback
* 👆 Swipe-Gesten für Cover-Wechsel (Touch & Mouse)
* ✨ Ken Burns Animation beim Abspielen
* 💫 Pulse-Effekt bei Track-Wechsel
* 🔍 Hover-Zoom auf Cover
* 📱 Responsive Design (Wide & Standard Layout)
* 🎨 Theme Manager mit Live-Preview
* 🔒 URL-Schutz für Audio-Dateien (optional)

= Streaming & Integration =

* 🔗 Links zu Spotify, Apple Music & Amazon Music
* 📊 Umami Analytics Integration (Events tracken)
* 🌐 iFrame Embed für externe Websites
* 🚀 BunnyCDN Unterstützung für schnelle Auslieferung

= Keyboard Shortcuts =

* Space = Play/Pause
* ← / → = 10 Sekunden vor/zurück
* ↑ / ↓ = Lautstärke +/-
* N = Nächster Track
* P = Vorheriger Track
* M = Mute/Unmute
* S = Shuffle ein/aus

= Technisch =

* ⚡ Vanilla JavaScript (kein jQuery, kein Framework)
* 🎯 Nur ~15KB CSS + JS
* 🔒 Sicherheits-geprüft (Nonce, Sanitization, Escaping)
* 🎭 Nur ein Player spielt gleichzeitig
* 🖥️ Cross-Browser kompatibel (Chrome, Firefox, Safari, Edge)

== Installation ==

1. Lade den Ordner `simple-audio-player` in `/wp-content/plugins/` hoch
2. Aktiviere das Plugin im WordPress Admin
3. Erstelle eine neue Playlist unter "Playlists"
4. Füge Tracks hinzu und setze Cover-Bilder
5. Nutze den Shortcode auf jeder Seite

== Usage ==

= Shortcodes =

Standard Layout:
[simple_player id="123"]

Wide Layout (horizontal):
[simple_player id="123" layout="wide"]

= Playlist erstellen =

1. Gehe zu "Playlists" > "Neue Playlist"
2. Titel eingeben
3. Tracks hinzufügen:
   - Titel und Künstler
   - Audio-Datei aus Mediathek
   - Cover-Bild (optional, sonst Featured Image)
   - Download erlauben (optional)
4. Streaming-Links eintragen (Spotify, Apple, Amazon)
5. Featured Image als Standard-Cover setzen
6. Veröffentlichen

= Einstellungen =

Unter "Playlists" > "Einstellungen":
- BunnyCDN URL konfigurieren
- Umami Analytics aktivieren/deaktivieren
- URL-Schutz aktivieren (verschleiert Audio-URLs mit Tokens)

= Theme Manager =

Unter "Playlists" > "Themes":
- Eigene Farbschemas erstellen
- Live-Preview beim Bearbeiten
- Visualizer-Farbe anpassen
- Waveform-Farben (aktiv/inaktiv) konfigurieren
- Themes aktivieren/deaktivieren

= Embed Codes =

Nach Veröffentlichung erscheinen in der Playlist-Bearbeitung:
- Shortcode (Standard)
- Shortcode (Wide Layout)
- iFrame Code für externe Websites

== Frequently Asked Questions ==

= Wie aktiviere ich die Keyboard Shortcuts? =
Die Shortcuts sind automatisch aktiv, wenn der Player im sichtbaren Bereich ist.

= Kann ich mehrere Player auf einer Seite haben? =
Ja! Nur einer spielt zur gleichen Zeit - die anderen pausieren automatisch.

= Wie tracke ich Plays mit Umami? =
Aktiviere "Umami Analytics" in den Einstellungen. Events: audio-play, audio-complete, audio-download.

= Funktioniert der Player mit Caching-Plugins? =
Ja, der Player ist vollständig kompatibel mit Caching-Plugins.

== Screenshots ==

1. Player im Standard-Layout
2. Player im Wide-Layout
3. Backend: Playlist bearbeiten
4. Backend: Einstellungen

== Changelog ==

= 1.7.0 =
* 🌐 CORS-Support für CDN-Audio (BunnyCDN, CloudFront, jsDelivr)
* 🎵 Visualizer funktioniert jetzt mit externen Audio-Quellen
* 🎨 CSS-Optimierungen für bessere Theme-Kompatibilität
* 🔧 Verbesserte Stabilität

= 1.6.0 =
* 🎨 Erweiterter Theme Manager mit mehr Farboptionen
* 🛠️ Backend-Verbesserungen
* ⚡ Performance-Optimierungen für Waveform-Rendering
* 🔒 Verbesserte Sicherheits-Validierung

= 1.5.0 =
* 🎨 Theme Manager mit Live-Preview
* 🌊 Waveform-Anzeige statt einfacher Progress-Bar
* 🔒 URL-Schutz mit zeitlich begrenzten Tokens
* 🛡️ Verbesserter XSS-Schutz im Admin
* 🔧 Range-Header Validierung für Streaming
* ✨ Separate Farben für Visualizer und Waveform

= 1.4.0 =
* Keyboard Shortcuts (Space, Pfeiltasten, N, P, M, S)
* Security Audit bestanden
* Performance-Optimierungen

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

== Upgrade Notice ==

= 1.7.0 =
CORS-Support für CDN-Audio! Visualizer funktioniert jetzt auch mit BunnyCDN und anderen CDNs.

= 1.6.0 =
Erweiterter Theme Manager mit zusätzlichen Farboptionen und Backend-Verbesserungen.

= 1.5.0 =
Neuer Theme Manager! Erstelle eigene Farbschemas mit Live-Preview. Waveform-Anzeige ersetzt die Progress-Bar. Optional: URL-Schutz für Audio-Dateien.

= 1.4.0 =
Keyboard Shortcuts hinzugefügt! Space für Play/Pause, Pfeiltasten für Seek und Lautstärke.
