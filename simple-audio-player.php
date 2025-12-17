<?php
/**
 * Plugin Name: Simple Audio Player
 * Description: Minimaler Audio-Player mit Download, Shuffle, Cover und Streaming-Links
 * Version: 1.3.2
 * Author: Martin Gräbing
 * Author URI: https://www.duesseldorp.de
 * Plugin URI: https://www.duesseldorp.de
 * Text Domain: simple-audio-player
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

define('SAP_VERSION', '1.3.2');
define('SAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAP_PLUGIN_URL', plugin_dir_url(__FILE__));

class Simple_Audio_Player {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Convert URL to CDN URL if CDN is configured
     */
    public static function cdn_url($url) {
        $cdn_url = get_option('sap_cdn_url', '');
        
        if (empty($cdn_url) || empty($url)) {
            return $url;
        }
        
        // Clean up CDN URL
        $cdn_url = trim($cdn_url);
        $cdn_url = rtrim($cdn_url, '/');
        
        // Add https:// if missing
        if (!preg_match('/^https?:\/\//', $cdn_url)) {
            $cdn_url = 'https://' . $cdn_url;
        }
        
        // Get site URL without protocol
        $site_url = site_url();
        $site_host = parse_url($site_url, PHP_URL_HOST);
        
        // Only rewrite URLs from our own domain
        if (strpos($url, $site_host) !== false) {
            // Replace domain with CDN URL
            $url = preg_replace('/https?:\/\/[^\/]+/', $cdn_url, $url);
        }
        
        return $url;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_shortcode('simple_player', array($this, 'render_player'));
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('template_redirect', array($this, 'handle_embed'));
    }
    
    /**
     * Handle Embed View
     */
    public function handle_embed() {
        if (!isset($_GET['embed']) || sanitize_text_field($_GET['embed']) !== '1') {
            return;
        }
        
        if (!is_singular('sap_playlist')) {
            return;
        }
        
        global $post;
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Output standalone embed page
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html(get_the_title()); ?></title>
            <?php wp_head(); ?>
            <style>
                body { 
                    margin: 0; 
                    padding: 16px; 
                    background: transparent;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .sap-player { 
                    margin: 0 auto !important;
                }
            </style>
        </head>
        <body>
            <?php echo do_shortcode('[simple_player id="' . intval($post->ID) . '"]'); ?>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Add Settings Page
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=sap_playlist',
            'Player Einstellungen',
            'Einstellungen',
            'manage_options',
            'sap-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register Settings
     */
    public function register_settings() {
        register_setting('sap_settings', 'sap_cdn_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ));
        
        register_setting('sap_settings', 'sap_umami_tracking', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ));
    }
    
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Simple Audio Player - Einstellungen</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('sap_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sap_cdn_url">CDN URL (BunnyCDN)</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sap_cdn_url" 
                                   name="sap_cdn_url" 
                                   value="<?php echo esc_attr(get_option('sap_cdn_url', '')); ?>" 
                                   class="regular-text"
                                   placeholder="https://deine-zone.b-cdn.net" />
                            <p class="description">
                                Deine BunnyCDN Pull Zone URL (ohne Slash am Ende).<br>
                                Leer lassen um CDN zu deaktivieren.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_umami_tracking">Umami Analytics</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sap_umami_tracking" 
                                       name="sap_umami_tracking" 
                                       value="1"
                                       <?php checked(get_option('sap_umami_tracking', false)); ?> />
                                Tracking aktivieren
                            </label>
                            <p class="description">
                                Sendet Events an Umami wenn Songs abgespielt, beendet oder heruntergeladen werden.<br>
                                Umami muss auf der Seite bereits eingebunden sein.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Einstellungen speichern'); ?>
            </form>
            
            <hr>
            <h2>Anleitung: BunnyCDN einrichten</h2>
            <ol>
                <li>Gehe zu <a href="https://bunny.net" target="_blank">bunny.net</a> und erstelle einen Account</li>
                <li>Erstelle eine neue <strong>Pull Zone</strong></li>
                <li>Als <strong>Origin URL</strong> gibst du deine Website-URL ein: <code><?php echo home_url(); ?></code></li>
                <li>Kopiere die Pull Zone URL (z.B. <code>https://deinname.b-cdn.net</code>) und trage sie oben ein</li>
            </ol>
        </div>
        <?php
    }

    /**
     * Assets laden
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'simple-audio-player',
            SAP_PLUGIN_URL . 'assets/css/player.css',
            array(),
            SAP_VERSION
        );

        wp_enqueue_script(
            'simple-audio-player',
            SAP_PLUGIN_URL . 'assets/js/player.js',
            array(),
            SAP_VERSION,
            true
        );
        
        // Pass settings to JavaScript
        wp_localize_script('simple-audio-player', 'sapSettings', array(
            'umamiTracking' => (bool) get_option('sap_umami_tracking', false),
        ));
    }

    /**
     * Admin Scripts für Media Uploader
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($post_type !== 'sap_playlist') {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script(
            'sap-admin',
            SAP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'media-upload', 'thickbox'),
            SAP_VERSION,
            true
        );
        wp_enqueue_style('thickbox');
        
        wp_enqueue_style(
            'sap-admin',
            SAP_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SAP_VERSION
        );
    }

    /**
     * Custom Post Type für Playlists
     */
    public function register_post_type() {
        register_post_type('sap_playlist', array(
            'labels' => array(
                'name' => __('Playlists', 'simple-audio-player'),
                'singular_name' => __('Playlist', 'simple-audio-player'),
                'add_new' => __('Neue Playlist', 'simple-audio-player'),
                'add_new_item' => __('Neue Playlist erstellen', 'simple-audio-player'),
                'edit_item' => __('Playlist bearbeiten', 'simple-audio-player'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-playlist-audio',
            'supports' => array('title'),
        ));
    }

    /**
     * Meta Boxes für Track-Daten
     */
    public function add_meta_boxes() {
        add_meta_box(
            'sap_tracks',
            __('Tracks', 'simple-audio-player'),
            array($this, 'render_tracks_metabox'),
            'sap_playlist',
            'normal',
            'high'
        );
    }

    /**
     * Tracks Metabox
     */
    public function render_tracks_metabox($post) {
        wp_nonce_field('sap_save_meta', 'sap_nonce');
        $tracks = get_post_meta($post->ID, '_sap_tracks', true);
        if (!is_array($tracks)) {
            $tracks = array();
        }
        ?>
        <div id="sap-tracks-container">
            <?php foreach ($tracks as $index => $track) : ?>
                <div class="sap-track-row" data-index="<?php echo $index; ?>">
                    <span class="sap-track-handle">☰</span>
                    <div class="sap-track-cover-preview">
                        <?php if (!empty($track['cover_url'])) : ?>
                            <img src="<?php echo esc_url($track['cover_url']); ?>" />
                        <?php else : ?>
                            <span class="sap-no-cover">🎵</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="sap_tracks[<?php echo $index; ?>][cover_id]" 
                           class="sap-track-cover-id" value="<?php echo esc_attr($track['cover_id'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo $index; ?>][cover_url]" 
                           class="sap-track-cover-url" value="<?php echo esc_url($track['cover_url'] ?? ''); ?>" />
                    <button type="button" class="button sap-select-cover" title="Cover auswählen">🖼️</button>
                    <input type="text" name="sap_tracks[<?php echo $index; ?>][title]" 
                           class="sap-track-title" placeholder="Titel" 
                           value="<?php echo esc_attr($track['title'] ?? ''); ?>" />
                    <input type="text" name="sap_tracks[<?php echo $index; ?>][artist]" 
                           class="sap-track-artist" placeholder="Artist" 
                           value="<?php echo esc_attr($track['artist'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo $index; ?>][url]" 
                           class="sap-track-url" value="<?php echo esc_url($track['url'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo $index; ?>][attachment_id]" 
                           class="sap-track-id" value="<?php echo esc_attr($track['attachment_id'] ?? ''); ?>" />
                    <span class="sap-track-filename"><?php echo esc_html(basename($track['url'] ?? 'Keine Datei')); ?></span>
                    <button type="button" class="button sap-select-audio">🎵 Audio</button>
                    <input type="url" name="sap_tracks[<?php echo $index; ?>][spotify]" 
                           class="sap-track-link sap-link-spotify" placeholder="🎵 Spotify" 
                           value="<?php echo esc_url($track['spotify'] ?? ''); ?>" />
                    <input type="url" name="sap_tracks[<?php echo $index; ?>][apple]" 
                           class="sap-track-link sap-link-apple" placeholder="🍎 Apple Music" 
                           value="<?php echo esc_url($track['apple'] ?? ''); ?>" />
                    <input type="url" name="sap_tracks[<?php echo $index; ?>][amazon]" 
                           class="sap-track-link sap-link-amazon" placeholder="📦 Amazon" 
                           value="<?php echo esc_url($track['amazon'] ?? ''); ?>" />
                    <label class="sap-download-label">
                        <input type="checkbox" name="sap_tracks[<?php echo $index; ?>][downloadable]" 
                               value="1" <?php checked(!empty($track['downloadable'])); ?> />
                        DL
                    </label>
                    <button type="button" class="button sap-remove-track">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-primary" id="sap-add-track">+ Track hinzufügen</button>
            <button type="button" class="button" id="sap-bulk-add">📁 Mehrere aus Mediathek</button>
        </p>
        
        <!-- Embed Shortcode Info -->
        <div class="sap-embed-info">
            <label>Shortcode zum Einbetten:</label>
            <div class="sap-embed-codes">
                <div class="sap-embed-code">
                    <span class="sap-embed-label">Standard:</span>
                    <code class="sap-shortcode" onclick="this.select(); document.execCommand('copy');" title="Klicken zum Kopieren">[simple_player id="<?php echo $post->ID; ?>"]</code>
                </div>
                <div class="sap-embed-code">
                    <span class="sap-embed-label">Wide Layout:</span>
                    <code class="sap-shortcode" onclick="this.select(); document.execCommand('copy');" title="Klicken zum Kopieren">[simple_player id="<?php echo $post->ID; ?>" layout="wide"]</code>
                </div>
                <?php if ($post->post_status === 'publish') : ?>
                <div class="sap-embed-code">
                    <span class="sap-embed-label">iFrame (extern):</span>
                    <code class="sap-shortcode sap-iframe-code" onclick="this.select(); document.execCommand('copy');" title="Klicken zum Kopieren">&lt;iframe src="<?php echo esc_url(add_query_arg('embed', '1', get_permalink($post->ID))); ?>" width="100%" height="400" frameborder="0" allow="autoplay"&gt;&lt;/iframe&gt;</code>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <input type="hidden" id="sap-track-index" value="<?php echo count($tracks); ?>" />
        <?php
    }

    /**
     * Streaming Links Metabox
     */
    public function render_streaming_metabox($post) {
        $spotify = get_post_meta($post->ID, '_sap_spotify', true);
        $apple = get_post_meta($post->ID, '_sap_apple', true);
        $amazon = get_post_meta($post->ID, '_sap_amazon', true);
        ?>
        <p>
            <label><strong>Spotify:</strong></label>
            <input type="url" name="sap_spotify" value="<?php echo esc_url($spotify); ?>" style="width:100%;" placeholder="https://open.spotify.com/..." />
        </p>
        <p>
            <label><strong>Apple Music:</strong></label>
            <input type="url" name="sap_apple" value="<?php echo esc_url($apple); ?>" style="width:100%;" placeholder="https://music.apple.com/..." />
        </p>
        <p>
            <label><strong>Amazon Music:</strong></label>
            <input type="url" name="sap_amazon" value="<?php echo esc_url($amazon); ?>" style="width:100%;" placeholder="https://music.amazon.com/..." />
        </p>
        
        <?php if ($post->post_status === 'publish') : ?>
        <hr style="margin: 20px 0;">
        <h4 style="margin-bottom: 10px;">📋 Embed Codes</h4>
        
        <p>
            <label><strong>Shortcode:</strong></label>
            <input type="text" 
                   value='[simple_player id="<?php echo $post->ID; ?>"]' 
                   readonly 
                   onclick="this.select(); document.execCommand('copy'); alert('Shortcode kopiert!');"
                   style="width:100%; background:#f0f0f0; cursor:pointer;" />
        </p>
        
        <p>
            <label><strong>Shortcode (Wide Layout):</strong></label>
            <input type="text" 
                   value='[simple_player id="<?php echo $post->ID; ?>" layout="wide"]' 
                   readonly 
                   onclick="this.select(); document.execCommand('copy'); alert('Shortcode kopiert!');"
                   style="width:100%; background:#f0f0f0; cursor:pointer;" />
        </p>
        
        <p>
            <label><strong>iFrame Embed:</strong></label>
            <textarea readonly 
                      onclick="this.select(); document.execCommand('copy'); alert('Embed Code kopiert!');"
                      style="width:100%; height:60px; background:#f0f0f0; cursor:pointer; font-size:11px;">&lt;iframe src="<?php echo esc_url(add_query_arg('embed', '1', get_permalink($post->ID))); ?>" width="100%" height="400" frameborder="0" allow="autoplay"&gt;&lt;/iframe&gt;</textarea>
            <span class="description">Für externe Websites</span>
        </p>
        <?php else : ?>
        <p class="description" style="margin-top:15px;"><em>Embed Codes werden nach Veröffentlichung angezeigt.</em></p>
        <?php endif; ?>
        
        <?php
    }

    /**
     * Meta speichern
     */
    public function save_meta($post_id) {
        if (!isset($_POST['sap_nonce']) || !wp_verify_nonce($_POST['sap_nonce'], 'sap_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Tracks speichern
        if (isset($_POST['sap_tracks'])) {
            $tracks = array();
            foreach ($_POST['sap_tracks'] as $track) {
                if (!empty($track['url'])) {
                    $tracks[] = array(
                        'title' => sanitize_text_field($track['title']),
                        'artist' => sanitize_text_field($track['artist'] ?? ''),
                        'url' => esc_url_raw($track['url']),
                        'attachment_id' => absint($track['attachment_id'] ?? 0),
                        'cover_id' => absint($track['cover_id'] ?? 0),
                        'cover_url' => esc_url_raw($track['cover_url'] ?? ''),
                        'spotify' => esc_url_raw($track['spotify'] ?? ''),
                        'apple' => esc_url_raw($track['apple'] ?? ''),
                        'amazon' => esc_url_raw($track['amazon'] ?? ''),
                        'downloadable' => !empty($track['downloadable']),
                    );
                }
            }
            update_post_meta($post_id, '_sap_tracks', $tracks);
        }

        // Streaming Links speichern
        if (isset($_POST['sap_spotify'])) {
            update_post_meta($post_id, '_sap_spotify', esc_url_raw($_POST['sap_spotify']));
        }
        if (isset($_POST['sap_apple'])) {
            update_post_meta($post_id, '_sap_apple', esc_url_raw($_POST['sap_apple']));
        }
        if (isset($_POST['sap_amazon'])) {
            update_post_meta($post_id, '_sap_amazon', esc_url_raw($_POST['sap_amazon']));
        }
    }

    /**
     * Shortcode: [simple_player id="123" layout="wide"]
     */
    public function render_player($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'layout' => '', // 'wide' for horizontal layout
        ), $atts);

        $post_id = intval($atts['id']);
        if (!$post_id) {
            return '<p>Keine Playlist-ID angegeben.</p>';
        }

        $tracks = get_post_meta($post_id, '_sap_tracks', true);
        if (empty($tracks)) {
            return '<p>Keine Tracks gefunden.</p>';
        }
        
        // Apply CDN URLs to all media
        foreach ($tracks as &$track) {
            if (!empty($track['url'])) {
                $track['url'] = self::cdn_url($track['url']);
            }
            if (!empty($track['cover_url'])) {
                $track['cover_url'] = self::cdn_url($track['cover_url']);
            }
        }
        unset($track);

        $cover = self::cdn_url(get_the_post_thumbnail_url($post_id, 'medium'));
        $title = get_the_title($post_id);
        $spotify = get_post_meta($post_id, '_sap_spotify', true);
        $apple = get_post_meta($post_id, '_sap_apple', true);
        $amazon = get_post_meta($post_id, '_sap_amazon', true);

        // Gesamtdauer berechnen
        $total_seconds = 0;
        foreach ($tracks as $track) {
            if (!empty($track['duration'])) {
                $parts = explode(':', $track['duration']);
                if (count($parts) == 2) {
                    $total_seconds += intval($parts[0]) * 60 + intval($parts[1]);
                } elseif (count($parts) == 3) {
                    $total_seconds += intval($parts[0]) * 3600 + intval($parts[1]) * 60 + intval($parts[2]);
                }
            }
        }
        $total_mins = floor($total_seconds / 60);
        $total_secs = $total_seconds % 60;
        $total_duration = sprintf('%d:%02d', $total_mins, $total_secs);

        ob_start();
        ?>
        <?php 
        $layout = strtolower(trim($atts['layout']));
        $is_wide = ($layout === 'wide');
        $layout_class = $is_wide ? ' sap-wide' : '';
        
        // Inline styles for wide layout (cache-proof)
        // Note: No !important here so CSS media queries can override on mobile
        $wide_style = $is_wide ? 'display:grid;grid-template-columns:auto 1fr;max-width:100%;width:100%;' : '';
        ?>
        <div class="sap-player<?php echo esc_attr($layout_class); ?>" <?php if($wide_style) echo 'style="'.$wide_style.'"'; ?> data-playlist='<?php echo esc_attr(json_encode($tracks)); ?>' data-default-cover='<?php echo esc_url($cover); ?>'>
            
            <!-- Cover Carousel -->
            <div class="sap-cover-carousel" <?php if($is_wide) echo 'style="height:100%;flex-shrink:0;"'; ?>>
                <div class="sap-cover-track">
                    <?php foreach ($tracks as $i => $t) : 
                        $track_cover = !empty($t['cover_url']) ? $t['cover_url'] : $cover;
                        if ($track_cover) :
                    ?>
                        <div class="sap-cover-slide <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>">
                            <img src="<?php echo esc_url($track_cover); ?>" alt="<?php echo esc_attr($t['title']); ?>" />
                        </div>
                    <?php endif; endforeach; ?>
                </div>
                                <!-- Audio Visualizer -->
                <canvas class="sap-visualizer"></canvas>
            </div>

            <!-- Content Section -->
            <div class="sap-content" <?php if($is_wide) echo 'style="display:flex;flex-direction:column;"'; ?>>
                
                <!-- Track Info -->
                <div class="sap-track-info">
                    <div class="sap-now-playing">Wähle einen Track</div>
                    <div class="sap-artist"></div>
                    <div class="sap-meta">
                        <span><?php echo count($tracks); ?> Tracks</span>
                        <span class="sap-meta-divider"></span>
                        <span><?php echo esc_html($total_duration); ?></span>
                    </div>
                </div>

                <!-- Progress -->
                <div class="sap-progress-section">
                    <div class="sap-progress">
                        <div class="sap-progress-bar"></div>
                    </div>
                    <div class="sap-time-row">
                        <span class="sap-current">0:00</span>
                        <span class="sap-duration">0:00</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="sap-controls">
                    <button class="sap-btn sap-shuffle" title="Shuffle">
                        <svg viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
                    </button>
                    <button class="sap-btn sap-prev" title="Zurück">
                        <svg viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                    </button>
                    <button class="sap-btn sap-play" title="Play/Pause">
                        <svg class="sap-icon-play" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="sap-icon-pause" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </button>
                    <button class="sap-btn sap-next" title="Weiter">
                        <svg viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                    </button>
                    <button class="sap-btn sap-download" title="Download" style="display:none;">
                        <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    </button>
                </div>

                <!-- Streaming Links -->
                <?php if ($spotify || $apple || $amazon) : ?>
                <div class="sap-streaming">
                    <?php if ($spotify) : ?>
                        <a href="<?php echo esc_url($spotify); ?>" target="_blank" rel="noopener" class="sap-link sap-spotify" title="Spotify">
                            <svg viewBox="0 0 24 24"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ($apple) : ?>
                        <a href="<?php echo esc_url($apple); ?>" target="_blank" rel="noopener" class="sap-link sap-apple" title="Apple Music">
                            <svg viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ($amazon) : ?>
                        <a href="<?php echo esc_url($amazon); ?>" target="_blank" rel="noopener" class="sap-link sap-amazon" title="Amazon Music">
                            <svg viewBox="0 0 24 24"><path d="M13.958 10.09c0 1.232.029 2.256-.591 3.351-.502.891-1.301 1.438-2.186 1.438-1.214 0-1.922-.924-1.922-2.292 0-2.692 2.415-3.182 4.7-3.182v.685zm3.186 7.705c-.209.189-.512.201-.746.074-1.052-.872-1.238-1.276-1.814-2.106-1.734 1.767-2.962 2.297-5.209 2.297-2.66 0-4.731-1.641-4.731-4.925 0-2.565 1.391-4.309 3.37-5.164 1.715-.754 4.11-.891 5.942-1.095v-.41c0-.753.06-1.642-.383-2.294-.385-.578-1.124-.82-1.775-.82-1.205 0-2.277.618-2.54 1.897-.054.285-.261.567-.549.582l-3.061-.333c-.259-.056-.548-.266-.472-.66C6.021 1.145 9.202 0 12.059 0c1.447 0 3.336.385 4.477 1.478 1.447 1.379 1.309 3.219 1.309 5.221v4.727c0 1.421.589 2.044 1.145 2.812.2.277.243.611-.01.818-.631.533-1.752 1.518-2.373 2.071l-.463-.332z"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Playlist -->
                <ul class="sap-playlist">
                    <?php foreach ($tracks as $index => $track) : ?>
                        <li class="sap-track" data-index="<?php echo $index; ?>" data-url="<?php echo esc_url($track['url']); ?>" data-downloadable="<?php echo !empty($track['downloadable']) ? '1' : '0'; ?>">
                            <span class="sap-track-num"><span><?php echo $index + 1; ?></span></span>
                            <div class="sap-track-details">
                                <span class="sap-track-title"><?php echo esc_html($track['title']); ?></span>
                            </div>
                            <?php if (!empty($track['duration'])) : ?>
                                <span class="sap-track-duration"><?php echo esc_html($track['duration']); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

            </div>

            <!-- Hidden Audio Element -->
            <audio class="sap-audio" preload="none"></audio>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Plugin initialisieren
Simple_Audio_Player::get_instance();
