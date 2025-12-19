<?php
/**
 * Plugin Name: Simple Audio Player
 * Description: Minimal audio player with download, shuffle, cover art, and visualization
 * Version: 2.0.0
 * Author: Martin Graebing
 * Author URI: https://www.duesseldorp.de
 * Plugin URI: https://www.duesseldorp.de
 * Text Domain: simple-audio-player
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

define('SAP_VERSION', '2.0.0');
define('SAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load text domain for translations
add_action('init', function() {
    load_plugin_textdomain('simple-audio-player', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * Theme Manager Class
 */
class SAP_Theme_Manager {
    
    private static $instance = null;
    private $table_name;
    
    // Default theme colors (standard theme)
    public static $default_theme = array(
        'name' => 'Standard',
        'colors' => array(
            // Background colors
            'sap-bg' => '#0a1118',
            'sap-card' => '#0f1a24',
            'sap-card-hover' => '#162230',
            'sap-border' => 'rgba(79, 128, 156, 0.15)',
            // Accent colors
            'sap-accent' => '#e85d3d',
            'sap-accent-light' => '#f4795d',
            'sap-accent-glow' => 'rgba(232, 93, 61, 0.35)',
            'sap-accent-glow-strong' => 'rgba(232, 93, 61, 0.5)',
            'sap-accent-glow-soft' => 'rgba(232, 93, 61, 0.12)',
            // Text colors
            'sap-white' => '#ffffff',
            'sap-gray-100' => 'rgba(255, 255, 255, 0.92)',
            'sap-gray-200' => 'rgba(200, 215, 225, 0.8)',
            'sap-gray-300' => 'rgba(160, 185, 200, 0.6)',
            'sap-gray-400' => 'rgba(120, 150, 170, 0.4)',
            // Effects
            'sap-blue-tint' => 'rgba(70, 130, 170, 0.1)',
            'sap-visualizer' => '#e85d3d',
            'sap-waveform-inactive' => 'rgba(120, 150, 170, 0.4)',
            'sap-track-active' => 'rgba(232, 93, 61, 0.12)',
        ),
        'settings' => array(
            'sap-radius' => '16px',
            'sap-radius-sm' => '10px',
        ),
    );
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sap_themes';
        
        add_action('admin_menu', array($this, 'add_theme_menu'));
        add_action('admin_init', array($this, 'register_theme_settings'));
        add_action('wp_ajax_sap_save_theme', array($this, 'ajax_save_theme'));
        add_action('wp_ajax_sap_delete_theme', array($this, 'ajax_delete_theme'));
        add_action('wp_ajax_sap_get_theme', array($this, 'ajax_get_theme'));
        add_action('wp_ajax_sap_set_active_theme', array($this, 'ajax_set_active_theme'));
    }
    
    /**
     * Create themes table on plugin activation
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sap_themes';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            colors longtext NOT NULL,
            settings longtext NOT NULL,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert default theme if not exists
        $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE is_default = 1", $table_name));
        if (!$existing) {
            $wpdb->insert($table_name, array(
                'name' => 'Standard',
                'colors' => json_encode(self::$default_theme['colors']),
                'settings' => json_encode(self::$default_theme['settings']),
                'is_default' => 1,
            ));
        }
    }
    
    /**
     * Add theme management menu
     */
    public function add_theme_menu() {
        add_submenu_page(
            'edit.php?post_type=sap_playlist',
            'Theme Manager',
            'Themes',
            'manage_options',
            'sap-themes',
            array($this, 'render_theme_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_theme_settings() {
        register_setting('sap_theme_settings', 'sap_active_theme_id', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ));
    }
    
    /**
     * Get all themes
     */
    public function get_all_themes() {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i ORDER BY is_default DESC, name ASC", $this->table_name), ARRAY_A);
        
        foreach ($results as &$theme) {
            $theme['colors'] = json_decode($theme['colors'], true);
            $theme['settings'] = json_decode($theme['settings'], true);
        }
        
        return $results;
    }
    
    /**
     * Get theme by ID
     */
    public function get_theme($id) {
        global $wpdb;
        $theme = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id), ARRAY_A);
        
        if ($theme) {
            $theme['colors'] = json_decode($theme['colors'], true);
            $theme['settings'] = json_decode($theme['settings'], true);
        }
        
        return $theme;
    }
    
    /**
     * Get active theme
     */
    public function get_active_theme() {
        $active_id = get_option('sap_active_theme_id', 0);
        
        if ($active_id) {
            $theme = $this->get_theme($active_id);
            if ($theme) {
                return $theme;
            }
        }
        
        // Return default theme
        global $wpdb;
        $theme = $wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE is_default = 1", $this->table_name), ARRAY_A);
        
        if ($theme) {
            $theme['colors'] = json_decode($theme['colors'], true);
            $theme['settings'] = json_decode($theme['settings'], true);
            return $theme;
        }
        
        // Fallback to hardcoded default
        return array(
            'id' => 0,
            'name' => 'Standard',
            'colors' => self::$default_theme['colors'],
            'settings' => self::$default_theme['settings'],
            'is_default' => 1,
        );
    }
    
    /**
     * Save theme
     */
    public function save_theme($data) {
        global $wpdb;
        
        $theme_data = array(
            'name' => sanitize_text_field($data['name']),
            'colors' => json_encode($data['colors']),
            'settings' => json_encode($data['settings']),
        );
        
        if (!empty($data['id'])) {
            // Update existing
            $wpdb->update(
                $this->table_name,
                $theme_data,
                array('id' => absint($data['id']))
            );
            return absint($data['id']);
        } else {
            // Insert new
            $wpdb->insert($this->table_name, $theme_data);
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Delete theme
     */
    public function delete_theme($id) {
        global $wpdb;
        
        // Don't delete default theme
        $theme = $this->get_theme($id);
        if ($theme && $theme['is_default']) {
            return false;
        }
        
        // If this was active, reset to default
        if (get_option('sap_active_theme_id') == $id) {
            delete_option('sap_active_theme_id');
        }
        
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
    
    /**
     * Reset theme to default values
     */
    public function reset_to_default($id) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            array(
                'colors' => json_encode(self::$default_theme['colors']),
                'settings' => json_encode(self::$default_theme['settings']),
            ),
            array('id' => $id)
        );
    }
    
    /**
     * Generate CSS from theme
     */
    public function generate_theme_css($theme) {
        // Merge with defaults to ensure all variables exist
        $colors = array_merge(self::$default_theme['colors'], $theme['colors'] ?? array());
        $settings = array_merge(self::$default_theme['settings'], $theme['settings'] ?? array());
        
        // Use both :root and .sap-player for higher specificity (fixes admin override issues)
        $css = ":root, .sap-player {\n";
        
        foreach ($colors as $var => $value) {
            $css .= "    --{$var}: {$value} !important;\n";
        }
        
        foreach ($settings as $var => $value) {
            $css .= "    --{$var}: {$value} !important;\n";
        }
        
        $css .= "}\n";
        
        return $css;
    }
    
    /**
     * AJAX: Save theme
     */
    public function ajax_save_theme() {
        check_ajax_referer('sap_theme_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $data = array(
            'id' => isset($_POST['id']) ? absint($_POST['id']) : 0,
            'name' => sanitize_text_field($_POST['name']),
            'colors' => array(),
            'settings' => array(),
        );
        
        // Parse colors
        if (!empty($_POST['colors']) && is_array($_POST['colors'])) {
            foreach ($_POST['colors'] as $key => $value) {
                $data['colors'][sanitize_key($key)] = sanitize_text_field($value);
            }
        }
        
        // Parse settings
        if (!empty($_POST['settings']) && is_array($_POST['settings'])) {
            foreach ($_POST['settings'] as $key => $value) {
                $data['settings'][sanitize_key($key)] = sanitize_text_field($value);
            }
        }
        
        $id = $this->save_theme($data);
        
        wp_send_json_success(array(
            'id' => $id,
            'message' => 'Theme saved!',
        ));
    }
    
    /**
     * AJAX: Delete theme
     */
    public function ajax_delete_theme() {
        check_ajax_referer('sap_theme_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = absint($_POST['id']);
        $result = $this->delete_theme($id);
        
        if ($result) {
            wp_send_json_success('Theme deleted!');
        } else {
            wp_send_json_error('Default theme cannot be deleted.');
        }
    }
    
    /**
     * AJAX: Get theme
     */
    public function ajax_get_theme() {
        check_ajax_referer('sap_theme_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = absint($_POST['id']);
        $theme = $this->get_theme($id);
        
        if ($theme) {
            wp_send_json_success($theme);
        } else {
            wp_send_json_error('Theme not found.');
        }
    }
    
    /**
     * AJAX: Set active theme
     */
    public function ajax_set_active_theme() {
        check_ajax_referer('sap_theme_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $id = absint($_POST['id']);
        update_option('sap_active_theme_id', $id);
        
        wp_send_json_success('Theme activated!');
    }
    
    /**
     * Render theme management page
     */
    public function render_theme_page() {
        $themes = $this->get_all_themes();
        $active_id = get_option('sap_active_theme_id', 0);
        $default_theme = self::$default_theme;
        
        // Grouped color labels for better organization
        $color_groups = array(
            'background' => array(
                'title' => '🎨 Background',
                'colors' => array(
                    'sap-bg' => 'Player Background',
                    'sap-card' => 'Card Background',
                    'sap-card-hover' => 'Card Hover',
                    'sap-border' => 'Border Color',
                )
            ),
            'accent' => array(
                'title' => '✨ Accent & Buttons',
                'colors' => array(
                    'sap-accent' => 'Main Accent (Play Button)',
                    'sap-accent-light' => 'Accent Light (Hover)',
                    'sap-accent-glow' => 'Accent Glow (Shadow)',
                    'sap-accent-glow-strong' => 'Accent Glow Strong',
                    'sap-accent-glow-soft' => 'Accent Glow Soft',
                    'sap-visualizer' => 'Visualizer Color',
                    'sap-waveform-inactive' => 'Waveform Inactive',
                    'sap-track-active' => 'Active Track Background',
                )
            ),
            'text' => array(
                'title' => '📝 Text & Typography',
                'colors' => array(
                    'sap-white' => 'Title (White)',
                    'sap-gray-100' => 'Text Light',
                    'sap-gray-200' => 'Text Normal',
                    'sap-gray-300' => 'Text Dimmed (Artist)',
                    'sap-gray-400' => 'Text Very Dimmed',
                )
            ),
            'effects' => array(
                'title' => '💫 Effects',
                'colors' => array(
                    'sap-blue-tint' => 'Blue Overlay',
                )
            ),
        );
        
        $settings_labels = array(
            'sap-radius' => 'Border Radius',
            'sap-radius-sm' => 'Border Radius (Small)',
        );
        ?>
        <style>
            .sap-theme-manager {
                max-width: 1400px;
                margin: 20px auto;
                padding: 0 20px;
            }
            .sap-theme-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
            }
            .sap-theme-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            .sap-theme-grid {
                display: grid;
                grid-template-columns: 320px 1fr;
                gap: 30px;
            }
            .sap-themes-list {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
            }
            .sap-themes-list-header {
                padding: 16px 20px;
                background: #f8f9fa;
                border-bottom: 1px solid #ddd;
                font-weight: 600;
            }
            .sap-theme-item {
                padding: 16px 20px;
                border-bottom: 1px solid #eee;
                cursor: pointer;
                transition: background 0.2s;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .sap-theme-item:last-child {
                border-bottom: none;
            }
            .sap-theme-item:hover {
                background: #f8f9fa;
            }
            .sap-theme-item.active {
                background: #e7f3ff;
                border-left: 3px solid #2271b1;
            }
            .sap-theme-item.editing {
                background: #fff8e5;
                border-left: 3px solid #dba617;
            }
            .sap-theme-swatch {
                width: 40px;
                height: 40px;
                border-radius: 8px;
                flex-shrink: 0;
                border: 2px solid #ddd;
            }
            .sap-theme-info {
                flex: 1;
            }
            .sap-theme-name {
                font-weight: 600;
                margin-bottom: 2px;
            }
            .sap-theme-badge {
                display: inline-block;
                font-size: 10px;
                padding: 2px 6px;
                border-radius: 4px;
                margin-left: 8px;
                font-weight: 500;
            }
            .sap-badge-default {
                background: #e0e0e0;
                color: #666;
            }
            .sap-badge-active {
                background: #d4edda;
                color: #155724;
            }
            .sap-theme-editor {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 24px;
            }
            .sap-editor-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 24px;
                padding-bottom: 16px;
                border-bottom: 1px solid #eee;
            }
            .sap-editor-title {
                font-size: 18px;
                font-weight: 600;
            }
            .sap-editor-actions {
                display: flex;
                gap: 8px;
            }
            .sap-color-section {
                margin-bottom: 24px;
            }
            .sap-color-section h3 {
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 16px;
                color: #333;
            }
            .sap-color-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 12px;
            }
            .sap-color-field {
                display: flex;
                align-items: center;
                gap: 8px;
                background: #f8f9fa;
                padding: 8px 12px;
                border-radius: 6px;
                border: 1px solid #eee;
            }
            .sap-color-field label {
                width: 100px;
                flex-shrink: 0;
                font-size: 12px;
                color: #555;
                line-height: 1.3;
            }
            .sap-color-field input[type="color"] {
                width: 36px;
                height: 36px;
                padding: 2px;
                border: 1px solid #ddd;
                border-radius: 6px;
                cursor: pointer;
                flex-shrink: 0;
            }
            .sap-color-field input[type="text"] {
                flex: 1;
                min-width: 0;
                padding: 8px 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: monospace;
                font-size: 12px;
            }
            .sap-theme-preview {
                margin-top: 30px;
                padding: 24px;
                border-radius: 12px;
                background: var(--preview-bg, #0a1118);
            }
            .sap-preview-player {
                max-width: 300px;
                margin: 0 auto;
                border-radius: var(--preview-radius, 16px);
                overflow: hidden;
                background: var(--preview-card, #0f1a24);
                border: 1px solid var(--preview-border, rgba(79, 128, 156, 0.15));
            }
            .sap-preview-cover {
                width: 100%;
                aspect-ratio: 1;
                background: linear-gradient(135deg, var(--preview-accent, #e85d3d), var(--preview-accent-light, #f4795d));
                position: relative;
                overflow: hidden;
            }
            .sap-preview-content {
                padding: 20px;
            }
            .sap-preview-title {
                color: var(--preview-white, #fff);
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 4px;
            }
            .sap-preview-artist {
                color: var(--preview-gray, rgba(160, 185, 200, 0.6));
                font-size: 13px;
                margin-bottom: 16px;
            }
            .sap-preview-progress {
                height: 4px;
                background: var(--preview-gray-400, rgba(120, 150, 170, 0.4));
                border-radius: 2px;
                margin-bottom: 16px;
            }
            .sap-preview-progress-bar {
                width: 40%;
                height: 100%;
                background: var(--preview-accent, #e85d3d);
                border-radius: 2px;
            }
            .sap-preview-controls {
                display: flex;
                justify-content: center;
                gap: 12px;
            }
            .sap-preview-btn {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--preview-gray-200, rgba(200, 215, 225, 0.8));
                background: transparent;
            }
            .sap-preview-btn.play {
                width: 48px;
                height: 48px;
                background: var(--preview-accent, #e85d3d);
                color: #fff;
            }
            .sap-preview-btn.small {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
            .sap-preview-info {
                text-align: center;
                margin-bottom: 12px;
            }
            .sap-preview-meta {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-top: 4px;
            }
            .sap-preview-meta-text {
                font-size: 11px;
                color: var(--preview-gray-300, rgba(160, 185, 200, 0.6));
            }
            .sap-preview-meta-dot {
                width: 3px;
                height: 3px;
                border-radius: 50%;
                background: var(--preview-gray-400, rgba(120, 150, 170, 0.4));
            }
            .sap-preview-waveform {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 2px;
                height: 24px;
                margin-bottom: 8px;
            }
            .sap-preview-waveform-bar {
                width: 4px;
                background: var(--preview-waveform-inactive, rgba(120, 150, 170, 0.4));
                border-radius: 1px;
            }
            .sap-preview-waveform-bar.active {
                background: var(--preview-accent, #e85d3d);
            }
            .sap-preview-times {
                display: flex;
                justify-content: space-between;
                margin-bottom: 12px;
            }
            .sap-preview-time {
                font-size: 10px;
                color: var(--preview-gray-300, rgba(160, 185, 200, 0.6));
            }
            .sap-preview-visualizer {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 60px;
                display: flex;
                align-items: flex-end;
                justify-content: center;
                gap: 4px;
                padding: 0 20px;
            }
            .sap-viz-bar {
                width: 8px;
                background: var(--preview-visualizer, #e85d3d);
                border-radius: 2px 2px 0 0;
                opacity: 0.8;
            }
            .sap-preview-playlist {
                margin-top: 12px;
                background: rgba(0,0,0,0.2);
                border-radius: 8px;
                overflow: hidden;
            }
            .sap-preview-track {
                display: flex;
                align-items: center;
                padding: 8px 12px;
                gap: 10px;
                font-size: 11px;
                transition: background 0.15s;
            }
            .sap-preview-track.hover {
                background: var(--preview-card-hover, #162230);
            }
            .sap-preview-track.active {
                background: rgba(232, 93, 61, 0.15);
                border-left: 2px solid var(--preview-accent, #e85d3d);
            }
            .sap-preview-track-num {
                width: 16px;
                text-align: center;
                color: var(--preview-gray-400, rgba(120, 150, 170, 0.4));
                font-size: 10px;
            }
            .sap-preview-track.active .sap-preview-track-num {
                color: var(--preview-accent, #e85d3d);
            }
            .sap-preview-track-title {
                flex: 1;
                color: var(--preview-gray-100, rgba(255, 255, 255, 0.92));
                text-transform: uppercase;
                letter-spacing: 0.02em;
                font-weight: 500;
            }
            .sap-preview-track.active .sap-preview-track-title {
                color: var(--preview-white, #fff);
            }
            .sap-preview-track-duration {
                color: var(--preview-gray-400, rgba(120, 150, 170, 0.4));
            }
            .sap-name-field {
                margin-bottom: 24px;
            }
            .sap-name-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
            }
            .sap-name-field input {
                width: 100%;
                max-width: 300px;
                padding: 10px 14px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
            }
            .sap-btn {
                padding: 10px 20px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
            }
            .sap-btn-primary {
                background: #2271b1;
                color: #fff;
            }
            .sap-btn-primary:hover {
                background: #135e96;
            }
            .sap-btn-secondary {
                background: #f0f0f0;
                color: #333;
                border: 1px solid #ddd;
            }
            .sap-btn-secondary:hover {
                background: #e5e5e5;
            }
            .sap-btn-danger {
                background: #dc3545;
                color: #fff;
            }
            .sap-btn-danger:hover {
                background: #c82333;
            }
            .sap-btn-success {
                background: #28a745;
                color: #fff;
            }
            .sap-btn-success:hover {
                background: #218838;
            }
            .sap-new-theme-btn {
                width: 100%;
                padding: 14px;
                text-align: center;
                background: #f8f9fa;
                border: 2px dashed #ddd;
                border-radius: 8px;
                color: #666;
                cursor: pointer;
                margin-top: 12px;
                transition: all 0.2s;
            }
            .sap-new-theme-btn:hover {
                border-color: #2271b1;
                color: #2271b1;
                background: #f0f7fc;
            }
            @media (max-width: 900px) {
                .sap-theme-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
        
        <div class="sap-theme-manager">
            <div class="sap-theme-header">
                <h1>🎨 Theme Manager</h1>
            </div>
            
            <div class="sap-theme-grid">
                <!-- Themes List -->
                <div>
                    <div class="sap-themes-list">
                        <div class="sap-themes-list-header">Saved Themes</div>
                        <?php foreach ($themes as $theme) : 
                            $is_active = ($active_id == $theme['id']) || ($active_id == 0 && $theme['is_default']);
                            $accent = $theme['colors']['sap-accent'] ?? '#e85d3d';
                            $bg = $theme['colors']['sap-bg'] ?? '#0a1118';
                        ?>
                            <div class="sap-theme-item <?php echo $is_active ? 'active' : ''; ?>" 
                                 data-id="<?php echo esc_attr($theme['id']); ?>">
                                <div class="sap-theme-swatch" style="background: linear-gradient(135deg, <?php echo esc_attr($accent); ?>, <?php echo esc_attr($bg); ?>);"></div>
                                <div class="sap-theme-info">
                                    <div class="sap-theme-name">
                                        <?php echo esc_html($theme['name']); ?>
                                        <?php if ($theme['is_default']) : ?>
                                            <span class="sap-theme-badge sap-badge-default">Standard</span>
                                        <?php endif; ?>
                                        <?php if ($is_active) : ?>
                                            <span class="sap-theme-badge sap-badge-active">Active</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="sap-new-theme-btn" id="sap-new-theme">+ Create New Theme</button>
                </div>
                
                <!-- Theme Editor -->
                <div class="sap-theme-editor" id="sap-theme-editor">
                    <div class="sap-editor-header">
                        <div class="sap-editor-title" id="sap-editor-title">Edit Theme</div>
                        <div class="sap-editor-actions">
                            <button type="button" class="sap-btn sap-btn-secondary" id="sap-reset-theme">↺ Reset</button>
                            <button type="button" class="sap-btn sap-btn-danger" id="sap-delete-theme" style="display:none;">🗑 Delete</button>
                            <button type="button" class="sap-btn sap-btn-success" id="sap-activate-theme">✓ Activate</button>
                            <button type="button" class="sap-btn sap-btn-primary" id="sap-save-theme">💾 Save</button>
                        </div>
                    </div>
                    
                    <form id="sap-theme-form">
                        <input type="hidden" name="id" id="sap-theme-id" value="">
                        <input type="hidden" name="is_default" id="sap-theme-is-default" value="0">
                        
                        <div class="sap-name-field">
                            <label for="sap-theme-name">Theme Name</label>
                            <input type="text" name="name" id="sap-theme-name" value="" placeholder="My Theme" required>
                        </div>
                        
                        <?php foreach ($color_groups as $group_key => $group) : ?>
                        <div class="sap-color-section">
                            <h3><?php echo esc_html($group['title']); ?></h3>
                            <div class="sap-color-grid">
                                <?php foreach ($group['colors'] as $var => $label) : 
                                    $default_value = $default_theme['colors'][$var] ?? '#000000';
                                    // Extract hex color for picker (convert rgba to hex if needed)
                                    $picker_value = '#000000';
                                    if (preg_match('/^#[0-9a-fA-F]{6}$/', $default_value)) {
                                        $picker_value = $default_value;
                                    } elseif (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $default_value, $matches)) {
                                        $picker_value = sprintf('#%02x%02x%02x', $matches[1], $matches[2], $matches[3]);
                                    }
                                ?>
                                    <div class="sap-color-field">
                                        <label><?php echo esc_html($label); ?></label>
                                        <input type="color" 
                                               class="sap-color-picker" 
                                               data-target="<?php echo esc_attr($var); ?>"
                                               value="<?php echo esc_attr($picker_value); ?>">
                                        <input type="text" 
                                               name="colors[<?php echo esc_attr($var); ?>]" 
                                               class="sap-color-input" 
                                               data-var="<?php echo esc_attr($var); ?>"
                                               value="<?php echo esc_attr($default_value); ?>" 
                                               placeholder="<?php echo esc_attr($default_value); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="sap-color-section">
                            <h3>⚙️ Settings</h3>
                            <div class="sap-color-grid">
                                <?php foreach ($settings_labels as $var => $label) : 
                                    $default_value = $default_theme['settings'][$var] ?? '16px';
                                ?>
                                    <div class="sap-color-field">
                                        <label><?php echo esc_html($label); ?></label>
                                        <input type="text" 
                                               name="settings[<?php echo esc_attr($var); ?>]" 
                                               class="sap-setting-input" 
                                               data-var="<?php echo esc_attr($var); ?>"
                                               value="<?php echo esc_attr($default_value); ?>" 
                                               placeholder="<?php echo esc_attr($default_value); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Complete Live Preview -->
                    <div class="sap-theme-preview" id="sap-theme-preview">
                        <div class="sap-preview-player">
                            <div class="sap-preview-cover">
                                <!-- Visualizer bars -->
                                <div class="sap-preview-visualizer">
                                    <div class="sap-viz-bar" style="height:30%"></div>
                                    <div class="sap-viz-bar" style="height:60%"></div>
                                    <div class="sap-viz-bar" style="height:80%"></div>
                                    <div class="sap-viz-bar" style="height:45%"></div>
                                    <div class="sap-viz-bar" style="height:70%"></div>
                                    <div class="sap-viz-bar" style="height:55%"></div>
                                    <div class="sap-viz-bar" style="height:40%"></div>
                                    <div class="sap-viz-bar" style="height:65%"></div>
                                </div>
                            </div>
                            <div class="sap-preview-content">
                                <div class="sap-preview-info">
                                    <div class="sap-preview-title">Track Title</div>
                                    <div class="sap-preview-artist">Artist Name</div>
                                    <div class="sap-preview-meta">
                                        <span class="sap-preview-meta-text">5 Tracks</span>
                                        <span class="sap-preview-meta-dot"></span>
                                        <span class="sap-preview-meta-text">12:34</span>
                                    </div>
                                </div>
                                <!-- Waveform Preview -->
                                <div class="sap-preview-waveform">
                                    <div class="sap-preview-waveform-bar active" style="height:35%"></div>
                                    <div class="sap-preview-waveform-bar active" style="height:55%"></div>
                                    <div class="sap-preview-waveform-bar active" style="height:70%"></div>
                                    <div class="sap-preview-waveform-bar active" style="height:45%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:60%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:80%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:50%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:65%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:40%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:55%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:75%"></div>
                                    <div class="sap-preview-waveform-bar" style="height:45%"></div>
                                </div>
                                <div class="sap-preview-times">
                                    <span class="sap-preview-time">1:23</span>
                                    <span class="sap-preview-time">3:45</span>
                                </div>
                                <div class="sap-preview-controls">
                                    <div class="sap-preview-btn small"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg></div>
                                    <div class="sap-preview-btn"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg></div>
                                    <div class="sap-preview-btn play"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></div>
                                    <div class="sap-preview-btn"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg></div>
                                    <div class="sap-preview-btn small"><svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg></div>
                                </div>
                                <!-- Playlist Preview -->
                                <div class="sap-preview-playlist">
                                    <div class="sap-preview-track active">
                                        <span class="sap-preview-track-num">●</span>
                                        <span class="sap-preview-track-title">Active Track</span>
                                        <span class="sap-preview-track-duration">3:45</span>
                                    </div>
                                    <div class="sap-preview-track">
                                        <span class="sap-preview-track-num">2</span>
                                        <span class="sap-preview-track-title">Next Song</span>
                                        <span class="sap-preview-track-duration">4:12</span>
                                    </div>
                                    <div class="sap-preview-track hover">
                                        <span class="sap-preview-track-num">3</span>
                                        <span class="sap-preview-track-title">Hover State</span>
                                        <span class="sap-preview-track-duration">2:58</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var defaultTheme = <?php echo json_encode($default_theme); ?>;
            var currentThemeId = null;
            var nonce = '<?php echo wp_create_nonce('sap_theme_nonce'); ?>';
            
            // Update preview
            function updatePreview() {
                var preview = $('#sap-theme-preview');
                var player = preview.find('.sap-preview-player');
                
                // Get color values
                var colors = {};
                $('.sap-color-input').each(function() {
                    colors[$(this).data('var')] = $(this).val();
                });
                
                // Background colors
                preview.css('background', colors['sap-bg']);
                player.css('background', colors['sap-card']);
                player.css('border-color', colors['sap-border']);
                preview.find('.sap-preview-track.hover').css('background', colors['sap-card-hover']);
                
                // Accent colors
                preview.find('.sap-preview-cover').css('background', 'linear-gradient(135deg, ' + colors['sap-accent'] + ', ' + colors['sap-accent-light'] + ')');
                preview.find('.sap-preview-progress-bar').css('background', colors['sap-accent']);
                preview.find('.sap-preview-btn.play').css('background', colors['sap-accent']);
                
                // Track active background
                var trackActiveBg = colors['sap-track-active'] || colors['sap-accent-glow-soft'] || 'rgba(232, 93, 61, 0.12)';
                preview.find('.sap-preview-track.active').css({
                    'background': trackActiveBg,
                    'border-left-color': colors['sap-accent']
                });
                preview.find('.sap-preview-track.active .sap-preview-track-num').css('color', colors['sap-accent']);
                
                // Waveform colors
                preview.find('.sap-preview-waveform-bar.active').css('background', colors['sap-accent']);
                preview.find('.sap-preview-waveform-bar:not(.active)').css('background', colors['sap-waveform-inactive']);
                
                // Visualizer
                preview.find('.sap-viz-bar').css('background', colors['sap-visualizer']);
                
                // Text colors
                preview.find('.sap-preview-title').css('color', colors['sap-white']);
                preview.find('.sap-preview-track.active .sap-preview-track-title').css('color', colors['sap-white']);
                preview.find('.sap-preview-track-title').not('.active .sap-preview-track-title').css('color', colors['sap-gray-100']);
                preview.find('.sap-preview-artist').css('color', colors['sap-gray-300']);
                preview.find('.sap-preview-meta-text').css('color', colors['sap-gray-300']);
                preview.find('.sap-preview-time').css('color', colors['sap-gray-300']);
                preview.find('.sap-preview-progress').css('background', colors['sap-gray-400']);
                preview.find('.sap-preview-meta-dot').css('background', colors['sap-gray-400']);
                preview.find('.sap-preview-track-num').not('.active .sap-preview-track-num').css('color', colors['sap-gray-400']);
                preview.find('.sap-preview-track-duration').css('color', colors['sap-gray-400']);
                preview.find('.sap-preview-btn').not('.play').css('color', colors['sap-gray-200']);
                
                // Border radius
                var radius = $('.sap-setting-input[data-var="sap-radius"]').val();
                var radiusSm = $('.sap-setting-input[data-var="sap-radius-sm"]').val();
                player.css('border-radius', radius);
                preview.find('.sap-preview-playlist').css('border-radius', radiusSm);
            }
            
            // Helper: hex to rgb
            function hexToRgb(hex) {
                var r = parseInt(hex.slice(1, 3), 16);
                var g = parseInt(hex.slice(3, 5), 16);
                var b = parseInt(hex.slice(5, 7), 16);
                return {r: r, g: g, b: b};
            }
            
            // Helper: extract hex from rgba
            function rgbaToHex(rgba) {
                var match = rgba.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
                if (match) {
                    var r = parseInt(match[1]).toString(16).padStart(2, '0');
                    var g = parseInt(match[2]).toString(16).padStart(2, '0');
                    var b = parseInt(match[3]).toString(16).padStart(2, '0');
                    return '#' + r + g + b;
                }
                return '#000000';
            }
            
            // Color picker sync - handles both hex and rgba
            $(document).on('input', '.sap-color-picker', function() {
                var target = $(this).data('target');
                var hexValue = $(this).val();
                var input = $('.sap-color-input[data-var="' + target + '"]');
                var currentValue = input.val();
                
                // Check if current value is rgba format
                if (currentValue.indexOf('rgba') === 0) {
                    // Extract alpha from current value
                    var alphaMatch = currentValue.match(/,\s*([\d.]+)\)$/);
                    var alpha = alphaMatch ? alphaMatch[1] : '1';
                    var rgb = hexToRgb(hexValue);
                    input.val('rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + alpha + ')');
                } else if (currentValue.indexOf('rgb(') === 0) {
                    var rgb = hexToRgb(hexValue);
                    input.val('rgb(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ')');
                } else {
                    input.val(hexValue);
                }
                updatePreview();
            });
            
            $(document).on('input change', '.sap-color-input, .sap-setting-input', function() {
                var varName = $(this).data('var');
                var value = $(this).val();
                var picker = $('.sap-color-picker[data-target="' + varName + '"]');
                
                if (picker.length) {
                    // Sync picker - convert rgba to hex if needed
                    if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                        picker.val(value);
                    } else if (value.indexOf('rgb') === 0) {
                        picker.val(rgbaToHex(value));
                    }
                }
                updatePreview();
            });
            
            // Load theme into editor
            function loadTheme(theme) {
                currentThemeId = theme.id;
                $('#sap-theme-id').val(theme.id || '');
                $('#sap-theme-is-default').val(theme.is_default || 0);
                $('#sap-theme-name').val(theme.name || '');
                $('#sap-editor-title').text(theme.name ? 'Theme: ' + theme.name : 'New Theme');
                
                // Load colors
                if (theme.colors) {
                    $.each(theme.colors, function(key, value) {
                        var input = $('.sap-color-input[data-var="' + key + '"]');
                        input.val(value);
                        var picker = $('.sap-color-picker[data-target="' + key + '"]');
                        if (picker.length) {
                            // Convert to hex for picker
                            if (/^#[0-9a-fA-F]{6}$/.test(value)) {
                                picker.val(value);
                            } else if (value.indexOf('rgb') === 0) {
                                picker.val(rgbaToHex(value));
                            }
                        }
                    });
                }
                
                // Load settings
                if (theme.settings) {
                    $.each(theme.settings, function(key, value) {
                        $('.sap-setting-input[data-var="' + key + '"]').val(value);
                    });
                }
                
                // Show/hide delete button (is_default can be "1" string or 1 number)
                var isDefault = theme.is_default == 1 || theme.is_default === '1' || theme.is_default === true;
                if (isDefault) {
                    $('#sap-delete-theme').hide();
                } else if (theme.id) {
                    $('#sap-delete-theme').show();
                } else {
                    $('#sap-delete-theme').hide();
                }
                
                updatePreview();
            }
            
            // Click on theme item
            $(document).on('click', '.sap-theme-item', function() {
                var id = $(this).data('id');
                
                $('.sap-theme-item').removeClass('editing');
                $(this).addClass('editing');
                
                $.post(ajaxurl, {
                    action: 'sap_get_theme',
                    id: id,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        loadTheme(response.data);
                    }
                });
            });
            
            // New theme
            $('#sap-new-theme').on('click', function() {
                $('.sap-theme-item').removeClass('editing');
                loadTheme({
                    id: '',
                    name: '',
                    colors: defaultTheme.colors,
                    settings: defaultTheme.settings,
                    is_default: 0
                });
            });
            
            // Reset to default
            $('#sap-reset-theme').on('click', function() {
                if (confirm('Reset all colors to default?')) {
                    loadTheme({
                        id: currentThemeId,
                        name: $('#sap-theme-name').val(),
                        colors: defaultTheme.colors,
                        settings: defaultTheme.settings,
                        is_default: $('#sap-theme-is-default').val()
                    });
                }
            });
            
            // Save theme
            $('#sap-save-theme').on('click', function() {
                var name = $('#sap-theme-name').val();
                if (!name) {
                    alert('Please enter a name.');
                    return;
                }
                
                var data = {
                    action: 'sap_save_theme',
                    nonce: nonce,
                    id: $('#sap-theme-id').val(),
                    name: name,
                    colors: {},
                    settings: {}
                };
                
                $('.sap-color-input').each(function() {
                    data.colors[$(this).data('var')] = $(this).val();
                });
                
                $('.sap-setting-input').each(function() {
                    data.settings[$(this).data('var')] = $(this).val();
                });
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            // Delete theme
            $('#sap-delete-theme').on('click', function() {
                if (!confirm('Really delete this theme?')) return;
                
                $.post(ajaxurl, {
                    action: 'sap_delete_theme',
                    nonce: nonce,
                    id: currentThemeId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            // Activate theme
            $('#sap-activate-theme').on('click', function() {
                if (!currentThemeId) {
                    alert('Please save the theme first.');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'sap_set_active_theme',
                    nonce: nonce,
                    id: currentThemeId
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            // Load first theme on init
            if ($('.sap-theme-item').length) {
                $('.sap-theme-item.active').first().click();
                if (!$('.sap-theme-item.editing').length) {
                    $('.sap-theme-item').first().click();
                }
            }
        });
        </script>
        <?php
    }
}

// Initialize theme manager
SAP_Theme_Manager::get_instance();

// Activation hook for creating table
register_activation_hook(__FILE__, array('SAP_Theme_Manager', 'create_table'));

/**
 * Waveform Manager Class
 * Generates and stores real waveform data for audio files
 */
class SAP_Waveform_Manager {
    
    private static $instance = null;
    const PEAKS_COUNT = 100;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_waveform_menu'));
        add_action('wp_ajax_sap_analyze_waveform', array($this, 'ajax_analyze_waveform'));
        add_action('wp_ajax_sap_save_waveform', array($this, 'ajax_save_waveform'));
        add_action('wp_ajax_sap_get_pending_waveforms', array($this, 'ajax_get_pending_waveforms'));
    }
    
    /**
     * Add waveform menu
     */
    public function add_waveform_menu() {
        add_submenu_page(
            'edit.php?post_type=sap_playlist',
            'Waveform Analysis',
            'Waveforms',
            'manage_options',
            'sap-waveforms',
            array($this, 'render_waveform_page')
        );
    }
    
    /**
     * Get all audio attachments used in playlists
     */
    public function get_playlist_audio_attachments() {
        global $wpdb;
        
        $playlists = get_posts(array(
            'post_type' => 'sap_playlist',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));
        
        $attachments = array();
        
        foreach ($playlists as $playlist) {
            $tracks = get_post_meta($playlist->ID, '_sap_tracks', true);
            if (!empty($tracks) && is_array($tracks)) {
                foreach ($tracks as $track) {
                    if (!empty($track['attachment_id'])) {
                        $att_id = absint($track['attachment_id']);
                        if (!isset($attachments[$att_id])) {
                            $file_path = get_attached_file($att_id);
                            $waveform = get_post_meta($att_id, '_sap_waveform', true);
                            $attachments[$att_id] = array(
                                'id' => $att_id,
                                'title' => $track['title'] ?? get_the_title($att_id),
                                'url' => wp_get_attachment_url($att_id),
                                'file_path' => $file_path,
                                'has_waveform' => !empty($waveform),
                                'playlist' => $playlist->post_title,
                            );
                        }
                    }
                }
            }
        }
        
        return array_values($attachments);
    }
    
    /**
     * Get waveform data for an attachment
     */
    public static function get_waveform($attachment_id) {
        $waveform = get_post_meta($attachment_id, '_sap_waveform', true);
        if (!empty($waveform) && is_array($waveform)) {
            return $waveform;
        }
        return null;
    }
    
    /**
     * Save waveform data for an attachment
     */
    public static function save_waveform($attachment_id, $peaks) {
        if (!is_array($peaks) || count($peaks) < 10) {
            return false;
        }
        
        // Normalize peaks to 0-1 range
        $max = max($peaks);
        if ($max > 0) {
            $peaks = array_map(function($p) use ($max) {
                return round($p / $max, 3);
            }, $peaks);
        }
        
        update_post_meta($attachment_id, '_sap_waveform', $peaks);
        return true;
    }
    
    /**
     * AJAX: Get pending waveforms
     */
    public function ajax_get_pending_waveforms() {
        check_ajax_referer('sap_waveform_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachments = $this->get_playlist_audio_attachments();
        $pending = array_filter($attachments, function($a) {
            return !$a['has_waveform'];
        });
        
        wp_send_json_success(array(
            'pending' => array_values($pending),
            'total' => count($attachments),
            'analyzed' => count($attachments) - count($pending),
        ));
    }
    
    /**
     * AJAX: Save waveform from client-side analysis
     */
    public function ajax_save_waveform() {
        check_ajax_referer('sap_waveform_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $peaks = json_decode(stripslashes($_POST['peaks'] ?? '[]'), true);
        
        if (!$attachment_id || !is_array($peaks)) {
            wp_send_json_error('Invalid data');
        }
        
        if (self::save_waveform($attachment_id, $peaks)) {
            wp_send_json_success('Waveform saved');
        } else {
            wp_send_json_error('Failed to save waveform');
        }
    }
    
    /**
     * AJAX: Analyze single waveform (server-side with getID3)
     */
    public function ajax_analyze_waveform() {
        check_ajax_referer('sap_waveform_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = absint($_POST['attachment_id'] ?? 0);
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error('File not found');
        }
        
        // Try server-side analysis (basic approach using file sampling)
        $peaks = $this->analyze_audio_file($file_path);
        
        if ($peaks) {
            self::save_waveform($attachment_id, $peaks);
            wp_send_json_success(array('peaks' => $peaks, 'method' => 'server'));
        } else {
            // Return URL for client-side analysis
            wp_send_json_success(array(
                'needs_client_analysis' => true,
                'url' => wp_get_attachment_url($attachment_id),
            ));
        }
    }
    
    /**
     * Analyze audio file server-side
     * Basic approach: read MP3 frame headers to estimate amplitude
     */
    private function analyze_audio_file($file_path) {
        // Check for getID3 (comes with WordPress)
        if (!function_exists('wp_read_audio_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // For now, return null to trigger client-side analysis
        // Server-side MP3 parsing is complex without FFmpeg
        return null;
    }
    
    /**
     * Render waveform management page
     */
    public function render_waveform_page() {
        $attachments = $this->get_playlist_audio_attachments();
        $pending_count = count(array_filter($attachments, function($a) { return !$a['has_waveform']; }));
        ?>
        <div class="wrap">
            <h1>🎵 Waveform Analysis</h1>
            <p>Generate real waveform data from your audio files for an authentic waveform display in the player.</p>
            
            <div class="sap-waveform-stats" style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;margin:20px 0;">
                <h2 style="margin-top:0;">Status</h2>
                <p>
                    <strong><?php echo count($attachments); ?></strong> audio files found in playlists<br>
                    <strong style="color:#46b450;"><?php echo count($attachments) - $pending_count; ?></strong> analyzed<br>
                    <strong style="color:#dc3232;"><?php echo $pending_count; ?></strong> pending
                </p>
                
                <?php if ($pending_count > 0) : ?>
                <button type="button" id="sap-analyze-all" class="button button-primary button-hero" data-mode="pending">
                    🔬 Analyze <?php echo $pending_count; ?> pending files
                </button>
                <?php else : ?>
                <p style="color:#46b450;font-weight:600;">✓ All files have been analyzed!</p>
                <?php endif; ?>
                
                <?php if (count($attachments) > 0) : ?>
                <button type="button" id="sap-reanalyze-all" class="button" style="margin-left:10px;">
                    🔄 Re-analyze all <?php echo count($attachments); ?> files
                </button>
                <?php endif; ?>
                
                <div id="sap-analysis-progress" style="display:none;margin-top:20px;">
                    <div style="background:#e0e0e0;border-radius:4px;height:24px;overflow:hidden;">
                        <div id="sap-progress-bar" style="background:#0073aa;height:100%;width:0%;transition:width 0.3s;"></div>
                    </div>
                    <p id="sap-progress-text" style="margin-top:10px;">Analyzing...</p>
                </div>
            </div>
            
            <h2>Audio Files</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px;">ID</th>
                        <th>Title</th>
                        <th>Playlist</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:200px;">Waveform</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attachments as $att) : ?>
                    <tr data-id="<?php echo $att['id']; ?>">
                        <td><?php echo $att['id']; ?></td>
                        <td><?php echo esc_html($att['title']); ?></td>
                        <td><?php echo esc_html($att['playlist']); ?></td>
                        <td>
                            <?php if ($att['has_waveform']) : ?>
                                <span style="color:#46b450;">✓ Analyzed</span>
                            <?php else : ?>
                                <span style="color:#dc3232;">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <canvas class="sap-mini-waveform" data-id="<?php echo $att['id']; ?>" 
                                    style="width:180px;height:30px;background:#f0f0f0;border-radius:4px;"
                                    data-peaks='<?php echo esc_attr(json_encode(self::get_waveform($att['id']) ?: [])); ?>'></canvas>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const nonce = '<?php echo wp_create_nonce('sap_waveform_nonce'); ?>';
            
            // Draw existing waveforms
            $('.sap-mini-waveform').each(function() {
                const canvas = this;
                const peaks = JSON.parse($(this).attr('data-peaks') || '[]');
                if (peaks.length > 0) {
                    drawMiniWaveform(canvas, peaks);
                }
            });
            
            function drawMiniWaveform(canvas, peaks) {
                const ctx = canvas.getContext('2d');
                const width = canvas.offsetWidth;
                const height = canvas.offsetHeight;
                canvas.width = width * 2;
                canvas.height = height * 2;
                ctx.scale(2, 2);
                
                ctx.clearRect(0, 0, width, height);
                const barWidth = width / peaks.length;
                
                for (let i = 0; i < peaks.length; i++) {
                    const barHeight = peaks[i] * height * 0.8;
                    const x = i * barWidth;
                    const y = (height - barHeight) / 2;
                    ctx.fillStyle = '#0073aa';
                    ctx.fillRect(x, y, barWidth - 1, barHeight);
                }
            }
            
            // Analyze all button
            $('#sap-analyze-all').on('click', async function() {
                const $btn = $(this);
                const $progress = $('#sap-analysis-progress');
                const $bar = $('#sap-progress-bar');
                const $text = $('#sap-progress-text');
                
                $btn.prop('disabled', true);
                $progress.show();
                
                // Get pending files
                const pending = [];
                $('tr[data-id]').each(function() {
                    if ($(this).find('td:eq(3)').text().includes('Pending')) {
                        pending.push({
                            id: $(this).data('id'),
                            row: $(this)
                        });
                    }
                });
                
                for (let i = 0; i < pending.length; i++) {
                    const item = pending[i];
                    const percent = Math.round((i / pending.length) * 100);
                    $bar.css('width', percent + '%');
                    $text.text('Analyzing ' + (i + 1) + ' of ' + pending.length + '...');
                    
                    try {
                        // First try server-side
                        const response = await $.post(ajaxurl, {
                            action: 'sap_analyze_waveform',
                            nonce: nonce,
                            attachment_id: item.id
                        });
                        
                        if (response.success && response.data.needs_client_analysis) {
                            // Client-side analysis needed
                            const peaks = await analyzeAudioClient(response.data.url);
                            if (peaks) {
                                await $.post(ajaxurl, {
                                    action: 'sap_save_waveform',
                                    nonce: nonce,
                                    attachment_id: item.id,
                                    peaks: JSON.stringify(peaks)
                                });
                                
                                // Update UI
                                const canvas = item.row.find('.sap-mini-waveform')[0];
                                drawMiniWaveform(canvas, peaks);
                                item.row.find('td:eq(3)').html('<span style="color:#46b450;">✓ Analyzed</span>');
                            }
                        } else if (response.success && response.data.peaks) {
                            // Server-side worked
                            const canvas = item.row.find('.sap-mini-waveform')[0];
                            drawMiniWaveform(canvas, response.data.peaks);
                            item.row.find('td:eq(3)').html('<span style="color:#46b450;">✓ Analyzed</span>');
                        }
                    } catch (e) {
                        console.error('Error analyzing', item.id, e);
                    }
                }
                
                $bar.css('width', '100%');
                $text.text('✓ Analysis complete!');
                $btn.text('✓ Done').prop('disabled', true);
            });
            
            // Re-analyze ALL files button
            $('#sap-reanalyze-all').on('click', async function() {
                const $btn = $(this);
                const $progress = $('#sap-analysis-progress');
                const $bar = $('#sap-progress-bar');
                const $text = $('#sap-progress-text');
                
                $btn.prop('disabled', true);
                $('#sap-analyze-all').prop('disabled', true);
                $progress.show();
                
                // Get ALL files (not just pending)
                const allFiles = [];
                $('tr[data-id]').each(function() {
                    allFiles.push({
                        id: $(this).data('id'),
                        row: $(this)
                    });
                });
                
                for (let i = 0; i < allFiles.length; i++) {
                    const item = allFiles[i];
                    const percent = Math.round((i / allFiles.length) * 100);
                    $bar.css('width', percent + '%');
                    $text.text('Analysiere ' + (i + 1) + ' von ' + allFiles.length + '...');
                    
                    try {
                        // First try server-side
                        const response = await $.post(ajaxurl, {
                            action: 'sap_analyze_waveform',
                            nonce: nonce,
                            attachment_id: item.id
                        });
                        
                        if (response.success && response.data.needs_client_analysis) {
                            // Client-side analysis needed
                            const peaks = await analyzeAudioClient(response.data.url);
                            if (peaks) {
                                await $.post(ajaxurl, {
                                    action: 'sap_save_waveform',
                                    nonce: nonce,
                                    attachment_id: item.id,
                                    peaks: JSON.stringify(peaks)
                                });
                                
                                // Update UI
                                const canvas = item.row.find('.sap-mini-waveform')[0];
                                drawMiniWaveform(canvas, peaks);
                                item.row.find('td:eq(3)').html('<span style="color:#46b450;">✓ Analyzed</span>');
                            }
                        } else if (response.success && response.data.peaks) {
                            // Server-side worked
                            const canvas = item.row.find('.sap-mini-waveform')[0];
                            drawMiniWaveform(canvas, response.data.peaks);
                            item.row.find('td:eq(3)').html('<span style="color:#46b450;">✓ Analyzed</span>');
                        }
                    } catch (e) {
                        console.error('Error analyzing', item.id, e);
                    }
                }
                
                $bar.css('width', '100%');
                $text.text('✓ All ' + allFiles.length + ' files re-analyzed!');
                $btn.text('✓ Done').prop('disabled', true);
            });
            
            // Client-side audio analysis using Web Audio API
            async function analyzeAudioClient(url) {
                return new Promise((resolve) => {
                    fetch(url)
                        .then(response => response.arrayBuffer())
                        .then(arrayBuffer => {
                            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                            audioContext.decodeAudioData(arrayBuffer, function(audioBuffer) {
                                const peaks = extractPeaks(audioBuffer, 150);
                                audioContext.close();
                                resolve(peaks);
                            }, function(e) {
                                console.error('Decode error', e);
                                resolve(null);
                            });
                        })
                        .catch(e => {
                            console.error('Fetch error', e);
                            resolve(null);
                        });
                });
            }
            
            function extractPeaks(audioBuffer, peakCount) {
                // Use both channels if stereo
                const channelData = audioBuffer.getChannelData(0);
                const channelData2 = audioBuffer.numberOfChannels > 1 ? audioBuffer.getChannelData(1) : null;
                const samplesPerPeak = Math.floor(channelData.length / peakCount);
                const peaks = [];
                
                for (let i = 0; i < peakCount; i++) {
                    const start = i * samplesPerPeak;
                    const end = start + samplesPerPeak;
                    let sum = 0;
                    let max = 0;
                    
                    // Calculate RMS and peak for this segment
                    for (let j = start; j < end; j++) {
                        let sample = Math.abs(channelData[j]);
                        if (channelData2) {
                            sample = Math.max(sample, Math.abs(channelData2[j]));
                        }
                        sum += sample * sample;
                        if (sample > max) max = sample;
                    }
                    
                    // Blend RMS and peak for natural look (70% RMS, 30% peak)
                    const rms = Math.sqrt(sum / (end - start));
                    const blended = rms * 0.7 + max * 0.3;
                    peaks.push(blended);
                }
                
                return peaks;
            }
        });
        </script>
        <?php
    }
}

// Initialize waveform manager
SAP_Waveform_Manager::get_instance();

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
        $shared_track = isset($_GET['track']) ? absint($_GET['track']) : 0;
        $shared_playlist = isset($_GET['playlist']) ? absint($_GET['playlist']) : 0;
        
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
                // Priority: cover_url > cover_id (attachment) > playlist cover
                $track_cover = '';
                
                // First try direct cover_url
                if (!empty($track['cover_url'])) {
                    $track_cover = $track['cover_url'];
                    // Apply CDN if configured
                    $track_cover = self::cdn_url($track_cover);
                }
                
                // Fallback to cover_id attachment
                if (empty($track_cover) && !empty($track['cover_id'])) {
                    $track_cover = wp_get_attachment_image_url(intval($track['cover_id']), 'large');
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
        <!-- Simple Audio Player - Open Graph Tags -->
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
     * Register Gutenberg Block
     */
    public function register_gutenberg_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Block-Script registrieren
        wp_register_script(
            'sap-block-editor',
            SAP_PLUGIN_URL . 'assets/js/block.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-api-fetch'),
            SAP_VERSION,
            true
        );
        
        // Block registrieren
        register_block_type('simple-audio-player/player', array(
            'editor_script' => 'sap-block-editor',
            'render_callback' => array($this, 'render_gutenberg_block'),
            'attributes' => array(
                'playlistId' => array(
                    'type' => 'string',
                    'default' => ''
                )
            )
        ));
    }
    
    /**
     * Render Gutenberg Block (Frontend)
     */
    public function render_gutenberg_block($attributes) {
        $playlist_id = isset($attributes['playlistId']) ? intval($attributes['playlistId']) : 0;
        
        if (!$playlist_id) {
            return '';
        }
        
        return $this->render_player(array('id' => $playlist_id));
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
            return new WP_Error('not_found', __('Playlist not found', 'simple-audio-player'), array('status' => 404));
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
        if (!isset($_GET['sap_stream'])) return;
        
        $token = sanitize_text_field($_GET['sap_stream']);
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
            $range = sanitize_text_field($_SERVER['HTTP_RANGE']);
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
        
        $length = $end - $start + 1;
        
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . $length);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Stream the file
        $fp = fopen($file_path, 'rb');
        fseek($fp, $start);
        
        $buffer_size = 8192;
        $bytes_remaining = $length;
        
        while ($bytes_remaining > 0 && !feof($fp)) {
            $read_size = min($buffer_size, $bytes_remaining);
            echo fread($fp, $read_size);
            $bytes_remaining -= $read_size;
            flush();
        }
        
        fclose($fp);
        exit;
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
            'Player Settings',
            'Settings',
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
            <h1>Simple Audio Player - Settings</h1>
            
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
                                   placeholder="https://your-zone.b-cdn.net" />
                            <p class="description">
                                Your BunnyCDN Pull Zone URL (without trailing slash).<br>
                                Leave empty to disable CDN.
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
                                Enable tracking
                            </label>
                            <p class="description">
                                Sends events to Umami when songs are played, completed, or downloaded.<br>
                                Umami must already be integrated on your site.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_url_protection">🔒 URL Protection</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sap_url_protection" 
                                       name="sap_url_protection" 
                                       value="1"
                                       <?php checked(get_option('sap_url_protection', false)); ?> />
                                Obfuscate audio URLs
                            </label>
                            <p class="description">
                                Hides real file URLs and uses time-limited tokens.<br>
                                <strong>Note:</strong> Does not work with CDN! When protection is enabled, CDN will be bypassed.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_cover_click_play">🖼️ Cover Click</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sap_cover_click_play" 
                                       name="sap_cover_click_play" 
                                       value="1"
                                       <?php checked(get_option('sap_cover_click_play', true)); ?> />
                                Start playback on cover click
                            </label>
                            <p class="description">
                                When enabled, clicking the cover image starts the first track.<br>
                                Disable this to prevent accidental playback on mobile devices.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label>🎵 Visualizer</label>
                        </th>
                        <td>
                            <?php $viz_type = get_option('sap_visualizer_type', 'bars'); ?>
                            <fieldset>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="bars" <?php checked($viz_type, 'bars'); ?> />
                                    Bars (Classic frequency bars)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="mirror" <?php checked($viz_type, 'mirror'); ?> />
                                    Mirror Bars (Top & bottom reflection)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="circular" <?php checked($viz_type, 'circular'); ?> />
                                    Circular (Radial around center)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="oscilloscope" <?php checked($viz_type, 'oscilloscope'); ?> />
                                    Oscilloscope (Waveform line)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="dots" <?php checked($viz_type, 'dots'); ?> />
                                    Dots (Dancing dots)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="wave" <?php checked($viz_type, 'wave'); ?> />
                                    Wave (Filled waveform)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="pulse" <?php checked($viz_type, 'pulse'); ?> />
                                    Pulse (Pulsing circle)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="butterfly" <?php checked($viz_type, 'butterfly'); ?> />
                                    Butterfly (Symmetrical wings)
                                </label>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="sap_visualizer_type" value="off" <?php checked($viz_type, 'off'); ?> />
                                    Off (No visualizer)
                                </label>
                            </fieldset>
                            <p class="description">
                                Choose the default visualization. Users can cycle through all options by double-clicking the cover or pressing V.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <hr>
            <h2>Guide: Setting up BunnyCDN</h2>
            <ol>
                <li>Go to <a href="https://bunny.net" target="_blank">bunny.net</a> and create an account</li>
                <li>Create a new <strong>Pull Zone</strong></li>
                <li>Enter your website URL as <strong>Origin URL</strong>: <code><?php echo home_url(); ?></code></li>
                <li>Copy the Pull Zone URL (e.g. <code>https://yourname.b-cdn.net</code>) and enter it above</li>
            </ol>
            
            <h3>⚠️ Important: CORS Headers for Audio Files</h3>
            <p>For audio files to play from CDN, CORS headers must be configured:</p>
            <ol>
                <li>Go to your Pull Zone → <strong>Headers</strong></li>
                <li>Add a new header:
                    <ul>
                        <li><strong>Header Name:</strong> <code>Access-Control-Allow-Origin</code></li>
                        <li><strong>Header Value:</strong> <code>*</code> (or <code><?php echo home_url(); ?></code>)</li>
                    </ul>
                </li>
                <li>Save and clear cache</li>
            </ol>
            <p><em>Without CORS headers, audio files will be blocked by the browser!</em></p>
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
        
        // Inject active theme CSS
        $theme_manager = SAP_Theme_Manager::get_instance();
        $active_theme = $theme_manager->get_active_theme();
        $theme_css = $theme_manager->generate_theme_css($active_theme);
        wp_add_inline_style('simple-audio-player', $theme_css);

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
            'coverClickPlay' => (bool) get_option('sap_cover_click_play', true),
            'visualizerType' => get_option('sap_visualizer_type', 'bars'),
        ));
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
     * Custom Post Type for Playlists
     */
    public function register_post_type() {
        register_post_type('sap_playlist', array(
            'labels' => array(
                'name' => __('Playlists', 'simple-audio-player'),
                'singular_name' => __('Playlist', 'simple-audio-player'),
                'add_new' => __('Add New', 'simple-audio-player'),
                'add_new_item' => __('Add New Playlist', 'simple-audio-player'),
                'edit_item' => __('Edit Playlist', 'simple-audio-player'),
                'view_item' => __('View Playlist', 'simple-audio-player'),
                'all_items' => __('All Playlists', 'simple-audio-player'),
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
                    <button type="button" class="button sap-select-cover" title="Select cover">🖼️</button>
                    <input type="text" name="sap_tracks[<?php echo $index; ?>][title]" 
                           class="sap-track-title" placeholder="Title" 
                           value="<?php echo esc_attr($track['title'] ?? ''); ?>" />
                    <input type="text" name="sap_tracks[<?php echo $index; ?>][artist]" 
                           class="sap-track-artist" placeholder="Artist" 
                           value="<?php echo esc_attr($track['artist'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo $index; ?>][url]" 
                           class="sap-track-url" value="<?php echo esc_url($track['url'] ?? ''); ?>" />
                    <input type="hidden" name="sap_tracks[<?php echo $index; ?>][attachment_id]" 
                           class="sap-track-id" value="<?php echo esc_attr($track['attachment_id'] ?? ''); ?>" />
                    <span class="sap-track-filename"><?php echo esc_html(basename($track['url'] ?? 'No file')); ?></span>
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
            <button type="button" class="button button-primary" id="sap-add-track">+ Add Track</button>
            <button type="button" class="button" id="sap-bulk-add">📁 Bulk add from Media Library</button>
        </p>
        
        <!-- Embed Shortcode Info -->
        <div class="sap-embed-info">
            <label>Embed Shortcode:</label>
            <div class="sap-embed-codes">
                <div class="sap-embed-code">
                    <span class="sap-embed-label">Standard:</span>
                    <code class="sap-shortcode" onclick="this.select(); document.execCommand('copy');" title="Click to copy">[simple_player id="<?php echo $post->ID; ?>"]</code>
                </div>
                <div class="sap-embed-code">
                    <span class="sap-embed-label">Wide Layout:</span>
                    <code class="sap-shortcode" onclick="this.select(); document.execCommand('copy');" title="Click to copy">[simple_player id="<?php echo $post->ID; ?>" layout="wide"]</code>
                </div>
                <?php if ($post->post_status === 'publish') : ?>
                <div class="sap-embed-code">
                    <span class="sap-embed-label">iFrame (external):</span>
                    <code class="sap-shortcode sap-iframe-code" onclick="this.select(); document.execCommand('copy');" title="Click to copy">&lt;iframe src="<?php echo esc_url(add_query_arg('embed', '1', get_permalink($post->ID))); ?>" width="100%" height="400" frameborder="0" allow="autoplay"&gt;&lt;/iframe&gt;</code>
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
                   onclick="this.select(); document.execCommand('copy'); alert('Shortcode copied!');"
                   style="width:100%; background:#f0f0f0; cursor:pointer;" />
        </p>
        
        <p>
            <label><strong>Shortcode (Wide Layout):</strong></label>
            <input type="text" 
                   value='[simple_player id="<?php echo $post->ID; ?>" layout="wide"]' 
                   readonly 
                   onclick="this.select(); document.execCommand('copy'); alert('Shortcode copied!');"
                   style="width:100%; background:#f0f0f0; cursor:pointer;" />
        </p>
        
        <p>
            <label><strong>iFrame Embed:</strong></label>
            <textarea readonly 
                      onclick="this.select(); document.execCommand('copy'); alert('Embed code copied!');"
                      style="width:100%; height:60px; background:#f0f0f0; cursor:pointer; font-size:11px;">&lt;iframe src="<?php echo esc_url(add_query_arg('embed', '1', get_permalink($post->ID))); ?>" width="100%" height="400" frameborder="0" allow="autoplay"&gt;&lt;/iframe&gt;</textarea>
            <span class="description">For external websites</span>
        </p>
        <?php else : ?>
        <p class="description" style="margin-top:15px;"><em>Embed codes will be shown after publishing.</em></p>
        <?php endif; ?>
        
        <?php
    }

    /**
     * Save Meta
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

        // Save tracks
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

        // Save streaming links
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
            $track_cover = !empty($track['cover_url']) ? esc_url_raw($track['cover_url']) : $cover;
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
        
        // Add global streaming links if available (validated URLs only)
        $spotify = get_post_meta($post_id, '_sap_spotify', true);
        $apple = get_post_meta($post_id, '_sap_apple', true);
        $amazon = get_post_meta($post_id, '_sap_amazon', true);
        
        $same_as = array();
        if ($spotify && filter_var($spotify, FILTER_VALIDATE_URL)) {
            $same_as[] = esc_url_raw($spotify);
        }
        if ($apple && filter_var($apple, FILTER_VALIDATE_URL)) {
            $same_as[] = esc_url_raw($apple);
        }
        if ($amazon && filter_var($amazon, FILTER_VALIDATE_URL)) {
            $same_as[] = esc_url_raw($amazon);
        }
        if (!empty($same_as)) {
            $schema['sameAs'] = $same_as;
        }
        
        // wp_json_encode handles escaping for JSON context
        return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
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
            return '<p>' . __('No playlist ID specified.', 'simple-audio-player') . '</p>';
        }

        $tracks = get_post_meta($post_id, '_sap_tracks', true);
        if (empty($tracks)) {
            return '<p>' . __('No tracks found.', 'simple-audio-player') . '</p>';
        }
        
        // Apply URL protection or CDN URLs to audio
        $url_protection = get_option('sap_url_protection', false);
        
        foreach ($tracks as &$track) {
            if (!empty($track['url'])) {
                if ($url_protection && !empty($track['attachment_id'])) {
                    // Use obfuscated streaming URL (bypasses CDN)
                    $track['url'] = self::get_stream_url($track['attachment_id']);
                } else {
                    // Use CDN URL if configured
                    $track['url'] = self::cdn_url($track['url']);
                }
            }
            if (!empty($track['cover_url'])) {
                $track['cover_url'] = self::cdn_url($track['cover_url']);
            }
            // Add waveform data if available
            if (!empty($track['attachment_id'])) {
                $waveform = SAP_Waveform_Manager::get_waveform($track['attachment_id']);
                if ($waveform) {
                    $track['waveform'] = $waveform;
                }
            }
        }
        unset($track);

        $cover = self::cdn_url(get_the_post_thumbnail_url($post_id, 'medium'));
        $title = get_the_title($post_id);
        $spotify = get_post_meta($post_id, '_sap_spotify', true);
        $apple = get_post_meta($post_id, '_sap_apple', true);
        $amazon = get_post_meta($post_id, '_sap_amazon', true);

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
        
        // Generate JSON-LD Schema for SEO
        $schema = $this->generate_schema_markup($post_id, $tracks, $title, $cover, $total_seconds);
        ?>
        <?php echo $schema; ?>
        <?php 
        $layout = strtolower(trim($atts['layout']));
        $is_wide = ($layout === 'wide');
        $layout_class = $is_wide ? ' sap-wide' : '';
        
        // Inline styles for wide layout (cache-proof)
        // Note: No !important here so CSS media queries can override on mobile
        $wide_style = $is_wide ? 'display:grid;grid-template-columns:auto 1fr;max-width:100%;width:100%;' : '';
        ?>
        <div class="sap-player<?php echo esc_attr($layout_class); ?>" <?php if($wide_style) echo 'style="'.$wide_style.'"'; ?> data-playlist='<?php echo esc_attr(json_encode($tracks)); ?>' data-playlist-id='<?php echo esc_attr($post_id); ?>' data-default-cover='<?php echo esc_url($cover); ?>' role="region" aria-label="Audio Player">
            
            <!-- Cover Carousel -->
            <div class="sap-cover-carousel" <?php if($is_wide) echo 'style="height:100%;flex-shrink:0;"'; ?>>
                <div class="sap-cover-track">
                    <?php foreach ($tracks as $i => $t) : 
                        $track_cover = !empty($t['cover_url']) ? $t['cover_url'] : $cover;
                        if ($track_cover) :
                    ?>
                        <div class="sap-cover-slide <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>">
                            <img src="<?php echo esc_url($track_cover); ?>" alt="<?php echo esc_attr($t['title']); ?>" loading="eager" decoding="async" />
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
                    <div class="sap-now-playing">Select a track</div>
                    <div class="sap-artist"></div>
                    <div class="sap-meta">
                        <span><?php echo count($tracks); ?> Tracks</span>
                        <span class="sap-meta-divider"></span>
                        <span><?php echo esc_html($total_duration); ?></span>
                    </div>
                </div>

                <!-- Progress with Waveform -->
                <div class="sap-progress-section" role="group" aria-label="Playback progress">
                    <div class="sap-waveform-container" role="slider" aria-label="Time position" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <canvas class="sap-waveform" aria-hidden="true"></canvas>
                        <div class="sap-progress">
                            <div class="sap-progress-bar"></div>
                        </div>
                    </div>
                    <div class="sap-time-row">
                        <span class="sap-current" aria-live="off">0:00</span>
                        <span class="sap-duration">0:00</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="sap-controls" role="group" aria-label="Audio controls">
                    <button class="sap-btn sap-btn-small sap-shuffle" title="Shuffle" aria-label="Shuffle playback" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
                    </button>
                    <button class="sap-btn sap-prev" title="Previous" aria-label="Previous track">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                    </button>
                    <button class="sap-btn sap-play" title="Play/Pause" aria-label="Play or pause">
                        <svg class="sap-icon-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="sap-icon-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                    </button>
                    <button class="sap-btn sap-next" title="Next" aria-label="Next track">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                    </button>
                    <button class="sap-btn sap-btn-small sap-share" title="Share" aria-label="Share this track">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>
                    </button>
                    <div class="sap-more-wrapper">
                        <button class="sap-btn sap-btn-small sap-more-btn" title="More options" aria-label="More options" aria-expanded="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                        </button>
                        <div class="sap-more-menu">
                            <button type="button" class="sap-more-item sap-download" data-action="download">
                                <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                <span>Download</span>
                            </button>
                            <button type="button" class="sap-more-item sap-repeat" data-action="repeat" data-mode="off">
                                <svg viewBox="0 0 24 24"><path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/></svg>
                                <span>Repeat: Off</span>
                            </button>
                            <button type="button" class="sap-more-item sap-speed" data-action="speed">
                                <svg viewBox="0 0 24 24"><path d="M20.38 8.57l-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44z"/><path d="M10.59 15.41a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"/></svg>
                                <span class="sap-speed-label">Speed: 1x</span>
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
                        </div>
                    </div>
                    <div class="sap-volume-wrapper">
                        <button class="sap-btn sap-btn-small sap-volume-btn" title="Volume" aria-label="Volume control">
                            <svg class="sap-icon-volume-high" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
                            <svg class="sap-icon-volume-low" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.5 12c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM5 9v6h4l5 5V4L9 9H5z"/></svg>
                            <svg class="sap-icon-volume-mute" viewBox="0 0 24 24" aria-hidden="true"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/></svg>
                        </button>
                        <div class="sap-volume-slider" role="slider" aria-label="Volume" aria-valuemin="0" aria-valuemax="100" aria-valuenow="70">
                            <div class="sap-volume-track">
                                <div class="sap-volume-fill"></div>
                                <div class="sap-volume-handle"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Playlist -->
                <ul class="sap-playlist" role="listbox" aria-label="Playlist">
                    <?php foreach ($tracks as $index => $track) : ?>
                        <li class="sap-track" role="option" aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>" tabindex="0" data-index="<?php echo $index; ?>" data-url="<?php echo esc_url($track['url']); ?>" data-downloadable="<?php echo !empty($track['downloadable']) ? '1' : '0'; ?>">
                            <span class="sap-track-num" aria-hidden="true"><span><?php echo $index + 1; ?></span></span>
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

// Initialize plugin
Simple_Audio_Player::get_instance();
