<?php
/**
 * Theme Manager: custom colour schemes stored in a custom table
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

/**
 * Theme Manager Class
 */
class SleekAudio_Theme_Manager {
    
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
            'sap-btn-bg' => '#162230',
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
            'sap-transition' => 'cubic-bezier(0.4, 0, 0.2, 1)',
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
        
        // Ensure table exists (important after plugin rename or manual installation)
        $this->maybe_create_table();
        
        add_action('admin_menu', array($this, 'add_theme_menu'));
        add_action('admin_init', array($this, 'register_theme_settings'));
        add_action('wp_ajax_sap_save_theme', array($this, 'ajax_save_theme'));
        add_action('wp_ajax_sap_delete_theme', array($this, 'ajax_delete_theme'));
        add_action('wp_ajax_sap_get_theme', array($this, 'ajax_get_theme'));
        add_action('wp_ajax_sap_set_active_theme', array($this, 'ajax_set_active_theme'));
    }
    
    /**
     * Check if table exists and create if not
     */
    private function maybe_create_table() {
        global $wpdb;
        
        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time schema existence check for the plugin's custom table; not a data query
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name)) === $this->table_name;
        
        if (!$table_exists) {
            self::create_table();
        }
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
        
        try {
            dbDelta($sql);
        } catch (Exception $e) {
            sleekaudio_log('Failed to create themes table', $e->getMessage());
            return false;
        }
        
        // Insert default theme if not exists
        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-time activation seed of the plugin's custom table; table name from $wpdb->prefix, escaped with esc_sql(); %i requires WP 6.2+ (plugin supports 5.0+)
            $existing = $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($table_name) . "` WHERE is_default = 1");
            if (!$existing) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- one-time activation seed of the plugin's custom table
                $result = $wpdb->insert($table_name, array(
                    'name' => 'Standard',
                    'colors' => wp_json_encode(self::$default_theme['colors']),
                    'settings' => wp_json_encode(self::$default_theme['settings']),
                    'is_default' => 1,
                ));
                if ($result === false) {
                    sleekaudio_log('Failed to insert default theme', $wpdb->last_error);
                }
            }
        } catch (Exception $e) {
            sleekaudio_log('Error checking/inserting default theme', $e->getMessage());
        }
        
        return true;
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
     * Clear theme caches (call after any write to the themes table).
     * Deletes individual keys because wp_cache_flush_group() needs WP 6.1+.
     */
    private function flush_theme_cache($id = 0) {
        wp_cache_delete('all_themes', 'sleekaudio_themes');
        wp_cache_delete('active_theme', 'sleekaudio_themes');
        if ($id) {
            wp_cache_delete('theme_' . absint($id), 'sleekaudio_themes');
        }
    }

    /**
     * Get all themes
     */
    public function get_all_themes() {
        global $wpdb;

        $cached = wp_cache_get('all_themes', 'sleekaudio_themes');
        if (false !== $cached) {
            return $cached;
        }

        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table (no WP API); result cached via wp_cache below; table name from $wpdb->prefix, escaped with esc_sql(); %i requires WP 6.2+ (plugin supports 5.0+)
            $results = $wpdb->get_results(
                "SELECT * FROM `" . esc_sql($this->table_name) . "` ORDER BY is_default DESC, name ASC",
                ARRAY_A
            );

            if ($results === null) {
                sleekaudio_log('Failed to get themes', $wpdb->last_error);
                return array();
            }

            foreach ($results as &$theme) {
                $theme['colors'] = json_decode($theme['colors'], true) ?: array();
                $theme['settings'] = json_decode($theme['settings'], true) ?: array();
            }
            unset($theme);

            wp_cache_set('all_themes', $results, 'sleekaudio_themes', HOUR_IN_SECONDS);
            return $results;
        } catch (Exception $e) {
            sleekaudio_log('Exception getting themes', $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get theme by ID
     */
    public function get_theme($id) {
        global $wpdb;
        
        if (!$id || !is_numeric($id)) {
            return null;
        }

        $cached = wp_cache_get('theme_' . absint($id), 'sleekaudio_themes');
        if (false !== $cached) {
            return $cached;
        }

        try {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table (no WP API); result cached via wp_cache below; table name from $wpdb->prefix, escaped with esc_sql(); value prepared with %d
            $theme = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `" . esc_sql($this->table_name) . "` WHERE id = %d", absint($id)),
                ARRAY_A
            );

            if ($theme) {
                $theme['colors'] = json_decode($theme['colors'], true) ?: array();
                $theme['settings'] = json_decode($theme['settings'], true) ?: array();
                wp_cache_set('theme_' . absint($id), $theme, 'sleekaudio_themes', HOUR_IN_SECONDS);
            }

            return $theme;
        } catch (Exception $e) {
            sleekaudio_log('Exception getting theme', $e->getMessage());
            return null;
        }
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

        $cached = wp_cache_get('active_theme', 'sleekaudio_themes');
        if (false !== $cached) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table (no WP API); result cached via wp_cache below; table name from $wpdb->prefix, escaped with esc_sql(); %i requires WP 6.2+ (plugin supports 5.0+)
        $theme = $wpdb->get_row(
            "SELECT * FROM `" . esc_sql($this->table_name) . "` WHERE is_default = 1",
            ARRAY_A
        );

        if ($theme) {
            $theme['colors'] = json_decode($theme['colors'], true);
            $theme['settings'] = json_decode($theme['settings'], true);
            wp_cache_set('active_theme', $theme, 'sleekaudio_themes', HOUR_IN_SECONDS);
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
            'colors' => wp_json_encode($data['colors']),
            'settings' => wp_json_encode($data['settings']),
        );
        
        if (!empty($data['id'])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table write (no WP API); caches invalidated below
            $wpdb->update(
                $this->table_name,
                $theme_data,
                array('id' => absint($data['id']))
            );
            $this->flush_theme_cache(absint($data['id']));
            return absint($data['id']);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table write (no WP API); caches invalidated below
            $wpdb->insert($this->table_name, $theme_data);
            $this->flush_theme_cache();
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

        $this->flush_theme_cache($id);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table write (no WP API); caches invalidated above
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
    
    /**
     * Reset theme to default values
     */
    public function reset_to_default($id) {
        global $wpdb;

        $this->flush_theme_cache($id);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table write (no WP API); caches invalidated above
        return $wpdb->update(
            $this->table_name,
            array(
                'colors' => wp_json_encode(self::$default_theme['colors']),
                'settings' => wp_json_encode(self::$default_theme['settings']),
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
            // Sanitize CSS variable name and value to prevent injection
            $safe_var = preg_replace('/[^a-zA-Z0-9-]/', '', $var);
            $safe_value = wp_strip_all_tags($value);
            // Additional CSS value sanitization - remove dangerous characters
            $safe_value = preg_replace('/[;<>{}]/', '', $safe_value);
            $css .= "    --{$safe_var}: {$safe_value} !important;\n";
        }
        
        foreach ($settings as $var => $value) {
            // Sanitize CSS variable name and value to prevent injection
            $safe_var = preg_replace('/[^a-zA-Z0-9-]/', '', $var);
            $safe_value = wp_strip_all_tags($value);
            // Additional CSS value sanitization - remove dangerous characters
            $safe_value = preg_replace('/[;<>{}]/', '', $safe_value);
            $css .= "    --{$safe_var}: {$safe_value} !important;\n";
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
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'colors' => array(),
            'settings' => array(),
        );
        
        // Parse colors - support both array and JSON string
        if (!empty($_POST['colors'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; decoded below and every key/value individually sanitized (sanitize_key / sanitize_text_field)
            $colors_raw = wp_unslash($_POST['colors']);
            if (is_string($colors_raw)) {
                $colors = json_decode($colors_raw, true);
            } else {
                $colors = $colors_raw;
            }
            if (is_array($colors)) {
                foreach ($colors as $key => $value) {
                    $data['colors'][sanitize_key($key)] = sanitize_text_field($value);
                }
            }
        }
        
        // Parse settings - support both array and JSON string
        if (!empty($_POST['settings'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; decoded below and every key/value individually sanitized (sanitize_key / sanitize_text_field)
            $settings_raw = wp_unslash($_POST['settings']);
            if (is_string($settings_raw)) {
                $settings = json_decode($settings_raw, true);
            } else {
                $settings = $settings_raw;
            }
            if (is_array($settings)) {
                foreach ($settings as $key => $value) {
                    $data['settings'][sanitize_key($key)] = sanitize_text_field($value);
                }
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
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
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
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
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
        
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
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
                'title' => '🎨 Backgrounds',
                'colors' => array(
                    'sap-bg' => 'Player Background (outer)',
                    'sap-card' => 'Player Background (inner)',
                    'sap-border' => 'Border Color',
                )
            ),
            'buttons' => array(
                'title' => '🔘 Buttons',
                'colors' => array(
                    'sap-btn-bg' => 'Button Background (pressed)',
                    'sap-accent' => 'Active Toggle Color (Shuffle, Repeat)',
                    'sap-accent-light' => 'Active Toggle Hover',
                )
            ),
            'playlist' => array(
                'title' => '📋 Playlist',
                'colors' => array(
                    'sap-card-hover' => 'Track Hover (mouse over)',
                    'sap-track-active' => 'Current Track (background)',
                )
            ),
            'progress' => array(
                'title' => '🎵 Progress & Visualizer',
                'colors' => array(
                    'sap-visualizer' => 'Progress Bar & Visualizer',
                    'sap-waveform-inactive' => 'Progress Bar (inactive)',
                    'sap-accent-glow' => 'Glow Effect (shadow)',
                    'sap-accent-glow-strong' => 'Glow Effect (strong)',
                    'sap-accent-glow-soft' => 'Glow Effect (soft)',
                )
            ),
            'text' => array(
                'title' => '📝 Text',
                'colors' => array(
                    'sap-white' => 'Title & Primary Text',
                    'sap-gray-100' => 'Track Title in Playlist',
                    'sap-gray-200' => 'Button Icons & Subtitle',
                    'sap-gray-300' => 'Artist & Time Display',
                    'sap-gray-400' => 'Track Numbers & Dimmed Text',
                )
            ),
            'effects' => array(
                'title' => '💫 Effects',
                'colors' => array(
                    'sap-blue-tint' => 'Blue Overlay (Cover)',
                )
            ),
        );
        
        $settings_labels = array(
            'sap-radius' => 'Border Radius',
            'sap-radius-sm' => 'Border Radius (Small)',
            'sap-transition' => 'Animation Timing',
        );
        ?>
        <style>
            :root {
                --tm-accent: #6366f1;
                --tm-accent-light: #818cf8;
                --tm-accent-bg: #eef2ff;
                --tm-bg: #f8fafc;
                --tm-card: #ffffff;
                --tm-border: #e2e8f0;
                --tm-text: #1e293b;
                --tm-text-secondary: #64748b;
                --tm-muted: #94a3b8;
                --tm-success: #22c55e;
                --tm-success-bg: #f0fdf4;
                --tm-danger: #ef4444;
                --tm-danger-bg: #fef2f2;
                --tm-warning: #f59e0b;
                --tm-warning-bg: #fef3c7;
                --tm-radius: 12px;
                --tm-radius-sm: 8px;
            }
            .sap-theme-manager {
                max-width: 1600px;
                margin: 20px auto;
                padding: 0 20px;
            }
            .sap-theme-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 1px solid var(--tm-border);
            }
            .sap-theme-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                color: var(--tm-text);
                letter-spacing: -0.02em;
            }
            .sap-theme-header-actions {
                display: flex;
                gap: 10px;
            }
            .sap-theme-grid {
                display: grid;
                grid-template-columns: 340px 1fr;
                gap: 30px;
            }
            .sap-themes-list {
                background: var(--tm-card);
                border: 1px solid var(--tm-border);
                border-radius: var(--tm-radius);
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            .sap-themes-list-header {
                padding: 18px 20px;
                background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
                border-bottom: 1px solid var(--tm-border);
                font-weight: 600;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--tm-text);
            }
            .sap-theme-item {
                padding: 16px 20px;
                border-bottom: 1px solid var(--tm-border);
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 14px;
            }
            .sap-theme-item:last-child {
                border-bottom: none;
            }
            .sap-theme-item:hover {
                background: var(--tm-accent-bg);
            }
            .sap-theme-item.active {
                background: var(--tm-accent-bg);
                border-left: 4px solid var(--tm-accent);
            }
            .sap-theme-item.editing {
                background: var(--tm-warning-bg);
                border-left: 4px solid var(--tm-warning);
            }
            .sap-theme-swatch {
                width: 44px;
                height: 44px;
                border-radius: var(--tm-radius-sm);
                flex-shrink: 0;
                border: 2px solid var(--tm-border);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .sap-theme-info {
                flex: 1;
            }
            .sap-theme-name {
                font-weight: 600;
                margin-bottom: 2px;
                color: var(--tm-text);
            }
            .sap-theme-badge {
                display: inline-block;
                font-size: 10px;
                padding: 3px 8px;
                border-radius: 12px;
                margin-left: 8px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .sap-badge-default {
                background: var(--tm-border);
                color: var(--tm-text-secondary);
            }
            .sap-badge-active {
                background: var(--tm-success-bg);
                color: var(--tm-success);
            }
            .sap-theme-editor {
                background: var(--tm-card);
                border: 1px solid var(--tm-border);
                border-radius: var(--tm-radius);
                padding: 28px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }
            .sap-editor-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 28px;
                padding-bottom: 20px;
                border-bottom: 1px solid var(--tm-border);
            }
            .sap-editor-title {
                font-size: 20px;
                font-weight: 700;
                color: var(--tm-text);
            }
            .sap-editor-actions {
                display: flex;
                gap: 10px;
            }
            .sap-color-section {
                margin-bottom: 28px;
            }
            .sap-color-section h3 {
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 16px;
                color: var(--tm-text);
                padding-bottom: 10px;
                border-bottom: 1px solid var(--tm-border);
            }
            .sap-color-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 14px;
            }
            .sap-color-field {
                display: flex;
                align-items: center;
                gap: 10px;
                background: var(--tm-bg);
                padding: 10px 14px;
                border-radius: var(--tm-radius-sm);
                border: 1px solid var(--tm-border);
                transition: all 0.2s ease;
            }
            .sap-color-field:hover {
                border-color: var(--tm-accent);
                box-shadow: 0 0 0 3px var(--tm-accent-bg);
            }
            .sap-color-field label {
                width: 110px;
                flex-shrink: 0;
                font-size: 12px;
                color: var(--tm-text-secondary);
                line-height: 1.3;
                font-weight: 500;
            }
            .sap-color-field input[type="color"] {
                width: 40px;
                height: 40px;
                padding: 2px;
                border: 2px solid var(--tm-border);
                border-radius: var(--tm-radius-sm);
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .sap-color-field input[type="color"]:hover {
                border-color: var(--tm-accent);
                transform: scale(1.05);
                cursor: pointer;
                flex-shrink: 0;
            }
            .sap-color-field input[type="text"] {
                flex: 1;
                min-width: 0;
                padding: 10px 12px;
                border: 1px solid var(--tm-border);
                border-radius: var(--tm-radius-sm);
                font-family: 'SF Mono', Monaco, 'Courier New', monospace;
                font-size: 12px;
                color: var(--tm-text);
                background: var(--tm-card);
                transition: all 0.2s ease;
            }
            .sap-color-field input[type="text"]:focus {
                border-color: var(--tm-accent);
                outline: none;
                box-shadow: 0 0 0 3px var(--tm-accent-bg);
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
                margin-bottom: 28px;
            }
            .sap-name-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 10px;
                color: var(--tm-text);
                font-size: 14px;
            }
            .sap-name-field input {
                width: 100%;
                max-width: 320px;
                padding: 12px 16px;
                border: 1px solid var(--tm-border);
                border-radius: var(--tm-radius-sm);
                font-size: 15px;
                color: var(--tm-text);
                background: var(--tm-bg);
                transition: all 0.2s ease;
            }
            .sap-name-field input:focus {
                border-color: var(--tm-accent);
                outline: none;
                box-shadow: 0 0 0 3px var(--tm-accent-bg);
                background: var(--tm-card);
            }
            .sap-btn {
                padding: 11px 22px;
                border-radius: var(--tm-radius-sm);
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                border: none;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .sap-btn:hover {
                transform: translateY(-1px);
            }
            .sap-btn:active {
                transform: translateY(0);
            }
            .sap-btn-primary {
                background: linear-gradient(135deg, var(--tm-accent) 0%, var(--tm-accent-light) 100%);
                color: #fff;
                box-shadow: 0 2px 8px rgba(99, 102, 241, 0.25);
            }
            .sap-btn-primary:hover {
                box-shadow: 0 4px 16px rgba(99, 102, 241, 0.35);
            }
            .sap-btn-secondary {
                background: var(--tm-bg);
                color: var(--tm-text-secondary);
                border: 1px solid var(--tm-border);
            }
            .sap-btn-secondary:hover {
                background: var(--tm-card);
                border-color: var(--tm-accent);
                color: var(--tm-accent);
            }
            .sap-btn-danger {
                background: linear-gradient(135deg, var(--tm-danger) 0%, #f87171 100%);
                color: #fff;
                box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);
            }
            .sap-btn-danger:hover {
                box-shadow: 0 4px 16px rgba(239, 68, 68, 0.35);
            }
            .sap-btn-success {
                background: linear-gradient(135deg, var(--tm-success) 0%, #4ade80 100%);
                color: #fff;
                box-shadow: 0 2px 8px rgba(34, 197, 94, 0.25);
            }
            .sap-btn-success:hover {
                box-shadow: 0 4px 16px rgba(34, 197, 94, 0.35);
            }
            .sap-new-theme-btn {
                width: 100%;
                padding: 18px;
                text-align: center;
                background: var(--tm-bg);
                border: 2px dashed var(--tm-border);
                border-radius: var(--tm-radius);
                color: var(--tm-text-secondary);
                cursor: pointer;
                margin-top: 16px;
                transition: all 0.2s ease;
                font-weight: 600;
                font-size: 14px;
            }
            .sap-new-theme-btn:hover {
                border-color: var(--tm-accent);
                color: var(--tm-accent);
                background: var(--tm-accent-bg);
                transform: translateY(-1px);
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
                <div class="sap-theme-header-actions">
                    <button type="button" class="sap-btn sap-btn-secondary" id="sap-import-theme">📥 Import</button>
                    <button type="button" class="sap-btn sap-btn-secondary" id="sap-export-theme">📤 Export</button>
                    <input type="file" id="sap-import-file" accept=".json" style="display:none;">
                </div>
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
                            <div class="sap-theme-item <?php echo esc_attr($is_active ? 'active' : ''); ?>" 
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
            var defaultTheme = <?php echo wp_json_encode($default_theme); ?>;
            var currentThemeId = null;
            var nonce = '<?php echo esc_attr( wp_create_nonce('sap_theme_nonce') ); ?>';
            
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
                $('.sap-theme-item.active').first().trigger('click');
                if (!$('.sap-theme-item.editing').length) {
                    $('.sap-theme-item').first().trigger('click');
                }
            }
            
            // ===== Export Theme =====
            $('#sap-export-theme').on('click', function() {
                var name = $('#sap-theme-name').val() || 'theme';
                var themeData = {
                    name: name,
                    version: '1.0',
                    exported: new Date().toISOString(),
                    colors: {},
                    settings: {}
                };
                
                // Collect colors
                $('.sap-color-input').each(function() {
                    themeData.colors[$(this).data('var')] = $(this).val();
                });
                
                // Collect settings
                $('.sap-setting-input').each(function() {
                    themeData.settings[$(this).data('var')] = $(this).val();
                });
                
                // Create download
                var json = JSON.stringify(themeData, null, 2);
                var blob = new Blob([json], {type: 'application/json'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'sap-theme-' + name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
            
            // ===== Import Theme =====
            $('#sap-import-theme').on('click', function() {
                $('#sap-import-file').trigger('click');
            });
            
            $('#sap-import-file').on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        var themeData = JSON.parse(e.target.result);
                        
                        // Validate structure
                        if (!themeData.colors || !themeData.settings) {
                            alert('Invalid theme file: missing colors or settings.');
                            return;
                        }
                        
                        // Prepare theme name
                        var themeName = themeData.name ? themeData.name + ' (Imported)' : 'Imported Theme';
                        
                        // Auto-save the imported theme (send as JSON strings for PHP compatibility)
                        var saveData = {
                            action: 'sap_save_theme',
                            nonce: nonce,
                            id: '', // New theme
                            name: themeName,
                            colors: JSON.stringify(themeData.colors),
                            settings: JSON.stringify(themeData.settings)
                        };
                        
                        $.post(ajaxurl, saveData, function(response) {
                            if (response.success) {
                                alert('Theme "' + themeName + '" imported and saved!');
                                location.reload();
                            } else {
                                alert('Error saving imported theme: ' + response.data);
                            }
                        });
                    } catch (err) {
                        alert('Error parsing theme file: ' + err.message);
                    }
                };
                reader.readAsText(file);
                
                // Reset file input
                $(this).val('');
            });
        });
        </script>
        <?php
    }
}
