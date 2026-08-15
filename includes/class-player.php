<?php
/**
 * Player: shortcodes, block, REST, meta boxes, assets, embed and SEO output
 *
 * @package   Sleek_Audio_Player
 * @author    Martin Gräbing <kontakt@duesseldorp.de>
 * @copyright 2025-2026 Martin Gräbing
 * @license   GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 */
defined('ABSPATH') || exit;

class SleekAudio_Player {

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
        $site_host = wp_parse_url($site_url, PHP_URL_HOST);
        
        // Only rewrite URLs from our own domain
        if (strpos($url, $site_host) !== false) {
            // Replace domain with CDN URL
            $url = preg_replace('/https?:\/\/[^\/]+/', $cdn_url, $url);
        }
        
        return $url;
    }

    private function __construct() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_shortcode('sleek_player', array($this, 'render_player'));
        add_shortcode('simple_player', array($this, 'render_player')); // Legacy alias for backwards compatibility
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_gutenberg_block'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('template_redirect', array($this, 'handle_embed'));
        add_action('init', array($this, 'handle_audio_stream'));
        
        // SEO: Auto-display player on playlist single pages
        add_filter('the_content', array($this, 'auto_display_player'));
        
        // SEO: Add Open Graph meta tags for playlist pages
        add_action('wp_head', array($this, 'add_open_graph_tags'));
        
        // oEmbed: Register as provider for playlist URLs
        add_action('rest_api_init', array($this, 'register_oembed_endpoint'));
        add_action('wp_head', array($this, 'add_oembed_discovery'));
    }
    
    /**
     * Load translations from the plugin's languages/ folder
     *
     * The relative path must come from SLEEKAUDIO_PLUGIN_BASENAME, never from
     * plugin_basename(__FILE__): this file lives in includes/, so its own
     * __FILE__ would resolve to ".../includes/languages" — a directory that
     * does not exist, leaving every string untranslated without any error.
     */
    public function load_textdomain() {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- required for installs distributed outside wordpress.org (GitHub); bundled translations in /languages don't load without it on WP < 4.6-language-pack coverage
        load_plugin_textdomain('sleek-audio-player', false, dirname(SLEEKAUDIO_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Auto-display player on playlist single pages
     */
    public function auto_display_player($content) {
        // Security: Only run on frontend, single playlist pages, in main query
        if (is_admin()) {
            return $content;
        }
        
        if (!is_singular('sap_playlist')) {
            return $content;
        }
        
        // Prevent duplicate output in excerpts, widgets, etc.
        if (!in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        global $post;
        
        // Validate post object
        if (!$post || !isset($post->ID)) {
            return $content;
        }
        
        // Generate player HTML
        $player = $this->render_player(array('id' => $post->ID));
        
        // Add player before content (or as content if empty)
        return $player . $content;
    }
    
    /**
     * Add Open Graph meta tags for playlist pages (SEO & Social Sharing)
     */
    public function add_open_graph_tags() {
        // Security: Only run on frontend playlist pages
        if (is_admin()) {
            return;
        }
        
        // Check if sharing a specific track via URL parameters
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public read-only share URLs (?playlist=X&track=Y); no state change, nonces don't apply to unauthenticated GET views
        $shared_track = isset($_GET['track']) ? absint($_GET['track']) : 0;
        $shared_playlist = isset($_GET['playlist']) ? absint($_GET['playlist']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        
        // If no playlist ID in URL, try to get from current post (if it's a playlist page)
        global $post;
        $playlist_id = 0;
        
        if ($shared_playlist > 0) {
            // Playlist ID from URL parameter
            $playlist_id = $shared_playlist;
        } elseif ($post && isset($post->ID) && $post->post_type === 'sap_playlist') {
            // Current page is a playlist
            $playlist_id = $post->ID;
        }
        
        // If no playlist found and no track being shared, exit
        if (!$playlist_id || $shared_track <= 0) {
            // Fall back to original behavior for playlist pages without track parameter
            if (!is_singular('sap_playlist')) {
                return;
            }
            $playlist_id = $post->ID;
        }
        
        // Get playlist data
        $playlist_post = get_post($playlist_id);
        if (!$playlist_post || $playlist_post->post_type !== 'sap_playlist' || $playlist_post->post_status !== 'publish') {
            return;
        }
        
        // Sanitize all output data
        $playlist_title = wp_strip_all_tags(get_the_title($playlist_id));
        $url = esc_url(get_permalink($playlist_id));
        $cover = get_the_post_thumbnail_url($playlist_id, 'large');
        $excerpt = wp_strip_all_tags(get_the_excerpt($playlist_id));
        $tracks = get_post_meta($playlist_id, '_sap_tracks', true);
        $track_count = is_array($tracks) ? count($tracks) : 0;
        
        // Check if a specific track is being shared
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only share URL parameter, absint-sanitized
        $shared_track = isset($_GET['track']) ? absint($_GET['track']) : 0;
        $title = $playlist_title;
        
        if ($shared_track > 0 && is_array($tracks) && isset($tracks[$shared_track - 1])) {
            $track = $tracks[$shared_track - 1];
            $track_title = isset($track['title']) ? wp_strip_all_tags($track['title']) : '';
            $track_artist = isset($track['artist']) ? wp_strip_all_tags($track['artist']) : '';
            
            if ($track_title) {
                $title = $track_title . ($track_artist ? ' - ' . $track_artist : '');
                $excerpt = '"' . $track_title . '"' . ($track_artist ? ' by ' . $track_artist : '') . ' - ' . $playlist_title;
                
                // Use track cover if available
                // Priority: cover_id (always fresh) > cover_url > playlist cover
                $track_cover = '';
                
                // First try cover_id (most reliable - generates fresh URL)
                if (!empty($track['cover_id'])) {
                    $track_cover = wp_get_attachment_image_url(intval($track['cover_id']), 'large');
                    if ($track_cover) {
                        $track_cover = self::cdn_url($track_cover);
                    }
                }
                
                // Fallback to stored cover_url
                if (empty($track_cover) && !empty($track['cover_url'])) {
                    $track_cover = self::cdn_url($track['cover_url']);
                }
                
                // Set cover if valid URL found
                if ($track_cover && filter_var($track_cover, FILTER_VALIDATE_URL)) {
                    $cover = $track_cover;
                }
            }
            
            // Include track parameter in URL
            $url = add_query_arg('track', $shared_track, $url);
        }
        
        // Validate URL
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }
        
        // Validate cover URL if present
        if ($cover && !filter_var($cover, FILTER_VALIDATE_URL)) {
            $cover = '';
        }
        
        // Default description if no excerpt (sanitized)
        if (empty($excerpt)) {
            $excerpt = sprintf('%s - %d Tracks', $playlist_title, $track_count);
        }
        
        // Limit excerpt length for meta tags
        if (mb_strlen($excerpt) > 200) {
            $excerpt = mb_substr($excerpt, 0, 197) . '...';
        }
        
        // Site name (sanitized)
        $site_name = wp_strip_all_tags(get_bloginfo('name'));
        
        ?>
        <!-- Sleek Audio Player - Open Graph Tags -->
        <meta property="og:type" content="music.playlist">
        <meta property="og:title" content="<?php echo esc_attr($title); ?>">
        <meta property="og:description" content="<?php echo esc_attr($excerpt); ?>">
        <meta property="og:url" content="<?php echo esc_url($url); ?>">
        <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>">
        <?php if ($cover) : ?>
        <meta property="og:image" content="<?php echo esc_url($cover); ?>">
        <?php endif; ?>
        <meta property="music:song_count" content="<?php echo absint($track_count); ?>">
        
        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo esc_attr($title); ?>">
        <meta name="twitter:description" content="<?php echo esc_attr($excerpt); ?>">
        <?php if ($cover) : ?>
        <meta name="twitter:image" content="<?php echo esc_url($cover); ?>">
        <?php endif; ?>
        <?php
    }
    
    /**
     * Register oEmbed REST API endpoint
     */
    public function register_oembed_endpoint() {
        register_rest_route('sleek-audio-player/v1', '/oembed', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_oembed_request'),
            'permission_callback' => '__return_true',
            'args' => array(
                'url' => array(
                    'required' => true,
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'maxwidth' => array(
                    'default' => 600,
                    'sanitize_callback' => 'absint',
                ),
                'maxheight' => array(
                    'default' => 280,
                    'sanitize_callback' => 'absint',
                ),
                'format' => array(
                    'default' => 'json',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    /**
     * Handle oEmbed request and return embed data
     */
    public function handle_oembed_request($request) {
        $url = $request->get_param('url');
        $maxwidth = $request->get_param('maxwidth');
        $maxheight = $request->get_param('maxheight');
        
        // Extract playlist ID from URL
        $playlist_id = url_to_postid($url);
        
        if (!$playlist_id || get_post_type($playlist_id) !== 'sap_playlist') {
            return new WP_Error('not_found', 'Playlist not found', array('status' => 404));
        }
        
        $post = get_post($playlist_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('not_found', 'Playlist not found', array('status' => 404));
        }
        
        // Get playlist data
        $title = get_the_title($playlist_id);
        
        // Get cover image
        $thumbnail_id = get_post_thumbnail_id($playlist_id);
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'medium') : '';
        
        // Build embed URL
        $embed_url = add_query_arg('embed', '1', get_permalink($playlist_id));
        
        // Calculate dimensions (maintain aspect ratio)
        $width = min($maxwidth, 600);
        $height = min($maxheight, 280);
        
        // Build iframe HTML
        $html = sprintf(
            '<iframe src="%s" width="%d" height="%d" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" style="border-radius:12px;"></iframe>',
            esc_url($embed_url),
            $width,
            $height
        );
        
        return array(
            'version' => '1.0',
            'type' => 'rich',
            'provider_name' => 'Sleek Audio Player',
            'provider_url' => home_url(),
            'title' => $title,
            'html' => $html,
            'width' => $width,
            'height' => $height,
            'thumbnail_url' => $thumbnail_url,
            'thumbnail_width' => 300,
            'thumbnail_height' => 300,
        );
    }
    
    /**
     * Add oEmbed discovery link tags to playlist pages
     */
    public function add_oembed_discovery() {
        if (!is_singular('sap_playlist')) {
            return;
        }
        
        $url = get_permalink();
        $oembed_url = rest_url('sleek-audio-player/v1/oembed');
        $oembed_url = add_query_arg('url', urlencode($url), $oembed_url);
        
        ?>
        <!-- Sleek Audio Player - oEmbed Discovery -->
        <link rel="alternate" type="application/json+oembed" href="<?php echo esc_url($oembed_url); ?>" title="<?php echo esc_attr(get_the_title()); ?>">
        <?php
    }
    
    /**
     * Register Gutenberg Block
     */
    public function register_gutenberg_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Block-Script registrieren
        wp_register_script(
            'sap-block-editor',
            SLEEKAUDIO_PLUGIN_URL . 'assets/js/block.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-api-fetch'),
            SLEEKAUDIO_VERSION,
            true
        );
        
        // Block registrieren
        register_block_type('sleek-audio-player/player', array(
            'editor_script' => 'sap-block-editor',
            'render_callback' => array($this, 'render_gutenberg_block'),
            'attributes' => array(
                'playlistId' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'layout' => array(
                    'type' => 'string',
                    'default' => 'wide'
                )
            )
        ));
    }
    
    /**
     * Render Gutenberg Block (Frontend)
     */
    public function render_gutenberg_block($attributes) {
        $playlist_id = isset($attributes['playlistId']) ? intval($attributes['playlistId']) : 0;
        $layout = isset($attributes['layout']) ? sanitize_text_field($attributes['layout']) : 'standard';
        
        if (!$playlist_id) {
            return '';
        }
        
        $shortcode_atts = array('id' => $playlist_id);
        if ($layout === 'wide' || $layout === 'mini') {
            $shortcode_atts['layout'] = $layout;
        }
        
        return $this->render_player($shortcode_atts);
    }
    
    /**
     * Register REST API Routes for Gutenberg
     */
    public function register_rest_routes() {
        register_rest_route('sap/v1', '/playlists', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_playlists'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('sap/v1', '/preview/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_preview'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST: Get all playlists
     */
    public function rest_get_playlists() {
        $playlists = get_posts(array(
            'post_type' => 'sap_playlist',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $result = array();
        foreach ($playlists as $playlist) {
            $tracks = get_post_meta($playlist->ID, '_sap_tracks', true);
            $track_count = is_array($tracks) ? count($tracks) : 0;
            
            $result[] = array(
                'id' => $playlist->ID,
                'name' => $playlist->post_title,
                'count' => $track_count
            );
        }
        
        return $result;
    }
    
    /**
     * REST: Get player preview HTML
     */
    public function rest_get_preview($request) {
        $id = intval($request['id']);
        $playlist = get_post($id);
        
        if (!$playlist || $playlist->post_type !== 'sap_playlist') {
            return new WP_Error('not_found', __('Playlist not found', 'sleek-audio-player'), array('status' => 404));
        }
        
        $tracks = get_post_meta($id, '_sap_tracks', true);
        $track_count = is_array($tracks) ? count($tracks) : 0;
        
        return sprintf(
            '<div style="text-align:left;color:#ccc;font-size:13px;">
                <strong style="color:#fff;">%s</strong><br>
                <span style="opacity:0.7;">%d Track%s</span>
            </div>',
            esc_html($playlist->post_title),
            $track_count,
            $track_count !== 1 ? 's' : ''
        );
    }
    
    /**
     * Generate secure token for audio streaming
     */
    public static function generate_stream_token($attachment_id, $expiry = 3600) {
        $secret = wp_salt('auth');
        $expires = time() + $expiry;
        $data = $attachment_id . '|' . $expires;
        $signature = hash_hmac('sha256', $data, $secret);
        return base64_encode($data . '|' . $signature);
    }
    
    /**
     * Verify stream token
     */
    public static function verify_stream_token($token) {
        $secret = wp_salt('auth');
        $decoded = base64_decode($token);
        
        if (!$decoded) return false;
        
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) return false;
        
        list($attachment_id, $expires, $signature) = $parts;
        
        // Check expiry
        if (time() > intval($expires)) return false;
        
        // Verify signature
        $expected = hash_hmac('sha256', $attachment_id . '|' . $expires, $secret);
        if (!hash_equals($expected, $signature)) return false;
        
        return intval($attachment_id);
    }
    
    /**
     * Generate obfuscated stream URL
     */
    public static function get_stream_url($attachment_id) {
        if (!get_option('sap_url_protection', false)) {
            return wp_get_attachment_url($attachment_id);
        }
        
        $token = self::generate_stream_token($attachment_id);
        return add_query_arg('sap_stream', $token, home_url('/'));
    }
    
    /**
     * Handle audio streaming requests
     */
    public function handle_audio_stream() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public streaming endpoint; access is authorized by the signed, time-limited HMAC token below, which is stronger than a nonce
        if (!isset($_GET['sap_stream'])) return;

        $token = sanitize_text_field(wp_unslash($_GET['sap_stream']));
        $attachment_id = self::verify_stream_token($token);
        
        if (!$attachment_id) {
            status_header(403);
            die('Invalid or expired token');
        }
        
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            status_header(404);
            die('File not found');
        }
        
        $mime_type = get_post_mime_type($attachment_id);
        $file_size = filesize($file_path);
        $file_name = basename($file_path);
        
        // Handle range requests for seeking
        $start = 0;
        $end = $file_size - 1;
        
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE']));
            if (preg_match('/^bytes=(\d+)-(\d*)$/', $range, $matches)) {
                $start = min(intval($matches[1]), $file_size - 1);
                if (!empty($matches[2])) {
                    $end = min(intval($matches[2]), $file_size - 1);
                }
                // Validate range
                if ($start > $end || $start < 0) {
                    status_header(416); // Range Not Satisfiable
                    die('Invalid range');
                }
            }
            
            status_header(206);
            header("Content-Range: bytes $start-$end/$file_size");
        } else {
            status_header(200);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        
        $length = $end - $start + 1;
        
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Stream the file - using direct PHP file operations for binary audio streaming
        // WP_Filesystem is not suitable for streaming large binary files with byte-range support
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Binary audio streaming requires direct file access
        $fp = fopen($file_path, 'rb');
        fseek($fp, $start);
        
        $buffer_size = 8192;
        $bytes_remaining = $length;
        
        while ($bytes_remaining > 0 && !feof($fp)) {
            $read_size = min($buffer_size, $bytes_remaining);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPress.Security.EscapeOutput.OutputNotEscaped -- Streaming raw binary audio with byte-range support; WP_Filesystem cannot stream chunks
            echo fread($fp, $read_size);
            $bytes_remaining -= $read_size;
            flush();
        }
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing file handle from fopen above
        fclose($fp);
        exit;
    }
    
    /**
     * Handle Embed View
     */
    public function handle_embed() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only embed view (?embed=1); no state change, nonces don't apply to unauthenticated GET views
        if (!isset($_GET['embed']) || sanitize_text_field(wp_unslash($_GET['embed'])) !== '1') {
            return;
        }
        
        if (!is_singular('sap_playlist')) {
            return;
        }
        
        global $post;
        
        // Enqueue assets
        $this->enqueue_assets();
        
        // Get layout parameter
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only embed view parameter, whitelisted against $valid_layouts below
        $layout = isset($_GET['layout']) ? sanitize_text_field(wp_unslash($_GET['layout'])) : '';
        $valid_layouts = array('wide', 'mini');
        $layout_attr = in_array($layout, $valid_layouts, true) ? ' layout="' . esc_attr($layout) . '"' : '';
        
        // Adjust padding for mini layout
        $body_padding = ($layout === 'mini') ? '8px' : '16px';
        
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
                    padding: <?php echo esc_attr($body_padding); ?>; 
                    background: transparent;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .sap-player { 
                    margin: 0 auto !important;
                }
            </style>
        </head>
        <body>
            <?php 
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $layout_attr is sanitized above
            echo do_shortcode('[sleek_player id="' . intval($post->ID) . '"' . $layout_attr . ']'); 
            ?>
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
            __('Player Settings', 'sleek-audio-player'),
            __('Settings', 'sleek-audio-player'),
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
        
        register_setting('sap_settings', 'sap_umami_script_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ));
        
        register_setting('sap_settings', 'sap_umami_website_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
        
        register_setting('sap_settings', 'sap_url_protection', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ));
        
        register_setting('sap_settings', 'sap_cover_click_play', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => true,
        ));
        
        register_setting('sap_settings', 'sap_remember_position', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false,
        ));
        
        register_setting('sap_settings', 'sap_visualizer_type', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'bars',
        ));
    }
    
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Sleek Audio Player - Settings', 'sleek-audio-player'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('sap_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sap_cdn_url"><?php echo esc_html__('CDN URL (BunnyCDN)', 'sleek-audio-player'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sap_cdn_url" 
                                   name="sap_cdn_url" 
                                   value="<?php echo esc_attr(get_option('sap_cdn_url', '')); ?>" 
                                   class="regular-text"
                                   placeholder="https://your-zone.b-cdn.net" />
                            <p class="description">
                                <?php echo esc_html__('Your BunnyCDN Pull Zone URL (without trailing slash).', 'sleek-audio-player'); ?><br>
                                <?php echo esc_html__('Leave empty to disable CDN.', 'sleek-audio-player'); ?>
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
                                <?php echo esc_html__('Enable tracking', 'sleek-audio-player'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Sends events to Umami when songs are played, completed, or downloaded.', 'sleek-audio-player'); ?>
                            </p>
                            
                            <div style="margin-top:12px;">
                                <label for="sap_umami_script_url"><strong><?php echo esc_html__('Script URL:', 'sleek-audio-player'); ?></strong></label><br>
                                <input type="url" 
                                       id="sap_umami_script_url" 
                                       name="sap_umami_script_url" 
                                       value="<?php echo esc_attr(get_option('sap_umami_script_url', '')); ?>" 
                                       class="regular-text"
                                       placeholder="https://stats.example.com/script.js" />
                            </div>
                            
                            <div style="margin-top:8px;">
                                <label for="sap_umami_website_id"><strong><?php echo esc_html__('Website ID:', 'sleek-audio-player'); ?></strong></label><br>
                                <input type="text" 
                                       id="sap_umami_website_id" 
                                       name="sap_umami_website_id" 
                                       value="<?php echo esc_attr(get_option('sap_umami_website_id', '')); ?>" 
                                       class="regular-text"
                                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
                            </div>
                            
                            <p class="description" style="margin-top:8px;">
                                <?php echo esc_html__('Required for tracking in embedded players on external sites.', 'sleek-audio-player'); ?><br>
                                <?php echo esc_html__('Leave empty if Umami is already integrated globally on your site.', 'sleek-audio-player'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_url_protection">🔒 <?php echo esc_html__('URL Protection', 'sleek-audio-player'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sap_url_protection" 
                                       name="sap_url_protection" 
                                       value="1"
                                       <?php checked(get_option('sap_url_protection', false)); ?> />
                                <?php echo esc_html__('Obfuscate audio URLs', 'sleek-audio-player'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Hides real file URLs and uses time-limited tokens.', 'sleek-audio-player'); ?><br>
                                <?php echo wp_kses(__('<strong>Note:</strong> Does not work with CDN! When protection is enabled, CDN will be bypassed.', 'sleek-audio-player'), array('strong' => array())); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_cover_click_play">🖼️ <?php echo esc_html__('Cover Click', 'sleek-audio-player'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sap_cover_click_play" 
                                       name="sap_cover_click_play" 
                                       value="1"
                                       <?php checked(get_option('sap_cover_click_play', true)); ?> />
                                <?php echo esc_html__('Start playback on cover click', 'sleek-audio-player'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('When enabled, clicking the cover image starts the first track.', 'sleek-audio-player'); ?><br>
                                <?php echo esc_html__('Disable this to prevent accidental playback on mobile devices.', 'sleek-audio-player'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_remember_position">⏱️ <?php echo esc_html__('Remember Position', 'sleek-audio-player'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sap_remember_position" 
                                       name="sap_remember_position" 
                                       value="1"
                                       <?php checked(get_option('sap_remember_position', false)); ?> />
                                <?php echo esc_html__('Remember playback position', 'sleek-audio-player'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('When enabled, the player remembers where the listener stopped.', 'sleek-audio-player'); ?><br>
                                <?php echo esc_html__('Playback resumes from that position on the next visit.', 'sleek-audio-player'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label>🎵 <?php echo esc_html__('Visualizer', 'sleek-audio-player'); ?></label>
                        </th>
                        <td>
                            <?php $viz_type = get_option('sap_visualizer_type', 'bars'); ?>
                            <fieldset>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="bars" <?php checked($viz_type, 'bars'); ?> />
                                    <?php echo esc_html__('Bars (Classic frequency bars)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="mirror" <?php checked($viz_type, 'mirror'); ?> />
                                    <?php echo esc_html__('Mirror Bars (Top & bottom reflection)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="circular" <?php checked($viz_type, 'circular'); ?> />
                                    <?php echo esc_html__('Circular (Radial around center)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="oscilloscope" <?php checked($viz_type, 'oscilloscope'); ?> />
                                    <?php echo esc_html__('Oscilloscope (Waveform line)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="dots" <?php checked($viz_type, 'dots'); ?> />
                                    <?php echo esc_html__('Dots (Dancing dots)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="wave" <?php checked($viz_type, 'wave'); ?> />
                                    <?php echo esc_html__('Wave (Filled waveform)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="pulse" <?php checked($viz_type, 'pulse'); ?> />
                                    <?php echo esc_html__('Pulse (Pulsing circle)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="circular_bars" <?php checked($viz_type, 'circular_bars'); ?> />
                                    <?php echo esc_html__('Circular Bars (Bars in a circle)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="particles" <?php checked($viz_type, 'particles'); ?> />
                                    <?php echo esc_html__('Particles (Floating particles)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="starburst" <?php checked($viz_type, 'starburst'); ?> />
                                    <?php echo esc_html__('Starburst (Rays from center)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="orbits" <?php checked($viz_type, 'orbits'); ?> />
                                    <?php echo esc_html__('Orbits (Rotating rings)', 'sleek-audio-player'); ?>
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="off" <?php checked($viz_type, 'off'); ?> />
                                    <?php echo esc_html__('Off (No visualizer)', 'sleek-audio-player'); ?>
                                </label>
                            </fieldset>
                            <p class="description">
                                <?php echo esc_html__('Choose the default visualization. Users can cycle through all options by double-clicking the cover or pressing V.', 'sleek-audio-player'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'sleek-audio-player')); ?>
            </form>
            
            <hr>
            <h2><?php echo esc_html__('Guide: Setting up BunnyCDN', 'sleek-audio-player'); ?></h2>
            <ol>
                <li><?php
                    printf(
                        /* translators: %s is a link to bunny.net */
                        wp_kses(__('Go to %s and create an account', 'sleek-audio-player'), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))),
                        '<a href="https://bunny.net" target="_blank" rel="noopener">bunny.net</a>'
                    );
                ?></li>
                <li><?php echo wp_kses(__('Create a new <strong>Pull Zone</strong>', 'sleek-audio-player'), array('strong' => array())); ?></li>
                <li><?php echo wp_kses(__('Enter your website URL as <strong>Origin URL</strong>:', 'sleek-audio-player'), array('strong' => array())); ?> <code><?php echo esc_url( home_url() ); ?></code></li>
                <li><?php echo wp_kses(__('Copy the Pull Zone URL (e.g. <code>https://yourname.b-cdn.net</code>) and enter it above', 'sleek-audio-player'), array('code' => array())); ?></li>
            </ol>
            
            <h3>⚠️ <?php echo esc_html__('Important: CORS Headers for Audio Files', 'sleek-audio-player'); ?></h3>
            <p><?php echo esc_html__('For audio files to play from CDN, CORS headers must be configured:', 'sleek-audio-player'); ?></p>
            <ol>
                <li><?php echo wp_kses(__('Go to your Pull Zone → <strong>Headers</strong>', 'sleek-audio-player'), array('strong' => array())); ?></li>
                <li><?php echo esc_html__('Add a new header:', 'sleek-audio-player'); ?>
                    <ul>
                        <li><strong><?php echo esc_html__('Header Name:', 'sleek-audio-player'); ?></strong> <code>Access-Control-Allow-Origin</code></li>
                        <li><strong><?php echo esc_html__('Header Value:', 'sleek-audio-player'); ?></strong> <code>*</code> <?php
                            /* translators: %s is the site URL */
                            echo wp_kses(sprintf(__('(or <code>%s</code>)', 'sleek-audio-player'), esc_url( home_url() )), array('code' => array()));
                        ?></li>
                    </ul>
                </li>
                <li><?php echo esc_html__('Save and clear cache', 'sleek-audio-player'); ?></li>
            </ol>
            <p><em><?php echo esc_html__('Without CORS headers, audio files will be blocked by the browser!', 'sleek-audio-player'); ?></em></p>
        </div>
        <?php
    }

    /**
     * Register frontend assets without enqueueing them.
     * Enqueued only when a player actually renders (see maybe_enqueue_assets /
     * enqueue_assets), so pages without a player load zero plugin assets.
     */
    public function register_assets() {
        if (wp_script_is('sleek-audio-player', 'registered')) {
            return;
        }

        // Serve minified builds (tools/minify.py) unless SCRIPT_DEBUG is set;
        // fall back to the unminified sources if a .min file is missing
        $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        $css = file_exists(SLEEKAUDIO_PLUGIN_DIR . "assets/css/player{$suffix}.css") ? "player{$suffix}.css" : 'player.css';
        $js = file_exists(SLEEKAUDIO_PLUGIN_DIR . "assets/js/player{$suffix}.js") ? "player{$suffix}.js" : 'player.js';

        wp_register_style(
            'sleek-audio-player',
            SLEEKAUDIO_PLUGIN_URL . 'assets/css/' . $css,
            array(),
            SLEEKAUDIO_VERSION
        );

        // Inject active theme CSS
        $theme_manager = SleekAudio_Theme_Manager::get_instance();
        $active_theme = $theme_manager->get_active_theme();
        $theme_css = $theme_manager->generate_theme_css($active_theme);
        wp_add_inline_style('sleek-audio-player', $theme_css);

        wp_register_script(
            'sleek-audio-player',
            SLEEKAUDIO_PLUGIN_URL . 'assets/js/' . $js,
            array(),
            SLEEKAUDIO_VERSION,
            true
        );

        // Pass settings to JavaScript
        wp_localize_script('sleek-audio-player', 'sapSettings', array(
            'umamiTracking' => (bool) get_option('sap_umami_tracking', false),
            'coverClickPlay' => (bool) get_option('sap_cover_click_play', true),
            'rememberPosition' => (bool) get_option('sap_remember_position', false),
            'visualizerType' => get_option('sap_visualizer_type', 'bars'),
            // Every string the player writes into the DOM at runtime.
            // player.js always passes an English fallback to sapText(), so a
            // missing key degrades to English instead of breaking the label.
            'i18n' => array(
                'tapToPlay' => __('Tap to Play', 'sleek-audio-player'),

                // More menu labels - full strings, not assembled from parts,
                // so translations can put the words in their own order
                /* translators: %1$s is the elapsed time, %2$s the total length, e.g. "1:23 of 3:45" */
                'progressValueText' => __('%1$s of %2$s', 'sleek-audio-player'),
                'repeatOff' => __('Repeat: Off', 'sleek-audio-player'),
                'repeatAll' => __('Repeat: All', 'sleek-audio-player'),
                'repeatOne' => __('Repeat: One', 'sleek-audio-player'),
                /* translators: %s is the playback speed, e.g. 1.25 */
                'speed' => __('Speed: %sx', 'sleek-audio-player'),
                'sleepOff' => __('Sleep Timer: Off', 'sleek-audio-player'),
                'sleepEndOfTrack' => __('Sleep: End of Track', 'sleek-audio-player'),
                /* translators: %s is the remaining time, e.g. 4:59 or 45s */
                'sleepRemaining' => __('Sleep: %s', 'sleek-audio-player'),
                'coverOff' => __('Cover: Off', 'sleek-audio-player'),
                'coverKenBurns' => __('Cover: Ken Burns', 'sleek-audio-player'),
                'coverVinyl' => __('Cover: Vinyl', 'sleek-audio-player'),
                'adaptiveOn' => __('Adaptive Colors: On', 'sleek-audio-player'),
                'adaptiveOff' => __('Adaptive Colors: Off', 'sleek-audio-player'),
                'copied' => __('✓ Copied!', 'sleek-audio-player'),

                // Visualizer names, shown when cycling with a double-click
                'vizBars' => __('Bars', 'sleek-audio-player'),
                'vizMirror' => __('Mirror', 'sleek-audio-player'),
                'vizCircular' => __('Circular', 'sleek-audio-player'),
                'vizOscilloscope' => __('Oscilloscope', 'sleek-audio-player'),
                'vizDots' => __('Dots', 'sleek-audio-player'),
                'vizWave' => __('Wave', 'sleek-audio-player'),
                'vizPulse' => __('Pulse', 'sleek-audio-player'),
                'vizCircularBars' => __('Circular Bars', 'sleek-audio-player'),
                'vizParticles' => __('Particles', 'sleek-audio-player'),
                'vizStarburst' => __('Starburst', 'sleek-audio-player'),
                'vizOrbits' => __('Orbits', 'sleek-audio-player'),

                // Playback errors shown to the visitor
                'errPlaybackFailed' => __('Playback failed', 'sleek-audio-player'),
                'errTrackLoad' => __('Track could not be loaded', 'sleek-audio-player'),
                'errAborted' => __('Playback aborted', 'sleek-audio-player'),
                'errNetwork' => __('Network error', 'sleek-audio-player'),
                'errDecode' => __('Audio decode error', 'sleek-audio-player'),
                'errFormat' => __('Format not supported', 'sleek-audio-player'),
            ),
        ));
    }

    /**
     * Enqueue the registered player assets. Safe to call multiple times.
     */
    public function enqueue_assets() {
        $this->register_assets();
        wp_enqueue_style('sleek-audio-player');
        wp_enqueue_script('sleek-audio-player');
    }

    /**
     * wp_enqueue_scripts hook: enqueue early (in <head>) when a player is
     * detectable up front. Players rendered outside post content (page
     * builders, widgets, templates) are covered by the late enqueue in
     * render_player() instead.
     */
    public function maybe_enqueue_assets() {
        $this->register_assets();

        if (is_singular('sap_playlist')) {
            $this->enqueue_assets();
            return;
        }

        $post = get_post();
        if ($post && (
            has_shortcode($post->post_content, 'sleek_player')
            || has_shortcode($post->post_content, 'simple_player')
            || (function_exists('has_block') && has_block('sleek-audio-player/player', $post))
        )) {
            $this->enqueue_assets();
        }
    }

    /**
     * Admin Scripts for Media Uploader
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($post_type !== 'sap_playlist') {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script(
            'sap-admin',
            SLEEKAUDIO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'media-upload', 'thickbox'),
            SLEEKAUDIO_VERSION,
            true
        );
        
        // Localize script for waveform auto-analysis
        wp_localize_script('sap-admin', 'sapWaveform', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sap_admin_waveform'),
            'autoAnalyze' => true
        ));

        // addTrackRow() rebuilds the meta box row in JavaScript, so it needs
        // the same strings the PHP template uses - see sapAdminText().
        wp_localize_script('sap-admin', 'sapAdmin', array(
            'i18n' => array(
                'selectCover' => __('Select cover', 'sleek-audio-player'),
                'title' => __('Title', 'sleek-audio-player'),
                'artist' => __('Artist', 'sleek-audio-player'),
                'noFile' => __('No file', 'sleek-audio-player'),
            ),
        ));
        
        wp_enqueue_style('thickbox');
        
        wp_enqueue_style(
            'sap-admin',
            SLEEKAUDIO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SLEEKAUDIO_VERSION
        );
    }

    /**
     * Custom Post Type for Playlists
     */
    public function register_post_type() {
        register_post_type('sap_playlist', array(
            'labels' => array(
                'name' => __('Playlists', 'sleek-audio-player'),
                'singular_name' => __('Playlist', 'sleek-audio-player'),
                'add_new' => __('Add New', 'sleek-audio-player'),
                'add_new_item' => __('Add New Playlist', 'sleek-audio-player'),
                'edit_item' => __('Edit Playlist', 'sleek-audio-player'),
                'view_item' => __('View Playlist', 'sleek-audio-player'),
                'all_items' => __('All Playlists', 'sleek-audio-player'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-playlist-audio',
            'supports' => array('title', 'thumbnail', 'excerpt'),
            'has_archive' => true,
            'rewrite' => array(
                'slug' => 'playlist',
                'with_front' => false,
            ),
        ));
    }

    /**
     * Meta Boxes for Track Data
     */
    public function add_meta_boxes() {
        add_meta_box(
            'sap_tracks',
            __('Tracks', 'sleek-audio-player'),
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
                <div class="sap-track-row" data-index="<?php echo esc_attr( $index ); ?>">
                    <span class="sap-track-handle">☰</span>
                    <div class="sap-track-cover-preview">
                        <?php 
                        $admin_cover_url = '';
                        // Prioritize cover_id (always fresh URL from attachment)
                        if (!empty($track['cover_id'])) {
                            $admin_cover_url = wp_get_attachment_image_url(intval($track['cover_id']), 'medium');
                        }
                        // Fallback to stored cover_url if no cover_id
                        if (empty($admin_cover_url) && !empty($track['cover_url'])) {
                            $admin_cover_url = $track['cover_url'];
                        }
                        if ($admin_cover_url) : ?>
                            <img src="<?php echo esc_url($admin_cover_url); ?>" />
                        <?php else : ?>
                            <span class="sap-no-cover">🎵</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="sap_tracks[<?php echo esc_attr( $index ); ?>][cover_id]" 
                           class="sap-track-cover-id" value="<?php echo esc_attr($track['cover_id'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo esc_attr( $index ); ?>][cover_url]" 
                           class="sap-track-cover-url" value="<?php echo esc_url($admin_cover_url); ?>" />
                    <button type="button" class="button sap-select-cover" title="<?php echo esc_attr__('Select cover', 'sleek-audio-player'); ?>">🖼️</button>
                    <input type="text" name="sap_tracks[<?php echo esc_attr( $index ); ?>][title]" 
                           class="sap-track-title" placeholder="<?php echo esc_attr__('Title', 'sleek-audio-player'); ?>" 
                           value="<?php echo esc_attr($track['title'] ?? ''); ?>" />
                    <input type="text" name="sap_tracks[<?php echo esc_attr( $index ); ?>][artist]" 
                           class="sap-track-artist" placeholder="<?php echo esc_attr__('Artist', 'sleek-audio-player'); ?>" 
                           value="<?php echo esc_attr($track['artist'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo esc_attr( $index ); ?>][url]" 
                           class="sap-track-url" value="<?php echo esc_url($track['url'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo esc_attr( $index ); ?>][attachment_id]"
                           class="sap-track-id" value="<?php echo esc_attr($track['attachment_id'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo esc_attr( $index ); ?>][duration]"
                           class="sap-track-duration" value="<?php echo esc_attr($track['duration'] ?? ''); ?>" />
                    <span class="sap-track-filename"><?php echo esc_html(!empty($track['url']) ? basename($track['url']) : __('No file', 'sleek-audio-player')); ?></span>
                    <button type="button" class="button sap-select-audio">🎵 <?php echo esc_html__('Audio', 'sleek-audio-player'); ?></button>
                    <input type="url" name="sap_tracks[<?php echo esc_attr( $index ); ?>][spotify]" 
                           class="sap-track-link sap-link-spotify" placeholder="🎵 Spotify" 
                           value="<?php echo esc_url($track['spotify'] ?? ''); ?>" />
                    <input type="url" name="sap_tracks[<?php echo esc_attr( $index ); ?>][apple]" 
                           class="sap-track-link sap-link-apple" placeholder="🍎 Apple Music" 
                           value="<?php echo esc_url($track['apple'] ?? ''); ?>" />
                    <input type="url" name="sap_tracks[<?php echo esc_attr( $index ); ?>][amazon]" 
                           class="sap-track-link sap-link-amazon" placeholder="📦 Amazon" 
                           value="<?php echo esc_url($track['amazon'] ?? ''); ?>" />
                    <input type="url" name="sap_tracks[<?php echo esc_attr( $index ); ?>][soundcloud]" 
                           class="sap-track-link sap-link-soundcloud" placeholder="☁️ SoundCloud" 
                           value="<?php echo esc_url($track['soundcloud'] ?? ''); ?>" />
                    <label class="sap-download-label">
                        <input type="checkbox" name="sap_tracks[<?php echo esc_attr( $index ); ?>][downloadable]" 
                               value="1" <?php checked(!empty($track['downloadable'])); ?> />
                        <?php echo esc_html__('DL', 'sleek-audio-player'); ?>
                    </label>
                    <button type="button" class="button sap-remove-track">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-primary" id="sap-add-track">+ <?php echo esc_html__('Add Track', 'sleek-audio-player'); ?></button>
            <button type="button" class="button" id="sap-bulk-add">📁 <?php echo esc_html__('Bulk add from Media Library', 'sleek-audio-player'); ?></button>
        </p>
        
        <!-- Embed Shortcode Info -->
        <div class="sap-embed-info">
            <label><?php echo esc_html__('Embed Shortcode:', 'sleek-audio-player'); ?></label>
            <div class="sap-embed-codes">
                <div class="sap-embed-code">
                    <span class="sap-embed-label"><?php echo esc_html__('Standard:', 'sleek-audio-player'); ?></span>
                    <code class="sap-shortcode" onclick="this.select(); document.execCommand('copy');" title="<?php echo esc_attr__('Click to copy', 'sleek-audio-player'); ?>">[sleek_player id="<?php echo esc_attr( $post->ID ); ?>"]</code>
                </div>
                <div class="sap-embed-code">
                    <span class="sap-embed-label"><?php echo esc_html__('Wide Layout:', 'sleek-audio-player'); ?></span>
                    <code class="sap-shortcode" onclick="this.select(); document.execCommand('copy');" title="<?php echo esc_attr__('Click to copy', 'sleek-audio-player'); ?>">[sleek_player id="<?php echo esc_attr( $post->ID ); ?>" layout="wide"]</code>
                </div>
                <?php if ($post->post_status === 'publish') : ?>
                <div class="sap-embed-code">
                    <span class="sap-embed-label"><?php echo esc_html__('iFrame (external):', 'sleek-audio-player'); ?></span>
                    <code class="sap-shortcode sap-iframe-code" onclick="this.select(); document.execCommand('copy');" title="<?php echo esc_attr__('Click to copy', 'sleek-audio-player'); ?>">&lt;iframe src="<?php echo esc_url(add_query_arg('embed', '1', get_permalink($post->ID))); ?>" width="100%" height="400" frameborder="0" allow="autoplay"&gt;&lt;/iframe&gt;</code>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <input type="hidden" id="sap-track-index" value="<?php echo intval(count($tracks)); ?>" />
        <?php
    }

    /**
     * Streaming Links Metabox
     */
    public function render_streaming_metabox($post) {
        $spotify = get_post_meta($post->ID, '_sap_spotify', true);
        $apple = get_post_meta($post->ID, '_sap_apple', true);
        $amazon = get_post_meta($post->ID, '_sap_amazon', true);
        $soundcloud = get_post_meta($post->ID, '_sap_soundcloud', true);
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
        <p>
            <label><strong>SoundCloud:</strong></label>
            <input type="url" name="sap_soundcloud" value="<?php echo esc_url($soundcloud); ?>" style="width:100%;" placeholder="https://soundcloud.com/..." />
        </p>
        
        <?php if ($post->post_status === 'publish') : ?>
        <hr style="margin: 20px 0;">
        <h4 style="margin-bottom: 10px;">📋 <?php echo esc_html__('Embed Codes', 'sleek-audio-player'); ?></h4>
        
        <p>
            <label><strong><?php echo esc_html__('Shortcode:', 'sleek-audio-player'); ?></strong></label>
            <input type="text" 
                   value='[sleek_player id="<?php echo esc_attr( $post->ID ); ?>"]' 
                   readonly 
                   onclick="this.select(); document.execCommand('copy'); alert('<?php echo esc_js(__('Shortcode copied!', 'sleek-audio-player')); ?>');"
                   style="width:100%; background:#f0f0f0; cursor:pointer;" />
        </p>
        
        <p>
            <label><strong><?php echo esc_html__('Shortcode (Wide Layout):', 'sleek-audio-player'); ?></strong></label>
            <input type="text" 
                   value='[sleek_player id="<?php echo esc_attr( $post->ID ); ?>" layout="wide"]' 
                   readonly 
                   onclick="this.select(); document.execCommand('copy'); alert('<?php echo esc_js(__('Shortcode copied!', 'sleek-audio-player')); ?>');"
                   style="width:100%; background:#f0f0f0; cursor:pointer;" />
        </p>
        
        <p>
            <label><strong><?php echo esc_html__('iFrame Embed:', 'sleek-audio-player'); ?></strong></label>
            <textarea readonly 
                      onclick="this.select(); document.execCommand('copy'); alert('<?php echo esc_js(__('Embed code copied!', 'sleek-audio-player')); ?>');"
                      style="width:100%; height:60px; background:#f0f0f0; cursor:pointer; font-size:11px;">&lt;iframe src="<?php echo esc_url(add_query_arg('embed', '1', get_permalink($post->ID))); ?>" width="100%" height="400" frameborder="0" allow="autoplay"&gt;&lt;/iframe&gt;</textarea>
            <span class="description"><?php echo esc_html__('For external websites', 'sleek-audio-player'); ?></span>
        </p>
        <?php else : ?>
        <p class="description" style="margin-top:15px;"><em><?php echo esc_html__('Embed codes will be shown after publishing.', 'sleek-audio-player'); ?></em></p>
        <?php endif; ?>
        
        <?php
    }

    /**
     * Save Meta
     */
    public function save_meta($post_id) {
        if (!isset($_POST['sap_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sap_nonce'])), 'sap_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save tracks
        if (isset($_POST['sap_tracks']) && is_array($_POST['sap_tracks'])) {
            $tracks = array();
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitized individually below
            $raw_tracks = wp_unslash($_POST['sap_tracks']);
            foreach ($raw_tracks as $track) {
                if (!empty($track['url'])) {
                    $tracks[] = array(
                        'title' => sanitize_text_field($track['title'] ?? ''),
                        'artist' => sanitize_text_field($track['artist'] ?? ''),
                        'url' => esc_url_raw($track['url']),
                        'attachment_id' => absint($track['attachment_id'] ?? 0),
                        'cover_id' => absint($track['cover_id'] ?? 0),
                        'cover_url' => esc_url_raw($track['cover_url'] ?? ''),
                        'spotify' => esc_url_raw($track['spotify'] ?? ''),
                        'apple' => esc_url_raw($track['apple'] ?? ''),
                        'amazon' => esc_url_raw($track['amazon'] ?? ''),
                        'soundcloud' => esc_url_raw($track['soundcloud'] ?? ''),
                        'downloadable' => !empty($track['downloadable']),
                        'duration' => sanitize_text_field($track['duration'] ?? ''),
                    );
                }
            }
            update_post_meta($post_id, '_sap_tracks', $tracks);
        }
    }

    /**
     * Generate JSON-LD Schema markup for SEO
     */
    private function generate_schema_markup($post_id, $tracks, $title, $cover, $total_seconds) {
        // Validate inputs
        if (!is_array($tracks) || empty($tracks)) {
            return '';
        }
        
        $schema_tracks = array();
        
        foreach ($tracks as $index => $track) {
            // Security: Sanitize all track data for JSON output
            $track_title = isset($track['title']) ? wp_strip_all_tags($track['title']) : '';
            if (empty($track_title)) {
                continue;
            }
            
            $track_schema = array(
                '@type' => 'MusicRecording',
                'position' => intval($index) + 1,
                'name' => $track_title,
            );
            
            // Add artist if available (sanitized)
            if (!empty($track['artist'])) {
                $track_schema['byArtist'] = array(
                    '@type' => 'MusicGroup',
                    'name' => wp_strip_all_tags($track['artist']),
                );
            }
            
            // Add duration in ISO 8601 format if available
            if (!empty($track['duration'])) {
                $parts = explode(':', sanitize_text_field($track['duration']));
                if (count($parts) == 2) {
                    $duration_seconds = absint($parts[0]) * 60 + absint($parts[1]);
                    $track_schema['duration'] = 'PT' . $duration_seconds . 'S';
                }
            }
            
            // Add track cover or fallback to playlist cover (validated URL)
            // Priority: cover_id (fresh URL) > cover_url > playlist cover
            $track_cover = '';
            if (!empty($track['cover_id'])) {
                $track_cover = wp_get_attachment_image_url(intval($track['cover_id']), 'large');
            }
            if (empty($track_cover) && !empty($track['cover_url'])) {
                $track_cover = esc_url_raw($track['cover_url']);
            }
            if (empty($track_cover)) {
                $track_cover = $cover;
            }
            if ($track_cover && filter_var($track_cover, FILTER_VALIDATE_URL)) {
                $track_schema['image'] = $track_cover;
            }
            
            // Add streaming links as sameAs (validated URLs only)
            $same_as = array();
            if (!empty($track['spotify']) && filter_var($track['spotify'], FILTER_VALIDATE_URL)) {
                $same_as[] = esc_url_raw($track['spotify']);
            }
            if (!empty($track['apple']) && filter_var($track['apple'], FILTER_VALIDATE_URL)) {
                $same_as[] = esc_url_raw($track['apple']);
            }
            if (!empty($track['amazon']) && filter_var($track['amazon'], FILTER_VALIDATE_URL)) {
                $same_as[] = esc_url_raw($track['amazon']);
            }
            if (!empty($track['soundcloud']) && filter_var($track['soundcloud'], FILTER_VALIDATE_URL)) {
                $same_as[] = esc_url_raw($track['soundcloud']);
            }
            if (!empty($same_as)) {
                $track_schema['sameAs'] = $same_as;
            }
            
            $schema_tracks[] = $track_schema;
        }
        
        // Don't output schema if no valid tracks
        if (empty($schema_tracks)) {
            return '';
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'MusicPlaylist',
            'name' => wp_strip_all_tags($title),
            'numTracks' => count($schema_tracks),
            'track' => $schema_tracks,
        );
        
        // Add cover image (validated)
        if ($cover && filter_var($cover, FILTER_VALIDATE_URL)) {
            $schema['image'] = esc_url_raw($cover);
        }
        
        // Add total duration in ISO 8601 format
        if ($total_seconds > 0) {
            $schema['duration'] = 'PT' . absint($total_seconds) . 'S';
        }
        
        // Add playlist URL
        $permalink = get_permalink($post_id);
        if ($permalink) {
            $schema['url'] = esc_url_raw($permalink);
        }
        
        // wp_json_encode handles escaping for JSON context
        return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }
    
    /**
     * Shortcode: [sleek_player id="123" layout="wide" autoplay="true"]
     */
    public function render_player($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'layout' => '', // 'wide' for horizontal layout
            'autoplay' => '', // 'true' or '1' to enable autoplay
        ), $atts);

        $post_id = intval($atts['id']);
        if (!$post_id) {
            return '<p>' . __('No playlist ID specified.', 'sleek-audio-player') . '</p>';
        }

        $tracks = get_post_meta($post_id, '_sap_tracks', true);
        
        // Validate and sanitize tracks using helper function
        $tracks = sleekaudio_validate_tracks($tracks);
        
        if (empty($tracks)) {
            sleekaudio_log('No valid tracks found for playlist', $post_id);
            return '<p>' . __('No tracks found.', 'sleek-audio-player') . '</p>';
        }
        
        // Apply URL protection or CDN URLs to audio
        $url_protection = get_option('sap_url_protection', false);
        $valid_tracks = array();
        
        foreach ($tracks as $track) {
            // Skip tracks without valid URL
            if (empty($track['url'])) {
                sleekaudio_log('Skipping track without URL', $track['title']);
                continue;
            }
            
            // Verify attachment exists if attachment_id is set
            if (!empty($track['attachment_id'])) {
                $attachment = get_post($track['attachment_id']);
                if (!$attachment || $attachment->post_type !== 'attachment') {
                    sleekaudio_log('Attachment not found, using stored URL', $track['attachment_id']);
                    // Keep the stored URL as fallback
                }
            }
            
            if (!empty($track['url'])) {
                if ($url_protection && !empty($track['attachment_id'])) {
                    // Use obfuscated streaming URL (bypasses CDN)
                    $track['url'] = self::get_stream_url($track['attachment_id']);
                } else {
                    // Use CDN URL if configured
                    $track['url'] = self::cdn_url($track['url']);
                }
            }
            
            // Handle cover: prioritize cover_id (always fresh URL), fallback to cover_url
            if (!empty($track['cover_id'])) {
                // Verify cover attachment exists
                $cover_attachment = get_post($track['cover_id']);
                if ($cover_attachment && $cover_attachment->post_type === 'attachment') {
                    $cover_from_id = wp_get_attachment_image_url(intval($track['cover_id']), 'large');
                    if ($cover_from_id) {
                        $track['cover_url'] = self::cdn_url($cover_from_id);
                    }
                } else {
                    sleekaudio_log('Cover attachment not found', $track['cover_id']);
                }
            } elseif (!empty($track['cover_url'])) {
                // Fallback to stored URL if no cover_id
                $track['cover_url'] = self::cdn_url($track['cover_url']);
            }
            
            // Add waveform data if available
            if (!empty($track['attachment_id'])) {
                $waveform = SleekAudio_Waveform_Manager::get_waveform($track['attachment_id']);
                if ($waveform) {
                    $track['waveform'] = $waveform;
                }
            }
            
            $valid_tracks[] = $track;
        }
        
        // Use validated tracks
        $tracks = $valid_tracks;
        
        // Final check - ensure we have at least one playable track
        if (empty($tracks)) {
            sleekaudio_log('No playable tracks after validation', $post_id);
            return '<p>' . __('No playable tracks found.', 'sleek-audio-player') . '</p>';
        }

        // Late enqueue: catches players rendered outside detectable post content
        // (page builders, widgets, templates). The script loads in the footer;
        // no-op if maybe_enqueue_assets() already enqueued in <head>.
        $this->enqueue_assets();

        $cover = self::cdn_url(get_the_post_thumbnail_url($post_id, 'large'));
        $title = get_the_title($post_id);

        // Calculate total duration
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

        // If wp_head already ran without our stylesheet (late enqueue case),
        // print the <link> right here so the player never renders unstyled
        if (did_action('wp_head') && !wp_style_is('sleek-audio-player', 'done')) {
            wp_print_styles('sleek-audio-player');
        }

        // Generate JSON-LD Schema for SEO
        $schema = $this->generate_schema_markup($post_id, $tracks, $title, $cover, $total_seconds);
        ?>
        <?php 
        // Schema is pre-escaped with wp_json_encode and contains <script> tag
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $schema; 
        ?>
        <?php 
        $layout = strtolower(trim($atts['layout']));
        $is_wide = ($layout === 'wide');
        $is_mini = ($layout === 'mini');
        $layout_class = $is_wide ? ' sap-wide' : ($is_mini ? ' sap-mini' : '');
        $autoplay = in_array(strtolower(trim($atts['autoplay'])), array('true', '1', 'yes'), true);
        
        // Inline styles for wide layout (cache-proof)
        // Note: No !important here so CSS media queries can override on mobile
        $wide_style = $is_wide ? 'display:grid;grid-template-columns:auto 1fr;max-width:100%;width:100%;' : '';
        ?>
        <div class="sap-player<?php echo esc_attr($layout_class); ?>" <?php if($wide_style) echo 'style="' . esc_attr( $wide_style ) . '"'; ?> data-playlist='<?php echo esc_attr(wp_json_encode($tracks)); ?>' data-playlist-id='<?php echo esc_attr($post_id); ?>' data-default-cover='<?php echo esc_url($cover); ?>' <?php if($autoplay) echo 'data-autoplay="true"'; ?> role="region" aria-label="<?php echo esc_attr__('Audio Player', 'sleek-audio-player'); ?>">
            
            <!-- Cover Carousel -->
            <div class="sap-cover-carousel" <?php if($is_wide) echo 'style="' . esc_attr('height:100%;flex-shrink:0;') . '"'; ?>>
                <div class="sap-cover-track">
                    <?php foreach ($tracks as $i => $t) : 
                        $track_cover = !empty($t['cover_url']) ? $t['cover_url'] : $cover;
                        if ($track_cover) :
                    ?>
                        <div class="sap-cover-slide <?php echo esc_attr($i === 0 ? 'active' : ''); ?>" data-index="<?php echo esc_attr( $i ); ?>">
                            <img src="<?php echo esc_url($track_cover); ?>" alt="<?php echo esc_attr($t['title']); ?>" loading="eager" decoding="async" />
                        </div>
                    <?php endif; endforeach; ?>
                </div>
                                <!-- Audio Visualizer -->
                <canvas class="sap-visualizer"></canvas>
            </div>

            <!-- Content Section -->
            <div class="sap-content" <?php if($is_wide) echo 'style="' . esc_attr('display:flex;flex-direction:column;') . '"'; ?>>
                
                <!-- Track Info -->
                <div class="sap-track-info">
                    <?php /* aria-live: the JS writes the track title here on every change, so screen reader users hear what started playing */ ?>
                    <div class="sap-now-playing" aria-live="polite"><?php echo esc_html__('Select a track', 'sleek-audio-player'); ?></div>
                    <div class="sap-artist"></div>
                    <div class="sap-meta">
                        <span><?php /* translators: %s is the number of tracks in the playlist */ printf(esc_html(_n('%s Track', '%s Tracks', count($tracks), 'sleek-audio-player')), esc_html(number_format_i18n(count($tracks)))); ?></span>
                        <span class="sap-meta-divider"></span>
                        <span><?php echo esc_html($total_duration); ?></span>
                    </div>
                </div>

                <!-- Progress with Waveform -->
                <div class="sap-progress-section" role="group" aria-label="<?php echo esc_attr__('Playback progress', 'sleek-audio-player'); ?>">
                    <div class="sap-waveform-container" role="slider" aria-label="<?php echo esc_attr__('Time position', 'sleek-audio-player'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <canvas class="sap-waveform" aria-hidden="true"></canvas>
                        <div class="sap-progress">
                            <div class="sap-progress-bar"></div>
                        </div>
                        <?php /* Filled by the JS on hover; aria-hidden because the slider already exposes the time via aria-valuetext */ ?>
                        <span class="sap-waveform-hover-time" aria-hidden="true"></span>
                    </div>
                    <div class="sap-time-row">
                        <span class="sap-current" aria-live="off">0:00</span>
                        <span class="sap-duration">0:00</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="sap-controls" role="group" aria-label="<?php echo esc_attr__('Audio controls', 'sleek-audio-player'); ?>">
                    <button class="sap-btn sap-prev" title="<?php echo esc_attr__('Previous', 'sleek-audio-player'); ?>" aria-label="<?php echo esc_attr__('Previous track', 'sleek-audio-player'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                    </button>
                    <button class="sap-btn sap-play" title="<?php echo esc_attr__('Play/Pause', 'sleek-audio-player'); ?>" aria-label="<?php echo esc_attr__('Play or pause', 'sleek-audio-player'); ?>">
                        <svg class="sap-icon-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="sap-icon-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </button>
                    <button class="sap-btn sap-next" title="<?php echo esc_attr__('Next', 'sleek-audio-player'); ?>" aria-label="<?php echo esc_attr__('Next track', 'sleek-audio-player'); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                    </button>
                    <div class="sap-more-wrapper">
                        <button class="sap-btn sap-btn-small sap-more-btn" title="<?php echo esc_attr__('More options', 'sleek-audio-player'); ?>" aria-label="<?php echo esc_attr__('More options', 'sleek-audio-player'); ?>" aria-expanded="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                        </button>
                        <div class="sap-more-menu">
                            <button type="button" class="sap-more-item sap-menu-share" data-action="share">
                                <svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>
                                <span><?php echo esc_html__('Share', 'sleek-audio-player'); ?></span>
                            </button>
                            <button type="button" class="sap-more-item sap-menu-shuffle" data-action="shuffle" aria-pressed="false">
                                <svg viewBox="0 0 24 24"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
                                <span><?php echo esc_html__('Shuffle', 'sleek-audio-player'); ?></span>
                            </button>
                            <button type="button" class="sap-more-item sap-download" data-action="download">
                                <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                <span><?php echo esc_html__('Download', 'sleek-audio-player'); ?></span>
                            </button>
                            <button type="button" class="sap-more-item sap-repeat" data-action="repeat" data-mode="off" aria-pressed="false">
                                <svg viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
                                <span><?php echo esc_html__('Repeat: Off', 'sleek-audio-player'); ?></span>
                            </button>
                            <button type="button" class="sap-more-item sap-speed" data-action="speed" aria-pressed="false">
                                <svg viewBox="0 0 24 24"><path d="M20.38 8.57l-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44z"/><path d="M10.59 15.41a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"/></svg>
                                <span class="sap-speed-label"><?php /* translators: %s is the playback speed, e.g. 1.25 */ printf(esc_html__('Speed: %sx', 'sleek-audio-player'), '1'); ?></span>
                            </button>
                            <div class="sap-more-divider"></div>
                            <button type="button" class="sap-more-item sap-sleep-timer" data-action="sleep" aria-pressed="false">
                                <svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z"/></svg>
                                <span class="sap-sleep-label"><?php echo esc_html__('Sleep Timer: Off', 'sleek-audio-player'); ?></span>
                            </button>
                            <div class="sap-sleep-submenu" style="display:none;">
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="0"><?php echo esc_html__('Off', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="5"><?php echo esc_html__('5 Min', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="10"><?php echo esc_html__('10 Min', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="15"><?php echo esc_html__('15 Min', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="30"><?php echo esc_html__('30 Min', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="45"><?php echo esc_html__('45 Min', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="60"><?php echo esc_html__('1 Hour', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-sleep-option" data-minutes="0" data-end-of-track="true"><?php echo esc_html__('End of Track', 'sleek-audio-player'); ?></button>
                            </div>
                            <button type="button" class="sap-more-item sap-cover-anim" data-action="cover-anim" aria-pressed="false">
                                <svg viewBox="0 0 24 24"><path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM9.41 15.95L12 12.36l2.59 3.59L17 13l4 5H3l4-4z"/></svg>
                                <span class="sap-cover-anim-label"><?php echo esc_html__('Cover: Ken Burns', 'sleek-audio-player'); ?></span>
                            </button>
                            <div class="sap-cover-anim-submenu" style="display:none;">
                                <button type="button" class="sap-more-item sap-cover-anim-option" data-anim="none"><?php echo esc_html__('Off', 'sleek-audio-player'); ?></button>
                                <button type="button" class="sap-more-item sap-cover-anim-option" data-anim="kenburns">Ken Burns</button>
                                <button type="button" class="sap-more-item sap-cover-anim-option" data-anim="vinyl">Vinyl</button>
                            </div>
                            <button type="button" class="sap-more-item sap-adaptive-color" data-action="adaptive-color" aria-pressed="false">
                                <svg viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                                <span class="sap-adaptive-color-label"><?php echo esc_html__('Adaptive Colors: Off', 'sleek-audio-player'); ?></span>
                            </button>
                            <div class="sap-more-divider"></div>
                            <button type="button" class="sap-more-item sap-embed-btn" data-action="embed" data-embed-url="<?php echo esc_url(add_query_arg('embed', '1', get_permalink($post_id))); ?>">
                                <svg viewBox="0 0 24 24"><path d="M18 16h-2v-1H8v1H6v-1H2v5h20v-5h-4zm2 3H4v-1h16zM6.5 10H4v5h2V10zm3.5 0H8v5h2v-5zm3.5 0H12v5h2V10zm3.5 0h-2v5h2v-5zm3.5 0h-2v5h2v-5zM2 8h20V3H2zm2-3h16v1H4z"/><path d="M9.4 6.4L8 5l-4 4 4 4 1.4-1.4L6.8 9zm5.2 0L16 5l4 4-4 4-1.4-1.4L17.2 9z" transform="translate(0,9) scale(0.6)"/></svg>
                                <span><?php echo esc_html__('Embed Player', 'sleek-audio-player'); ?></span>
                            </button>
                            <div class="sap-more-divider sap-stream-divider" style="display:none;"></div>
                            <a href="#" target="_blank" rel="noopener" class="sap-more-item sap-stream-link sap-stream-spotify" style="display:none;">
                                <span>Spotify</span>
                            </a>
                            <a href="#" target="_blank" rel="noopener" class="sap-more-item sap-stream-link sap-stream-apple" style="display:none;">
                                <span>Apple Music</span>
                            </a>
                            <a href="#" target="_blank" rel="noopener" class="sap-more-item sap-stream-link sap-stream-amazon" style="display:none;">
                                <span>Amazon Music</span>
                            </a>
                            <a href="#" target="_blank" rel="noopener" class="sap-more-item sap-stream-link sap-stream-soundcloud" style="display:none;">
                                <span>SoundCloud</span>
                            </a>
                        </div>
                    </div>
                    <div class="sap-volume-wrapper">
                        <button class="sap-btn sap-btn-small sap-volume-btn" title="<?php echo esc_attr__('Volume', 'sleek-audio-player'); ?>" aria-label="<?php echo esc_attr__('Volume control', 'sleek-audio-player'); ?>">
                            <svg class="sap-icon-volume-high" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                            <svg class="sap-icon-volume-low" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
                            <svg class="sap-icon-volume-mute" viewBox="0 0 24 24" aria-hidden="true"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                        </button>
                        <div class="sap-volume-slider" role="slider" aria-label="<?php echo esc_attr__('Volume', 'sleek-audio-player'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="70">
                            <div class="sap-volume-track">
                                <div class="sap-volume-fill"></div>
                                <div class="sap-volume-handle"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Playlist -->
                <ul class="sap-playlist" role="listbox" aria-label="<?php echo esc_attr__('Playlist', 'sleek-audio-player'); ?>">
                    <?php foreach ($tracks as $index => $track) : ?>
                        <li class="sap-track" role="option" aria-selected="<?php echo esc_attr($index === 0 ? 'true' : 'false'); ?>" tabindex="0" data-index="<?php echo esc_attr( $index ); ?>" data-url="<?php echo esc_url($track['url']); ?>" data-downloadable="<?php echo esc_attr(!empty($track['downloadable']) ? '1' : '0'); ?>">
                            <span class="sap-track-num" aria-hidden="true"><span><?php echo esc_html( $index + 1 ); ?></span></span>
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
            
            <!-- Embed Modal -->
            <div class="sap-embed-modal" style="display:none;" role="dialog" aria-labelledby="sap-embed-title" aria-modal="true">
                <div class="sap-embed-backdrop"></div>
                <div class="sap-embed-dialog">
                    <div class="sap-embed-header">
                        <h3 id="sap-embed-title"><?php echo esc_html__('Embed Player', 'sleek-audio-player'); ?></h3>
                        <button type="button" class="sap-embed-close" aria-label="<?php echo esc_attr__('Close', 'sleek-audio-player'); ?>">
                            <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                        </button>
                    </div>
                    <div class="sap-embed-body">
                        <div class="sap-embed-option">
                            <label><?php echo esc_html__('Layout', 'sleek-audio-player'); ?></label>
                            <div class="sap-embed-layouts">
                                <button type="button" class="sap-embed-layout active" data-layout="wide" data-height="280">
                                    <svg viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                    <span><?php echo esc_html__('Wide', 'sleek-audio-player'); ?></span>
                                </button>
                                <button type="button" class="sap-embed-layout" data-layout="mini" data-height="150">
                                    <svg viewBox="0 0 24 24"><rect x="2" y="9" width="20" height="6" rx="1" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                    <span><?php echo esc_html__('Mini', 'sleek-audio-player'); ?></span>
                                </button>
                            </div>
                        </div>
                        <div class="sap-embed-option">
                            <label><?php echo esc_html__('Embed Code', 'sleek-audio-player'); ?></label>
                            <textarea class="sap-embed-code" readonly rows="3"></textarea>
                            <button type="button" class="sap-embed-copy">
                                <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                                <span><?php echo esc_html__('Copy Code', 'sleek-audio-player'); ?></span>
                            </button>
                        </div>
                        <div class="sap-embed-preview-section">
                            <label><?php echo esc_html__('Preview', 'sleek-audio-player'); ?></label>
                            <div class="sap-embed-preview"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $output = ob_get_clean();
        // Strip whitespace between tags: page builders sometimes run wpautop over the
        // already-rendered shortcode output, turning our template newlines into stray
        // <br>/<p> elements inside the player (broken More menu spacing on such pages).
        // The player markup contains no whitespace-sensitive content (the embed
        // textarea is empty and filled via JS), so this is safe.
        return preg_replace('/>\s+</', '><', $output);
    }
}
